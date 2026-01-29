<?php
session_start();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? 'user';
    $redirect_url = ($role === 'helper') ? '../helper_dashboard.php' : '../dashboard.php';

    // Text Fields
    $name = trim($_POST['name']);
    $gender = $_POST['gender'] ?? null;
    $bio = $_POST['bio'] ?? null;
    $phone = $_POST['phone_number'] ?? null;
    $address = $_POST['address'] ?? null;
    $hourly_rate = $_POST['hourly_rate'] ?? null;
    $job_role = $_POST['job_role'] ?? null;

    if (empty($name)) {
        $_SESSION['error'] = 'Name cannot be empty.';
        header("Location: $redirect_url");
        exit;
    }

    // Photo Upload Logic
    $profile_photo_path = null;
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
        // Base Query
        $sql = "UPDATE users SET name = ?, gender = ?, bio = ?, phone_number = ?, address = ?";
        $params = [$name, $gender, $bio, $phone, $address];

        // Add Role-Specific Fields
        if ($role === 'helper') {
            if ($hourly_rate) {
                $sql .= ", hourly_rate = ?";
                $params[] = $hourly_rate;
            }
            if ($job_role) {
                $sql .= ", job_role = ?";
                $params[] = $job_role;
            }
        }

        // Add Photo if new one uploaded
        if ($profile_photo_path) {
            $sql .= ", profile_photo = ?";
            $params[] = $profile_photo_path;
        }

        $sql .= " WHERE id = ?";
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['user_name'] = $name; // Update session
        $_SESSION['success'] = 'Profile updated successfully!';

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to update profile: ' . $e->getMessage();
    }

    header("Location: $redirect_url");
    exit;

} else {
    header('Location: ../index.php');
    exit;
}
?>