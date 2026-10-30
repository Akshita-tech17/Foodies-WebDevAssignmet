<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$port = 3307;
$username = "root";
$password = "";
$database = "food_ordering";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Something went wrong. Please try again later.");
}

if (!$conn->set_charset("utf8mb4")) {
    error_log("Failed to set database charset: " . $conn->error);
    http_response_code(500);
    exit("Something went wrong. Please try again later.");
}
?>