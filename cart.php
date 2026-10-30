<?php
require_once __DIR__ . "/config/user_auth.php";
require_once __DIR__ . "/config/csrf.php";

$user_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT c.id AS cart_id, c.quantity,
            m.name, m.price,
            (c.quantity * m.price) AS subtotal
     FROM cart c
     JOIN menu_items m ON c.menu_item_id = m.id
     WHERE c.user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$total = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - Foodies</title>

    <link rel="stylesheet" href="/phpt/Foodies-WebDevAssignmet/assest/css/cart.css">
</head>

<body class="cart-page">

<div class="cart-container">

    <h1>My Cart</h1>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($item = $result->fetch_assoc()): ?>
            <?php $total += (float) $item["subtotal"]; ?>

            <div class="cart-item">

                <h3><?= htmlspecialchars($item["name"]) ?></h3>

                <p>
                    Price: ₹<?= number_format((float) $item["price"], 2) ?>
                </p>

                <p>
                    Subtotal: ₹<?= number_format((float) $item["subtotal"], 2) ?>
                </p>

                <!-- Update Quantity -->
                <form action="update_cart.php" method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <input
                        type="hidden"
                        name="cart_id"
                        value="<?= (int) $item["cart_id"] ?>"
                    >

                    <label for="qty-<?= (int) $item["cart_id"] ?>">
                        Quantity:
                    </label>

                    <input
                        type="number"
                        id="qty-<?= (int) $item["cart_id"] ?>"
                        name="quantity"
                        value="<?= (int) $item["quantity"] ?>"
                        min="1"
                        max="99"
                        required
                    >

                    <button type="submit" class="update-btn">
                        Update Quantity
                    </button>
                </form>

                <!-- Remove Item -->
                <form action="remove_from_cart.php" method="POST" class="remove-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <input
                        type="hidden"
                        name="cart_id"
                        value="<?= (int) $item["cart_id"] ?>"
                    >

                    <button type="submit" class="remove-btn">
                        Remove Item
                    </button>
                </form>

            </div>

        <?php endwhile; ?>

        <h2 class="cart-total">
            Total: ₹<?= number_format($total, 2) ?>
        </h2>

        <!-- Place Order -->
        <form action="place_order.php" method="POST" class="place-order-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <button type="submit" class="place-order-btn">
                Place Order
            </button>
        </form>

    <?php else: ?>

        <div class="empty-cart">
            <h3>Your cart is empty.</h3>
            <p>Add some delicious food to get started!</p>
        </div>

    <?php endif; ?>

    <a href="index.php" class="continue-shopping">
        ← Continue Shopping
    </a>

</div>

<?php
$stmt->close();
?>
<script src="/phpt/Foodies-WebDevAssignmet/assest/js/cart.js"></script>
</body>
</html>