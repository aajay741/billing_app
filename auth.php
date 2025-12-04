<?php
// auth.php

function loginUser(string $email, string $password): bool
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Store only required fields in session
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];

    return true;
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function isAdmin(): bool
{
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: index.php?page=login');
        exit;
    }
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Access denied.</p>';
        exit;
    }
}
