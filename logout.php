<?php
require_once 'config/csrf.php';

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

// Verify CSRF token
if (!verifyCsrfToken()) {
    http_response_code(403);
    exit("Invalid request. Please refresh the page and try again.");
}

// Clear session data
$_SESSION = [];

// Destroy session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>