<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
            <nav class="nav-links">
                <a href="index.php">Home</a>
                <a href="register.php">Sign Up</a>
            </nav>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-left">
                <h2 style="margin-bottom: 0.5rem; font-size: 2rem; color: var(--primary-dark);">Welcome Back</h2>
                <p style="margin-bottom: 2rem; color: var(--text-light);">Please sign in to your account</p>

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

                <form action="api/auth_handler.php" method="POST">
                    <input type="hidden" name="action" value="login">

                    <div class="input-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>

                    <div style="margin-bottom: 1.5rem; text-align: right;">
                        <a href="forgot_password.php" style="color: var(--primary-color); font-size: 0.9rem;">Forgot
                            Password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Sign In</button>

                    <div style="text-align: center; margin: 1rem 0;">
                        <span style="color: var(--text-light); bg-color: #fff; padding: 0 10px;">OR</span>
                    </div>

                    <?php
                    require_once 'includes/google_config.php';
                    if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID') {
                        $google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email') . '&redirect_uri=' . urlencode(GOOGLE_REDIRECT_URL) . '&response_type=code&client_id=' . GOOGLE_CLIENT_ID . '&access_type=online';
                        ?>
                        <a href="<?php echo $google_login_url; ?>" class="btn btn-block"
                            style="background-color: #fff; color: #757575; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 10px;">
                            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google"
                                style="width: 18px; height: 18px;">
                            Sign in with Google
                        </a>
                    <?php } else { ?>
                        <a href="#"
                            onclick="alert('Please configure Google Client ID and Secret in includes/google_config.php first!'); return false;"
                            class="btn btn-block"
                            style="background-color: #f5f5f5; color: #999; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; cursor: not-allowed;">
                            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google"
                                style="width: 18px; height: 18px; opacity: 0.5;">
                            Sign in with Google (Not Configured)
                        </a>
                    <?php } ?>
                </form>

                <p style="margin-top: 1.5rem; text-align: center; color: var(--text-light);">
                    Don't have an account? <a href="register.php"
                        style="color: var(--primary-color); font-weight: 600;">Sign Up</a>
                </p>
            </div>
            <div class="auth-right">
                <img src="assets/img/login_side.png" alt="Welcome Back"
                    style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        </div>
    </div>
</body>

</html>