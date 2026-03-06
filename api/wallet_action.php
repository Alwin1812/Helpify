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
    case 'get_balance':
        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        echo json_encode(['success' => true, 'balance' => $row['wallet_balance']]);
        break;
    case 'create_recharge_order':
        // Generate a Razorpay order for adding money to the wallet
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount']);
            exit;
        }

        $paise = (int) ($amount * 100);

        // Make Razorpay API call via cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/orders");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'amount' => $paise,
            'currency' => 'INR',
            'receipt' => 'wallet_add_' . $user_id . '_' . time(),
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

    case 'verify_recharge':
        // Verify payment and update wallet balance
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $payment_id = $data['razorpay_payment_id'] ?? '';
        $order_id = $data['razorpay_order_id'] ?? '';
        $signature = $data['razorpay_signature'] ?? '';

        // We need to know the amount from the order or session
        // For simplicity in this test environment, let's fetch the order amount from Razorpay
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/orders/$order_id");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ":" . $razorpay_key_secret);
        $result = curl_exec($ch);
        curl_close($ch);
        $order = json_decode($result, true);
        $amount = ($order['amount'] ?? 0) / 100;

        $generated_signature = hash_hmac('sha256', $order_id . "|" . $payment_id, $razorpay_key_secret);

        if (hash_equals($generated_signature, $signature)) {
            $pdo->beginTransaction();
            try {
                // Update user wallet balance
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt->execute([$amount, $user_id]);

                // Record transaction
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, razorpay_payment_id) VALUES (?, ?, 'credit', 'Added money via Razorpay', ?)");
                $stmt->execute([$user_id, $amount, $payment_id]);

                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Signature mismatch']);
        }
        break;

    case 'pay_from_wallet':
        // Pay for a booking using wallet balance
        $booking_id = $_POST['booking_id'] ?? null;
        if (!$booking_id) {
            echo json_encode(['success' => false, 'error' => 'Booking ID missing']);
            exit;
        }

        // Check if user is Plus member
        $stmt = $pdo->prepare("SELECT is_plus_member, wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $is_plus = (bool) ($user['is_plus_member'] ?? false);
        $current_balance = (float) ($user['wallet_balance'] ?? 0);

        // Fetch booking price (already includes platform fee from booking_action.php)
        $stmt = $pdo->prepare("
            SELECT total_amount, platform_fee
            FROM bookings 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$booking_id, $user_id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            echo json_encode(['success' => false, 'error' => 'Booking not found']);
            exit;
        }

        $total_price = (float) $booking['total_amount'];
        $fee = (float) $booking['platform_fee'];

        // Apply Wallet Offer: 5% Instant Discount
        $discount_pct = 5;
        $discount_amount = ($total_price * $discount_pct) / 100;
        $final_payable = $total_price - $discount_amount;

        if ($current_balance < $final_payable) {
            echo json_encode(['success' => false, 'error' => "Insufficient wallet balance. Total required (after 5% discount): ₹" . number_format($final_payable, 2)]);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Deduct from wallet (discounted amount)
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
            $stmt->execute([$final_payable, $user_id]);

            // Update booking status and record total discount (Promo + Wallet)
            $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid', payment_method = 'Wallet', total_amount = ?, discount_amount = discount_amount + ? WHERE id = ?");
            $stmt->execute([$final_payable, $discount_amount, $booking_id]);

            // Record transaction with discount info
            $desc = "Payment for Booking #$booking_id (5% Wallet Discount Applied: -₹" . number_format($discount_amount, 2) . ")";
            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
            $stmt->execute([$user_id, $final_payable, $desc]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_transactions':
        $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $transactions = $stmt->fetchAll();
        echo json_encode(['success' => true, 'transactions' => $transactions]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>