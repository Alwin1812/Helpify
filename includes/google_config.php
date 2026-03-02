<?php
// Google API Configuration

// Attempt to get from environment variables
$client_id = getenv('GOOGLE_CLIENT_ID');
$client_secret = getenv('GOOGLE_CLIENT_SECRET');

// Fallback to hardcoded values if not found in environment (Development)
if (!$client_id) {
    $client_id = 'YOUR_GOOGLE_CLIENT_ID'; // Replace via environment variables
}

if (!$client_secret) {
    $client_secret = 'YOUR_GOOGLE_CLIENT_SECRET'; // Replace via environment variables
}

define('GOOGLE_CLIENT_ID', $client_id);
define('GOOGLE_CLIENT_SECRET', $client_secret);
define('GOOGLE_REDIRECT_URL', 'http://localhost:8012/helpify/api/google_callback.php');

// Adjust port 8012 above if your localhost port is different
?>