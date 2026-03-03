<?php
require 'includes/db_connect.php';

$users_to_delete = [23, 30]; // anna christina johny, abner sam

foreach ($users_to_delete as $user_id) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? OR helper_id = ?");
        $stmt->execute([$user_id, $user_id]);
        $booking_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($booking_ids)) {
            $placeholders = implode(',', array_fill(0, count($booking_ids), '?'));

            $stmt = $pdo->prepare("DELETE FROM reviews WHERE booking_id IN ($placeholders)");
            $stmt->execute($booking_ids);

            $stmt = $pdo->prepare("DELETE FROM booking_requests WHERE booking_id IN ($placeholders)");
            $stmt->execute($booking_ids);

            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id IN ($placeholders)");
            $stmt->execute($booking_ids);
        }

        $stmt = $pdo->prepare("DELETE FROM reviews WHERE reviewer_id = ? OR reviewee_id = ?");
        $stmt->execute([$user_id, $user_id]);

        $stmt = $pdo->prepare("DELETE FROM booking_requests WHERE helper_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM helper_services WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        $pdo->commit();
        echo "User/Helper $user_id deleted successfully.\n";
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "Failed to delete user $user_id: " . $e->getMessage() . "\n\n";
    }
}
?>