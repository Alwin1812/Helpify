<?php
require_once 'includes/db_connect.php';

try {
    $email = 'admin@helpify.com';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        echo "Admin user already exists.";
    } else {
        $name = 'System Admin';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $role = 'admin';

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role]);
        echo "Admin user created successfully. Email: admin@helpify.com, Password: admin123";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>