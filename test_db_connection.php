<?php
require_once 'includes/db_connect.php';

if ($pdo) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed (pdo is null but no exception thrown?)";
}
?>