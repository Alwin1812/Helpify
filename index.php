<?php
// index.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpify - Simple Household Services</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
            <nav class="nav-links">
                <a href="index.php" class="active">Home</a>
                <a href="#services">Services</a>
                <a href="#about">About</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-primary" style="margin-left: 1rem; color: white;">Dashboard</a>
                <?php else: ?>
                    <a href="login.php">Sign In</a>
                    <a href="register.php" class="btn btn-primary" style="margin-left: 1rem; color: white;">Sign Up</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container grid" style="grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center;">
            <div class="hero-content">
                <h1>Simplifying Your Household Needs</h1>
                <p>Find trusted helpers for cleaning, cooking, babysitting, and more. Book instantly with Helpify.</p>
                <a href="register.php" class="btn btn-white" style="background: white; color: var(--primary-color);">Get
                    Started</a>
                <a href="#services" class="btn btn-outline"
                    style="border-color: white; color: white; margin-left: 1rem;">View Services</a>
            </div>
            <div class="hero-image">
                <img src="assets/images/hero.png" alt="Helpify Hero" class="floating-img">
            </div>
        </div>
    </section>

    <section id="services" class="py-12">
        <div class="container">
            <h2 class="text-center" style="margin-bottom: 3rem;">Our Services</h2>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                <!-- Service 1 -->
                <!-- Service 1 -->
                <!-- Service 1 -->
                <div class="service-card">
                    <div class="service-img-box">
                        <img src="assets/images/service-cleaning.png" alt="Cleaning" class="service-img">
                    </div>
                    <h3 class="service-title">Cleaning</h3>
                    <p class="text-light">Professional home cleaning services experienced staff.</p>
                    <div class="service-price">₹500 / visit</div>
                    <a href="dashboard.php" class="btn btn-primary btn-block" style="margin-top: 1rem;">Book Now</a>
                </div>

                <!-- Service 2 -->
                <div class="service-card">
                    <div class="service-img-box">
                        <img src="assets/images/service-cooking.png" alt="Cooking" class="service-img">
                    </div>
                    <h3 class="service-title">Cooking</h3>
                    <p class="text-light">Delicious home-cooked meals by expert chefs.</p>
                    <div class="service-price">₹800 / day</div>
                    <a href="dashboard.php" class="btn btn-primary btn-block" style="margin-top: 1rem;">Book Now</a>
                </div>

                <!-- Service 3 -->
                <div class="service-card">
                    <div class="service-img-box">
                        <img src="assets/images/service-babysitting.png" alt="Babysitting" class="service-img">
                    </div>
                    <h3 class="service-title">Babysitting</h3>
                    <p class="text-light">Trusted and caring babysitters for your little ones.</p>
                    <div class="service-price">₹400 / hour</div>
                    <a href="dashboard.php" class="btn btn-primary btn-block" style="margin-top: 1rem;">Book Now</a>
                </div>

                <!-- Service 4 -->
                <div class="service-card">
                    <div class="service-img-box">
                        <img src="assets/images/service-elderly.png" alt="Elderly Care" class="service-img">
                    </div>
                    <h3 class="service-title">Elderly Care</h3>
                    <p class="text-light">Compassionate care for senior citizens.</p>
                    <div class="service-price">₹600 / visit</div>
                    <a href="dashboard.php" class="btn btn-primary btn-block" style="margin-top: 1rem;">Book Now</a>
                </div>
            </div>
        </div>
    </section>

    <footer style="background: #111827; color: white; padding: 4rem 0; margin-top: auto;">
        <div class="container text-center">
            <h2 class="logo" style="margin-bottom: 1rem;">Helpify</h2>
            <p style="color: #9CA3AF; margin-bottom: 2rem;">Connecting you with the best household helpers.</p>
            <p style="font-size: 0.9rem; color: #4B5563;">&copy; 2025 Helpify. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>