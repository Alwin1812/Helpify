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
    </style>
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify</a>
            <div class="flex items-center gap-4">
                <span>Welcome, <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b></span>
                <a href="api/logout.php" class="btn btn-outline"
                    style="padding: 0.5rem 1rem; font-size: 0.9rem;">Logout</a>
            </div>
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

                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($services as $service): ?>
                        <div class="service-card" style="text-align: left;">
                            <div class="service-img-box">
                                <?php
                                $slug = strtolower($service['name']);
                                if (strpos($slug, 'elderly') !== false) {
                                    $img_filename = 'service-elderly.png';
                                } else {
                                    $img_filename = 'service-' . str_replace(' ', '-', $slug) . '.png';
                                }
                                ?>
                                <img src="assets/images/<?php echo $img_filename; ?>"
                                    alt="<?php echo htmlspecialchars($service['name']); ?>" class="service-img">
                            </div>

                            <h4 style="margin: 0; font-size: 1.25rem; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($service['name']); ?>
                            </h4>
                            <div style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">
                                From ₹<?php echo $service['base_price']; ?></div>

                            <button class="btn btn-primary btn-block"
                                onclick="openBookingModal('<?php echo $service['id']; ?>', '<?php echo htmlspecialchars($service['name']); ?>')">Book
                                Now</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Booking Modal -->
            <div id="bookingModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeBookingModal()">&times;</span>
                    <h2>Book <span id="modalServiceName"></span></h2>
                    <form action="api/booking_action.php" method="POST" style="margin-top: 1rem;">
                        <input type="hidden" name="action" value="book">
                        <input type="hidden" name="service_id" id="modalServiceId">

                        <div class="input-group">
                            <label>Date</label>
                            <input type="date" name="date" class="form-control" required
                                min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="input-group">
                            <label>Time</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                        <div class="input-group">
                            <label>Location</label>
                            <input type="text" name="location" class="form-control" placeholder="Your Address" required>
                        </div>
                        <div class="input-group">
                            <label>Budget (₹)</label>
                            <input type="number" name="budget" class="form-control" placeholder="e.g. 500">
                        </div>
                        <div class="input-group">
                            <label>Special Instructions</label>
                            <textarea name="instructions" class="form-control" rows="3"
                                placeholder="Any specific requirements..."></textarea>
                        </div>

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
                            <label
                                style="display: block; margin-bottom: 0.5rem; color: var(--text-light);">Address</label>
                            <textarea name="address" rows="2"
                                style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($user_details['address'] ?? ''); ?></textarea>
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

        function openBookingModal(serviceId, serviceName) {
            document.getElementById('modalServiceId').value = serviceId;
            document.getElementById('modalServiceName').innerText = serviceName;
            bookingModal.style.display = 'block';
        }

        function closeBookingModal() {
            bookingModal.style.display = 'none';
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
    </script>

</body>

</html>