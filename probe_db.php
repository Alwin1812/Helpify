<?php
$ports = [3306, 3307, 3308, 8889];
$host = '127.0.0.1';
$username = 'root';
$password = '';

echo "Probing MySQL ports...\n";

foreach ($ports as $port) {
    echo "Trying port $port... ";
    try {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2];
        $pdo = new PDO($dsn, $username, $password, $options);
        echo "SUCCESS! Connection established on port $port.\n";
        exit(0);
    } catch (PDOException $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}

echo "All attempts failed. MySQL might not be running.\n";
?>