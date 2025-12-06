<?php
require __DIR__ . '/../header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='p-3 small'>Invalid sales ID.</div>";
    require __DIR__ . '/../footer.php';
    exit;
}

// Company
$cs = $pdo->query("SELECT * FROM company_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);

// Header
$stmt = $pdo->prepare("SELECT si.*, c.name AS customer_name, c.address AS customer_address,
                              c.city AS customer_city, c.state AS customer_state, c.gst_number AS customer_gst,
                              c.phone AS customer_phone
                       FROM sales_invoices si
                       JOIN customers c ON c.id=si.customer_id
                       WHERE si.id=?");
$stmt->execute([$id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
    echo "<div class='p-3 small'>Sales invoice not found.</div>";
    require __DIR__ . '/../footer.php';
    exit;
}

// Items
$stmt2 = $pdo->prepare("SELECT it.*, p.item_name, p.sku, p.unit
                        FROM sales_items it
                        JOIN products p ON p.id = it.product_id
                        WHERE it.sales_id=?
                        ORDER BY it.id ASC");
$stmt2->execute([$id]);
$items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// GST Split
$total_cgst = 0;
$total_sgst = 0;
foreach ($items as &$it) {
    $gstRate = (float)$it['tax_percent'];
    $fullGST = ($it['amount'] * $gstRate / 100);
    $it['cgst'] = $fullGST / 2;
    $it['sgst'] = $fullGST / 2;
    $total_cgst += $it['cgst'];
    $total_sgst += $it['sgst'];
}
unset($it);

?>

<div class="p-3 bg-white border rounded shadow-sm small" id="print-area">
    <div class="d-flex justify-content-between mb-2">
        <div>
            <h5 class="mb-0"><?=htmlspecialchars($cs['company_name'])?></h5>
            <div><?=nl2br(htmlspecialchars($cs['address']))?></div>
            <div><?=htmlspecialchars($cs['city'])?>, <?=htmlspecialchars($cs['state'])?> - <?=htmlspecialchars($cs['pincode'])?></div>
            <div>GST: <?=htmlspecialchars($cs['gst_number'])?></div>
            <div>Phone: <?=htmlspecialchars($cs['phone'])?></div>
        </div>
        <div class="text-end">
            <h6 class="mb-1">Sales Invoice</h6>
            <div><strong>No:</strong> <?=htmlspecialchars($inv['invoice_no'])?></div>
            <div><strong>Date:</strong> <?=htmlspecialchars($inv['invoice_date'])?></div>
            <div><strong>Payment:</strong> <?=htmlspecialchars(ucfirst($inv['payment_type']))?></div>
        </div>
    </div>

    <hr class="my-1">

    <div class="row mb-2">
        <div class="col-md-6">
            <h6 class="mb-1">Bill To</h6>
            <div><strong><?=htmlspecialchars($inv['customer_name'])?></strong></div>
            <div><?=nl2br(htmlspecialchars($inv['customer_address']))?></div>
            <div><?=htmlspecialchars($inv['customer_city'])?>, <?=htmlspecialchars($inv['customer_state'])?></div>
            <div>GSTIN: <?=htmlspecialchars($inv['customer_gst'])?></div>
            <div>Phone: <?=htmlspecialchars($inv['customer_phone'])?></div>
        </div>
        <div class="col-md-6">
            <h6 class="mb-1">Delivery Address</h6>
            <div><?=nl2br(htmlspecialchars($inv['customer_address']))?></div>
            <div><?=htmlspecialchars($inv['customer_city'])?>, <?=htmlspecialchars($inv['customer_state'])?></div>
        </div>
    </div>

    <div class="table-responsive">
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
                <th class="text-end">GST%</th>
                <th class="text-end">Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php $i=1; foreach ($items as $it): ?>
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

    <div class="row">
        <div class="col-md-6"></div>
        <div class="col-md-6">
            <table class="table table-sm mb-0">
                <tr>
                    <td class="border-0">Subtotal</td>
                    <td class="text-end border-0"><?=number_format($inv['total_amount'],2)?></td>
                </tr>
                <tr>
                    <td class="border-0">CGST</td>
                    <td class="text-end border-0"><?=number_format($total_cgst,2)?></td>
                </tr>
                <tr>
                    <td class="border-0">SGST</td>
                    <td class="text-end border-0"><?=number_format($total_sgst,2)?></td>
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
    <a href="index.php?page=sales" class="btn btn-sm btn-secondary">Back</a>
    <button class="btn btn-sm btn-dark" onclick="window.print()">Print</button>

    <!-- WhatsApp share: Invoice No + Amount + Customer -->
    <?php
    $msg = rawurlencode(
        "Invoice: {$inv['invoice_no']}\n".
        "Customer: {$inv['customer_name']}\n".
        "Total: ".number_format($inv['grand_total'],2)
    );
    ?>
    <a href="https://wa.me/?text=<?=$msg?>" target="_blank" class="btn btn-sm btn-success">WhatsApp</a>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
