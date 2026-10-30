<?php
require_once __DIR__ . "/config/user_auth.php";
require_once __DIR__ . "/config/csrf.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cart.php");
    exit;
}

if (!verifyCsrfToken()) {
    http_response_code(403);
    exit("Invalid security token. Please refresh the page and try again.");
}

$user_id = (int) $_SESSION["user_id"];

try {
    $conn->begin_transaction();

    // Fetch customer delivery details
    $stmt = $conn->prepare(
        "SELECT name, phone, address
         FROM users
         WHERE id = ?
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception("Unable to prepare customer query.");
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Unable to fetch customer details.");
    }

    $customerResult = $stmt->get_result();
    $customer = $customerResult->fetch_assoc();
    $stmt->close();

    if (!$customer) {
        throw new Exception("Customer account not found.");
    }

    $delivery_name = trim($customer["name"] ?? "");
    $delivery_phone = trim($customer["phone"] ?? "");
    $delivery_address = trim($customer["address"] ?? "");

    if (
        $delivery_name === "" ||
        $delivery_phone === "" ||
        $delivery_address === ""
    ) {
        throw new Exception(
            "Please save your name, phone number and delivery address in your profile."
        );
    }

    // Fetch cart items and lock their rows
    $stmt = $conn->prepare(
        "SELECT c.menu_item_id, c.quantity, m.price, m.is_available
         FROM cart c
         JOIN menu_items m ON c.menu_item_id = m.id
         WHERE c.user_id = ?
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception("Unable to prepare cart query.");
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Unable to fetch cart items.");
    }

    $result = $stmt->get_result();
    $items = [];
    $total = 0;

    while ($item = $result->fetch_assoc()) {
        $quantity = (int) $item["quantity"];
        $price = (float) $item["price"];

        if ((int) $item["is_available"] !== 1) {
            throw new Exception(
                "One or more items in your cart are unavailable."
            );
        }

        if (
            $quantity < 1 ||
            $quantity > 99 ||
            !is_finite($price) ||
            $price < 0
        ) {
            throw new Exception("Invalid cart item.");
        }

        $item["subtotal"] = $quantity * $price;
        $total += $item["subtotal"];
        $items[] = $item;
    }

    $stmt->close();

    if (empty($items)) {
        throw new Exception("Your cart is empty.");
    }

    // Create order with a snapshot of delivery details
    $stmt = $conn->prepare(
        "INSERT INTO orders
         (user_id, total_amount, status,
          delivery_name, delivery_phone, delivery_address)
         VALUES (?, ?, 'Placed', ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Unable to prepare order.");
    }

    $stmt->bind_param(
        "idsss",
        $user_id,
        $total,
        $delivery_name,
        $delivery_phone,
        $delivery_address
    );

    if (!$stmt->execute()) {
        throw new Exception("Unable to create order.");
    }

    $order_id = $conn->insert_id;
    $stmt->close();

    // Save order items with their purchase-time prices
    $stmt = $conn->prepare(
        "INSERT INTO order_items
         (order_id, menu_item_id, quantity, price)
         VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Unable to prepare order items.");
    }

    foreach ($items as $item) {
        $menu_item_id = (int) $item["menu_item_id"];
        $quantity = (int) $item["quantity"];
        $price = (float) $item["price"];

        $stmt->bind_param(
            "iiid",
            $order_id,
            $menu_item_id,
            $quantity,
            $price
        );

        if (!$stmt->execute()) {
            throw new Exception("Unable to save order items.");
        }
    }

    $stmt->close();

    // Clear cart only after order data has been saved
    $stmt = $conn->prepare(
        "DELETE FROM cart WHERE user_id = ?"
    );

    if (!$stmt) {
        throw new Exception("Unable to prepare cart cleanup.");
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Unable to clear cart.");
    }

    $stmt->close();

    $conn->commit();

    header("Location: order_success.php?id=" . $order_id);
    exit;

} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log(
            "Order rollback failed: " . $rollbackError->getMessage()
        );
    }

    error_log("Order placement failed: " . $e->getMessage());

    http_response_code(400);

    if (
        $e->getMessage() ===
        "Please save your name, phone number and delivery address in your profile."
    ) {
        echo htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        );
        echo ' <a href="profile.php">Go to Profile</a>';
    } else {
        echo "Unable to place your order. Please check your cart and try again.";
    }
}
?>