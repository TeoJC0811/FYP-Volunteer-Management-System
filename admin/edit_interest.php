<?php
session_start();
include("../db.php");

// Only allow admins
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$categoryID = $_GET['id'] ?? null;
if (!$categoryID) {
    header("Location: manage_interest.php?error=InterestNotFound");
    exit();
}

// Fetch existing interest
$stmt = $conn->prepare("SELECT * FROM Category WHERE categoryID = ?");
$stmt->bind_param("i", $categoryID);
$stmt->execute();
$result = $stmt->get_result();
$interest = $result->fetch_assoc();

if (!$interest) {
    header("Location: manage_interest.php?error=InterestNotFound");
    exit();
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $categoryName = trim($_POST['categoryName']);

    if (!empty($categoryName)) {
        $stmt = $conn->prepare("UPDATE Category SET categoryName = ? WHERE categoryID = ?");
        $stmt->bind_param("si", $categoryName, $categoryID);

        if ($stmt->execute()) {
            header("Location: manage_interest.php?success=1");
            exit();
        } else {
            header("Location: manage_interest.php?error=UpdateFailed");
            exit();
        }
    } else {
        $error = "⚠️ Interest name cannot be empty.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Interest</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* Define the width to match edit_training */
        :root {
            --form-width: 600px;
        }

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
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            height: 44px;
        }
        button {
            margin-top: 20px;
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

        /* NEW WRAPPER TO ALIGN BACK BUTTON (Same as edit_training) */
        .back-link-wrapper {
            max-width: var(--form-width); 
            margin: 30px auto 10px auto; 
            text-align: left; 
            padding: 0 30px; 
        }

        /* NICE BACK BUTTON - Styled as simple link */
        .back-link {
            display: inline-block;
            padding: 0; 
            background: none; 
            color: #333 !important; 
            text-decoration: none;
            font-weight: normal; 
            transition: color 0.25s, text-decoration 0.25s;
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
        <a href="manage_interest.php" class="back-link">← Back to Manage Interests</a>
    </div>

    <div class="form-container">
        <h2>Edit Interest</h2>

        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

        <form method="post">
            <div class="form-group">
                <label for="categoryName">Interest Name</label>
                <input type="text" name="categoryName" id="categoryName" 
                       value="<?= htmlspecialchars($interest['categoryName']) ?>" required>
            </div>

            <button type="submit">Update Interest</button>
        </form>
    </div>
</div>

</body>
</html>