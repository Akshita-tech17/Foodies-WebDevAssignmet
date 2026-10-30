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

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || trim($password) === "") {
        $message = "Please fill all fields.";
    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password, is_active
             FROM users
             WHERE email = ?"
        );

        if (!$stmt) {
            $message = "Something went wrong. Please try again.";
        } else {
            $stmt->bind_param("s", $email);

            if (!$stmt->execute()) {
                $message = "Something went wrong. Please try again.";
            } else {
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                if (
                    $user &&
                    password_verify($password, $user["password"])
                ) {
                    // Block deactivated accounts
                    if ((int)$user["is_active"] !== 1) {
                        $message = "Your account has been deactivated. Please contact the administrator.";
                    } else {
                        session_regenerate_id(true);

                        $_SESSION["user_id"] = $user["id"];
                        $_SESSION["user_name"] = $user["name"];

                        header("Location: index.php");
                        exit;
                    }
                } else {
                    $message = "Invalid email or password.";
                }
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
    <title>Login - Foodies</title>
    <link rel="stylesheet" href="/phpt/Foodies-WebDevAssignmet/assest/css/auth.css">
    <style>
  
</style>
</head>
<body class="auth-page">

    <h1>Login to Foodies</h1>

    <?php if ($message !== ""): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form action="login.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <br><br>

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <br><br>

        <button type="submit">Login</button>

    </form>

    <p>Don't have an account?
        <a href="signup.php">Sign Up</a>
    </p>

</body>
</html>