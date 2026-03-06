<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'helper') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$helper_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
    exit;
}

try {
    // Check if the booking is assigned to this helper and payment method is Cash
    $stmt = $pdo->prepare("SELECT id, payment_method, helper_id FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    if ($booking['helper_id'] != $helper_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized action on this booking']);
        exit;
    }

    if ($booking['payment_method'] != 'Cash') {
        echo json_encode(['success' => false, 'message' => 'Payment method is not Cash']);
        exit;
    }

    // Update payment status to 'paid'
    $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
    $stmt->execute([$booking_id]);

    // Create notification for the user
    $stmt = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $user_id = $stmt->fetchColumn();

    $message = "Your cash payment for booking #$booking_id has been confirmed by the helper.";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'info')");
    $stmt->execute([$user_id, $message]);

    echo json_encode(['success' => true, 'message' => 'Payment marked as received']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>