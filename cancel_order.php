<?php
require_once __DIR__ . "/config/user_auth.php";
require_once __DIR__ . "/config/csrf.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: order_history.php");
    exit;
}

if (!verifyCsrfToken()) {
    http_response_code(403);
    exit("Invalid request. Please refresh the page and try again.");
}

if (
    !isset($_POST["order_id"]) ||
    !is_scalar($_POST["order_id"]) ||
    !ctype_digit((string)$_POST["order_id"]) ||
    (int)$_POST["order_id"] < 1
) {
    header("Location: order_history.php?error=failed");
    exit;
}

$orderId = (int)$_POST["order_id"];
$userId = (int)$_SESSION["user_id"];

$conn->begin_transaction();

try {
    // Lock the user's order while checking its status.
    $stmt = $conn->prepare(
        "SELECT status
         FROM orders
         WHERE id = ? AND user_id = ?
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception("Could not prepare order query.");
    }

    $stmt->bind_param("ii", $orderId, $userId);

    if (!$stmt->execute()) {
        throw new Exception("Could not check order status.");
    }

    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $conn->rollback();
        header("Location: order_history.php?error=failed");
        exit;
    }

    // Only orders that are still Placed can be cancelled.
    if ($order["status"] !== "Placed") {
        $conn->rollback();
        header("Location: order_history.php?error=status");
        exit;
    }

    $update = $conn->prepare(
        "UPDATE orders
         SET status = 'Cancelled'
         WHERE id = ? AND user_id = ? AND status = 'Placed'"
    );

    if (!$update) {
        throw new Exception("Could not prepare cancellation.");
    }

    $update->bind_param("ii", $orderId, $userId);

    if (!$update->execute() || $update->affected_rows !== 1) {
        $update->close();
        throw new Exception("Order cancellation failed.");
    }

    $update->close();
    $conn->commit();

    header("Location: order_history.php?cancelled=1");
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    error_log("Order cancellation error: " . $e->getMessage());

    header("Location: order_history.php?error=failed");
    exit;
}
?>