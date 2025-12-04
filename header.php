<?php
// header.php
$user = currentUser();
$page = $_GET['page'] ?? '';
$isLoginPage = ($page === 'login');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f5f6f7;
            font-size: 14px;
        }

        /* Top Navigation */
        .top-nav {
            height: 60px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            position: fixed;
            left: 0; right: 0; top: 0;
            z-index: 1000;
            padding: 0 18px;
            display: flex;
            align-items: center;
        }
        .top-nav .navbar-brand {
            font-size: 16px;
            font-weight: 600;
        }

        /* Sidebar */
        .sidebar {
            width: 175px;
            background: #fff;
            position: fixed;
            top: 60px;
            bottom: 0;
            left: 0;
            padding: 10px 0;
            border-right: 1px solid #ddd;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: #111;
            text-decoration: none;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        .sidebar a i {
            font-size: 15px;
            width: 18px;
            text-align: center;
        }
        .sidebar a:hover {
            background: #f0f0f0;
        }
        .sidebar a.active {
            background: #e8f0ff;
            color: #0d6efd;
            border-left-color: #0d6efd;
        }

        /* Main Content */
        .content-area {
            margin-left: 175px;
            margin-top: 70px;
            padding: 18px 20px;
        }
    </style>
</head>
<body>

<?php if (!$isLoginPage): ?>

<!-- Top Navigation -->
<div class="top-nav d-flex justify-content-between">
    <a class="navbar-brand text-dark" href="index.php?page=dashboard">
        <?= htmlspecialchars(APP_NAME) ?>
    </a>

    <?php if ($user): ?>
        <div class="d-flex align-items-center">
            <span class="small me-3">
                <i class="bi bi-person"></i>
                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
            </span>
            <a href="index.php?page=logout" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    <?php endif; ?>
</div>

<!-- Sidebar Menu -->
<div class="sidebar">
    <a href="index.php?page=dashboard"  class="<?= $page==='dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer"></i> Dashboard
    </a>
    <a href="index.php?page=sales" class="<?= $page==='sales' ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i> Sales Invoice
    </a>
    <a href="index.php?page=purchase" class="<?= $page==='purchase' ? 'active' : '' ?>">
        <i class="bi bi-truck"></i> Purchase Invoice
    </a>
    <a href="index.php?page=inventory" class="<?= $page==='inventory' ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Inventory
    </a>
    <a href="index.php?page=customers" class="<?= $page==='customers' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Customers
    </a>
    <a href="index.php?page=suppliers" class="<?= $page==='suppliers' ? 'active' : '' ?>">
        <i class="bi bi-briefcase"></i> Suppliers
    </a>
    <a href="index.php?page=accounting" class="<?= $page==='accounting' ? 'active' : '' ?>">
        <i class="bi bi-calculator"></i> Accounting
    </a>
    <a href="index.php?page=settings" class="<?= $page==='settings' ? 'active' : '' ?>">
        <i class="bi bi-gear"></i> Company Settings
    </a>
    <a href="index.php?page=users" class="<?= $page==='users' ? 'active' : '' ?>">
        <i class="bi bi-person-gear"></i> Users
    </a>
</div>

<!-- Main Content Start -->
<div class="content-area">

<?php else: ?>

<!-- Login Page Layout -->
<div class="container mt-5">

<?php endif; ?>
