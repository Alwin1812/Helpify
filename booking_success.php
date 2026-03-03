<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['success'])) {
    header('Location: dashboard.php');
    exit;
}
$success_message = $_SESSION['success'];
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Successful - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/home_redesign.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            background-color: var(--bg-light);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }

        .success-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .success-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            padding: 3rem 2rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .success-icon-wrapper {
            width: 80px;
            height: 80px;
            background: #D1FAE5;
            /* Light emerald */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            opacity: 0;
            transform: scale(0.5);
        }

        .success-icon-wrapper .material-icons {
            font-size: 40px;
            color: #10B981;
            /* Emerald */
        }

        h1 {
            color: var(--primary-dark);
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }

        p {
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-outline:hover {
            background: #F3F4F6;
        }

        @keyframes popIn {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Loader Dots Animation */
        .matching-loader {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 1.5rem 0;
            background: #F3F4F6;
            padding: 1rem;
            border-radius: 8px;
        }

        .matching-loader span {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .dot {
            width: 8px;
            height: 8px;
            background-color: var(--primary-color);
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .dot1 {
            animation-delay: -0.32s;
        }

        .dot2 {
            animation-delay: -0.16s;
        }

        @keyframes bounce {

            0%,
            80%,
            100% {
                transform: scale(0);
            }

            40% {
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <header class="uc-header">
        <div class="header-container">
            <div class="header-left">
                <a href="index.php" class="logo" style="text-decoration: none; display: flex; align-items: center;">
                    <span
                        style="background: black; color: white; padding: 4px 8px; border-radius: 6px; margin-right: 8px; font-weight: 700; font-size: 1.1rem; line-height: 1;">HF</span>
                    <span
                        style="color: #111827; font-weight: 800; font-size: 1.4rem; letter-spacing: -0.5px;">HELPIFY</span>
                </a>
            </div>
            <div class="header-right">
                <nav class="nav-links">
                    <a href="dashboard.php" class="btn btn-primary"
                        style="padding: 0.5rem 1.5rem; color: white;">Dashboard</a>
                </nav>
            </div>
        </div>
    </header>

    <div class="success-container">
        <div class="success-card">
            <div class="success-icon-wrapper">
                <span class="material-icons">check</span>
            </div>
            <h1>Booking Confirmed!</h1>
            <p>
                <?php echo htmlspecialchars($success_message); ?>
            </p>

            <div
                style="margin: 2rem 0; padding: 1rem; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px;">
                <span class="material-icons"
                    style="color: #10B981; font-size: 32px; display: block; margin: 0 auto 0.5rem auto;">task_alt</span>
                <span
                    style="color: #065F46; font-weight: 700; font-size: 1.1rem; display: block; margin-bottom: 0.5rem;">A
                    professional is ready!</span>
                <p style="font-size: 0.9rem; color: #047857; margin: 0;">We've already assigned a helper to your job.
                    Check your dashboard to view their profile and contact them.</p>
            </div>

            <div class="actions">
                <a href="dashboard.php" class="btn btn-primary"
                    style="background-color: #10B981; border-color: #10B981; box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.39);">View
                    Booking Details</a>
            </div>
        </div>
    </div>
</body>

</html>