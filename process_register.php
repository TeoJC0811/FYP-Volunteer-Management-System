<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur'); // Set timezone for expiry
include 'db.php'; 

// Include PHPMailer
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userName = trim($_POST['userName']);
    $userEmail = trim($_POST['userEmail']);
    $password = $_POST['password'];
    $dateOfBorn = $_POST['dateOfBorn'];
    $gender = $_POST['gender'];
    $country = $_POST['country'];
    $phoneNumber = $_POST['phoneNumber'];

    // 1. Basic validation
    if (empty($userName) || empty($userEmail) || empty($password)) {
        header("Location: register.php?error=All fields are required.");
        exit;
    }

    // 2. Check if email already exists
    $stmt = $conn->prepare("SELECT userID FROM user WHERE userEmail = ?");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        header("Location: register.php?error=Email already registered.");
        exit;
    }
    $stmt->close();

    // 3. Secure Password and Generate Verification Code
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $verification_code = rand(100000, 999999);
    $expiry_time = date("Y-m-d H:i:s", strtotime('+15 minutes'));

    // 4. Insert into database with is_verified = 0
    $stmt = $conn->prepare("INSERT INTO user (userName, userEmail, password, dateOfBorn, gender, country, phoneNumber, totalPoints, userRoles, verification_code, code_expiry, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'user', ?, ?, 0)");
    $stmt->bind_param("sssssssss", $userName, $userEmail, $hashedPassword, $dateOfBorn, $gender, $country, $phoneNumber, $verification_code, $expiry_time);

    if ($stmt->execute()) {
        // 5. Send verification email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'teojuncheng11@gmail.com';
            $mail->Password = 'irob djou wupz mpmb'; 
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('teojuncheng11@gmail.com', 'ServeTogether System');
            $mail->addAddress($userEmail, $userName);
            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Account';
            $mail->Body    = "<h2>Welcome, $userName!</h2>
                              <p>Your 6-digit verification code is: <b style='font-size: 24px; color: #28a745;'>$verification_code</b></p>
                              <p>This code is valid for 15 minutes.</p>";

            $mail->send();

            // Set session so verify.php knows which email to check
            $_SESSION['verify_email'] = $userEmail;
            header("Location: verify.php");
            exit();

        } catch (Exception $e) {
            // If email fails, still send them to verify.php so they can try "Resend Code"
            $_SESSION['verify_email'] = $userEmail;
            header("Location: verify.php?error=Account created but email failed to send. Click Resend.");
            exit();
        }
    } else {
        header("Location: register.php?error=Registration failed. Please try again.");
        exit();
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: register.php");
    exit();
}
?>