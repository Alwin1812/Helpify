<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/google_config.php';

if (isset($_GET['code'])) {

    // 1. Exchange authorization code for access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'code' => $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'grant_type' => 'authorization_code'
    ];

    // Capture state (role) if present
    $role_state = isset($_GET['state']) ? $_GET['state'] : 'user';
    // Validate role
    if (!in_array($role_state, ['user', 'helper'])) {
        $role_state = 'user';
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Disable SSL verification for localhost development
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        $error_response = json_decode($response, true);
        $error_msg = isset($error_response['error_description']) ? $error_response['error_description'] : 'Unknown error';
        $_SESSION['error'] = 'Failed to get access token: ' . $error_msg;
        header('Location: ../login.php');
        exit;
    }

    $token_data = json_decode($response, true);
    $access_token = $token_data['access_token'];

    // 2. Get User Profile Info
    $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $user_info_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // See note above

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        $_SESSION['error'] = 'Failed to get user info from Google.';
        header('Location: ../login.php');
        exit;
    }

    $google_user = json_decode($response, true);

    $google_id = $google_user['id'];
    $email = $google_user['email'];
    $name = $google_user['name'];
    $picture = $google_user['picture'] ?? ''; // Can be used for profile pic

    // 3. Check if user exists in DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists
        if (empty($user['google_id'])) {
            // Link existing account to Google
            $update_stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $update_stmt->execute([$google_id, $user['id']]);
        }

        // Log in
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        if ($user['role'] === 'helper') {
            header('Location: ../helper_dashboard.php');
        } else {
            header('Location: ../dashboard.php');
        }
        exit;

    } else {
        // New user - Register them
        try {
            // Users via Google are standard users by default
            // Password is not applicable, but DB might require it if not null. 
            // We made it nullable, so we can pass NULL.

            $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $google_id, $role_state]);

            $new_user_id = $pdo->lastInsertId();

            // Requested flow: Redirect to login page after creation
            $_SESSION['success'] = 'Account created successfully! Please sign in with Google.';
            header('Location: ../login.php');
            exit;

        } catch (PDOException $e) {
            $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
            header('Location: ../login.php');
            exit;
        }
    }

} else {
    header('Location: ../login.php');
    exit;
}
?>