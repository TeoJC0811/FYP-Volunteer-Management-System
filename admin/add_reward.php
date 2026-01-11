<?php
session_start();
include("../db.php");

// Only allow admin
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success = $error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rewardName = trim($_POST['rewardName']);
    $description = trim($_POST['description']);
    $pointRequired = intval($_POST['pointRequired']);
    
    // Handle image upload
    $rewardImage = "";
    if (!empty($_FILES['rewardImage']['name'])) {
        $targetDir = "../uploads/rewards/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["rewardImage"]["name"]);
        $rewardImage = $targetDir . $fileName;

        if (!move_uploaded_file($_FILES["rewardImage"]["tmp_name"], $rewardImage)) {
            $error = "⚠️ Failed to upload image.";
        }
    }

    if (!empty($rewardName) && $pointRequired > 0 && !$error) {
        $sql = "INSERT INTO reward (rewardName, description, rewardImage, pointRequired) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $rewardName, $description, $rewardImage, $pointRequired);

        if ($stmt->execute()) {
            $success = "✅ Reward added successfully!";
        } else {
            $error = "❌ Database error: " . $stmt->error;
        }
    } elseif (!$error) {
        $error = "⚠️ Please fill all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Reward - Admin</title>
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
        input, textarea {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
            box-sizing: border-box;
        }
        textarea {
            min-height: 80px;
            resize: none; /* ✅ disable resize */
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
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="form-container">
        <h2>Add Reward</h2>
        <?php if ($success) echo "<p class='success'>$success</p>"; ?>
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>

        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Reward Name:</label>
                <input type="text" name="rewardName" required>
            </div>

            <div class="form-group">
                <label>Description:</label>
                <textarea name="description"></textarea>
            </div>

            <div class="form-group">
                <label>Reward Image:</label>
                <input type="file" name="rewardImage" accept="image/png, image/jpeg">
            </div>

            <div class="form-group">
                <label>Points Required:</label>
                <input type="number" name="pointRequired" required min="1">
            </div>

            <button type="submit">Add Reward</button>
        </form>
    </div>
</div>

</body>
</html>
