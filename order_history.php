<?php
require_once __DIR__ . "/config/user_auth.php";
require_once __DIR__ . "/config/csrf.php";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$userId = (int)$_SESSION["user_id"];

$sql = "SELECT id, total_amount, status, created_at,
               delivery_name, delivery_phone, delivery_address
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit("Unable to load order history.");
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Foodies</title>

    <link rel="stylesheet"
          href="/phpt/Foodies-WebDevAssignmet/assest/css/order_history.css">
</head>
<body class="order-history-page">

<div class="order-history-container">

    <header class="history-header">
        <div>
            <span class="page-label">FOODIES</span>
            <h1>My Orders</h1>
            <p class="history-subtitle">
                Track your orders and manage your deliveries.
            </p>
        </div>

        <a href="index.php" class="home-button">
            Back to Home
        </a>
    </header>

    <?php if (isset($_GET["cancelled"])): ?>
        <div class="alert alert-success">
            Your order has been cancelled successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-error">
            <?= $_GET["error"] === "status"
                ? "This order cannot be cancelled because its status has changed."
                : "Unable to cancel the order. Please try again." ?>
        </div>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>

        <div class="orders-list">

            <?php while ($order = $result->fetch_assoc()): ?>

                <article class="order-card">

                    <div class="order-card-header">
                        <div>
                            <span class="order-label">ORDER ID</span>
                            <h2> #<?= (int)$order["id"] ?></h2>
                        </div>

                        <span class="order-status status-<?= strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim((string)$order["status"]))) ?>">
                            <?= e($order["status"]) ?>
                        </span>
                    </div>

                    <div class="order-summary">

                        <div class="summary-item">
                            <span class="summary-label">Order Date</span>
                            <p><?= e($order["created_at"]) ?></p>
                        </div>

                        <div class="summary-item">
                            <span class="summary-label">Total Amount</span>
                            <p class="order-total">
                                ₹<?= number_format((float)$order["total_amount"], 2) ?>
                            </p>
                        </div>

                    </div>

                    <div class="delivery-section">
                        <h3>Delivery Details</h3>

                        <div class="delivery-detail">
                            <span>Name</span>
                            <p><?= e($order["delivery_name"] ?? "") ?></p>
                        </div>

                        <div class="delivery-detail">
                            <span>Phone</span>
                            <p><?= e($order["delivery_phone"] ?? "") ?></p>
                        </div>

                        <div class="delivery-detail address-detail">
                            <span>Address</span>
                            <p><?= nl2br(e($order["delivery_address"] ?? "")) ?></p>
                        </div>
                    </div>

                    <div class="order-actions">

                        <a class="details-button"
                           href="order_details.php?id=<?= (int)$order["id"] ?>">
                            View Order Details
                        </a>

                        <?php if ($order["status"] === "Placed"): ?>
                            <form action="cancel_order.php" method="POST"
                                  onsubmit="return confirm('Are you sure you want to cancel this order?');">

                                <input type="hidden" name="csrf_token"
                                       value="<?= e(generateCsrfToken()) ?>">

                                <input type="hidden" name="order_id"
                                       value="<?= (int)$order["id"] ?>">

                                <button type="submit" class="cancel-button">
                                    Cancel Order
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>

                </article>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty-orders">
            <div class="empty-icon">🛍️</div>
            <h2>No Orders Yet</h2>
            <p>
                You haven't placed any orders yet.
                Explore our menu and find something delicious!
            </p>
            <a href="index.php" class="details-button">
                Explore Foodies
            </a>
        </div>

    <?php endif; ?>

</div>

</body>
</html>

<?php
$stmt->close();
?>