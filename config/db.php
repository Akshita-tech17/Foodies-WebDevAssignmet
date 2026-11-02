<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host     = getenv('DB_HOST') ?: "127.0.0.1";
$port     = (int)(getenv('DB_PORT') ?: 3306);
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$database = getenv('DB_NAME') ?: "food_ordering";

$conn = mysqli_init();

// Enable SSL if connecting to cloud database (TiDB, Aiven, etc.) or if DB_SSL is set
$isRemote = ($host !== '127.0.0.1' && $host !== 'localhost');
$useSsl   = $isRemote || getenv('DB_SSL') === 'true';

if ($useSsl) {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $flags = MYSQLI_CLIENT_SSL;
} else {
    $flags = 0;
}

$connected = @$conn->real_connect($host, $username, $password, $database, $port, NULL, $flags);

// If local default port 3306 fails and no explicit port env var is set, try 3307
if (!$connected && !getenv('DB_PORT') && $port === 3306 && !$isRemote) {
    $conn = mysqli_init();
    $connected = @$conn->real_connect($host, $username, $password, $database, 3307);
}

if (!$connected) {
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