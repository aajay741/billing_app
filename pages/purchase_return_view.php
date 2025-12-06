<?php
require __DIR__ . '/../header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='p-3 small'>Invalid return ID.</div>";
    require __DIR__ . '/../footer.php';
    exit;
}

// Company
$cs = $pdo->query("SELECT * FROM company_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);

// Header: return + original purchase + supplier
$stmt = $pdo->prepare("
    SELECT pr.*, pi.invoice_no, pi.invoice_date, s.name AS supplier_name,
           s.address AS supplier_address, s.city AS supplier_city,
           s.state AS supplier_state, s.gstin AS supplier_gst
    FROM purchase_returns pr
    JOIN purchase_invoices pi ON pi.id = pr.purchase_id
    JOIN suppliers s ON s.id = pi.supplier_id
    WHERE pr.id = ?
");
$stmt->execute([$id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
    echo "<div class='p-3 small'>Purchase return not found.</div>";
    require __DIR__ . '/../footer.php';
    exit;
}

// Items
$stmt2 = $pdo->prepare("
    SELECT pri.*, p.item_name, p.sku, p.unit
    FROM purchase_return_items pri
    JOIN products p ON p.id = pri.product_id
    WHERE pri.return_id = ?
    ORDER BY pri.id ASC
");
$stmt2->execute([$id]);
$items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-3 bg-white border rounded shadow-sm small" id="print-area">
    <div class="d-flex justify-content-between mb-2">
        <div>
            <h5 class="mb-0"><?=htmlspecialchars($cs['company_name'] ?? '')?></h5>
            <div><?=nl2br(htmlspecialchars($cs['address'] ?? ''))?></div>
            <div><?=htmlspecialchars($cs['city'] ?? '')?>, <?=htmlspecialchars($cs['state'] ?? '')?> - <?=htmlspecialchars($cs['pincode'] ?? '')?></div>
            <div>GST: <?=htmlspecialchars($cs['gst_number'] ?? '')?></div>
            <div>Phone: <?=htmlspecialchars($cs['phone'] ?? '')?></div>
        </div>
        <div class="text-end">
            <h6 class="mb-1">Purchase Return</h6>
            <div><strong>Return ID:</strong> <?=htmlspecialchars($inv['id'])?></div>
            <div><strong>Return Date:</strong> <?=htmlspecialchars($inv['return_date'])?></div>
            <div><strong>Invoice No:</strong> <?=htmlspecialchars($inv['invoice_no'])?></div>
            <div><strong>Invoice Date:</strong> <?=htmlspecialchars($inv['invoice_date'])?></div>
            <div><strong>Payment:</strong> <?=htmlspecialchars(ucfirst($inv['payment_type']))?></div>
        </div>
    </div>

    <hr class="my-2">

    <div class="row mb-2">
        <div class="col-md-6">
            <h6 class="mb-1">Supplier</h6>
            <div><strong><?=htmlspecialchars($inv['supplier_name'])?></strong></div>
            <div><?=nl2br(htmlspecialchars($inv['supplier_address']))?></div>
            <div><?=htmlspecialchars($inv['supplier_city'])?>, <?=htmlspecialchars($inv['supplier_state'])?></div>
            <div>GSTIN: <?=htmlspecialchars($inv['supplier_gst'])?></div>
        </div>
    </div>

    <div class="table-responsive mt-2">
        <table class="table table-sm table-bordered align-middle mb-2">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>SKU</th>
                <th>Unit</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Rate</th>
                <th class="text-end">Disc</th>
                <th class="text-end">Tax%</th>
                <th class="text-end">Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $i = 1;
            foreach ($items as $it):
            ?>
            <tr>
                <td><?=$i++?></td>
                <td><?=htmlspecialchars($it['item_name'])?></td>
                <td><?=htmlspecialchars($it['sku'])?></td>
                <td><?=htmlspecialchars($it['unit'])?></td>
                <td class="text-end"><?=number_format($it['qty'],3)?></td>
                <td class="text-end"><?=number_format($it['rate'],2)?></td>
                <td class="text-end"><?=number_format($it['discount'],2)?></td>
                <td class="text-end"><?=number_format($it['tax_percent'],2)?></td>
                <td class="text-end"><?=number_format($it['amount'],2)?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row mt-2">
        <div class="col-md-6"></div>
        <div class="col-md-6">
            <table class="table table-sm mb-0">
                <tr>
                    <td class="border-0">Subtotal</td>
                    <td class="text-end border-0"><?=number_format($inv['total_amount'],2)?></td>
                </tr>
                <tr>
                    <td class="border-0">GST (CGST+SGST)</td>
                    <td class="text-end border-0"><?=number_format($inv['total_tax'],2)?></td>
                </tr>
                <tr class="fw-bold">
                    <td class="border-0">Grand Total</td>
                    <td class="text-end border-0"><?=number_format($inv['grand_total'],2)?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="mt-2 d-print-none">
    <a href="index.php?page=purchase_returns" class="btn btn-sm btn-secondary">Back</a>
    <button class="btn btn-sm btn-dark" onclick="window.print()">Print</button>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
