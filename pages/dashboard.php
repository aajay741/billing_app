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

    <!-- Placeholder cards for future billing modules -->
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

<?php require __DIR__ . '/../footer.php'; ?>
