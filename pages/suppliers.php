<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// SAVE FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name']);
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city    = trim($_POST['city']);
    $state   = trim($_POST['state']);
    $pincode = trim($_POST['pincode']);
    $gstin   = trim($_POST['gstin']);

    if ($name === "")  $errors[] = "Name required.";
    if ($phone === "") $errors[] = "Phone required.";

    if (!$errors) {
        $data = [
            'n'=>$name,'p'=>$phone,'a'=>$address,
            'c'=>$city,'s'=>$state,'pc'=>$pincode,'g'=>$gstin
        ];

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE suppliers SET 
                name=:n, phone=:p, address=:a, city=:c, state=:s, pincode=:pc, gstin=:g
                WHERE id=:id");
            $data['id'] = $id;
            $stmt->execute($data);
            $success = "Supplier updated";
        } else {
            $check = $pdo->prepare("SELECT id FROM suppliers WHERE phone=:p LIMIT 1");
            $check->execute(['p'=>$phone]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                $stmt = $pdo->prepare("UPDATE suppliers SET 
                    name=:n, phone=:p, address=:a, city=:c, state=:s, pincode=:pc, gstin=:g
                    WHERE id=:id");
                $data['id'] = $existingId;
                $stmt->execute($data);
                $success = "Existing supplier updated";
            } else {
                $stmt = $pdo->prepare("INSERT INTO suppliers
                    (name, phone, address, city, state, pincode, gstin)
                    VALUES (:n,:p,:a,:c,:s,:pc,:g)");
                $stmt->execute($data);
                $success = "Supplier added";
            }
        }
    }
}

// LOAD FOR EDIT
$editData = [
    'id'=>"", 'name'=>"", 'phone'=>"", 'address'=>"",
    'city'=>"", 'state'=>"", 'pincode'=>"", 'gstin'=>""
];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch() ?: $editData;
}

// SEARCH + LIST
$q = trim($_GET['q'] ?? '');
$sql = "FROM suppliers WHERE 1";
$params = [];
if ($q !== "") {
    $sql .= " AND (name LIKE :q OR phone LIKE :q OR gstin LIKE :q)";
    $params['q'] = "%$q%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $sql");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT * $sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>

<div class="p-3 border rounded mb-3 bg-white shadow-sm">
<form method="post" class="row g-3 align-items-end small">
    <input type="hidden" name="id" value="<?=$editData['id']?>">

    <div class="col-md-2">
        <label>Name*</label>
        <input name="name" value="<?=$editData['name']?>"
               class="form-control form-control-sm" required>
    </div>

    <div class="col-md-2">
        <label>Phone*</label>
        <input name="phone" value="<?=$editData['phone']?>"
               class="form-control form-control-sm" required>
    </div>

    <div class="col-md-2">
        <label>GSTIN</label>
        <input name="gstin" value="<?=$editData['gstin']?>"
               class="form-control form-control-sm">
    </div>

    <div class="col-md-2">
        <label>City</label>
        <input name="city" value="<?=$editData['city']?>"
               class="form-control form-control-sm">
    </div>

    <div class="col-md-2">
        <label>State</label>
        <input name="state" value="<?=$editData['state']?>"
               class="form-control form-control-sm">
    </div>

    <div class="col-md-2">
        <label>Pincode</label>
        <input name="pincode" value="<?=$editData['pincode']?>"
               class="form-control form-control-sm">
    </div>

    <div class="col-md-6">
        <label>Address</label>
        <textarea name="address" rows="1"
                  class="form-control form-control-sm"><?=$editData['address']?></textarea>
    </div>

    <div class="col-md-2 text-end">
        <button class="btn btn-dark btn-sm w-100">
            <?=$editData['id']?'Update':'Save'?>
        </button>
    </div>
</form>
</div>

<?php if($errors): ?>
<div class="alert alert-danger py-2 small">
    <ul class="m-0"><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul>
</div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success py-2 small"><?=$success?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2 small">
<form method="get" class="d-flex">
    <input type="hidden" name="page" value="suppliers">
    <input type="text" name="q" class="form-control form-control-sm"
           placeholder="Search name, phone, GST"
           value="<?=htmlspecialchars($q)?>">
    <button class="btn btn-sm btn-dark ms-2">Search</button>
</form>
</div>

<div class="card shadow-sm border-0">
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 small align-middle">
    <thead class="table-light">
    <tr>
        <th>#</th>
        <th>Name</th>
        <th>Phone</th>
        <th>City</th>
        <th>GSTIN</th>
        <th>Address</th>
        <th width="80">Action</th>
    </tr>
    </thead>
    <tbody>
    <?php if(!$suppliers): ?>
        <tr><td colspan="7" class="text-center py-3">No suppliers</td></tr>
    <?php else: foreach($suppliers as $s): ?>
        <tr>
            <td><?=$s['id']?></td>
            <td><?=$s['name']?></td>
            <td><?=$s['phone']?></td>
            <td><?=$s['city']?></td>
            <td><?=$s['gstin']?></td>
            <td class="text-truncate" style="max-width:180px;"><?=$s['address']?></td>
            <td>
                <a href="index.php?page=suppliers&edit=<?=$s['id']?>&p=<?=$pageNum?>"
                   class="btn btn-sm btn-outline-dark">Edit</a>
            </td>
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
       href="index.php?page=suppliers&p=<?=$i?>&q=<?=urlencode($q)?>">
       <?=$i?>
    </a>
</li>
<?php endfor; ?>
</ul>
</nav>

<?php require __DIR__ . '/../footer.php'; ?>
