<?php
session_start();
$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-left" style="width: 100%; max-width: 500px; mx: auto;">
                <h2 style="margin-bottom: 0.5rem; font-size: 2rem; color: var(--primary-dark);">Reset Password</h2>
                <p style="margin-bottom: 2rem; color: var(--text-light);">Create a new password for your account</p>

                <form action="api/password_reset_handler.php" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="input-group">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="Enter new password" required minlength="6">
                    </div>

                    <div class="input-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>