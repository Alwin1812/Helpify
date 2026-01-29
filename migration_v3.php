<?php
require_once 'includes/db_connect.php';

try {
    echo "Starting migration v3...\n";

    // 1. Add arrival_estimate to booking_requests
    echo "Altering booking_requests table...\n";
    try {
        $sql = "ALTER TABLE booking_requests ADD COLUMN arrival_estimate VARCHAR(100) NULL AFTER status";
        $pdo->exec($sql);
        echo "Added arrival_estimate column.\n";
    } catch (PDOException $e) {
        echo "arrival_estimate might already exist.\n";
    }

    // 2. Create notifications table
    echo "Creating notifications table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);

    echo "Migration v3 completed.\n";

} catch (PDOException $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>