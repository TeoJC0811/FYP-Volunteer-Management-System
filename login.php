<?php
session_start();

/*
|------------------------------------------------------------------
| SAVE REDIRECT URL (SAFE VERSION)
|------------------------------------------------------------------
| - DO NOT override redirect if QR check-in is pending
| - Only save redirect for normal protected pages
|------------------------------------------------------------------
*/
if (!isset($_SESSION['userID'])) {

    // ❗ If QR check-in is pending, DO NOTHING
    if (!isset($_SESSION['pending_checkin'])) {

        if (!isset($_SESSION['redirect_after_login'])) {

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $currentUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

            // Avoid saving login page itself
            if (strpos($currentUrl, 'login.php') === false) {
                $_SESSION['redirect_after_login'] = $currentUrl;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Volunteering System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Ensuring all elements use consistent sizing */
        * {
            box-sizing: border-box;
        }

        .login-container {
            max-width: 500px; /* Optional: adjust as per your design */
            margin: 50px auto;
            padding: 20px;
        }

        .success-box, .info-box, .error-box {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }
        .success-box { background: #d4edda; color: #155724; }
        .info-box { background: #e3f2fd; color: #0d47a1; }
        .error-box { background: #f8d7da; color: #721c24; }

        /* Unified Width for Inputs and Labels */
        .form-group {
            margin-bottom: 15px;
            width: 100%;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%; /* Makes input same width as container */
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 42px; /* Adjusted to align with input center, considering label */
            cursor: pointer;
            color: #666;
            font-size: 18px;
        }
        .toggle-password:hover { color: #000; }

        .form-buttons {
            margin-top: 20px;
            width: 100%;
        }

        .btn-login {
            width: 100%; /* Matches the input width */
            padding: 12px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        .btn-login:hover { background-color: #218838; }

        .forgot-password { font-size: 14px; margin-top: 5px; margin-bottom: 15px; }
        .forgot-password a { color: #007bff; text-decoration: none; }
        .forgot-password a:hover { text-decoration: underline; }

        .google-login {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #db4437;
            color: white;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            width: 100%;
        }
        .google-login i { margin-right: 10px; }
        .google-login:hover { background-color: #c1351d; }

        .divider {
            text-align: center;
            margin: 20px 0;
            color: #666;
            position: relative;
        }
        .divider::before,
        .divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background-color: #ccc;
        }
        .divider::before { left: 0; }
        .divider::after { right: 0; }
    </style>
</head>
<body>

<?php if (isset($_GET['verified']) && $_GET['verified'] == 1): ?>
<script>alert("🎉 Your email has been successfully verified!");</script>
<?php endif; ?>

<div class="login-container">
    <h2>Login</h2>

    <?php if (isset($_SESSION['pending_checkin'])): ?>
        <div class="info-box">
            📸 Please log in to complete your attendance check-in.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
        <div class="success-box">
            🎉 Registration successful! Please log in.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="error-box">
            ❌ <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="process_login.php">
        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" required placeholder="Enter your email">
        </div>

        <div class="form-group password-wrapper">
            <label>Password *</label>
            <input type="password" id="password" name="password" required placeholder="Enter your password">
            <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
        </div>

        <div class="forgot-password">
            <a href="forgot_password.php">Forgot password?</a>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn-login">Login</button>
        </div>
    </form>

    <div class="divider">or</div>

    <a href="google_login.php" class="google-login">
        <i class="fa-brands fa-google"></i> Login with Google
    </a>
</div>

<script>
const togglePassword = document.querySelector("#togglePassword");
const passwordField = document.querySelector("#password");

togglePassword.addEventListener("click", () => {
    const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
    passwordField.setAttribute("type", type);
    togglePassword.classList.toggle("fa-eye");
    togglePassword.classList.toggle("fa-eye-slash");
});
</script>

</body>
</html>