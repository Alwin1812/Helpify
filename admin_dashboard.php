<?php
session_start();
require_once 'includes/db_connect.php';

// Check for admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize filter variables
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? 'all';

// Stats Queries
$total_users_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$total_helpers_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role='helper'")->fetchColumn();
$total_bookings_count = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

// Fetch Users
$users = [];
if ($role_filter === 'all' || $role_filter === 'user') {
    $sql = "SELECT * FROM users WHERE role = 'user'";
    $params = [];
    if ($search) {
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
}

// Fetch Helpers
$helpers = [];
if ($role_filter === 'all' || $role_filter === 'helper') {
    $sql = "SELECT * FROM users WHERE role = 'helper'";
    $params = [];
    if ($search) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR job_role LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $helpers = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Helpify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 70px;
        }

        body {
            background-color: #F9FAFB;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: #FFFFFF;
            border-right: 1px solid #F3F4F6;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            z-index: 50;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.02);
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid #F3F4F6;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 900;
            color: #111827;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: -0.5px;
        }

        .nav-menu {
            flex: 1;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            overflow-y: auto;
        }

        .nav-section-title {
            font-size: 0.7rem;
            font-weight: 700;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin: 1.5rem 0 0.5rem 1rem;
        }

        .nav-section-title:first-child {
            margin-top: 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            color: #4B5563;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            background-color: #F9FAFB;
            color: #111827;
            transform: translateX(4px);
        }

        .nav-link.active {
            background-color: #EFF6FF;
            color: #2563EB;
        }

        .nav-link .material-icons {
            font-size: 22px;
            transition: color 0.2s ease;
        }

        .nav-link.active .material-icons {
            color: #2563EB;
        }

        .user-profile-mini {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid #F3F4F6;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #FAFAFA;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .top-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .page-content {
            padding: 2rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Tables & Filters */
        .content-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #F3F4F6;
        }

        .data-table th {
            background: #F9FAFB;
            font-weight: 600;
            color: var(--text-light);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-blue {
            background: #EFF6FF;
            color: #1D4ED8;
        }

        .badge-purple {
            background: #F5F3FF;
            color: #7C3AED;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.2s;
        }

        .action-btn:hover {
            color: var(--danger);
        }

        /* Search Input Style */
        .search-group input {
            padding: 0.5rem 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 250px;
        }

        .btn-filter {
            padding: 0.5rem 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            background: white;
            font-size: 0.9rem;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo-text">
                <span class="material-icons" style="font-size: 28px; color: #2563EB;">admin_panel_settings</span>
                Helpify
            </a>
        </div>

        <nav class="nav-menu">
            <div class="nav-section-title">Main Menu</div>
            <a href="admin_dashboard.php?role=all"
                class="nav-link <?php echo $role_filter === 'all' ? 'active' : ''; ?>">
                <span class="material-icons">dashboard</span> Dashboard Overview
            </a>

            <div class="nav-section-title">User Management</div>
            <a href="admin_dashboard.php?role=user"
                class="nav-link <?php echo $role_filter === 'user' ? 'active' : ''; ?>">
                <span class="material-icons">people</span> Manage Users
            </a>
            <a href="admin_dashboard.php?role=helper"
                class="nav-link <?php echo $role_filter === 'helper' ? 'active' : ''; ?>">
                <span class="material-icons">engineering</span> Manage Helpers
            </a>

            <div class="nav-section-title">Operations</div>
            <a href="admin_dashboard.php?role=bookings"
                class="nav-link <?php echo $role_filter === 'bookings' ? 'active' : ''; ?>">
                <span class="material-icons">book_online</span> All Bookings
            </a>
            <a href="admin_dashboard.php?role=reviews"
                class="nav-link <?php echo $role_filter === 'reviews' ? 'active' : ''; ?>">
                <span class="material-icons">star_rate</span> Service Reviews
            </a>
            <a href="admin_dashboard.php?role=payments"
                class="nav-link <?php echo $role_filter === 'payments' ? 'active' : ''; ?>">
                <span class="material-icons">payments</span> Transactions
            </a>

            <div class="nav-section-title">System</div>
            <a href="admin_dashboard.php?role=settings"
                class="nav-link <?php echo $role_filter === 'settings' ? 'active' : ''; ?>">
                <span class="material-icons">settings</span> Settings
            </a>
        </nav>

        <div class="user-profile-mini">
            <div
                style="width: 36px; height: 36px; background: #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                <span class="material-icons" style="font-size: 20px;">admin_panel_settings</span>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.95rem; font-weight: 700; color: #111827;">
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <div style="font-size: 0.8rem; color: #6B7280;">Super Admin</div>
            </div>
            <a href="api/logout.php" style="color: #EF4444; transition: transform 0.2s;"
                onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"><span
                    class="material-icons">logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <h2 style="font-size: 1.25rem;">Overview</h2>
            <!-- Additional header actions if needed -->
        </header>

        <div class="page-content">
            <!-- Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div
                    style="background: #FEF2F2; color: #B91C1C; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #FECACA;">
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div
                    style="background: #ECFDF5; color: #047857; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #A7F3D0;">
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?php echo $total_users_count; ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $total_helpers_count; ?></span>
                    <span class="stat-label">Verified Helpers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $total_bookings_count; ?></span>
                    <span class="stat-label">Total Bookings</span>
                </div>
                <div class="stat-card" style="background: #F0FDFA; border-color: #CCFBF1;">
                    <span class="stat-value" style="color: #0F766E;">Active</span>
                    <span class="stat-label" style="color: #0F766E;">System Status</span>
                </div>
            </div>

            <!-- Enhanced Filter Bar -->
            <div class="content-card" style="margin-bottom: 1.5rem; padding: 1rem;">
                <form method="GET" action="" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <div class="search-group" style="flex: 1;">
                        <input type="text" name="search" placeholder="Search by name, email or job role..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select name="role" class="btn-filter">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Users Only</option>
                        <option value="helper" <?php echo $role_filter === 'helper' ? 'selected' : ''; ?>>Helpers Only
                        </option>
                    </select>
                    <button type="submit" class="btn btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 0.9rem;">Filter Results</button>
                    <?php if ($search || $role_filter !== 'all'): ?>
                        <a href="admin_dashboard.php" class="btn btn-outline"
                            style="padding: 0.5rem 1rem; font-size: 0.9rem;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($role_filter === 'all' || $role_filter === 'user'): ?>
                <!-- Users Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 style="font-size: 1.1rem; margin: 0;">Registered Users</h3>
                        <span class="status-badge badge-blue"><?php echo count($users); ?> Users</span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User Info</th>
                                    <th>Contact</th>
                                    <th>Joined Date</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0):
                                    foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--primary-color);">
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: var(--text-light);">ID:
                                                    #<?php echo $user['id']; ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td style="text-align: right;">
                                                <form action="api/admin_action.php" method="POST"
                                                    onsubmit="return confirm('Delete this user?');" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="action-btn" title="Delete User">
                                                        <span class="material-icons">delete_outline</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-light);">No users found
                                            matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role_filter === 'all' || $role_filter === 'helper'): ?>
                <!-- Helpers Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 style="font-size: 1.1rem; margin: 0;">Service Providers (Helpers)</h3>
                        <span class="status-badge badge-purple"><?php echo count($helpers); ?> Helpers</span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Helper Info</th>
                                    <th>Job Role</th>
                                    <th>Contact</th>
                                    <th>Joined</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($helpers) > 0):
                                    foreach ($helpers as $helper): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--primary-color);">
                                                    <?php echo htmlspecialchars($helper['name']); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: var(--text-light);">ID:
                                                    #<?php echo $helper['id']; ?></div>
                                            </td>
                                            <td>
                                                <span
                                                    class="status-badge badge-purple"><?php echo htmlspecialchars($helper['job_role'] ?? 'General'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($helper['email']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($helper['created_at'])); ?></td>
                                            <td style="text-align: right;">
                                                <button type="button" class="action-btn" title="Edit Helper"
                                                    onclick='openEditModal(<?php echo json_encode($helper, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                    style="margin-right: 0.5rem; color: var(--primary-color);">
                                                    <span class="material-icons">edit</span>
                                                </button>
                                                <form action="api/admin_action.php" method="POST"
                                                    onsubmit="return confirm('Delete this helper?');" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_helper">
                                                    <input type="hidden" name="user_id" value="<?php echo $helper['id']; ?>">
                                                    <button type="submit" class="action-btn" title="Delete Helper">
                                                        <span class="material-icons">delete_outline</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-light);">No helpers found
                                            matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!in_array($role_filter, ['all', 'user', 'helper'])): ?>
                <div class="content-card"
                    style="padding: 4rem 2rem; text-align: center; border: 1px dashed #D1D5DB; background: #F9FAFB;">
                    <span class="material-icons"
                        style="font-size: 64px; color: #9CA3AF; margin-bottom: 1rem;">construction</span>
                    <h3 style="font-size: 1.5rem; color: #111827; margin-bottom: 0.5rem; font-weight: 700;">Module in
                        Development</h3>
                    <p style="color: #6B7280; max-width: 400px; margin: 0 auto; line-height: 1.5;">
                        The <strong style="color: #4B5563; text-transform: capitalize;">
                            <?php echo htmlspecialchars($role_filter); ?>
                        </strong> module is currently being built and will be available in the next system update.
                    </p>
                </div>
            <?php endif; ?>

        </div>

        <!-- Edit Helper Modal -->
        <div id="editHelperModal"
            style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
            <div
                style="background: white; width: 90%; max-width: 600px; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative; max-height: 90vh; overflow-y: auto;">
                <span onclick="document.getElementById('editHelperModal').style.display='none'"
                    style="position: absolute; top: 1rem; right: 1rem; cursor: pointer; font-size: 24px; color: #666;">&times;</span>

                <h2 style="margin-bottom: 1.5rem; color: var(--primary-color);">Edit Helper Details</h2>

                <form action="api/admin_action.php" method="POST">
                    <input type="hidden" name="action" value="update_helper">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Full Name</label>
                            <input type="text" name="name" id="edit_name" required
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Phone Number</label>
                            <input type="text" name="phone_number" id="edit_phone"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;"
                                pattern="[0-9]{10}" maxlength="10">
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Gender</label>
                            <select name="gender" id="edit_gender"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Hourly Rate
                                (₹)</label>
                            <input type="number" name="hourly_rate" id="edit_rate"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Job Role</label>
                            <select name="job_role" id="edit_role"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">Select Role</option>
                                <option value="Cleaning">Cleaning</option>
                                <option value="Cooking">Cooking</option>
                                <option value="Babysitting">Babysitting</option>
                                <option value="Elderly Care">Elderly Care</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Electrical">Electrical</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label style="margin: 0; font-weight: 500;">Address</label>
                            <button type="button" class="btn btn-outline"
                                style="padding: 0.2rem 0.5rem; font-size: 0.7rem; display: flex; align-items: center; gap: 4px;"
                                onclick="getAdminEditLocation()">
                                <span class="material-icons" style="font-size: 12px;">my_location</span> Use Current
                                Location
                            </button>
                        </div>
                        <textarea name="address" id="edit_address" rows="2"
                            style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;"></textarea>
                        <div id="adminEditMap"
                            style="height: 150px; width: 100%; border-radius: 6px; margin-top: 0.5rem; border: 1px solid #ddd; display: none;">
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Bio</label>
                        <textarea name="bio" id="edit_bio" rows="3"
                            style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;"></textarea>
                    </div>

                    <div style="text-align: right;">
                        <button type="button" onclick="document.getElementById('editHelperModal').style.display='none'"
                            style="background: #f3f4f6; color: #333; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; margin-right: 0.5rem; cursor: pointer;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">Update
                            Helper</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openEditModal(helper) {
                document.getElementById('edit_user_id').value = helper.id || '';
                document.getElementById('edit_name').value = helper.name || '';
                document.getElementById('edit_phone').value = helper.phone_number || '';
                document.getElementById('edit_gender').value = helper.gender || '';
                document.getElementById('edit_rate').value = helper.hourly_rate || '';
                document.getElementById('edit_role').value = helper.job_role || '';
                document.getElementById('edit_address').value = helper.address || '';
                document.getElementById('edit_bio').value = helper.bio || '';

                document.getElementById('editHelperModal').style.display = 'flex';
            }

            // Close modal when clicking outside
            window.onclick = function (event) {
                const modal = document.getElementById('editHelperModal');
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }

            // Admin Edit Map & Location
            let adminEditMap, adminEditMarker;
            function initAdminEditMap() {
                if (adminEditMap) return;
                document.getElementById('adminEditMap').style.display = 'block';
                adminEditMap = L.map('adminEditMap').setView([20.5937, 78.9629], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(adminEditMap);
                adminEditMarker = L.marker([20.5937, 78.9629], { draggable: true }).addTo(adminEditMap);

                adminEditMarker.on('dragend', function () {
                    const latlng = adminEditMarker.getLatLng();
                    reverseGeocodeToAdmin(latlng.lat, latlng.lng);
                });
            }

            function getAdminEditLocation() {
                initAdminEditMap();
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition((position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        adminEditMap.setView([lat, lng], 16);
                        adminEditMarker.setLatLng([lat, lng]);
                        reverseGeocodeToAdmin(lat, lng);
                    });
                }
            }

            function reverseGeocodeToAdmin(lat, lng) {
                fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.display_name) {
                            document.getElementById('edit_address').value = data.display_name;
                        }
                    });
            }
        </script>
    </main>
</body>

</html>