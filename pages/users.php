<?php
// pages/users.php

$errors = [];
$success = '';

// Handle new user form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($email === '') {
        $errors[] = 'Email is required.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        $errors[] = 'Invalid role.';
    }

    if (!$errors) {
        try {
            global $pdo;
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role, is_active)
                VALUES (:name, :email, :password_hash, :role, :is_active)
            ");
            $stmt->execute([
                'name'         => $name,
                'email'        => $email,
                'password_hash'=> password_hash($password, PASSWORD_DEFAULT),
                'role'         => $role,
                'is_active'    => $active,
            ]);
            $success = 'User created successfully.';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // duplicate email
                $errors[] = 'Email already exists.';
            } else {
                $errors[] = 'Error creating user.';
            }
        }
    }
}

// Fetch users
global $pdo;
$stmt = $pdo->query("SELECT id, name, email, role, is_active, created_at FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();

require __DIR__ . '/../header.php';
?>


<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header">
                <h6 class="mb-0 small">Add User</h6>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success py-2 small">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="mb-2">
                        <label class="form-label small">Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Role</label>
                        <select name="role" class="form-select form-select-sm">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Password</label>
                        <input type="password" name="password" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label small" for="is_active">
                            Active
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        Save User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header">
                <h6 class="mb-0 small">User List</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="small">
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                        <?php if (!$users): ?>
                            <tr>
                                <td colspan="6" class="text-center py-3">No users found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= (int)$u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['name']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= htmlspecialchars($u['role']) ?></td>
                                    <td>
                                        <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                                    </td>
                                    <td><?= htmlspecialchars($u['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
