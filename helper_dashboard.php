<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'helper') {
    header('Location: login.php');
    exit;
}

$helper_id = $_SESSION['user_id'];

// Fetch Helper's Job Role, Email, and Gender
$stmt = $pdo->prepare("SELECT job_role, email, gender, average_rating, bio, phone_number, address, profile_photo, hourly_rate, name FROM users WHERE id = ?");
$stmt->execute([$helper_id]);
$helper = $stmt->fetch();
$helper_role = $helper['job_role'];

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
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
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
                                            <form action="api/booking_action.php" method="POST">
                                                <input type="hidden" name="action" value="complete">
                                                <input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" class="btn btn-primary"
                                                    style="padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 6px; width: 100%;">
                                                    Mark Done
                                                </button>
                                            </form>
                                        <?php elseif ($job['status'] === 'completed'): ?>
                                            <span
                                                style="color: #10B981; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                                <span class="material-icons" style="font-size: 16px;">task_alt</span> Paid
                                            </span>
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
                                        <span class="material-icons" style="font-size: 64px; color: #9CA3AF;">person</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h3 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($helper['name']); ?></h3>
                            <p style="color: #6B7280; margin-bottom: 1.5rem; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($helper['email']); ?>
                            </p>

                            <div
                                style="background: #FEF3C7; color: #92400E; padding: 1rem; border-radius: 8px; font-size: 0.85rem; margin-top: 2rem;">
                                <p style="margin-bottom: 0.5rem;"><strong>Profile Locked</strong></p>
                                <p>To update your profile details or photo, please contact the administrator.</p>
                            </div>
                        </div>

                        <!-- Right Panel: Fields (Read Only) -->
                        <div style="padding: 3rem;">
                            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div class="input-group">
                                    <label>Full Name</label>
                                    <div class="form-control" style="background: #F3F4F6;">
                                        <?php echo htmlspecialchars($helper['name']); ?>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label>Phone Number</label>
                                    <div class="form-control" style="background: #F3F4F6;">
                                        <?php echo htmlspecialchars($helper['phone_number'] ?? 'Not set'); ?>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label>Gender</label>
                                    <div class="form-control" style="background: #F3F4F6;">
                                        <?php echo htmlspecialchars($helper['gender'] ?? 'Not set'); ?>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label>Hourly Rate (₹)</label>
                                    <div class="form-control" style="background: #F3F4F6;">
                                        <?php echo htmlspecialchars($helper['hourly_rate'] ?? 'Not set'); ?>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label>Job Category</label>
                                    <div class="form-control" style="background: #F3F4F6;">
                                        <span
                                            class="status-badge badge-purple"><?php echo htmlspecialchars($helper_role ?? 'General'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="input-group" style="margin-top: 1.5rem;">
                                <label>Address</label>
                                <div class="form-control" style="background: #F3F4F6; min-height: 60px;">
                                    <?php echo htmlspecialchars($helper['address'] ?? 'Not set'); ?>
                                </div>
                            </div>

                            <div class="input-group">
                                <label>Bio</label>
                                <div class="form-control" style="background: #F3F4F6; min-height: 80px;">
                                    <?php echo htmlspecialchars($helper['bio'] ?? 'No bio provided'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
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
</body>

</html>