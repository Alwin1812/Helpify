<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Helpify</title>
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
                    <a href="login.php" class="btn btn-primary" style="padding: 0.5rem 1.5rem; color: white;">Sign
                        In</a>
                </nav>
            </div>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-left">
                <h2 style="margin-bottom: 0.5rem; font-size: 2rem; color: var(--primary-dark);">Create Account</h2>
                <p style="margin-bottom: 2rem; color: var(--text-light);">Join Helpify to easily book reliable services
                </p>

                <?php if (isset($_SESSION['error'])): ?>
                    <div
                        style="background: var(--danger); color: white; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form action="api/auth_handler.php" method="POST">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="role" id="roleInput" value="user">

                    <div class="input-group">
                        <label>Full Name</label>
                        <input type="text" name="name" id="regName" placeholder="Enter your full name" required
                            oninput="validateRegField(this)" class="form-control">
                        <div class="error-message">Name is required.</div>
                    </div>

                    <div class="input-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="regEmail" placeholder="Enter your email" required
                            oninput="validateRegField(this)" class="form-control">
                        <div class="error-message">Please enter a valid email.</div>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" id="regPassword" placeholder="Create a password" required
                            minlength="6" oninput="validateRegField(this)" class="form-control">
                        <div class="error-message">Password must be at least 6 characters.</div>
                    </div>





                    <button type="submit" class="btn btn-primary btn-block">Sign Up</button>

                    <div style="text-align: center; margin: 1rem 0;">
                        <span style="color: var(--text-light); bg-color: #fff; padding: 0 10px;">OR</span>
                    </div>

                    <?php
                    $is_google_configured = (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID');
                    if ($is_google_configured) {
                        $base_google_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email') . '&redirect_uri=' . urlencode(GOOGLE_REDIRECT_URL) . '&response_type=code&client_id=' . GOOGLE_CLIENT_ID . '&access_type=online&prompt=select_account';
                        $google_signup_url = $base_google_url . '&state=user';
                    } else {
                        $google_signup_url = '#';
                    }
                    ?>
                    <a href="<?php echo $google_signup_url; ?>" id="google-signup-btn" <?php if (!$is_google_configured)
                           echo 'onclick="alert(\'Please configure Google Client ID and Secret in includes/google_config.php first!\'); return false;"'; ?> class="btn btn-block"
                        style="background-color: #fff; color: #374151; border: 1px solid #D1D5DB; display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 1rem; border-radius: 8px; font-weight: 500; transition: all 0.2s ease;">
                        <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google"
                            style="width: 20px; height: 20px;">
                        Sign up with Google
                    </a>

                    <?php if ($is_google_configured): ?>
                        <script>
                            const baseGoogleUrl = "<?php echo $base_google_url; ?>";
                        </script>
                    <?php endif; ?>

                    <script>
                        function validateRegField(input) {
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
                            } else if (input.type === 'password' && input.value.length < 6) {
                                isValid = false;
                                message = "Password must be at least 6 characters.";
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
                                if (!validateRegField(input)) allValid = false;
                            });
                            if (!allValid) {
                                e.preventDefault();
                                alert("Please correct the errors in the form.");
                            }
                        });
                    </script>


                </form>

                <p style="margin-top: 1.5rem; text-align: center; color: var(--text-light);">
                    Already have an account? <a href="login.php"
                        style="color: var(--primary-color); font-weight: 600;">Sign In</a>
                </p>
            </div>
            <div class="auth-right">
                <img src="assets/img/registration_side.png?v=<?php echo time(); ?>" alt="Join Our Community"
                    style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        </div>
    </div>
</body>

</html>