<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// Fetch all claims with reward details
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
    // Matching your ENUM values exactly
    if ($row['status'] === 'pending') {
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
    <title>My Reward Progress</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .reward-history-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; font-family: 'Segoe UI', sans-serif; }
        .section-header { border-bottom: 2px solid #eee; padding-bottom: 10px; margin: 40px 0 20px; display: flex; align-items: center; gap: 10px; }
        
        .claim-card { 
            background: white; border-radius: 12px; margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #eef0f2;
            overflow: hidden;
        }
        
        .card-header { display: flex; padding: 20px; align-items: center; border-bottom: 1px solid #f1f1f1; }
        .reward-img { width: 90px; height: 90px; object-fit: cover; border-radius: 10px; margin-right: 20px; border: 1px solid #ddd; }
        
        .header-info { flex-grow: 1; }
        .header-info h3 { margin: 0; color: #333; font-size: 20px; }
        .claim-id { font-size: 11px; color: #999; font-weight: bold; }

        /* Status Colors based on your ENUM */
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff9db; color: #f0932b; }
        .status-delivered { background: #ebfbee; color: #27ae60; }
        .status-rejected { background: #fff5f5; color: #eb4d4b; }

        .info-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; padding: 20px; background: #fafafa;
        }
        .info-item label { display: block; font-size: 10px; font-weight: bold; color: #aaa; text-transform: uppercase; margin-bottom: 3px; }
        .info-item span { font-size: 13px; color: #444; font-weight: 500; }
        .full-row { grid-column: 1 / -1; background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #eee; }

        /* Minimal Progress Tracker */
        .progress-track { display: flex; padding: 0 20px 20px; gap: 10px; align-items: center; font-size: 12px; color: #888; }
        .dot { height: 10px; width: 10px; background: #ddd; border-radius: 50%; }
        .dot.active { background: #2ecc71; box-shadow: 0 0 8px #2ecc71; }
    </style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="reward-history-container">
    <h2><i class="fa-solid fa-gift" style="color: #2ecc71;"></i> My Reward Claims</h2>

    <div class="section-header">
        <i class="fa-solid fa-truck-fast"></i>
        <h3>Ongoing Claims</h3>
    </div>

    <?php if (empty($ongoing)): ?>
        <p style="color: #999; text-align: center; padding: 20px;">No active claims at the moment.</p>
    <?php else: foreach ($ongoing as $row): ?>
        <div class="claim-card">
            <?php renderCardHeader($row); ?>
            <div class="progress-track">
                <div class="dot active"></div> <span>Claimed</span>
                <div style="flex:1; height:2px; background:#eee;"></div>
                <div class="dot"></div> <span>Processing</span>
                <div style="flex:1; height:2px; background:#eee;"></div>
                <div class="dot"></div> <span>Shipped</span>
            </div>
            <?php renderCardDetails($row); ?>
        </div>
    <?php endforeach; endif; ?>

    <div class="section-header">
        <i class="fa-solid fa-box-open"></i>
        <h3>Past Redemptions</h3>
    </div>

    <?php if (empty($past)): ?>
        <p style="color: #999; text-align: center; padding: 20px;">Your history is currently empty.</p>
    <?php else: foreach ($past as $row): ?>
        <div class="claim-card">
            <?php renderCardHeader($row); ?>
            <?php renderCardDetails($row); ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php 
function renderCardHeader($row) {
    $dbImg = $row['rewardImage'] ?? '';
    // Path matched to your reward.php
    $imagePath = (strpos($dbImg, 'http') === 0) ? $dbImg : 'uploads/rewards/' . basename($dbImg);
    if (empty($dbImg)) $imagePath = 'https://via.placeholder.com/90';
    ?>
    <div class="card-header">
        <img src="<?= htmlspecialchars($imagePath) ?>" class="reward-img">
        <div class="header-info">
            <span class="claim-id">ST-RED-#<?= $row['claimID'] ?></span>
            <h3><?= htmlspecialchars($row['rewardName']) ?></h3>
            <small><i class="fa fa-calendar"></i> Requested: <?= date('d M Y', strtotime($row['claimDate'])) ?></small>
        </div>
        <div class="status-badge status-<?= $row['status'] ?>">
            <?= ucfirst($row['status']) ?>
        </div>
    </div>
<?php } 

function renderCardDetails($row) { ?>
    <div class="info-grid">
        <div class="info-item">
            <label>Recipient</label>
            <span><?= htmlspecialchars($row['recipientName'] ?: 'N/A') ?></span>
        </div>
        <div class="info-item">
            <label>Phone</label>
            <span><?= htmlspecialchars($row['phoneNumber']) ?></span>
        </div>
        <div class="info-item">
            <label>Courier</label>
            <span><?= htmlspecialchars($row['deliveryCompany'] ?: 'TBA') ?></span>
        </div>
        <div class="info-item">
            <label>Tracking #</label>
            <span style="color:#007bff; font-weight:bold;"><?= htmlspecialchars($row['trackingNumber'] ?: 'Pending') ?></span>
        </div>
        <div class="info-item">
            <label>ETA</label>
            <span><?= htmlspecialchars($row['etaText'] ?: 'Updating...') ?></span>
        </div>
        <div class="info-item full-row">
            <label>Shipping Address</label>
            <span><?= nl2br(htmlspecialchars($row['deliveryAddress'])) ?></span>
        </div>
    </div>
<?php } ?>

<?php include 'includes/footer.php'; ?>
</body>
</html>