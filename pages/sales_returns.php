<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination for return list
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

$sales_id = (int)($_GET['sales_id'] ?? 0);

// ---------- LOAD SALES INVOICE WHEN SELECTED ----------
$invoice = null;
$items   = [];

if ($sales_id > 0) {
    // Header
    $stmt = $pdo->prepare("SELECT si.*, c.name AS customer_name 
                           FROM sales_invoices si
                           JOIN customers c ON c.id=si.customer_id
                           WHERE si.id=?");
    $stmt->execute([$sales_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {
        // Items
        $stmt2 = $pdo->prepare("SELECT it.*, p.item_name, p.sku, p.unit
                                FROM sales_items it
                                JOIN products p ON p.id = it.product_id
                                WHERE it.sales_id=?
                                ORDER BY it.id ASC");
        $stmt2->execute([$sales_id]);
        $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // For each item compute already returned qty
        $retStmt = $pdo->prepare("
            SELECT sri.product_id, COALESCE(SUM(sri.qty),0) AS returned_qty
            FROM sales_return_items sri
            JOIN sales_returns sr ON sr.id = sri.return_id
            WHERE sr.sales_id = ?
            GROUP BY sri.product_id
        ");
        $retStmt->execute([$sales_id]);
        $returnedMap = [];
        foreach ($retStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $returnedMap[$r['product_id']] = (float)$r['returned_qty'];
        }

        foreach ($items as &$row) {
            $soldQty = (float)$row['qty'];
            $already = $returnedMap[$row['product_id']] ?? 0;
            $row['returned_qty']   = $already;
            $row['max_returnable'] = max(0, $soldQty - $already);
        }
        unset($row);
    } else {
        $errors[] = "Sales invoice not found.";
        $sales_id = 0;
    }
}

// ---------- SAVE SALES RETURN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sales_id'])) {
    $sales_id  = (int)($_POST['sales_id'] ?? 0);
    $return_date = trim($_POST['return_date'] ?? '');

    if ($sales_id <= 0) $errors[] = "Invalid sales reference.";
    if ($return_date === '') $errors[] = "Return date required.";

    $retDateObj = null;
    if ($return_date !== '') {
        $retDateObj = date_create_from_format('Y-m-d', $return_date);
        if (!$retDateObj) $errors[] = "Invalid return date.";
    }

    $prod_ids   = $_POST['product_id'] ?? [];
    $ret_qtys   = $_POST['return_qty'] ?? [];
    $rates      = $_POST['rate'] ?? [];
    $taxes      = $_POST['tax_percent'] ?? [];
    $sold_qtys  = $_POST['sold_qty'] ?? [];
    $line_discs = $_POST['line_discount'] ?? [];

    $lines = [];
    $lineCount = max(count($prod_ids), count($ret_qtys));
    for ($i=0; $i<$lineCount; $i++) {
        $pid       = (int)($prod_ids[$i] ?? 0);
        $rq        = (float)($ret_qtys[$i] ?? 0);
        $rate      = (float)($rates[$i] ?? 0);
        $taxp      = (float)($taxes[$i] ?? 0);
        $soldQty   = (float)($sold_qtys[$i] ?? 0);
        $lineDisc  = (float)($line_discs[$i] ?? 0); // full line discount from original

        if ($pid > 0 && $rq > 0) {
            if ($rq <= 0) {
                $errors[] = "Return quantity must be > 0.";
                continue;
            }
            if ($rate <= 0) {
                $errors[] = "Rate must be > 0 for returned item.";
            }
            // Check max returnable from DB to avoid cheating
            $chk = $pdo->prepare("
                SELECT si.qty - COALESCE(SUM(sri.qty),0) AS balance
                FROM sales_items si
                LEFT JOIN sales_return_items sri
                  ON sri.product_id = si.product_id
                 AND sri.return_id IN (
                    SELECT id FROM sales_returns WHERE sales_id = si.sales_id
                 )
                WHERE si.sales_id = :sid AND si.product_id = :pid
                GROUP BY si.product_id
            ");
            $chk->execute(['sid'=>$sales_id,'pid'=>$pid]);
            $balance = (float)$chk->fetchColumn();
            if ($balance <= 0) {
                $errors[] = "No balance quantity left to return for one of the items.";
            } elseif ($rq > $balance + 0.0001) {
                $errors[] = "Return qty cannot exceed remaining balance for one of the items.";
            }

            // Compute discount allocation per unit
            $discPerUnit = 0.0;
            if ($soldQty > 0 && $lineDisc > 0) {
                $discPerUnit = $lineDisc / $soldQty;
            }
            $netPerUnit  = $rate - $discPerUnit;
            if ($netPerUnit < 0) $netPerUnit = 0;
            $netAmount   = $netPerUnit * $rq;
            $taxAmount   = $netAmount * $taxp / 100;

            $lines[] = [
                'product_id'  => $pid,
                'qty'         => $rq,
                'rate'        => $rate,
                'discount'    => $discPerUnit * $rq,
                'tax_percent' => $taxp,
                'net_amount'  => $netAmount,
                'tax_amount'  => $taxAmount,
            ];
        }
    }

    if (!$lines) {
        $errors[] = "Enter at least one item with return quantity.";
    }

    if (!$errors) {
        $total_amount = 0;
        $total_tax    = 0;
        foreach ($lines as $ln) {
            $total_amount += $ln['net_amount'];
            $total_tax    += $ln['tax_amount'];
        }
        $grand_total = $total_amount + $total_tax;

        try {
            $pdo->beginTransaction();

            // Insert return header
            $stmt = $pdo->prepare("INSERT INTO sales_returns
                (sales_id, return_date, total_amount, total_tax, grand_total)
                VALUES (:sid, :rdate, :ta, :tt, :gt)");
            $stmt->execute([
                'sid'   => $sales_id,
                'rdate' => $retDateObj ? $retDateObj->format('Y-m-d') : null,
                'ta'    => $total_amount,
                'tt'    => $total_tax,
                'gt'    => $grand_total,
            ]);
            $returnId = $pdo->lastInsertId();

            // Insert items + stock ledger (qty_in)
            $itemStmt = $pdo->prepare("INSERT INTO sales_return_items
                (return_id, product_id, qty, rate, discount, tax_percent, amount)
                VALUES
                (:rid, :pid, :q, :r, :d, :t, :amt)");

            $ledgerStmtSel = $pdo->prepare("SELECT closing_stock FROM stock_ledger WHERE product_id=? ORDER BY id DESC LIMIT 1");
            $ledgerStmtIns = $pdo->prepare("INSERT INTO stock_ledger
                (product_id, reference_type, reference_id, qty_in, qty_out, closing_stock)
                VALUES (:prid, 'sale_return', :refid, :qin, 0, :cs)");

            foreach ($lines as $ln) {
                $itemStmt->execute([
                    'rid'  => $returnId,
                    'pid'  => $ln['product_id'],
                    'q'    => $ln['qty'],
                    'r'    => $ln['rate'],
                    'd'    => $ln['discount'],
                    't'    => $ln['tax_percent'],
                    'amt'  => $ln['net_amount'],
                ]);

                // stock ledger per product (IN)
                $ledgerStmtSel->execute([$ln['product_id']]);
                $prevClosing = (float)$ledgerStmtSel->fetchColumn();
                $newClosing  = $prevClosing + $ln['qty'];

                $ledgerStmtIns->execute([
                    'prid'  => $ln['product_id'],
                    'refid' => $returnId,
                    'qin'   => $ln['qty'],
                    'cs'    => $newClosing,
                ]);
            }

            $pdo->commit();
            $success = "Sales return saved.";
            // Reset sales_id so form becomes selection again
            $sales_id = 0;
            $invoice = null;
            $items   = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error saving sales return: " . $e->getMessage();
        }
    }
}

// ---------- SALES RETURN LIST ----------
$lsql = "FROM sales_returns sr
         JOIN sales_invoices si ON si.id=sr.sales_id
         JOIN customers c ON c.id=si.customer_id
         WHERE 1";
$params = [];

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $lsql .= " AND (si.invoice_no LIKE :q OR c.name LIKE :q)";
    $params['q'] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $lsql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

$listStmt = $pdo->prepare("SELECT sr.*, si.invoice_no, si.invoice_date, c.name AS customer_name
                           $lsql
                           ORDER BY sr.id DESC
                           LIMIT $limit OFFSET $offset");
$listStmt->execute($params);
$returnRows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- LIST OF RECENT SALES FOR SELECTION ----------
$salesSelectStmt = $pdo->query("
    SELECT si.id, si.invoice_no, si.invoice_date, c.name AS customer_name
    FROM sales_invoices si
    JOIN customers c ON c.id=si.customer_id
    ORDER BY si.id DESC
    LIMIT 20
");
$recentSales = $salesSelectStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-7">

        <div class="p-3 border rounded mb-3 bg-white shadow-sm small">
            <h6 class="mb-2">Select Sales Invoice for Return</h6>
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th width="80">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$recentSales): ?>
                    <tr><td colspan="5" class="text-center py-3">No sales invoices</td></tr>
                <?php else: foreach ($recentSales as $s): ?>
                    <tr>
                        <td><?=$s['id']?></td>
                        <td><?=htmlspecialchars($s['invoice_date'])?></td>
                        <td><?=htmlspecialchars($s['invoice_no'])?></td>
                        <td><?=htmlspecialchars($s['customer_name'])?></td>
                        <td>
                            <a href="index.php?page=sales_returns&sales_id=<?=$s['id']?>"
                               class="btn btn-sm btn-outline-dark">Return</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header py-2 small">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Sales Returns</span>
                    <form method="get" class="d-flex ms-2">
                        <input type="hidden" name="page" value="sales_returns">
                        <input type="text" name="q" class="form-control form-control-sm"
                               placeholder="Search invoice/customer" value="<?=htmlspecialchars($search)?>">
                        <button class="btn btn-sm btn-dark ms-2">Search</button>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 small align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Return Date</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th class="text-end">Grand</th>
                        <th width="80">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$returnRows): ?>
                        <tr><td colspan="6" class="text-center py-3">No returns</td></tr>
                    <?php else: foreach ($returnRows as $r): ?>
                        <tr>
                            <td><?=$r['id']?></td>
                            <td><?=htmlspecialchars($r['return_date'])?></td>
                            <td><?=htmlspecialchars($r['invoice_no'])?></td>
                            <td><?=htmlspecialchars($r['customer_name'])?></td>
                            <td class="text-end"><?=number_format($r['grand_total'],2)?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-dark"
                                   href="index.php?page=sales_return_view&id=<?=$r['id']?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <nav class="mt-2">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <?php for($i=1; $i<=$totalPages; $i++): ?>
                        <li class="page-item <?=$i==$pageNum?'active':''?>">
                            <a class="page-link"
                               href="index.php?page=sales_returns&p=<?=$i?>&q=<?=urlencode($search)?>"><?=$i?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>

    </div>

    <div class="col-md-5">
        <div class="p-3 border rounded bg-white shadow-sm small">
            <h6 class="mb-2">Create Sales Return</h6>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small">
                    <ul class="m-0">
                        <?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2 small"><?=htmlspecialchars($success)?></div>
            <?php endif; ?>

            <?php if (!$invoice): ?>
                <div class="text-muted small">
                    Select a sales invoice from the left list to create a return.
                </div>
            <?php else: ?>

                <form method="post" class="small">
                    <input type="hidden" name="sales_id" value="<?=$invoice['id']?>">

                    <div class="mb-2">
                        <strong>Invoice:</strong> <?=htmlspecialchars($invoice['invoice_no'])?><br>
                        <strong>Date:</strong> <?=htmlspecialchars($invoice['invoice_date'])?><br>
                        <strong>Customer:</strong> <?=htmlspecialchars($invoice['customer_name'])?>
                    </div>

                    <div class="mb-2">
                        <label>Return Date*</label>
                        <input type="date" name="return_date"
                               class="form-control form-control-sm"
                               value="<?=htmlspecialchars($_POST['return_date'] ?? date('Y-m-d'))?>" required>
                    </div>

                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Sold</th>
                                <th class="text-end">Returned</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end">Return Qty</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <?=htmlspecialchars($it['item_name'])?>
                                        <input type="hidden" name="product_id[]" value="<?=$it['product_id']?>">
                                        <input type="hidden" name="rate[]" value="<?=$it['rate']?>">
                                        <input type="hidden" name="tax_percent[]" value="<?=$it['tax_percent']?>">
                                        <input type="hidden" name="sold_qty[]" value="<?=$it['qty']?>">
                                        <input type="hidden" name="line_discount[]" value="<?=$it['discount']?>">
                                    </td>
                                    <td class="text-end"><?=number_format($it['qty'],3)?></td>
                                    <td class="text-end"><?=number_format($it['returned_qty'],3)?></td>
                                    <td class="text-end"><?=number_format($it['max_returnable'],3)?></td>
                                    <td class="text-end">
                                        <input type="number" step="0.001" name="return_qty[]"
                                               class="form-control form-control-sm text-end"
                                               max="<?=$it['max_returnable']?>"
                                               <?= $it['max_returnable'] <= 0 ? 'readonly' : '' ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        <button class="btn btn-dark btn-sm">Save Return</button>
                    </div>
                </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
