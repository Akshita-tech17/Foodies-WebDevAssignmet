<?php
require_once "../config/admin_auth.php";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Dashboard statistics
$userQuery = $conn->query(
    "SELECT COUNT(*) AS total FROM users"
);
$totalUsers = $userQuery
    ? (int)$userQuery->fetch_assoc()["total"]
    : 0;

$orderQuery = $conn->query(
    "SELECT COUNT(*) AS total FROM orders"
);
$totalOrders = $orderQuery
    ? (int)$orderQuery->fetch_assoc()["total"]
    : 0;

$menuQuery = $conn->query(
    "SELECT COUNT(*) AS total FROM menu_items"
);
$totalMenuItems = $menuQuery
    ? (int)$menuQuery->fetch_assoc()["total"]
    : 0;

$revenueQuery = $conn->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS revenue
     FROM orders
     WHERE status = 'Delivered'"
);
$totalRevenue = $revenueQuery
    ? (float)$revenueQuery->fetch_assoc()["revenue"]
    : 0;

// Count orders by status
$statusNames = [
    "Placed",
    "Confirmed",
    "Preparing",
    "Out for Delivery",
    "Delivered",
    "Cancelled"
];

$statusCounts = [];

foreach ($statusNames as $status) {
    $statusCounts[$status] = 0;
}

$statusQuery = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM orders
     GROUP BY status"
);

if ($statusQuery) {
    while ($row = $statusQuery->fetch_assoc()) {
        $status = $row["status"];

        if (array_key_exists($status, $statusCounts)) {
            $statusCounts[$status] = (int)$row["total"];
        }
    }
}

// Fetch latest 10 orders
$recentOrders = $conn->query(
    "SELECT id, user_id, total_amount, status, created_at
     FROM orders
     ORDER BY created_at DESC, id DESC
     LIMIT 10"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Foodies</title>
    <link rel="stylesheet" href="../assest/css/admin.css">
</head>
<body>

    <h1>Foodies Admin Dashboard</h1>

    <p>
        Welcome,
        <?= e($_SESSION["user_name"] ?? "Admin") ?>!
    </p>

    <hr>

    <h2>Dashboard Statistics</h2>

    <h3>Total Users: <?= $totalUsers ?></h3>
    <h3>Total Orders: <?= $totalOrders ?></h3>
    <h3>Total Menu Items: <?= $totalMenuItems ?></h3>

    <h3>Total Revenue: ₹<?= number_format($totalRevenue, 2) ?></h3>
    <p>Revenue from delivered orders only.</p>

    <hr>

    <h2>Order Analytics</h2>

    <?php foreach ($statusCounts as $status => $count): ?>
        <div>
            <strong><?= e($status) ?>:</strong>
            <?= $count ?> orders
        </div>
    <?php endforeach; ?>

    <hr>

    <h2>Recent 10 Orders</h2>

    <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>

        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>User ID</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Order Date</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($order = $recentOrders->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$order["id"] ?></td>
                        <td><?= (int)$order["user_id"] ?></td>
                        <td>₹<?= number_format((float)$order["total_amount"], 2) ?></td>
                        <td><?= e($order["status"]) ?></td>
                        <td><?= e($order["created_at"]) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php else: ?>
        <p>No orders found yet.</p>
    <?php endif; ?>

    <hr>

    <h2>Admin Actions</h2>

    <ul>
        <li><a href="orders.php">Manage Orders</a></li>
        <li><a href="menu.php">Manage Menu</a></li>
        <li><a href="../index.php">View Food Menu</a></li>
    </ul>

</body>
</html>