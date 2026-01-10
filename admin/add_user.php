<?php
session_start();
include("../db.php");

// Only allow admin
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success = $error = "";
$selectedCountry = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userName = trim($_POST['userName']);
    $userEmail = trim($_POST['userEmail']);
    $password = $_POST['password'];
    $dateOfBorn = $_POST['dateOfBorn'];
    $gender = $_POST['gender'];
    $country = trim($_POST['country']);
    $phoneNumber = trim($_POST['phoneNumber']);
    $role = $_POST['role'];

    $selectedCountry = $country; // Keep selected value if validation fails

    // Handle QR upload (only if organizer)
    $qrCodeUrl = null;
    if ($role === "organizer" && isset($_FILES["qrCode"]["name"]) && $_FILES["qrCode"]["error"] === 0) {
        $uploadDir = "../uploads/qrcodes/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["qrCode"]["name"]);
        $targetFile = $uploadDir . $fileName;
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ["jpg", "jpeg", "png"];

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["qrCode"]["tmp_name"], $targetFile)) {
                $qrCodeUrl = "uploads/qrcodes/" . $fileName;
            } else {
                $error = "❌ Failed to upload QR code.";
            }
        } else {
            $error = "⚠️ Only JPG, JPEG, and PNG files are allowed for QR.";
        }
    }

    if (!$error && !empty($userName) && !empty($userEmail) && !empty($password) && !empty($role)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO User (userName, userEmail, password, dateOfBorn, gender, country, phoneNumber, userRoles, totalPoints, qrCodeUrl) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $userName, $userEmail, $hashedPassword, $dateOfBorn, $gender, $country, $phoneNumber, $role, $qrCodeUrl);

        if ($stmt->execute()) {
            // ✅ Show success, then reload page with GET param to clear form
            header("Location: add_user.php?success=1");
            exit();
        } else {
            $error = "❌ Database error: " . $stmt->error;
        }
    } elseif (!$error) {
        $error = "⚠️ Please fill in all required fields.";
    }
}

// ✅ Show success alert after redirect (form will be empty)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "✅ User added successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User - Admin</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .form-container {
            max-width: 600px;
            margin: 30px auto;
            background: #f9f9f9;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display:block; margin-bottom: 5px; }
        input, select {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
            box-sizing: border-box;
        }
        button {
            margin-top: 15px;
            padding: 12px;
            width: 100%;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }
        button:hover { background: #0056b3; }
        .success { color:green; font-weight:bold; text-align:center; }
        .error { color:red; font-weight:bold; text-align:center; }
        .qr-section { display:none; }
    </style>
    <script>
        function toggleQRField() {
            const role = document.getElementById("role").value;
            const qrSection = document.getElementById("qrSection");
            qrSection.style.display = (role === "organizer") ? "block" : "none";
        }
    </script>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="form-container">
        <h2>Add User</h2>
        <?php if ($success) echo "<p class='success'>$success</p>"; ?>
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>

        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Full Name:</label>
                <input type="text" name="userName" required value="<?php echo htmlspecialchars($_POST['userName'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="userEmail" required value="<?php echo htmlspecialchars($_POST['userEmail'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Date of Birth:</label>
                <input type="date" name="dateOfBorn" value="<?php echo htmlspecialchars($_POST['dateOfBorn'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Gender:</label>
                <select name="gender">
                    <option value="">-- Select --</option>
                    <option value="Male" <?php if(($_POST['gender'] ?? '') === 'Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if(($_POST['gender'] ?? '') === 'Female') echo 'selected'; ?>>Female</option>
                    <option value="Other" <?php if(($_POST['gender'] ?? '') === 'Other') echo 'selected'; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Country:</label>
                <select name="country" required>
                    <option value="">-- Select Country --</option>
                    <?php
                    $result = $conn->query("SHOW COLUMNS FROM User LIKE 'country'");
                    $row = $result->fetch_assoc();
                    $enumStr = $row['Type'];
                    preg_match("/^enum\('(.*)'\)$/", $enumStr, $matches);
                    $enumValues = explode("','", $matches[1]);
                    foreach ($enumValues as $value) {
                        $sel = ($selectedCountry === $value) ? "selected" : "";
                        echo "<option value=\"$value\" $sel>$value</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Phone Number:</label>
                <input type="text" name="phoneNumber" value="<?php echo htmlspecialchars($_POST['phoneNumber'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Role:</label>
                <select name="role" id="role" required onchange="toggleQRField()">
                    <option value="">-- Select Role --</option>
                    <option value="user" <?php if(($_POST['role'] ?? '')==='user') echo 'selected'; ?>>User</option>
                    <option value="organizer" <?php if(($_POST['role'] ?? '')==='organizer') echo 'selected'; ?>>Organizer</option>
                    <option value="admin" <?php if(($_POST['role'] ?? '')==='admin') echo 'selected'; ?>>Admin</option>
                </select>
            </div>

            <div class="form-group qr-section" id="qrSection">
                <label>Upload Bank QR Code (for Organizer):</label>
                <input type="file" name="qrCode" accept="image/png, image/jpeg">
            </div>

            <button type="submit">Add User</button>
        </form>
    </div>
</div>

<script>
    // Keep QR visible when role = organizer
    toggleQRField();
</script>

</body>
</html>
