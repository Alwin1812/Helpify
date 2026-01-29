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
    <style>
        .admin-container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 3rem;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-dark);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .btn-delete {
            background-color: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-delete:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body>
    <header>
        <div class="container flex justify-between items-center" style="height: 100%;">
            <a href="index.php" class="logo">Helpify Admin</a>
            <nav class="nav-links">
                <span style="margin-right: 1rem;">Welcome,
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>
                <a href="api/logout.php" class="btn btn-primary">Logout</a>
            </nav>
        </div>
    </header>

    <div class="admin-container">
        <!-- Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: var(--danger); color: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: var(--success); color: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label for="search" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-light);">Search</label>
                    <input type="text" name="search" id="search" placeholder="Search by name, email..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 0.75rem; border: 1px solid #E5E7EB; border-radius: 8px;">
                </div>
                <div style="width: 200px;">
                    <label for="role" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-light);">Role</label>
                    <select name="role" id="role" style="width: 100%; padding: 0.75rem; border: 1px solid #E5E7EB; border-radius: 8px;">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Users Only</option>
                        <option value="helper" <?php echo $role_filter === 'helper' ? 'selected' : ''; ?>>Helpers Only</option>
                    </select>
                </div>
                <div style="margin-bottom: 3px;">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <?php if($search || $role_filter !== 'all'): ?>
                        <a href="admin_dashboard.php" class="btn btn-outline" style="margin-left: 0.5rem;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($role_filter === 'all' || $role_filter === 'user'): ?>
        <!-- Users Section -->
        <div class="section-header">
            <h2>Manage Users</h2>
        </div>

        <?php if (count($users) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#
                                <?php echo $user['id']; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td>
                                <form action="api/admin_action.php" method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this user? All their bookings will also be deleted.');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-delete">
                                        <span class="material-icons" style="font-size: 18px;">delete</span> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No users found.</p>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($role_filter === 'all' || $role_filter === 'helper'): ?>
        <!-- Helpers Section -->
        <div class="section-header">
            <h2>Manage Helpers</h2>
        </div>

        <?php if (count($helpers) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Job Category</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($helpers as $helper): ?>
                        <tr>
                            <td>#
                                <?php echo $helper['id']; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($helper['name']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($helper['email']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($helper['job_role'] ?? 'Not Set'); ?>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($helper['created_at'])); ?>
                            </td>
                            <td>
                                <form action="api/admin_action.php" method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this helper? All their bookings will also be deleted.');">
                                    <input type="hidden" name="action" value="delete_helper">
                                    <input type="hidden" name="user_id" value="<?php echo $helper['id']; ?>">
                                    <button type="submit" class="btn-delete">
                                        <span class="material-icons" style="font-size: 18px;">delete</span> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No helpers found.</p>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</body>

</html>