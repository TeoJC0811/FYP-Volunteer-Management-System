<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur'); // Malaysia timezone
include 'db.php';

// Include PHPMailer
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$step = 1; // Step 1: ask for email, Step 2: enter code + new password
$baseMessage = "";
$errorMessage = "";

// Step 1: User submits email to request reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['userEmail']) && !isset($_POST['code'])) {
    $userEmail = trim($_POST['userEmail']);

    // Check if user exists
    $stmt = $conn->prepare("SELECT * FROM user WHERE userEmail = ?");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // User exists, generate 6-digit code
        $reset_code = rand(100000, 999999);
        $expiry_time = date("Y-m-d H:i:s", strtotime('+15 minutes'));

        // Save code and expiry to database
        $update = $conn->prepare("UPDATE user SET reset_code = ?, reset_expiry = ? WHERE userEmail = ?");
        $update->bind_param("sss", $reset_code, $expiry_time, $userEmail);
        $update->execute();
        $update->close();

        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'teojuncheng11@gmail.com';
            $mail->Password = 'irob djou wupz mpmb';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('teojuncheng11@gmail.com', 'ServeTogether Volunteering System');
            $mail->addAddress($userEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code';
            $mail->Body = "Hello,<br><br>Your password reset code is: <b>$reset_code</b>. It is valid for 15 minutes.";

            $mail->send();

            $_SESSION['reset_email'] = $userEmail;
            $step = 2;
            $baseMessage = "✅ A 6-digit code has been sent to <b>$userEmail</b>. Enter the code and your new password below.";
        } catch (Exception $e) {
            $errorMessage = "❌ Failed to send reset code. Please check your email and try again.";
        }
    } else {
        $errorMessage = "❌ Email not found. Please enter a registered email.";
    }

    $stmt->close();
}

// Step 2: User submits code + new password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['code']) && isset($_SESSION['reset_email'])) {
    $userEmail = $_SESSION['reset_email'];
    $code = trim($_POST['code']);
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    if ($newPassword !== $confirmPassword) {
        $errorMessage = "❌ Passwords do not match. Please try again.";
        $step = 2;
    } else {
        // Check if code is correct and not expired
        $stmt = $conn->prepare("SELECT * FROM user WHERE userEmail = ? AND reset_code = ? AND reset_expiry >= NOW()");
        $stmt->bind_param("ss", $userEmail, $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE user SET password = ?, reset_code = NULL, reset_expiry = NULL WHERE userEmail = ?");
            $update->bind_param("ss", $hashedPassword, $userEmail);
            $update->execute();
            $update->close();

            // Clear session
            unset($_SESSION['reset_email']);

            $baseMessage = "✅ Password successfully updated! You can now <a href='login.php'>login</a>.";
            $step = 3; // Done
        } else {
            $errorMessage = "❌ Invalid or expired code. Please request a new reset code.";
            $step = 2;
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password - Volunteering System</title>
<link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
<style>
body { background-color: #f4f6f8; font-family: Arial, sans-serif; }
.login-container { max-width: 450px; margin: 80px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
h2 { text-align: center; margin-bottom: 25px; color: #333; }
.form-group { margin-bottom: 18px; }
label { font-weight: bold; display: block; margin-bottom: 6px; }
input { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 15px; box-sizing: border-box; }
.btn-login, .btn-submit { display: inline-block; text-align: center; padding: 10px 20px; border-radius: 6px; font-size: 15px; font-weight: bold; text-decoration: none; cursor: pointer; transition: background 0.3s; background: #007bff; color: #fff; border: none; }
.btn-login:hover, .btn-submit:hover { background: #0056b3; }
.error { color: red; margin-bottom: 15px; text-align: center; }
p { text-align: center; font-size: 16px; }
.form-buttons { display: flex; justify-content: flex-end; align-items: center; margin-top: 20px; }
</style>
</head>
<body>

<div class="login-container">
    <h2>Forgot Password</h2>

    <?php if (!empty($baseMessage)) echo "<p>$baseMessage</p>"; ?>
    <?php if (!empty($errorMessage)) echo "<p class='error'>$errorMessage</p>"; ?>

    <?php if ($step == 1): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="userEmail">Enter your registered Email <span style="color:red">*</span></label>
                <input type="email" id="userEmail" name="userEmail" required placeholder="you@example.com">
            </div>
            <div class="form-buttons" style="justify-content: space-between;">
                <a href="login.php" class="btn-submit" style="background:#28a745;">Back to Login</a>
                <button type="submit" class="btn-login">Send Reset Code</button>
            </div>
        </form>

    <?php elseif ($step == 2): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="newPassword">New Password <span style="color:red">*</span></label>
                <input type="password" id="newPassword" name="newPassword" required placeholder="New password">
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm Password <span style="color:red">*</span></label>
                <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Confirm password">
            </div>
            <div class="form-group">
                <label for="code">Enter 6-digit code <span style="color:red">*</span></label>
                <input type="text" id="code" name="code" required placeholder="123456">
            </div>
            <div class="form-buttons">
                <button type="submit" class="btn-login">Update Password</button>
            </div>
        </form>
    <?php endif; ?>

</div>

</body>
</html>
