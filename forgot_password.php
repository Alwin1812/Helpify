 <?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
            <nav class="nav-links">
                <a href="login.php">Sign In</a>
            </nav>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-left" style="width: 100%; max-width: 500px; mx: auto;">
                <h2 style="margin-bottom: 0.5rem; font-size: 2rem; color: var(--primary-dark);">Forgot Password</h2>
                <p style="margin-bottom: 2rem; color: var(--text-light);">Enter your email to reset your password</p>

                <?php if (isset($_SESSION['error'])): ?>
                    <div
                        style="background: var(--danger); color: white; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div
                        style="background: var(--success); color: white; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php echo $_SESSION['success'];
                        unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <form action="api/password_reset_handler.php" method="POST">
                    <input type="hidden" name="action" value="request_reset">

                    <div class="input-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Enter your registered email" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>

                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="login.php" style="color: var(--primary-color);">Back to Sign In</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>