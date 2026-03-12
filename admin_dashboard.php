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

// Fetch Services for Job Roles
$stmt = $pdo->query("SELECT * FROM services ORDER BY name ASC");
$all_services = $stmt->fetchAll();

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
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM bookings b 
             WHERE b.helper_id = u.id 
             AND b.status = 'confirmed' 
             AND b.date = CURRENT_DATE()) as active_jobs
            FROM users u WHERE role = 'helper'";
    $params = [];
    if ($search) {
        $sql .= " AND (u.name LIKE ? OR email LIKE ? OR job_role LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $helpers = $stmt->fetchAll();
}

// --- Analytics Data (Fetch only for overview or specific analytics role) ---
$revenue_stats = [];
$service_usage_stats = [];
$booking_status_stats = [];
$total_platform_revenue = 0;

if ($role_filter === 'all' || $role_filter === 'analytics') {
    // 1. Monthly Revenue (Last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%b %Y') as month_label,
            SUM(total_amount) as monthly_revenue
        FROM bookings
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month_label
        ORDER BY MIN(created_at) ASC
    ");
    $revenue_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Services Popularity (By booking count)
    $stmt = $pdo->query("
        SELECT 
            s.name as service_name,
            COUNT(b.id) as booking_count,
            SUM(b.total_amount) as total_revenue
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        GROUP BY s.id
        ORDER BY booking_count DESC
        LIMIT 5
    ");
    $service_usage_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Booking Status Distribution
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM bookings 
        GROUP BY status
    ");
    $booking_status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Total Platform Revenue
    $total_platform_revenue = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE status != 'cancelled'")->fetchColumn() ?: 0;
}

// Fetch Bookings
$all_bookings = [];
if ($role_filter === 'bookings') {
    $sql = "SELECT b.*, u.name as user_name, h.name as helper_name, s.name as service_name 
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN services s ON b.service_id = s.id 
            LEFT JOIN users h ON b.helper_id = h.id 
            WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (u.name LIKE ? OR s.name LIKE ? OR b.id = ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = $search;
    }
    $sql .= " ORDER BY b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Payments / Transactions
$all_transactions = [];
if ($role_filter === 'payments') {
    $stmt = $pdo->query("
        SELECT wt.*, u.name as user_name, u.email as user_email 
        FROM wallet_transactions wt 
        JOIN users u ON wt.user_id = u.id 
        ORDER BY wt.created_at DESC 
        LIMIT 100
    ");
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Reviews
$all_reviews = [];
if ($role_filter === 'reviews') {
    $stmt = $pdo->query("
        SELECT r.*, u.name as user_name, s.name as service_name 
        FROM reviews r 
        JOIN users u ON r.reviewer_id = u.id 
        JOIN bookings b ON r.booking_id = b.id 
        JOIN services s ON b.service_id = s.id 
        ORDER BY r.created_at DESC
    ");
    $all_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Complaints
$complaints = [];
if ($role_filter === 'complaints') {
    // We join bookings and then join users AGAIN (as 'other_party') to find out who the other person in the job was.
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u.name as reporter_name, 
               u.role as reporter_role,
               s.name as service_name,
               other_u.name as subject_name,
               other_u.role as subject_role
        FROM complaints c 
        JOIN users u ON c.reporter_id = u.id 
        LEFT JOIN bookings b ON c.booking_id = b.id 
        LEFT JOIN services s ON b.service_id = s.id
        LEFT JOIN users other_u ON (
            (u.role = 'user' AND b.helper_id = other_u.id) OR 
            (u.role = 'helper' AND b.user_id = other_u.id)
        )
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Complaint Chat Styles */
        .chat-modal-overlay {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 380px;
            height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 1000;
            border: 1px solid #E5E7EB;
        }

        .chat-header {
            padding: 1rem;
            background: #2563EB;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header h4 {
            margin: 0;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background: #F9FAFB;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message-bubble {
            max-width: 80%;
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 0.9rem;
            line-height: 1.4;
            position: relative;
        }

        .received {
            background: white;
            border: 1px solid #E5E7EB;
            align-self: flex-start;
            color: #374151;
        }

        .sent {
            background: #2563EB;
            color: white;
            align-self: flex-end;
        }

        .message-time {
            display: block;
            font-size: 0.7rem;
            margin-top: 4px;
            opacity: 0.7;
        }

        .chat-input-area {
            padding: 1rem;
            border-top: 1px solid #E5E7EB;
            display: flex;
            gap: 8px;
            background: white;
        }

        .chat-input-area input {
            flex: 1;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 0.5rem;
        }

        .chat-send-btn {
            background: #2563EB;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .close-chat {
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .close-chat:hover {
            opacity: 1;
        }

        @media (max-width: 1024px) {
            .sidebar {
                left: -260px;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .menu-btn {
                display: block !important;
            }

            #sidebarOverlay.active {
                display: block;
            }
        }

        #sidebarOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 45;
            backdrop-filter: blur(2px);
        }
    </style>
</head>

<body>
    <div id="sidebarOverlay" onclick="toggleSidebar()"></div>

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
            <a href="admin_dashboard.php?role=analytics"
                class="nav-link <?php echo $role_filter === 'analytics' ? 'active' : ''; ?>">
                <span class="material-icons">bar_chart</span> Financial Analytics
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
            <a href="admin_dashboard.php?role=complaints"
                class="nav-link <?php echo $role_filter === 'complaints' ? 'active' : ''; ?>">
                <span class="material-icons">report_problem</span> Complaints
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
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button id="backBtn" class="material-icons"
                    style="display: none; background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #111827; padding: 5px;"
                    onclick="window.location.href='admin_dashboard.php?role=all'">arrow_back</button>
                <button id="menuBtn" class="material-icons menu-btn"
                    style="display: none; background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #111827; padding:5px;">menu</button>
                <h2 style="font-size: 1.25rem;">Overview</h2>
            </div>
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
                <div class="stat-card">
                    <span class="stat-value">₹<?php echo number_format($total_platform_revenue, 2); ?></span>
                    <span class="stat-label">Total Revenue</span>
                </div>
            </div>

            <?php if ($role_filter === 'all' || $role_filter === 'analytics'): ?>
                <!-- Analytics Section -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- Revenue Chart -->
                    <div class="content-card" style="padding: 1.5rem;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">Revenue Overview (Last 6 Months)</h3>
                        <canvas id="revenueChart" height="150"></canvas>
                    </div>
                    <!-- Booking Status Pie -->
                    <div class="content-card" style="padding: 1.5rem;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">Booking Status</h3>
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- Top Services -->
                    <div class="content-card" style="padding: 1.5rem;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">Most Popular Services</h3>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Bookings</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($service_usage_stats as $stat): ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?php echo htmlspecialchars($stat['service_name']); ?>
                                            </td>
                                            <td><?php echo $stat['booking_count']; ?></td>
                                            <td>₹<?php echo number_format($stat['total_revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Additional Card: Service Usage Breakdown (Doughnut) -->
                    <div class="content-card" style="padding: 1.5rem;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">Service Distribution</h3>
                        <canvas id="serviceChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>

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
                        style="padding: 0.5rem 1rem; font-size: 0.9rem;">Filter
                        Results</button>
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
                        <div>
                            <span class="status-badge badge-purple"
                                style="margin-right: 1rem;"><?php echo count($helpers); ?>
                                Helpers</span>
                            <button type="button" class="btn btn-primary" onclick="openAddHelperModal()"
                                style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                                <span class="material-icons"
                                    style="font-size: 16px; vertical-align: middle; margin-right: 4px;">add</span> Add
                                Helper
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Helper Info</th>
                                    <th>Job Role</th>
                                    <th>Contact</th>
                                    <th>Status At Work</th>
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
                                            <td>
                                                <?php if ($helper['active_jobs'] > 0): ?>
                                                    <span class="status-badge" style="background: #FEF3C7; color: #92400E;">
                                                        <span class="material-icons"
                                                            style="font-size: 14px; margin-right: 4px;">work</span> NOT AVAILABLE
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge" style="background: #D1FAE5; color: #065F46;">
                                                        <span class="material-icons"
                                                            style="font-size: 14px; margin-right: 4px;">check_circle</span> AVAILABLE
                                                    </span>
                                                <?php endif; ?>
                                            </td>
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
                                        <td colspan="6" style="text-align: center; color: var(--text-light);">No helpers found
                                            matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role_filter === 'complaints'): ?>
                <!-- Complaints Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 style="font-size: 1.1rem; margin: 0;">Reported Issues & Complaints</h3>
                        <span class="status-badge badge-red"><?php echo count($complaints); ?> Active</span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Reporter</th>
                                    <th>Service/Job</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($complaints) > 0): ?>
                                    <?php foreach ($complaints as $c): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--primary-color);">
                                                    <?php echo htmlspecialchars($c['reporter_name']); ?>
                                                </div>
                                                <div
                                                    style="font-size: 0.75rem; color: #6B7280; font-weight: 500; text-transform: uppercase;">
                                                    <?php echo $c['reporter_role']; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?php echo htmlspecialchars($c['service_name'] ?: 'General'); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #6B7280;">
                                                    Job #<?php echo $c['booking_id']; ?>
                                                    <?php if ($c['subject_name']): ?>
                                                        • Against: <strong><?php echo htmlspecialchars($c['subject_name']); ?></strong>
                                                        (<?php echo $c['subject_role']; ?>)
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="max-width: 300px;">
                                                <div style="font-size: 0.9rem; color: #4B5563;">
                                                    <?php echo htmlspecialchars($c['description']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge"
                                                    style="background: <?php echo $c['status'] === 'resolved' ? '#D1FAE5; color: #065F46;' : ($c['status'] === 'pending' ? '#FEF3C7; color: #92400E;' : '#F3F4F6; color: #374151;'); ?>">
                                                    <?php echo ucfirst($c['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                                            <td style="text-align: right;">
                                                <button class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;"
                                                    onclick="openAdminComplaintChat(<?php echo $c['id']; ?>, '<?php echo addslashes($c['reporter_name']); ?>')">
                                                    Manage & Chat
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 3rem; color: #9CA3AF;">No complaints
                                            found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role_filter === 'bookings'): ?>
                <!-- Bookings Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 style="font-size: 1.1rem; margin: 0;">Service Bookings</h3>
                        <span class="status-badge badge-blue"><?php echo count($all_bookings); ?> Bookings</span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ref / Service</th>
                                    <th>Customer</th>
                                    <th>Helper</th>
                                    <th>Schedule</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($all_bookings) > 0): ?>
                                    <?php foreach ($all_bookings as $b): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 700; color: #111827;">#<?php echo $b['id']; ?></div>
                                                <div style="font-size: 0.85rem; color: #6B7280;">
                                                    <?php echo htmlspecialchars($b['service_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($b['user_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    style="color: <?php echo $b['helper_name'] ? '#2563EB' : '#94A3B8'; ?>; font-weight: 500;">
                                                    <?php echo htmlspecialchars($b['helper_name'] ?: 'Not Assigned'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.9rem;"><?php echo date('d M Y', strtotime($b['date'])); ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #9CA3AF;"><?php echo $b['time']; ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 700;">₹<?php echo number_format($b['total_amount'], 2); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge" style="
                                                    background: <?php echo $b['status'] === 'completed' ? '#D1FAE5' : ($b['status'] === 'cancelled' ? '#FEE2E2' : '#EFF6FF'); ?>;
                                                    color: <?php echo $b['status'] === 'completed' ? '#065F46' : ($b['status'] === 'cancelled' ? '#991B1B' : '#1E40AF'); ?>;
                                                ">
                                                    <?php echo strtoupper($b['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge" style="
                                                    background: <?php echo $b['payment_status'] === 'paid' ? '#D1FAE5' : '#FEF3C7'; ?>;
                                                    color: <?php echo $b['payment_status'] === 'paid' ? '#065F46' : '#92400E'; ?>;
                                                ">
                                                    <?php echo strtoupper($b['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: #9CA3AF;">No bookings
                                            found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role_filter === 'payments'): ?>
                <!-- Transactions Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 style="font-size: 1.1rem; margin: 0;">Wallet Transactions</h3>
                        <span class="status-badge badge-blue">Recent History</span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($all_transactions) > 0): ?>
                                    <?php foreach ($all_transactions as $tx): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($tx['user_name']); ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #6B7280;"><?php echo $tx['user_email']; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($tx['description']); ?></td>
                                            <td>
                                                <div
                                                    style="font-weight: 700; color: <?php echo $tx['type'] === 'credit' ? '#10B981' : '#EF4444'; ?>;">
                                                    <?php echo $tx['type'] === 'credit' ? '+' : '-'; ?>
                                                    ₹<?php echo number_format($tx['amount'], 2); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="status-badge <?php echo $tx['type'] === 'credit' ? 'badge-blue' : 'badge-purple'; ?>">
                                                    <?php echo strtoupper($tx['type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code
                                                    style="font-size: 0.75rem; color: #6366F1;"><?php echo $tx['razorpay_payment_id'] ?: 'Internal'; ?></code>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.9rem;">
                                                    <?php echo date('d M Y', strtotime($tx['created_at'])); ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #9CA3AF;">
                                                    <?php echo date('h:i A', strtotime($tx['created_at'])); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 3rem; color: #9CA3AF;">No
                                            transactions recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role_filter === 'reviews'): ?>
                <!-- Reviews Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 style="font-size: 1.1rem; margin: 0;">Service Reviews</h3>
                        <span class="status-badge badge-blue"><?php echo count($all_reviews); ?> Total</span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Customer</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($all_reviews) > 0): ?>
                                    <?php foreach ($all_reviews as $r): ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?php echo htmlspecialchars($r['service_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['user_name']); ?></td>
                                            <td>
                                                <div
                                                    style="color: #F59E0B; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                                    <span class="material-icons" style="font-size: 16px;">star</span>
                                                    <?php echo $r['rating']; ?>.0
                                                </div>
                                            </td>
                                            <td style="max-width: 400px;"><?php echo htmlspecialchars($r['comment']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 3rem; color: #9CA3AF;">No reviews
                                            yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role_filter === 'settings'): ?>
                <!-- Settings Section -->
                <div class="content-card" style="padding: 2rem;">
                    <h3 style="margin-bottom: 2rem;">System Settings</h3>
                    <form action="api/admin_action.php" method="POST" style="max-width: 600px;">
                        <input type="hidden" name="action" value="update_settings">

                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Business Name</label>
                            <input type="text" name="business_name" value="Helpify" class="btn-filter"
                                style="width: 100%; padding: 0.8rem;">
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Admin Email</label>
                            <input type="email" name="admin_email" value="admin@helpify.com" class="btn-filter"
                                style="width: 100%; padding: 0.8rem;">
                        </div>

                        <div
                            style="margin-bottom: 2rem; padding: 1rem; background: #FFF7ED; border-radius: 8px; border: 1px solid #FFEDD5;">
                            <h4
                                style="color: #9A3412; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                                <span class="material-icons" style="font-size: 18px;">key</span> Razorpay Integration
                            </h4>
                            <p style="font-size: 0.85rem; color: #9A3412; margin-bottom: 1rem;">API keys are currently
                                managed in <code>api/config_razorpay.php</code> for security.</p>
                            <button type="button" class="btn btn-outline" style="font-size: 0.8rem;">Rotate API
                                Keys</button>
                        </div>

                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">Save
                            Configurations</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!in_array($role_filter, ['all', 'user', 'helper', 'complaints', 'bookings', 'payments', 'reviews', 'settings'])): ?>
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

        <!-- Add Helper Modal -->
        <div id="addHelperModal"
            style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
            <div
                style="background: white; width: 90%; max-width: 600px; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative; max-height: 90vh; overflow-y: auto;">
                <span onclick="closeAddHelperModal()"
                    style="position: absolute; top: 1rem; right: 1rem; cursor: pointer; font-size: 24px; color: #666;">&times;</span>

                <h2 style="margin-bottom: 1.5rem; color: var(--primary-color);">Create New Helper</h2>

                <form action="api/admin_action.php" method="POST">
                    <input type="hidden" name="action" value="add_helper">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Full Name</label>
                            <input type="text" name="name" required
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Email</label>
                            <input type="email" name="email" required
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Password</label>
                            <input type="password" name="password" required minlength="6"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Phone Number</label>
                            <input type="text" id="add_phone" name="phone_number"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;"
                                pattern="[0-9]{10}" maxlength="10">
                            <small id="add_phone_error"
                                style="color: #EF4444; font-size: 0.75rem; display: none; margin-top: 0.25rem;">Enter a
                                10-digit number</small>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Gender</label>
                            <select name="gender"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <input type="hidden" name="hourly_rate" id="add_hourly_rate">

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Job Role</label>
                            <select name="job_role" id="add_job_role" onchange="updateRate('add')"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;"
                                required>
                                <option value="">Select Role</option>
                                <?php foreach ($all_services as $svc): ?>
                                    <option value="<?php echo htmlspecialchars($svc['name']); ?>"
                                        data-price="<?php echo htmlspecialchars($svc['base_price']); ?>">
                                        <?php echo htmlspecialchars($svc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 1rem;">
                        <button type="button" onclick="closeAddHelperModal()"
                            style="background: #f3f4f6; color: #333; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; margin-right: 0.5rem; cursor: pointer;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">Create
                            Helper</button>
                    </div>
                </form>
            </div>
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
                            <small id="edit_phone_error"
                                style="color: #EF4444; font-size: 0.75rem; display: none; margin-top: 0.25rem;">Enter a
                                10-digit number</small>
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

                        <input type="hidden" name="hourly_rate" id="edit_rate">

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Job Role</label>
                            <select name="job_role" id="edit_role" onchange="updateRate('edit')"
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">Select Role</option>
                                <?php foreach ($all_services as $svc): ?>
                                    <option value="<?php echo htmlspecialchars($svc['name']); ?>"
                                        data-price="<?php echo htmlspecialchars($svc['base_price']); ?>">
                                        <?php echo htmlspecialchars($svc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
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
            function openAddHelperModal() {
                document.getElementById('addHelperModal').style.display = 'flex';
            }

            function closeAddHelperModal() {
                document.getElementById('addHelperModal').style.display = 'none';
            }

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

            window.onclick = function (event) {
                const editModal = document.getElementById('editHelperModal');
                const addModal = document.getElementById('addHelperModal');
                if (event.target == editModal) {
                    editModal.style.display = "none";
                }
                if (event.target == addModal) {
                    addModal.style.display = "none";
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

            function updateRate(mode) {
                let selectElem, rateInput;
                if (mode === 'add') {
                    selectElem = document.getElementById('add_job_role');
                    rateInput = document.getElementById('add_hourly_rate');
                } else {
                    selectElem = document.getElementById('edit_role');
                    rateInput = document.getElementById('edit_rate');
                }

                if (selectElem && rateInput) {
                    const selectedOption = selectElem.options[selectElem.selectedIndex];
                    const basePrice = selectedOption.getAttribute('data-price');
                    if (basePrice) {
                        rateInput.value = basePrice;
                    }
                }
            }
            function setupPhoneValidation(inputId, errorId) {
                const input = document.getElementById(inputId);
                const error = document.getElementById(errorId);
                if (input && error) {
                    input.addEventListener('input', function () {
                        this.value = this.value.replace(/\D/g, '');
                        if (this.value === '') {
                            this.style.borderColor = '#ddd';
                            error.style.display = 'none';
                        } else {
                            const isValidPrefix = /^[6-9]/.test(this.value);
                            const isCorrectLength = this.value.length === 10;

                            if (isValidPrefix && isCorrectLength) {
                                this.style.borderColor = '#10B981';
                                error.style.display = 'none';
                            } else {
                                this.style.borderColor = '#EF4444';
                                error.style.display = 'block';
                                if (!isValidPrefix) {
                                    error.textContent = "Start with 6, 7, 8, or 9";
                                } else {
                                    error.textContent = "Enter a 10-digit number";
                                }
                            }
                        }
                    });
                }
            }

            setupPhoneValidation('add_phone', 'add_phone_error');
            setupPhoneValidation('edit_phone', 'edit_phone_error');

            // Intercept form submissions
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    const phoneInput = this.querySelector('input[name="phone_number"]');
                    if (phoneInput && phoneInput.value.length > 0) {
                        const isValidPrefix = /^[6-9]/.test(phoneInput.value);
                        const isCorrectLength = phoneInput.value.length === 10;
                        if (!isValidPrefix || !isCorrectLength) {
                            e.preventDefault();
                            alert("Please enter a valid 10-digit phone number starting with 6-9.");
                        }
                    }
                });
            });
        </script>

        <script>
            // Analytics Charts
            document.addEventListener('DOMContentLoaded', function () {
                <?php if ($role_filter === 'all' || $role_filter === 'analytics'): ?>
                    // 1. Revenue Chart
                    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
                    new Chart(revenueCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode(array_column($revenue_stats, 'month_label')); ?>,
                            datasets: [{
                                label: 'Revenue (₹)',
                                data: <?php echo json_encode(array_column($revenue_stats, 'monthly_revenue')); ?>,
                                borderColor: '#2563EB',
                                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });

                    // 2. Status Chart
                    const statusCtx = document.getElementById('statusChart').getContext('2d');
                    new Chart(statusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_column($booking_status_stats, 'status')); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_column($booking_status_stats, 'count')); ?>,
                                backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#6366F1']
                            }]
                        },
                        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                    });

                    // 3. Service Chart
                    const serviceCtx = document.getElementById('serviceChart').getContext('2d');
                    new Chart(serviceCtx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode(array_column($service_usage_stats, 'service_name')); ?>,
                            datasets: [{
                                label: 'Bookings',
                                data: <?php echo json_encode(array_column($service_usage_stats, 'booking_count')); ?>,
                                backgroundColor: '#6366F1'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                <?php endif; ?>
            });
        </script>
    </main>
    <!-- Admin Complaint Chat Overlay -->
    <div id="adminComplaintChatOverlay" class="chat-modal-overlay">
        <div class="chat-header">
            <h4><span class="material-icons">support_agent</span> Chat with <span id="chatReporterName"></span></h4>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button onclick="resolveComplaint()"
                    style="background: #10B981; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">Resolve</button>
                <span class="material-icons close-chat" onclick="closeAdminComplaintChat()">close</span>
            </div>
        </div>
        <div id="adminComplaintMessages" class="chat-messages"></div>
        <div class="chat-input-area">
            <input type="text" id="adminComplaintInput" placeholder="Reply to user...">
            <button class="chat-send-btn" onclick="sendAdminComplaintMessage()">
                <span class="material-icons">send</span>
            </button>
        </div>
    </div>

    <script>
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;
        let activeComplaintId = null;
        let adminComplaintPolling = null;

        function openAdminComplaintChat(complaintId, reporterName) {
            activeComplaintId = complaintId;
            document.getElementById('chatReporterName').textContent = reporterName;
            document.getElementById('adminComplaintChatOverlay').style.display = 'flex';
            fetchAdminComplaintMessages();
            if (adminComplaintPolling) clearInterval(adminComplaintPolling);
            adminComplaintPolling = setInterval(fetchAdminComplaintMessages, 3000);
        }

        function closeAdminComplaintChat() {
            document.getElementById('adminComplaintChatOverlay').style.display = 'none';
            if (adminComplaintPolling) clearInterval(adminComplaintPolling);
        }

        function fetchAdminComplaintMessages() {
            if (!activeComplaintId) return;
            fetch(`api/complaint_action.php?action=fetch_messages&complaint_id=${activeComplaintId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('adminComplaintMessages');
                        container.innerHTML = '';
                        data.messages.forEach(msg => {
                            const div = document.createElement('div');
                            const isSent = msg.sender_role === 'admin';
                            div.className = `message-bubble ${isSent ? 'sent' : 'received'}`;

                            div.innerHTML = `
                                <div style="font-size: 0.7rem; font-weight: 700; margin-bottom: 2px;">${isSent ? 'Me (Admin)' : msg.sender_name}</div>
                                ${msg.message}
                                <span class="message-time">${new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                            `;
                            container.appendChild(div);
                        });
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }

        function sendAdminComplaintMessage() {
            const input = document.getElementById('adminComplaintInput');
            const message = input.value.trim();
            if (!message || !activeComplaintId) return;

            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('complaint_id', activeComplaintId);
            fd.append('message', message);

            input.value = '';
            fetch('api/complaint_action.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) fetchAdminComplaintMessages();
                });
        }

        function resolveComplaint() {
            if (!confirm('Mark this complaint as resolved?')) return;
            const fd = new FormData();
            fd.append('action', 'resolve');
            fd.append('complaint_id', activeComplaintId);

            fetch('api/complaint_action.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        closeAdminComplaintChat();
                        location.reload();
                    }
                });
        }

        document.getElementById('adminComplaintInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendAdminComplaintMessage();
        });

        function toggleSidebar(force) {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (force === undefined) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            } else if (force) {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            } else {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        }

        document.getElementById('menuBtn').addEventListener('click', () => toggleSidebar());

        // Handle back button on mobile
        window.addEventListener('DOMContentLoaded', () => {
            if (window.innerWidth <= 1024) {
                const role = '<?php echo $role_filter; ?>';
                const backBtn = document.getElementById('backBtn');
                const menuBtn = document.getElementById('menuBtn');
                if (role !== 'all' && role !== '') {
                    backBtn.style.display = 'block';
                    menuBtn.style.display = 'none';
                } else {
                    backBtn.style.display = 'none';
                    menuBtn.style.display = 'block';
                }
            }
        });
    </script>
</body>

</html>