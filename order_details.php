<?php
require_once __DIR__ . "/config/user_auth.php";

$user_id = (int) $_SESSION["user_id"];
$order_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$order_id || $order_id < 1) {
    header("Location: order_history.php");
    exit;
}

// Fetch the order only if it belongs to the logged-in user
$stmt = $conn->prepare(
    "SELECT id, total_amount, status, created_at,
            delivery_name, delivery_phone, delivery_address
     FROM orders
     WHERE id = ? AND user_id = ?"
);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load order details.");
}

$stmt->bind_param("ii", $order_id, $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Unable to load order details.");
}

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit("Order not found.");
}

// Fetch the food items belonging to this order
$stmt = $conn->prepare(
    "SELECT oi.quantity, oi.price, m.name
     FROM order_items oi
     JOIN menu_items m ON oi.menu_item_id = m.id
     WHERE oi.order_id = ?"
);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load ordered items.");
}

$stmt->bind_param("i", $order_id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Unable to load ordered items.");
}

$items = $stmt->get_result();

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Order Details - Foodies</title>

    <link rel="stylesheet" href="/phpt/Foodies-WebDevAssignmet/assest/css/order_details.css">
</head>

<body class="order-details-page">

<main class="order-container">

    <header class="order-header">
        <p class="page-label">FOODIES ORDER SUMMARY</p>
        <h1>Order Details</h1>
        <p class="order-number">
            Order #<?= (int)$order["id"] ?>
        </p>
    </header>

    <section class="order-status-card">
        <div>
            <span class="detail-label">Order Status</span>
            <strong class="status-value">
                <?= e($order["status"]) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">Order Date</span>
            <strong><?= e($order["created_at"]) ?></strong>
        </div>
    </section>

    <section class="details-card">
        <h2>
            <span class="section-icon" aria-hidden="true">📍</span>
            Delivery Details
        </h2>

        <?php if (
            !empty($order["delivery_name"]) ||
            !empty($order["delivery_phone"]) ||
            !empty($order["delivery_address"])
        ): ?>

            <div class="delivery-info">

                <div class="info-row">
                    <span>Name</span>
                    <strong><?= e($order["delivery_name"]) ?></strong>
                </div>

                <div class="info-row">
                    <span>Phone</span>
                    <strong><?= e($order["delivery_phone"]) ?></strong>
                </div>

                <div class="info-row">
                    <span>Address</span>
                    <strong class="delivery-address">
                        <?= nl2br(e($order["delivery_address"])) ?>
                    </strong>
                </div>

            </div>

        <?php else: ?>

            <p class="empty-message">
                Delivery details are not available for this order.
            </p>

        <?php endif; ?>
    </section>

    <section class="details-card">
        <h2>
            <span class="section-icon" aria-hidden="true">🍽️</span>
            Food Items
        </h2>

        <?php if ($items->num_rows > 0): ?>

            <div class="food-list">

                <?php while ($item = $items->fetch_assoc()): ?>

                    <article class="food-item">

                        <div class="food-info">
                            <h3><?= e($item["name"]) ?></h3>

                            <p>
                                ₹<?= number_format((float)$item["price"], 2) ?>
                                per item
                            </p>

                            <p class="food-quantity">
                                Quantity: <?= (int)$item["quantity"] ?>
                            </p>
                        </div>

                        <strong class="food-subtotal">
                            ₹<?= number_format(
                                (float)$item["price"] * (int)$item["quantity"],
                                2
                            ) ?>
                        </strong>

                    </article>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <p class="empty-message">
                No food items found for this order.
            </p>

        <?php endif; ?>
    </section>

    <section class="total-card">
        <span>Total Amount</span>
        <strong>
            ₹<?= number_format((float)$order["total_amount"], 2) ?>
        </strong>
    </section>

    <?php $stmt->close(); ?>

    <nav class="order-navigation">
        <a href="order_history.php" class="back-orders">
            ← Back to Order History
        </a>

        <a href="index.php" class="home-button">
            Back to Home
        </a>
    </nav>

</main>

</body>
</html>