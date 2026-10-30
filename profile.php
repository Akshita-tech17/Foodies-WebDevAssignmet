<?php
require_once __DIR__ . "/config/user_auth.php";
require_once __DIR__ . "/config/csrf.php";

$user_id = (int) $_SESSION["user_id"];
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh and try again.");
    }

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");

    if ($name === "" || strlen($name) > 150) {
        $message = "Please enter a valid name.";
    } elseif ($phone !== "" &&
        !preg_match('/^\+?[0-9]{10,14}$/', $phone)) {
        $message = "Enter a valid phone number (10-14 digits, optional +).";
    } elseif (strlen($address) > 2000) {
        $message = "Address is too long.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE users
             SET name = ?, phone = ?, address = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            $message = "Unable to update profile. Please try again.";
        } else {
            $stmt->bind_param("sssi", $name, $phone, $address, $user_id);

            if ($stmt->execute()) {
                $_SESSION["user_name"] = $name;
                $message = "Profile updated successfully.";
            } else {
                $message = "Unable to update profile. Please try again.";
            }

            $stmt->close();
        }
    }
}

$stmt = $conn->prepare(
    "SELECT name, email, phone, address
     FROM users
     WHERE id = ?"
);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load profile.");
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Unable to load profile.");
}

$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
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

    <title>My Profile - Foodies</title>

    <link rel="stylesheet" href="/phpt/Foodies-WebDevAssignmet/assest/css/profile.css">
</head>

<body class="profile-page">

    <main class="profile-container">

        <header class="profile-header">
            <div class="profile-avatar" aria-hidden="true">
                <?= e(strtoupper(substr(trim($user["name"]), 0, 1))) ?>
            </div>

            <h1>My Profile</h1>
            <p>Manage your personal information</p>
        </header>

        <a href="index.php" class="back-link">
            &larr; Back to Foodies
        </a>

        <section class="profile-card">

            <div class="profile-email">
                <span>Email Address</span>
                <strong><?= e($user["email"]) ?></strong>
            </div>

            <?php if ($message !== ""): ?>
                <p class="profile-message" role="status">
                    <?= e($message) ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="profile.php" class="profile-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(generateCsrfToken()) ?>"
                >

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="150"
                        value="<?= e($user["name"]) ?>"
                        placeholder="Enter your full name"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        maxlength="15"
                        value="<?= e($user["phone"] ?? "") ?>"
                        placeholder="Enter phone number"
                    >
                </div>

                <div class="form-group">
                    <label for="address">Delivery Address</label>
                    <textarea
                        id="address"
                        name="address"
                        rows="5"
                        maxlength="2000"
                        placeholder="Enter your complete delivery address"
                    ><?= e($user["address"] ?? "") ?></textarea>
                </div>

                <button type="submit" class="save-profile-btn">
                    Save Profile
                </button>

            </form>

        </section>

    </main>

</body>
</html>