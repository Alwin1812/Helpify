<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'helper') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Profile - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-left" style="width: 100%;">
                <h2 style="margin-bottom: 0.5rem; font-size: 2rem; color: var(--primary-dark);">Complete Your Profile
                </h2>
                <p style="margin-bottom: 2rem; color: var(--text-light);">Please provide your contact details to get
                    started.</p>

                <form action="api/save_helper_details.php" method="POST" enctype="multipart/form-data">
                    <div class="input-group">
                        <label>Profile Picture</label>
                        <input type="file" name="profile_photo" accept="image/*" class="form-control"
                            style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; background: white;">
                    </div>

                    <div class="input-group">
                        <label>Phone Number</label>
                        <input type="tel" id="phoneInput" name="phone_number"
                            placeholder="Enter your 10-digit phone number" required maxlength="10"
                            style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;">
                        <small id="phoneError" style="color: red; display: none;">Please enter a valid 10-digit phone
                            number.</small>
                    </div>

                    <div class="input-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Enter your full address"
                            required
                            style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Bio (Optional)</label>
                        <textarea name="bio" class="form-control" rows="3" placeholder="Tell us a bit about yourself..."
                            style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                    </div>

                    <button type="submit" id="submitBtn" class="btn btn-primary btn-block">All Set!</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const phoneInput = document.getElementById('phoneInput');
        const phoneError = document.getElementById('phoneError');
        const submitBtn = document.getElementById('submitBtn');

        phoneInput.addEventListener('input', function (e) {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');

            // Validate length
            if (this.value.length === 10) {
                this.style.borderColor = '#10B981'; // Green
                phoneError.style.display = 'none';
                submitBtn.disabled = false;
            } else {
                this.style.borderColor = '#EF4444'; // Red
                if (this.value.length > 0) {
                    phoneError.style.display = 'block';
                }
                submitBtn.disabled = true;
            }
        });
    </script>
</body>

</html>