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

// Keep messages always visible
$baseMessage = "";
$errorMessage = "";
$successMessage = ""; 
$isVerifiedSuccessfully = false; 

// Determine user's email from session
if (isset($_SESSION['verify_email'])) {
    $userEmail = $_SESSION['verify_email'];
}

// Check if redirected from login due to unverified email
if (isset($_GET['not_verified']) && $_GET['not_verified'] == 1) {
    if (isset($userEmail)) {
        $baseMessage = "❌ Your account is not verified yet. Enter the 6-digit code sent to <b>$userEmail</b> to verify your account.";
    } else {
        $baseMessage = "❌ Your account is not verified yet. Please register again.";
    }
}

// If coming from registration page normally
if (isset($userEmail) && empty($baseMessage)) {
    $baseMessage = "✅ Registration successful! Enter the 6-digit code sent to <b>$userEmail</b>. (Valid for 15 minutes)";
}

// Handle resend code request
if (isset($_POST['resend_code'])) {
    $newCode = rand(100000, 999999);
    $expiry_time = date("Y-m-d H:i:s", strtotime('+15 minutes'));

    $updateStmt = $conn->prepare("UPDATE user SET verification_code = ?, code_expiry = ? WHERE userEmail = ?");
    $updateStmt->bind_param("sss", $newCode, $expiry_time, $userEmail);
    $updateStmt->execute();
    $updateStmt->close();

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
        $mail->Subject = 'Your Verification Code';
        $mail->Body = "Your new 6-digit verification code is: <b>$newCode</b><br><br>This code will expire in 15 minutes.";
        $mail->send();
        $baseMessage = "✅ A new verification code has been sent to <b>$userEmail</b>.";
    } catch (Exception $e) {
        $errorMessage = "❌ Failed to resend verification code.";
    }
}

// If user submitted verification form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['code'])) {
    $code = trim($_POST['code']);

    // Check if code matches and fetch role
    $stmt = $conn->prepare("SELECT userRoles FROM user WHERE userEmail = ? AND verification_code = ? AND code_expiry >= NOW()");
    $stmt->bind_param("ss", $userEmail, $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $userRole = $userData['userRoles'];

        // Mark verified
        $update = $conn->prepare("UPDATE user SET is_verified = 1, verification_code = NULL, code_expiry = NULL WHERE userEmail = ?");
        $update->bind_param("s", $userEmail);
        $update->execute();

        // Show success screen
        $isVerifiedSuccessfully = true;

        if ($userRole === 'organizer') {
            $successMessage = "✅ Email verified successfully! You can now login to browse and join events. <br><br><b>Note:</b> Your organizer dashboard and event creation tools will be activated once the admin approves your organization name.";
        } else {
            $successMessage = "✅ Email verified successfully! You can now login to your account and start volunteering.";
        }

        // Clear session variable
        unset($_SESSION['verify_email']);

    } else {
        $errorMessage = "❌ Invalid or expired code. Please try again.";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Email Verification</title>
<link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
<style>
body { background-color: #f4f6f8; font-family: Arial, sans-serif; }
.verify-container { max-width: 500px; margin: 100px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; }
h2 { color: #333; margin-bottom: 20px; }
p { font-size: 16px; margin-bottom: 20px; line-height: 1.5; }
input { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 15px; box-sizing: border-box; margin-bottom: 15px; }
.btn { display: inline-block; text-align: center; padding: 10px 20px; border-radius: 6px; font-size: 15px; font-weight: bold; text-decoration: none; cursor: pointer; transition: background 0.3s; background: #007bff; color: #fff; border: none; width: 100%; box-sizing: border-box;}
.btn:hover { background: #0056b3; }
.btn-login { background: #28a745; margin-top: 15px; }
.btn-login:hover { background: #218838; }
.error { color: red; margin-top: -10px; margin-bottom: 15px; font-size: 15px; }
</style>
</head>
<body>

<div class="verify-container">
    <h2>Email Verification</h2>

    <?php if ($isVerifiedSuccessfully): ?>
        <p><?php echo $successMessage; ?></p>
        <a href="login.php" class="btn btn-login">Back to Login</a>
    <?php else: ?>
        <p><?php echo $baseMessage; ?></p>

        <?php if (!empty($errorMessage)): ?>
            <p class="error"><?php echo $errorMessage; ?></p>
        <?php endif; ?>

        <?php if (isset($userEmail)): ?>
        <form method="POST" action="">
            <input type="text" name="code" placeholder="Enter 6-digit code" required>
            <button type="submit" class="btn">Verify</button>
        </form>

        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="resend_code" value="1">
            <button type="submit" class="btn" style="background: #6c757d;">Resend Code</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>