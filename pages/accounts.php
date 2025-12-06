<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// VALID TYPES
$types = ['Asset','Liability','Income','Expense'];

// SAVE FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['account_name']);
    $type = trim($_POST['account_type']);
    $active = (int)($_POST['is_active']);

    if ($name === "") $errors[] = "Account name required.";
    if (!in_array($type, $types)) $errors[] = "Invalid account type.";

    // Check if editing a system account
    if ($id > 0) {
        $sysChk = $pdo->prepare("SELECT is_system FROM accounts WHERE id=?");
        $sysChk->execute([$id]);
        $isSystem = (int)$sysChk->fetchColumn();
        if ($isSystem) {
            // System accounts — only status change allowed
            if ($name !== $_POST['old_name'] || $type !== $_POST['old_type']) {
                $errors[] = "System accounts cannot be modified.";
            }
        }
    }

    if (!$errors) {
        if ($id > 0) {
            // UPDATE
            $stmt = $pdo->prepare("UPDATE accounts SET 
                account_name=:n,
                account_type=:t,
                is_active=:a
                WHERE id=:id");
            $stmt->execute([
                'n'=>$name,'t'=>$type,'a'=>$active,'id'=>$id
            ]);
            $success = "Updated";
        } else {
            // Duplicate name
            $chk = $pdo->prepare("SELECT id FROM accounts WHERE account_name=:n LIMIT 1");
            $chk->execute(['n'=>$name]);
            if ($chk->fetchColumn()) {
                $errors[] = "Account already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO accounts 
                    (account_name, account_type, is_active)
                    VALUES (:n,:t,:a)");
                $stmt->execute([
                    'n'=>$name,'t'=>$type,'a'=>$active
                ]);
                $success = "Added";
            }
        }
    }
}

// FORM DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
    $editData = [
        'id'=>$_POST['id'] ?? '',
        'account_name'=>$_POST['account_name'] ?? '',
        'account_type'=>$_POST['account_type'] ?? '',
        'is_active'=>$_POST['is_active'] ?? 1,
        'is_system'=>$_POST['is_system'] ?? 0
    ];
}
elseif (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}
else {
    $editData = [
        'id'=>'','account_name'=>'','account_type'=>'',
        'is_active'=>1,'is_system'=>0
    ];
}

// SEARCH + LIST
$q = trim($_GET['q'] ?? '');
$sql = "FROM accounts WHERE 1";
$params = [];
if ($q !== "") {
    $sql .= " AND (account_name LIKE :q)";
    $params['q'] = "%$q%";
}

$count = $pdo->prepare("SELECT COUNT(*) $sql");
$count->execute($params);
$totalRows = $count->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT * $sql ORDER BY id ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- FORM -->
<div class="p-3 border rounded mb-3 bg-white shadow-sm">
<form method="post" class="row g-3 small">
    <input type="hidden" name="id" value="<?=$editData['id']?>">
    <input type="hidden" name="old_name" value="<?=$editData['account_name']?>">
    <input type="hidden" name="old_type" value="<?=$editData['account_type']?>">

    <div class="col-md-4">
        <label>Name*</label>
        <input name="account_name" class="form-control form-control-sm"
               required value="<?=$editData['account_name']?>"
               <?=$editData['is_system'] ? 'readonly' : ''?>>
    </div>

    <div class="col-md-3">
        <label>Type*</label>
        <select name="account_type" class="form-select form-select-sm"
                <?=$editData['is_system'] ? 'disabled' : ''?>>
            <?php foreach($types as $t): ?>
            <option <?=$editData['account_type']==$t?'selected':''?>><?=$t?></option>
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

    <div class="col-md-2 d-flex align-items-end">
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

<!-- SEARCH -->
<div class="d-flex justify-content-between align-items-center mb-2 small">
<form method="get" class="d-flex">
    <input type="hidden" name="page" value="accounts">
    <input type="text" name="q" class="form-control form-control-sm"
           value="<?=htmlspecialchars($q)?>" placeholder="Search">
    <button class="btn btn-sm btn-dark ms-2">Search</button>
</form>
</div>

<!-- LIST -->
<div class="card shadow-sm border-0">
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 small align-middle">
<thead class="table-light">
<tr>
    <th>#</th>
    <th>Name</th>
    <th>Type</th>
    <th>System</th>
    <th>Status</th>
    <th width="80">Action</th>
</tr>
</thead>
<tbody>
<?php if(!$rows): ?>
<tr><td colspan="6" class="text-center py-3">No data</td></tr>
<?php else: foreach($rows as $a): ?>
<tr>
    <td><?=$a['id']?></td>
    <td><?=$a['account_name']?></td>
    <td><?=$a['account_type']?></td>
    <td><?=$a['is_system']?'Yes':'No'?></td>
    <td><?=$a['is_active']?'Active':'Inactive'?></td>
    <td>
        <a class="btn btn-sm btn-outline-dark"
           href="index.php?page=accounts&edit=<?=$a['id']?>&p=<?=$pageNum?>">Edit</a>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>

<!-- PAGINATION -->
<nav class="mt-2">
<ul class="pagination pagination-sm justify-content-end mb-0">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<li class="page-item <?=$i==$pageNum?'active':''?>">
    <a class="page-link"
       href="index.php?page=accounts&p=<?=$i?>&q=<?=urlencode($q)?>"><?=$i?></a>
</li>
<?php endfor; ?>
</ul>
</nav>

<?php require __DIR__ . '/../footer.php'; ?>
