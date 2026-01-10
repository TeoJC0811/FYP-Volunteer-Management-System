<?php
session_start();
include("../db.php");

// Only allow admins
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$success = $error = "";

// Handle form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $categoryName = trim($_POST['categoryName']);

    if (!empty($categoryName)) {
        $sql = "INSERT INTO Category (categoryName) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $categoryName);

        if ($stmt->execute()) {
            $success = "✅ Interest added successfully!";
        } else {
            $error = "❌ Database error: " . $stmt->error;
        }
    } else {
        $error = "⚠️ Please enter an interest name.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Interest - Admin</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>"> <!-- cache bust -->
    <style>
        .form-container {
            max-width: 600px;
            margin: 30px auto;
            background: #f9f9f9;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { 
            text-align: center; 
            margin-bottom: 20px; 
        }
        .form-group { 
            margin-bottom: 15px; 
        }
        label { 
            font-weight: bold; 
            display:block; 
            margin-bottom: 5px; 
        }
        input {
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
        button:hover { 
            background: #0056b3; 
        }
        .success { 
            color:green; 
            font-weight:bold; 
            text-align:center; 
        }
        .error { 
            color:red; 
            font-weight:bold; 
            text-align:center; 
        }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="form-container">
        <h2>Add Interest</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p class='success'>$success</p>"; ?>

        <form method="post" action="">
            <div class="form-group">
                <label>Interest Name:</label>
                <input type="text" name="categoryName" placeholder="Enter interest name" required>
            </div>

            <button type="submit" name="add">Add Interest</button>
        </form>
    </div>
</div>

</body>
</html>
