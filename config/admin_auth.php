<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT role, is_active FROM users WHERE id = ?"
);

if (!$stmt) {
    http_response_code(500);
    exit("Something went wrong. Please try again later.");
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Something went wrong. Please try again later.");
}

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

// Block deleted or deactivated accounts
if (!$user || (int)$user["is_active"] !== 1) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $cookie = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $cookie["path"],
            $cookie["domain"],
            $cookie["secure"],
            $cookie["httponly"]
        );
    }

    session_destroy();

    header("Location: ../login.php");
    exit;
}

// Verify admin permission
if ($user["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}
?>