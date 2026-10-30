<?php
require_once "../config/admin_auth.php";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$today = date("Y-m-d");
$defaultStart = date("Y-m-01");

$startInput = $_GET["start_date"] ?? $defaultStart;
$endInput = $_GET["end_date"] ?? $today;

$startDate = is_string($startInput) ? $startInput : "";
$endDate = is_string($endInput) ? $endInput : "";

$dateError = "";

$isValidStart = is_string($startDate)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
    && DateTime::createFromFormat("!Y-m-d", $startDate)
    && DateTime::createFromFormat("!Y-m-d", $startDate)->format("Y-m-d") === $startDate;

$isValidEnd = is_string($endDate)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)
    && DateTime::createFromFormat("!Y-m-d", $endDate)
    && DateTime::createFromFormat("!Y-m-d", $endDate)->format("Y-m-d") === $endDate;

$report = [];
$totalRevenue = 0;
$totalDeliveredOrders = 0;

if (!$isValidStart || !$isValidEnd || $startDate > $endDate) {
    $dateError = "Please select a valid date range.";
} else {
    // Daily revenue and delivered order count
    $sql = "SELECT DATE(created_at) AS sale_date,
                   COUNT(*) AS total_orders,
                   COALESCE(SUM(total_amount), 0) AS revenue
            FROM orders
            WHERE status = 'Delivered'
              AND created_at >= ?
              AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY DATE(created_at)
            ORDER BY sale_date DESC";

    $stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Sales report prepare failed: " . $conn->error);
    http_response_code(500);
    exit("Unable to generate the sales report.");
}

    $stmt->bind_param("ss", $startDate, $endDate);

    if (!$stmt->execute()) {
        $stmt->close();
        exit("Unable to generate the sales report.");
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row["total_orders"] = (int)$row["total_orders"];
        $row["revenue"] = (float)$row["revenue"];

        $report[] = $row;
        $totalDeliveredOrders += $row["total_orders"];
        $totalRevenue += $row["revenue"];
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Foodies Admin</title>
    <link rel="stylesheet" href="../assest/css/admin.css">
</head>
<body>

    <h1>Foodies Sales Report</h1>

    <p>Welcome, <?= e($_SESSION["user_name"] ?? "Admin") ?>!</p>

    <hr>

    <h2>Select Date Range</h2>

    <form method="GET" action="sales_report.php">
        <label for="start_date">Start Date:</label>
        <input
            type="date"
            id="start_date"
            name="start_date"
            value="<?= e($startDate) ?>"
            required
        >

        <br><br>

        <label for="end_date">End Date:</label>
        <input
            type="date"
            id="end_date"
            name="end_date"
            value="<?= e($endDate) ?>"
            required
        >

        <br><br>

        <button type="submit">Generate Report</button>
        <a href="sales_report.php">Reset Dates</a>
    </form>

    <?php if ($dateError !== ""): ?>

        <p><?= e($dateError) ?></p>

    <?php else: ?>

        <hr>

        <h2>Report Summary</h2>

        <p>
            <strong>Period:</strong>
            <?= e($startDate) ?> to <?= e($endDate) ?>
        </p>

        <h3>
            Total Delivered Orders: <?= $totalDeliveredOrders ?>
        </h3>

        <h3>
            Total Revenue: ₹<?= number_format($totalRevenue, 2) ?>
        </h3>

        <hr>

        <h2>Daily Sales Details</h2>

        <?php if (count($report) > 0): ?>

            <table border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Delivered Orders</th>
                        <th>Revenue</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($report as $row): ?>
                        <tr>
                            <td><?= e($row["sale_date"]) ?></td>
                            <td><?= $row["total_orders"] ?></td>
                            <td>
                                ₹<?= number_format($row["revenue"], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <p>No delivered orders found for this date range.</p>

        <?php endif; ?>

    <?php endif; ?>

    <hr>

    <h2>Admin Navigation</h2>

    <ul>
        <li><a href="dashboard.php">Admin Dashboard</a></li>
        <li><a href="orders.php">Manage Orders</a></li>
        <li><a href="menu.php">Manage Menu</a></li>
    </ul>

</body>
</html>