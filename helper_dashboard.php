<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'helper') {
    header('Location: login.php');
    exit;
}

$helper_id = $_SESSION['user_id'];

// Fetch Helper's Job Role, Email, and Gender
$stmt = $pdo->prepare("SELECT job_role, email, gender, average_rating, bio, phone_number, address, profile_photo, hourly_rate, name, password_changed FROM users WHERE id = ?");
$stmt->execute([$helper_id]);
$helper = $stmt->fetch();
$helper_role = $helper['job_role'];

// Fetch Services for Job Roles
$stmt = $pdo->query("SELECT * FROM services ORDER BY name ASC");
$all_services = $stmt->fetchAll();

// Fetch my application details for various bookings
$stmt = $pdo->prepare("SELECT booking_id, status, bid_price, arrival_estimate, notes FROM booking_requests WHERE helper_id = ?");
$stmt->execute([$helper_id]);
$my_applications = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $my_applications[$row['booking_id']] = $row;
}

// Available jobs (pending or accepted-but-open, matching role)
$stmt = $pdo->prepare("
    SELECT b.*, s.name as service_name, u.name as user_name, u.profile_photo as user_photo
    FROM bookings b 
    JOIN services s ON b.service_id = s.id 
    JOIN users u ON b.user_id = u.id 
    WHERE (b.status = 'pending' OR (b.status = 'accepted' AND b.helper_id IS NULL))
    AND s.name = ? 
    ORDER BY b.created_at ASC
");
$stmt->execute([$helper_role]);
$all_requests = $stmt->fetchAll();

// Filter requests
$new_requests = [];
foreach ($all_requests as $req) {
    $my_app = $my_applications[$req['id']] ?? null;
    $status = $my_app['status'] ?? null;
    if ($status === 'rejected')
        continue;
    $new_requests[] = $req;
}

// My Jobs (Assigned to me)
$stmt = $pdo->prepare("
    SELECT b.*, s.name as service_name, u.name as user_name 
    FROM bookings b 
    JOIN services s ON b.service_id = s.id 
    JOIN users u ON b.user_id = u.id 
    WHERE b.helper_id = ? 
    ORDER BY b.date DESC
");
$stmt->execute([$helper_id]);
$my_jobs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helper Dashboard - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/chat.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .payment-status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .ps-paid {
            background: #ECFDF5;
            color: #10B981;
        }

        .ps-pending {
            background: #FFF7ED;
            color: #F97316;
        }
    </style>
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%; width: 100%;">
            <a href="index.php" class="logo" style="text-decoration: none; display: flex; align-items: center;">
                <span
                    style="color: #111827; font-weight: 800; font-size: 1.4rem; letter-spacing: -0.5px;">HELPIFY</span>
            </a>
            <nav class="nav-links" style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-size: 0.9rem; color: var(--text-light);">
                    Helper • <b
                        style="color: var(--text-color);"><?php echo htmlspecialchars($_SESSION['user_name']); ?></b>
                </span>
                <span class="status-badge status-completed" style="font-size: 0.75rem;">AVAILABLE</span>
                <a href="api/logout.php" class="btn btn-primary" style="margin-left: 1rem;">Logout</a>
            </nav>
        </div>
    </header>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="container" style="margin-top: 1rem;">
            <div style="background: #ECFDF5; color: #065F46; padding: 1rem; border-radius: 8px; border: 1px solid #A7F3D0;">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="container" style="margin-top: 1rem;">
            <div style="background: #FEF2F2; color: #991B1B; padding: 1rem; border-radius: 8px; border: 1px solid #FECACA;">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div style="margin-bottom: 2rem; padding: 0 1rem;">
                <p
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.6); font-weight: 600;">
                    Main Menu</p>
            </div>
            <div class="nav-item active" onclick="showSection('dashboard')">
                <span class="material-icons">grid_view</span> Dashboard
            </div>
            <div class="nav-item" onclick="showSection('requests')">
                <span class="material-icons">work_outline</span> Job Market
            </div>
            <div class="nav-item" onclick="showSection('jobs')">
                <span class="material-icons">task_alt</span> My Jobs
            </div>
            <div class="nav-item" onclick="showSection('profile')">
                <span class="material-icons">person_outline</span> Profile
            </div>
            <div class="nav-item" onclick="showSection('earnings')">
                <span class="material-icons">payments</span> My Earnings
            </div>
            <div class="nav-item" onclick="showSection('complaints')">
                <span class="material-icons">report_problem</span> Support & Complaints
            </div>

            <div
                style="margin-top: auto; padding: 1.5rem; background: rgba(0,0,0,0.1); border-radius: 12px; margin-bottom: 1rem;">
                <p style="font-size: 0.8rem; opacity: 0.9;">Total Earnings</p>
                <h3 style="color: white; font-size: 1.5rem; margin-top: 0.25rem;">
                    ₹<?php echo number_format(count(array_filter($my_jobs, fn($j) => $j['status'] === 'completed')) * ($helper['hourly_rate'] ?: 500)); ?>
                </h3>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">

            <!-- Dashboard Overview Section -->
            <div id="dashboard-section" class="tab-content">
                <div class="flex justify-between items-center mb-4">
                    <h2 style="font-size: 1.5rem; color: #111827;">Overview</h2>
                    <span style="font-size: 0.9rem; color: #6B7280;"><?php echo date('l, d F Y'); ?></span>
                </div>

                <!-- Stats Grid -->
                <div class="grid"
                    style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">

                    <div class="stat-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3>Total Jobs Done</h3>
                                <div class="stat-value" style="color: var(--primary-color);">
                                    <?php echo count(array_filter($my_jobs, fn($j) => $j['status'] === 'completed')); ?>
                                </div>
                            </div>
                            <span class="material-icons" style="color: #E5E7EB; font-size: 40px;">check_circle</span>
                        </div>
                        <span>Lifetime completed services</span>
                    </div>

                    <div class="stat-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3>Active Jobs</h3>
                                <div class="stat-value" style="color: #F59E0B;">
                                    <?php echo count(array_filter($my_jobs, fn($j) => $j['status'] === 'confirmed')); ?>
                                </div>
                            </div>
                            <span class="material-icons" style="color: #E5E7EB; font-size: 40px;">pending_actions</span>
                        </div>
                        <span>Jobs currently in progress</span>
                    </div>

                    <div class="stat-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3>Average Rating</h3>
                                <div class="stat-value" style="color: #10B981;">
                                    <?php echo $helper['average_rating'] > 0 ? $helper['average_rating'] : 'N/A'; ?>
                                    <span style="font-size: 1.5rem; color: #F59E0B; vertical-align: middle;">★</span>
                                </div>
                            </div>
                            <span class="material-icons" style="color: #E5E7EB; font-size: 40px;">star_outline</span>
                        </div>
                        <span>Based on customer reviews</span>
                    </div>
                </div>

                <!-- Quick Action / Banner -->
                <div
                    style="background: linear-gradient(to right, #ffffff, #f0f9ff); border: 1px solid #DBEAFE; border-radius: 16px; padding: 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div>
                        <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem; font-size: 1.25rem;">Find New
                            Opportunities</h3>
                        <p style="color: #4B5563; max-width: 500px; margin-bottom: 1.5rem;">
                            There are currently <strong><?php echo count($new_requests); ?></strong> job requests
                            matching your profile.
                            Browse the market to find your next gig.
                        </p>
                        <button onclick="showSection('requests')" class="btn btn-primary">Go to Job Market</button>
                    </div>
                    <div style="display: none; @media(min-width: 768px) { display: block; }">
                        <span class="material-icons"
                            style="font-size: 100px; color: #BFDBFE; opacity: 0.5;">travel_explore</span>
                    </div>
                </div>
            </div>

            <!-- Booking Requests Section -->
            <div id="requests-section" class="tab-content" style="display: none;">
                <div class="flex justify-between items-center mb-4">
                    <h2 style="font-size: 1.5rem; color: #111827;">Job Market</h2>
                    <div
                        style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="material-icons" style="font-size: 20px; color: #999;">filter_list</span>
                        <select
                            style="border: none; outline: none; font-size: 0.9rem; color: #444; background: transparent;">
                            <option>All Requests</option>
                            <option>High Budget</option>
                            <option>Near Me</option>
                        </select>
                    </div>
                </div>

                <div class="grid" style="gap: 1.5rem;">
                    <?php if (count($new_requests) > 0): ?>
                        <?php foreach ($new_requests as $req): ?>
                            <?php
                            $my_app = $my_applications[$req['id']] ?? null;
                            $app_status = $my_app['status'] ?? null;
                            $is_applied = ($app_status === 'accepted'); // Actually 'accepted' status in requests table usually means helper is selected, but here we track application status differently maybe? 
                            // The logic in original file: 'accepted' meant applied?
                            // Let's assume 'status' in booking_requests is 'pending', 'accepted' (meaning selected by user), or 'rejected'.
                            // If I applied, there should be a record.
                            $has_bid = !empty($my_app);
                            ?>
                            <div class="card" style="<?php echo $is_applied ? 'border-color: #F59E0B;' : ''; ?>">
                                <div class="flex justify-between items-start" style="flex-wrap: wrap; gap: 1rem;">
                                    <div class="flex gap-4 items-start">
                                        <div
                                            style="width: 56px; height: 56px; background: #F3F4F6; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                            <span class="material-icons" style="color: #6B7280;">person</span>
                                        </div>
                                        <div>
                                            <h4 style="font-size: 1.1rem; margin-bottom: 0.25rem;">
                                                <?php echo htmlspecialchars($req['service_name']); ?> Job
                                                <?php if ($has_bid): ?>
                                                    <span class="status-badge status-pending"
                                                        style="margin-left: 0.5rem;">Applied</span>
                                                <?php endif; ?>
                                            </h4>
                                            <p style="color: var(--text-light); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                                Posted by <strong><?php echo htmlspecialchars($req['user_name']); ?></strong> •
                                                <span
                                                    style="font-size: 0.85rem;"><?php echo date('d M, h:i A', strtotime($req['created_at'])); ?></span>
                                            </p>
                                            <div class="flex gap-4" style="font-size: 0.85rem; color: #4B5563;">
                                                <span class="flex items-center gap-1"><span class="material-icons"
                                                        style="font-size: 16px;">calendar_today</span>
                                                    <?php echo date('d M Y', strtotime($req['date'])); ?></span>
                                                <span class="flex items-center gap-1"><span class="material-icons"
                                                        style="font-size: 16px;">schedule</span>
                                                    <?php echo $req['time'] ? date('h:i A', strtotime($req['time'])) : 'Flex'; ?></span>
                                                <span class="flex items-center gap-1"><span class="material-icons"
                                                        style="font-size: 16px;">place</span>
                                                    <?php echo htmlspecialchars($req['location'] ?? 'Remote'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center" style="min-width: 120px;">
                                        <p
                                            style="font-size: 0.8rem; color: var(--text-light); text-transform: uppercase; font-weight: 600;">
                                            Client Budget</p>
                                        <div style="font-size: 1.5rem; font-weight: 700; color: #111827;">
                                            <?php echo $req['budget'] ? '₹' . $req['budget'] : '<span style="font-size:1rem; color:#666;">Open</span>'; ?>
                                        </div>
                                    </div>
                                </div>

                                <hr style="border: 0; border-top: 1px solid #F3F4F6; margin: 1.25rem 0;">

                                <?php if ($req['special_instructions']): ?>
                                    <div
                                        style="background: #F9FAFB; padding: 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; color: #4B5563;">
                                        <strong>Note:</strong> <?php echo htmlspecialchars($req['special_instructions']); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Bidding Area -->
                                <div
                                    style="background: #FAFAFA; border: 1px dashed #E5E7EB; padding: 1rem; border-radius: 8px;">
                                    <form action="api/booking_action.php" method="POST" class="flex items-end gap-2"
                                        style="flex-wrap: wrap;">
                                        <input type="hidden" name="action" value="accept">
                                        <!-- 'accept' here means apply/bid -->
                                        <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">

                                        <div style="flex: 1; min-width: 140px;">
                                            <label
                                                style="display: block; font-size: 0.8rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Your
                                                Offer (₹)</label>
                                            <input type="number" name="bid_price" required
                                                value="<?php echo $has_bid ? htmlspecialchars($my_app['bid_price'] ?? '') : ($req['budget'] ?: ''); ?>"
                                                style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 6px;">
                                        </div>

                                        <div style="flex: 1; min-width: 140px;">
                                            <label
                                                style="display: block; font-size: 0.8rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Arrival
                                                Time</label>
                                            <input type="text" name="arrival_estimate" required placeholder="e.g. 10:00 AM"
                                                value="<?php echo $has_bid ? htmlspecialchars($my_app['arrival_estimate'] ?? '') : ''; ?>"
                                                style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 6px;">
                                        </div>

                                        <div style="flex: 2; min-width: 200px;">
                                            <label
                                                style="display: block; font-size: 0.8rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem;">Notes</label>
                                            <input type="text" name="notes" placeholder="I have my own tools..."
                                                value="<?php echo $has_bid ? htmlspecialchars($my_app['notes'] ?? '') : ''; ?>"
                                                style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 6px;">
                                        </div>

                                        <div style="flex: 0 0 auto;">
                                            <label
                                                style="display: block; font-size: 0.8rem; font-weight: 600; color: #4B5563; margin-bottom: 0.25rem; visibility: hidden;">Action</label>
                                            <button type="submit" class="btn btn-primary"
                                                style="padding: 0.55rem 1.5rem; border-radius: 6px;">
                                                <?php echo $has_bid ? 'Update Bid' : 'Submit Bid'; ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div
                            style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 1px dashed #E5E7EB;">
                            <span class="material-icons"
                                style="font-size: 48px; color: #D1D5DB; margin-bottom: 1rem;">inbox</span>
                            <h3 style="color: #6B7280; font-size: 1.1rem;">No new jobs available</h3>
                            <p style="color: #9CA3AF;">Check back later for new opportunities in
                                <?php echo htmlspecialchars($helper_role); ?>.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Jobs Section -->
            <div id="jobs-section" class="tab-content" style="display: none;">
                <h2 style="font-size: 1.5rem; color: #111827; margin-bottom: 1.5rem;">Job History</h2>
                <div class="grid" style="gap: 1.5rem;">
                    <?php if (count($my_jobs) > 0): ?>
                        <?php foreach ($my_jobs as $job): ?>
                            <div class="card" style="border-left: 4px solid var(--primary-color);">
                                <div class="flex justify-between items-start" style="flex-wrap: wrap; gap: 1rem;">
                                    <div class="flex gap-4 items-start">
                                        <div
                                            style="width: 56px; height: 56px; background: #E0E7FF; color: #4F46E5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem;">
                                            <?php echo strtoupper(substr($job['user_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h4 style="font-size: 1.1rem; margin-bottom: 0.25rem;">
                                                <?php echo htmlspecialchars($job['service_name']); ?>
                                            </h4>
                                            <p style="color: var(--text-light); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                                Client: <strong
                                                    style="color: var(--text-color);"><?php echo htmlspecialchars($job['user_name']); ?></strong>
                                                <span
                                                    style="font-size: 0.8rem; color: #9CA3AF; margin-left: 0.5rem;">#<?php echo $job['id']; ?></span>
                                            </p>
                                            <div
                                                style="margin-bottom: 0.75rem; display: flex; flex-direction: column; gap: 4px;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 0.8rem; color: #666;">Payment:
                                                        <strong><?php echo htmlspecialchars($job['payment_method']); ?></strong></span>
                                                    <?php if ($job['payment_status'] === 'paid'): ?>
                                                        <span class="payment-status-badge ps-paid">Paid</span>
                                                    <?php else: ?>
                                                        <span class="payment-status-badge ps-pending">Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 1rem; font-weight: 700; color: #111827;">
                                                    Amount: ₹<?php echo number_format($job['total_amount'], 2); ?>
                                                </div>
                                            </div>
                                            <div class="flex gap-4" style="font-size: 0.85rem; color: #4B5563;">
                                                <span class="flex items-center gap-1">
                                                    <span class="material-icons" style="font-size: 16px;">calendar_today</span>
                                                    <?php echo date('d M Y', strtotime($job['date'])); ?>
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <span class="material-icons" style="font-size: 16px;">schedule</span>
                                                    <?php echo $job['time'] ? date('h:i A', strtotime($job['time'])) : 'Flexible'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center" style="min-width: 120px;">
                                        <div style="margin-bottom: 0.5rem;">
                                            <span class="status-badge status-<?php echo $job['status']; ?>">
                                                <?php echo ucfirst($job['status']); ?>
                                            </span>
                                        </div>
                                        <?php if ($job['status'] === 'confirmed'): ?>
                                            <form action="api/verify_otp.php" method="POST" class="otp-verify-form">
                                                <input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>">
                                                <input type="hidden" name="type" value="start">
                                                <div style="margin-bottom: 8px;">
                                                    <input type="text" name="otp" placeholder="Enter Start OTP" required
                                                        maxlength="4"
                                                        style="width: 100%; padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px; text-align: center; font-weight: 700; letter-spacing: 2px;">
                                                </div>
                                                <button type="submit" class="btn btn-primary"
                                                    style="padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 6px; width: 100%; background: #2563EB;">
                                                    Start Job
                                                </button>
                                            </form>
                                        <?php elseif ($job['status'] === 'in-progress'): ?>
                                            <form action="api/verify_otp.php" method="POST" class="otp-verify-form">
                                                <input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>">
                                                <input type="hidden" name="type" value="end">
                                                <div style="margin-bottom: 8px;">
                                                    <input type="text" name="otp" placeholder="Enter End OTP" required maxlength="4"
                                                        style="width: 100%; padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px; text-align: center; font-weight: 700; letter-spacing: 2px;">
                                                </div>
                                                <button type="submit" class="btn btn-primary"
                                                    style="padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 6px; width: 100%; background: #10B981; border: none;">
                                                    Complete Job
                                                </button>
                                            </form>
                                        <?php elseif ($job['status'] === 'completed'): ?>
                                            <span
                                                style="color: #10B981; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                                <span class="material-icons" style="font-size: 16px;">task_alt</span> Finished
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($job['payment_method'] === 'Cash' && $job['payment_status'] === 'pending' && in_array($job['status'], ['in-progress', 'completed'])): ?>
                                            <button class="btn btn-primary"
                                                style="margin-top: 8px; width: 100%; background: #F97316; border: none; font-size: 0.8rem;"
                                                onclick="markAsPaid('<?php echo $job['id']; ?>')">
                                                <span class="material-icons"
                                                    style="font-size: 16px; vertical-align: middle;">paid</span>
                                                Received Cash
                                            </button>
                                        <?php endif; ?>
                                        <?php if (in_array($job['status'], ['confirmed', 'in-progress'])): ?>
                                            <button class="btn btn-outline"
                                                style="margin-top: 8px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 4px; font-size: 0.8rem;"
                                                onclick="openChat('<?php echo $job['id']; ?>', '<?php echo $job['user_id']; ?>', '<?php echo htmlspecialchars($job['user_name']); ?>')">
                                                <span class="material-icons" style="font-size: 16px;">chat</span> Chat
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($job['status'] !== 'cancelled'): ?>
                                            <button class="btn btn-outline"
                                                style="margin-top: 8px; width: 100%; color: #EF4444; border-color: #EF4444; font-size: 0.8rem; padding: 0.4rem 1rem;"
                                                onclick="openComplaintModal('<?php echo $job['id']; ?>', '<?php echo htmlspecialchars($job['service_name']); ?>')">
                                                <span class="material-icons"
                                                    style="font-size: 16px; vertical-align: middle;">report_problem</span>
                                                Report Issue
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr style="border: 0; border-top: 1px solid #F3F4F6; margin: 1.25rem 0;">
                                <div
                                    style="font-size: 0.9rem; color: #4B5563; background: #F9FAFB; padding: 0.75rem; border-radius: 6px; display: flex; gap: 8px;">
                                    <span class="material-icons"
                                        style="font-size: 18px; color: var(--primary-color);">location_on</span>
                                    <span><?php echo htmlspecialchars($job['location']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div
                            style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 1px dashed #E5E7EB;">
                            <span class="material-icons"
                                style="font-size: 48px; color: #D1D5DB; margin-bottom: 1rem;">event_busy</span>
                            <h3 style="color: #6B7280; font-size: 1.1rem;">No active jobs</h3>
                            <p style="color: #9CA3AF;">You don't have any matching active or past jobs yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Section -->
            <div id="profile-section" class="tab-content" style="display: none;">
                <h2 style="font-size: 1.5rem; color: #111827; margin-bottom: 2rem;">Edit Profile</h2>

                <form action="api/update_profile.php" method="POST" enctype="multipart/form-data">
                    <div style="background: white; border: 1px solid #E5E7EB; border-radius: 16px; overflow: hidden;">
                        <div class="grid" style="grid-template-columns: 300px 1fr; gap: 0;">

                            <!-- Left Panel: Photo -->
                            <div
                                style="background: #F9FAFB; padding: 3rem 2rem; text-align: center; border-right: 1px solid #E5E7EB;">
                                <div
                                    style="width: 140px; height: 140px; background: white; border-radius: 50%; border: 4px solid white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin: 0 auto 1.5rem; overflow: hidden; position: relative;">
                                    <?php if (!empty($helper['profile_photo'])): ?>
                                        <img src="<?php echo htmlspecialchars($helper['profile_photo']); ?>"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div
                                            style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #E5E7EB;">
                                            <span class="material-icons"
                                                style="font-size: 64px; color: #9CA3AF;">person</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <label class="btn btn-outline"
                                    style="display: inline-block; padding: 0.5rem 1rem; font-size: 0.85rem; cursor: pointer;">
                                    Change Photo
                                    <input type="file" name="profile_photo" accept="image/png, image/jpeg, image/webp"
                                        style="display: none;">
                                </label>

                                <h3 style="margin-top: 1.5rem; margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($helper['name']); ?>
                                </h3>
                                <p style="color: #6B7280; margin-bottom: 1.5rem; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($helper['email']); ?>
                                </p>
                            </div>

                            <!-- Right Panel: Fields (Editable) -->
                            <div style="padding: 3rem;">
                                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="input-group">
                                        <label>Full Name</label>
                                        <input type="text" name="name" class="form-control"
                                            value="<?php echo htmlspecialchars($helper['name']); ?>" required>
                                    </div>
                                    <div class="input-group">
                                        <label>Phone Number</label>
                                        <input type="text" name="phone_number" class="form-control"
                                            value="<?php echo htmlspecialchars($helper['phone_number']); ?>">
                                    </div>
                                    <div class="input-group">
                                        <label>Gender</label>
                                        <select name="gender" class="form-control">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($helper['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($helper['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($helper['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>

                                    <div class="input-group">
                                        <label>Job Category</label>
                                        <input type="text" class="form-control"
                                            value="<?php echo htmlspecialchars($helper_role ?? 'General'); ?>"
                                            style="background: #F9FAFB; color: #6B7280; font-weight: 500;" readonly
                                            title="Contact an administrator to change your job category">
                                    </div>
                                </div>

                                <div class="input-group" style="margin-top: 1.5rem;">
                                    <label>Address</label>
                                    <textarea name="address" class="form-control"
                                        rows="2"><?php echo htmlspecialchars($helper['address']); ?></textarea>
                                </div>

                                <div class="input-group">
                                    <label>Bio</label>
                                    <textarea name="bio" class="form-control"
                                        rows="3"><?php echo htmlspecialchars($helper['bio']); ?></textarea>
                                </div>

                                <?php if (isset($helper['password_changed']) && $helper['password_changed'] == 0): ?>
                                    <div
                                        style="margin-top: 1.5rem; background: #FEF2F2; padding: 1.5rem; border-radius: 8px; border: 1px solid #FECACA;">
                                        <h4 style="color: #991B1B; margin-bottom: 1rem; font-size: 1rem;">Update Default
                                            Password</h4>
                                        <p style="font-size: 0.85rem; color: #DC2626; margin-bottom: 1rem;">Please set a new
                                            secure password. This option is only available once to secure your account.</p>
                                        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                                            <div class="input-group" style="margin: 0;">
                                                <input type="password" name="new_password" class="form-control"
                                                    placeholder="New Password" minlength="6">
                                            </div>
                                            <div class="input-group" style="margin: 0;">
                                                <input type="password" name="confirm_password" class="form-control"
                                                    placeholder="Confirm Password" minlength="6">
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div style="text-align: right; margin-top: 2rem;">
                                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">Save
                                        Changes</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Complaints Section -->
            <div id="complaints-section" class="tab-content" style="display: none; padding: 1.5rem;">
                <h3 style="margin-bottom: 2rem; color: #111827;">Support & Complaints</h3>
                <div id="complaintList" class="grid"
                    style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <!-- Complaints will be loaded here by JS -->
                </div>
            </div>

            <!-- Earnings Section -->
            <div id="earnings-section" class="tab-content" style="display: none; padding: 1.5rem;">
                <div class="flex justify-between items-center mb-4">
                    <h2 style="font-size: 1.5rem; color: #111827;">My Earnings</h2>
                    <button class="btn btn-primary" onclick="openWithdrawModal()">
                        <span class="material-icons" style="font-size: 18px; vertical-align: middle;">account_balance_wallet</span> Withdraw Funds
                    </button>
                </div>

                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="stat-card" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; border: none;">
                        <span style="font-size: 0.9rem; opacity: 0.9; text-transform: uppercase; font-weight: 600;">Total Lifetime Earnings</span>
                        <div style="font-size: 2.5rem; font-weight: 800; margin: 0.5rem 0;">₹<span id="totalEarnings">0</span></div>
                        <span style="font-size: 0.8rem; opacity: 0.8;">After 20% platform fee</span>
                    </div>
                    <div class="stat-card" style="background: white; border: 1px solid #E5E7EB;">
                        <span style="font-size: 0.9rem; color: #6B7280; text-transform: uppercase; font-weight: 600;">Withdrawable Balance</span>
                        <div style="font-size: 2.5rem; font-weight: 800; margin: 0.5rem 0; color: #111827;">₹<span id="withdrawableBalance">0</span></div>
                        <span style="font-size: 0.8rem; color: #10B981; font-weight: 600;">Available for payout</span>
                    </div>
                </div>

                <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
                    <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem; color: #374151;">Earnings Overview (Last 7 Days)</h3>
                    <div style="height: 300px;">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 1.5rem; background: #F8FAFC; border-bottom: 1px solid #E5E7EB;">
                        <h3 style="font-size: 1.1rem; color: #374151;">Recent Withdrawal Requests</h3>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #F9FAFB; text-align: left;">
                                    <th style="padding: 1rem; font-size: 0.85rem; color: #6B7280; font-weight: 600; border-bottom: 1px solid #E5E7EB;">Date</th>
                                    <th style="padding: 1rem; font-size: 0.85rem; color: #6B7280; font-weight: 600; border-bottom: 1px solid #E5E7EB;">Amount</th>
                                    <th style="padding: 1rem; font-size: 0.85rem; color: #6B7280; font-weight: 600; border-bottom: 1px solid #E5E7EB;">Bank Details</th>
                                    <th style="padding: 1rem; font-size: 0.85rem; color: #6B7280; font-weight: 600; border-bottom: 1px solid #E5E7EB;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="withdrawHistory">
                                <!-- Requests will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Withdraw Modal -->
            <div id="withdrawModal" class="chat-modal-overlay" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
                <div style="background: white; width: 450px; padding: 2rem; border-radius: 16px; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                    <span class="material-icons" style="position: absolute; right: 1.5rem; top: 1.5rem; cursor: pointer; color: #9CA3AF;" onclick="closeWithdrawModal()">close</span>
                    <h2 style="margin-bottom: 0.5rem; color: #111827;">Withdraw Funds</h2>
                    <p style="font-size: 0.9rem; color: #6B7280; margin-bottom: 1.5rem;">Transfer your earnings to your bank account.</p>
                    
                    <form id="withdrawForm">
                        <div class="input-group">
                            <label style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; display: block;">Withdrawal Amount (₹)</label>
                            <input type="number" id="withdrawAmount" name="amount" required placeholder="Enter amount..." style="width: 100%; padding: 0.75rem; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 1.1rem; font-weight: 700;">
                            <small style="color: #6B7280; margin-top: 0.5rem; display: block;">Max withdrawable: ₹<span id="maxWithdraw">0</span></small>
                        </div>
                        
                        <div class="input-group">
                            <label style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; display: block;">Bank Account / UPI Details</label>
                            <textarea name="bank_details" required placeholder="Enter your Bank Name, A/c Number, IFSC or UPI ID..." style="width: 100%; padding: 0.75rem; border: 1px solid #D1D5DB; border-radius: 8px; height: 100px; resize: none;"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-weight: 700; background: #0F172A; border: none; font-size: 1rem; margin-top: 1rem;">Process Payout</button>
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

            const targetEl = document.getElementById(targetId);
            if (targetEl) targetEl.style.display = 'block';

            // Find the index of nav item to activate
            const indexMap = { 'dashboard': 0, 'requests': 1, 'jobs': 2, 'profile': 3 };
            const navItems = document.querySelectorAll('.nav-item');
            if (navItems[indexMap[sectionId]]) {
                navItems[indexMap[sectionId]].classList.add('active');
            }
        }
    </script>
    <!-- Chat Modal Overlay -->
    <div id="chatOverlay" class="chat-modal-overlay">
        <div class="chat-header">
            <h4><span class="material-icons">chat</span> Chat with <span id="chatReceiverName">Client</span></h4>
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
    <script>
        // Location Tracking
        function trackLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    const fd = new FormData();
                    fd.append('lat', pos.coords.latitude);
                    fd.append('lng', pos.coords.longitude);
                    fetch('api/update_location.php', {
                        method: 'POST',
                        body: fd
                    }).catch(e => console.error("Location update failed", e));
                }, (err) => console.warn("Geolocation permission or error", err), {
                    enableHighAccuracy: true
                });
            }
        }

        // Only track if there is an active job confirmed or in-progress
        const activeJobsCount = <?php echo count(array_filter($my_jobs, fn($j) => in_array($j['status'], ['confirmed', 'in-progress']))); ?>;
        if (activeJobsCount > 0) {
            trackLocation(); // Initial call
            setInterval(trackLocation, 30000); // Every 30 seconds
        }

        function markAsPaid(bookingId) {
            if (!confirm('Are you sure you have received the cash payment for this booking?')) return;

            const fd = new FormData();
            fd.append('booking_id', bookingId);

            fetch('api/mark_as_paid.php', {
                method: 'POST',
                body: fd
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred. Please try again.');
                });
        }
    </script>
    <!-- Complaint Modal -->
    <div id="complaintModal" class="chat-modal-overlay"
        style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div style="background: white; width: 400px; padding: 2rem; border-radius: 12px; position: relative;">
            <span class="material-icons" style="position: absolute; right: 1rem; top: 1rem; cursor: pointer;"
                onclick="closeComplaintModal()">close</span>
            <h2 style="margin-bottom: 1rem; color: #DC2626;">Report Issue</h2>
            <p id="complaintBookingInfo" style="font-size: 0.9rem; color: #6B7280; margin-bottom: 1.5rem;"></p>
            <form id="complaintForm">
                <input type="hidden" id="complaintBookingId" name="booking_id">
                <textarea id="complaintDescription" name="description" placeholder="What's the issue with this job?"
                    required
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 1rem; height: 100px; resize: none;"></textarea>
                <button type="submit" class="btn btn-primary"
                    style="width: 100%; background: #DC2626; border: none;">Submit Report</button>
            </form>
        </div>
    </div>

    <!-- Complaint Chat Overlay (Floating) -->
    <div id="complaintChatOverlay" class="chat-modal-overlay">
        <div class="chat-header" style="background: #DC2626;">
            <h4><span class="material-icons">support_agent</span> Admin Support</h4>
            <span class="material-icons close-chat" onclick="closeComplaintChat()">close</span>
        </div>
        <div id="complaintMessages" class="chat-messages"></div>
        <div class="chat-input-area">
            <input type="text" id="complaintChatInput" placeholder="Type a message...">
            <button class="chat-send-btn" style="background: #DC2626;" onclick="sendComplaintMessage()">
                <span class="material-icons">send</span>
            </button>
        </div>
    </div>

    <script>
        function openComplaintModal(bookingId, serviceName) {
            document.getElementById('complaintBookingId').value = bookingId;
            document.getElementById('complaintBookingInfo').textContent = "Job: " + serviceName + " (#" + bookingId + ")";
            document.getElementById('complaintModal').style.display = 'flex';
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
                            container.innerHTML = '<div style="text-align: center; padding: 3rem; color: #94a3b8; grid-column: 1/-1;"><p>No active complaints.</p></div>';
                            return;
                        }

                        data.complaints.forEach(c => {
                            const card = document.createElement('div');
                            card.className = 'card';
                            card.style.cursor = 'pointer';
                            card.style.borderLeft = '4px solid ' + (c.status === 'resolved' ? '#10B981' : (c.status === 'pending' ? '#F59E0B' : '#94a3b8'));
                            card.onclick = () => openComplaintChat(c.id);

                            card.innerHTML = `
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h4 style="margin: 0;">${c.service_name || 'General Support'}</h4>
                                    <p style="font-size: 0.9rem; color: #64748B; margin: 0.5rem 0;">${c.description}</p>
                                    <small style="color: #94a3b8;">${new Date(c.created_at).toLocaleDateString()}</small>
                                </div>
                                <span class="status-badge" style="background: #f1f5f9; color: #475569;">${c.status.toUpperCase()}</span>
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

        // Initialize helper scripts
        const originalShowSectionHelper = showSection;
        showSection = function (sectionId) {
            originalShowSectionHelper(sectionId);
            if (sectionId === 'complaints') loadComplaints();
            if (sectionId === 'earnings') loadEarnings();
        };

        function loadEarnings() {
            fetch('api/helper_earnings.php?action=get_stats')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalEarnings').textContent = parseFloat(data.total_earnings).toLocaleString();
                        document.getElementById('withdrawableBalance').textContent = parseFloat(data.withdrawable_balance).toLocaleString();
                        document.getElementById('maxWithdraw').textContent = parseFloat(data.withdrawable_balance).toLocaleString();
                        updateEarningsChart(data.daily_stats);
                        loadWithdrawals();
                    }
                });
        }

        let earningsChart = null;
        function updateEarningsChart(stats) {
            const ctx = document.getElementById('earningsChart').getContext('2d');
            const labels = stats.map(s => new Date(s.date).toLocaleDateString([], { month: 'short', day: 'numeric' }));
            const values = stats.map(s => s.earnings);

            if (earningsChart) earningsChart.destroy();

            earningsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Earnings (₹)',
                        data: values,
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#10B981',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        function loadWithdrawals() {
            fetch('api/helper_earnings.php?action=get_withdrawals')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.getElementById('withdrawHistory');
                        tbody.innerHTML = '';
                        if (data.withdrawals.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="4" style="padding: 2rem; text-align: center; color: #94a3b8;">No withdrawal history.</td></tr>';
                            return;
                        }
                        data.withdrawals.forEach(w => {
                            const statusColor = w.status === 'completed' ? '#10B981' : (w.status === 'pending' ? '#F59E0B' : '#EF4444');
                            tbody.innerHTML += `
                                <tr>
                                    <td style="padding: 1rem; border-bottom: 1px solid #E5E7EB;">${new Date(w.created_at).toLocaleDateString()}</td>
                                    <td style="padding: 1rem; border-bottom: 1px solid #E5E7EB; font-weight: 600;">₹${parseFloat(w.amount).toLocaleString()}</td>
                                    <td style="padding: 1rem; border-bottom: 1px solid #E5E7EB; color: #64748B; font-size: 0.85rem;">${w.bank_details}</td>
                                    <td style="padding: 1rem; border-bottom: 1px solid #E5E7EB;">
                                        <span class="status-badge" style="background: ${statusColor}15; color: ${statusColor};">${w.status.toUpperCase()}</span>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                });
        }

        function openWithdrawModal() {
            document.getElementById('withdrawModal').style.display = 'flex';
        }

        function closeWithdrawModal() {
            document.getElementById('withdrawModal').style.display = 'none';
        }

        document.getElementById('withdrawForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'request_withdrawal');

            fetch('api/helper_earnings.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeWithdrawModal();
                    loadEarnings();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        });
    </script>
</body>

</html>