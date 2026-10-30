
<?php
require_once __DIR__ . "/config/user_auth.php";
require_once __DIR__ . "/config/csrf.php";
// Accept only POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cart.php");
    exit;
}

// Verify CSRF token
if (!verifyCsrfToken()) {
    http_response_code(403);
    exit("Invalid security token. Please refresh the page and try again.");
}

$user_id = (int) $_SESSION["user_id"];
$cart_id = filter_input(INPUT_POST, "cart_id", FILTER_VALIDATE_INT);

// Validate cart ID
if (!$cart_id || $cart_id < 1) {
    header("Location: cart.php?error=invalid_input");
    exit;
}

// Remove only the logged-in user's cart item
$stmt = $conn->prepare(
    "DELETE FROM cart WHERE id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $cart_id, $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Unable to remove item. Please try again.");
}

$stmt->close();

header("Location: cart.php");
exit;
?>