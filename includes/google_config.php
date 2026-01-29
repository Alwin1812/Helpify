<?php
// Google API Configuration
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URL', 'http://localhost:8012/helpify/api/google_callback.php');

// Adjust port 8012 above if your localhost port is different
?>