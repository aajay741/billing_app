<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// Pagination
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// SAVE (ADD/EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['category_name']);
    $active = isset($_POST['is_active']) ? 1 : 0;

    if ($name === "") $errors[] = "Category name required.";

    if (!$errors) {
        // Check duplicate
        $check = $pdo->prepare("SELECT id FROM categories WHERE category_name=:n LIMIT 1");
        $check->execute(['n'=>$name]);
        $existing = $check->fetchColumn();

        if ($id > 0) {
            // Update
            $stmt = $pdo->prepare("UPDATE categories 
                SET category_name=:n, is_active=:a 
                WHERE id=:id");
            $stmt->execute(['n'=>$name, 'a'=>$active, 'id'=>$id]);
            $success = "Category updated";
        } else {
            if ($existing) {
                $errors[] = "Category already exists.";
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO categories(category_name,is_active)
                    VALUES (:n,:a)");
                $stmt->execute(['n'=>$name, 'a'=>$active]);
                $success = "Category added";
            }
        }
    }
}

// LOAD FOR EDIT
$editData = ['id'=>'','category_name'=>'','is_active'=>1];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch() ?: $editData;
}

// SEARCH + LIST
$q = trim($_GET['q'] ?? '');
$sql = "FROM categories WHERE 1";
$params = [];

if ($q !== "") {
    $sql .= " AND category_name LIKE :q";
    $params['q'] = "%$q%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $sql");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT * $sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$categories = $stmt->fetchAll();
?>

<!-- INLINE FORM -->
<div class="p-3 border rounded mb-3 bg-white shadow-sm">
<form method="post" class="row g-3 align-items-end small">
    <input type="hidden" name="id" value="<?=$editData['id']?>">

    <div class="col-md-6">
        <label>Category Name*</label>
        <input name="category_name" value="<?=$editData['category_name']?>"
               class="form-control form-control-sm" required>
    </div>

    <div class="col-md-3">
        <label>Status</label>
        <select name="is_active" class="form-select form-select-sm">
            <option value="1" <?=$editData['is_active']?'selected':''?>>Active</option>
            <option value="0" <?=!$editData['is_active']?'selected':''?>>Inactive</option>
        </select>
    </div>

    <div class="col-md-3 text-end">
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
    <input type="hidden" name="page" value="categories">
    <input type="text" name="q"
           class="form-control form-control-sm"
           placeholder="Search category"
           value="<?=htmlspecialchars($q)?>">
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
    <th>Category</th>
    <th>Status</th>
    <th width="90">Action</th>
</tr>
</thead>
<tbody>
<?php if(!$categories): ?>
<tr><td colspan="4" class="text-center py-3">No categories</td></tr>
<?php else: foreach($categories as $c): ?>
<tr>
    <td><?=$c['id']?></td>
    <td><?=$c['category_name']?></td>
    <td><?=$c['is_active']?'Active':'Inactive'?></td>
    <td>
        <a class="btn btn-sm btn-outline-dark"
           href="index.php?page=categories&edit=<?=$c['id']?>&p=<?=$pageNum?>">
           Edit
        </a>
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
       href="index.php?page=categories&p=<?=$i?>&q=<?=urlencode($q)?>">
       <?=$i?>
    </a>
</li>
<?php endfor; ?>
</ul>
</nav>

<?php require __DIR__ . '/../footer.php'; ?>
