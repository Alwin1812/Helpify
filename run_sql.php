<?php
require_once 'includes/db_connect.php';

try {
    // Add job_role column
    $sql = "ALTER TABLE users ADD COLUMN job_role VARCHAR(100) DEFAULT NULL AFTER role";
    try {
        $pdo->exec($sql);
        echo "Added job_role column.\n";
    } catch (PDOException $e) {
        echo "job_role column might already exist.\n";
    }

    // Add gender column
    $sql = "ALTER TABLE users ADD COLUMN gender ENUM('Male', 'Female', 'Other') DEFAULT NULL AFTER email";
    try {
        $pdo->exec($sql);
        echo "Added gender column.\n";
    } catch (PDOException $e) {
        echo "gender column might already exist.\n";
    }

    // Add icon column to services
    $sql = "ALTER TABLE services ADD COLUMN icon VARCHAR(255) DEFAULT 'cleaning_services'";
    try {
        $pdo->exec($sql);
        echo "Added icon column to services.\n";
    } catch (PDOException $e) {
        echo "icon column might already exist.\n";
    }

} catch (PDOException $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
?>