<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch Services
$stmt = $pdo->query("SELECT * FROM services");
$services = $stmt->fetchAll();

// Fetch Recent Bookings
$stmt = $pdo->prepare("
    SELECT b.*, s.name as service_name, h.name as helper_name 
    FROM bookings b 
    JOIN services s ON b.service_id = s.id 
    LEFT JOIN users h ON b.helper_id = h.id 
    WHERE b.user_id = ? 
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// Fetch Applicants for Accepted Bookings
$booking_ids = array_column($bookings, 'id');
$applicants = [];
if (!empty($booking_ids)) {
    $placeholders = str_repeat('?,', count($booking_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT br.*, u.name, u.gender, u.job_role, u.profile_photo, u.average_rating, u.hourly_rate, u.bio
        FROM booking_requests br
        JOIN users u ON br.helper_id = u.id
        WHERE br.booking_id IN ($placeholders) AND br.status = 'accepted'
    ");
    $stmt->execute($booking_ids);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        // Mock completed jobs count for now ... (omitted comments)
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE helper_id = ? AND status = 'completed'");
        $stmt_count->execute([$row['helper_id']]);
        $row['completed_jobs_count'] = $stmt_count->fetchColumn();

        $applicants[$row['booking_id']][] = $row;
    }

    // Sort applicants by Rating DESC, then Jobs DESC
    foreach ($applicants as $bid => &$apps) {
        usort($apps, function ($a, $b) {
            if ($a['average_rating'] == $b['average_rating']) {
                return $b['completed_jobs_count'] - $a['completed_jobs_count'];
            }
            return ($b['average_rating'] < $a['average_rating']) ? -1 : 1;
        });
    }
}

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(); // In real app, mark as read on view or dismiss

// Fetch my reviews to know which bookings I reviewed
$stmt = $pdo->prepare("SELECT booking_id FROM reviews WHERE reviewer_id = ?");
$stmt->execute([$user_id]);
$reviewed_bookings = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
        }

        .helper-card {
            border: 1px solid #eee;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-light);
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            cursor: pointer;
            color: #ddd;
            font-size: 24px;
        }

        .star-rating input:checked~label {
            color: #f59e0b;
        }

        .star-rating label:hover,
        .star-rating label:hover~label {
            color: #f59e0b;
        }

        #map {
            height: 200px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
        }

        .location-container {
            position: relative;
        }

        .locate-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .locate-btn:hover {
            background: #f3f4f6;
        }

        .input-with-button {
            padding-right: 40px !important;
        }

        /* Validation Styles */
        .form-control.invalid {
            border-color: #EF4444 !important;
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
        }

        .form-control.valid {
            border-color: #10B981 !important;
        }

        .error-message {
            color: #EF4444;
            font-size: 0.75rem;
            margin-top: -0.5rem;
            margin-bottom: 0.5rem;
            display: none;
            font-weight: 500;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <header>
        <div
            style="width: 100%; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
            <nav class="nav-links" style="display: flex; align-items: center;">
                <span>Welcome, <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b></span>
                <a href="api/logout.php" class="btn btn-primary" style="margin-left: 1rem;">Logout</a>
            </nav>
        </div>
    </header>

    <?php
    // Fetch User Details for Profile
    $stmt = $pdo->prepare("SELECT email, gender, phone_number, address, profile_photo FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch();
    ?>

    <div class="dashboard-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="nav-item active" onclick="showSection('dashboard')">
                <span class="material-icons">dashboard</span> Dashboard
            </div>
            <div class="nav-item" onclick="showSection('book')">
                <span class="material-icons">add_circle</span> Book Service
            </div>
            <div class="nav-item" onclick="showSection('history')">
                <span class="material-icons">history</span> History
            </div>
            <div class="nav-item" onclick="showSection('profile')">
                <span class="material-icons">person</span> Profile
            </div>
            <div class="nav-item cart-nav-item" onclick="openCartModal()" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 1rem; padding-top: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;"><span class="material-icons">shopping_cart</span> Cart</div>
                <span id="cartCountBadge" style="background: var(--warning); color: #000; padding: 2px 8px; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">0</span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">

            <!-- Dashboard Section -->
            <div id="dashboard-section" class="tab-content">
                <h2 style="margin-bottom: 2rem;">Dashboard Overview</h2>

                <!-- Notifications -->
                <?php if (count($notifications) > 0): ?>
                    <div style="margin-bottom: 2rem;">
                        <?php foreach ($notifications as $note): ?>
                            <div style="
                                padding: 1rem; 
                                border-radius: 8px; 
                                margin-bottom: 0.5rem; 
                                border-left: 5px solid;
                                background: white;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                border-color: <?php echo ($note['type'] == 'success') ? '#10B981' : (($note['type'] == 'error') ? '#EF4444' : '#3B82F6'); ?>;
                            ">
                                <p style="margin: 0; color: #333;">
                                    <?php echo htmlspecialchars($note['message']); ?>
                                </p>
                                <small style="color: #999;">
                                    <?php echo date('h:i A', strtotime($note['created_at'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        <!-- Mark all as read button/link could go here -->
                        <div style="text-align: right;">
                            <a href="api/mark_notifications_read.php"
                                style="font-size: 0.9rem; color: var(--primary-color);">Clear All</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Booking Applications Preview -->
                <?php
                $has_applications = false;
                foreach ($bookings as $b) {
                    if (isset($applicants[$b['id']]) && count($applicants[$b['id']]) > 0) {
                        $has_applications = true;
                        break;
                    }
                }
                ?>

                <?php if ($has_applications): ?>
                    <h3 style="margin-bottom: 1rem;">Review Applications</h3>
                    <div class="grid"
                        style="grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                        <?php foreach ($bookings as $booking): ?>
                            <?php if (isset($applicants[$booking['id']]) && count($applicants[$booking['id']]) > 0): ?>
                                <div
                                    style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                        <div>
                                            <h4 style="margin: 0;">
                                                <?php echo htmlspecialchars($booking['service_name']); ?>
                                            </h4>
                                            <small style="color: #666;">
                                                <?php echo date('d M, h:i A', strtotime($booking['date'])); ?>
                                            </small>
                                        </div>
                                        <span
                                            style="background: #ede9fe; color: #7c3aed; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">
                                            <?php echo count($applicants[$booking['id']]); ?> Applied
                                        </span>
                                    </div>

                                    <div
                                        style="border-top: 1px solid #eee; padding-top: 1rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
                                        <?php
                                        $top_rated_shown = false;
                                        foreach ($applicants[$booking['id']] as $index => $app):
                                            // Since array is already sorted by Best Rating/Jobs, the first one is Top Rated
                                            $is_top_rated = ($index === 0 && $app['average_rating'] > 0);
                                            ?>
                                            <div
                                                style="display: flex; flex-direction: column; gap: 1rem; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; position: relative; background: #fff;">

                                                <?php if ($is_top_rated): ?>
                                                    <span
                                                        style="position: absolute; top: 0; right: 0; background: #FEF3C7; color: #D97706; font-size: 0.7rem; padding: 2px 8px; border-radius: 99px; font-weight: 600;">
                                                        Top Rated
                                                    </span>
                                                <?php endif; ?>

                                                <div style="display: flex; gap: 1rem; align-items: center;">
                                                    <div
                                                        style="width: 48px; height: 48px; background: #f3f4f6; border-radius: 50%; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border: 2px solid <?php echo $is_top_rated ? '#FCD34D' : 'transparent'; ?>;">
                                                        <?php if (!empty($app['profile_photo'])): ?>
                                                            <img src="<?php echo htmlspecialchars($app['profile_photo']); ?>"
                                                                style="width: 100%; height: 100%; object-fit: cover;">
                                                        <?php else: ?>
                                                            <span class="material-icons"
                                                                style="font-size: 24px; color: #9ca3af;">person</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div style="flex: 1; padding-right: 60px;"> <!-- Padding for badge -->
                                                        <div style="font-weight: 600; font-size: 0.95rem; color: #111;">
                                                            <?php echo htmlspecialchars($app['name']); ?>
                                                        </div>
                                                        <div
                                                            style="font-size: 0.85rem; color: #666; margin-top: 2px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                                                            <span
                                                                style="display: flex; align-items: center; color: #F59E0B; font-weight: bold;">
                                                                <span class="material-icons"
                                                                    style="font-size: 14px; margin-right: 2px;">star</span>
                                                                <?php echo $app['average_rating'] > 0 ? $app['average_rating'] : 'New'; ?>
                                                            </span>
                                                            <span
                                                                style="width: 4px; height: 4px; background: #ddd; border-radius: 50%;"></span>
                                                            <span><?php echo $app['completed_jobs_count']; ?> Jobs</span>
                                                            <span
                                                                style="width: 4px; height: 4px; background: #ddd; border-radius: 50%;"></span>
                                                            <span>Est:
                                                                <?php echo htmlspecialchars($app['arrival_estimate'] ?: 'N/A'); ?></span>
                                                            <?php if (!empty($app['hourly_rate'])): ?>
                                                                <span
                                                                    style="width: 4px; height: 4px; background: #ddd; border-radius: 50%;"></span>
                                                                <span
                                                                    style="color: #10B981; font-weight: 600;">₹<?php echo $app['hourly_rate']; ?>/hr</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <a href="#"
                                                            onclick='viewHelperProfile(<?php echo json_encode($app, JSON_HEX_APOS | JSON_HEX_QUOT); ?>); return false;'
                                                            style="font-size: 0.8rem; color: var(--primary-color); text-decoration: none; font-weight: 500; display: inline-block; margin-top: 4px;">View
                                                            Profile</a>
                                                    </div>
                                                </div>

                                                <div style="display: flex; gap: 0.5rem; flex-direction: column;">
                                                    <form action="api/booking_action.php" method="POST">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="helper_id" value="<?php echo $app['helper_id']; ?>">

                                                        <input type="text" name="message" class="form-control"
                                                            placeholder="Optional message to helper..."
                                                            style="margin-bottom: 0.5rem; font-size: 0.9rem; padding: 0.5rem;">

                                                        <div style="display: flex; gap: 0.5rem;">
                                                            <button type="submit" name="action" value="confirm_helper"
                                                                class="btn btn-primary"
                                                                style="flex: 1; padding: 0.5rem; font-size: 0.85rem; background-color: #0056D2;">Accept</button>
                                                            <button type="submit" name="action" value="reject_applicant"
                                                                class="btn btn-outline"
                                                                style="flex: 1; padding: 0.5rem; font-size: 0.85rem; color: #DC2626; border-color: #DC2626;"
                                                                onclick="return confirm('Decline this helper?')">Decline</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="grid mb-4"
                    style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div class="stat-card">
                        <h3>Total Bookings</h3>
                        <div class="stat-value"><?php
                        echo count(array_filter($bookings, function ($b) {
                            return $b['status'] !== 'cancelled';
                        }));
                        ?></div>
                        <span style="color: var(--text-light); font-size: 0.9rem;">Lifetime</span>
                    </div>
                    <div class="stat-card">
                        <h3>Wallet Balance</h3>
                        <div class="stat-value">₹1,500</div>
                        <span style="color: var(--text-light); font-size: 0.9rem;">Available Credits</span>
                    </div>
                </div>

                <!-- Recent Activity Preview -->
                <div style="margin-top: 2rem; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                    <h3>Welcome Back!</h3>
                    <p>Ready to book your next service? Go to the <strong>Book Service</strong> tab to find help.</p>
                </div>
            </div>

            <!-- Book Service Section -->
            <div id="book-section" class="tab-content" style="display: none;">
                <h3 style="margin-bottom: 2rem;">Book a Service</h3>

                <?php if (isset($_SESSION['success'])): ?>
                    <div
                        style="background: var(--success); color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php echo $_SESSION['success'];
                        unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div style="background: #DC2626; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php
                // Group Services
                $parent_services = [];
                $sub_services_map = [];

                foreach ($services as $s) {
                    if (empty($s['parent_id'])) {
                        $parent_services[] = $s;
                    } else {
                        $sub_services_map[$s['parent_id']][] = $s;
                    }
                }
                ?>

                <!-- Hidden data for JS -->
                <script>
                    const subServicesMap = <?php echo json_encode($sub_services_map); ?>;
                    const serviceImages = {
                        // InstaHelp
                        'Maid Service': 'assets/images/service-cleaning.png',
                        'Cooking': 'assets/images/service-cooking.png',
                        'Babysitting': 'assets/images/service-babysitting.png',
                        'Elderly Care': 'assets/images/service-elderly.png',
                        'Patient Care': 'assets/images/service_patient_care.png',
                        // Laundry
                        'Washing & Ironing': 'assets/images/service_washing_ironing.png',
                        'Dry Cleaning': 'assets/images/service_dry_cleaning.png',
                        'Blanket & Curtain Wash': 'assets/images/service_blanket_wash.png',
                        'Shoe Cleaning & Polish': 'assets/images/service_shoe_clean.png',
                        'Premium Suit Care': 'assets/images/service_premium_suit.png',
                        // Car Wash
                        'Complete Exterior Wash': 'assets/images/service_exterior_wash.png',
                        'Full Interior Detailing': 'assets/images/service_interior_detailing.png',
                        'Engine Steam Cleaning': 'assets/images/service_engine_steam.png',
                        'Deep Paint Correction': 'assets/images/service_paint_correction.jpg',
                        // Cleaning & Pest
                        'Full Home Deep Cleaning': 'assets/images/service_cleaning_pest.png',
                        'Sofa & Carpet Cleaning': 'assets/images/service-cleaning.png',
                        'Kitchen Deep Cleaning': 'assets/images/service_kitchen_cleaning_new.jpg',
                        'Bathroom Cleaning': 'assets/images/service_bathroom_cleaning_new.png',
                        'Pest Control Service': 'assets/images/service_pest_control_new.png',
                        // Electrician, Plumber & Carpenter
                        'Electrician': 'assets/images/service_electrician_new.png',
                        'Plumber': 'assets/images/service_plumber_new.png',
                        'Carpenter': 'assets/images/service_carpenter_new.png',
                        // Native Water Purifier
                        'RO Service': 'assets/images/service_ro_service.png',
                        'RO Installation': 'assets/images/service_ro_installation.png',
                        'Filter Change': 'assets/images/service_filter_change.png',
                        // Painting & Waterproofing
                        'Room Painting': 'assets/images/service_painting.png',
                        'Waterproofing': 'assets/images/service_waterproofing.png',
                        'Wallpaper Installation': 'assets/images/service_wall.png',
                        // AC & Appliance Repair
                        'AC Service': 'assets/images/service_ac_service.png',
                        'AC Gas Refill': 'assets/images/service_ac_gas_refill.png',
                        'Fridge Repair': 'assets/images/service_fridge_repair.png',
                        'Washing Machine Repair': 'assets/images/service_washing_machine_repair.png',
                        // Wall makeover by Revamp
                        '3D Wall Panel': 'assets/images/service_3d_wall_panel.png',
                        'Texture Painting': 'assets/images/service_texture_painting.png',
                        'Custom Wallpaper': 'assets/images/service_custom_wallpaper.png'
                    };
                </script>

                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($parent_services as $service): ?>
                        <div class="service-card" style="text-align: left;">
                            <div class="service-img-box">
                                <?php
                                $known_images = [
                                    'InstaHelp' => 'service_instahelp.png',
                                    'Laundry & Dry Cleaning' => 'service_laundry.png',
                                    'Car Wash & Detailing' => 'service_carwash.png',
                                    'Women\'s Salon & Spa' => 'service_womens_salon.png',
                                    'Men\'s Salon & Massage' => 'service_mens_salon.png',
                                    'Cleaning & Pest Control' => 'service_cleaning_pest.png',
                                    'Electrician, Plumber & Carpenter' => 'service_plumbing.png',
                                    'Native Water Purifier' => 'service_water.png',
                                    'Painting & Waterproofing' => 'service_painting.png',
                                    'AC & Appliance Repair' => 'service_electrical.png',
                                    'Wall makeover by Revamp' => 'service_wall.png',
                                    'Babysitting' => 'service-babysitting.png',
                                    'Elderly Care' => 'service-elderly.png',
                                    'Cleaning' => 'service-cleaning.png',
                                    'Cooking' => 'service-cooking.png'
                                ];

                                $img_src = null;
                                if (isset($known_images[$service['name']])) {
                                    $potential_src = 'assets/images/' . $known_images[$service['name']];
                                    if (file_exists($potential_src)) {
                                        $img_src = $potential_src;
                                    }
                                }
                                ?>
                                <?php if ($img_src): ?>
                                    <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($service['name']); ?>"
                                        class="service-img">
                                <?php else: ?>
                                    <div
                                        style="width:100%; height:100%; background: #EEF2FF; display: flex; align-items: center; justify-content: center; color: var(--primary-color);">
                                        <span class="material-icons"
                                            style="font-size: 64px;"><?php echo htmlspecialchars($service['icon'] ?? 'work'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h4 style="margin: 0; font-size: 1.25rem; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($service['name']); ?>
                            </h4>
                            <div style="color: var(--text-light); font-size: 0.9rem; margin-bottom: 1rem;">
                                <?php echo htmlspecialchars($service['description'] ?? ''); ?>
                            </div>

                            <?php if (isset($sub_services_map[$service['id']])): ?>
                                <button class="btn btn-primary btn-block"
                                    onclick="openCategoryModal(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name'], ENT_QUOTES); ?>')">View
                                    Services</button>
                            <?php else: ?>
                                <div style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">
                                    From ₹<?php echo $service['base_price']; ?>
                                </div>
                                <div id="action-container-parent-<?php echo $service['id']; ?>">
                                    <button class="btn btn-primary btn-block"
                                        onclick="showBookingOptionsParent('<?php echo $service['id']; ?>', '<?php echo htmlspecialchars($service['name'], ENT_QUOTES); ?>', <?php echo $service['base_price']; ?>)">Book</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Category Modal (Sub-services) -->
            <div id="categoryModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeCategoryModal()">&times;</span>
                    <h2 id="categoryModalTitle" style="margin-bottom: 1.5rem;">Services</h2>
                    <div id="categoryModalContent" class="grid"
                        style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <!-- JS Rendered -->
                    </div>
                </div>
            </div>

            <!-- Booking section renamed to Cart Modal -->
            <div id="bookingModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeBookingModal()">&times;</span>
                    <h2>Cart Checkout</h2>
                    <div id="cartItemsContainer" style="margin: 1rem 0; max-height: 200px; overflow-y: auto; border: 1px solid #E5E7EB; border-radius: 8px; padding: 1rem;">
                        <p style="text-align:center; color: var(--text-light); margin:0;">Your cart is empty.</p>
                    </div>
                    <div style="text-align: right; font-weight: 700; font-size: 1.2rem; margin-bottom: 1rem;">Total: ₹<span id="cartTotalDisplay">0</span></div>
                    
                    <form action="api/booking_action.php" method="POST" id="checkoutForm" onsubmit="return validateCheckout()">
                        <input type="hidden" name="action" value="book">
                        <div id="cartHiddenInputs"></div>

                        <div class="input-group">
                            <label>Date</label>
                            <input type="date" name="date" id="bookingDate" class="form-control" required
                                min="<?php echo date('Y-m-d'); ?>" oninput="validateBookingField(this)">
                            <div class="error-message" id="dateError">Please select a valid future date.</div>
                        </div>
                        <div class="input-group">
                            <label>Time</label>
                            <input type="time" name="time" id="bookingTime" class="form-control" required
                                oninput="validateBookingField(this)">
                            <div class="error-message" id="timeError">Please select a valid time.</div>
                        </div>
                        <div class="input-group">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <label style="margin: 0;">Location</label>
                                <button type="button" class="btn btn-outline"
                                    style="padding: 0.25rem 0.5rem; font-size: 0.75rem; display: flex; align-items: center; gap: 4px;"
                                    onclick="getCurrentLocation()">
                                    <span class="material-icons" style="font-size: 14px;">my_location</span> Use Current
                                    Location
                                </button>
                            </div>
                            <div class="location-container">
                                <input type="text" name="location" id="locationInput"
                                    class="form-control input-with-button" placeholder="Your Address" required
                                    oninput="validateBookingField(this)">
                                <button type="button" class="locate-btn" onclick="getCurrentLocation()"
                                    title="Use current location">
                                    <span class="material-icons">my_location</span>
                                </button>
                            </div>
                            <div class="error-message" id="locationError">Please provide a service location.</div>
                        </div>
                        <div id="map"></div>


                        <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">Confirm
                            Booking Request</button>
                    </form>
                </div>
            </div>

            <!-- History Section -->
            <div id="history-section" class="tab-content" style="display: none;">
                <h3 style="margin-bottom: 2rem;">Booking History</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Details</th>
                                <th>Date/Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($bookings) > 0): ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                        <td>
                                            <?php if ($booking['helper_name']): ?>
                                                Helper: <b><?php echo htmlspecialchars($booking['helper_name']); ?></b><br>
                                            <?php endif; ?>
                                            <small><?php echo htmlspecialchars($booking['location'] ?? 'No location'); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('d-M-Y', strtotime($booking['date'])); ?>
                                            <br>
                                            <small><?php echo $booking['time'] ? date('h:i A', strtotime($booking['time'])) : ''; ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                            <?php if ($booking['status'] === 'accepted' && isset($applicants[$booking['id']])): ?>
                                                <br><small style="color: purple;"><?php echo count($applicants[$booking['id']]); ?>
                                                    helpers applied</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] == 'accepted' && isset($applicants[$booking['id']])): ?>
                                                <button class="btn btn-primary"
                                                    onclick='showApplicants(<?php echo json_encode($applicants[$booking['id']]); ?>, <?php echo $booking['id']; ?>)'>
                                                    Select Helper
                                                </button>
                                            <?php elseif ($booking['status'] == 'pending' || $booking['status'] == 'confirmed'): ?>
                                                <form action="api/booking_action.php" method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" class="btn btn-outline"
                                                        style="padding: 0.25rem 0.75rem; font-size: 0.875rem; color: #DC2626; border-color: #DC2626; margin-left: 0.5rem;">
                                                        Cancel
                                                    </button>
                                                </form>
                                            <?php elseif ($booking['status'] == 'completed'): ?>
                                                <?php if (in_array($booking['id'], $reviewed_bookings)): ?>
                                                    <button class="btn btn-outline" disabled>Reviewed</button>
                                                <?php else: ?>
                                                    <button class="btn btn-primary"
                                                        onclick="openReviewModal(<?php echo $booking['id']; ?>)">Rate</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button class="btn btn-outline" disabled style="opacity: 0.5;">-</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-light);">No bookings found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Applicant Selection Modal -->
            <div id="applicantsModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeApplicantsModal()">&times;</span>
                    <h2>Select a Helper</h2>
                    <div id="applicantsList" style="margin-top: 1rem;">
                        <!-- JS populated -->
                    </div>
                </div>
            </div>

            <!-- Review Modal -->
            <div id="reviewModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeReviewModal()">&times;</span>
                    <h2>Rate Service</h2>
                    <form action="api/submit_review.php" method="POST" style="margin-top: 1rem;">
                        <input type="hidden" name="booking_id" id="reviewBookingId">

                        <div class="input-group">
                            <label>Rating</label>
                            <div class="star-rating">
                                <input type="radio" id="star5" name="rating" value="5" required /><label for="star5"
                                    title="5 stars">★</label>
                                <input type="radio" id="star4" name="rating" value="4" /><label for="star4"
                                    title="4 stars">★</label>
                                <input type="radio" id="star3" name="rating" value="3" /><label for="star3"
                                    title="3 stars">★</label>
                                <input type="radio" id="star2" name="rating" value="2" /><label for="star2"
                                    title="2 stars">★</label>
                                <input type="radio" id="star1" name="rating" value="1" /><label for="star1"
                                    title="1 star">★</label>
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Comments</label>
                            <textarea name="comment" class="form-control" rows="3"
                                placeholder="Share your experience..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">Submit
                            Review</button>
                    </form>
                </div>
            </div>

            <!-- Profile View Modal -->
            <div id="helperProfileModal" class="modal">
                <div class="modal-content" style="max-width: 400px; text-align: center;">
                    <span class="close" onclick="closeProfileModal()">&times;</span>
                    <div id="helperProfileContent"></div>
                </div>
            </div>

            <!-- Profile Section -->
            <div id="profile-section" class="tab-content" style="display: none;">
                <h2 style="margin-bottom: 2rem;">My Profile</h2>
                <div
                    style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <form action="api/update_profile.php" method="POST" enctype="multipart/form-data">

                        <!-- Profile Photo Preview -->
                        <div style="text-align: center; margin-bottom: 2rem;">
                            <div
                                style="width: 100px; height: 100px; background: #f3f4f6; border-radius: 50%; overflow: hidden; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($user_details['profile_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($user_details['profile_photo']); ?>"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span class="material-icons" style="font-size: 48px; color: #9ca3af;">person</span>
                                <?php endif; ?>
                            </div>
                            <label for="c_photo_upload"
                                style="cursor: pointer; color: var(--primary-color); font-size: 0.9rem;">
                                Change Photo
                                <input type="file" id="c_photo_upload" name="profile_photo" accept="image/*"
                                    style="display: none;">
                            </label>
                        </div>

                        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <div class="input-group">
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-light);">Full
                                    Name</label>
                                <input type="text" name="name"
                                    value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>"
                                    style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"
                                    required>
                            </div>
                            <div class="input-group">
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-light);">Email
                                    Address</label>
                                <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>"
                                    style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; background: #f3f4f6;"
                                    readonly>
                            </div>
                            <div class="input-group">
                                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-light);">Phone
                                    Number</label>
                                <input type="tel" name="phone_number"
                                    value="<?php echo htmlspecialchars($user_details['phone_number'] ?? ''); ?>"
                                    pattern="[0-9]{10}" maxlength="10"
                                    style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"
                                    placeholder="10-digit number">
                            </div>
                            <div class="input-group">
                                <label
                                    style="display: block; margin-bottom: 0.5rem; color: var(--text-light);">Gender</label>
                                <select name="gender"
                                    style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; background: #fff;">
                                    <option value="" disabled <?php echo empty($user_details['gender']) ? 'selected' : ''; ?>>Select Gender</option>
                                    <option value="Male" <?php echo ($user_details['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($user_details['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($user_details['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-group" style="margin-top: 1rem;">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <label style="margin: 0; color: var(--text-light);">Address</label>
                                <button type="button" class="btn btn-outline"
                                    style="padding: 0.25rem 0.5rem; font-size: 0.75rem; display: flex; align-items: center; gap: 4px;"
                                    onclick="getLocationForProfile()">
                                    <span class="material-icons" style="font-size: 14px;">my_location</span> Use Current
                                    Location
                                </button>
                            </div>
                            <textarea name="address" id="profileAddress" rows="2"
                                style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($user_details['address'] ?? ''); ?></textarea>
                            <div id="profileMap"
                                style="height: 150px; width: 100%; border-radius: 8px; margin-top: 0.5rem; border: 1px solid #ddd; display: none;">
                            </div>
                        </div>
                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary"
                                style="background:var(--primary-color); color: white; padding: 0.8rem 1.5rem;">Save
                                Changes</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));

            let targetId = sectionId + '-section';
            if (sectionId === 'dashboard') targetId = 'dashboard-section';

            const el = document.getElementById(targetId);
            if (el) el.style.display = 'block';

            const indexMap = { 'dashboard': 0, 'book': 1, 'history': 2, 'profile': 3 };
            const navItems = document.querySelectorAll('.nav-item');
            if (navItems[indexMap[sectionId]]) {
                navItems[indexMap[sectionId]].classList.add('active');
            }
        }

        // Modal Logic
        const bookingModal = document.getElementById('bookingModal');
        const applicantsModal = document.getElementById('applicantsModal');
        const reviewModal = document.getElementById('reviewModal');
        const categoryModal = document.getElementById('categoryModal');

        function openCategoryModal(categoryId, categoryName) {
            document.getElementById('categoryModalTitle').innerText = categoryName;
            const content = document.getElementById('categoryModalContent');
            content.innerHTML = '';

            const services = subServicesMap[categoryId] || [];
            if (services.length === 0) {
                content.innerHTML = '<p>No services available in this category.</p>';
            } else {
                services.forEach(s => {
                    const imgSrc = serviceImages[s.name];
                    const div = document.createElement('div');
                    div.className = 'service-card';
                    div.style.textAlign = 'left';
                    div.style.padding = '0';
                    div.style.overflow = 'hidden';

                    div.innerHTML = `
                        <div class="service-img-box" style="height: 140px;">
                            ${imgSrc ?
                            `<img src="${imgSrc}" alt="${s.name}" class="service-img">` :
                            `<div style="width:100%; height:100%; background: #EEF2FF; display: flex; align-items: center; justify-content: center; color: var(--primary-color);">
                                    <span class="material-icons" style="font-size: 48px;">${s.icon || 'work'}</span>
                                </div>`
                        }
                        </div>
                        <div style="padding: 1rem;">
                            <h4 style="font-size: 1.1rem; margin-bottom: 0.25rem;">${s.name}</h4>
                            <p style="color: var(--text-light); font-size: 0.85rem; margin-bottom: 1rem; line-height: 1.4;">${s.description || ''}</p>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto;">
                                <div style="font-weight: 700; color: var(--primary-color); font-size: 1rem;">₹${s.base_price}</div>
                                <div id="action-container-sub-${s.id}">
                                    <button class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.85rem;" onclick="showBookingOptionsSub('${s.id}', '${s.name.replace(/'/g, "\\'")}', ${s.base_price})">Book</button>
                                </div>
                            </div>
                        </div>
                    `;
                    content.appendChild(div);
                });
            }
            categoryModal.style.display = 'block';
        }

        function closeCategoryModal() {
            categoryModal.style.display = 'none';
        }

        let cart = [];

        function showBookingOptionsParent(id, name, price) {
            const container = document.getElementById('action-container-parent-' + id);
            container.innerHTML = `
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-outline" style="flex:1; font-size: 0.85rem; padding: 0.5rem;" onclick="addToCart('${id}', '${name}', ${price}, this)">Add to Cart</button>
                    <button class="btn btn-primary" style="flex:1; font-size: 0.85rem; padding: 0.5rem;" onclick="addToCart('${id}', '${name}', ${price}, null); openCartModal();">Continue Booking</button>
                </div>
            `;
        }

        function showBookingOptionsSub(id, name, price) {
            const container = document.getElementById('action-container-sub-' + id);
            container.innerHTML = `
                <div style="display: flex; gap: 0.25rem;">
                    <button class="btn btn-outline" style="padding: 0.4rem 0.5rem; font-size: 0.75rem;" onclick="addToCart('${id}', '${name}', ${price}, this)">Add to Cart</button>
                    <button class="btn btn-primary" style="padding: 0.4rem 0.5rem; font-size: 0.75rem;" onclick="addToCart('${id}', '${name}', ${price}, null); openCartModal();">Continue Booking</button>
                </div>
            `;
        }

        function addToCart(serviceId, serviceName, basePrice, btnElement) {
            cart.push({ id: serviceId, name: serviceName, price: parseFloat(basePrice) });
            updateCartUI();
            
            if (btnElement) {
                const originalText = btnElement.innerHTML;
                btnElement.innerHTML = 'Added ✔';
                btnElement.style.backgroundColor = 'var(--success)';
                btnElement.style.borderColor = 'var(--success)';
                btnElement.style.color = '#fff';
                setTimeout(() => {
                    btnElement.innerHTML = originalText;
                    btnElement.style.backgroundColor = '';
                    btnElement.style.borderColor = '';
                    btnElement.style.color = '';
                }, 1000);
            }
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartUI();
        }

        function updateCartUI() {
            document.getElementById('cartCountBadge').innerText = cart.length;
            
            const container = document.getElementById('cartItemsContainer');
            const totalDisplay = document.getElementById('cartTotalDisplay');
            const hiddenInputs = document.getElementById('cartHiddenInputs');
            
            hiddenInputs.innerHTML = '';
            
            if (cart.length === 0) {
                container.innerHTML = '<p style="text-align:center; color: var(--text-light); margin:0;">Your cart is empty.</p>';
                totalDisplay.innerText = '0';
                return;
            }
            
            let total = 0;
            let html = '';
            cart.forEach((item, index) => {
                total += item.price;
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6;">
                        <div>
                            <div style="font-weight: 600; color: var(--text-color);">${item.name}</div>
                            <div style="color: var(--primary-color); font-size: 0.9rem;">₹${item.price}</div>
                        </div>
                        <button type="button" onclick="removeFromCart(${index})" style="background: none; border: none; color: var(--danger); cursor: pointer; display: flex; align-items: center;">
                            <span class="material-icons" style="font-size: 18px;">delete</span>
                        </button>
                    </div>
                `;
                hiddenInputs.innerHTML += `<input type="hidden" name="service_ids[]" value="${item.id}">`;
            });
            
            container.innerHTML = html;
            totalDisplay.innerText = total;
        }

        function openCartModal() {
            bookingModal.style.display = 'block';
            initMap();
        }

        function closeBookingModal() {
            bookingModal.style.display = 'none';
        }

        function validateCheckout() {
            if (cart.length === 0) {
                alert('Your cart is empty. Please add services to book.');
                return false;
            }
            return true;
        }

        // Map Logic
        let map, marker;
        function initMap() {
            // Default center (can be user's previous address if available)
            const defaultLat = 9.9312; // Example: Kochi
            const defaultLng = 76.2673;

            if (!map) {
                map = L.map('map').setView([defaultLat, defaultLng], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

                marker.on('dragend', function (e) {
                    const latlng = marker.getLatLng();
                    reverseGeocode(latlng.lat, latlng.lng);
                });

                map.on('click', function (e) {
                    marker.setLatLng(e.latlng);
                    reverseGeocode(e.latlng.lat, e.latlng.lng);
                });
            } else {
                // Refresh map size because it might have been hidden
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
        }

        function getCurrentLocation() {
            if (navigator.geolocation) {
                const btn = document.querySelector('.locate-btn');
                const originalIcon = btn.innerHTML;
                btn.innerHTML = '<span class="material-icons" style="animation: spin 1s linear infinite">sync</span>';

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const latlng = [lat, lng];

                        map.setView(latlng, 16);
                        marker.setLatLng(latlng);
                        reverseGeocode(lat, lng);
                        btn.innerHTML = originalIcon;
                    },
                    (error) => {
                        alert("Geolocation failed: " + error.message);
                        btn.innerHTML = originalIcon;
                    }
                );
            } else {
                alert("Geolocation is not supported by your browser.");
            }
        }

        function reverseGeocode(lat, lng) {
            const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        document.getElementById('locationInput').value = data.display_name;
                    }
                })
                .catch(error => console.error('Error reverse geocoding:', error));
        }

        function closeApplicantsModal() {
            applicantsModal.style.display = 'none';
        }

        function openReviewModal(bookingId) {
            document.getElementById('reviewBookingId').value = bookingId;
            reviewModal.style.display = 'block';
        }

        function closeReviewModal() {
            reviewModal.style.display = 'none';
        }

        let currentApplicants = [];

        function showApplicants(applicants, bookingId) {
            currentApplicants = applicants;
            const list = document.getElementById('applicantsList');
            list.innerHTML = `
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #eee;">
                    <span style="font-size: 0.9rem; font-weight: 600; color: #666; align-self: center;">Sort by:</span>
                    <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;" onclick="sortApplicants('price_asc', ${bookingId})">Lowest Price</button>
                    <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;" onclick="sortApplicants('rating_desc', ${bookingId})">Highest Rating</button>
                </div>
                <div id="applicantsListInner"></div>
            `;
            renderApplicantsList(bookingId);
        }

        function sortApplicants(criteria, bookingId) {
            if (criteria === 'price_asc') {
                currentApplicants.sort((a, b) => {
                    const priceA = parseFloat(a.bid_price) || parseFloat(a.hourly_rate) || 0;
                    const priceB = parseFloat(b.bid_price) || parseFloat(b.hourly_rate) || 0;
                    return priceA - priceB;
                });
            } else if (criteria === 'rating_desc') {
                currentApplicants.sort((a, b) => parseFloat(b.average_rating) - parseFloat(a.average_rating));
            }
            renderApplicantsList(bookingId);
        }

        function renderApplicantsList(bookingId) {
            const listInner = document.getElementById('applicantsListInner');
            listInner.innerHTML = '';

            currentApplicants.forEach((app, index) => {
                const div = document.createElement('div');
                div.className = 'helper-card';
                div.innerHTML = `
                    <div style="display: flex; gap: 1rem; align-items: flex-start; width: 100%;">
                        <div style="width: 50px; height: 50px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                            ${app.profile_photo ? `<img src="${app.profile_photo}" style="width: 100%; height: 100%; object-fit: cover;">` : '<span class="material-icons" style="color: #aaa;">person</span>'}
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <h4 style="margin: 0;">${app.name}</h4>
                                <div style="text-align: right;">
                                    ${app.bid_price ? `<div style="color: #10B981; font-weight: 700; font-size: 1.1rem;">₹${app.bid_price}</div><small style="color:#666;">Bid Amount</small>` : (app.hourly_rate ? `<div style="color: #10B981; font-weight: 600;">₹${app.hourly_rate}/hr</div>` : '')}
                                </div>
                            </div>
                            
                            <p style="color: #666; font-size: 0.9rem; margin-top: 0.25rem;">
                                <span style="color: #F59E0B; font-weight: bold;">${app.average_rating || 0} ★</span> 
                                • ${app.completed_jobs_count || 0} Jobs Done
                            </p>
                            
                            <div style="background: #f9fafb; padding: 0.5rem; border-radius: 4px; margin-top: 0.5rem; font-size: 0.85rem;">
                                <p style="margin: 0; color: #374151;">
                                    <span class="material-icons" style="font-size: 14px; vertical-align: middle; color: #6B7280;">schedule</span> 
                                    <b>Arrival:</b> ${app.arrival_estimate || 'Not specified'}
                                </p>
                                ${app.notes ? `<p style="margin: 0.25rem 0 0 0; color: #4B5563;"><b>Note:</b> ${app.notes}</p>` : ''}
                            </div>

                            <a href="#" onclick="viewProfileByIndex(${index}); return false;" style="font-size: 0.8rem; color: var(--primary-color); font-weight: 600; display: inline-block; margin-top: 0.5rem;">View Full Profile</a>
                        </div>
                         
                        <div style="display: flex; gap: 0.5rem; flex-direction: column; width: 100px;">
                            <form action="api/booking_action.php" method="POST">
                                <input type="hidden" name="action" value="confirm_helper">
                                <input type="hidden" name="booking_id" value="${bookingId}">
                                <input type="hidden" name="helper_id" value="${app.helper_id}">
                                <button type="submit" class="btn btn-primary" style="white-space: nowrap; padding: 0.5rem; width: 100%; font-size: 0.85rem;">Accept</button>
                            </form>
                            <form action="api/booking_action.php" method="POST" onsubmit="return confirm('Are you sure you want to decline this helper?');">
                                <input type="hidden" name="action" value="reject_applicant">
                                <input type="hidden" name="booking_id" value="${bookingId}">
                                <input type="hidden" name="helper_id" value="${app.helper_id}">
                                <button type="submit" class="btn btn-outline" style="white-space: nowrap; padding: 0.5rem; width: 100%; color: #DC2626; border-color: #DC2626; font-size: 0.85rem;">Decline</button>
                            </form>
                        </div>
                    </div>
                `;
                listInner.appendChild(div);
            });

            applicantsModal.style.display = 'block';
        }

        function viewProfileByIndex(index) {
            if (currentApplicants[index]) {
                viewHelperProfile(currentApplicants[index]);
            }
        }

        const profileModal = document.getElementById('helperProfileModal');

        function viewHelperProfile(app) {
            const content = document.getElementById('helperProfileContent');
            content.innerHTML = `
                <div style="width: 100px; height: 100px; background: #eee; border-radius: 50%; margin: 0 auto 1rem; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        ${app.profile_photo ? `<img src="${app.profile_photo}" style="width: 100%; height: 100%; object-fit: cover;">` : '<span class="material-icons" style="font-size: 48px; color: #aaa;">person</span>'}
                </div>
                <h2 style="margin: 0 0 0.5rem;">${app.name}</h2>
                <div style="font-size: 0.9rem; color: #555; margin-bottom: 0.5rem;">${app.job_role || ''}</div>
                <div style="color: #666; font-size: 0.95rem; margin-bottom: 1rem;">
                    <span style="color: #F59E0B; font-weight: bold;">${app.average_rating || 0} ★</span> 
                    • ${app.completed_jobs_count || 0} Jobs Done
                    ${app.gender ? `• ${app.gender}` : ''}
                    <br>
                    ${app.hourly_rate ? `<span style="color: #10B981; font-weight: 600;">₹${app.hourly_rate}/hr</span>` : ''}
                </div>
                <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; text-align: left; margin-bottom: 1rem;">
                    <h4 style="margin: 0 0 0.5rem; color: #333;">About Helper</h4>
                    <p style="color: #555; font-size: 0.9rem; margin: 0; line-height: 1.5;">
                        ${app.bio || 'No bio provided.'}
                    </p>
                </div>
                <div style="text-align: left; margin-bottom: 1rem;">
                        <small style="display: block; color: #888;">Expected Arrival</small>
                        <strong>${app.arrival_estimate || 'Not specified'}</strong>
                </div>
            `;
            profileModal.style.display = 'block';
        }

        function closeProfileModal() {
            profileModal.style.display = 'none';
        }

        window.onclick = function (event) {
            if (event.target == bookingModal) closeBookingModal();
            if (event.target == applicantsModal) closeApplicantsModal();
            if (event.target == reviewModal) closeReviewModal();
            if (event.target == profileModal) closeProfileModal();
        }

        // Profile Map & Location
        let profileMap, profileMarker;
        function initProfileMap() {
            if (profileMap) return;
            document.getElementById('profileMap').style.display = 'block';
            profileMap = L.map('profileMap').setView([9.9312, 76.2673], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(profileMap);
            profileMarker = L.marker([9.9312, 76.2673], { draggable: true }).addTo(profileMap);

            profileMarker.on('dragend', function () {
                const latlng = profileMarker.getLatLng();
                reverseGeocodeToId(latlng.lat, latlng.lng, 'profileAddress');
            });
        }

        function getLocationForProfile() {
            initProfileMap();
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    profileMap.setView([lat, lng], 16);
                    profileMarker.setLatLng([lat, lng]);
                    reverseGeocodeToId(lat, lng, 'profileAddress');
                });
            }
        }

        function reverseGeocodeToId(lat, lng, elementId) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.display_name) {
                        const el = document.getElementById(elementId);
                        el.value = data.display_name;
                        validateBookingField(el); // Re-validate after auto-fill
                    }
                });
        }

        // Live Validation Logic
        function validateBookingField(input) {
            const errorEl = input.parentElement.querySelector('.error-message') ||
                input.parentElement.parentElement.querySelector('.error-message');

            let isValid = true;
            let message = "";

            if (input.required && !input.value.trim()) {
                isValid = false;
                message = "This field is required.";
            } else if (input.type === 'date') {
                const selectedDate = new Date(input.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (selectedDate < today) {
                    isValid = false;
                    message = "Date cannot be in the past.";
                }
            } else if (input.id === 'locationInput' && input.value.trim().length < 5) {
                isValid = false;
                message = "Please enter a more detailed address.";
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

        // Intercept form submission to validate everything
        document.querySelector('#bookingModal form').addEventListener('submit', function (e) {
            const inputs = this.querySelectorAll('input[required], textarea[required]');
            let allValid = true;
            inputs.forEach(input => {
                if (!validateBookingField(input)) allValid = false;
            });

            if (!allValid) {
                e.preventDefault();
                alert("Please correct the errors in the form before submitting.");
            }
        });
    </script>

</body>

</html>