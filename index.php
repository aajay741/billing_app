<?php
// index.php

require_once __DIR__ . '/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';

switch ($page) {

    case 'login':
        if (isLoggedIn()) {
            header('Location: index.php?page=dashboard');
            exit;
        }
        require __DIR__ . '/pages/login.php';
        break;

    case 'logout':
        logoutUser();
        header('Location: index.php?page=login');
        exit;

    case 'users':
        requireLogin();
        requireAdmin();
        require __DIR__ . '/pages/users.php';
        break;

    case 'settings':
        requireLogin();
        requireAdmin();
        require __DIR__ . '/pages/settings.php';
        break;

    case 'customers':
        requireLogin();
        require __DIR__ . '/pages/customers.php';
        break;

    case 'suppliers':
        requireLogin();
        require __DIR__ . '/pages/suppliers.php';
        break;

    case 'categories':
        requireLogin();
        require __DIR__ . '/pages/categories.php';
        break;

    case 'products':
        requireLogin();
        require __DIR__ . '/pages/products.php';
        break;

    case 'accounts':
        requireLogin();
        require __DIR__ . '/pages/accounts.php';
        break;

    case 'stock_ledger':
        requireLogin();
        require __DIR__ . '/pages/stock_ledger.php';
        break;

    // NEW: Purchase invoice list + add
    case 'purchases':
        requireLogin();
        require __DIR__ . '/pages/purchases.php';
        break;

    // NEW: Purchase view/print
    case 'purchase_view':
        requireLogin();
        require __DIR__ . '/pages/purchase_view.php';
        break;
        
    case 'sales':
        requireLogin();
        require __DIR__ . '/pages/sales.php';
        break;

    case 'sales_view':
        requireLogin();
        require __DIR__ . '/pages/sales_view.php';
        break;


        case 'sales_returns':
        requireLogin();
        require __DIR__ . '/pages/sales_returns.php';
        break;

    case 'sales_return_view':
        requireLogin();
        require __DIR__ . '/pages/sales_return_view.php';
        break;

    case 'purchase_returns':
        requireLogin();
        require __DIR__ . '/pages/purchase_returns.php';
        break;

    case 'purchase_return_view':
        requireLogin();
        require __DIR__ . '/pages/purchase_return_view.php';
        break;

case 'supplier_payments':
    requireLogin();
    require __DIR__ . '/pages/supplier_payments.php';
    break;
    case 'supplier_ledger':
        requireLogin();
        require __DIR__ . '/pages/supplier_ledger.php';
        break;          

        
    case 'dashboard':
    default:
        requireLogin();
        require __DIR__ . '/pages/dashboard.php';
        break;
}
