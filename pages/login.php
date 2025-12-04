<?php
// pages/login.php

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        if (loginUser($email, $password)) {
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $error = 'Invalid credentials or inactive user.';
        }
    }
}

require __DIR__ . '/../header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Login</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label small">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Password</label>
                        <input type="password" name="password" class="form-control form-control-sm" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-sm">
                        Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
