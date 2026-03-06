<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'send':
        $booking_id = $_POST['booking_id'] ?? null;
        $receiver_id = $_POST['receiver_id'] ?? null;
        $message = trim($_POST['message'] ?? '');

        if (!$booking_id || !$receiver_id || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO messages (booking_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$booking_id, $user_id, $receiver_id, $message]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'fetch':
        $booking_id = $_GET['booking_id'] ?? null;
        if (!$booking_id) {
            echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT m.*, u.name as sender_name 
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE m.booking_id = ? 
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$booking_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'poll':
        $booking_id = $_GET['booking_id'] ?? null;
        $last_id = $_GET['last_id'] ?? 0;

        if (!$booking_id) {
            echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
            exit;
        }

        try {
            // Long polling or just a quick check
            $stmt = $pdo->prepare("
                SELECT m.*, u.name as sender_name 
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE m.booking_id = ? AND m.id > ? 
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$booking_id, $last_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>