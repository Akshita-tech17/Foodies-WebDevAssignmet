<?php
require_once "../config/admin_auth.php";
require_once "../config/csrf.php";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$searchInput = $_GET["search"] ?? "";
$search = is_string($searchInput) ? trim($searchInput) : "";

$sql = "SELECT id, name, email, role, phone, is_active
        FROM users";

if ($search !== "") {
    $sql .= " WHERE name LIKE ? OR email LIKE ?";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit("Unable to load users.");
}

if ($search !== "") {
    $term = "%" . $search . "%";
    $stmt->bind_param("ss", $term, $term);
}

if (!$stmt->execute()) {
    exit("Unable to load users.");
}

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Foodies Admin</title>
    <link rel="stylesheet" href="../assest/css/admin.css">
</head>
<body>

<h1>Foodies - User Management</h1>

<p>Welcome, <?= e($_SESSION["user_name"] ?? "Admin") ?>!</p>

<?php if (isset($_GET["updated"])): ?>
    <p>User account status updated successfully.</p>
<?php endif; ?>

<?php if (isset($_GET["error"])): ?>
    <p>
        <?php
        $errorMessages = [
            "self" => "You cannot deactivate your own account.",
            "notfound" => "User not found.",
            "failed" => "Unable to update the account status."
        ];
        echo e($errorMessages[$_GET["error"]] ?? "Something went wrong.");
        ?>
    </p>
<?php endif; ?>

<hr>

<h2>Search Users</h2>

<form method="GET" action="users.php">
    <input
        type="text"
        name="search"
        value="<?= e($search) ?>"
        placeholder="Search by name or email"
    >
    <button type="submit">Search</button>
    <a href="users.php">Reset</a>
</form>

<hr>

<h2>Registered Users</h2>

<?php if ($result->num_rows > 0): ?>

<table border="1" cellpadding="8" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Phone</th>
            <th>Account Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>

    <?php while ($user = $result->fetch_assoc()): ?>
        <tr>
            <td><?= (int)$user["id"] ?></td>
            <td><?= e($user["name"]) ?></td>
            <td><?= e($user["email"]) ?></td>
            <td><?= e($user["role"]) ?></td>
            <td><?= e($user["phone"] ?? "") ?></td>
            <td><?= (int)$user["is_active"] === 1 ? "Active" : "Deactivated" ?></td>
            <td>
                <?php if ((int)$user["id"] === (int)$_SESSION["user_id"]): ?>
                    Current account
                <?php else: ?>
                    <form method="POST" action="toggle_user.php"
                          onsubmit="return confirm('Are you sure you want to change this account status?');">

                        <input type="hidden" name="csrf_token"
                               value="<?= e(generateCsrfToken()) ?>">

                        <input type="hidden" name="user_id"
                               value="<?= (int)$user["id"] ?>">

                        <input type="hidden" name="action"
                               value="<?= (int)$user["is_active"] === 1 ? "deactivate" : "activate" ?>">

                        <button type="submit">
                            <?= (int)$user["is_active"] === 1 ? "Deactivate" : "Reactivate" ?>
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>

    </tbody>
</table>

<?php else: ?>
    <p>No users found.</p>
<?php endif; ?>

<?php $stmt->close(); ?>

<hr>

<h2>Admin Navigation</h2>
<ul>
    <li><a href="dashboard.php">Admin Dashboard</a></li>
    <li><a href="orders.php">Manage Orders</a></li>
    <li><a href="menu.php">Manage Menu</a></li>
    <li><a href="sales_report.php">Sales Report</a></li>
</ul>

</body>
</html>