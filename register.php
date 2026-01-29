<?php
session_start();
require_once 'includes/db_connect.php';

// Fetch services for the form
try {
    $stmt = $pdo->query("SELECT * FROM services");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $services = []; // Fail gracefully
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>

    </style>
    <script>
        function selectRole(role) {
            document.getElementById('roleInput').value = role;
            document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + role).classList.add('active');

            const jobRoleContainer = document.getElementById('job-role-container');
            const jobRoleSelect = document.getElementById('job-role-select');

            if (role === 'helper') {
                jobRoleContainer.style.display = 'block';
                jobRoleSelect.required = true;
            } else {
                jobRoleContainer.style.display = 'none';
                jobRoleSelect.required = false;
            }
        }


    </script>
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
            <nav class="nav-links">
                <a href="index.php">Home</a>
                <a href="login.php">Sign In</a>
            </nav>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-left">
                <h2 style="margin-bottom: 0.5rem; font-size: 2rem; color: var(--primary-dark);">Create Account</h2>
                <p style="margin-bottom: 2rem; color: var(--text-light);">Join Helpify as a user or a helper</p>

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

                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-light);">I
                        am a:</label>
                    <div class="auth-tabs">
                        <div id="btn-user" class="role-btn active" onclick="selectRole('user')">User (Customer)</div>
                        <div id="btn-helper" class="role-btn" onclick="selectRole('helper')">Helper (Provider)</div>
                    </div>

                    <div class="input-group">
                        <label>Full Name</label>
                        <input type="text" name="name" placeholder="Enter your full name" required>
                    </div>

                    <div class="input-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Create a password" required minlength="6">
                    </div>

                    <!-- Job Role Selection (Hidden by default) -->
                    <div id="job-role-container" style="display: none; margin-bottom: 1.5rem;">
                        <div class="input-group">
                            <label>Job Category</label>
                            <select name="job_role" id="job-role-select"
                                style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; background: #fff;">
                                <option value="" disabled selected>Select your job category...</option>
                                <?php
                                $roles = ['Cleaning', 'Cooking', 'Babysitting', 'Elderly Care'];
                                foreach ($roles as $roleOption) {
                                    echo '<option value="' . htmlspecialchars($roleOption) . '">' . htmlspecialchars($roleOption) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>





                    <button type="submit" class="btn btn-primary btn-block">Sign Up</button>

                    <div style="text-align: center; margin: 1rem 0;">
                        <span style="color: var(--text-light); bg-color: #fff; padding: 0 10px;">OR</span>
                    </div>

                    <?php
                    require_once 'includes/google_config.php';
                    // Default link for 'user'
                    if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID') {
                        $base_google_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email') . '&redirect_uri=' . urlencode(GOOGLE_REDIRECT_URL) . '&response_type=code&client_id=' . GOOGLE_CLIENT_ID . '&access_type=online';
                        $google_signup_url = $base_google_url . '&state=user'; // Default state
                        ?>
                        <a href="<?php echo $google_signup_url; ?>" id="google-signup-btn" class="btn btn-block"
                            style="background-color: #fff; color: #757575; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 10px;">
                            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google"
                                style="width: 18px; height: 18px;">
                            Sign up with Google
                        </a>
                        <script>
                            // Store base URL in JS variable
                            const baseGoogleUrl = "<?php echo $base_google_url; ?>";

                            // Override selectRole function to update Google Link
                            const originalSelectRole = window.selectRole;
                            window.selectRole = function (role) {
                                // Call original logic logic is actually easier to just copy-paste completely or fix it.
                                // Let's just fix the implementation here to include job-role-container

                                document.getElementById('roleInput').value = role;
                                document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
                                document.getElementById('btn-' + role).classList.add('active');

                                const jobRoleContainer = document.getElementById('job-role-container');
                                const jobRoleSelect = document.getElementById('job-role-select');

                                if (role === 'helper') {
                                    jobRoleContainer.style.display = 'block';
                                    jobRoleSelect.required = true;
                                } else {
                                    jobRoleContainer.style.display = 'none';
                                    jobRoleSelect.required = false;
                                }

                                // Update Google Link state
                                const googleBtn = document.getElementById('google-signup-btn');
                                if (googleBtn) {
                                    googleBtn.href = baseGoogleUrl + '&state=' + role;
                                }
                            }
                        </script>
                    <?php } else { ?>
                        <a href="#"
                            onclick="alert('Please configure Google Client ID and Secret in includes/google_config.php first!'); return false;"
                            class="btn btn-block"
                            style="background-color: #f5f5f5; color: #999; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; cursor: not-allowed;">
                            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google"
                                style="width: 18px; height: 18px; opacity: 0.5;">
                            Sign up with Google (Not Configured)
                        </a>
                    <?php } ?>
                </form>

                <p style="margin-top: 1.5rem; text-align: center; color: var(--text-light);">
                    Already have an account? <a href="login.php"
                        style="color: var(--primary-color); font-weight: 600;">Sign In</a>
                </p>
            </div>
            <div class="auth-right">
                <img src="assets/img/registration_side.png" alt="Join Our Community"
                    style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        </div>
    </div>
</body>

</html>