<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db.php';

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if this is an organizer registration
$isOrganizer = isset($_GET['role']) && $_GET['role'] === 'organizer';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userEmail = trim($_POST['userEmail']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // PHP Server-side validation (Double Check)
    $specialChars = '/[\'\/~`\!@#\$%\^&\*\(\)_\-\+=\{\}\[\]\|;:"\<\>,\.\?\\\]/';
    if (strlen($password) < 8 || !preg_match($specialChars, $password)) {
        $error = "❌ Password must be at least 8 characters and include a special character.";
    } elseif ($password !== $confirmPassword) {
        $error = "❌ Passwords do not match. Please try again.";
    } else {
        // Determine role and username
        if ($isOrganizer) {
            $userRoles = 'organizer';
            $userName = trim($_POST['userName']); 
        } else {
            $userRoles = 'user';
            $userName = explode('@', $userEmail)[0]; 
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $status = 'pending'; 

        $check = $conn->prepare("SELECT * FROM user WHERE userEmail = ?");
        $check->bind_param("s", $userEmail);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "❌ This email is already registered.";
        } else {
            $verification_code = rand(100000, 999999);
            $expiry_time = date("Y-m-d H:i:s", strtotime('+15 minutes'));

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
                $mail->Body    = "Hello,<br><br>Your 6-digit verification code is: <b>$verification_code</b><br><br>This code will expire in 15 minutes.";

                $mail->send();

                $stmt = $conn->prepare("INSERT INTO user (userName, userEmail, password, userRoles, status, verification_code, code_expiry) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $userName, $userEmail, $hashedPassword, $userRoles, $status, $verification_code, $expiry_time);
                $stmt->execute();
                $stmt->close();

                $_SESSION['verify_email'] = $userEmail;
                header("Location: verify.php");
                exit();

            } catch (Exception $e) {
                $error = "❌ Verification email could not be sent. Please check your email address.";
            }
        }
        $check->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isOrganizer ? 'Organizer' : 'User'; ?> Register - Volunteering System</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; margin-bottom: 25px; color: #333; }
        .error-box { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; text-align: center; background: #f8d7da; color: #721c24; }
        .form-group { margin-bottom: 15px; width: 100%; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; }
        .password-wrapper { position: relative; }
        .toggle-password { position: absolute; right: 12px; top: 42px; cursor: pointer; color: #666; font-size: 18px; }
        .toggle-password:hover { color: #000; }
        .form-buttons { margin-top: 20px; width: 100%; display: flex; flex-direction: column; gap: 10px; }
        .btn-register { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px; }
        .btn-register:hover { background-color: #0056b3; }
        .btn-back { text-align: center; font-size: 14px; color: #666; text-decoration: none; margin-top: 5px; }
        .btn-back:hover { text-decoration: underline; }
        .google-login { display: flex; align-items: center; justify-content: center; background-color: #db4437; color: white; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 20px; width: 100%; }
        .google-login i { margin-right: 10px; }
        .divider { text-align: center; margin: 20px 0; color: #666; position: relative; }
        .divider::before, .divider::after { content: ""; position: absolute; top: 50%; width: 40%; height: 1px; background-color: #ccc; }
        .divider::before { left: 0; } .divider::after { right: 0; }
        .promoter-box { margin-top: 15px; text-align: center; font-size: 14px; color: #555; padding: 10px; border: 1px dashed #007bff; border-radius: 8px; }
        .promoter-box a { color: #007bff; font-weight: bold; text-decoration: none; }
        
        /* Requirement text color */
        .password-req {
            font-size: 12px;
            color: #d9534f;
            margin-top: 5px;
            display: block;
        }
    </style>
</head>
<body>

<div class="register-container">
    <h2><?php echo $isOrganizer ? 'Organizer Registration' : 'Create Account'; ?></h2>

    <?php if(isset($error)): ?>
        <div class="error-box"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="" onsubmit="return validateForm()">
        <?php if($isOrganizer): ?>
        <div class="form-group">
            <label>Organizer/Company Name *</label>
            <input type="text" name="userName" required placeholder="Enter organization name" value="<?php echo isset($_POST['userName']) ? htmlspecialchars($_POST['userName']) : ''; ?>">
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="userEmail" required placeholder="Enter your email" value="<?php echo isset($_POST['userEmail']) ? htmlspecialchars($_POST['userEmail']) : ''; ?>">
        </div>

        <div class="form-group password-wrapper">
            <label>Password *</label>
            <input type="password" id="password" name="password" required placeholder="Create a password">
            <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
            <small class="password-req">* Minimum 8 letters and at least 1 special character.</small>
        </div>

        <div class="form-group">
            <label>Confirm Password *</label>
            <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Re-enter your password">
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn-register"><?php echo $isOrganizer ? 'Register as Organizer' : 'Register'; ?></button>
            <a href="login.php" class="btn-back">Already have an account? Login</a>
        </div>
    </form>

    <?php if(!$isOrganizer): ?>
        <div class="promoter-box">
            Have event that want to be promote here? <br>
            <a href="register.php?role=organizer">Sign up as Organizer</a>
        </div>
        <div class="divider">or</div>
        <a href="google_login.php" class="google-login"><i class="fa-brands fa-google"></i> Sign up with Google</a>
    <?php else: ?>
        <a href="register.php" class="btn-back" style="color:#007bff; display:block; margin-top:20px;">Register as a volunteer instead</a>
    <?php endif; ?>
</div>

<script>
    // Toggle Password Visibility
    const togglePassword = document.querySelector("#togglePassword");
    const passwordField = document.querySelector("#password");
    togglePassword.addEventListener("click", () => {
        const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
        passwordField.setAttribute("type", type);
        togglePassword.classList.toggle("fa-eye");
        togglePassword.classList.toggle("fa-eye-slash");
    });

    // Form Validation Logic
    function validateForm() {
        const password = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirmPassword").value;
        
        // Regex for at least one special character
        const specialCharRegex = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/;

        if (password.length < 8) {
            alert("❌ Password must be at least 8 characters long.");
            return false;
        }

        if (!specialCharRegex.test(password)) {
            alert("❌ Password must contain at least one special character.");
            return false;
        }

        if (password !== confirmPassword) {
            alert("❌ Passwords do not match.");
            return false;
        }

        return true;
    }
</script>

</body>
</html>