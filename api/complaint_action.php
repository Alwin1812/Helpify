<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = PHP_SAPI === 'cli' ? 'submit' : ($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'submit':
        $booking_id = $_POST['booking_id'] ?? null;
        $description = trim($_POST['description'] ?? '');

        if (empty($description)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a description of your issue.']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO complaints (booking_id, reporter_id, description) VALUES (?, ?, ?)");
            $stmt->execute([$booking_id ?: null, $user_id, $description]);
            $complaint_id = $pdo->lastInsertId();

            // Initial message from the user
            $stmtMes = $pdo->prepare("INSERT INTO complaint_messages (complaint_id, sender_id, message) VALUES (?, ?, ?)");
            $stmtMes->execute([$complaint_id, $user_id, "Report: " . $description]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Your complaint has been submitted. Our support team will review it shortly.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'fetch':
        try {
            // User can see their own complaints
            $stmt = $pdo->prepare("SELECT c.*, s.name as service_name FROM complaints c LEFT JOIN bookings b ON c.booking_id = b.id LEFT JOIN services s ON b.service_id = s.id WHERE c.reporter_id = ? ORDER BY c.created_at DESC");
            $stmt->execute([$user_id]);
            $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'complaints' => $complaints]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'fetch_messages':
        $complaint_id = $_GET['complaint_id'] ?? null;
        if (!$complaint_id) {
            echo json_encode(['success' => false, 'message' => 'Missing complaint ID']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT cm.*, u.name as sender_name, u.role as sender_role FROM complaint_messages cm JOIN users u ON cm.sender_id = u.id WHERE cm.complaint_id = ? ORDER BY cm.created_at ASC");
            $stmt->execute([$complaint_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'send_message':
        $complaint_id = $_POST['complaint_id'] ?? null;
        $message = trim($_POST['message'] ?? '');
        if (!$complaint_id || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO complaint_messages (complaint_id, sender_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$complaint_id, $user_id, $message]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'resolve':
        if ($_SESSION['user_role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Only admins can resolve complaints']);
            exit;
        }
        $complaint_id = $_POST['complaint_id'] ?? null;
        try {
            $stmt = $pdo->prepare("UPDATE complaints SET status = 'resolved' WHERE id = ?");
            $stmt->execute([$complaint_id]);
            echo json_encode(['success' => true, 'message' => 'Complaint marked as resolved']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>