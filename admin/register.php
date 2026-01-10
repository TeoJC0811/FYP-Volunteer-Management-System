<?php
session_start();
include("../db.php");

// Handle form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm']);

    // Check passwords match
    if ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert new admin into User table
        $sql = "INSERT INTO User (userName, userEmail, password, userRoles) VALUES (?, ?, ?, 'admin')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $email, $hashedPassword);

        if ($stmt->execute()) {
            // Redirect to login page with success flag
            header("Location: login.php?registered=1");
            exit();
        } else {
            $error = "❌ Error: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Registration</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .register-box {
            width: 320px; margin: 100px auto; padding: 20px;
            background: white; border-radius: 8px; box-shadow: 0 0 10px #ccc;
        }
        input { width: 100%; padding: 8px; margin: 8px 0; }
        button { width: 100%; padding: 8px; background: #333; color: white; border: none; cursor: pointer; }
        button:hover { background: #555; }
        .error { color: red; font-size: 14px; }
    </style>
</head>
<body>
    <div class="register-box">
        <h2>Admin Registration</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="post" action="">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirm" placeholder="Confirm Password" required>
            <button type="submit">Register Admin</button>
        </form>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
