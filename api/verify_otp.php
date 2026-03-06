<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'helper') {
    $_SESSION['error'] = "Unauthorized access.";
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $booking_id = $_POST['booking_id'] ?? null;
    $otp = $_POST['otp'] ?? null;
    $type = $_POST['type'] ?? null; // 'start' or 'end'

    if (!$booking_id || !$otp || !$type) {
        $_SESSION['error'] = "Invalid verification request.";
        header('Location: ../helper_dashboard.php');
        exit;
    }

    // Fetch booking details
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND helper_id = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $_SESSION['error'] = "Booking not found or not assigned to you.";
        header('Location: ../helper_dashboard.php');
        exit;
    }

    if ($type === 'start') {
        if ($booking['start_otp'] === $otp) {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'in-progress', started_at = NOW() WHERE id = ?");
            $stmt->execute([$booking_id]);
            $_SESSION['success'] = "Job started successfully! Please complete the task.";
        } else {
            $_SESSION['error'] = "Invalid Start OTP. Please check with the client.";
        }
    } elseif ($type === 'end') {
        if ($booking['end_otp'] === $otp) {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$booking_id]);
            $_SESSION['success'] = "Job completed successfully! Well done.";
        } else {
            $_SESSION['error'] = "Invalid Completion OTP. Please check with the client.";
        }
    }

    header('Location: ../helper_dashboard.php');
    exit;
}
?>