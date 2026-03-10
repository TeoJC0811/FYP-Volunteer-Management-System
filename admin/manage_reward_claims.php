<?php
session_start();
include("../db.php");

// --- UTILITY FUNCTIONS ---
function h(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect_with_status(string $page, string $param, string $value): void {
    header("Location: $page?$param=" . urlencode($value));
    exit();
}

// --- ACCESS CONTROL ---
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$target_page = "manage_reward_claims.php";
$deliveryCompanies = ['J&T Express', 'Pos Laju', 'FedEx', 'GDEX', 'DHL'];

// --- CLAIM UPDATE HANDLER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update'])) {
    $claimID         = filter_input(INPUT_POST, 'claimID', FILTER_VALIDATE_INT);
    $status          = trim($_POST['status'] ?? '');
    $etaText         = trim((string)($_POST['etaText'] ?? '')); 
    $deliveryCompany = trim((string)($_POST['deliveryCompany'] ?? '')); 
    $trackingNumber  = trim((string)($_POST['trackingNumber'] ?? '')); 

    if (!$claimID || $status === '') {
        redirect_with_status($target_page, "error", "❌ Invalid Claim ID or status.");
    }

    // 1. Fetch current status and point info for potential refund
    $stmt = $conn->prepare("
        SELECT rc.status, rc.userID, r.pointRequired
        FROM rewardclaims rc
        JOIN reward r ON rc.rewardID = r.rewardID
        WHERE rc.claimID = ?
    ");
    $stmt->bind_param("i", $claimID);
    $stmt->execute();
    $stmt->bind_result($dbStatus, $targetUserID, $pointRequired);
    $stmt->fetch();
    $stmt->close();

    $conn->begin_transaction();
    try {
        // 2. Point Refund Logic (Only if rejecting a claim that wasn't already rejected)
        if ($status === "rejected" && $dbStatus !== 'rejected') {
            $updatePoints = $conn->prepare("UPDATE user SET totalPoints = totalPoints + ? WHERE userID = ?");
            $updatePoints->bind_param("ii", $pointRequired, $targetUserID);
            $updatePoints->execute();
            $updatePoints->close();
        }

        // 3. Update Reward Claim Record (NO Notification logic here)
        $sql_update = "UPDATE rewardclaims SET status=?, etaText=?, deliveryCompany=?, trackingNumber=? WHERE claimID=?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssssi", $status, $etaText, $deliveryCompany, $trackingNumber, $claimID);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        redirect_with_status($target_page, "message", "✅ Claim status updated successfully.");

    } catch (Exception $e) {
        $conn->rollback();
        redirect_with_status($target_page, "error", "❌ Update Failed: " . $e->getMessage());
    }
}

// --- FETCH DATA ---
$filter_status = $_GET['filter_status'] ?? '';
$search_query = $_GET['search'] ?? '';
$sql_fetch = "SELECT rc.*, u.userName, r.rewardName, r.rewardImage FROM rewardclaims rc JOIN user u ON rc.userID = u.userID JOIN reward r ON rc.rewardID = r.rewardID WHERE 1=1";
if (!empty($filter_status)) $sql_fetch .= " AND rc.status = '" . $conn->real_escape_string($filter_status) . "'";
if (!empty($search_query)) {
    $s = $conn->real_escape_string($search_query);
    $sql_fetch .= " AND (rc.recipientName LIKE '%$s%' OR u.userName LIKE '%$s%')";
}
$sql_fetch .= " ORDER BY rc.claimDate DESC";
$result = $conn->query($sql_fetch);
$claimsData = ($result) ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Reward Claims</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .claims-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; padding: 20px; }
        .claim-card { background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: 1px solid #eee; }
        .reward-thumbnail { width: 50px; height: 50px; border-radius: 5px; object-fit: cover; }
        .status-badge { font-size: 11px; padding: 3px 8px; border-radius: 10px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff9db; color: #f0932b; }
        .status-delivered { background: #ebfbee; color: #27ae60; }
        .status-rejected { background: #fff5f5; color: #eb4d4b; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.show { display: block; }
        .modal-content { background: #fff; width: 400px; margin: 10% auto; padding: 20px; border-radius: 8px; }
        input, select { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        .btn-save { background: #3f51b5; color: white; border: none; padding: 10px; width: 100%; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
<?php include '../admin/sidebar.php'; ?>
<div class="main-content">
    <h2>📦 Reward Claim Management</h2>

    <?php if (isset($_GET['message'])) echo "<p style='color:green;'>".h($_GET['message'])."</p>"; ?>

    <div class="claims-grid">
        <?php foreach ($claimsData as $row): ?>
            <div class="claim-card">
                <div style="display:flex; gap:10px; align-items:center;">
                    <?php $img = $row['rewardImage']; $src = (strpos($img, 'http') === 0) ? $img : "../uploads/rewards/".basename($img); ?>
                    <img src="<?= h($src) ?>" class="reward-thumbnail">
                    <div>
                        <strong><?= h($row['rewardName']) ?></strong>
                        <div style="font-size:11px; color:#888;">ID: #<?= $row['claimID'] ?></div>
                    </div>
                </div>
                <p style="font-size:13px; margin: 10px 0;">User: <?= h($row['userName']) ?></p>
                <span class="status-badge status-<?= $row['status'] ?>"><?= $row['status'] ?></span>
                
                <?php if ($row['status'] === 'pending'): ?>
                    <button onclick="openModal(<?= $row['claimID'] ?>)" style="float:right; cursor:pointer;">Update</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="claimModal" class="modal">
    <div class="modal-content">
        <h3>Update Claim Status</h3>
        <form method="post">
            <input type="hidden" name="claimID" id="formClaimID">
            <input type="hidden" name="update" value="1">
            <select name="status">
                <option value="pending">Pending</option>
                <option value="delivered">Delivered</option>
                <option value="rejected">Rejected</option>
            </select>
            <input type="text" name="trackingNumber" placeholder="Tracking Number">
            <select name="deliveryCompany">
                <option value="">Select Courier</option>
                <?php foreach ($deliveryCompanies as $c) echo "<option value='$c'>$c</option>"; ?>
            </select>
            <input type="text" name="etaText" placeholder="ETA Description">
            <button type="submit" class="btn-save">Save Changes</button>
            <button type="button" onclick="closeModal()" style="width:100%; margin-top:5px;">Cancel</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById('formClaimID').value = id; document.getElementById('claimModal').classList.add('show'); }
    function closeModal() { document.getElementById('claimModal').classList.remove('show'); }
</script>
</body>
</html>