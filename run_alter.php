<?php
require_once 'includes/db_connect.php';
try {
    $pdo->query("ALTER TABLE users ADD COLUMN password_changed TINYINT(1) DEFAULT 0");
    echo "Column added successfully.\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>