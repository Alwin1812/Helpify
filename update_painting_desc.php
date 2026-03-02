<?php
require_once 'includes/db_connect.php';

$updates = [
    'Room Painting' => 'Professional interior and exterior room painting services.',
    'Waterproofing' => 'Advanced waterproofing solutions for roofs, walls, and basements.',
    'Wallpaper Installation' => 'Expert installation of custom wallpapers and wall coverings.'
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