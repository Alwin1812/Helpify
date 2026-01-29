<?php
require_once 'includes/db_connect.php';

try {
    echo "Adding phone_number and address columns...\n";
    $sql = "ALTER TABLE users 
            ADD COLUMN phone_number VARCHAR(20) NULL,
            ADD COLUMN address TEXT NULL";
    $pdo->exec($sql);
    echo "Migration successful.\n";
} catch (PDOException $e) {
    echo "Error (maybe columns exist): " . $e->getMessage() . "\n";
}
