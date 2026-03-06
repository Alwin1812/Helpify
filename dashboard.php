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



// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(); // In real app, mark as read on view or dismiss

// Fetch my reviews to know which bookings I reviewed
$stmt = $pdo->prepare("SELECT booking_id FROM reviews WHERE reviewer_id = ?");
$stmt->execute([$user_id]);
$reviewed_bookings = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch user details for tracking
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_details = $stmt->fetch();
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
    <link rel="stylesheet" href="assets/css/chat.css?v=<?php echo time(); ?>">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
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

        .pay-option.active {
            border-color: var(--primary-color) !important;
            background: #eff6ff !important;
            color: var(--primary-color) !important;
        }

        .payment-status-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 99px;
            font-weight: 600;
        }

        .ps-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .ps-pending {
            background: #fef3c7;
            color: #92400e;
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

        /* Booking Cards Redesign */
        .booking-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .bc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .bc-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0 0 0.25rem 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bc-meta {
            color: var(--text-light);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .bc-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .bc-helper-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bc-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 24px;
            overflow: hidden;
        }

        .bc-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .bc-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bc-actions {
            display: flex;
            gap: 0.5rem;
        }

        .chat-btn {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .chat-btn:hover {
            background: #e2e8f0;
            color: #0F172A;
        }
    </style>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>

<body>
    <header>
        <div
            style="width: 100%; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; height: 100%;">
            <a href="index.php" class="logo" style="text-decoration: none; display: flex; align-items: center;">
                <span
                    style="color: #111827; font-weight: 800; font-size: 1.4rem; letter-spacing: -0.5px;">HELPIFY</span>
            </a>
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
            <div class="nav-item " onclick="showSection('complaints')">
                <span class="material-icons">report_problem</span> Support & Complaints
            </div>
            <div class="nav-item cart-nav-item" onclick="openBookingModal()"
                style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 1rem; padding-top: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;"><span
                        class="material-icons">shopping_cart</span> Cart</div>
                <span id="cartCountBadge"
                    style="background: var(--warning); color: #000; padding: 2px 8px; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">0</span>
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

            <!-- Complaints Section -->
            <div id="complaints-section" class="tab-content" style="display: none;">
                <h2 style="margin-bottom: 2rem;">Support & Complaints</h2>
                <div class="grid" id="complaintList"
                    style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <!-- Loaded via JS -->
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
                                        onclick="openBookingModal('<?php echo $service['id']; ?>', '<?php echo htmlspecialchars($service['name'], ENT_QUOTES); ?>', <?php echo $service['base_price']; ?>)">Book</button>
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

            <div id="bookingModal" class="modal">
                <div class="modal-content" style="border-radius: 16px; padding: 2rem;">
                    <span class="close" onclick="closeBookingModal()"
                        style="font-size: 1.5rem; top: 1.5rem; right: 1.5rem;">&times;</span>
                    <h2 id="bookingModalTitle"
                        style="color: #0F172A; font-weight: 800; font-size: 1.6rem; margin-bottom: 1.5rem;">Book Service
                    </h2>

                    <div id="singleServiceInfo"
                        style="margin-bottom: 1.5rem; padding: 1.25rem; background: #F8FAFC; border-radius: 12px; display: none; border: 1px solid #E2E8F0;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 1.2rem; margin-bottom: 0.25rem;"
                            id="modalServiceName"></div>
                        <div style="font-weight: 600; color: #475569; font-size: 0.95rem;">Price: ₹<span
                                id="modalServicePrice"></span>
                        </div>
                    </div>

                    <div id="cartItemsContainer"
                        style="margin: 1rem 0; max-height: 200px; overflow-y: auto; border: 1px solid #E5E7EB; border-radius: 8px; padding: 1rem;">
                        <p style="text-align:center; color: var(--text-light); margin:0;">Your cart is empty.</p>
                    </div>
                    <div id="cartTotalSection"
                        style="text-align: right; font-weight: 700; font-size: 1.2rem; margin-bottom: 1rem;">Total:
                        ₹<span id="cartTotalDisplay">0</span></div>

                    <form action="api/booking_action.php" method="POST" id="checkoutForm"
                        onsubmit="return validateCheckout()">
                        <input type="hidden" name="action" value="book">
                        <input type="hidden" name="service_id" id="modalServiceId">
                        <div id="cartHiddenInputs"></div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="input-group">
                                <label
                                    style="font-size: 0.9rem; color: #64748B; font-weight: 600; margin-bottom: 0.5rem; display: block;">From
                                    Date</label>
                                <input type="date" name="date" id="bookingDate" class="form-control" required
                                    min="<?php echo date('Y-m-d'); ?>"
                                    oninput="validateBookingField(this); calculateDays()"
                                    style="padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #CBD5E1; width: 100%; box-sizing: border-box; color: #0F172A; font-family: inherit;">
                                <div class="error-message" id="dateError">Please select a valid future date.</div>
                            </div>
                            <div class="input-group">
                                <label
                                    style="font-size: 0.9rem; color: #64748B; font-weight: 600; margin-bottom: 0.5rem; display: block;">To
                                    Date</label>
                                <input type="date" name="end_date" id="bookingEndDate" class="form-control" required
                                    min="<?php echo date('Y-m-d'); ?>"
                                    oninput="validateBookingField(this); calculateDays()"
                                    style="padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #CBD5E1; width: 100%; box-sizing: border-box; color: #0F172A; font-family: inherit;">
                                <div class="error-message" id="endDateError">End date must be on or after start date.
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="num_days" id="numDaysInput" value="1">
                        <div
                            style="text-align: right; color: var(--text-light); font-size: 0.9rem; margin-top: -0.5rem; margin-bottom: 1rem;">
                            Total Days: <span id="displayNumDays"
                                style="font-weight: 700; color: var(--primary-color);">1</span>
                        </div>
                        <div class="input-group">
                            <label
                                style="font-size: 0.9rem; color: #64748B; font-weight: 600; margin-bottom: 0.5rem; display: block;">Time</label>
                            <div style="display: flex; gap: 0.75rem; align-items: center;">
                                <select id="hourSelect" class="form-control"
                                    style="margin-bottom:0; flex: 1; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #CBD5E1; color: #0F172A; background-color: #fff; appearance: auto;">
                                    <?php for ($i = 1; $i <= 12; $i++)
                                        echo "<option value='" . str_pad($i, 2, '0', STR_PAD_LEFT) . "'>$i</option>"; ?>
                                </select>
                                <span style="font-weight: bold; color: #475569;">:</span>
                                <select id="minuteSelect" class="form-control"
                                    style="margin-bottom:0; flex: 1; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #CBD5E1; color: #0F172A; background-color: #fff; appearance: auto;">
                                    <?php for ($i = 0; $i < 60; $i += 5)
                                        echo "<option value='" . str_pad($i, 2, '0', STR_PAD_LEFT) . "'>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>"; ?>
                                </select>
                                <select id="ampmSelect" class="form-control"
                                    style="margin-bottom:0; flex: 1; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #CBD5E1; color: #0F172A; background-color: #fff; appearance: auto;">
                                    <option value="AM" <?php echo date('H') < 12 ? 'selected' : ''; ?>>AM</option>
                                    <option value="PM" <?php echo date('H') >= 12 ? 'selected' : ''; ?>>PM</option>
                                </select>
                            </div>
                            <input type="hidden" name="time" id="bookingTime" required>
                            <div class="error-message" id="timeError">Please select a valid time.</div>
                        </div>
                        <div class="input-group">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <label
                                    style="margin: 0; font-size: 0.9rem; color: #64748B; font-weight: 600;">Location</label>
                                <button type="button" class="btn btn-outline"
                                    style="padding: 0.35rem 0.6rem; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; border-radius: 6px; border: 1px solid #0F172A; color: #0F172A; font-weight: 600;"
                                    onclick="getCurrentLocation(this)">
                                    <span class="material-icons" style="font-size: 16px;">my_location</span> Use Current
                                    Location
                                </button>
                            </div>
                            <div class="location-container">
                                <input type="text" name="location" id="locationInput" class="form-control"
                                    placeholder="Your Address" required oninput="validateBookingField(this)"
                                    style="padding: 0.85rem 1rem; border-radius: 8px; border: 1px solid #CBD5E1; width: 100%; box-sizing: border-box; color: #0F172A; margin-top: 0.25rem;">
                            </div>
                            <div class="error-message" id="locationError">Please provide a service location.</div>
                        </div>
                        <div class="input-group" style="margin-top: 1rem;">
                            <label
                                style="font-size: 0.9rem; color: #64748B; font-weight: 600; margin-bottom: 0.5rem; display: block;">Payment
                                Method</label>
                            <div style="display: flex; gap: 1rem;">
                                <div style="flex: 1; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.8rem; cursor: pointer; text-align: center; background: #F8FAFC; transition: all 0.2s;"
                                    onclick="selectPayment('Cash', this)" id="payCash" class="pay-option active">
                                    <span class="material-icons"
                                        style="font-size: 24px; vertical-align: middle; color: #334155; margin-bottom: 0.3rem; display: block;">payments</span>
                                    <div style="font-size: 0.85rem; font-weight: 500; color: #334155;">Cash After</div>
                                </div>
                                <div style="flex: 1; border: 1px solid #CBD5E1; border-radius: 8px; padding: 0.8rem; cursor: pointer; text-align: center; background: #F8FAFC; transition: all 0.2s;"
                                    onclick="selectPayment('Online', this)" id="payOnline" class="pay-option">
                                    <span class="material-icons"
                                        style="font-size: 24px; vertical-align: middle; color: #334155; margin-bottom: 0.3rem; display: block;">account_balance_wallet</span>
                                    <div style="font-size: 0.85rem; font-weight: 500; color: #334155;">Online Pay</div>
                                </div>
                            </div>
                            <input type="hidden" name="payment_method" id="paymentMethodInput" value="Cash">
                        </div>
                        <div class="input-group" style="margin-top: 1rem;">
                            <label
                                style="font-size: 0.9rem; color: #64748B; font-weight: 600; margin-bottom: 0.5rem; display: block;">Recurring
                                Booking (Helpify Plus)</label>
                            <select name="recurrence_type" class="form-control"
                                style="padding: 0.85rem 1rem; border-radius: 8px; border: 1px solid #CBD5E1; width: 100%; box-sizing: border-box; color: #0F172A; background-color: #fff; appearance: auto;">
                                <option value="none">One-time Service</option>
                                <option value="daily">Daily Plan</option>
                                <option value="weekly">Weekly Plan</option>
                                <option value="monthly">Monthly Plan</option>
                            </select>
                            <small style="color: #64748B; font-size: 0.75rem;">Get discounts and priority assignment
                                with
                                recurring plans.</small>
                        </div>

                        <div id="map"
                            style="border-radius: 12px; margin-top: 1rem; overflow: hidden; border: 1px solid #E2E8F0;">
                        </div>

                        <div class="input-group" style="margin-top: 1.5rem;">
                            <label
                                style="font-size: 0.9rem; color: #64748B; font-weight: 600; margin-bottom: 0.5rem; display: block;">Have
                                a Promo Code?</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="text" id="promoCodeInput" class="form-control"
                                    placeholder="Enter code (e.g., WELCOME50)"
                                    style="padding: 0.85rem 1rem; border-radius: 8px; border: 1px solid #CBD5E1; flex: 1; box-sizing: border-box; color: #0F172A; text-transform: uppercase;">
                                <button type="button" class="btn btn-outline" onclick="applyPromoCode()"
                                    style="padding: 0 1.5rem; border-radius: 8px; font-weight: 600; border-color: #0F172A; color: #0F172A;">Apply</button>
                            </div>
                            <div id="promoMessage" style="font-size: 0.85rem; margin-top: 0.5rem; display: none;"></div>
                            <input type="hidden" name="promo_code" id="appliedPromoCode" value="">
                        </div>

                        <div
                            style="background: #F8FAFC; padding: 1.5rem; border-radius: 12px; margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; border: 1px solid #E2E8F0;">
                            <span style="font-weight: 600; font-size: 1.1rem; color: #475569;">Total Amount to
                                Pay:</span>
                            <span style="font-weight: 800; font-size: 1.8rem; color: var(--primary-color);">₹<span
                                    id="grandTotalDisplay">0.00</span></span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 1rem; margin-top: 1.5rem;">
                            <button type="button" class="btn btn-outline" id="modalAddToCartBtn"
                                style="padding: 1rem; border-radius: 8px; font-weight: 600; font-size: 1rem; border-color: #0F172A; color: #0F172A;"
                                onclick="addCurrentToCart()">
                                <span class="material-icons"
                                    style="font-size: 20px; vertical-align: middle; margin-right: 4px;">add_shopping_cart</span>
                                Add to
                                Cart
                            </button>
                            <button type="submit" class="btn btn-primary" id="modalSubmitBtn"
                                style="padding: 1rem; border-radius: 8px; font-weight: 600; font-size: 1rem; background: #0F172A;">Confirm
                                Booking</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- History Section -->
            <div id="history-section" class="tab-content" style="display: none;">
                <h3 style="margin-bottom: 2rem;">My Bookings</h3>
                <div class="booking-list">
                    <?php if (count($bookings) > 0): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <div class="booking-card">
                                <div class="bc-header">
                                    <div>
                                        <h4 class="bc-title">
                                            <span class="material-icons"
                                                style="font-size: 18px; color: var(--primary-color);">build_circle</span>
                                            <?php echo htmlspecialchars($booking['service_name']); ?>
                                        </h4>
                                        <div class="bc-meta">
                                            <span class="material-icons" style="font-size: 14px;">event</span>
                                            <?php echo date('d M Y', strtotime($booking['date'])); ?>
                                            <?php echo $booking['time'] ? 'at ' . date('h:i A', strtotime($booking['time'])) : ''; ?>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>

                                <div class="bc-body">
                                    <div class="bc-helper-info">
                                        <?php if ($booking['helper_name']): ?>
                                            <div style="display: flex; gap: 12px; align-items: center; cursor: pointer;"
                                                onclick="fetchAndShowHelperProfile('<?php echo $booking['helper_id']; ?>')">
                                                <div class="bc-avatar">
                                                    <span class="material-icons">person</span>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; font-size: 0.95rem; color: var(--primary-color);">
                                                        <?php echo htmlspecialchars($booking['helper_name']); ?>
                                                    </div>
                                                    <div style="font-size: 0.8rem; color: var(--text-light);">Assigned Helper</div>
                                                </div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 0.8rem; color: #666; margin-bottom: 2px;">Payment:
                                                    <?php echo $booking['payment_method']; ?>
                                                </div>
                                                <?php if ($booking['payment_status'] == 'paid'): ?>
                                                    <span class="payment-status-badge ps-paid">Paid</span>
                                                <?php else: ?>
                                                    <span class="payment-status-badge ps-pending">Pending</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="bc-avatar" style="background: #fdf2f8; color: #db2777;">
                                                <span class="material-icons">hourglass_empty</span>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-light);">Finding
                                                    a pro...</div>
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <div style="font-size: 0.8rem; color: var(--text-light);">We will notify you soon.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div
                                        style="text-align: right; font-size: 0.85rem; color: var(--text-light); max-width: 200px;">
                                        <span class="material-icons"
                                            style="font-size: 14px; vertical-align: middle;">location_on</span>
                                        <?php echo htmlspecialchars($booking['location'] ?? 'No location provided'); ?>
                                    </div>
                                </div>

                                <div class="bc-footer">
                                    <div>
                                        <?php if ($booking['status'] == 'confirmed'): ?>
                                            <div
                                                style="background: #EFF6FF; border: 1px dashed #2563EB; padding: 10px; border-radius: 8px; display: inline-block; margin-top: 5px;">
                                                <span
                                                    style="font-size: 0.8rem; color: #1E40AF; display: block; font-weight: 700;">JOB
                                                    START OTP</span>
                                                <span
                                                    style="font-size: 1.2rem; color: #2563EB; font-weight: 900; letter-spacing: 2px;"><?php echo $booking['start_otp']; ?></span>
                                            </div>
                                        <?php elseif ($booking['status'] == 'in-progress'): ?>
                                            <div
                                                style="background: #F0FDF4; border: 1px dashed #10B981; padding: 10px; border-radius: 8px; display: inline-block; margin-top: 5px;">
                                                <span
                                                    style="font-size: 0.8rem; color: #065F46; display: block; font-weight: 700;">COMPLETION
                                                    OTP</span>
                                                <span
                                                    style="font-size: 1.2rem; color: #10B981; font-weight: 900; letter-spacing: 2px;"><?php echo $booking['end_otp']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($booking['status'] == 'confirmed' || $booking['status'] == 'in-progress'): ?>
                                            <div style="margin-top: 8px;">
                                                <span style="font-size: 0.85rem; color: var(--primary-color); font-weight: 600;">
                                                    <span class="material-icons"
                                                        style="font-size: 14px; vertical-align: text-bottom;">chat</span> Need to
                                                    message? <a href="#"
                                                        onclick="fetchAndShowHelperProfile('<?php echo $booking['helper_id']; ?>'); return false;"
                                                        style="color: var(--primary-color); text-decoration: underline; cursor: pointer;">View
                                                        details.</a>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bc-actions">
                                        <?php if ($booking['helper_id'] && in_array($booking['status'], ['accepted', 'confirmed', 'in-progress'])): ?>
                                            <button class="chat-btn"
                                                onclick="openChat('<?php echo $booking['id']; ?>', '<?php echo $booking['helper_id']; ?>', '<?php echo htmlspecialchars($booking['helper_name']); ?>')">
                                                <span class="material-icons" style="font-size: 18px;">chat</span>
                                                Chat
                                            </button>
                                        <?php endif; ?>
                                        <?php if (in_array($booking['status'], ['in-progress'])): ?>
                                            <button class="chat-btn"
                                                style="background: #eff6ff; color: #2563eb; border-color: #bfdbfe;"
                                                onclick="startTracking('<?php echo $booking['helper_id']; ?>', '<?php echo htmlspecialchars($booking['helper_name'] ?? 'Helper'); ?>', '<?php echo $user_details['last_lat'] ?? 20.5937; ?>', '<?php echo $user_details['last_lng'] ?? 78.9629; ?>')">
                                                <span class="material-icons" style="font-size: 18px;">my_location</span>
                                                Track
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($booking['payment_method'] == 'Online' && $booking['payment_status'] == 'pending' && $booking['status'] != 'cancelled'): ?>
                                                                    <button onclick="payOnline(<?php echo $booking['id']; ?>)" class="btn btn-primary"
                                                                        style="background: #10B981; border-color: #10B981; display: flex; align-items: center; gap: 4px;">
                                                                        <span class="material-icons" style="font-size: 18px;">payment</span> Pay Now
                                                                    </button>
                                                        <?php endif; ?>
                                                        <?php if ($booking['payment_status'] == 'paid'): ?>
                                                                    <a href="receipt.php?id=<?php echo $booking['id']; ?>" target="_blank" class="chat-btn"
                                                                        style="background: #eef2ff; color: #4338ca; border-color: #c7d2fe; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                                                        <span class="material-icons" style="font-size: 18px;">receipt_long</span> Receipt
                                                                    </a>
                                                        <?php endif; ?>
                                                        <?php if (!in_array($booking['status'], ['cancelled'])): ?>
                                                                    <button class="chat-btn" style="color: #ef4444; border-color: #fecaca;"
                                                                        onclick="openComplaintModal('<?php echo $booking['id']; ?>', '<?php echo htmlspecialchars($booking['service_name']); ?>')">
                                                                        <span class="material-icons" style="font-size: 18px;">report_problem</span>
                                                                        Report
                                                                    </button>
                                                        <?php endif; ?>
                                                        <?php if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed'): ?>
                                                                    <form action="api/booking_action.php" method="POST" style="margin: 0;"
                                                                        onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                                        <input type="hidden" name="action" value="cancel">
                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <button type="submit" class="btn btn-outline"
                                                                            style="color: #DC2626; border-color: #DC2626;">Cancel Booking</button>
                                                                    </form>
                                                        <?php elseif ($booking['status'] == 'completed'): ?>
                                                                    <?php if (in_array($booking['id'], $reviewed_bookings)): ?>
                                                                                <button class="btn btn-outline" disabled style="background: #f3f4f6;">Reviewed</button>
                                                                    <?php else: ?>
                                                                                <button class="btn btn-primary"
                                                                                    onclick="openReviewModal(<?php echo $booking['id']; ?>)">Leave a Review</button>
                                                                    <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                <?php endforeach; ?>
                    <?php else: ?>
                                <div
                                    style="text-align: center; padding: 3rem; background: white; border-radius: 12px; border: 1px dashed var(--border-color);">
                                    <span class="material-icons"
                                        style="font-size: 48px; color: #d1d5db; margin-bottom: 1rem;">event_busy</span>
                                    <h4 style="color: var(--text-color); margin-bottom: 0.5rem;">No bookings yet</h4>
                                    <p style="color: var(--text-light); font-size: 0.9rem;">You haven't requested any services yet.
                                        Head over to the Book tab!</p>
                                </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Complaints Section -->
            <div id="complaints-section" class="tab-content" style="display: none;">
                <h3 style="margin-bottom: 2rem;">My Complaints & Support</h3>
                <div id="complaintList" class="booking-list">
                    <!-- Complaints will be loaded here by JS -->
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
                                <input type="tel" id="profile_phone" name="phone_number"
                                    value="<?php echo htmlspecialchars($user_details['phone_number'] ?? ''); ?>"
                                    pattern="[0-9]{10}" maxlength="10"
                                    style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"
                                    placeholder="10-digit number">
                                <small id="profile_phone_error"
                                    style="color: #EF4444; font-size: 0.75rem; display: none; margin-top: 0.25rem;">Please
                                    enter a valid 10-digit phone number.</small>
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

            // Find the nav item by text/icon if index isn't reliable
            document.querySelectorAll('.nav-item').forEach(item => {
                if (item.textContent.toLowerCase().includes(sectionId.toLowerCase())) {
                    item.classList.add('active');
                }
            });

            if (sectionId === 'complaints') {
                loadComplaints();
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
                                    <button class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.85rem;" onclick="openBookingModal('${s.id}', '${s.name.replace(/'/g, "\\'")}', ${s.base_price})">Book</button>
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
        let currentModalBasePrice = 0;
        let cartBaseTotal = 0;
        let activePromo = null;

        async function applyPromoCode() {
            const codeInput = document.getElementById('promoCodeInput').value.trim();
            const msgEl = document.getElementById('promoMessage');

            if (!codeInput) {
                activePromo = null;
                msgEl.style.display = 'block';
                msgEl.style.color = '#EF4444';
                msgEl.innerText = "Please enter a code first.";
                document.getElementById('appliedPromoCode').value = '';
                updateBookingTotal();
                return;
            }

            try {
                const response = await fetch('api/validate_promo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code: codeInput })
                });
                const res = await response.json();

                if (res.success) {
                    activePromo = res;
                    document.getElementById('appliedPromoCode').value = codeInput;
                    msgEl.style.display = 'block';
                    msgEl.style.color = '#10B981';
                    msgEl.innerText = "Code applied successfully!";
                    updateBookingTotal();
                } else {
                    activePromo = null;
                    document.getElementById('appliedPromoCode').value = '';
                    msgEl.style.display = 'block';
                    msgEl.style.color = '#EF4444';
                    msgEl.innerText = res.message;
                    updateBookingTotal();
                }
            } catch (e) {
                console.error(e);
            }
        }

        function updateBookingTotal() {
            let days = parseInt(document.getElementById('numDaysInput').value) || 1;
            if (days < 1) days = 1;

            let finalTotal = 0;
            if (document.getElementById('modalServiceId').value) {
                finalTotal = currentModalBasePrice * days;
                document.getElementById('modalServicePrice').innerText = finalTotal.toFixed(2);
            } else {
                finalTotal = cartBaseTotal * days;
                document.getElementById('cartTotalDisplay').innerText = finalTotal.toFixed(2);
            }

            // Apply Promo if present
            if (activePromo && finalTotal >= activePromo.min_order_amount) {
                let discount = 0;
                if (activePromo.discount_type === 'percentage') {
                    discount = (finalTotal * activePromo.discount_value) / 100;
                    if (activePromo.max_discount_amount) {
                        discount = Math.min(discount, activePromo.max_discount_amount);
                    }
                } else {
                    discount = activePromo.discount_value;
                }

                finalTotal -= discount;
                if (finalTotal < 0) finalTotal = 0;
            }

            const grandTotalEl = document.getElementById('grandTotalDisplay');
            if (grandTotalEl) grandTotalEl.innerText = finalTotal.toFixed(2);
        }

        function calculateDays() {
            const start = document.getElementById('bookingDate').value;
            const end = document.getElementById('bookingEndDate').value;
            const endDateInput = document.getElementById('bookingEndDate');
            const errorMsg = document.getElementById('endDateError');

            let days = 1;
            if (start && end) {
                const sDate = new Date(start);
                const eDate = new Date(end);

                if (eDate < sDate) {
                    endDateInput.classList.add('invalid');
                    if (errorMsg) {
                        errorMsg.style.display = 'block';
                        errorMsg.innerText = "End date must be on or after start date.";
                    }
                    days = 1;
                } else {
                    endDateInput.classList.remove('invalid');
                    if (errorMsg) errorMsg.style.display = 'none';

                    const timeDiff = eDate.getTime() - sDate.getTime();
                    days = Math.floor(timeDiff / (1000 * 3600 * 24)) + 1;
                }
            } else if (start && !end) {
                endDateInput.min = start;
            }

            document.getElementById('numDaysInput').value = days;
            const displayDays = document.getElementById('displayNumDays');
            if (displayDays) displayDays.innerText = days;

            updateBookingTotal();
        }

        function openBookingModal(id = null, name = null, price = null) {
            const singleInfo = document.getElementById('singleServiceInfo');
            const cartItems = document.getElementById('cartItemsContainer');
            const cartTotal = document.getElementById('cartTotalSection');
            const addToCartBtn = document.getElementById('modalAddToCartBtn');
            const title = document.getElementById('bookingModalTitle');
            const submitBtn = document.getElementById('modalSubmitBtn');

            document.getElementById('bookingDate').value = '';
            document.getElementById('bookingEndDate').value = '';
            calculateDays();

            if (id) {
                // Single Service Mode
                title.innerText = "Book Service";
                singleInfo.style.display = 'block';
                cartItems.style.display = 'none';
                cartTotal.style.display = 'none';
                addToCartBtn.style.display = 'flex';
                submitBtn.innerText = "Confirm Booking";

                document.getElementById('modalServiceName').innerText = name;
                currentModalBasePrice = parseFloat(price) || 0;
                document.getElementById('modalServicePrice').innerText = currentModalBasePrice.toFixed(2);
                document.getElementById('modalServiceId').value = id;
                const daysInput = document.getElementById('numDaysInput');
                if (daysInput) daysInput.value = 1;
                updateBookingTotal();
            } else {
                // Cart Checkout Mode
                title.innerText = "Cart Checkout";
                singleInfo.style.display = 'none';
                cartItems.style.display = 'block';
                cartTotal.style.display = 'block';
                addToCartBtn.style.display = 'none';
                submitBtn.innerText = "Checkout All Items";
                document.getElementById('modalServiceId').value = '';
                const daysInput = document.getElementById('numDaysInput');
                if (daysInput) daysInput.value = 1;
                updateCartUI();
            }

            bookingModal.style.display = 'block';
            initMap();
        }

        function addCurrentToCart() {
            const id = document.getElementById('modalServiceId').value;
            const name = document.getElementById('modalServiceName').innerText;
            const price = currentModalBasePrice;

            if (id) {
                cart.push({ id: id, name: name, price: parseFloat(price) });
                updateCartUI();
                alert(`${name} added to cart!`);
                closeBookingModal();
            }
        }

        function addToCart(serviceId, serviceName, basePrice) {
            cart.push({ id: serviceId, name: serviceName, price: parseFloat(basePrice) });
            updateCartUI();
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
            cartBaseTotal = total;
            updateBookingTotal();
        }

        function selectPayment(method, element) {
            document.getElementById('paymentMethodInput').value = method;
            document.querySelectorAll('.pay-option').forEach(opt => opt.classList.remove('active'));
            element.classList.add('active');
        }

        function closeBookingModal() {
            bookingModal.style.display = 'none';
        }

        function validateCheckout() {
            const serviceId = document.getElementById('modalServiceId').value;
            if (!serviceId && cart.length === 0) {
                alert('Please select a service or check your cart.');
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

        function getCurrentLocation(btnElement) {
            if (navigator.geolocation) {
                // If btnElement is provided, use it, else fallback to finding a generic button (for backwards compatibility)
                const btn = btnElement || document.querySelector('.btn-outline[onclick*="getCurrentLocation"]');
                let originalIcon = '<span class="material-icons" style="font-size: 14px;">my_location</span> Use Current Location';
                if (btn) {
                    originalIcon = btn.innerHTML;
                    btn.innerHTML = '<span class="material-icons" style="animation: spin 1s linear infinite; font-size: 14px;">sync</span> Loading...';
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const latlng = [lat, lng];

                        map.setView(latlng, 16);
                        marker.setLatLng(latlng);
                        reverseGeocode(lat, lng);
                        if (btn) btn.innerHTML = originalIcon;
                    },
                    (error) => {
                        alert("Geolocation failed: " + error.message);
                        if (btn) btn.innerHTML = originalIcon;
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

        function fetchAndShowHelperProfile(helperId) {
            fetch(`api/get_helper_details.php?helper_id=${helperId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        viewHelperProfile(data.helper);
                    } else {
                        alert(data.error || 'Failed to fetch helper details');
                    }
                })
                .catch(e => console.error('Error:', e));
        }

        function viewHelperProfile(app) {
            const content = document.getElementById('helperProfileContent');
            content.innerHTML = `
                <div style="width: 100px; height: 100px; background: #eee; border-radius: 50%; margin: 0 auto 1rem; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        ${app.profile_photo ? `<img src="${app.profile_photo}" style="width: 100%; height: 100%; object-fit: cover;">` : '<span class="material-icons" style="font-size: 48px; color: #aaa;">person</span>'}
                </div>
                <h2 style="margin: 0 0 0.5rem;">${app.name}</h2>
                <div style="font-size: 0.9rem; color: #555; margin-bottom: 0.5rem;">${app.job_role || 'Helper'}</div>
                <div style="color: #666; font-size: 0.95rem; margin-bottom: 1rem;">
                    <span style="color: #F59E0B; font-weight: bold;">${parseFloat(app.average_rating || 0).toFixed(1)} ★</span> 
                    • ${app.completed_jobs_count || 0} Jobs Done
                </div>
                
                <div style="display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 1.5rem;">
                    <a href="tel:${app.phone_number || ''}" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                        <span class="material-icons" style="font-size: 18px;">phone</span> Call
                    </a>
                    <a href="https://wa.me/91${app.phone_number || ''}" target="_blank" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-color: #25D366; color: #25D366; display: flex; align-items: center; gap: 4px;">
                        <span class="material-icons" style="font-size: 18px;">chat</span> WhatsApp
                    </a>
                </div>

                <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; text-align: left; margin-bottom: 1rem; border: 1px solid #f3f4f6;">
                    <h4 style="margin: 0 0 0.5rem; color: #374151; font-size: 0.9rem;">About Helper</h4>
                    <p style="color: #6B7280; font-size: 0.85rem; margin: 0; line-height: 1.5;">
                        ${app.bio || 'Professional helper dedicated to providing high-quality service.'}
                    </p>
                    ${app.address ? `
                    <h4 style="margin: 0.75rem 0 0.25rem; color: #374151; font-size: 0.9rem;">Home Location</h4>
                    <p style="color: #6B7280; font-size: 0.85rem; margin: 0;">
                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">location_on</span>
                        ${app.address}
                    </p>
                    ` : ''}
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
            if (event.target == complaintModal) closeComplaintModal();
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

        // 12-Hour Clock Sync
        function syncTime() {
            let h = parseInt(document.getElementById('hourSelect').value);
            const m = document.getElementById('minuteSelect').value;
            const p = document.getElementById('ampmSelect').value;

            let hours24 = h;
            if (p === 'PM' && h < 12) hours24 += 12;
            if (p === 'AM' && h === 12) hours24 = 0;

            const timeValue = String(hours24).padStart(2, '0') + ":" + m;
            const timeInput = document.getElementById('bookingTime');
            timeInput.value = timeValue;
            validateBookingField(timeInput);
        }

        // Add listeners for the 12-hour picker
        document.addEventListener('DOMContentLoaded', () => {
            const hSel = document.getElementById('hourSelect');
            const mSel = document.getElementById('minuteSelect');
            const pSel = document.getElementById('ampmSelect');

            if (hSel && mSel && pSel) {
                hSel.addEventListener('change', syncTime);
                mSel.addEventListener('change', syncTime);
                pSel.addEventListener('change', syncTime);

                // Initial sync
                syncTime();
            }
        });

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

        // Profile Phone Validation
        const profilePhoneInput = document.getElementById('profile_phone');
        const profilePhoneError = document.getElementById('profile_phone_error');

        if (profilePhoneInput) {
            profilePhoneInput.addEventListener('input', function () {
                // Keep only numeric characters
                this.value = this.value.replace(/\D/g, '');

                if (this.value === '') {
                    this.style.borderColor = '#ddd';
                    profilePhoneError.style.display = 'none';
                } else {
                    const isValidPrefix = /^[6-9]/.test(this.value);
                    const isCorrectLength = this.value.length === 10;

                    if (isValidPrefix && isCorrectLength) {
                        this.style.borderColor = '#10B981'; // Green
                        profilePhoneError.style.display = 'none';
                    } else {
                        this.style.borderColor = '#EF4444'; // Red
                        profilePhoneError.style.display = 'block';
                        if (!isValidPrefix) {
                            profilePhoneError.textContent = "Number must start with 6, 7, 8, or 9.";
                        } else {
                            profilePhoneError.textContent = "Please enter a valid 10-digit phone number.";
                        }
                    }
                }
            });

            // Re-validate on blur for better UX
            profilePhoneInput.addEventListener('blur', function () {
                const isValidPrefix = /^[6-9]/.test(this.value);
                const isCorrectLength = this.value.length === 10;
                if (this.value.length > 0 && (!isValidPrefix || !isCorrectLength)) {
                    this.style.borderColor = '#EF4444';
                    profilePhoneError.style.display = 'block';
                }
            });
        }

        // Intercept profile form submission to validate everything
        const profileForm = document.querySelector('#profile-section form');
        if (profileForm) {
            profileForm.addEventListener('submit', function (e) {
                const isValidPrefix = /^[6-9]/.test(profilePhoneInput.value);
                const isCorrectLength = profilePhoneInput.value.length === 10;

                if (profilePhoneInput && (!isValidPrefix || !isCorrectLength)) {
                    e.preventDefault();
                    profilePhoneInput.style.borderColor = '#EF4444';
                    profilePhoneError.style.display = 'block';
                    alert("Please enter a valid 10-digit phone number starting with 6-9.");
                }
            });
        }

        // Intercept booking form submission to validate everything
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

        // Razorpay Payment Logic
        function payOnline(bookingId) {
            fetch('api/create_razorpay_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'booking_id=' + bookingId
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert("Error: " + data.error);
                        return;
                    }

                    var options = {
                        "key": data.key,
                        "amount": data.amount,
                        "currency": "INR",
                        "name": "Helpify Services",
                        "description": "Payment for Booking #" + bookingId,
                        "order_id": data.order_id,
                        "handler": function (response) {
                            // Validate hash/signature on backend
                            fetch('api/verify_payment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_signature: response.razorpay_signature,
                                    booking_id: bookingId
                                })
                            })
                                .then(r => r.json())
                                .then(resData => {
                                    if (resData.success) {
                                        alert("Payment successful! Thank you.");
                                        location.reload();
                                    } else {
                                        alert("Payment verification failed! " + resData.error);
                                    }
                                });
                        },
                        "prefill": {
                            "name": "<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>",
                            "email": "<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>",
                            "contact": "<?php echo htmlspecialchars($user_details['phone_number'] ?? ''); ?>"
                        },
                        "theme": {
                            "color": "#3B82F6"
                        }
                    };
                    var rzp1 = new Razorpay(options);
                    rzp1.on('payment.failed', function (response) {
                        alert("Payment Failed: " + response.error.description);
                    });
                    rzp1.open();
                })
                .catch(err => {
                    alert("Network error. Please try again later.");
                    console.error(err);
                });
        }
    </script>

    <!-- Chat Modal Overlay -->
    <div id="chatOverlay" class="chat-modal-overlay">
        <div class="chat-header">
            <h4 id="chatHeaderTitle"><span class="material-icons">chat</span> Chat with <span
                    id="chatReceiverName">Helper</span></h4>
            <span class="material-icons close-chat" onclick="closeChat()">close</span>
        </div>
        <div id="chatMessages" class="chat-messages">
            <!-- Messages will be loaded here -->
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Type a message...">
            <button class="chat-send-btn" onclick="sendMessage()">
                <span class="material-icons">send</span>
            </button>
        </div>
    </div>

    <script>
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="assets/js/chat.js?v=<?php echo time(); ?>"></script>

    <!-- Complaint Modal -->
    <div id="complaintModal" class="modal">
        <div class="modal-content" style="max-width: 500px; border-radius: 16px;">
            <span class="close" onclick="closeComplaintModal()">&times;</span>
            <h2 style="margin-bottom: 1rem;"><span class="material-icons"
                    style="vertical-align: middle; color: #ef4444;">report_problem</span> Report an Issue</h2>
            <p id="complaintBookingInfo" style="margin-bottom: 1.5rem; color: #64748B;"></p>
            <form id="complaintForm">
                <input type="hidden" id="complaintBookingId" name="booking_id">
                <div class="input-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Explain the problem</label>
                    <textarea id="complaintDescription" name="description" class="form-control" rows="4"
                        placeholder="Describe what went wrong in detail..." required style="resize: none;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"
                    style="width: 100%; padding: 1rem; background: #ef4444; border: none;">Submit Report</button>
            </form>
        </div>
    </div>

    <!-- Complaint Chat Overlay (Floating) -->
    <div id="complaintChatOverlay" class="chat-modal-overlay">
        <div class="chat-header" style="background: #ef4444;">
            <h4><span class="material-icons">support_agent</span> Admin Support</h4>
            <span class="material-icons close-chat" onclick="closeComplaintChat()">close</span>
        </div>
        <div id="complaintMessages" class="chat-messages"></div>
        <div class="chat-input-area">
            <input type="text" id="complaintChatInput" placeholder="Type a message...">
            <button class="chat-send-btn" style="background: #ef4444;" onclick="sendComplaintMessage()">
                <span class="material-icons">send</span>
            </button>
        </div>
    </div>

    <!-- Tracking Modal -->
    <div id="trackingModal" class="modal">
        <div class="modal-content" style="max-width: 800px; border-radius: 16px;">
            <span class="close" onclick="closeTrackingModal()">&times;</span>
            <h2 style="margin-bottom: 1rem;">Track Helper - <span id="trackHelperName"></span></h2>
            <div id="trackingMap" style="height: 400px; width: 100%; border-radius: 12px; border: 1px solid #e2e8f0;">
            </div>
            <p
                style="margin-top: 1rem; color: #64748B; font-size: 0.9rem; display: flex; align-items: center; gap: 4px;">
                <span class="material-icons" style="font-size: 16px;">update</span>
                Last updated: <span id="trackLastUpdated">Just now</span>
            </p>
        </div>
    </div>

    <script>
        // Tracking Logic
        let trackingMap;
        let helperMarker;
        let userMarker;
        let trackingInterval;

        function startTracking(helperId, helperName, userLat, userLng) {
            document.getElementById('trackHelperName').textContent = helperName;
            document.getElementById('trackingModal').style.display = 'block';

            setTimeout(() => {
                if (!trackingMap) {
                    trackingMap = L.map('trackingMap').setView([userLat, userLng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(trackingMap);
                    userMarker = L.marker([userLat, userLng]).addTo(trackingMap).bindPopup("Service Location").openPopup();
                } else {
                    trackingMap.setView([userLat, userLng], 13);
                    userMarker.setLatLng([userLat, userLng]);
                    trackingMap.invalidateSize();
                }

                updateHelperPos(helperId);
                if (trackingInterval) clearInterval(trackingInterval);
                trackingInterval = setInterval(() => updateHelperPos(helperId), 15000);
            }, 200);
        }

        function updateHelperPos(helperId) {
            fetch(`api/get_helper_location.php?helper_id=${helperId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const {
                            last_lat,
                            last_lng
                        } = data.location;
                        if (!helperMarker) {
                            helperMarker = L.circleMarker([last_lat, last_lng], {
                                radius: 10,
                                fillColor: "#2563EB",
                                color: "#fff",
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.8
                            }).addTo(trackingMap).bindPopup("Helper is here");
                        } else {
                            helperMarker.setLatLng([last_lat, last_lng]);
                        }
                        document.getElementById('trackLastUpdated').textContent = new Date().toLocaleTimeString();
                    }
                });
        }

        function closeTrackingModal() {
            document.getElementById('trackingModal').style.display = 'none';
            if (trackingInterval) clearInterval(trackingInterval);
        }

        // Complaint Logic
        function openComplaintModal(bookingId, serviceName) {
            document.getElementById('complaintBookingId').value = bookingId;
            document.getElementById('complaintBookingInfo').textContent = "Reporting issue for: " + serviceName + " (#" + bookingId + ")";
            document.getElementById('complaintModal').style.display = 'block';
        }

        function closeComplaintModal() {
            document.getElementById('complaintModal').style.display = 'none';
        }

        document.getElementById('complaintForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'submit');

            fetch('api/complaint_action.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        closeComplaintModal();
                        loadComplaints();
                    }
                });
        });

        function loadComplaints() {
            fetch('api/complaint_action.php?action=fetch')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('complaintList');
                        if (!container) return;
                        container.innerHTML = '';
                        if (data.complaints.length === 0) {
                            container.innerHTML = '<div style="text-align: center; padding: 3rem; color: #94a3b8; width: 100%;"><span class="material-icons" style="font-size: 48px;">check_circle</span><p>No active complaints.</p></div>';
                            return;
                        }

                        data.complaints.forEach(c => {
                            const card = document.createElement('div');
                            card.className = 'stat-card';
                            card.style.cursor = 'pointer';
                            card.style.borderLeft = '4px solid ' + (c.status === 'resolved' ? '#10B981' : (c.status === 'pending' ? '#F59E0B' : '#94a3b8'));
                            card.onclick = () => openComplaintChat(c.id);

                            card.innerHTML = `
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h4 style="margin: 0; color: #1e293b;">${c.service_name || 'General Support'}</h4>
                                    <p style="font-size: 0.9rem; color: #64748B; margin: 0.5rem 0;">${c.description.substring(0, 50)}${c.description.length > 50 ? '...' : ''}</p>
                                    <small style="color: #94a3b8;">${new Date(c.created_at).toLocaleDateString()}</small>
                                </div>
                                <span class="status-badge" style="font-size: 0.7rem; background: #f1f5f9; color: #475569;">${c.status.toUpperCase()}</span>
                            </div>
                        `;
                            container.appendChild(card);
                        });
                    }
                });
        }

        let currentComplaintId = null;
        let complaintPolling = null;

        function openComplaintChat(complaintId) {
            currentComplaintId = complaintId;
            document.getElementById('complaintChatOverlay').style.display = 'flex';
            fetchComplaintMessages();
            if (complaintPolling) clearInterval(complaintPolling);
            complaintPolling = setInterval(fetchComplaintMessages, 3000);
        }

        function closeComplaintChat() {
            document.getElementById('complaintChatOverlay').style.display = 'none';
            if (complaintPolling) clearInterval(complaintPolling);
        }

        function fetchComplaintMessages() {
            if (!currentComplaintId) return;
            fetch(`api/complaint_action.php?action=fetch_messages&complaint_id=${currentComplaintId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('complaintMessages');
                        container.innerHTML = '';
                        data.messages.forEach(msg => {
                            const div = document.createElement('div');
                            const isSent = msg.sender_id == currentUserId;
                            div.className = `message-bubble ${isSent ? 'sent' : 'received'}`;
                            if (!isSent) div.style.background = '#f1f5f9';

                            div.innerHTML = `
                            <div style="font-size: 0.7rem; font-weight: 700; margin-bottom: 2px; color: ${isSent ? '#fff' : '#475569'}">${msg.sender_role === 'admin' ? 'Support' : msg.sender_name}</div>
                            ${msg.message}
                            <span class="message-time" style="color: ${isSent ? 'rgba(255,255,255,0.7)' : '#94a3b8'}">${new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                        `;
                            container.appendChild(div);
                        });
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }

        function sendComplaintMessage() {
            const input = document.getElementById('complaintChatInput');
            const message = input.value.trim();
            if (!message || !currentComplaintId) return;

            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('complaint_id', currentComplaintId);
            fd.append('message', message);

            input.value = '';
            fetch('api/complaint_action.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) fetchComplaintMessages();
                });
        }

        document.getElementById('complaintChatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendComplaintMessage();
        });
    </script>
</body>

</html>