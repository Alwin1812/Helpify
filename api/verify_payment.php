<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once 'config_razorpay.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON Payload']);
    exit;
}

$payment_id = $data['razorpay_payment_id'] ?? '';
$order_id = $data['razorpay_order_id'] ?? '';
$signature = $data['razorpay_signature'] ?? '';
$booking_id = $data['booking_id'] ?? '';

// Verify the Razorpay signature
$generated_signature = hash_hmac('sha256', $order_id . "|" . $payment_id, $razorpay_key_secret);

if (hash_equals($generated_signature, $signature)) {
    // Payment is authentic and verified
    try {
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET payment_status = 'paid', 
                razorpay_payment_id = ?, 
                razorpay_signature = ? 
            WHERE id = ? AND razorpay_order_id = ?
        ");

        $success = $stmt->execute([$payment_id, $signature, $booking_id, $order_id]);

        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not find booking or database update failed.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database exception: ' . $e->getMessage()]);
    }
} else {
    // Payment verification failed
    echo json_encode(['success' => false, 'error' => 'Razorpay Signature Mismatch! Details logged.']);
}
?>