<?php
session_start();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $name = trim($_POST['name']);
        $email = $_POST['email'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        if (!in_array($role, ['user', 'helper'])) {
            $_SESSION['error'] = 'Invalid role.';
            header('Location: ../register.php');
            exit;
        }
        $job_role = ($role === 'helper' && isset($_POST['job_role'])) ? $_POST['job_role'] : null;

        // Simple validation
        if (empty($name) || empty($email) || empty($password)) {
            $_SESSION['error'] = 'All fields are required.';
            header('Location: ../register.php');
            exit;
        }

        if (strlen($name) < 2) {
            $_SESSION['error'] = 'Name must be at least 2 characters long.';
            header('Location: ../register.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Invalid email address format.';
            header('Location: ../register.php');
            exit;
        }

        if (strlen($password) < 6) {
            $_SESSION['error'] = 'Password must be at least 6 characters long.';
            header('Location: ../register.php');
            exit;
        }

        if ($role === 'helper' && empty($job_role)) {
            $_SESSION['error'] = 'Please select a job category.';
            header('Location: ../register.php');
            exit;
        }

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already registered.';
            header('Location: ../register.php');
            exit;
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, job_role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password, $role, $job_role]);
            $user_id = $pdo->lastInsertId();

            $pdo->commit();

            // Auto Login
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;

            if ($role === 'helper') {
                header('Location: ../helper_setup.php');
            } else {
                header('Location: ../dashboard.php');
            }
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Registration failed. Please try again.';
            header('Location: ../register.php');
            exit;
        }

    } elseif ($action === 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email and password are required.';
            header('Location: ../login.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'helper') {
                header('Location: ../helper_dashboard.php');
            } elseif ($user['role'] === 'admin') {
                header('Location: ../admin_dashboard.php');
            } else {
                header('Location: ../dashboard.php');
            }
            exit;
        } else {
            $_SESSION['error'] = 'Invalid email or password.';
            header('Location: ../login.php');
            exit;
        }
    }
} else {
    header('Location: ../index.php');
    exit;
}
?>