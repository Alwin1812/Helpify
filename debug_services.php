<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("SELECT id, name FROM services");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($services as $s) {
    echo "ID: " . $s['id'] . " | Name: '" . $s['name'] . "'\n";
    $filename_underscore = 'service_' . strtolower(str_replace(' ', '_', $s['name'])) . '.png';
    echo "Expected Underscore: " . $filename_underscore . "\n";
}
?>