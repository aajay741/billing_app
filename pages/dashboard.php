<?php
// pages/dashboard.php

$user = currentUser();
require __DIR__ . '/../header.php';
?>

<div class="row">
    <div class="col-12 mb-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Dashboard</h5>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="small text-muted mb-1">Logged in as</div>
                <div class="fw-semibold">
                    <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
                </div>
            </div>
        </div>
    </div>

    <!-- Placeholder cards -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="small text-muted mb-1">Today’s Sales</div>
                <div class="fs-5">₹0.00</div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="small text-muted mb-1">Total Invoices</div>
                <div class="fs-5">0</div>
            </div>
        </div>
    </div>
</div>


<!-- Chart of Accounts Overview -->
<div class="row g-3 mt-2">
    <?php
    $tot = $pdo->query("SELECT COUNT(*) FROM accounts WHERE is_active=1")->fetchColumn();
    $asset = $pdo->query("SELECT COUNT(*) FROM accounts WHERE account_type='Asset' AND is_active=1")->fetchColumn();
    $liab = $pdo->query("SELECT COUNT(*) FROM accounts WHERE account_type='Liability' AND is_active=1")->fetchColumn();
    $income = $pdo->query("SELECT COUNT(*) FROM accounts WHERE account_type='Income' AND is_active=1")->fetchColumn();
    $expense = $pdo->query("SELECT COUNT(*) FROM accounts WHERE account_type='Expense' AND is_active=1")->fetchColumn();
    ?>

    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header small fw-semibold text-dark py-2">
                Chart of Accounts Overview
            </div>
            <div class="card-body small">
                <div class="row text-center">

                    <div class="col-md-3 mb-2">
                        <div class="fw-bold fs-6"><?=$asset?></div>
                        <div class="text-muted">Assets</div>
                    </div>

                    <div class="col-md-3 mb-2">
                        <div class="fw-bold fs-6"><?=$liab?></div>
                        <div class="text-muted">Liabilities</div>
                    </div>

                    <div class="col-md-3 mb-2">
                        <div class="fw-bold fs-6"><?=$income?></div>
                        <div class="text-muted">Income</div>
                    </div>

                    <div class="col-md-3 mb-2">
                        <div class="fw-bold fs-6"><?=$expense?></div>
                        <div class="text-muted">Expenses</div>
                    </div>

                </div>

                <hr class="my-2">

                <div class="d-flex justify-content-between align-items-center">
                    <div class="small">
                        Total Active Accounts:
                        <strong><?=$tot?></strong>
                    </div>

                    <a href="index.php?page=accounts" class="btn btn-sm btn-outline-dark">
                        Manage Accounts
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
