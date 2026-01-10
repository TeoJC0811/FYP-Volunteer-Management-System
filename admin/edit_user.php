<?php
session_start();
include("../db.php");

// Only allow admins
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$userID = $_GET['id'] ?? null;
if (!$userID) {
    header("Location: manage_user.php?error=NoUserSelected");
    exit();
}

// Fetch existing user
$stmt = $conn->prepare("SELECT * FROM User WHERE userID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: manage_user.php?error=UserNotFound");
    exit();
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['userName']);
    $email = trim($_POST['userEmail']);
    $role = trim($_POST['userRoles']);

    // Handle QR upload only if organizer
    $qrPath = $user['qrCodeUrl'];
    if ($role === "organizer" && isset($_FILES['qrCode']) && $_FILES['qrCode']['error'] === 0) {
        $targetDir = "../uploads/qrcodes/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = "qr_" . time() . "_" . basename($_FILES["qrCode"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["qrCode"]["tmp_name"], $targetFile)) {
            $qrPath = "uploads/qrcodes/" . $fileName;
        }
    }

    if (!empty($name) && !empty($email) && !empty($role)) {
        $stmt = $conn->prepare("UPDATE User SET userName = ?, userEmail = ?, userRoles = ?, qrCodeUrl = ? WHERE userID = ?");
        $stmt->bind_param("ssssi", $name, $email, $role, $qrPath, $userID);

        if ($stmt->execute()) {
            header("Location: manage_user.php?success=1");
            exit();
        } else {
            header("Location: manage_user.php?error=UpdateFailed");
            exit();
        }
    } else {
        $error = "⚠️ All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* Consistency with other edit pages */
        :root {
            --form-width: 600px;
        }

        * { box-sizing: border-box; }

        .form-container {
            max-width: var(--form-width);
            margin: 0 auto;
            padding: 30px;
            background: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .form-container h2 {
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            height: 44px;
        }      
        .qr-preview {
            margin-top: 15px;
            text-align: center;
            background: #fff;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 6px;
        }
        .qr-preview img {
            max-width: 180px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-top: 8px;
        }
        button {
            margin-top: 25px;
            padding: 14px;
            width: 100%;
            background: #333;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background: #555; }

        /* BACK BUTTON WRAPPER (Aligned with form) */
        .back-link-wrapper {
            max-width: var(--form-width); 
            margin: 30px auto 10px auto; 
            text-align: left; 
            padding: 0 30px; 
        }

        .back-link {
            display: inline-block;
            color: #333 !important; 
            text-decoration: none;
            font-weight: normal; 
            transition: color 0.25s;
        }

        .back-link:hover {
            color: #000 !important; 
            text-decoration: underline;
        }

        .error {
            background:#f8d7da; 
            color:#721c24; 
            padding:10px; 
            border-radius:5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="back-link-wrapper">
        <a href="manage_user.php" class="back-link">← Back to Manage Users</a>
    </div>

    <div class="form-container">
        <h2>Edit User</h2>

        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="userName">Name</label>
                <input type="text" name="userName" id="userName" value="<?= htmlspecialchars($user['userName']) ?>" required>
            </div>

            <div class="form-group">
                <label for="userEmail">Email</label>
                <input type="email" name="userEmail" id="userEmail" value="<?= htmlspecialchars($user['userEmail']) ?>" required>
            </div>

            <div class="form-group">
                <label for="userRoles">Role</label>
                <select name="userRoles" id="userRoles" required onchange="toggleQR(this.value)">
                    <option value="user" <?= ($user['userRoles']=="user") ? "selected" : "" ?>>User</option>
                    <option value="organizer" <?= ($user['userRoles']=="organizer") ? "selected" : "" ?>>Organizer</option>
                    <option value="admin" <?= ($user['userRoles']=="admin") ? "selected" : "" ?>>Admin</option>
                </select>
            </div>

            <div class="form-group" id="qr-section" style="display: <?= ($user['userRoles']=="organizer") ? "block" : "none" ?>;">
                <label for="qrCode">Replace Organizer QR Code (Bank)</label>
                <input type="file" name="qrCode" id="qrCode" accept="image/*" style="height: auto; padding: 10px 12px;">
                
                <?php if (!empty($user['qrCodeUrl'])): ?>
                    <div class="qr-preview">
                        <p style="margin:0; font-size:13px; color:#666;">Current QR Code:</p>
                        <img src="../<?= htmlspecialchars($user['qrCodeUrl']) ?>" alt="QR Code">
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit">Update User</button>
        </form>
    </div>
</div>

<script>
function toggleQR(role) {
    const qrSection = document.getElementById("qr-section");
    qrSection.style.display = (role === "organizer") ? "block" : "none";
}
</script>

</body>
</html>