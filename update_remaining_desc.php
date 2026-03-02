<?php
require_once 'includes/db_connect.php';

$updates = [
    'Room Painting' => 'Professional interior and exterior room painting services.',
    'Waterproofing' => 'Advanced waterproofing solutions for roofs, walls, and basements.',
    'Wallpaper Installation' => 'Expert installation of custom wallpapers and wall coverings.',
    // Wall makeover
    '3D Wall Panel' => 'Premium 3D wall panel installation for a modern aesthetic.',
    'Texture Painting' => 'Artistic textured painting styles for accent walls.',
    'Custom Wallpaper' => 'Bespoke wallpaper designs and seamless installation.',
    // AC & Appliance Reset
    'AC Service' => 'Comprehensive AC maintenance and cleaning.',
    'AC Gas Refill' => 'AC gas refilling and leak fixing.',
    'Fridge Repair' => 'Diagnostics and repair for all types of refrigerators.',
    'Washing Machine Repair' => 'Expert repairs for top/front load washing machines.'
];

try {
    $stmt = $pdo->prepare("UPDATE services SET description = ? WHERE name = ?");
    foreach ($updates as $name => $desc) {
        $stmt->execute([$desc, $name]);
        echo "Updated $name: $desc\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>