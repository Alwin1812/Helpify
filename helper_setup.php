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
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="index.php" class="back-btn material-icons"
                    style="display: none; text-decoration: none; color: #111827; font-size: 1.8rem;">arrow_back</a>
                <a href="index.php" class="logo">Helpify</a>
            </div>
        </div>
    </header>

    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-left" style="width: 100%;">
                <h2 style="margin-bottom: 0.5rem; font-size: 2rem; color: var(--primary-dark);">Complete Your Profile
                </h2>
                <p style="margin-bottom: 2rem; color: var(--text-light);">Please provide your contact details to get
                    started.</p>

                <?php if (isset($_GET['error'])): ?>
                    <div
                        style="background: var(--danger); color: white; padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                        <?php
                        $error = $_GET['error'];
                        if ($error === 'missing_fields')
                            echo "Please fill in all required fields.";
                        elseif ($error === 'invalid_phone')
                            echo "Please enter a valid 10-digit phone number.";
                        elseif ($error === 'db_error')
                            echo "An error occurred while saving your profile. Please try again.";
                        else
                            echo "Something went wrong. Please try again.";
                        ?>
                    </div>
                <?php endif; ?>


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
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label style="margin: 0;">Address</label>
                            <button type="button" class="btn btn-outline"
                                style="padding: 0.25rem 0.5rem; font-size: 0.75rem; display: flex; align-items: center; gap: 4px;"
                                onclick="getCurrentLocation()">
                                <span class="material-icons" style="font-size: 14px;">my_location</span> Use Current
                                Location
                            </button>
                        </div>
                        <textarea name="address" id="addressInput" class="form-control" rows="3"
                            placeholder="Enter your full address" required
                            style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                        <div id="setupMap"
                            style="height: 200px; width: 100%; border-radius: 8px; margin-top: 0.5rem; border: 1px solid #ddd; display: none;">
                        </div>
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

        if (phoneInput) {
            phoneInput.addEventListener('input', function () {
                // Keep only numeric characters
                this.value = this.value.replace(/\D/g, '');

                if (this.value === '') {
                    this.style.borderColor = '#ddd';
                    phoneError.style.display = 'none';
                    submitBtn.disabled = true;
                } else {
                    const isValidPrefix = /^[6-9]/.test(this.value);
                    const isCorrectLength = this.value.length === 10;

                    if (isValidPrefix && isCorrectLength) {
                        this.style.borderColor = '#10B981'; // Green
                        phoneError.style.display = 'none';
                        submitBtn.disabled = false;
                    } else {
                        this.style.borderColor = '#EF4444'; // Red
                        phoneError.style.display = 'block';
                        if (!isValidPrefix) {
                            phoneError.textContent = "Number must start with 6, 7, 8, or 9.";
                        } else {
                            phoneError.textContent = "Please enter a valid 10-digit phone number.";
                        }
                        submitBtn.disabled = true;
                    }
                }
            });
        }

        // Map & Location Logic
        let setupMap, setupMarker;
        function initSetupMap() {
            if (setupMap) return;
            document.getElementById('setupMap').style.display = 'block';
            setupMap = L.map('setupMap').setView([9.9312, 76.2673], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(setupMap);
            setupMarker = L.marker([9.9312, 76.2673], { draggable: true }).addTo(setupMap);

            setupMarker.on('dragend', function () {
                const latlng = setupMarker.getLatLng();
                reverseGeocodeToInput(latlng.lat, latlng.lng);
            });
        }

        function getCurrentLocation() {
            initSetupMap();
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    setupMap.setView([lat, lng], 16);
                    setupMarker.setLatLng([lat, lng]);
                    reverseGeocodeToInput(lat, lng);
                }, (error) => {
                    alert("Location access denied or unavailable.");
                });
            }
        }

        function reverseGeocodeToInput(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.display_name) {
                        document.getElementById('addressInput').value = data.display_name;
                    }
                });
        }
    </script>
</body>

</html>