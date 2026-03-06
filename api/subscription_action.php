<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once 'config_razorpay.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get action from GET, POST or JSON body
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? '';

switch ($action) {
    case 'get_plans':
        $stmt = $pdo->query("SELECT * FROM subscription_plans");
        $plans = $stmt->fetchAll();
        echo json_encode(['success' => true, 'plans' => $plans]);
        break;

    case 'create_plus_order':
        // Generate a Razorpay order for purchasing Helpify Plus
        $paise = 19900; // Fixed price for Plus ₹199

        // Make Razorpay API call via cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/orders");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'amount' => $paise,
            'currency' => 'INR',
            'receipt' => 'sub_buy_' . $user_id . '_' . time(),
            'payment_capture' => 1
        ]));
        curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ":" . $razorpay_key_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);

        $order = json_decode($result, true);

        if (isset($order['id'])) {
            echo json_encode(['success' => true, 'order_id' => $order['id'], 'key' => $razorpay_key_id, 'amount' => $paise]);
        } else {
            echo json_encode(['success' => false, 'error' => $order['error']['description'] ?? 'Failed to create Razorpay order.']);
        }
        break;

    case 'verify_plus_payment':
        // Verify payment and update user subscription status
        $data = $input ?: $_POST;
        $payment_id = $data['razorpay_payment_id'] ?? '';
        $order_id = $data['razorpay_order_id'] ?? '';
        $signature = $data['razorpay_signature'] ?? '';

        $generated_signature = hash_hmac('sha256', $order_id . "|" . $payment_id, $razorpay_key_secret);

        if (hash_equals($generated_signature, $signature)) {
            $expiry_date = date('Y-m-d', strtotime("+30 days"));

            $stmt = $pdo->prepare("UPDATE users SET is_plus_member = 1, plus_expiry_date = ? WHERE id = ?");
            $stmt->execute([$expiry_date, $user_id]);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Signature mismatch']);
        }
        break;

    case 'get_status':
        $stmt = $pdo->prepare("SELECT is_plus_member, plus_expiry_date FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        echo json_encode(['success' => true, 'is_plus' => (bool) $row['is_plus_member'], 'expiry' => $row['plus_expiry_date']]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>