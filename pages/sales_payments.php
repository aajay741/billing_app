<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination for invoice list
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// --- Load invoices having outstanding ---
$q = trim($_GET['q'] ?? '');
$sql = "FROM sales_invoices si
        JOIN customers c ON c.id = si.customer_id
        WHERE si.grand_total > si.paid_amount";
$params = [];

if ($q !== '') {
    $sql .= " AND (si.invoice_no LIKE :q OR c.name LIKE :q)";
    $params['q'] = "%$q%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $sql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$listStmt = $pdo->prepare("SELECT si.*, c.name AS cust_name,
                           (si.grand_total - si.paid_amount) AS balance
                           $sql
                           ORDER BY si.id DESC
                           LIMIT $limit OFFSET $offset");
$listStmt->execute($params);
$invoices = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Payment form selected invoice
$sales_id = (int)($_GET['sales_id'] ?? 0);
$inv = null;
if ($sales_id > 0) {
    $st = $pdo->prepare("SELECT si.*, c.name AS cust_name
                         FROM sales_invoices si
                         JOIN customers c ON c.id = si.customer_id
                         WHERE si.id=?");
    $st->execute([$sales_id]);
    $inv = $st->fetch(PDO::FETCH_ASSOC);
    if (!$inv) {
        $errors[] = "Invoice not found.";
        $sales_id = 0;
    }
}

// ---- Save Payment ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid    = (int)($_POST['sales_id'] ?? 0);
    $pdate  = trim($_POST['pay_date'] ?? '');
    $ptype  = ($_POST['payment_type'] ?? 'cash') === 'bank' ? 'bank' : 'cash';
    $amount = (float)($_POST['amount'] ?? 0);
    $notes  = trim($_POST['notes'] ?? '');

    if ($sid <= 0) $errors[] = "No invoice selected.";
    if ($pdate === '') $errors[] = "Payment date required.";
    if ($amount <= 0) $errors[] = "Amount must be greater than zero.";

    if (!$errors) {
        // Fetch outstanding
        $ost = $pdo->prepare("SELECT grand_total, paid_amount
                              FROM sales_invoices WHERE id=?");
        $ost->execute([$sid]);
        $row = $ost->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $errors[] = "Invoice not found.";
        } else {
            $balance = $row['grand_total'] - $row['paid_amount'];
            if ($amount > $balance + 0.001) {
                $errors[] = "Amount exceeds outstanding.";
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Insert payment
            $stm = $pdo->prepare("INSERT INTO sales_payments
                (sales_id, pay_date, payment_type, amount, notes)
                VALUES (?, ?, ?, ?, ?)");
            $stm->execute([$sid, $pdate, $ptype, $amount, $notes]);

            // Update invoice paid_amount
            $upd = $pdo->prepare("UPDATE sales_invoices
                    SET paid_amount = paid_amount + ?
                    WHERE id=?");
            $upd->execute([$amount, $sid]);

            $pdo->commit();
            $success = "Payment recorded.";
            $sales_id = 0;
            $inv = null;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: ".$e->getMessage();
        }
    }
}

// Recent payments list
$payList = $pdo->query("
    SELECT sp.*, si.invoice_no
    FROM sales_payments sp
    JOIN sales_invoices si ON si.id = sp.sales_id
    ORDER BY sp.id DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <!-- Invoice List -->
    <div class="col-md-7">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header py-2 small">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Select Invoice to Settle</span>
                    <form method="get" class="d-flex ms-2">
                        <input type="hidden" name="page" value="sales_payments">
                        <input type="text" name="q" class="form-control form-control-sm"
                            value="<?=htmlspecialchars($q)?>" placeholder="Search">
                        <button class="btn btn-sm btn-dark ms-2">Search</button>
                    </form>
                </div>
            </div>
            <div class="table-responsive small">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th class="text-end">Balance</th>
                        <th width="70">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$invoices): ?>
                        <tr><td colspan="6" class="text-center py-2">No outstanding</td></tr>
                    <?php else: foreach ($invoices as $r): ?>
                        <tr>
                            <td><?=$r['id']?></td>
                            <td><?=$r['invoice_date']?></td>
                            <td><?=$r['invoice_no']?></td>
                            <td><?=htmlspecialchars($r['cust_name'])?></td>
                            <td class="text-end"><?=number_format($r['balance'],2)?></td>
                            <td>
                                <a href="index.php?page=sales_payments&sales_id=<?=$r['id']?>"
                                   class="btn btn-sm btn-outline-dark">Pay</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment Form -->
    <div class="col-md-5">
        <div class="card shadow-sm border-0 mb-3 p-3 small">
            <h6 class="mb-2">Record Payment</h6>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2"><ul class="m-0">
                <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
                </ul></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2"><?=$success?></div>
            <?php endif; ?>

            <?php if (!$inv): ?>
                <div class="text-muted small">Select an invoice to proceed.</div>
            <?php else: ?>
            <form method="post" class="small">
                <input type="hidden" name="sales_id" value="<?=$inv['id']?>">

                <div class="mb-2"><strong>Invoice:</strong> <?=$inv['invoice_no']?></div>
                <div class="mb-2"><strong>Customer:</strong> <?=htmlspecialchars($inv['cust_name'])?></div>
                <div class="mb-2"><strong>Outstanding:</strong>
                    <?=number_format($inv['grand_total']-$inv['paid_amount'],2)?>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label>Date*</label>
                        <input type="date" name="pay_date" class="form-control form-control-sm"
                               required value="<?=date('Y-m-d')?>"> 
                    </div>
                    <div class="col-6">
                        <label>Mode</label>
                        <select name="payment_type" class="form-select form-select-sm">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <label>Amount*</label>
                    <input type="number" step="0.01" name="amount" class="form-control form-control-sm" required>
                </div>

                <div class="mb-2">
                    <label>Notes</label>
                    <input type="text" name="notes" class="form-control form-control-sm">
                </div>

                <button class="btn btn-dark btn-sm">Save Payment</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card shadow-sm border-0 small">
    <div class="card-header py-2 fw-bold">Recent Payments</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Invoice</th>
                <th class="text-end">Amount</th>
                <th>Mode</th>
                <th>Notes</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$payList): ?>
                <tr><td colspan="6" class="text-center py-2">No payments yet</td></tr>
            <?php else: foreach ($payList as $p): ?>
                <tr>
                    <td><?=$p['id']?></td>
                    <td><?=$p['pay_date']?></td>
                    <td><?=$p['invoice_no']?></td>
                    <td class="text-end"><?=number_format($p['amount'],2)?></td>
                    <td><?=ucfirst($p['payment_type'])?></td>
                    <td><?=htmlspecialchars($p['notes'])?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
