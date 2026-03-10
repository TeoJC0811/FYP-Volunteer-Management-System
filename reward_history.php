<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// Fetch All Claims joined with Reward details
$sql = "SELECT rc.*, r.rewardName, r.rewardImage 
        FROM rewardclaims rc 
        JOIN reward r ON rc.rewardID = r.rewardID 
        WHERE rc.userID = ? 
        ORDER BY rc.claimDate DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

$ongoing = [];
$past = [];

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'Pending') {
        $ongoing[] = $row;
    } else {
        $past[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reward Claim History | ServeTogether</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .reward-history-container { max-width: 1100px; margin: 40px auto; padding: 0 20px; font-family: 'Segoe UI', sans-serif; }
        .section-header { border-bottom: 2px solid #eee; padding-bottom: 10px; margin: 40px 0 20px; display: flex; align-items: center; gap: 10px; color: #2c3e50; }
        
        .claim-card { 
            background: white; border-radius: 15px; margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eef0f2;
            overflow: hidden;
        }
        
        .card-header { display: flex; padding: 20px; align-items: center; background: #fff; border-bottom: 1px dashed #ddd; }
        .reward-img { width: 100px; height: 100px; object-fit: cover; border-radius: 12px; margin-right: 20px; border: 1px solid #eee; }
        
        .header-info { flex-grow: 1; }
        .header-info h3 { margin: 0; color: #333; font-size: 22px; }
        .claim-id { font-size: 11px; color: #999; font-weight: bold; letter-spacing: 1px; }

        .status-badge { 
            padding: 8px 16px; border-radius: 30px; font-size: 12px; font-weight: 700; 
        }
        .status-pending { background: #fff9db; color: #f0932b; }
        .status-approved { background: #ebfbee; color: #27ae60; }
        .status-rejected { background: #fff5f5; color: #eb4d4b; }

        /* Full Info Grid */
        .info-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 20px; padding: 20px; background: #fdfdfd;
        }
        .info-group { display: flex; flex-direction: column; }
        .info-group label { font-size: 11px; font-weight: bold; color: #95a5a6; text-transform: uppercase; margin-bottom: 4px; }
        .info-group span { font-size: 14px; color: #2c3e50; font-weight: 500; }
        
        .full-width { grid-column: 1 / -1; background: #f1f2f6; padding: 12px; border-radius: 8px; }
        .tracking-highlight { color: #007bff !important; font-family: 'Courier New', monospace; font-weight: bold !important; font-size: 16px !important; }
    </style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="reward-history-container">
    <h2><i class="fa-solid fa-gift"></i> Reward Claim History</h2>
    <p>Track your redemptions and shipping status below.</p>

    <div class="section-header">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <h3>Ongoing Claims</h3>
    </div>

    <?php if (empty($ongoing)): ?>
        <p style="color: #999; text-align: center; margin: 20px;">No active claims at this time.</p>
    <?php else: foreach ($ongoing as $row): renderClaimCard($row); endforeach; endif; ?>

    <div class="section-header">
        <i class="fa-solid fa-history"></i>
        <h3>Past Redemptions</h3>
    </div>

    <?php if (empty($past)): ?>
        <p style="color: #999; text-align: center; margin: 20px;">Your claim history is empty.</p>
    <?php else: foreach ($past as $row): renderClaimCard($row); endforeach; endif; ?>
</div>

<?php 
function renderClaimCard($row) {
    // --- Image Logic (Matched to your reward.php) ---
    $dbImg = $row['rewardImage'] ?? '';
    $imagePath = 'https://via.placeholder.com/100';
    if (!empty($dbImg)) {
        if (strpos($dbImg, 'http') === 0) {
            $imagePath = $dbImg;
        } else {
            // Note: Updated to 'rewards' to match your reward.php
            $imagePath = 'uploads/rewards/' . basename($dbImg);
        }
    }
    
    $statusClass = 'status-' . strtolower($row['status']);
?>
    <div class="claim-card">
        <div class="card-header">
            <img src="<?= htmlspecialchars($imagePath) ?>" class="reward-img">
            <div class="header-info">
                <span class="claim-id">ST-REDEMPTION-#<?= $row['claimID'] ?></span>
                <h3><?= htmlspecialchars($row['rewardName']) ?></h3>
                <span><i class="fa fa-calendar-check"></i> Requested on: <?= date('d M Y', strtotime($row['claimDate'])) ?></span>
            </div>
            <div class="status-badge <?= $statusClass ?>">
                <?= htmlspecialchars($row['status']) ?>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-group">
                <label>Recipient Name</label>
                <span><?= htmlspecialchars($row['recipientName'] ?: 'N/A') ?></span>
            </div>
            <div class="info-group">
                <label>Contact Number</label>
                <span><?= htmlspecialchars($row['phoneNumber'] ?: 'N/A') ?></span>
            </div>

            <div class="info-group">
                <label>Delivery Company</label>
                <span><?= htmlspecialchars($row['deliveryCompany'] ?: 'To be assigned') ?></span>
            </div>
            <div class="info-group">
                <label>Tracking Number</label>
                <span class="tracking-highlight"><?= htmlspecialchars($row['trackingNumber'] ?: 'Pending') ?></span>
            </div>
            <div class="info-group">
                <label>Estimated Arrival (ETA)</label>
                <span><?= htmlspecialchars($row['etaText'] ?: 'Processing...') ?></span>
            </div>

            <div class="info-group full-width">
                <label>Delivery Address</label>
                <span><?= nl2br(htmlspecialchars($row['deliveryAddress'])) ?></span>
            </div>
        </div>
    </div>
<?php } ?>

<?php include 'includes/footer.php'; ?>
</body>
</html>