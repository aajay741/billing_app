<?php
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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background:#f4f5f7; font-size:14px; }

        .top-nav {
            height:54px; background:#fff; border-bottom:1px solid #dcdde1;
            position:fixed; left:0; right:0; top:0; z-index:1000;
            padding:0 18px; display:flex; align-items:center;
        }
        .top-nav .navbar-brand { font-size:15px; font-weight:600; color:#222; }

        .sidebar {
            width:200px; background:#fff;
            position:fixed; top:54px; bottom:0; left:0;
            padding-top:8px; border-right:1px solid #dcdde1;
            overflow-y:auto;
        }
        .sidebar a {
            display:flex; align-items:center; gap:10px;
            padding:9px 14px; color:#222; text-decoration:none;
            font-size:13px; border-left:3px solid transparent;
        }
        .sidebar a i { font-size:15px; width:18px; text-align:center; }
        .sidebar a:hover { background:#eef3fb; }
        .sidebar a.active {
            background:#e1e9ff; color:#0d6efd;
            border-left-color:#0d6efd; font-weight:600;
        }

        /* SECTION HEADERS */
        .menu-section {
            font-size:11px;
            text-transform:uppercase;
            font-weight:700;
            color:#555;
            margin-top:12px;
            padding:6px 14px;
            border-left:3px solid #0d6efd;
            display:flex; align-items:center; gap:6px;
            background:#f4f7ff;
        }
        .menu-section i { font-size:14px; }

        .content-area {
            margin-left:200px; margin-top:64px;
            padding:15px 20px;
        }
    </style>
</head>
<body>

<?php if (!$isLoginPage): ?>

<div class="top-nav d-flex justify-content-between">
    <a class="navbar-brand" href="index.php?page=dashboard"><?= htmlspecialchars(APP_NAME) ?></a>
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

<div class="sidebar">

    <a href="index.php?page=dashboard" class="<?= $page==='dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <!-- SALES GROUP -->
    <div class="menu-section"><i class="bi bi-receipt"></i> Sales</div>
    <a href="index.php?page=sales" class="<?= ($page==='sales' || $page==='sales_view') ? 'active' : '' ?>">
        <i class="bi bi-receipt-cutoff"></i> Sales Invoice
    </a>
    <a href="index.php?page=sales_returns" class="<?= ($page==='sales_returns' || $page==='sales_return_view') ? 'active' : '' ?>">
        <i class="bi bi-arrow-90deg-left"></i> Sales Returns
    </a>

    <!-- PURCHASE GROUP -->
    <div class="menu-section"><i class="bi bi-cart"></i> Purchases</div>
    <a href="index.php?page=purchases" class="<?= ($page==='purchases' || $page==='purchase_view') ? 'active' : '' ?>">
        <i class="bi bi-cart-check"></i> Purchase Invoice
    </a>
    <a href="index.php?page=purchase_returns" class="<?= ($page==='purchase_returns' || $page==='purchase_return_view') ? 'active' : '' ?>">
        <i class="bi bi-arrow-90deg-right"></i> Purchase Returns
    </a>
    <a href="index.php?page=supplier_payments" class="<?= $page==='supplier_payments' ? 'active' : '' ?>">
        <i class="bi bi-cash"></i> Supplier Payments
    </a>
    <a href="index.php?page=supplier_ledger" class="<?= $page==='supplier_ledger' ? 'active' : '' ?>">
        <i class="bi bi-journal-text"></i> Supplier Ledger
    </a>

    <!-- INVENTORY GROUP -->
    <div class="menu-section"><i class="bi bi-box"></i> Inventory</div>
    <a href="index.php?page=products" class="<?= $page==='products' ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Products
    </a>
    <a href="index.php?page=categories" class="<?= $page==='categories' ? 'active' : '' ?>">
        <i class="bi bi-tags"></i> Categories
    </a>
    <a href="index.php?page=stock_ledger" class="<?= $page==='stock_ledger' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Stock Ledger
    </a>

    <!-- ACCOUNTS GROUP -->
    <div class="menu-section"><i class="bi bi-journal"></i> Accounts</div>
    <a href="index.php?page=accounts" class="<?= $page==='accounts' ? 'active' : '' ?>">
        <i class="bi bi-journal-bookmark-fill"></i> Chart of Accounts
    </a>

    <!-- CRM GROUP -->
    <div class="menu-section"><i class="bi bi-people"></i> CRM</div>
    <a href="index.php?page=customers" class="<?= $page==='customers' ? 'active' : '' ?>">
        <i class="bi bi-person-vcard"></i> Customers
    </a>
    <a href="index.php?page=suppliers" class="<?= $page==='suppliers' ? 'active' : '' ?>">
        <i class="bi bi-briefcase"></i> Suppliers
    </a>

    <!-- ADMIN GROUP -->
    <div class="menu-section"><i class="bi bi-gear"></i> Administration</div>
    <a href="index.php?page=settings" class="<?= $page==='settings' ? 'active' : '' ?>">
        <i class="bi bi-gear-wide"></i> Company Settings
    </a>
    <a href="index.php?page=users" class="<?= $page==='users' ? 'active' : '' ?>">
        <i class="bi bi-person-gear"></i> User Management
    </a>

</div>

<div class="content-area">

<?php else: ?>
<div class="container mt-5">
<?php endif; ?>
