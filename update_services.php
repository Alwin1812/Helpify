<?php
require 'includes/db_connect.php';
try {
    $pdo->exec("UPDATE services SET name = 'Laundry & Dry Cleaning' WHERE id = 2");
    $pdo->exec("UPDATE services SET name = 'Car Wash & Detailing' WHERE id = 3");
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}