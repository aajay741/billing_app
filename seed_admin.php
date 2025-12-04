<?php
// seed_admin.php
require_once __DIR__ . '/bootstrap.php';

$email    = 'admin@example.com';
$name     = 'Main Admin';
$password = 'admin123';

try {
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, role, is_active)
        VALUES (:name, :email, :password_hash, 'admin', 1)
    ");
    $stmt->execute([
        'name'          => $name,
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    echo "Admin user created.<br>Email: {$email}<br>Password: {$password}";
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo "Admin email already exists.";
    } else {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}
