<?php
require __DIR__ . '/../header.php';

$errors = [];
$success = "";

// PAGINATION
$limit = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNum - 1) * $limit;

// SAVE FORM (ADD + EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if ($name === "") $errors[] = "Name required.";
    if ($email === "") $errors[] = "Email required.";
    if (!in_array($role, ['admin','staff'], true)) $errors[] = "Invalid role.";

    if ($id === 0 && $password === "") $errors[] = "Password required for new user.";

    if (!$errors) {
        if ($id > 0) {
            // UPDATE
            if ($password !== "") {
                $stmt = $pdo->prepare("UPDATE users SET
                    name=:n, email=:e, role=:r, is_active=:a,
                    password_hash=:ph
                    WHERE id=:id");
                $stmt->execute([
                    'n'=>$name,'e'=>$email,'r'=>$role,'a'=>$active,
                    'ph'=>password_hash($password, PASSWORD_DEFAULT),
                    'id'=>$id
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET
                    name=:n, email=:e, role=:r, is_active=:a
                    WHERE id=:id");
                $stmt->execute([
                    'n'=>$name,'e'=>$email,'r'=>$role,'a'=>$active,'id'=>$id
                ]);
            }
            $success = "User updated";
        } else {
            // INSERT
            try {
                $stmt = $pdo->prepare("INSERT INTO users
                    (name, email, password_hash, role, is_active)
                    VALUES (:n,:e,:ph,:r,:a)");
                $stmt->execute([
                    'n'=>$name,'e'=>$email,
                    'ph'=>password_hash($password, PASSWORD_DEFAULT),
                    'r'=>$role,'a'=>$active
                ]);
                $success = "User created";
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') $errors[] = "Email already exists.";
                else $errors[] = "Error creating user.";
            }
        }
    }
}

// LOAD DATA FOR EDIT
$edit = [
    'id'=>"", 'name'=>"", 'email'=>"",'role'=>"staff",
    'is_active'=>1
];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([ (int)$_GET['edit'] ]);
    $edit = $stmt->fetch() ?: $edit;
}

// SEARCH & LIST
$q = trim($_GET['q'] ?? '');
$sql = "FROM users WHERE 1";
$params = [];

if ($q !== "") {
    $sql .= " AND (name LIKE :q OR email LIKE :q)";
    $params['q'] = "%$q%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $sql");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT id,name,email,role,is_active,created_at
    $sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!-- INLINE ADD/EDIT FORM -->
<div class="p-3 border rounded mb-3 bg-white shadow-sm">
<form method="post" class="row g-3 align-items-end small">
    <input type="hidden" name="id" value="<?=$edit['id']?>">

    <div class="col-md-2">
        <label>Name*</label>
        <input name="name" value="<?=$edit['name']?>" class="form-control form-control-sm" required>
    </div>

    <div class="col-md-2">
        <label>Email*</label>
        <input name="email" value="<?=$edit['email']?>" class="form-control form-control-sm" required>
    </div>

    <div class="col-md-2">
        <label>Role</label>
        <select name="role" class="form-select form-select-sm">
            <option value="staff" <?=$edit['role']=='staff'?'selected':''?>>Staff</option>
            <option value="admin" <?=$edit['role']=='admin'?'selected':''?>>Admin</option>
        </select>
    </div>

    <div class="col-md-2">
        <label>Password<?=$edit['id']?' (optional)':'*'?></label>
        <input name="password" type="password" class="form-control form-control-sm" <?=$edit['id']?'':'required'?>>
    </div>

    <div class="col-md-2">
        <label>Status</label>
        <select name="is_active" class="form-select form-select-sm">
            <option value="1" <?=$edit['is_active']?'selected':''?>>Active</option>
            <option value="0" <?=!$edit['is_active']?'selected':''?>>Inactive</option>
        </select>
    </div>

    <div class="col-md-2 text-end">
        <button class="btn btn-dark btn-sm w-100">
            <?=$edit['id']?'Update':'Save'?>
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

<!-- SEARCH BAR -->
<div class="d-flex justify-content-between align-items-center mb-2 small">
    <form method="get" class="d-flex">
        <input type="hidden" name="page" value="users">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Search name or email"
               value="<?=htmlspecialchars($q)?>">
        <button class="btn btn-sm btn-dark ms-2">Search</button>
    </form>
</div>

<!-- LIST TABLE -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 small align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th width="90">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!$users): ?>
                <tr><td colspan="7" class="text-center py-3">No Users</td></tr>
            <?php else: foreach($users as $u): ?>
                <tr>
                    <td><?=$u['id']?></td>
                    <td><?=$u['name']?></td>
                    <td><?=$u['email']?></td>
                    <td><?=$u['role']?></td>
                    <td><?=$u['is_active']?'Active':'Inactive'?></td>
                    <td><?=$u['created_at']?></td>
                    <td>
                        <a href="index.php?page=users&edit=<?=$u['id']?>&p=<?=$pageNum?>"
                           class="btn btn-sm btn-outline-dark">Edit</a>
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
       href="index.php?page=users&p=<?=$i?>&q=<?=urlencode($q)?>">
       <?=$i?>
    </a>
</li>
<?php endfor; ?>
</ul>
</nav>

<?php require __DIR__ . '/../footer.php'; ?>
