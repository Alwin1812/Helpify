<?php
session_start();
require_once '../includes/db_connect.php';

// Include PHPMailer classes manually
require_once '../PHPMailer-master/src/Exception.php';
require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_reset') {
        $email = trim($_POST['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Invalid email address format.';
            header('Location: ../forgot_password.php');
            exit;
        }

        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            // Generic message for security
            $_SESSION['success'] = 'If that email exists, a reset link has been sent.';
            header('Location: ../forgot_password.php');
            exit;
        }

        // Generate Token
        $token = bin2hex(random_bytes(32));

        // Save to DB
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
        $stmt->execute([$email, $token]);

        // Send Email
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'alwinvarghese2028@mca.ajce.in';
            $mail->Password = 'vksigiwtleojjoln';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('alwinvarghese2028@mca.ajce.in', 'Helpify Support');
            $mail->addAddress($email);

            // Content
            $link = "http://localhost:8012/helpify/reset_password.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - Helpify';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Password Reset Request</h2>
                    <p>Click the link below to reset your password:</p>
                    <p><a href='$link' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p style='margin-top: 20px; font-size: 12px; color: #999;'>If you did not request this, please ignore this email.</p>
                </div>
            ";
            $mail->AltBody = "Click the link to reset your password: $link";

            $mail->send();

            $_SESSION['success'] = "Reset link has been sent to your email! Please check your inbox.";
            header('Location: ../forgot_password.php');
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            header('Location: ../forgot_password.php');
            exit;
        }

    } elseif ($action === 'reset_password') {
        $token = $_POST['token'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            $_SESSION['error'] = 'Password must be at least 6 characters long.';
            header("Location: ../reset_password.php?token=$token");
            exit;
        }

        if ($password !== $confirm_password) {
            $_SESSION['error'] = 'Passwords do not match.';
            // In a better real-world app, we would redirect back to reset_password with token
            // But since GET param is needed, we need to handle that carefully.
            // For now simplest fix: keep them on the page or redirect with token if possible
            header("Location: ../reset_password.php?token=$token");
            exit;
        }

        // Validate Token
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $reset_request = $stmt->fetch();

        if (!$reset_request) {
            $_SESSION['error'] = 'Invalid or expired token.';
            header('Location: ../login.php');
            exit;
        }

        $email = $reset_request['email'];

        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update User Password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $email]);

        // Delete used token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);

        $_SESSION['success'] = 'Password updated successfully! Please login.';
        header('Location: ../login.php');
        exit;
    }
}
header('Location: ../index.php');
?>