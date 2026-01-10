<?php
$message = isset($_GET['message']) ? $_GET['message'] : "Unknown message.";
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : "index.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Message</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
        }
        .popup-box {
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 10px;
            display: inline-block;
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 15px;
            background: #007BFF;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="popup-box">
        <h2><?= htmlspecialchars($message) ?></h2>
        <a href="<?= htmlspecialchars($redirect) ?>" class="btn">OK</a>
    </div>
</body>
</html>
