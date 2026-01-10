<?php
session_start();
include("../db.php");

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$rewardID = $_GET['id'] ?? null;
if (!$rewardID) {
    header("Location: manage_reward.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM reward WHERE rewardID = ?");
$stmt->bind_param("i", $rewardID);
$stmt->execute();
$result = $stmt->get_result();
$reward = $result->fetch_assoc();

if (!$reward) {
    header("Location: manage_reward.php?error=RewardNotFound");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['rewardName']);
    $desc = trim($_POST['description']);
    $points = intval($_POST['pointRequired']);
    $imagePath = $reward['rewardImage'];

    if (!empty($_FILES['rewardImage']['name'])) {
        $targetDir = "../uploads/rewards/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = time() . "_" . basename($_FILES["rewardImage"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["rewardImage"]["tmp_name"], $targetFile)) {
            $imagePath = "uploads/rewards/" . $fileName;
        } else {
            header("Location: manage_reward.php?error=UploadFailed");
            exit();
        }
    }

    $stmt = $conn->prepare("UPDATE reward SET rewardName = ?, description = ?, pointRequired = ?, rewardImage = ? WHERE rewardID = ?");
    $stmt->bind_param("ssisi", $name, $desc, $points, $imagePath, $rewardID);

    if ($stmt->execute()) {
        header("Location: manage_reward.php?success=1");
        exit();
    } else {
        header("Location: manage_reward.php?error=UpdateFailed");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Reward</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* Consistency with edit_training */
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

        label { 
            display: block; 
            margin-top: 15px; 
            font-weight: bold;
        }

        input:not([type="submit"]):not([type="button"]), 
        textarea {
            width: 100%; 
            padding: 12px; 
            margin-top: 5px;
            border: 1px solid #ccc; 
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        input:not([type="file"]):not([type="submit"]) {
            height: 44px;
        }

        textarea { resize: none; height: 120px; }

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

        /* BACK BUTTON WRAPPER */
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

        .preview-img {
            margin-top: 10px;
            max-width: 200px;
            border: 1px solid #ddd;
            border-radius: 6px;
            display: block;
        }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="back-link-wrapper">
        <a href="manage_reward.php" class="back-link">← Back to Manage Rewards</a>
    </div>

    <div class="form-container">
        <h2>Edit Reward</h2>

        <?php if ($reward): ?>
        <form method="post" enctype="multipart/form-data">
            <label for="rewardName">Reward Name</label>
            <input type="text" name="rewardName" id="rewardName" 
                   value="<?= htmlspecialchars($reward['rewardName']) ?>" required>

            <label for="description">Description</label>
            <textarea name="description" id="description" required><?= htmlspecialchars($reward['description']) ?></textarea>

            <label for="pointRequired">Points Required</label>
            <input type="number" name="pointRequired" id="pointRequired" 
                   value="<?= htmlspecialchars($reward['pointRequired']) ?>" required>

            <label>Current Reward Image</label>
            <?php if (!empty($reward['rewardImage'])): ?>
                <img id="preview" src="../<?= htmlspecialchars($reward['rewardImage']) ?>" alt="Reward" class="preview-img">
            <?php else: ?>
                <img id="preview" class="preview-img" style="display:none;">
            <?php endif; ?>

            <label for="rewardImage">Replace Reward Image</label>
            <input type="file" name="rewardImage" id="rewardImage" accept="image/*">

            <button type="submit">Update Reward</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('rewardImage').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('preview');
            preview.src = e.target.result;
            preview.style.display = "block";
        }
        reader.readAsDataURL(file);
    }
});
</script>

</body>
</html>