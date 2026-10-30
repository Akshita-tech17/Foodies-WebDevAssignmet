<?php
require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/csrf.php";

$message = "";

$allowedTransitions = [
    "Placed" => ["Confirmed", "Cancelled"],
    "Confirmed" => ["Preparing", "Cancelled"],
    "Preparing" => ["Out for Delivery", "Cancelled"],
    "Out for Delivery" => ["Delivered"],
    "Delivered" => [],
    "Cancelled" => []
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh and try again.");
    }

    $order_id = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
    $new_status = $_POST["status"] ?? "";

    if ($order_id === false || $order_id === null ||$order_id < 1 ||!is_string($new_status)) {
        $message = "Invalid order or status.";
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "SELECT status FROM orders WHERE id = ? FOR UPDATE"
            );

            if (!$stmt) {
                throw new Exception("Unable to check order.");
            }

            $stmt->bind_param("i", $order_id);

            if (!$stmt->execute()) {
                throw new Exception("Unable to check order.");
            }

            $result = $stmt->get_result();
            $order = $result->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $current_status = $order["status"];
            $valid_next_statuses =
                $allowedTransitions[$current_status] ?? [];

            if (!in_array($new_status, $valid_next_statuses, true)) {
                throw new Exception(
                    "This status change is not allowed."
                );
            }

            $stmt = $conn->prepare(
                "UPDATE orders SET status = ? WHERE id = ?"
            );

            if (!$stmt) {
                throw new Exception("Unable to update order.");
            }

            $stmt->bind_param("si", $new_status, $order_id);

            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception("Unable to update order.");
            }

            $stmt->close();
            $conn->commit();

            $message = "Order status updated successfully.";
        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                error_log("Order rollback failed: " . $rollbackError->getMessage());
            }

            error_log("Admin order status update failed: " . $e->getMessage());
            $message = $e->getMessage() === "Order not found."
                ? "Order not found."
                : ($e->getMessage() === "This status change is not allowed."
                    ? "This status change is not allowed."
                    : "Unable to update order. Please try again.");
        }
    }
}

$result = $conn->query(
    "SELECT o.id, o.user_id, u.name, o.total_amount,
            o.status, o.created_at
     FROM orders o
     JOIN users u ON o.user_id = u.id
     ORDER BY o.id DESC"
);

if (!$result) {
    error_log("Admin orders query failed: " . $conn->error);
    http_response_code(500);
    exit("Unable to load orders.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Orders</title>
    <link rel="stylesheet" href="../assest/css/admin.css">
</head>
<body>
    <h1>Manage Orders</h1>

    <p><a href="../index.php">Back to Foodies</a></p>

    <?php if ($message !== ""): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
        <p>No orders found.</p>
    <?php else: ?>
        <?php while ($order = $result->fetch_assoc()): ?>
            <?php
            $current = $order["status"];
            $nextStatuses = $allowedTransitions[$current] ?? [];
            ?>

            <hr>
            <h3>Order #<?= (int) $order["id"] ?></h3>

            <p>
                Customer:
                <?= htmlspecialchars($order["name"], ENT_QUOTES, "UTF-8") ?>
            </p>

            <p>
                Total: ₹<?= number_format((float) $order["total_amount"], 2) ?>
            </p>

            <p>
                Current status:
                <?= htmlspecialchars($current, ENT_QUOTES, "UTF-8") ?>
            </p>

            <p>
                Order date:
                <?= htmlspecialchars($order["created_at"] ?? "", ENT_QUOTES, "UTF-8") ?>
            </p>

            <?php if (!empty($nextStatuses)): ?>
                <form method="POST" action="orders.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, "UTF-8") ?>"
                    >

                    <input
                        type="hidden"
                        name="order_id"
                        value="<?= (int) $order["id"] ?>"
                    >

                    <label for="status-<?= (int) $order["id"] ?>">
                        Update status:
                    </label>

                    <select
                        id="status-<?= (int) $order["id"] ?>"
                        name="status"
                        required
                    >
                        <?php foreach ($nextStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>">
                                <?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">Update Status</button>
                </form>
            <?php else: ?>
                <p>This order is complete and cannot be changed.</p>
            <?php endif; ?>

        <?php endwhile; ?>
    <?php endif; ?>
</body>
</html>