<?php
require_once 'includes/db_connect.php';

try {
    // Parent ID for 'Electrician, Plumber & Carpenter' is 5
    $parentId = 5;

    $subServices = [
        ['name' => 'Electrician', 'price' => 399.00, 'icon' => 'electrical_services'],
        ['name' => 'Plumber', 'price' => 399.00, 'icon' => 'plumbing'],
        ['name' => 'Carpenter', 'price' => 499.00, 'icon' => 'construction']
    ];

    $stmt = $pdo->prepare("INSERT INTO services (name, description, base_price, icon, parent_id) VALUES (?, ?, ?, ?, ?)");

    foreach ($subServices as $s) {
        // Check if already exists to avoid duplicates
        $check = $pdo->prepare("SELECT id FROM services WHERE name = ? AND parent_id = ?");
        $check->execute([$s['name'], $parentId]);
        if (!$check->fetch()) {
            $stmt->execute([$s['name'], 'Expert ' . strtolower($s['name']) . ' services', $s['price'], $s['icon'], $parentId]);
            echo "Inserted sub-service: " . $s['name'] . "\n";
        } else {
            echo "Sub-service " . $s['name'] . " already exists.\n";
        }
    }

    echo "Handyman services updated successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>