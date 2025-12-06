<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// Load active products
$prodStmt = $pdo->query("SELECT id, item_name, sku FROM products WHERE is_active=1 ORDER BY item_name");
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

$types = ['opening','purchase','purchase_return','sale','sale_return'];

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $ref_type = trim($_POST['reference_type'] ?? '');
    $reference_id = (int)($_POST['reference_id'] ?? 0);
    $qty_in  = (float)($_POST['qty_in'] ?? 0);
    $qty_out = (float)($_POST['qty_out'] ?? 0);

    if ($product_id <= 0) $errors[] = "Select product.";
    if (!in_array($ref_type, $types)) $errors[] = "Invalid type.";
    if ($qty_in <= 0 && $qty_out <= 0) $errors[] = "Enter qty_in or qty_out.";

    if (!$errors) {
        $c = $pdo->prepare("SELECT closing_stock FROM stock_ledger WHERE product_id=? ORDER BY id DESC LIMIT 1");
        $c->execute([$product_id]);
        $prev = (float)$c->fetchColumn();

        $closing = $prev + $qty_in - $qty_out;

        $stmt = $pdo->prepare("INSERT INTO stock_ledger 
            (product_id, reference_type, reference_id, qty_in, qty_out, closing_stock)
            VALUES (:p,:rt,:rid,:qi,:qo,:cs)");
        $stmt->execute([
            'p'=>$product_id,
            'rt'=>$ref_type,
            'rid'=>$reference_id,
            'qi'=>$qty_in,
            'qo'=>$qty_out,
            'cs'=>$closing
        ]);

        $success = "Added";
    }
}

$editData = [
    'product_id'=>'',
    'reference_type'=>'purchase',
    'reference_id'=>'',
    'qty_in'=>'0',
    'qty_out'=>'0'
];

// SEARCH + LIST
$q = trim($_GET['q'] ?? '');
$sql = "FROM stock_ledger sl
        JOIN products p ON p.id = sl.product_id
        WHERE 1";
$params = [];
if ($q !== "") {
    $sql .= " AND (p.item_name LIKE :q OR sl.reference_id LIKE :q)";
    $params['q'] = "%$q%";
}

$count = $pdo->prepare("SELECT COUNT(*) $sql");
$count->execute($params);
$totalRows = $count->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $pdo->prepare("
SELECT sl.*, p.item_name, p.sku
$sql
ORDER BY sl.id DESC
LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- FORM -->
<div class="p-3 border rounded mb-3 bg-white shadow-sm">
<form method="post" class="row g-3 small">

<div class="col-md-4">
    <label>Product*</label>
    <select name="product_id" class="form-select form-select-sm" required>
        <option value="">Select</option>
        <?php foreach($products as $p): ?>
        <option value="<?=$p['id']?>">
            <?=$p['item_name']?> (<?=$p['sku']?>)
        </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-3">
    <label>Ref. Type*</label>
    <select name="reference_type" class="form-select form-select-sm">
        <?php foreach($types as $t): ?>
        <option value="<?=$t?>"><?=$t?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="col-md-2">
    <label>Ref ID</label>
    <input type="number" name="reference_id" class="form-control form-control-sm">
</div>

<div class="col-md-1">
    <label>In</label>
    <input type="number" step="0.001" name="qty_in" class="form-control form-control-sm">
</div>

<div class="col-md-1">
    <label>Out</label>
    <input type="number" step="0.001" name="qty_out" class="form-control form-control-sm">
</div>

<div class="col-md-1 d-flex align-items-end">
    <button class="btn btn-dark btn-sm w-100">Save</button>
</div>

</form>
</div>

<?php if($errors): ?>
<div class="alert alert-danger py-2 small"><ul class="m-0"><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success py-2 small"><?=$success?></div>
<?php endif; ?>

<!-- SEARCH -->
<div class="d-flex justify-content-between align-items-center mb-2 small">
<form method="get" class="d-flex">
    <input type="hidden" name="page" value="stock_ledger">
    <input type="text" name="q" class="form-control form-control-sm"
           value="<?=htmlspecialchars($q)?>" placeholder="Search product or ref">
    <button class="btn btn-sm btn-dark ms-2">Search</button>
</form>
</div>

<div class="card shadow-sm border-0">
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 small align-middle">
<thead class="table-light">
<tr>
    <th>ID</th>
    <th>Product</th>
    <th>Type</th>
    <th>Ref ID</th>
    <th>In</th>
    <th>Out</th>
    <th>Closing</th>
    <th>Date</th>
</tr>
</thead>
<tbody>
<?php if(!$rows): ?>
<tr><td colspan="8" class="text-center py-3">No data</td></tr>
<?php else: foreach($rows as $r): ?>
<tr>
    <td><?=$r['id']?></td>
    <td><?=$r['item_name']?> (<?=$r['sku']?>)</td>
    <td><?=$r['reference_type']?></td>
    <td><?=$r['reference_id']?></td>
    <td><?=$r['qty_in']?></td>
    <td><?=$r['qty_out']?></td>
    <td><?=$r['closing_stock']?></td>
    <td><?=$r['entry_date']?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>

<nav class="mt-2">
<ul class="pagination pagination-sm justify-content-end mb-0">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<li class="page-item <?=$i==$pageNum?'active':''?>">
    <a class="page-link"
       href="index.php?page=stock_ledger&p=<?=$i?>&q=<?=urlencode($q)?>"><?=$i?></a>
</li>
<?php endfor; ?>
</ul>
</nav>

<?php require __DIR__ . '/../footer.php'; ?>
