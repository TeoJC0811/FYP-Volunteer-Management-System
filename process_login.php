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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userEmail = trim($_POST['email']);
    $password = $_POST['password'];

    // Fetch user
    $stmt = $conn->prepare("SELECT * FROM user WHERE userEmail = ?");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $storedPassword = $user['password'];

        /*
        |------------------------------------------------------------------
        | 1. CHECK FOR GOOGLE-ONLY ACCOUNTS
        |------------------------------------------------------------------
        */
        if (empty($storedPassword)) {
            header("Location: login.php?error=" . urlencode("This account uses Google Login. Please click 'Login with Google'."));
            exit();
        }

        /*
        |------------------------------------------------------------------
        | 2. CHECK VERIFICATION STATUS
        |------------------------------------------------------------------
        */
        if ($user['is_verified'] == 0) {
            $verification_code = rand(100000, 999999);
            $expiry_time = date("Y-m-d H:i:s", strtotime('+15 minutes'));

            $updateStmt = $conn->prepare("UPDATE user SET verification_code = ?, code_expiry = ? WHERE userID = ?");
            $updateStmt->bind_param("ssi", $verification_code, $expiry_time, $user['userID']);
            $updateStmt->execute();
            $updateStmt->close();

            // Send verification email
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
                $mail->addAddress($userEmail, $user['userName']);
                $mail->isHTML(true);
                $mail->Subject = 'Your Verification Code';
                $mail->Body    = "Hello {$user['userName']},<br><br>Your 6-digit verification code is: <b>$verification_code</b>.<br>This code will expire in 15 minutes.";

                $mail->send();
            } catch (Exception $e) { }

            $_SESSION['verify_email'] = $userEmail;
            header("Location: verify.php?not_verified=1");
            exit();
        }

        /*
        |------------------------------------------------------------------
        | 3. PASSWORD VERIFICATION & LOGIN
        |------------------------------------------------------------------
        */
        if (password_verify($password, $storedPassword) || $password === $storedPassword) {
            
            // Legacy plain-text check: update to hash if necessary
            if ($password === $storedPassword && !password_get_info($storedPassword)['algo']) {
                $newHashed = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE user SET password = ? WHERE userID = ?");
                $upd->bind_param("si", $newHashed, $user['userID']);
                $upd->execute();
                $upd->close();
            }

            // Set session variables
            $_SESSION['userID'] = $user['userID'];
            $_SESSION['userName'] = $user['userName'];
            $_SESSION['userRoles'] = $user['userRoles'];
            
            /*
            |--------------------------------------------------------------
            | 4. REDIRECT LOGIC (QR Check-in or Normal Page)
            |--------------------------------------------------------------
            | If redirect_after_login is set (from checkin.php), send them there.
            | Otherwise, send them to the home page.
            */
            if (isset($_SESSION['redirect_after_login'])) {
                $targetUrl = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']); // Clear session
                header("Location: " . $targetUrl);
            } else {
                header("Location: index.php");
            }
            exit();

        } else {
            header("Location: login.php?error=Invalid+password");
            exit();
        }

    } else {
        header("Location: login.php?error=User+not+found");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>