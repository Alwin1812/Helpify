<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a promo code']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $promo = $stmt->fetch();

    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired promo code']);
        exit;
    }

    if ($promo['valid_until'] && strtotime($promo['valid_until']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This promo code has expired']);
        exit;
    }

    if ($promo['max_uses'] && $promo['current_uses'] >= $promo['max_uses']) {
        echo json_encode(['success' => false, 'message' => 'This promo code limit has been reached']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'discount_type' => $promo['discount_type'],
        'discount_value' => (float) $promo['discount_value'],
        'min_order_amount' => (float) $promo['min_order_amount'],
        'max_discount_amount' => $promo['max_discount_amount'] ? (float) $promo['max_discount_amount'] : null,
        'message' => 'Promo code applied successfully!'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
