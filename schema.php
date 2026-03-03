<?php
$pdo = new PDO('mysql:host=localhost;dbname=helpify', 'root', '');
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "Table: $t\n";
    echo $pdo->query("SHOW CREATE TABLE $t")->fetchColumn(1) . "\n\n";
}
?>