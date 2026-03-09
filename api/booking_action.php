<?php
session_start();
require_once '../includes/db_connect.php';

function createNotification($pdo, $user_id, $message, $type = 'info')
{
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $message, $type]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? '';

    // Check if user is a Plus member
    $stmt = $pdo->prepare("SELECT is_plus_member FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $is_plus_member = (int) $stmt->fetchColumn();
    $platform_fee = $is_plus_member ? 0.00 : 25.00;

    if ($action === 'book') {
        // ... (existing book logic)
        $service_ids = $_POST['service_ids'] ?? [];
        if (isset($_POST['service_id']) && !empty($_POST['service_id'])) {
            $service_ids[] = $_POST['service_id'];
        }

        $date = $_POST['date'];
        $end_date = $_POST['end_date'] ?? null;
        $time = $_POST['time'] ?? null;
        $location = $_POST['location'] ?? null;
        $budget = $_POST['budget'] ?? null;
        $instructions = $_POST['instructions'] ?? null;
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $num_days = isset($_POST['num_days']) ? max(1, (int) $_POST['num_days']) : 1;
        $promo_code = trim($_POST['promo_code'] ?? '');
        $recurrence_type = $_POST['recurrence_type'] ?? 'none';
        $preferred_gender = $_POST['preferred_gender'] ?? 'Any';
        $is_subscription_active = ($recurrence_type !== 'none') ? 1 : 0;

        if (empty($service_ids) || empty($date)) {
            $_SESSION['error'] = 'Please select a service and date.';
            header('Location: ../dashboard.php');
            exit;
        }

        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            $_SESSION['error'] = 'Booking date cannot be in the past.';
            header('Location: ../dashboard.php');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $booked_count = 0;
            $recent_booking_ids = [];

            // Validate promo at backend securely
            $promoDetails = null;
            if (!empty($promo_code)) {
                $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
                $stmt->execute([$promo_code]);
                $promoDetails = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($promoDetails && $promoDetails['valid_until'] && strtotime($promoDetails['valid_until']) < time()) {
                    $promoDetails = null; // Expired
                }
                if ($promoDetails && $promoDetails['max_uses'] && $promoDetails['current_uses'] >= $promoDetails['max_uses']) {
                    $promoDetails = null; // Max uses reached
                }
            }

            foreach ($service_ids as $service_id) {
                // 1. Get Service Name & Price
                $stmt = $pdo->prepare("SELECT name, base_price FROM services WHERE id = ?");
                $stmt->execute([$service_id]);
                $serviceData = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$serviceData)
                    continue;
                $service_name = $serviceData['name'];
                $base_price = (float) $serviceData['base_price'];
                $total_amount = $base_price * $num_days;
                $discount_amount = 0.00;

                if ($promoDetails && $total_amount >= $promoDetails['min_order_amount']) {
                    if ($promoDetails['discount_type'] === 'percentage') {
                        $discount_amount = ($total_amount * $promoDetails['discount_value']) / 100;
                        if ($promoDetails['max_discount_amount']) {
                            $discount_amount = min($discount_amount, $promoDetails['max_discount_amount']);
                        }
                    } else {
                        $discount_amount = $promoDetails['discount_value'];
                    }
                    $total_amount -= $discount_amount;
                    if ($total_amount < 0)
                        $total_amount = 0;
                }

                $total_amount += $platform_fee;

                // 2. Find Matching Helpers (Keyword matching for assignment)
                $keywords = [];
                if (stripos($service_name, 'Clean') !== false || stripos($service_name, 'Maid') !== false || stripos($service_name, 'InstaHelp') !== false)
                    $keywords = ['Maid', 'Cleaner', 'Housekeeper', 'Cleaning'];
                elseif (stripos($service_name, 'Cook') !== false || stripos($service_name, 'Cooking') !== false)
                    $keywords = ['Cook', 'Chef', 'Cooking'];
                elseif (stripos($service_name, 'Baby') !== false || stripos($service_name, 'Sitt') !== false)
                    $keywords = ['Nanny', 'Babysitter', 'Babysitting'];
                elseif (stripos($service_name, 'Elder') !== false || stripos($service_name, 'Patient') !== false)
                    $keywords = ['Caregiver', 'Nurse', 'Elderly Care', 'Patient Care'];
                elseif (stripos($service_name, 'Plumb') !== false)
                    $keywords = ['Plumber', 'Plumbing'];
                elseif (stripos($service_name, 'Electr') !== false)
                    $keywords = ['Electrician', 'Electrical'];
                elseif (stripos($service_name, 'RO ') !== false || stripos($service_name, 'Purifier') !== false)
                    $keywords = ['RO', 'Purifier', 'Water Service'];
                elseif (stripos($service_name, 'Paint') !== false || stripos($service_name, 'Wall') !== false)
                    $keywords = ['Painter', 'Painting', 'Wallpaper', 'Wall', 'Revamp'];

                $sql = "SELECT id FROM users WHERE role = 'helper'";
                $params = [];

                if (!empty($keywords)) {
                    $conditions = [];
                    foreach ($keywords as $k) {
                        $conditions[] = "job_role LIKE ?";
                        $params[] = "%$k%";
                    }
                    $sql .= " AND (" . implode(" OR ", $conditions) . ")";
                } else {
                    $sql .= " AND (job_role LIKE ? OR job_role LIKE ?)";
                    $params[] = "%$service_name%";
                    $params[] = "%" . trim($service_name) . "%";
                }

                if ($preferred_gender !== 'Any') {
                    $sql .= " AND gender = ?";
                    $params[] = $preferred_gender;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $helpers = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // No random fallback anymore. If no match, it stays pending.

                $assigned_helper_id = null;
                $status = 'pending';
                if (!empty($helpers)) {
                    $assigned_helper_id = $helpers[array_rand($helpers)];
                    $status = 'confirmed';
                }

                $start_otp = sprintf("%04d", rand(1000, 9999));
                $end_otp = sprintf("%04d", rand(1000, 9999));

                $stmt = $pdo->prepare("
                    INSERT INTO bookings 
                    (user_id, service_id, helper_id, date, end_date, time, location, budget, special_instructions, preferred_gender, status, payment_method, num_days, total_amount, promo_code, discount_amount, platform_fee, start_otp, end_otp, recurrence_type, is_subscription_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $service_id,
                    $assigned_helper_id,
                    $date,
                    $end_date,
                    $time,
                    $location,
                    $budget,
                    $instructions,
                    $preferred_gender,
                    $status,
                    $payment_method,
                    $num_days,
                    $total_amount,
                    $promo_code,
                    $discount_amount,
                    $platform_fee,
                    $start_otp,
                    $end_otp,
                    $recurrence_type,
                    $is_subscription_active
                ]);
                $booking_id = $pdo->lastInsertId();
                $recent_booking_ids[] = $booking_id;

                if ($assigned_helper_id) {
                    createNotification($pdo, $assigned_helper_id, "New Job Assigned: $service_name on $date. Check your My Jobs tab!", 'info');
                }

                $booked_count++;
            }

            if ($promoDetails && $booked_count > 0) {
                // Increment promo use count once per batch booking
                $pdo->prepare("UPDATE promo_codes SET current_uses = current_uses + 1 WHERE id = ?")->execute([$promoDetails['id']]);
            }

            $pdo->commit();
            $_SESSION['recent_booking_ids'] = $recent_booking_ids;

            // Adjust success message
            if ($booked_count > 0 && isset($status) && $status === 'confirmed') {
                $_SESSION['success'] = "$booked_count Booking(s) confirmed! A helper has been automatically assigned.";
            } else {
                $_SESSION['success'] = "$booked_count Booking request(s) sent successfully! Waiting for helpers.";
            }

            header('Location: ../booking_success.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Booking failed: ' . $e->getMessage();
            header('Location: ../dashboard.php');
            exit;
        }

    } elseif ($action === 'accept') {
        // ... (existing accept logic)
        if ($role !== 'helper') {
            $_SESSION['error'] = 'Unauthorized action.';
            header('Location: ../index.php');
            exit;
        }

        $booking_id = $_POST['booking_id'];
        $arrival_estimate = $_POST['arrival_estimate'] ?? null;
        $bid_price = $_POST['bid_price'] ?? null;
        $notes = $_POST['notes'] ?? null;

        try {
            $stmt = $pdo->prepare("SELECT id, status, user_id FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch();

            if ($booking && in_array($booking['status'], ['pending', 'accepted'])) {

                $stmt = $pdo->prepare("
                    INSERT INTO booking_requests (booking_id, helper_id, status, arrival_estimate, bid_price, notes) 
                    VALUES (?, ?, 'accepted', ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = 'accepted', arrival_estimate = VALUES(arrival_estimate), bid_price = VALUES(bid_price), notes = VALUES(notes)
                ");
                $stmt->execute([$booking_id, $user_id, $arrival_estimate, $bid_price, $notes]);

                $pdo->prepare("UPDATE bookings SET status = 'accepted' WHERE id = ? AND status = 'pending'")
                    ->execute([$booking_id]);

                $helper_name = $_SESSION['user_name'];

                // Customize notification based on existing accepted count
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM booking_requests WHERE booking_id = ? AND status = 'accepted'");
                $stmtCount->execute([$booking_id]);
                $count = $stmtCount->fetchColumn();

                $msg = ($count > 1)
                    ? "Multiple helpers have accepted your booking #$booking_id. Please choose one."
                    : "A helper ($helper_name) is available for booking #$booking_id. Please review and confirm.";

                createNotification($pdo, $booking['user_id'], $msg);

                $_SESSION['success'] = 'Job accepted! Waiting for customer confirmation.';
            } else {
                $_SESSION['error'] = 'This job is no longer available.';
            }

            header('Location: ../helper_dashboard.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to accept job: ' . $e->getMessage();
            header('Location: ../helper_dashboard.php');
            exit;
        }

    } elseif ($action === 'reject') {
        // Helper rejects/ignores a job request
        if ($role !== 'helper') {
            $_SESSION['error'] = 'Unauthorized action.';
            header('Location: ../index.php');
            exit;
        }

        $booking_id = $_POST['booking_id'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO booking_requests (booking_id, helper_id, status) 
                VALUES (?, ?, 'rejected')
                ON DUPLICATE KEY UPDATE status = 'rejected'
            ");
            $stmt->execute([$booking_id, $user_id]);

            $_SESSION['success'] = 'Job request ignored.';
            header('Location: ../helper_dashboard.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to reject job: ' . $e->getMessage();
            header('Location: ../helper_dashboard.php');
            exit;
        }

    } elseif ($action === 'confirm_helper') {
        // ... (existing confirm_helper logic)
        if ($role !== 'user') {
            $_SESSION['error'] = 'Unauthorized action.';
            header('Location: ../index.php');
            exit;
        }

        $booking_id = $_POST['booking_id'];
        $helper_id = $_POST['helper_id'];
        $custom_message = $_POST['message'] ?? '';

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET helper_id = ?, status = 'confirmed' 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$helper_id, $booking_id, $user_id]);

            $stmt = $pdo->prepare("UPDATE booking_requests SET status = 'selected' WHERE booking_id = ? AND helper_id = ?");
            $stmt->execute([$booking_id, $helper_id]);

            $stmt = $pdo->prepare("UPDATE booking_requests SET status = 'rejected' WHERE booking_id = ? AND helper_id != ?");
            $stmt->execute([$booking_id, $helper_id]);

            $msg = "You have been hired for booking #$booking_id! Please proceed.";
            if (!empty($custom_message)) {
                $msg .= " Note from user: \"$custom_message\"";
            }
            createNotification($pdo, $helper_id, $msg, 'success');

            $stmt = $pdo->prepare("SELECT helper_id FROM booking_requests WHERE booking_id = ? AND status = 'rejected'");
            $stmt->execute([$booking_id]);
            $rejected_helpers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rejected_helpers as $rej_id) {
                if ($rej_id != $helper_id) {
                    createNotification($pdo, $rej_id, "Booking #$booking_id has been filled by another helper.", 'warning');
                }
            }

            $pdo->commit();

            $_SESSION['success'] = 'Helper confirmed! Your booking is now active.';
            header('Location: ../dashboard.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Failed to confirm helper.';
            header('Location: ../dashboard.php');
            exit;
        }

    } elseif ($action === 'reject_applicant') {
        // NEW: User rejects a specific applicant
        if ($role !== 'user') {
            $_SESSION['error'] = 'Unauthorized action.';
            header('Location: ../index.php');
            exit;
        }

        $booking_id = $_POST['booking_id'];
        $helper_id = $_POST['helper_id'];
        $custom_message = $_POST['message'] ?? '';

        try {
            // Verify booking ownership
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ?");
            $stmt->execute([$booking_id, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Invalid booking.");
            }

            // Mark request as rejected
            $stmt = $pdo->prepare("UPDATE booking_requests SET status = 'rejected' WHERE booking_id = ? AND helper_id = ?");
            $stmt->execute([$booking_id, $helper_id]);

            $msg = "Your application for booking #$booking_id was declined by the user.";
            if (!empty($custom_message)) {
                $msg .= " Reason: \"$custom_message\"";
            }
            createNotification($pdo, $helper_id, $msg, 'error');

            // Check if any accepted helpers remain
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking_requests WHERE booking_id = ? AND status = 'accepted'");
            $stmt->execute([$booking_id]);
            $remaining = $stmt->fetchColumn();

            if ($remaining == 0) {
                // No helpers left, revert booking to pending so others can apply (or keep it accepted but empty? 'pending' is better for logic)
                $pdo->prepare("UPDATE bookings SET status = 'pending' WHERE id = ?")->execute([$booking_id]);
            }

            $_SESSION['success'] = 'Helper application declined.';
            header('Location: ../dashboard.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Failed to decline helper.';
            header('Location: ../dashboard.php');
            exit;
        }

    } elseif ($action === 'reject') {
        // ... (existing helper reject logic)
        $booking_id = $_POST['booking_id'];
        try {
            $stmt = $pdo->prepare("
                INSERT INTO booking_requests (booking_id, helper_id, status) 
                VALUES (?, ?, 'rejected')
                ON DUPLICATE KEY UPDATE status = 'rejected'
            ");
            $stmt->execute([$booking_id, $user_id]);

            $_SESSION['success'] = 'Job rejected.';
            header('Location: ../helper_dashboard.php');
            exit;
        } catch (PDOException $e) {
            header('Location: ../helper_dashboard.php');
            exit;
        }

    } elseif ($action === 'complete') {
        // ... (existing complete logic)
        if ($role !== 'helper') {
            $_SESSION['error'] = 'Unauthorized action.';
            header('Location: ../index.php');
            exit;
        }
        $booking_id = $_POST['booking_id'];
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $uid = $stmt->fetchColumn();

            $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed', payment_status = 'paid' WHERE id = ? AND helper_id = ?");
            $stmt->execute([$booking_id, $user_id]);

            createNotification($pdo, $uid, "Booking #$booking_id is marked completed by the helper. Please rate the service.", 'success');

            $_SESSION['success'] = 'Job marked as completed!';
            header('Location: ../helper_dashboard.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to update job status.';
            header('Location: ../helper_dashboard.php');
            exit;
        }
    } elseif ($action === 'cancel') {
        if ($role !== 'user') {
            $_SESSION['error'] = 'Unauthorized action.';
            header('Location: ../index.php');
            exit;
        }

        $booking_id = $_POST['booking_id'];
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id, status, helper_id, total_amount, payment_status, user_id FROM bookings WHERE id = ? AND user_id = ?");
            $stmt->execute([$booking_id, $user_id]);
            $booking = $stmt->fetch();

            if ($booking && in_array($booking['status'], ['pending', 'accepted', 'confirmed'])) {
                // 1. Cancel the booking
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$booking_id]);

                $refund_msg = "";
                // 2. Handle Refund if paid
                if ($booking['payment_status'] === 'paid') {
                    $refund_amount = (float) $booking['total_amount'];

                    // Update User Wallet
                    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
                        ->execute([$refund_amount, $user_id]);

                    // Log Transaction
                    $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)")
                        ->execute([$user_id, $refund_amount, "Refund for cancelled booking #$booking_id"]);

                    // Update Booking Payment Status
                    $pdo->prepare("UPDATE bookings SET payment_status = 'refunded' WHERE id = ?")
                        ->execute([$booking_id]);

                    $refund_msg = " A refund of ₹" . number_format($refund_amount, 2) . " has been credited to your wallet.";
                    createNotification($pdo, $user_id, "Booking #$booking_id cancelled. ₹$refund_amount refunded to wallet.", 'success');
                }

                if ($booking['helper_id']) {
                    createNotification($pdo, $booking['helper_id'], "Booking #$booking_id has been cancelled by the user.", 'error');
                } else {
                    // Notify all helpers who applied
                    $stmt = $pdo->prepare("SELECT helper_id FROM booking_requests WHERE booking_id = ? AND status = 'accepted'");
                    $stmt->execute([$booking_id]);
                    $applicants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($applicants as $app_id) {
                        createNotification($pdo, $app_id, "Booking #$booking_id that you applied for has been cancelled by the user.", 'warning');
                    }
                    // Mark requests as rejected or cancelled
                    $pdo->prepare("UPDATE booking_requests SET status = 'rejected' WHERE booking_id = ? AND status = 'accepted'")
                        ->execute([$booking_id]);
                }

                $pdo->commit();
                $_SESSION['success'] = 'Booking cancelled successfully.' . $refund_msg;
            } else {
                $pdo->rollBack();
                $_SESSION['error'] = 'Invalid booking or cannot cancel this booking.';
            }
            header('Location: ../dashboard.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            $_SESSION['error'] = 'Failed to cancel booking: ' . $e->getMessage();
            header('Location: ../dashboard.php');
            exit;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_bundle_items') {
    $bundle_id = $_GET['bundle_id'] ?? 0;
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.name, s.base_price 
            FROM bundle_items bi 
            JOIN services s ON bi.service_id = s.id 
            WHERE bi.bundle_id = ?
        ");
        $stmt->execute([$bundle_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'items' => $items]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_applicants') {
    $booking_id = $_GET['booking_id'] ?? 0;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, u.name, u.profile_photo, u.bio, u.phone_number, u.address, u.gender,
            (SELECT COUNT(*) FROM bookings WHERE helper_id = u.id AND status = 'completed') as completed_jobs_count,
            (SELECT AVG(rating) FROM reviews WHERE booking_id IN (SELECT id FROM bookings WHERE helper_id = u.id)) as average_rating
            FROM booking_requests r
            JOIN users u ON r.helper_id = u.id
            WHERE r.booking_id = ? AND r.status = 'accepted'
        ");
        $stmt->execute([$booking_id]);
        $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'applicants' => $applicants]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
// Fallback
header('Location: ../index.php');
exit;
?>