<?php
require_once 'config/db.php';
require_once 'config/csrf.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token
    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid request. Please refresh the page and try again.");
    }

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || trim($password) === "") {
        $message = "Please fill all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password)
             VALUES (?, ?, ?)"
        );

        if (!$stmt) {
            $message = "Something went wrong. Please try again.";
        } else {
            $stmt->bind_param(
                "sss",
                $name,
                $email,
                $hashedPassword
            );

            if ($stmt->execute()) {
                $message = "Account created successfully!";
            } elseif ($stmt->errno === 1062) {
                $message = "This email is already registered.";
            } else {
                $message = "Something went wrong. Please try again.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Foodies</title>
    <link rel="stylesheet" href="/phpt/Foodies-WebDevAssignmet/assest/css/auth.css">
</head>
<body class="auth-page signup-page">

    <h1>Create Your Account</h1>

    <?php if ($message !== ""): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form action="signup.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" required>

        <br><br>

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required>

        <br><br>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <br><br>

        <button type="submit">Create Account</button>
    </form>

</body>
</html>