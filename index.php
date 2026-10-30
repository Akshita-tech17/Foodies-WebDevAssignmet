<?php
require_once __DIR__ . "/config/user_auth.php";
require_once __DIR__ . "/config/csrf.php";

$search = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");
$minPriceInput = trim($_GET["min_price"] ?? "");
$maxPriceInput = trim($_GET["max_price"] ?? "");
$availableOnly = $_GET["available"] ?? "1";

$message = "";
$foods = [];

$minPrice = null;
$maxPrice = null;

// Check minimum and maximum price
if (
    $minPriceInput !== "" &&
    (!is_numeric($minPriceInput) ||
     !is_finite((float)$minPriceInput) ||
     (float)$minPriceInput < 0)
) {
    $message = "Please enter a valid minimum price.";
} elseif (
    $maxPriceInput !== "" &&
    (!is_numeric($maxPriceInput) ||
     !is_finite((float)$maxPriceInput) ||
     (float)$maxPriceInput < 0)
) {
    $message = "Please enter a valid maximum price.";
} else {
    if ($minPriceInput !== "") {
        $minPrice = (float)$minPriceInput;
    }

    if ($maxPriceInput !== "") {
        $maxPrice = (float)$maxPriceInput;
    }

    if (
        $minPrice !== null &&
        $maxPrice !== null &&
        $minPrice > $maxPrice
    ) {
        $message = "Minimum price cannot exceed maximum price.";
    }
}

if (!in_array($availableOnly, ["0", "1"], true)) {
    $availableOnly = "1";
}

// Check whether the current user is an active admin
$showAdminLink = false;

if (isset($_SESSION["user_id"])) {
    $adminCheck = $conn->prepare(
        "SELECT role, is_active FROM users WHERE id = ?"
    );

    if ($adminCheck) {
        $currentUserId = (int) $_SESSION["user_id"];

        $adminCheck->bind_param("i", $currentUserId);

        if ($adminCheck->execute()) {
            $adminResult = $adminCheck->get_result();
            $adminUser = $adminResult->fetch_assoc();

            $showAdminLink = $adminUser
                && $adminUser["role"] === "admin"
                && (int) $adminUser["is_active"] === 1;
        }

        $adminCheck->close();
    }
}

// Fetch menu categories
$categoryResult = $conn->query(
    "SELECT DISTINCT category
     FROM menu_items
     WHERE category IS NOT NULL AND category <> ''
     ORDER BY category"
);

if (!$categoryResult) {
    error_log("Category query failed: " . $conn->error);
    http_response_code(500);
    exit("Unable to load menu categories.");
}

$categories = [];

while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row["category"];
}

// Build the menu search query
if ($message === "") {
    $sql = "SELECT id, name, description, price, category, image, is_available
            FROM menu_items
            WHERE 1 = 1";

    $types = "";
    $params = [];

    if ($search !== "") {
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
        $searchTerm = "%" . $search . "%";
        $types .= "ss";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($category !== "") {
        $sql .= " AND category = ?";
        $types .= "s";
        $params[] = $category;
    }

    if ($minPrice !== null) {
        $sql .= " AND price >= ?";
        $types .= "d";
        $params[] = $minPrice;
    }

    if ($maxPrice !== null) {
        $sql .= " AND price <= ?";
        $types .= "d";
        $params[] = $maxPrice;
    }

    if ($availableOnly === "1") {
        $sql .= " AND is_available = 1";
    }

    $sql .= " ORDER BY name ASC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Menu search preparation failed: " . $conn->error);
        http_response_code(500);
        exit("Unable to load menu.");
    }

    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log("Menu search failed: " . $stmt->error);
        $stmt->close();
        http_response_code(500);
        exit("Unable to load menu.");
    }

    $result = $stmt->get_result();

    while ($food = $result->fetch_assoc()) {
        $foods[] = $food;
    }

    $stmt->close();
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Foodies - Home</title>

    <link rel="stylesheet" href="assest/css/style.css">
</head>

<body>

<header class="site-header">

    <a href="index.php" class="brand">
        <span class="brand-icon">F</span>
        <span>Foodies<span class="brand-dot">.</span></span>
    </a>

    <nav class="nav-links" aria-label="Main navigation">

        <a href="index.php" class="nav-link active">Home</a>
        <a href="cart.php" class="nav-link">View Cart</a>
        <a href="profile.php" class="nav-link">My Profile</a>
        <a href="order_history.php" class="nav-link">My Orders</a>

        <?php if ($showAdminLink): ?>
            <a href="admin/dashboard.php" class="nav-link">
                Admin Panel
            </a>
        <?php endif; ?>

    </nav>

    <form action="logout.php" method="POST" class="logout-form">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(generateCsrfToken()) ?>"
        >

        <button type="submit" class="logout-button">
            Logout
        </button>

    </form>

</header>

<section class="welcome">
    <h2>Welcome, <?= e($_SESSION["user_name"] ?? "Customer") ?>!</h2>
    <p>Search your favourite food and explore our menu.</p>
</section>

<h2 style="text-align:center;">Search &amp; Filter Menu</h2>

<section class="filters">

    <form action="index.php" method="GET">

        <div class="filter-grid">

            <div>
                <label for="search">Food Name</label>

                <input
                    type="search"
                    id="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search food..."
                    maxlength="150"
                >
            </div>

            <div>
                <label for="category">Category</label>

                <select id="category" name="category">
                    <option value="">All Categories</option>

                    <?php foreach ($categories as $cat): ?>
                        <option
                            value="<?= e($cat) ?>"
                            <?= $category === $cat ? "selected" : "" ?>
                        >
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label for="min_price">Minimum Price (₹)</label>

                <input
                    type="number"
                    id="min_price"
                    name="min_price"
                    min="0"
                    step="0.01"
                    value="<?= e($minPriceInput) ?>"
                >
            </div>

            <div>
                <label for="max_price">Maximum Price (₹)</label>

                <input
                    type="number"
                    id="max_price"
                    name="max_price"
                    min="0"
                    step="0.01"
                    value="<?= e($maxPriceInput) ?>"
                >
            </div>

            <div>
                <label for="available">Availability</label>

                <select id="available" name="available">

                    <option
                        value="1"
                        <?= $availableOnly === "1" ? "selected" : "" ?>
                    >
                        Available Only
                    </option>

                    <option
                        value="0"
                        <?= $availableOnly === "0" ? "selected" : "" ?>
                    >
                        Include Unavailable
                    </option>

                </select>
            </div>

        </div>

        <div class="filter-actions">

            <button class="search-button" type="submit">
                Search &amp; Filter
            </button>

            <a class="reset-button" href="index.php">
                Reset Filters
            </a>

        </div>

    </form>

</section>

<h2 style="text-align:center;">Our Menu</h2>

<?php if ($message !== ""): ?>

    <p class="message"><?= e($message) ?></p>

<?php else: ?>

    <p class="result-count">
        <?= count($foods) ?> food item(s) found.
    </p>

    <section class="menu">

        <?php if (count($foods) > 0): ?>

            <?php foreach ($foods as $food): ?>

                <div class="food-card">

                    <?php if (!empty($food["image"])): ?>

                        <img
                            class="food-image"
                            src="<?= e($food["image"]) ?>"
                            alt="<?= e($food["name"]) ?>"
                            loading="lazy"
                        >

                    <?php endif; ?>

                    <h3><?= e($food["name"]) ?></h3>

                    <p><?= e($food["description"] ?? "") ?></p>

                    <p class="category">
                        Category: <?= e($food["category"] ?? "Other") ?>
                    </p>

                    <p class="price">
                        ₹<?= number_format((float)$food["price"], 2) ?>
                    </p>

                    <?php if ((int)$food["is_available"] === 1): ?>

                        <form action="add_to_cart.php" method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(generateCsrfToken()) ?>"
                            >

                            <input
                                type="hidden"
                                name="menu_item_id"
                                value="<?= (int)$food["id"] ?>"
                            >

                            <label for="quantity-<?= (int)$food["id"] ?>">
                                Quantity:
                            </label>

                            <input
                                type="number"
                                id="quantity-<?= (int)$food["id"] ?>"
                                name="quantity"
                                value="1"
                                min="1"
                                max="99"
                                required
                            >

                            <button type="submit">Add to Cart</button>

                        </form>

                    <?php else: ?>

                        <p>This item is currently unavailable.</p>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>No food items match your filters.</p>

        <?php endif; ?>

    </section>

<?php endif; ?>

</body>
</html>