<?php
session_start();
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reward Claim Successful</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .success-box {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            margin: 50px auto;
        }
        .success-box h1 {
            color: #28a745;
        }
        .success-box p {
            margin: 15px 0;
        }
        .success-box .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .success-box .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>

<?php include("user_navbar.php"); ?> <!-- ✅ Same navbar -->

<div class="main-content">
    <div class="success-box">
        <h1>🎉 Congratulations!</h1>
        <p>You have successfully claimed your reward.</p>
        <p>Our team will process your request and get your reward delivered soon.</p>
        <a href="reward.php" class="btn">⬅️ Back to Rewards</a>
    </div>
</div>

</body>
</html>
