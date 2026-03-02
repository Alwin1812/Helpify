<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_user' || $action === 'delete_helper') {
        $user_id = $_POST['user_id'];

        if (empty($user_id)) {
            $_SESSION['error'] = 'User ID is required.';
            header('Location: ../admin_dashboard.php');
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Delete bookings where user is involved (as user or helper)
            // As a user
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // As a helper
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE helper_id = ?");
            $stmt->execute([$user_id]);

            // Delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();
            $_SESSION['success'] = 'User/Helper deleted successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Failed to delete user: ' . $e->getMessage();
        }

        header('Location: ../admin_dashboard.php');
        exit;
    } elseif ($action === 'update_helper') {
        $helper_id = $_POST['user_id'];
        $name = $_POST['name'];
        $phone_number = $_POST['phone_number'];
        $gender = $_POST['gender'];
        $hourly_rate = $_POST['hourly_rate'];
        $job_role = $_POST['job_role'];
        $address = $_POST['address'];
        $bio = $_POST['bio'];

        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone_number = ?, gender = ?, hourly_rate = ?, job_role = ?, address = ?, bio = ? WHERE id = ? AND role = 'helper'");
            $stmt->execute([$name, $phone_number, $gender, $hourly_rate, $job_role, $address, $bio, $helper_id]);

            $_SESSION['success'] = 'Helper details updated successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to update helper: ' . $e->getMessage();
        }

        header('Location: ../admin_dashboard.php?role=helper');
        exit;
    }
} else {
    header('Location: ../admin_dashboard.php');
    exit;
}
?>