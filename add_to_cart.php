<?php
require_once __DIR__ . "/config/user_auth.php";
require_once __DIR__ . "/config/csrf.php";

// Accept only POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

// Verify CSRF token
if (!verifyCsrfToken()) {
    http_response_code(403);
    exit("Invalid security token. Please refresh the page and try again.");
}

$user_id = (int) $_SESSION["user_id"];
$menu_item_id = filter_input(INPUT_POST, "menu_item_id", FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

// Validate item ID and quantity
if (!$menu_item_id || !$quantity || $quantity < 1 || $quantity > 99) {
    header("Location: index.php?error=invalid_input");
    exit;
}

// Check whether the food item exists and is available
$check = $conn->prepare(
    "SELECT id FROM menu_items WHERE id = ? AND is_available = 1"
);

$check->bind_param("i", $menu_item_id);
$check->execute();

$item = $check->get_result()->fetch_assoc();
$check->close();

if (!$item) {
    header("Location: index.php?error=item_unavailable");
    exit;
}

// Add item to cart, or increase its quantity
$stmt = $conn->prepare(
    "INSERT INTO cart (user_id, menu_item_id, quantity)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
);

$stmt->bind_param("iii", $user_id, $menu_item_id, $quantity);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: index.php?cart=added");
    exit;
}

$stmt->close();

http_response_code(500);
echo "Unable to add the item to the cart. Please try again.";
?>