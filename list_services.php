<?php
require 'includes/db_connect.php';
$stmt = $pdo->query('SELECT id, name FROM services');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    echo $s['id'] . ': ' . $s['name'] . "\n";
}