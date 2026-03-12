<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/home_redesign.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* Validation Styles */
        .form-control.invalid {
            border-color: #EF4444 !important;
        }

        .form-control.valid {
            border-color: #10B981 !important;
        }

        .error-message {
            color: #EF4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: none;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <header class="uc-header">
        <div class="header-container">
            <div class="header-left">
                <a href="index.php" class="back-btn material-icons"
                    style="display: none; text-decoration: none; color: #111827; font-size: 1.8rem; margin-right: 0.5rem; vertical-align: middle;">arrow_back</a>
                <a href="index.php" class="logo" style="text-decoration: none; display: flex; align-items: center;">
                    <span
                        style="background: black; color: white; padding: 4px 8px; border-radius: 6px; margin-right: 8px; font-weight: 700; font-size: 1.1rem; line-height: 1;">HF</span>
                    <span
                        style="color: #111827; font-weight: 800; font-size: 1.4rem; letter-spacing: -0.5px;">HELPIFY</span>
                </a>
            </div>
            <div class="header-right">
                <nav class="nav-links">
                    <a href="index.php" style="font-weight: 600;">Home</a>
                    <a href="register.php" class="btn btn-primary" style="padding: 0.5rem 1.5rem; color: white;">Sign
                        Up</a>
                </nav>
            </div>
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
                        <input type="email" name="email" placeholder="Enter your email" required
                            oninput="validateLoginField(this)" class="form-control">
                        <div class="error-message">Please enter a valid email.</div>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required
                            oninput="validateLoginField(this)" class="form-control">
                        <div class="error-message">Password is required.</div>
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
                    $is_google_configured = (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID');
                    if ($is_google_configured) {
                        $google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email') . '&redirect_uri=' . urlencode(GOOGLE_REDIRECT_URL) . '&response_type=code&client_id=' . GOOGLE_CLIENT_ID . '&access_type=online&prompt=select_account';
                    } else {
                        $google_login_url = '#';
                    }
                    ?>

                    <a href="<?php echo $google_login_url; ?>" <?php if (!$is_google_configured)
                           echo 'onclick="alert(\'Please configure Google Client ID and Secret in includes/google_config.php first!\'); return false;"'; ?> class="btn btn-block"
                        style="background-color: #fff; color: #374151; border: 1px solid #D1D5DB; display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 1rem; border-radius: 8px; font-weight: 500; transition: all 0.2s ease;">
                        <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google"
                            style="width: 20px; height: 20px;">
                        Sign in with Google
                    </a>
                </form>


                <p style="margin-top: 1.5rem; text-align: center; color: var(--text-light);">
                    Don't have an account? <a href="register.php"
                        style="color: var(--primary-color); font-weight: 600;">Sign Up</a>
                </p>
            </div>
            <div class="auth-right">
                <img src="assets/img/login_side.png?v=<?php echo time(); ?>" alt="Welcome Back"
                    style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        </div>
    </div>
    <script>
        function validateLoginField(input) {
            const errorEl = input.nextElementSibling;
            let isValid = true;
            let message = "";

            if (input.required && !input.value.trim()) {
                isValid = false;
                message = "This field is required.";
            } else if (input.type === 'email') {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!re.test(input.value)) {
                    isValid = false;
                    message = "Please enter a valid email address.";
                }
            }

            if (!isValid) {
                input.classList.add('invalid');
                input.classList.remove('valid');
                if (errorEl) {
                    errorEl.textContent = message;
                    errorEl.style.display = 'block';
                }
            } else {
                input.classList.remove('invalid');
                input.classList.add('valid');
                if (errorEl) errorEl.style.display = 'none';
            }
            return isValid;
        }

        document.querySelector('form').addEventListener('submit', function (e) {
            const inputs = this.querySelectorAll('input[required]');
            let allValid = true;
            inputs.forEach(input => {
                if (!validateLoginField(input)) allValid = false;
            });
            if (!allValid) {
                e.preventDefault();
                alert("Please correct the errors in the form.");
            }
        });
    </script>
</body>

</html>