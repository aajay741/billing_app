<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// Load active categories
$catStmt = $pdo->query("SELECT id, category_name FROM categories WHERE is_active=1 ORDER BY category_name");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $item = trim($_POST['item_name']);
    $sku  = strtoupper(trim($_POST['sku']));
    $cat  = (int)($_POST['category_id']);
    $unit = trim($_POST['unit']);
    $gst  = (float)($_POST['tax_percent'] ?? 0);
    $pp   = (float)($_POST['purchase_price'] ?? 0);
    $sp   = (float)($_POST['selling_price'] ?? 0);
    $os   = ($_POST['opening_stock'] !== '' ? (float)$_POST['opening_stock'] : 0);
    $active = (int)($_POST['is_active'] ?? 1);

    if ($item === "") $errors[] = "Item name required.";
    if ($sku === "")  $errors[] = "SKU required.";
    if ($unit === "") $errors[] = "Unit required.";
    if ($os < 0)      $errors[] = "Opening stock cannot be negative.";
    if ($sp < $pp)    $errors[] = "Selling price cannot be less than purchase price.";

    if (!$errors) {
        if ($id > 0) {
            // EDIT
            $chk = $pdo->prepare("SELECT id FROM products WHERE sku=:s AND id<>:id LIMIT 1");
            $chk->execute(['s'=>$sku, 'id'=>$id]);
            if ($chk->fetchColumn()) {
                $errors[] = "SKU already exists.";
            } else {
                $stmt = $pdo->prepare("UPDATE products SET
                    item_name=:i, sku=:s, category_id=:c, unit=:u,
                    tax_percent=:g, purchase_price=:pp,
                    selling_price=:sp, is_active=:a
                    WHERE id=:id");
                $stmt->execute([
                    'i'=>$item,'s'=>$sku,'c'=>$cat,'u'=>$unit,
                    'g'=>$gst,'pp'=>$pp,'sp'=>$sp,'a'=>$active,'id'=>$id
                ]);
                $success = "Updated";
            }
        } else {
            // ADD NEW PRODUCT
            $chk = $pdo->prepare("SELECT id FROM products WHERE sku=:s LIMIT 1");
            $chk->execute(['s'=>$sku]);
            if ($chk->fetchColumn()) {
                $errors[] = "SKU already exists.";
            } else {
                // Insert product
                $stmt = $pdo->prepare("INSERT INTO products
                    (item_name, sku, category_id, unit, tax_percent,
                     purchase_price, selling_price, opening_stock, is_active)
                    VALUES (:i,:s,:c,:u,:g,:pp,:sp,:os,:a)");
                $stmt->execute([
                    'i'=>$item,'s'=>$sku,'c'=>$cat,'u'=>$unit,
                    'g'=>$gst,'pp'=>$pp,'sp'=>$sp,'os'=>$os,'a'=>$active
                ]);
                $newId = $pdo->lastInsertId();

                // Insert opening ledger entry
                if ($os > 0) {
                    $stmt2 = $pdo->prepare("INSERT INTO stock_ledger
                        (product_id, reference_type, reference_id, qty_in, qty_out, closing_stock)
                        VALUES (:pid,'opening',0,:qi,0,:qi)");
                    $stmt2->execute([
                        'pid'=>$newId,
                        'qi'=>$os
                    ]);
                }
                $success = "Added";
            }
        }
    }
}

// Form fill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
    $editData = $_POST;
}
elseif (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
}
else {
    $editData = [
        'id'=>'','item_name'=>'','sku'=>'','category_id'=>'',
        'unit'=>'','tax_percent'=>'','purchase_price'=>'',
        'selling_price'=>'','opening_stock'=>'','is_active'=>1
    ];
}

// SEARCH + LIST
$q = trim($_GET['q'] ?? '');
$sql = "FROM products WHERE 1";
$params = [];
if ($q !== "") {
    $sql .= " AND (item_name LIKE :q OR sku LIKE :q)";
    $params['q'] = "%$q%";
}

$count = $pdo->prepare("SELECT COUNT(*) $sql");
$count->execute($params);
$totalRows = $count->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT * $sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- FORM -->
<div class="p-3 border rounded mb-3 bg-white shadow-sm">
<form method="post" class="row g-3 small">
<input type="hidden" name="id" value="<?=$editData['id']?>">

<div class="col-md-3">
    <label>Item*</label>
    <input name="item_name" class="form-control form-control-sm" required value="<?=$editData['item_name']?>">
</div>
<div class="col-md-2">
    <label>SKU*</label>
    <input name="sku" class="form-control form-control-sm text-uppercase" required value="<?=$editData['sku']?>">
</div>
<div class="col-md-3">
    <label>Category</label>
    <select name="category_id" class="form-select form-select-sm">
        <option value="">--None--</option>
        <?php foreach($categories as $c): ?>
        <option value="<?=$c['id']?>" <?=$editData['category_id']==$c['id']?'selected':''?>>
            <?=$c['category_name']?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-2">
    <label>Unit*</label>
    <select name="unit" class="form-select form-select-sm" required>
        <?php foreach(['PCS','KG','LTR','BOX','BAG'] as $u): ?>
        <option <?=$editData['unit']==$u?'selected':''?>><?=$u?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-2">
    <label>Status</label>
    <select name="is_active" class="form-select form-select-sm">
        <option value="1" <?=$editData['is_active']?'selected':''?>>Active</option>
        <option value="0" <?=!$editData['is_active']?'selected':''?>>Inactive</option>
    </select>
</div>

<div class="col-md-2">
    <label>GST%</label>
    <input type="number" step="0.01" name="tax_percent" class="form-control form-control-sm"
           value="<?=$editData['tax_percent']?>">
</div>
<div class="col-md-2">
    <label>Buy</label>
    <input type="number" step="0.01" name="purchase_price" class="form-control form-control-sm" required
           value="<?=$editData['purchase_price']?>">
</div>
<div class="col-md-2">
    <label>Sell</label>
    <input type="number" step="0.01" name="selling_price" class="form-control form-control-sm" required
           value="<?=$editData['selling_price']?>">
</div>
<div class="col-md-2">
    <label>Opening</label>
    <input type="number" step="0.01" name="opening_stock" class="form-control form-control-sm"
           value="<?=$editData['opening_stock']?>"
           <?=$editData['id'] ? 'readonly' : ''?>>
</div>

<div class="col-md-2 d-flex align-items-end">
    <button class="btn btn-dark btn-sm w-100"><?=$editData['id']?'Update':'Save'?></button>
</div>
</form>
</div>

<?php if($errors): ?>
<div class="alert alert-danger py-2 small"><ul class="m-0">
<?php foreach($errors as $e) echo "<li>$e</li>"; ?>
</ul></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success py-2 small"><?=$success?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2 small">
<form method="get" class="d-flex">
    <input type="hidden" name="page" value="products">
    <input type="text" name="q" class="form-control form-control-sm"
           value="<?=htmlspecialchars($q)?>" placeholder="Search name or SKU">
    <button class="btn btn-sm btn-dark ms-2">Search</button>
</form>
</div>

<div class="card shadow-sm border-0">
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 small align-middle">
<thead class="table-light">
<tr>
    <th>#</th>
    <th>Item</th>
    <th>SKU</th>
    <th>Unit</th>
    <th>Buy</th>
    <th>Sell</th>
    <th>GST%</th>
    <th>Status</th>
    <th width="80">Action</th>
</tr>
</thead>
<tbody>
<?php if(!$rows): ?>
<tr><td colspan="9" class="text-center py-3">No data</td></tr>
<?php else: foreach($rows as $p): ?>
<tr>
    <td><?=$p['id']?></td>
    <td><?=$p['item_name']?></td>
    <td><?=$p['sku']?></td>
    <td><?=$p['unit']?></td>
    <td><?=$p['purchase_price']?></td>
    <td><?=$p['selling_price']?></td>
    <td><?=$p['tax_percent']?></td>
    <td><?=$p['is_active']?'Active':'Inactive'?></td>
    <td><a class="btn btn-sm btn-outline-dark"
           href="index.php?page=products&edit=<?=$p['id']?>&p=<?=$pageNum?>">Edit</a></td>
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
       href="index.php?page=products&p=<?=$i?>&q=<?=urlencode($q)?>"><?=$i?></a>
</li>
<?php endfor; ?>
</ul>
</nav>

<?php require __DIR__ . '/../footer.php'; ?>
