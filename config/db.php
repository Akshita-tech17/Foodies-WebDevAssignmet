<?php
mysqli_report(MYSQLI_REPORT_OFF);

// Read from environment variables if present (for cloud/production hosting), otherwise fallback to local defaults
$host     = getenv('DB_HOST') ?: "127.0.0.1";
$port     = (int)(getenv('DB_PORT') ?: 3306);
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$database = getenv('DB_NAME') ?: "food_ordering";

$conn = @new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);

// If local default port 3306 fails and no explicit port env var is set, try 3307
if ($conn->connect_error && !getenv('DB_PORT') && $port === 3306) {
    $alt_port = 3307;
    $conn = @new mysqli(
        $host,
        $username,
        $password,
        $database,
        $alt_port
    );
}

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed. Please check your database settings.");
}

if (!$conn->set_charset("utf8mb4")) {
    error_log("Failed to set database charset: " . $conn->error);
    http_response_code(500);
    exit("Something went wrong. Please try again later.");
}
?>