<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['recent_booking_ids'])) {
    echo json_encode(['status' => 'pending']);
    exit;
}

$booking_ids = $_SESSION['recent_booking_ids'];
if (empty($booking_ids)) {
    echo json_encode(['status' => 'pending']);
    exit;
}

$placeholders = str_repeat('?,', count($booking_ids) - 1) . '?';
// Status 'accepted' in bookings means a helper has applied OR it was accepted.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE id IN ($placeholders) AND status IN ('accepted', 'confirmed')");
$stmt->execute($booking_ids);
$accepted_count = $stmt->fetchColumn();

if ($accepted_count > 0) {
    echo json_encode(['status' => 'accepted']);
} else {
    echo json_encode(['status' => 'pending']);
}
