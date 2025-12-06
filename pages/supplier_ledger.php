<?php
require __DIR__ . '/../header.php';
requireLogin();

$supplier_id = (int)($_GET['id'] ?? 0);

// Fetch suppliers for dropdown
$allSup = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// If supplier not selected -> no blocking error
$supplier = null;
if ($supplier_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch();
    if (!$supplier) $supplier_id = 0;
}
?>

<div class="p-3 border rounded mb-3 bg-white shadow-sm small">
    <h6 class="mb-2">Supplier Ledger</h6>

    <form method="get" class="row g-2 align-items-end small mb-3">
        <input type="hidden" name="page" value="supplier_ledger">
        <div class="col-md-4">
            <label>Select Supplier</label>
            <select name="id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- choose --</option>
                <?php foreach($allSup as $s): ?>
                <option value="<?=$s['id']?>" <?=($supplier_id==$s['id']?'selected':'')?>>
                    <?=$s['name']?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($supplier): ?>
        <strong><?= htmlspecialchars($supplier['name']) ?></strong><br>
        <span class="text-muted"><?= htmlspecialchars($supplier['phone']) ?></span>
    <?php else: ?>
        <div class="text-muted small">Select a supplier to view ledger.</div>
    <?php endif; ?>
</div>

<?php
if ($supplier_id > 0):

$sql = "
SELECT * FROM (
    SELECT
        pi.invoice_date AS date,
        CONCAT('PI-', pi.id) AS ref,
        pi.grand_total AS amount,
        'Purchase' AS trans_type
    FROM purchase_invoices pi
    WHERE pi.supplier_id = :sid

    UNION ALL

    SELECT
        pr.return_date AS date,
        CONCAT('PR-', pr.id) AS ref,
        -pr.grand_total AS amount,
        'Purchase Return' AS trans_type
    FROM purchase_returns pr
    WHERE pr.purchase_id IN (
        SELECT id FROM purchase_invoices WHERE supplier_id = :sid
    )

    UNION ALL

    SELECT
        sp.payment_date AS date,
        CONCAT('PAY-', sp.id) AS ref,
        -sp.amount AS amount,
        'Payment' AS trans_type
    FROM supplier_payments sp
    WHERE sp.supplier_id = :sid
) AS ledger
ORDER BY date ASC, ref ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['sid' => $supplier_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$balance = 0;
foreach ($rows as &$r) {
    $balance += (float)$r['amount'];
    $r['balance'] = $balance;
}
unset($r);
?>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 small align-middle">
            <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Type</th>
                <th class="text-end">Amount</th>
                <th class="text-end">Balance</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center py-3">No ledger entries</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['date']) ?></td>
                    <td><?= htmlspecialchars($r['ref']) ?></td>
                    <td><?= htmlspecialchars($r['trans_type']) ?></td>
                    <td class="text-end"><?= number_format($r['amount'], 2) ?></td>
                    <td class="text-end"><?= number_format($r['balance'], 2) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../footer.php'; ?>
