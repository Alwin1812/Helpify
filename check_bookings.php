<?php
require 'includes/db_connect.php';
$stmt = $pdo->query('DESCRIBE bookings');
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('schema_check.txt', "Bookings Schema:\n" . print_r($bookings, true));
