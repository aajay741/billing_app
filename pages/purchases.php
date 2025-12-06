<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// Load suppliers
$suppStmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
$suppliers = $suppStmt->fetchAll(PDO::FETCH_ASSOC);

// Load products
$prodStmt = $pdo->query("SELECT id, item_name, sku, tax_percent, purchase_price FROM products WHERE is_active=1 ORDER BY item_name");
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- SAVE PURCHASE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id  = (int)($_POST['supplier_id'] ?? 0);
    $invoice_no   = trim($_POST['invoice_no'] ?? '');
    $invoice_date = trim($_POST['invoice_date'] ?? '');
    $payment_type = ($_POST['payment_type'] ?? 'credit') === 'cash' ? 'cash' : 'credit';

    $prod_ids  = $_POST['product_id'] ?? [];
    $qtys      = $_POST['qty'] ?? [];
    $rates     = $_POST['rate'] ?? [];
    $discounts = $_POST['discount'] ?? [];
    $taxes     = $_POST['tax_percent'] ?? [];

    if ($supplier_id <= 0) $errors[] = "Supplier required.";
    if ($invoice_no === '') $errors[] = "Invoice number required.";
    if ($invoice_date === '') $errors[] = "Invoice date required.";

    $invoiceDateObj = null;
    if ($invoice_date !== '') {
        $invoiceDateObj = date_create_from_format('Y-m-d', $invoice_date);
        if (!$invoiceDateObj) $errors[] = "Invalid invoice date.";
    }

    // At least one valid line
    $lines = [];
    $lineCount = max(count($prod_ids), count($qtys));
    for ($i = 0; $i < $lineCount; $i++) {
        $pid = (int)($prod_ids[$i] ?? 0);
        $q   = (float)($qtys[$i] ?? 0);
        $r   = (float)($rates[$i] ?? 0);
        $d   = (float)($discounts[$i] ?? 0);
        $t   = (float)($taxes[$i] ?? 0);

        if ($pid > 0 && $q > 0) {
            if ($r <= 0) {
                $errors[] = "Rate must be > 0 for all valid rows.";
            }
            $lineNet = ($q * $r) - $d;
            if ($lineNet < 0) {
                $errors[] = "Line amount cannot be negative.";
            }
            $lines[] = [
                'product_id'   => $pid,
                'qty'          => $q,
                'rate'         => $r,
                'discount'     => $d,
                'tax_percent'  => $t,
                'net_amount'   => $lineNet, // before tax
            ];
        }
    }

    if (!$lines) {
        $errors[] = "At least one product line with quantity > 0 required.";
    }

    // Check duplicate invoice number for same supplier
    if (!$errors) {
        $chk = $pdo->prepare("SELECT id FROM purchase_invoices WHERE supplier_id=:s AND invoice_no=:no LIMIT 1");
        $chk->execute(['s'=>$supplier_id, 'no'=>$invoice_no]);
        if ($chk->fetchColumn()) {
            $errors[] = "Invoice number already exists for this supplier.";
        }
    }

    if (!$errors) {
        // Compute totals
        $total_amount = 0;
        $total_tax = 0;
        foreach ($lines as &$ln) {
            $lineTax = $ln['net_amount'] * $ln['tax_percent'] / 100;
            $ln['tax_amount'] = $lineTax;
            $total_amount += $ln['net_amount'];
            $total_tax    += $lineTax;
        }
        unset($ln);
        $grand_total = $total_amount + $total_tax;

        try {
            $pdo->beginTransaction();

            // Insert purchase header
            $stmt = $pdo->prepare("INSERT INTO purchase_invoices
                (supplier_id, invoice_no, invoice_date, total_amount, total_tax, grand_total, payment_type, is_posted)
                VALUES
                (:sid, :ino, :idate, :ta, :tt, :gt, :ptype, 1)");
            $stmt->execute([
                'sid'   => $supplier_id,
                'ino'   => $invoice_no,
                'idate' => $invoiceDateObj ? $invoiceDateObj->format('Y-m-d') : null,
                'ta'    => $total_amount,
                'tt'    => $total_tax,
                'gt'    => $grand_total,
                'ptype' => $payment_type,
            ]);
            $purchaseId = $pdo->lastInsertId();

            // Insert items + stock ledger
            $itemStmt = $pdo->prepare("INSERT INTO purchase_items
                (purchase_id, product_id, qty, rate, discount, tax_percent, amount)
                VALUES
                (:pid, :prid, :q, :r, :d, :t, :amt)");

            $ledgerStmtSel = $pdo->prepare("SELECT closing_stock FROM stock_ledger WHERE product_id=? ORDER BY id DESC LIMIT 1");
            $ledgerStmtIns = $pdo->prepare("INSERT INTO stock_ledger
                (product_id, reference_type, reference_id, qty_in, qty_out, closing_stock)
                VALUES (:prid, 'purchase', :refid, :qin, 0, :cs)");

            foreach ($lines as $ln) {
                $itemStmt->execute([
                    'pid'  => $purchaseId,
                    'prid' => $ln['product_id'],
                    'q'    => $ln['qty'],
                    'r'    => $ln['rate'],
                    'd'    => $ln['discount'],
                    't'    => $ln['tax_percent'],
                    'amt'  => $ln['net_amount'],
                ]);

                // stock ledger per product
                $ledgerStmtSel->execute([$ln['product_id']]);
                $prevClosing = (float)$ledgerStmtSel->fetchColumn();
                $newClosing  = $prevClosing + $ln['qty'];

                $ledgerStmtIns->execute([
                    'prid'  => $ln['product_id'],
                    'refid' => $purchaseId,
                    'qin'   => $ln['qty'],
                    'cs'    => $newClosing,
                ]);
            }

            $pdo->commit();
            $success = "Purchase invoice saved";
            // Clear form after success
            $_POST = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error saving purchase: " . $e->getMessage();
        }
    }
}

// ---------- LIST PURCHASE INVOICES ----------
$q = trim($_GET['q'] ?? '');
$listSql = "FROM purchase_invoices pi
            JOIN suppliers s ON s.id = pi.supplier_id
            WHERE 1";
$params = [];

if ($q !== '') {
    $listSql .= " AND (pi.invoice_no LIKE :q OR s.name LIKE :q)";
    $params['q'] = "%$q%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $listSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

$listStmt = $pdo->prepare("SELECT pi.*, s.name AS supplier_name
                           $listSql
                           ORDER BY pi.id DESC
                           LIMIT $limit OFFSET $offset");
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for form old value
function old($name, $default='') {
    return htmlspecialchars($_POST[$name] ?? $default);
}
?>

<div class="p-3 border rounded mb-3 bg-white shadow-sm">
    <h6 class="mb-3">New Purchase Invoice</h6>
    <form method="post" class="small" id="purchase-form">
        <div class="row g-2 mb-2">
            <div class="col-md-3">
                <label>Supplier*</label>
                <select name="supplier_id" class="form-select form-select-sm" required>
                    <option value="">Select supplier</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?=$s['id']?>"
                            <?=(isset($_POST['supplier_id']) && $_POST['supplier_id']==$s['id'])?'selected':''?>>
                            <?=$s['name']?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label>Invoice No*</label>
                <input type="text" name="invoice_no" class="form-control form-control-sm"
                       required value="<?=old('invoice_no')?>">
            </div>
            <div class="col-md-2">
                <label>Date*</label>
                <input type="date" name="invoice_date" class="form-control form-control-sm"
                       required value="<?=old('invoice_date', date('Y-m-d'))?>">
            </div>
            <div class="col-md-2">
                <label>Payment</label>
                <select name="payment_type" class="form-select form-select-sm">
                    <option value="credit" <?=(old('payment_type','credit')=='credit'?'selected':'')?>>Credit</option>
                    <option value="cash"   <?=(old('payment_type')=='cash'?'selected':'')?>>Cash</option>
                </select>
            </div>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-sm mb-0 align-middle" id="items-table">
                <thead class="table-light">
                <tr>
                    <th style="width:25%">Product</th>
                    <th style="width:10%">Qty</th>
                    <th style="width:15%">Rate</th>
                    <th style="width:10%">Disc</th>
                    <th style="width:10%">GST%</th>
                    <th style="width:15%" class="text-end">Amount</th>
                    <th style="width:5%"></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $rowsCount = max(1, isset($_POST['product_id']) ? count($_POST['product_id']) : 1);
                for ($i = 0; $i < $rowsCount; $i++):
                    $pId = $_POST['product_id'][$i] ?? '';
                    $q   = $_POST['qty'][$i] ?? '';
                    $r   = $_POST['rate'][$i] ?? '';
                    $d   = $_POST['discount'][$i] ?? '';
                    $t   = $_POST['tax_percent'][$i] ?? '';
                    $amt = '';
                ?>
                <tr>
                    <td>
                        <select name="product_id[]" class="form-select form-select-sm product-select">
                            <option value="">Select</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?=$p['id']?>"
                                        data-rate="<?=$p['purchase_price']?>"
                                        data-tax="<?=$p['tax_percent']?>"
                                    <?=$pId==$p['id']?'selected':''?>>
                                    <?=$p['item_name']?> (<?=$p['sku']?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.001" name="qty[]" class="form-control form-control-sm qty-input"
                               value="<?=$q?>">
                    </td>
                    <td>
                        <input type="number" step="0.01" name="rate[]" class="form-control form-control-sm rate-input"
                               value="<?=$r?>">
                    </td>
                    <td>
                        <input type="number" step="0.01" name="discount[]" class="form-control form-control-sm disc-input"
                               value="<?=$d?>">
                    </td>
                    <td>
                        <input type="number" step="0.01" name="tax_percent[]" class="form-control form-control-sm tax-input"
                               value="<?=$t?>">
                    </td>
                    <td class="text-end">
                        <input type="text" name="amount[]" class="form-control form-control-sm text-end amount-output" value="<?=$amt?>" readonly>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button>
                    </td>
                </tr>
                <?php endfor; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <td colspan="7">
                        <button type="button" class="btn btn-sm btn-outline-dark" id="add-row">+ Add Row</button>
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>

        <div class="row g-2 mt-2 small">
            <div class="col-md-8"></div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between">
                    <span>Subtotal</span>
                    <span id="subtotal-label">0.00</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Total GST</span>
                    <span id="tax-label">0.00</span>
                </div>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Grand Total</span>
                    <span id="grand-label">0.00</span>
                </div>
            </div>
        </div>

        <div class="mt-2">
            <button class="btn btn-dark btn-sm">Save Purchase</button>
        </div>
    </form>
</div>

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

<div class="d-flex justify-content-between align-items-center mb-2 small">
    <form method="get" class="d-flex">
        <input type="hidden" name="page" value="purchases">
        <input type="text" name="q" class="form-control form-control-sm"
               value="<?=htmlspecialchars($q)?>" placeholder="Search supplier or invoice no">
        <button class="btn btn-sm btn-dark ms-2">Search</button>
    </form>
</div>

<div class="card shadow-sm border-0">
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 small align-middle">
    <thead class="table-light">
    <tr>
        <th>#</th>
        <th>Date</th>
        <th>Invoice No</th>
        <th>Supplier</th>
        <th class="text-end">Total</th>
        <th class="text-end">GST</th>
        <th class="text-end">Grand</th>
        <th>Pay</th>
        <th width="80">Action</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="9" class="text-center py-3">No purchases</td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><?=$r['id']?></td>
            <td><?=htmlspecialchars($r['invoice_date'])?></td>
            <td><?=htmlspecialchars($r['invoice_no'])?></td>
            <td><?=htmlspecialchars($r['supplier_name'])?></td>
            <td class="text-end"><?=number_format($r['total_amount'],2)?></td>
            <td class="text-end"><?=number_format($r['total_tax'],2)?></td>
            <td class="text-end"><?=number_format($r['grand_total'],2)?></td>
            <td><?=htmlspecialchars(ucfirst($r['payment_type']))?></td>
            <td>
                <a class="btn btn-sm btn-outline-dark"
                   href="index.php?page=purchase_view&id=<?=$r['id']?>">View</a>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>
</div>

<nav class="mt-2">
<ul class="pagination pagination-sm justify-content-end mb-0">
<?php for($i=1; $i<=$totalPages; $i++): ?>
    <li class="page-item <?=$i==$pageNum?'active':''?>">
        <a class="page-link"
           href="index.php?page=purchases&p=<?=$i?>&q=<?=urlencode($q)?>"><?=$i?></a>
    </li>
<?php endfor; ?>
</ul>
</nav>

<script>
(function(){
    function recalcRow(row) {
        const qty  = parseFloat(row.querySelector('.qty-input')?.value || 0);
        const rate = parseFloat(row.querySelector('.rate-input')?.value || 0);
        const disc = parseFloat(row.querySelector('.disc-input')?.value || 0);
        const taxp = parseFloat(row.querySelector('.tax-input')?.value || 0);
        let net = qty * rate - disc;
        if (net < 0) net = 0;
        const lineTax = net * taxp / 100;
        const amtInput = row.querySelector('.amount-output');
        if (amtInput) amtInput.value = net.toFixed(2);
        return {net, tax: lineTax};
    }

    function recalcTotals() {
        let subtotal = 0, totaltax = 0;
        document.querySelectorAll('#items-table tbody tr').forEach(row => {
            const res = recalcRow(row);
            subtotal += res.net;
            totaltax += res.tax;
        });
        const grand = subtotal + totaltax;
        document.getElementById('subtotal-label').innerText = subtotal.toFixed(2);
        document.getElementById('tax-label').innerText      = totaltax.toFixed(2);
        document.getElementById('grand-label').innerText    = grand.toFixed(2);
    }

    document.getElementById('items-table').addEventListener('input', function(e){
        if (e.target.classList.contains('qty-input') ||
            e.target.classList.contains('rate-input') ||
            e.target.classList.contains('disc-input') ||
            e.target.classList.contains('tax-input')) {
            recalcTotals();
        }
    });

    document.getElementById('items-table').addEventListener('change', function(e){
        if (e.target.classList.contains('product-select')) {
            const opt = e.target.selectedOptions[0];
            if (opt) {
                const row = e.target.closest('tr');
                const rateInput = row.querySelector('.rate-input');
                const taxInput  = row.querySelector('.tax-input');
                if (rateInput && opt.dataset.rate) rateInput.value = opt.dataset.rate;
                if (taxInput && opt.dataset.tax) taxInput.value = opt.dataset.tax;
                recalcTotals();
            }
        }
    });

    document.getElementById('add-row').addEventListener('click', function(){
        const tbody = document.querySelector('#items-table tbody');
        const lastRow = tbody.querySelector('tr:last-child');
        const clone = lastRow.cloneNode(true);
        clone.querySelectorAll('input').forEach(inp => { inp.value = ''; });
        clone.querySelectorAll('select').forEach(sel => { sel.selectedIndex = 0; });
        tbody.appendChild(clone);
    });

    document.getElementById('items-table').addEventListener('click', function(e){
        if (e.target.classList.contains('remove-row')) {
            const tbody = document.querySelector('#items-table tbody');
            if (tbody.querySelectorAll('tr').length > 1) {
                e.target.closest('tr').remove();
                recalcTotals();
            }
        }
    });

    recalcTotals();
})();
</script>

<?php require __DIR__ . '/../footer.php'; ?>
