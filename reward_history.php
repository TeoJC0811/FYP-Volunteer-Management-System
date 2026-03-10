<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// 1. Fetch Ongoing Claims (Status is Pending)
// Note: Changed table name to rewardclaims and column to status
$ongoingSql = "SELECT rc.*, r.rewardName, r.rewardImage 
               FROM rewardclaims rc 
               JOIN reward r ON rc.rewardID = r.rewardID 
               WHERE rc.userID = ? AND rc.status = 'Pending'
               ORDER BY rc.claimDate DESC";
$stmt1 = $conn->prepare($ongoingSql);
$stmt1->bind_param("i", $userID);
$stmt1->execute();
$ongoingClaims = $stmt1->get_result();

// 2. Fetch Past Claims (Status is Approved or Rejected)
$pastSql = "SELECT rc.*, r.rewardName, r.rewardImage 
            FROM rewardclaims rc 
            JOIN reward r ON rc.rewardID = r.rewardID 
            WHERE rc.userID = ? AND rc.status IN ('Approved', 'Rejected')
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .reward-history-container { max-width: 1000px; margin: 40px auto; padding: 20px; }
        .section-title { border-left: 5px solid #2ecc71; padding-left: 15px; margin: 40px 0 20px; font-size: 22px; }
        
        .claim-card { 
            display: flex; align-items: center; background: white; 
            padding: 15px; border-radius: 12px; margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee;
        }
        .claim-card img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; margin-right: 20px; }
        .claim-info { flex-grow: 1; }
        .claim-info h4 { margin: 0 0 5px 0; color: #333; }
        
        .status-badge { 
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        .tracking-info { margin-top: 5px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
<?php include("user_navbar.php"); ?>

<div class="reward-history-container">
    <h2>🎁 My Reward Claims</h2>
    <hr>

    <h3 class="section-title">Ongoing Claims</h3>
    <?php if ($ongoingClaims->num_rows > 0): ?>
        <?php while($row = $ongoingClaims->fetch_assoc()): ?>
            <div class="claim-card">
                <?php 
                    $rImg = $row['rewardImage'];
                    $imgSrc = (strpos($rImg, 'http') === 0) ? $rImg : 'uploads/reward/'.$rImg;
                ?>
                <img src="<?= $imgSrc ?>" alt="Reward">
                <div class="claim-info">
                    <h4><?= htmlspecialchars($row['rewardName']) ?></h4>
                    <small><i class="fa fa-calendar"></i> Claimed: <?= date('d M Y', strtotime($row['claimDate'])) ?></small>
                </div>
                <span class="status-badge status-pending">Processing</span>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="color: #888; padding: 10px;">You don't have any rewards currently being processed.</p>
    <?php endif; ?>

    <h3 class="section-title">Claim History</h3>
    <?php if ($pastClaims->num_rows > 0): ?>
        <?php while($row = $pastClaims->fetch_assoc()): ?>
            <div class="claim-card">
                <?php 
                    $rImg = $row['rewardImage'];
                    $imgSrc = (strpos($rImg, 'http') === 0) ? $rImg : 'uploads/reward/'.$rImg;
                ?>
                <img src="<?= $imgSrc ?>" alt="Reward">
                <div class="claim-info">
                    <h4><?= htmlspecialchars($row['rewardName']) ?></h4>
                    <small>Processed: <?= date('d M Y', strtotime($row['claimDate'])) ?></small>
                    
                    <?php if ($row['status'] === 'Approved' && !empty($row['trackingNumber'])): ?>
                        <div class="tracking-info">
                            <i class="fa fa-truck"></i> <?= htmlspecialchars($row['deliveryCompany']) ?>: <strong><?= htmlspecialchars($row['trackingNumber']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="status-badge status-<?= strtolower($row['status']) ?>">
                    <?= htmlspecialchars($row['status']) ?>
                </span>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="color: #888; padding: 10px;">Your claim history is empty.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>