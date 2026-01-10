<?php
session_start();
include 'db.php';

// Check if logged in
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];
$rewardID = $_GET['id'] ?? null;

if (!$rewardID) {
    echo "Invalid reward.";
    exit();
}

// --- FETCH DATA PART ---
$userQuery = $conn->prepare("SELECT * FROM user WHERE userID = ?");
$userQuery->bind_param("i", $userID);
$userQuery->execute();
$userResult = $userQuery->get_result();
$userRaw = $userResult->fetch_assoc();

$u = array_change_key_case($userRaw, CASE_LOWER);

$totalPoints = $u['totalpoints'] ?? 0;
$dbName    = $u['username'] ?? '';
$dbPhone   = $u['phonenumber'] ?? '';
$dbAddress = $u['address'] ?? '';

// Get reward details
$rewardQuery = $conn->prepare("SELECT * FROM reward WHERE rewardID = ?");
$rewardQuery->bind_param("i", $rewardID);
$rewardQuery->execute();
$rewardResult = $rewardQuery->get_result();
$reward = $rewardResult->fetch_assoc();

if (!$reward) {
    echo "Reward not found.";
    exit();
}

$imagePath = !empty($reward['rewardImage'])
    ? 'uploads/rewards/' . basename($reward['rewardImage'])
    : 'https://via.placeholder.com/200x150';

$canClaim = $totalPoints >= $reward['pointRequired'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Claim Reward</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        .page-container { flex: 1; }
        .top-bar { display: flex; justify-content: flex-end; margin: 20px; }
        .point-box { background-color: #f0f0f0; padding: 10px 15px; border-radius: 10px; font-weight: bold; }
        .main-flex { display: flex; justify-content: center; align-items: flex-start; gap: 20px; margin: 20px; }
        .claim-container { max-width: 900px; width: 100%; padding: 20px; border: 1px solid #ddd; border-radius: 10px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; gap: 20px; align-items: flex-start; }
        .claim-left { flex: 1; text-align: center; }
        .claim-image { width: 100%; max-width: 250px; border: 2px solid #ccc; border-radius: 10px; }
        .claim-right { flex: 2; text-align: left; }
        .form-group { margin: 10px 0; }
        .form-group label { font-weight: bold; display: block; }
        .form-group input { width: 100%; max-width: 600px; padding: 8px; margin-top: 5px; border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; }
        .checkbox-row { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; }
        .checkbox-row input { width: 18px; height: 18px; cursor: pointer; }
        .action-row { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; max-width: 600px; }
        .required-points { padding: 6px 12px; background: #f8f8f8; border-radius: 8px; font-weight: bold; }
        .claim-btn { padding: 8px 15px; background: #007BFF; color: white; border-radius: 5px; border: none; cursor: pointer; font-weight: bold; }
        .claim-btn:hover { background-color: #0056b3; }
        .back-btn { margin-left: 200px; }
        .input-hint { font-size: 11px; color: #666; display: block; margin-top: 3px; }
    </style>
</head>
<body>

<div class="page-container">
    <?php include 'user_navbar.php'; ?>
    <div class="top-bar">
        <div class="point-box">Available Points: <?= htmlspecialchars($totalPoints) ?></div>
    </div>

    <div class="main-flex">
        <a href="reward.php" class="back-btn">← Back</a>
        <div class="claim-container">
            <div class="claim-left">
                <img src="<?= htmlspecialchars($imagePath) ?>" class="claim-image">
            </div>
            <div class="claim-right">
                <h2><?= htmlspecialchars($reward['rewardName']) ?></h2>
                <p><?= htmlspecialchars($reward['description']) ?></p>

                <?php if ($canClaim): ?>
                <form method="POST" action="reward_process.php" id="claimForm">
                    <input type="hidden" name="rewardID" value="<?= htmlspecialchars($rewardID) ?>">

                    <div class="form-group">
                        <label for="recipientName">Recipient Name</label>
                        <input type="text" id="recipientName" name="recipientName" required>
                    </div>

                    <div class="form-group">
                        <label for="phoneNumber">Phone Number</label>
                        <input type="tel" id="phoneNumber" name="phoneNumber" 
                               pattern="[0-9\-]{10,13}" placeholder="011-12345678" required>
                        <span class="input-hint">Format: 000-00000000</span>
                    </div>

                    <div class="form-group">
                        <label for="deliveryAddress">Delivery Address</label>
                        <input type="text" id="deliveryAddress" name="deliveryAddress" required>
                    </div>

                    <div class="checkbox-row">
                        <input type="checkbox" id="useProfile" onclick="toggleProfileFill()">
                        <label for="useProfile">Use my profile details</label>
                    </div>

                    <div class="action-row">
                        <div class="required-points">Points Required: <?= htmlspecialchars($reward['pointRequired']) ?></div>
                        <button type="submit" class="claim-btn">Confirm Claim</button>
                    </div>
                </form>
                <?php else: ?>
                    <p style="color:red; font-weight:bold;">Insufficient points.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    const userData = {
        name: <?= json_encode($dbName) ?>,
        phone: <?= json_encode($dbPhone) ?>,
        address: <?= json_encode($dbAddress) ?>
    };

    const rewardName = <?= json_encode($reward['rewardName'] ?? '') ?>;
    const requiredPoints = <?= json_encode($reward['pointRequired'] ?? 0) ?>;

    function toggleProfileFill() {
        const cb = document.getElementById("useProfile");
        if (cb.checked) {
            document.getElementById("recipientName").value = userData.name;
            document.getElementById("phoneNumber").value = userData.phone;
            document.getElementById("deliveryAddress").value = userData.address;
        } else {
            document.getElementById("recipientName").value = "";
            document.getElementById("phoneNumber").value = "";
            document.getElementById("deliveryAddress").value = "";
        }
    }

    // Auto-dash formatter to prevent "pattern" errors and enforce consistency
    document.getElementById('phoneNumber').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 3) {
            value = value.slice(0, 3) + '-' + value.slice(3);
        }
        e.target.value = value.slice(0, 12);
    });

    document.getElementById('claimForm').onsubmit = function() {
        const msg = `Are you sure you want to claim "${rewardName}" for ${requiredPoints} points? This will be deducted immediately.`;
        return confirm(msg);
    };
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>