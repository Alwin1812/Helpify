<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'helper') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$helper_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_stats':
        // Get total earnings (all-time)
        $stmt = $pdo->prepare("SELECT SUM(total_amount * 0.8) as earnings FROM bookings WHERE helper_id = ? AND status = 'completed' AND payment_status = 'paid'");
        $stmt->execute([$helper_id]);
        $total_earnings = (float) $stmt->fetchColumn() ?: 0.00;

        // Get recent daily earnings for graph (last 7 days)
        $stmt = $pdo->prepare("
            SELECT DATE(completed_at) as date, SUM(total_amount * 0.8) as earnings 
            FROM bookings 
            WHERE helper_id = ? AND status = 'completed' AND payment_status = 'paid' 
            AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
            GROUP BY DATE(completed_at) 
            ORDER BY date ASC
        ");
        $stmt->execute([$helper_id]);
        $daily_earnings = $stmt->fetchAll();

        // Get pending withdrawals
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawal_requests WHERE helper_id = ? AND status IN ('pending', 'approved')");
        $stmt->execute([$helper_id]);
        $pending_withdrawals = (float) $stmt->fetchColumn() ?: 0.00;

        $withdrawable_balance = $total_earnings - $pending_withdrawals; // Need to subtract already COMPLETED withdrawals too if I track them separately

        // Actually I should track completed withdrawals too
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawal_requests WHERE helper_id = ? AND status = 'completed'");
        $stmt->execute([$helper_id]);
        $completed_withdrawals = (float) $stmt->fetchColumn() ?: 0.00;

        $withdrawable_balance = $total_earnings - $pending_withdrawals - $completed_withdrawals;

        echo json_encode([
            'success' => true,
            'total_earnings' => $total_earnings,
            'withdrawable_balance' => max(0, $withdrawable_balance),
            'daily_stats' => $daily_earnings
        ]);
        break;

    case 'request_withdrawal':
        $amount = (float) ($_POST['amount'] ?? 0);
        $bank_details = $_POST['bank_details'] ?? '';

        if ($amount <= 0 || empty($bank_details)) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount or bank details']);
            exit;
        }

        // Check if helper has enough balance
        // Re-calculate balance properly
        $stmt = $pdo->prepare("SELECT SUM(total_amount * 0.8) FROM bookings WHERE helper_id = ? AND status = 'completed' AND payment_status = 'paid'");
        $stmt->execute([$helper_id]);
        $total = (float) $stmt->fetchColumn() ?: 0.00;

        $stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawal_requests WHERE helper_id = ? AND status IN ('pending', 'approved', 'completed')");
        $stmt->execute([$helper_id]);
        $withdrawn = (float) $stmt->fetchColumn() ?: 0.00;

        $current_balance = $total - $withdrawn;

        if ($amount > $current_balance) {
            echo json_encode(['success' => false, 'error' => 'Insufficient balance']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO withdrawal_requests (helper_id, amount, bank_details) VALUES (?, ?, ?)");
        $stmt->execute([$helper_id, $amount, $bank_details]);

        echo json_encode(['success' => true, 'message' => 'Withdrawal request submitted successfully']);
        break;

    case 'get_withdrawals':
        $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE helper_id = ? ORDER BY created_at DESC");
        $stmt->execute([$helper_id]);
        $withdrawals = $stmt->fetchAll();
        echo json_encode(['success' => true, 'withdrawals' => $withdrawals]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>