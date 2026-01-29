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
// $my_applications is now [booking_id => ['status'=>..., 'bid_price'=>..., ...]]

// Available jobs (pending or accepted-but-open, matching role)
// Exclude jobs where I have already been rejected or selected (though selected would mean helper_id is set likely)
// Actually if I was rejected, I shouldn't see it.
$stmt = $pdo->prepare("
    SELECT b.*, s.name as service_name, u.name as user_name 
    FROM bookings b 
    JOIN services s ON b.service_id = s.id 
    JOIN users u ON b.user_id = u.id 
    WHERE (b.status = 'pending' OR b.status = 'accepted')
    AND b.helper_id IS NULL
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
        continue; // Don't show if I rejected or was rejected
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
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
            <div class="flex items-center gap-4">
                <span>Helper: <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b></span>
                <span class="status-badge status-completed">Available</span>
                <a href="api/logout.php" class="btn btn-outline"
                    style="padding: 0.5rem 1rem; font-size: 0.9rem;">Logout</a>
            </div>
        </div>
    </header>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="container" style="margin-top: 1rem;">
            <div style="background: var(--success); color: white; padding: 1rem; border-radius: 8px;">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="container" style="margin-top: 1rem;">
            <div style="background: #DC2626; color: white; padding: 1rem; border-radius: 8px;">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-layout">
        <!-- Sidebar -->
        <div class="sidebar" style="background-color: #10B981;">
            <div class="nav-item active" onclick="showSection('dashboard')">
                <span class="material-icons">dashboard</span> Dashboard
            </div>
            <div class="nav-item" onclick="showSection('requests')">
                <span class="material-icons">assignment</span> Job Market
            </div>
            <div class="nav-item" onclick="showSection('jobs')">
                <span class="material-icons">work</span> My Jobs
            </div>
            <div class="nav-item" onclick="showSection('profile')">
                <span class="material-icons">person</span> Profile
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">

            <!-- Dashboard Overview Section -->
            <div id="dashboard-section" class="tab-content">
                <h2 style="margin-bottom: 2rem;">Helper Dashboard</h2>
                <!-- Stats -->
                <div class="grid mb-4"
                    style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div class="stat-card">
                        <h3>Total Jobs Done</h3>
                        <div class="stat-value">
                            <?php echo count(array_filter($my_jobs, fn($j) => $j['status'] === 'completed')); ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <h3>Active Jobs</h3>
                        <div class="stat-value">
                            <?php echo count(array_filter($my_jobs, fn($j) => $j['status'] === 'confirmed')); ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <h3>Rating</h3>
                        <div class="stat-value">
                            <?php echo $helper['average_rating'] > 0 ? $helper['average_rating'] . ' ★' : 'N/A'; ?>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                    <h3>Quick Overview</h3>
                    <p>You have <strong><?php echo count($new_requests); ?></strong> opportunities in the Market.
                        Check the "Job Market" tab.</p>
                </div>
            </div>

            <!-- Booking Requests Section -->
            <div id="requests-section" class="tab-content" style="display: none;">
                <h2 style="margin-bottom: 2rem;">Job Market</h2>
                <div class="grid" style="gap: 1rem;">
                    <?php if (count($new_requests) > 0): ?>
                        <?php foreach ($new_requests as $req): ?>
                            <?php
                            $my_app = $my_applications[$req['id']] ?? null;
                            $app_status = $my_app['status'] ?? null;
                            $is_applied = ($app_status === 'accepted');
                            ?>
                            <div class="stat-card flex justify-between items-center"
                                style="border-left: 5px solid <?php echo $is_applied ? '#F59E0B' : '#10B981'; ?>;">
                                <div style="flex: 1;">
                                    <h4 style="margin-bottom: 0.25rem;">
                                        User: <?php echo htmlspecialchars($req['user_name']); ?>
                                        <?php if($is_applied): ?> 
                                            <span style="font-size:0.8rem; background:#F59E0B; color:white; padding:2px 6px; border-radius:4px; vertical-align:middle; margin-left:5px;">Applied</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p style="color: var(--text-light); font-size: 0.9rem;">
                                        Service: <b><?php echo htmlspecialchars($req['service_name']); ?></b> <br>
                                        Date: <?php echo date('d-M-Y', strtotime($req['date'])); ?>
                                        <?php if ($req['time'])
                                            echo ' at ' . date('h:i A', strtotime($req['time'])); ?> <br>
                                        Location: <?php echo htmlspecialchars($req['location'] ?? 'N/A'); ?> <br>
                                        Budget: <?php echo $req['budget'] ? '₹' . $req['budget'] : 'Standard Rate'; ?>
                                    </p>
                                    <?php if ($req['special_instructions']): ?>
                                        <p style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">
                                            <i>Note: <?php echo htmlspecialchars($req['special_instructions']); ?></i>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex gap-4">
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <form action="api/booking_action.php" method="POST"
                                            style="display: flex; gap: 0.5rem; flex-direction: column;">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">

                                            <input type="number" name="bid_price" 
                                                placeholder="Your Offer (₹)" required
                                                value="<?php echo $is_applied ? htmlspecialchars($my_app['bid_price'] ?? '') : ($req['budget'] ?: ''); ?>"
                                                style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; width: 140px;">

                                            <input type="text" name="arrival_estimate"
                                                placeholder="Arrival Time" required
                                                value="<?php echo $is_applied ? htmlspecialchars($my_app['arrival_estimate'] ?? '') : ''; ?>"
                                                style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; width: 140px;">

                                            <textarea name="notes" placeholder="Notes (e.g. tools)" rows="1"
                                                style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; resize: vertical; width: 140px;"><?php echo $is_applied ? htmlspecialchars($my_app['notes'] ?? '') : ''; ?></textarea>

                                            <button type="submit" class="btn"
                                                style="background: <?php echo $is_applied ? '#3B82F6' : '#10B981'; ?>; color: white; width: 100%;">
                                                <?php echo $is_applied ? 'Update Bid' : 'Submit Bid'; ?>
                                            </button>
                                        </form>

                                        <?php if ($is_applied): ?>
                                            <form action="api/booking_action.php" method="POST" onsubmit="return confirm('Withdraw your bid?');">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">
                                                <button type="submit" class="btn"
                                                    style="background: #6B7280; color: white; width: 100%; border:none; font-size: 0.9rem; padding: 0.5rem;">Withdraw</button>
                                            </form>
                                        <?php else: ?>
                                            <form action="api/booking_action.php" method="POST">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">
                                                <button type="submit" class="btn"
                                                    style="background: #EF4444; color: white; width: 100%; border:none; font-size: 0.9rem; padding: 0.5rem;">Ignore</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-light);">No new jobs available for
                            <strong><?php echo htmlspecialchars($helper_role); ?></strong>.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Jobs Section -->
            <div id="jobs-section" class="tab-content" style="display: none;">
                <h2 style="margin-bottom: 2rem;">My Jobs History</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Service Details</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_jobs as $job): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($job['user_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($job['service_name']); ?><br>
                                        <small><?php echo htmlspecialchars($job['location']); ?></small>
                                    </td>
                                    <td><?php echo date('d-M-Y', strtotime($job['date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $job['status']; ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($job['status'] === 'confirmed'): ?>
                                            <form action="api/booking_action.php" method="POST">
                                                <input type="hidden" name="action" value="complete">
                                                <input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" class="btn"
                                                    style="background: #3B82F6; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">Mark
                                                    Done</button>
                                            </form>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                                style="width: 120px; height: 120px; background: #f3f4f6; border-radius: 50%; overflow: hidden; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; border: 4px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                <?php if (!empty($helper['profile_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($helper['profile_photo']); ?>"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span class="material-icons" style="font-size: 64px; color: #9ca3af;">person</span>
                                <?php endif; ?>
                            </div>
                            <label for="photo_upload"
                                style="cursor: pointer; color: var(--primary-color); font-weight: 500;">
                                Change Photo
                                <input type="file" id="photo_upload" name="profile_photo" accept="image/*"
                                    style="display: none;"
                                    onchange="document.getElementById('save-btn').disabled = false;">
                            </label>
                        </div>

                        <div class="grid"
                            style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">

                            <!-- Personal Details -->
                            <div>
                                <h3 style="margin-bottom: 1rem; color: #4b5563; font-size: 1.1rem;">Personal Details
                                </h3>
                                <div class="input-group">
                                    <label>Full Name</label>
                                    <input type="text" name="name"
                                        value="<?php echo htmlspecialchars($helper['name']); ?>" required>
                                </div>
                                <div class="input-group">
                                    <label>Email Address</label>
                                    <input type="email" value="<?php echo htmlspecialchars($helper['email']); ?>"
                                        readonly style="background: #f3f4f6;">
                                </div>
                                <div class="input-group">
                                    <label>Phone Number</label>
                                    <input type="tel" name="phone_number"
                                        value="<?php echo htmlspecialchars($helper['phone_number'] ?? ''); ?>"
                                        pattern="[0-9]{10}" maxlength="10" placeholder="10-digit number">
                                </div>
                                <div class="input-group">
                                    <label>Gender</label>
                                    <select name="gender">
                                        <option value="" disabled <?php echo empty($helper['gender']) ? 'selected' : ''; ?>>Select Gender</option>
                                        <option value="Male" <?php echo ($helper['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($helper['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo ($helper['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Professional Details -->
                            <div>
                                <h3 style="margin-bottom: 1rem; color: #4b5563; font-size: 1.1rem;">Professional Details
                                </h3>
                                <div class="input-group">
                                    <label>Job Category</label>
                                    <select name="job_role">
                                        <?php
                                        $roles = ['Cleaning', 'Cooking', 'Babysitting', 'Elderly Care'];
                                        foreach ($roles as $role) {
                                            $selected = ($helper_role === $role) ? 'selected' : '';
                                            echo "<option value=\"$role\" $selected>$role</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label>Hourly Rate (₹)</label>
                                    <input type="number" name="hourly_rate"
                                        value="<?php echo htmlspecialchars($helper['hourly_rate'] ?? ''); ?>"
                                        placeholder="e.g. 500">
                                </div>
                                <div class="input-group">
                                    <label>Address</label>
                                    <textarea name="address"
                                        rows="2"><?php echo htmlspecialchars($helper['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="input-group">
                                    <label>Bio</label>
                                    <textarea name="bio" rows="3"
                                        placeholder="Describe your skills..."><?php echo htmlspecialchars($helper['bio'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                            <button type="submit" id="save-btn" class="btn btn-primary"
                                style="background: #10B981; color: white; padding: 0.8rem 2rem;">Save Changes</button>
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

            let targetId = sectionId;
            if (sectionId === 'dashboard') targetId = 'dashboard-section';
            else if (sectionId === 'requests') targetId = 'requests-section';
            else if (sectionId === 'jobs') targetId = 'jobs-section';
            else if (sectionId === 'profile') targetId = 'profile-section';

            document.getElementById(targetId).style.display = 'block';

            const indexMap = { 'dashboard': 0, 'requests': 1, 'jobs': 2, 'profile': 3 };
            document.querySelectorAll('.nav-item')[indexMap[sectionId]].classList.add('active');
        }
    </script>
</body>

</html>