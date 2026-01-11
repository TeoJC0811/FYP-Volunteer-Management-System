<?php
session_start();
include("../db.php");

// Handle login form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Query only admin and organizer accounts
    $sql = "SELECT * FROM user WHERE userEmail = ? AND (userRoles = 'admin' OR userRoles = 'organizer') LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Save session values
            $_SESSION['userID']   = $user['userID'];
            $_SESSION['role']     = $user['userRoles'];
            $_SESSION['userName'] = $user['userName'];

            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No admin/organizer account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin / Organizer Login</title>
    <!-- Font Awesome for eye icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f4f4f4; 
        }
        .login-box {
            width: 320px; 
            margin: 100px auto; 
            padding: 30px;
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 0 10px #ccc;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group {
            position: relative;
            margin: 10px 0;
        }
        input, button {
            width: 100%; 
            padding: 10px; 
            box-sizing: border-box;
            font-size: 14px;
            border-radius: 4px;
        }
        input {
            border: 1px solid #ccc;
        }
        button {
            margin-top: 10px;
            background: #333; 
            color: white; 
            border: none; 
            cursor: pointer; 
            font-weight: bold;
        }
        button:hover { 
            background: #555; 
        }
        .error { 
            color: red; 
            font-size: 14px; 
            margin-bottom: 10px; 
            text-align: center;
        }
        .notification {
            background: #4CAF50;
            color: white;
            padding: 12px;
            margin: 10px auto;
            width: 100%;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .toggle-password {
            position: absolute;
            right: 5px;
            top: 50%; /* 👈 moved down a bit */
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 16px;
            padding: 5px;
        }
        .toggle-password:hover {
            color: #000;
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['registered'])): ?>
        <div id="notification" class="notification">
            ✅ Registration successful! Please login.
        </div>
        <script>
            setTimeout(() => {
                const note = document.getElementById("notification");
                if (note) note.style.display = "none";
            }, 3000);
        </script>
    <?php endif; ?>

    <div class="login-box">
        <h2>Admin / Organizer Login</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="post" action="">
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" required>
            </div>
            <div class="form-group">
                <input type="password" id="password" name="password" placeholder="Password" required>
                <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector("#togglePassword");
        const passwordField = document.querySelector("#password");

        togglePassword.addEventListener("click", function () {
            const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
            passwordField.setAttribute("type", type);

            this.classList.toggle("fa-eye");
            this.classList.toggle("fa-eye-slash");
        });
    </script>
</body>
</html>
