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

            // 1. Find all bookings associated with the user
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? OR helper_id = ?");
            $stmt->execute([$user_id, $user_id]);
            $booking_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // 2. Delete dependent records for these bookings
            if (!empty($booking_ids)) {
                $placeholders = implode(',', array_fill(0, count($booking_ids), '?'));

                // Delete reviews linked to these bookings
                $stmt = $pdo->prepare("DELETE FROM reviews WHERE booking_id IN ($placeholders)");
                $stmt->execute($booking_ids);

                // Delete booking requests linked to these bookings
                $stmt = $pdo->prepare("DELETE FROM booking_requests WHERE booking_id IN ($placeholders)");
                $stmt->execute($booking_ids);

                // Finally delete the bookings
                $stmt = $pdo->prepare("DELETE FROM bookings WHERE id IN ($placeholders)");
                $stmt->execute($booking_ids);
            }

            // 3. Delete other related lists where the user is involved
            // Reviews (as reviewer or as helper being reviewed)
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE reviewer_id = ? OR reviewee_id = ?");
            $stmt->execute([$user_id, $user_id]);

            // Booking Requests (as helper)
            $stmt = $pdo->prepare("DELETE FROM booking_requests WHERE helper_id = ?");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Helper Services
            $stmt = $pdo->prepare("DELETE FROM helper_services WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // 4. Delete the user
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
    } elseif ($action === 'add_helper') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $phone_number = $_POST['phone_number'];
        $gender = $_POST['gender'];
        $job_role = $_POST['job_role'];
        $hourly_rate = $_POST['hourly_rate'] ?? 0;
        $role = 'helper';

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'A user or helper with this email already exists.';
            header('Location: ../admin_dashboard.php?role=helper');
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, job_role, gender, phone_number, hourly_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password, $role, $job_role, $gender, $phone_number, $hourly_rate]);
            $_SESSION['success'] = 'New helper added successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to add helper: ' . $e->getMessage();
        }

        header('Location: ../admin_dashboard.php?role=helper');
        exit;
    }
} else {
    header('Location: ../admin_dashboard.php');
    exit;
}
?>