<?php
require_once 'includes/db_connect.php';

try {
    // 1. Add parent_id column
    $pdo->exec("ALTER TABLE services ADD COLUMN parent_id INT DEFAULT NULL AFTER id");
    echo "Added parent_id column.\n";
} catch (PDOException $e) {
    echo "parent_id column might already exist.\n";
}

try {
    // 2. Insert sub-services for InstaHelp (ID 1)
    // We need to re-insert Cleaning, Cooking, Babysitting, Elderly Care as CHILDREN of InstaHelp
    // Note: 'Cleaning & Pest Control' (ID 4) exists, but user wants specific sub-services under InstaHelp.

    // Let's assume InstaHelp (ID 1) is the category.
    $parentId = 1;

    $subServices = [
        ['name' => 'Maid Service', 'price' => 400.00, 'icon' => 'cleaning_services'],
        ['name' => 'Cooking', 'price' => 800.00, 'icon' => 'rice_bowl'],
        ['name' => 'Babysitting', 'price' => 500.00, 'icon' => 'child_care'],
        ['name' => 'Elderly Care', 'price' => 600.00, 'icon' => 'elderly'],
        ['name' => 'Patient Care', 'price' => 700.00, 'icon' => 'medical_services']
    ];

    $stmt = $pdo->prepare("INSERT INTO services (name, description, base_price, icon, parent_id) VALUES (?, ?, ?, ?, ?)");

    foreach ($subServices as $s) {
        $stmt->execute([$s['name'], 'Service under InstaHelp', $s['price'], $s['icon'], $parentId]);
        echo "Inserted sub-service: " . $s['name'] . "\n";
    }

    // Also, let's enable sub-services for other categories if we want consistency?
    // For now align with user request: "click on instahelper shows elderly care ,cooking ,clean etc"

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>