<?php
require 'includes/db_connect.php';
$out = "";
foreach (['reviews', 'booking_requests', 'notifications', 'bookings'] as $t) {
    $out .= "================ Table: $t ================\n";
    $stmt = $pdo->query("SHOW CREATE TABLE $t");
    if ($stmt)
        $out .= $stmt->fetchColumn(1) . "\n\n";
}
file_put_contents('schema_output.php.txt', $out);
?>