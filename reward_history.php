<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// Fetch Ongoing Claims (Pending)
$ongoingSql = "SELECT rc.*, r.rewardName, r.rewardImage 
               FROM rewardclaim rc 
               JOIN reward r ON rc.rewardID = r.rewardID 
               WHERE rc.userID = ? AND rc.claimStatus = 'pending'
               ORDER BY rc.claimDate DESC";
$stmt1 = $conn->prepare($ongoingSql);
$stmt1->bind_param("i", $userID);
$stmt1->execute();
$ongoingClaims = $stmt1->get_result();

// Fetch Past Claims (Approved or Rejected)
$pastSql = "SELECT rc.*, r.rewardName, r.rewardImage 
            FROM rewardclaim rc 
            JOIN reward r ON rc.rewardID = r.rewardID 
            WHERE rc.userID = ? AND rc.claimStatus IN ('approved', 'rejected')
            ORDER BY rc.claimDate DESC";
$stmt2 = $conn->prepare($pastSql);
$stmt2->bind_param("i", $userID);
$stmt2->execute();
$pastClaims = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Reward Progress</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <style>
        .reward-history-container { max-width: 1000px; margin: 40px auto; padding: 20px; }
        .section-title { border-left: 5px solid #2ecc71; padding-left: 15px; margin: 30px 0 20px; }
        
        .claim-card { 
            display: flex; align-items: center; background: white; 
            padding: 15px; border-radius: 12px; margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee;
        }
        .claim-card img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; margin-right: 20px; }
        .claim-info { flex-grow: 1; }
        .status-badge { 
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<?php include("user_navbar.php"); ?>

<div class="reward-history-container">
    <h2>🎁 My Reward Claims</h2>
    <p>Track your points redemption and view your history.</p>

    <h3 class="section-title">Ongoing Claims</h3>
    <?php if ($ongoingClaims->num_rows > 0): ?>
        <?php while($row = $ongoingClaims->fetch_assoc()): ?>
            <div class="claim-card">
                <img src="<?= (strpos($row['rewardImage'], 'http') === 0) ? $row['rewardImage'] : 'uploads/reward/'.$row['rewardImage'] ?>" alt="">
                <div class="claim-info">
                    <h4><?= htmlspecialchars($row['rewardName']) ?></h4>
                    <small>Claimed on: <?= date('d M Y', strtotime($row['claimDate'])) ?></small>
                </div>
                <span class="status-badge status-pending">Processing</span>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="color: #888;">No ongoing claims at the moment.</p>
    <?php endif; ?>

    <h3 class="section-title">Claim History</h3>
    <?php if ($pastClaims->num_rows > 0): ?>
        <?php while($row = $pastClaims->fetch_assoc()): ?>
            <div class="claim-card">
                <img src="<?= (strpos($row['rewardImage'], 'http') === 0) ? $row['rewardImage'] : 'uploads/reward/'.$row['rewardImage'] ?>" alt="">
                <div class="claim-info">
                    <h4><?= htmlspecialchars($row['rewardName']) ?></h4>
                    <small>Processed on: <?= date('d M Y', strtotime($row['claimDate'])) ?></small>
                </div>
                <span class="status-badge status-<?= strtolower($row['claimStatus']) ?>">
                    <?= htmlspecialchars($row['claimStatus']) ?>
                </span>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="color: #888;">Your reward history is empty.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>