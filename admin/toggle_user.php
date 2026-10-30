<?php
require_once "../config/admin_auth.php";
require_once "../config/csrf.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: users.php");
    exit;
}

if (!verifyCsrfToken()) {
    http_response_code(403);
    exit("Invalid request. Please refresh the page and try again.");
}

$userId = $_POST["user_id"] ?? "";
$action = $_POST["action"] ?? "";

if (
    !is_string($userId) ||
    !ctype_digit($userId) ||
    (int)$userId < 1 ||
    !in_array($action, ["activate", "deactivate"], true)
) {
    header("Location: users.php?error=failed");
    exit;
}

$userId = (int)$userId;

if ($userId === (int)$_SESSION["user_id"]) {
    header("Location: users.php?error=self");
    exit;
}

$isActive = $action === "activate" ? 1 : 0;

$stmt = $conn->prepare(
    "UPDATE users SET is_active = ? WHERE id = ?"
);

if (!$stmt) {
    header("Location: users.php?error=failed");
    exit;
}

$stmt->bind_param("ii", $isActive, $userId);

if (!$stmt->execute()) {
    $stmt->close();
    header("Location: users.php?error=failed");
    exit;
}

$updated = $stmt->affected_rows;
$stmt->close();

if ($updated === 0) {
    $check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $check->bind_param("i", $userId);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    header("Location: users.php?error=" . ($exists ? "failed" : "notfound"));
    exit;
}

header("Location: users.php?updated=1");
exit;
?>