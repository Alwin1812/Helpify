<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'helper') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $phone = $_POST['phone_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $bio = $_POST['bio'] ?? '';

    // Basic validation
    if (empty($phone) || empty($address)) {
        header('Location: ../helper_setup.php?error=missing_fields');
        exit;
    }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        header('Location: ../helper_setup.php?error=invalid_phone');
        exit;
    }

    $profile_photo_path = null;

    // Handle File Upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
        $fileName = $_FILES['profile_photo']['name'];
        $fileSize = $_FILES['profile_photo']['size'];
        $fileType = $_FILES['profile_photo']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $profile_photo_path = 'assets/uploads/' . $newFileName;
            }
        }
    }

    try {
        if ($profile_photo_path) {
            $stmt = $pdo->prepare("UPDATE users SET phone_number = ?, address = ?, bio = ?, profile_photo = ? WHERE id = ?");
            $stmt->execute([$phone, $address, $bio, $profile_photo_path, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET phone_number = ?, address = ?, bio = ? WHERE id = ?");
            $stmt->execute([$phone, $address, $bio, $user_id]);
        }

        header('Location: ../helper_dashboard.php');
        exit;
    } catch (PDOException $e) {
        // Handle error
        header('Location: ../helper_setup.php?error=db_error');
        exit;
    }
} else {
    header('Location: ../helper_setup.php');
    exit;
}
