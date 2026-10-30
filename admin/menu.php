<?php
require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/csrf.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh and try again.");
    }

    $action = $_POST["action"] ?? "";

    // Add a new food item
    if ($action === "add") {
        $name = trim($_POST["name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $priceInput = trim($_POST["price"] ?? "");

        if ($name === "" || $priceInput === "" ||
            !is_numeric($priceInput) ||
            !is_finite((float)$priceInput) ||
            (float)$priceInput <= 0 ||
            strlen($name) > 150) {
            $message = "Enter a valid food name and price.";
        } else {
            $price = (float)$priceInput;

            $stmt = $conn->prepare(
                "INSERT INTO menu_items (name, description, price, is_available)
                 VALUES (?, ?, ?, 1)"
            );

            if ($stmt) {
                $stmt->bind_param("ssd", $name, $description, $price);

                if ($stmt->execute()) {
                    $message = "Food item added successfully.";
                } else {
                    $message = "Unable to add food item.";
                }

                $stmt->close();
            } else {
                $message = "Unable to add food item.";
            }
        }
    }

    // Update food details
    elseif ($action === "edit") {
        $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
        $name = trim($_POST["name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $priceInput = trim($_POST["price"] ?? "");

        if (!$id || $name === "" || $priceInput === "" ||
            !is_numeric($priceInput) ||
            !is_finite((float)$priceInput) ||
            (float)$priceInput <= 0 ||
            strlen($name) > 150) {
            $message = "Enter valid food details.";
        } else {
            $price = (float)$priceInput;

            $stmt = $conn->prepare(
                "UPDATE menu_items
                 SET name = ?, description = ?, price = ?
                 WHERE id = ?"
            );

            if ($stmt) {
                $stmt->bind_param("ssdi", $name, $description, $price, $id);

                if ($stmt->execute()) {
                    $message = $stmt->affected_rows > 0
                        ? "Food item updated successfully."
                        : "No changes made, or item not found.";
                } else {
                    $message = "Unable to update food item.";
                }

                $stmt->close();
            } else {
                $message = "Unable to update food item.";
            }
        }
    }

    // Change availability
    elseif ($action === "availability") {
        $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
        $available = $_POST["is_available"] ?? "";

        if (!$id || !in_array($available, ["0", "1"], true)) {
            $message = "Invalid availability request.";
        } else {
            $stmt = $conn->prepare(
                "UPDATE menu_items SET is_available = ? WHERE id = ?"
            );

            if ($stmt) {
                $availableInt = (int)$available;
                $stmt->bind_param("ii", $availableInt, $id);

                if ($stmt->execute()) {
                    $message = $stmt->affected_rows > 0
                        ? "Availability updated successfully."
                        : "No changes made, or item not found.";
                } else {
                    $message = "Unable to update availability.";
                }

                $stmt->close();
            } else {
                $message = "Unable to update availability.";
            }
        }
    }

    // Delete food item
    elseif ($action === "delete") {
        $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

        if (!$id) {
            $message = "Invalid food item.";
        } else {
            $stmt = $conn->prepare(
                "DELETE FROM menu_items WHERE id = ?"
            );

            if ($stmt) {
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $message = $stmt->affected_rows > 0
                        ? "Food item deleted successfully."
                        : "Food item not found.";
                } else {
                    $message = "Unable to delete item. It may be linked to an existing order.";
                }

                $stmt->close();
            } else {
                $message = "Unable to delete food item.";
            }
        }
    } else {
        $message = "Invalid action.";
    }
}

$result = $conn->query(
    "SELECT id, name, description, price, is_available
     FROM menu_items
     ORDER BY id DESC"
);

if (!$result) {
    error_log("Admin menu query failed: " . $conn->error);
    http_response_code(500);
    exit("Unable to load menu items.");
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Menu</title>
    <link rel="stylesheet" href="../assest/css/admin.css">
</head>
<body>

<h1>Admin - Manage Food Menu</h1>
<p><a href="../index.php">Back to Foodies</a></p>
<p><a href="orders.php">Manage Orders</a></p>

<?php if ($message !== ""): ?>
    <p><?= e($message) ?></p>
<?php endif; ?>

<hr>

<h2>Add New Food Item</h2>

<form method="POST" action="menu.php">
    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
    <input type="hidden" name="action" value="add">

    <label for="name">Food Name:</label><br>
    <input type="text" id="name" name="name" maxlength="150" required>
    <br><br>

    <label for="description">Description:</label><br>
    <textarea id="description" name="description" rows="3"></textarea>
    <br><br>

    <label for="price">Price:</label><br>
    <input type="number" id="price" name="price" min="0.01" step="0.01" required>
    <br><br>

    <button type="submit">Add Food Item</button>
</form>

<hr>

<h2>Existing Food Items</h2>

<?php if ($result->num_rows === 0): ?>
    <p>No food items found.</p>
<?php else: ?>
    <?php while ($item = $result->fetch_assoc()): ?>
        <h3><?= e($item["name"]) ?> (ID: <?= (int)$item["id"] ?>)</h3>

        <p>Description: <?= e($item["description"] ?? "") ?></p>
        <p>Price: ₹<?= number_format((float)$item["price"], 2) ?></p>
        <p>
            Availability:
            <?= (int)$item["is_available"] === 1 ? "Available" : "Unavailable" ?>
        </p>

        <h4>Edit Food Details</h4>

        <form method="POST" action="menu.php">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= (int)$item["id"] ?>">

            <label>Food Name:</label><br>
            <input type="text" name="name" maxlength="150" value="<?= e($item["name"]) ?>" required>
            <br><br>

            <label>Description:</label><br>
            <textarea name="description" rows="3"><?= e($item["description"] ?? "") ?></textarea>
            <br><br>

            <label>Price:</label><br>
            <input type="number" name="price" min="0.01" step="0.01" value="<?= e($item["price"]) ?>" required>
            <br><br>

            <button type="submit">Save Changes</button>
        </form>

        <h4>Change Availability</h4>

        <form method="POST" action="menu.php">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="availability">
            <input type="hidden" name="id" value="<?= (int)$item["id"] ?>">

            <select name="is_available" required>
                <option value="1" <?= (int)$item["is_available"] === 1 ? "selected" : "" ?>>
                    Available
                </option>
                <option value="0" <?= (int)$item["is_available"] === 0 ? "selected" : "" ?>>
                    Unavailable
                </option>
            </select>

            <button type="submit">Update Availability</button>
        </form>

        <h4>Delete Food Item</h4>

        <form method="POST" action="menu.php"
              onsubmit="return confirm('Are you sure you want to delete this food item?');">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$item["id"] ?>">

            <button type="submit">Delete Item</button>
        </form>

        <hr>
    <?php endwhile; ?>
<?php endif; ?>

</body>
</html>