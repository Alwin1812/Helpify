<?php
require_once 'includes/db_connect.php';

try {
    // Modify status column ENUM to include 'accepted'
    $sql = "ALTER TABLE bookings MODIFY COLUMN status ENUM('pending', 'accepted', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending'";
    $pdo->exec($sql);
    echo "Successfully updated bookings table status ENUM to include 'accepted'.\n";

} catch (PDOException $e) {
    echo "Error updating table: " . $e->getMessage() . "\n";
}
?>