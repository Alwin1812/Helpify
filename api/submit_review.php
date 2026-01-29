<?php
session_start();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
        header('Location: ../login.php');
        exit;
    }

    $booking_id = $_POST['booking_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    $reviewer_id = $_SESSION['user_id'];

    if (empty($booking_id) || empty($rating)) {
        $_SESSION['error'] = 'Please provide a rating.';
        header('Location: ../dashboard.php');
        exit;
    }

    try {
        // Get the helper_id from the booking
        $stmt = $pdo->prepare("SELECT helper_id FROM bookings WHERE id = ? AND user_id = ?");
        $stmt->execute([$booking_id, $reviewer_id]);
        $booking = $stmt->fetch();

        if (!$booking || !$booking['helper_id']) {
            $_SESSION['error'] = 'Invalid booking.';
            header('Location: ../dashboard.php');
            exit;
        }

        $reviewee_id = $booking['helper_id'];

        // Insert review
        $stmt = $pdo->prepare("
            INSERT INTO reviews (booking_id, reviewer_id, reviewee_id, rating, comment) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$booking_id, $reviewer_id, $reviewee_id, $rating, $comment]);

        // Update helper's average rating
        // Calculate new average
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE reviewee_id = ?");
        $stmt->execute([$reviewee_id]);
        $result = $stmt->fetch();
        $new_avg = $result['avg_rating'];

        // Update user table
        $stmt = $pdo->prepare("UPDATE users SET average_rating = ? WHERE id = ?");
        $stmt->execute([$new_avg, $reviewee_id]);

        $_SESSION['success'] = 'Review submitted successfully!';
        header('Location: ../dashboard.php');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to submit review: ' . $e->getMessage();
        header('Location: ../dashboard.php');
        exit;
    }

} else {
    header('Location: ../index.php');
    exit;
}
?>