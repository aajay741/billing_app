<?php
// index.php

require_once __DIR__ . '/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';

// Simple routing
switch ($page) {
    case 'login':
        // If already logged in, go to dashboard
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

    
    case 'dashboard':
    default:
        requireLogin();
        require __DIR__ . '/pages/dashboard.php';
        break;
}
