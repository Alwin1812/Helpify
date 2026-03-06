<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once 'config_razorpay.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$booking_id = $_POST['booking_id'] ?? null;
if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Booking ID missing']);
    exit;
}

try {
    // Get total price from booking record (already calculated in booking_action.php)
    $stmt = $pdo->prepare("
        SELECT total_amount 
        FROM bookings 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Invalid booking or unauthorized']);
        exit;
    }

    $total_price = (float) $row['total_amount'];

    $amount = (int) ($total_price * 100); // converting to paise

    // Make Razorpay API call via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/orders");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'amount' => $amount,
        'currency' => 'INR',
        'receipt' => 'receipt_' . $booking_id,
        'payment_capture' => 1
    ]));
    curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ":" . $razorpay_key_secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $order = json_decode($result, true);

    if (isset($order['id'])) {
        // Update booking with the new order ID
        $updateStmt = $pdo->prepare("UPDATE bookings SET razorpay_order_id = ? WHERE id = ?");
        $updateStmt->execute([$order['id'], $booking_id]);

        echo json_encode([
            'success' => true,
            'order_id' => $order['id'],
            'amount' => $amount,
            'key' => $razorpay_key_id
        ]);
    } else {
        // e.g. Invalid API keys
        echo json_encode([
            'success' => false,
            'error' => $order['error']['description'] ?? 'Failed to create Razorpay order. (Check API Keys)'
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>