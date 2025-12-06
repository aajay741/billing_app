<?php
require __DIR__ . '/../header.php';
requireLogin();

$errors = [];
$success = "";

// Pagination
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// Load suppliers
$suppStmt = $pdo->query("SELECT id,name FROM suppliers ORDER BY name");
$suppliers = $suppStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle save payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? '');
    $payment_type = ($_POST['payment_type'] ?? 'cash');
    $amount = (float)($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($supplier_id <= 0) $errors[] = "Supplier required.";
    if ($payment_date === "") $errors[] = "Payment date required.";
    if ($amount <= 0) $errors[] = "Amount must be greater than 0.";
    if (!in_array($payment_type, ['cash','bank'])) $errors[] = "Invalid payment type.";

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO supplier_payments
            (supplier_id, payment_date, payment_type, amount, notes)
            VALUES (:sid, :dt, :pt, :amt, :nt)");
        $stmt->execute([
            'sid'=>$supplier_id,
            'dt'=>$payment_date,
            'pt'=>$payment_type,
            'amt'=>$amount,
            'nt'=>$notes,
        ]);
        $success = "Payment saved.";
    }
}

// Search list
$q = trim($_GET['q'] ?? '');
$sql = "FROM supplier_payments sp
        JOIN suppliers s ON s.id=sp.supplier_id
        WHERE 1";
$params = [];

if ($q !== "") {
    $sql .= " AND (s.name LIKE :q OR s.phone LIKE :q)";
    $params['q'] = "%$q%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $sql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$listStmt = $pdo->prepare("SELECT sp.*, s.name AS supplier_name
                           $sql
                           ORDER BY sp.id DESC
                           LIMIT $limit OFFSET $offset");
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-4">
        <div class="p-3 border rounded bg-white shadow-sm small mb-3">
            <h6 class="mb-2">Record Supplier Payment</h6>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small"><ul class="m-0">
                    <?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
                </ul></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2 small"><?=htmlspecialchars($success)?></div>
            <?php endif; ?>

            <form method="post" class="small">
                <div class="mb-2">
                    <label>Supplier*</label>
                    <select name="supplier_id" class="form-select form-select-sm" required>
                        <option value="">-- Select --</option>
                        <?php foreach($suppliers as $s): ?>
                            <option value="<?=$s['id']?>"><?=$s['name']?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label>Date*</label>
                    <input type="date" name="payment_date" class="form-control form-control-sm"
                           value="<?=date('Y-m-d')?>" required>
                </div>

                <div class="mb-2">
                    <label>Payment Type*</label>
                    <select name="payment_type" class="form-select form-select-sm">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label>Amount*</label>
                    <input type="number" step="0.01" name="amount"
                           class="form-control form-control-sm" required>
                </div>

                <div class="mb-2">
                    <label>Notes</label>
                    <input type="text" name="notes" class="form-control form-control-sm">
                </div>

                <button class="btn btn-dark btn-sm mt-2 w-100">Save Payment</button>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header py-2 small d-flex justify-content-between align-items-center">
                <span>Supplier Payments</span>
                <form method="get" class="d-flex ms-2">
                    <input type="hidden" name="page" value="supplier_payments">
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Search supplier" value="<?=htmlspecialchars($q)?>">
                    <button class="btn btn-sm btn-dark ms-2">Search</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 small align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="5" class="text-center py-3">No payments</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?=$r['id']?></td>
                            <td><?=htmlspecialchars($r['payment_date'])?></td>
                            <td><?=htmlspecialchars($r['supplier_name'])?></td>
                            <td><?=htmlspecialchars(ucfirst($r['payment_type']))?></td>
                            <td class="text-end"><?=number_format($r['amount'],2)?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <nav class="mt-2">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <?php for($i=1;$i<=$totalPages;$i++): ?>
                        <li class="page-item <?=$i==$pageNum?'active':''?>">
                            <a class="page-link"
                               href="index.php?page=supplier_payments&p=<?=$i?>&q=<?=urlencode($q)?>"><?=$i?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>

        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
