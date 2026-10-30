<?php
require_once __DIR__ . "/config/user_auth.php";

$user_id = (int) $_SESSION["user_id"];
$order_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$order_id || $order_id < 1) {
    header("Location: index.php");
    exit;
}

// Verify that this order belongs to the logged-in user
$stmt = $conn->prepare(
    "SELECT id, total_amount, status, created_at
     FROM orders
     WHERE id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit("Order not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful - Foodies</title>

    <link rel="stylesheet" href="/phpt/Foodies-WebDevAssignmet/assest/css/success.css">
</head>

<body class="order-success-page">

    <main class="success-card">

        <div class="success-icon" aria-hidden="true">✓</div>

        <h1>Order Placed Successfully!</h1>

        <p class="thank-you">
            Thank you, <?= htmlspecialchars($_SESSION["user_name"]) ?>!
        </p>

        <p class="success-message">
            Your order has been placed. We hope you enjoy your meal!
        </p>

        <div class="order-details">

            <h2>Order Details</h2>

            <div class="detail-row">
                <span>Order ID</span>
                <strong>#<?= (int) $order["id"] ?></strong>
            </div>

            <div class="detail-row">
                <span>Total Amount</span>
                <strong class="order-amount">
                    ₹<?= number_format((float) $order["total_amount"], 2) ?>
                </strong>
            </div>

            <div class="detail-row">
                <span>Status</span>
                <strong class="order-status">
                    <?= htmlspecialchars($order["status"]) ?>
                </strong>
            </div>

            <div class="detail-row">
                <span>Order Date</span>
                <strong>
                    <?= htmlspecialchars($order["created_at"]) ?>
                </strong>
            </div>

        </div>

        <a href="index.php" class="continue-shopping">
            Continue Shopping
        </a>

    </main>

</body>
</html>