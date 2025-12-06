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
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name']);
    $phone = trim($_POST['phone']);

    if ($name === "")  $errors[] = "Name required.";
    if ($phone === "") $errors[] = "Phone required.";

    if (!$errors) {

        // Common values
        $data = [
            'n'  => $_POST['name'],
            'p'  => $_POST['phone'],
            'e'  => $_POST['email'],
            'a'  => $_POST['address'],
            'c'  => $_POST['city'],
            's'  => $_POST['state'],
            'pc' => $_POST['pincode'],
            'g'  => $_POST['gst_number']
        ];

        if ($id > 0) {
            // DIRECT UPDATE BY ID
            $stmt = $pdo->prepare("UPDATE customers SET 
                name=:n, phone=:p, email=:e, address=:a, city=:c, state=:s, 
                pincode=:pc, gst_number=:g
                WHERE id=:id");
            $data['id'] = $id;
            $stmt->execute($data);
            $success = "Updated";

        } else {
            // NEW ENTRY: CHECK IF PHONE ALREADY EXISTS
            $check = $pdo->prepare("SELECT id FROM customers WHERE phone = :p LIMIT 1");
            $check->execute(['p' => $phone]);
            $existingId = $check->fetchColumn();

            if ($existingId) {
                // PHONE EXISTS -> UPDATE THAT RECORD INSTEAD OF INSERT
                $stmt = $pdo->prepare("UPDATE customers SET 
                    name=:n, phone=:p, email=:e, address=:a, city=:c, state=:s, 
                    pincode=:pc, gst_number=:g
                    WHERE id=:id");
                $data['id'] = $existingId;
                $stmt->execute($data);
                $success = "Existing customer updated";
            } else {
                // PHONE NOT FOUND -> INSERT NEW
                $stmt = $pdo->prepare("INSERT INTO customers
                    (name, phone, email, address, city, state, pincode, gst_number)
                    VALUES (:n,:p,:e,:a,:c,:s,:pc,:g)");
                $stmt->execute($data);
                $success = "Customer added";
            }
        }
    }
}

// LOAD DATA FOR EDIT
$editData = [
    'id'=>"", 'name'=>"", 'phone'=>"", 'email'=>"", 
    'address'=>"", 'city'=>"", 'state'=>"", 'pincode'=>"", 'gst_number'=>""
];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch() ?: $editData;
}

// SEARCH + LIST
$q = trim($_GET['q'] ?? '');
$sql = "FROM customers WHERE 1";
$params = [];
if ($q !== "") {
    $sql .= " AND (name LIKE :q OR phone LIKE :q OR email LIKE :q)";
    $params['q'] = "%$q%";
}

$cstmt = $pdo->prepare("SELECT COUNT(*) $sql");
$cstmt->execute($params);
$totalRows = $cstmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT * $sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<!-- INLINE FORM -->
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
            <label>Email</label>
            <input name="email" value="<?=$editData['email']?>"
                   class="form-control form-control-sm">
        </div>

        <div class="col-md-2">
            <label>Address</label>
            <input name="address" value="<?=$editData['address']?>"
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

        <div class="col-md-2">
            <label>GST Number</label>
            <input name="gst_number" value="<?=$editData['gst_number']?>"
                   class="form-control form-control-sm">
        </div>

        <div class="col-md-2 text-end">
            <button class="btn btn-dark btn-sm w-100">
                <?=$editData['id'] ? 'Update' : 'Save'?>
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
        <input type="hidden" name="page" value="customers">
        <input type="text" name="q"
               class="form-control form-control-sm"
               placeholder="Search name, phone, email"
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
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>City</th>
                <th>GST</th>
                <th width="90">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if(!$customers): ?>
                <tr><td colspan="7" class="text-center py-3">No data</td></tr>
            <?php else: foreach($customers as $c): ?>
                <tr>
                    <td><?=$c['id']?></td>
                    <td><?=$c['name']?></td>
                    <td><?=$c['phone']?></td>
                    <td><?=$c['email']?></td>
                    <td><?=$c['city']?></td>
                    <td><?=$c['gst_number']?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-dark"
                           href="index.php?page=customers&edit=<?=$c['id']?>&p=<?=$pageNum?>">
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
        <?php for($i=1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?=$i==$pageNum?'active':''?>">
                <a class="page-link"
                   href="index.php?page=customers&p=<?=$i?>&q=<?=urlencode($q)?>">
                    <?=$i?>
                </a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>

<?php require __DIR__ . '/../footer.php'; ?>
