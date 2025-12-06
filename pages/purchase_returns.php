<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination for return list
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

$purchase_id = (int)($_GET['purchase_id'] ?? 0);

// ---------- LOAD PURCHASE INVOICE WHEN SELECTED ----------
$invoice = null;
$items   = [];

if ($purchase_id > 0) {
    // Header
    $stmt = $pdo->prepare("SELECT pi.*, s.name AS supplier_name 
                           FROM purchase_invoices pi
                           JOIN suppliers s ON s.id=pi.supplier_id
                           WHERE pi.id=?");
    $stmt->execute([$purchase_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {
        // Items
        $stmt2 = $pdo->prepare("SELECT it.*, p.item_name, p.sku, p.unit
                                FROM purchase_items it
                                JOIN products p ON p.id = it.product_id
                                WHERE it.purchase_id=?
                                ORDER BY it.id ASC");
        $stmt2->execute([$purchase_id]);
        $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Already returned qty per product
        $retStmt = $pdo->prepare("
            SELECT pri.product_id, COALESCE(SUM(pri.qty),0) AS returned_qty
            FROM purchase_return_items pri
            JOIN purchase_returns pr ON pr.id = pri.return_id
            WHERE pr.purchase_id = ?
            GROUP BY pri.product_id
        ");
        $retStmt->execute([$purchase_id]);
        $returnedMap = [];
        foreach ($retStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $returnedMap[$r['product_id']] = (float)$r['returned_qty'];
        }

        foreach ($items as &$row) {
            $pQty   = (float)$row['qty'];
            $already = $returnedMap[$row['product_id']] ?? 0;
            $row['returned_qty']   = $already;
            $row['max_returnable'] = max(0, $pQty - $already);
        }
        unset($row);
    } else {
        $errors[] = "Purchase invoice not found.";
        $purchase_id = 0;
    }
}

// ---------- SAVE PURCHASE RETURN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_id'])) {
    $purchase_id  = (int)($_POST['purchase_id'] ?? 0);
    $return_date  = trim($_POST['return_date'] ?? '');
    $payment_type = ($_POST['payment_type'] ?? 'credit') === 'cash' ? 'cash' : 'credit';

    if ($purchase_id <= 0) $errors[] = "Invalid purchase reference.";
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
    $purch_qtys = $_POST['purch_qty'] ?? [];
    $line_discs = $_POST['line_discount'] ?? [];

    $lines = [];
    $lineCount = max(count($prod_ids), count($ret_qtys));
    for ($i=0; $i<$lineCount; $i++) {
        $pid       = (int)($prod_ids[$i] ?? 0);
        $rq        = (float)($ret_qtys[$i] ?? 0);
        $rate      = (float)($rates[$i] ?? 0);
        $taxp      = (float)($taxes[$i] ?? 0);
        $pQty      = (float)($purch_qtys[$i] ?? 0);
        $lineDisc  = (float)($line_discs[$i] ?? 0);

        if ($pid > 0 && $rq > 0) {
            if ($rq <= 0) {
                $errors[] = "Return quantity must be > 0.";
                continue;
            }
            if ($rate <= 0) {
                $errors[] = "Rate must be > 0 for returned item.";
            }

            // Check max returnable from DB side
            $chk = $pdo->prepare("
                SELECT pi.qty - COALESCE(SUM(pri.qty),0) AS balance
                FROM purchase_items pi
                LEFT JOIN purchase_return_items pri
                  ON pri.product_id = pi.product_id
                 AND pri.return_id IN (
                    SELECT id FROM purchase_returns WHERE purchase_id = pi.purchase_id
                 )
                WHERE pi.purchase_id = :pid AND pi.product_id = :prod
                GROUP BY pi.product_id
            ");
            $chk->execute(['pid'=>$purchase_id,'prod'=>$pid]);
            $balance = (float)$chk->fetchColumn();
            if ($balance <= 0) {
                $errors[] = "No balance quantity left to return for one of the items.";
            } elseif ($rq > $balance + 0.0001) {
                $errors[] = "Return qty cannot exceed remaining balance for one of the items.";
            }

            // discount allocation per unit
            $discPerUnit = 0.0;
            if ($pQty > 0 && $lineDisc > 0) {
                $discPerUnit = $lineDisc / $pQty;
            }
            $netPerUnit = $rate - $discPerUnit;
            if ($netPerUnit < 0) $netPerUnit = 0;
            $netAmount = $netPerUnit * $rq;
            $taxAmount = $netAmount * $taxp / 100;

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

            // header
            $stmt = $pdo->prepare("INSERT INTO purchase_returns
                (purchase_id, return_date, payment_type, total_amount, total_tax, grand_total)
                VALUES (:pid, :rdate, :ptype, :ta, :tt, :gt)");
            $stmt->execute([
                'pid'   => $purchase_id,
                'rdate' => $retDateObj ? $retDateObj->format('Y-m-d') : null,
                'ptype' => $payment_type,
                'ta'    => $total_amount,
                'tt'    => $total_tax,
                'gt'    => $grand_total,
            ]);
            $returnId = $pdo->lastInsertId();

            // items + stock ledger (qty_out)
            $itemStmt = $pdo->prepare("INSERT INTO purchase_return_items
    (return_id, product_id, qty, rate, tax_percent, amount, discount)
    VALUES (:rid, :pid, :q, :r, :t, :amt, :d)");

            $ledgerStmtSel = $pdo->prepare("SELECT closing_stock FROM stock_ledger WHERE product_id=? ORDER BY id DESC LIMIT 1");
            $ledgerStmtIns = $pdo->prepare("INSERT INTO stock_ledger
                (product_id, reference_type, reference_id, qty_in, qty_out, closing_stock)
                VALUES (:prid, 'purchase_return', :refid, 0, :qout, :cs)");

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

                $ledgerStmtSel->execute([$ln['product_id']]);
                $prevClosing = (float)$ledgerStmtSel->fetchColumn();
                $newClosing  = $prevClosing - $ln['qty']; // return reduces stock

                $ledgerStmtIns->execute([
                    'prid'  => $ln['product_id'],
                    'refid' => $returnId,
                    'qout'  => $ln['qty'],
                    'cs'    => $newClosing,
                ]);
            }

            // NOTE: Accounting: PR-CHOOSE
            // - If payment_type='credit' => adjust supplier balance later in journal layer
            // - If 'cash' => adjust cash/bank later

            $pdo->commit();
            $success = "Purchase return saved.";
            $purchase_id = 0;
            $invoice = null;
            $items   = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error saving purchase return: " . $e->getMessage();
        }
    }
}

// ---------- PURCHASE RETURN LIST ----------
$lsql = "FROM purchase_returns pr
         JOIN purchase_invoices pi ON pi.id=pr.purchase_id
         JOIN suppliers s ON s.id=pi.supplier_id
         WHERE 1";
$params = [];

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $lsql .= " AND (pi.invoice_no LIKE :q OR s.name LIKE :q)";
    $params['q'] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $lsql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

$listStmt = $pdo->prepare("SELECT pr.*, pi.invoice_no, pi.invoice_date, s.name AS supplier_name
                           $lsql
                           ORDER BY pr.id DESC
                           LIMIT $limit OFFSET $offset");
$listStmt->execute($params);
$returnRows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- RECENT PURCHASES FOR SELECTION ----------
$purchSelect = $pdo->query("
    SELECT pi.id, pi.invoice_no, pi.invoice_date, s.name AS supplier_name
    FROM purchase_invoices pi
    JOIN suppliers s ON s.id=pi.supplier_id
    ORDER BY pi.id DESC
    LIMIT 20
");
$recentPurchases = $purchSelect->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-7">

        <div class="p-3 border rounded mb-3 bg-white shadow-sm small">
            <h6 class="mb-2">Select Purchase Invoice for Return</h6>
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Invoice</th>
                    <th>Supplier</th>
                    <th width="80">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$recentPurchases): ?>
                    <tr><td colspan="5" class="text-center py-3">No purchase invoices</td></tr>
                <?php else: foreach ($recentPurchases as $p): ?>
                    <tr>
                        <td><?=$p['id']?></td>
                        <td><?=htmlspecialchars($p['invoice_date'])?></td>
                        <td><?=htmlspecialchars($p['invoice_no'])?></td>
                        <td><?=htmlspecialchars($p['supplier_name'])?></td>
                        <td>
                            <a href="index.php?page=purchase_returns&purchase_id=<?=$p['id']?>"
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
                    <span>Purchase Returns</span>
                    <form method="get" class="d-flex ms-2">
                        <input type="hidden" name="page" value="purchase_returns">
                        <input type="text" name="q" class="form-control form-control-sm"
                               placeholder="Search invoice/supplier" value="<?=htmlspecialchars($search)?>">
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
                        <th>Supplier</th>
                        <th class="text-end">Grand</th>
                        <th>Pay</th>
                        <th width="80">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$returnRows): ?>
                        <tr><td colspan="7" class="text-center py-3">No returns</td></tr>
                    <?php else: foreach ($returnRows as $r): ?>
                        <tr>
                            <td><?=$r['id']?></td>
                            <td><?=htmlspecialchars($r['return_date'])?></td>
                            <td><?=htmlspecialchars($r['invoice_no'])?></td>
                            <td><?=htmlspecialchars($r['supplier_name'])?></td>
                            <td class="text-end"><?=number_format($r['grand_total'],2)?></td>
                            <td><?=htmlspecialchars(ucfirst($r['payment_type']))?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-dark"
                                   href="index.php?page=purchase_return_view&id=<?=$r['id']?>">View</a>
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
                               href="index.php?page=purchase_returns&p=<?=$i?>&q=<?=urlencode($search)?>"><?=$i?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>

    </div>

    <div class="col-md-5">
        <div class="p-3 border rounded bg-white shadow-sm small">
            <h6 class="mb-2">Create Purchase Return</h6>

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
                    Select a purchase invoice from the left list to create a return.
                </div>
            <?php else: ?>

                <form method="post" class="small">
                    <input type="hidden" name="purchase_id" value="<?=$invoice['id']?>">

                    <div class="mb-2">
                        <strong>Invoice:</strong> <?=htmlspecialchars($invoice['invoice_no'])?><br>
                        <strong>Date:</strong> <?=htmlspecialchars($invoice['invoice_date'])?><br>
                        <strong>Supplier:</strong> <?=htmlspecialchars($invoice['supplier_name'])?>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label>Return Date*</label>
                            <input type="date" name="return_date"
                                   class="form-control form-control-sm"
                                   value="<?=htmlspecialchars($_POST['return_date'] ?? date('Y-m-d'))?>" required>
                        </div>
                        <div class="col-md-6">
                            <label>Payment Type</label>
                            <select name="payment_type" class="form-select form-select-sm">
                                <option value="credit" <?=(($_POST['payment_type'] ?? 'credit')=='credit'?'selected':'')?>>Credit</option>
                                <option value="cash"   <?=(($_POST['payment_type'] ?? '')=='cash'?'selected':'')?>>Cash</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Purchased</th>
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
                                        <input type="hidden" name="purch_qty[]" value="<?=$it['qty']?>">
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
