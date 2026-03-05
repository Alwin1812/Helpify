<?php
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

if (!isset($_GET['helper_id'])) {
    echo json_encode(['error' => 'Helper ID is required']);
    exit;
}

$helper_id = $_GET['helper_id'];

try {
    // Fetch helper details: name, phone, address, bio, profile_photo, average_rating, job_role
    $stmt = $pdo->prepare("
        SELECT id, name, phone_number, address, bio, profile_photo, average_rating, job_role, 
               (SELECT COUNT(*) FROM bookings WHERE helper_id = users.id AND status = 'completed') as completed_jobs_count
        FROM users 
        WHERE id = ? AND role = 'helper'
    ");
    $stmt->execute([$helper_id]);
    $helper = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($helper) {
        // Fetch recent reviews for this helper
        $stmt = $pdo->prepare("
            SELECT r.rating, r.comment, r.created_at, u.name as reviewer_name 
            FROM reviews r 
            JOIN users u ON r.reviewer_id = u.id 
            WHERE r.reviewee_id = ? 
            ORDER BY r.created_at DESC 
            LIMIT 3
        ");
        $stmt->execute([$helper_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $helper['reviews'] = $reviews;
        echo json_encode(['success' => true, 'helper' => $helper]);
    } else {
        echo json_encode(['error' => 'Helper not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
