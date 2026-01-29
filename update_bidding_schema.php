<?php
require_once 'includes/db_connect.php';

try {
    // Add bid_price
    $sql = "ALTER TABLE booking_requests ADD COLUMN bid_price DECIMAL(10, 2) NULL";
    try {
        $pdo->exec($sql);
        echo "Added bid_price column.\n";
    } catch (PDOException $e) {
        echo "bid_price column might already exist or error: " . $e->getMessage() . "\n";
    }

    // Add notes
    $sql = "ALTER TABLE booking_requests ADD COLUMN notes TEXT NULL";
    try {
        $pdo->exec($sql);
        echo "Added notes column.\n";
    } catch (PDOException $e) {
        echo "notes column might already exist or error: " . $e->getMessage() . "\n";
    }

} catch (PDOException $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
?>