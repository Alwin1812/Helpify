<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$helper_id = $_GET['helper_id'] ?? null;
if (!$helper_id) {
    echo json_encode(['success' => false, 'message' => 'Missing helper ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT last_lat, last_lng, location_updated_at FROM users WHERE id = ?");
    $stmt->execute([$helper_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($location && $location['last_lat']) {
        echo json_encode(['success' => true, 'location' => $location]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Location not available']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>