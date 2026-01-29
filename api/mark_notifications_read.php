<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Redirect back to dashboard
    header('Location: ../dashboard.php');
    exit;
} catch (PDOException $e) {
    echo "Error clearing notifications.";
}
?>