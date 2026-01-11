<?php
// Define constant for cleaner SQL formatting in source code
const NL = PHP_EOL;

session_start();
include("../db.php");

// --- UTILITY FUNCTIONS ---

function h(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function display(mixed $value): string {
    return (isset($value) && trim((string)$value) !== "") ? h($value) : "-";
}

function redirect_with_status(string $page, string $param, string $value): void {
    header("Location: $page?$param=" . urlencode($value));
    exit();
}

function renderStatusTag(string $status): string {
    $status_clean = ucfirst(strtolower(trim($status)));
    $class = 'tag-pending'; 

    if ($status_clean === 'Delivered') {
        $class = 'tag-delivered';
    } elseif ($status_clean === 'Rejected') {
        $class = 'tag-rejected';
    }

    return "<span class='status-tag $class'>" . h($status_clean) . "</span>";
}

// --- ACCESS CONTROL ---
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$target_page = "manage_reward_claims.php";
// Removed N/A from array to handle it as a placeholder instead
$deliveryCompanies = ['J&T Express', 'Pos Laju', 'FedEx', 'GDEX', 'DHL'];

const SUPPORT_EMAIL = 'support@servetogether.com';
const SUPPORT_PHONE = '+60 12-345 6789';

// --- CLAIM UPDATE HANDLER ---

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update'])) {
    $claimID         = filter_input(INPUT_POST, 'claimID', FILTER_VALIDATE_INT);
    $status          = trim($_POST['status'] ?? '');
    
    $recipientName   = trim((string)($_POST['recipientName'] ?? '')); 
    $phoneNumber     = trim((string)($_POST['phoneNumber'] ?? '')); 
    $deliveryAddress = trim((string)($_POST['deliveryAddress'] ?? '')); 
    
    $etaText         = trim((string)($_POST['etaText'] ?? '')); 
    $deliveryCompany = trim((string)($_POST['deliveryCompany'] ?? '')); // Default to empty
    $trackingNumber  = trim((string)($_POST['trackingNumber'] ?? '')); 

    if (!$claimID || $status === '') {
        redirect_with_status($target_page, "error", "❌ Invalid Claim ID or status.");
    }

    // --- CONDITIONAL BACKEND VALIDATION ---
    if ($status === 'Delivered') {
        // If status is Delivered, company cannot be empty or 'N/A'
        if (empty($etaText) || empty($deliveryCompany) || $deliveryCompany === 'N/A' || empty($trackingNumber)) {
            redirect_with_status($target_page, "error", "❌ For 'Delivered' status, ETA, Carrier, and Tracking Number are required.");
        }
    }

    $stmt = $conn->prepare("
        SELECT rc.status, rc.userID, rc.rewardID, r.rewardName, r.pointRequired
        FROM rewardclaims rc
        JOIN reward r ON rc.rewardID = r.rewardID
        WHERE rc.claimID = ?
    ");
    $stmt->bind_param("i", $claimID);
    $stmt->execute();
    $stmt->bind_result($dbStatus, $targetUserID, $rewardID, $rewardName, $pointRequired);
    $stmt->fetch();
    $stmt->close();
    
    $dbStatus = $dbStatus ?: "Pending";

    if (strcasecmp($dbStatus, 'Delivered') === 0 || strcasecmp($dbStatus, 'Rejected') === 0) {
        redirect_with_status($target_page, "error", "❌ This claim is finalized ($dbStatus) and cannot be modified.");
    }

    $refunded = false;
    if ($status === "Rejected") {
        $updatePoints = $conn->prepare("UPDATE User SET totalPoints = totalPoints + ? WHERE userID = ?");
        $updatePoints->bind_param("ii", $pointRequired, $targetUserID);
        $updatePoints->execute();
        $updatePoints->close();
        $refunded = true;
        // Set delivery info to N/A for database consistency if rejected
        $etaText = $trackingNumber = "-";
        $deliveryCompany = "N/A";
    }

    $sql_update = "UPDATE rewardclaims SET status=?, recipientName=?, phoneNumber=?, deliveryAddress=?, etaText=?, deliveryCompany=?, trackingNumber=? WHERE claimID=?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("sssssssi", $status, $recipientName, $phoneNumber, $deliveryAddress, $etaText, $deliveryCompany, $trackingNumber, $claimID);
    
    if (!$stmt->execute()) {
        redirect_with_status($target_page, "error", "❌ Database error: " . $stmt->error);
    }
    $stmt->close();

    // 4. NOTIFY USER
    if ($status === 'Rejected') {
        $msg = "❌ <b>Your claim for " . h($rewardName) . " has been rejected.</b><br>";
        $msg .= "Unfortunately, we could not process your claim.<br>";
        $msg .= "We have <b>returned " . $pointRequired . " points</b> to your account balance.<br>";
    } else {
        $msg = "📢 <b>Update for your reward claim:</b><br><br>";
        $msg .= "Reward: <b>" . h($rewardName) . "</b><br>";
        $msg .= "New Status: <b>" . h($status) . "</b><br>";
        
        if (!empty($etaText) && $etaText !== "-") $msg .= "Estimated Arrival: " . h($etaText) . "<br>";
        if (!empty($deliveryCompany) && $deliveryCompany !== 'N/A') $msg .= "Carrier: " . h($deliveryCompany) . "<br>";
        if (!empty($trackingNumber) && $trackingNumber !== "-") $msg .= "Tracking Number: <b>" . h($trackingNumber) . "</b><br>";
    }
    
    $msg .= "<br>---<br>For support, contact us:<br>Email: " . SUPPORT_EMAIL . "<br>Phone: " . SUPPORT_PHONE;

    $n = $conn->prepare("INSERT INTO notification (userID, message, activityType, activityID, isRead, createdAt) VALUES (?, ?, 'reward', ?, 0, NOW())");
    $n->bind_param("isi", $targetUserID, $msg, $claimID);
    $n->execute(); 
    $n->close();

    $final_msg = "Status updated to " . $status . ".";
    if ($refunded) $final_msg .= " Points refunded to user.";
    redirect_with_status($target_page, "message", $final_msg);
}

// --- HANDLE FILTERING & SEARCH ---
$filter_status = $_GET['filter_status'] ?? '';
$search_query = $_GET['search'] ?? '';

$sql_fetch = "
    SELECT rc.*, u.userName, u.userEmail, r.rewardName, r.rewardImage
    FROM rewardclaims rc
    JOIN user u ON rc.userID = u.userID
    JOIN reward r ON rc.rewardID = r.rewardID
    WHERE 1=1
";

if (!empty($filter_status)) {
    $sql_fetch .= " AND rc.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if (!empty($search_query)) {
    $s = $conn->real_escape_string($search_query);
    $sql_fetch .= " AND (rc.recipientName LIKE '%$s%' OR u.userName LIKE '%$s%')";
}

$sql_fetch .= " ORDER BY rc.claimDate DESC";
$result = $conn->query($sql_fetch);
$claimsData = ($result) ? $result->fetch_all(MYSQLI_ASSOC) : [];

$status_message = h($_GET['message'] ?? null);
$status_error   = h($_GET['error'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Reward Claims - Admin</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    /* Styling for conditional required asterisks */
    .required-label::after { content: " *"; color: red; }
    #updateForm input[readonly] { background-color: #f7f7f7; cursor: not-allowed; border: 1px solid #ddd; }
    :root { --primary-color: #3f51b5; --success-color: #4caf50; --error-color: #f44336; --pending-color: #ff9800; --rejected-color: #f44336; --delivered-color: #4caf50; --border-radius: 8px; --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    .success, .error { padding: 10px 15px; margin-bottom: 20px; border-radius: var(--border-radius); font-weight: bold; }
    .success { background-color: #e8f5e9; color: var(--success-color); border: 1px solid var(--success-color); }
    .error { background-color: #ffebee; color: var(--error-color); border: 1px solid var(--error-color); }
    
    .search-bar { display: flex; justify-content: center; align-items: center; gap: 10px; margin: 20px 0 30px; padding: 15px; background: #f4f7f9; border-radius: 8px; }
    .search-bar input { padding: 10px 12px; flex-grow: 1; max-width: 400px; border: 1px solid #ced4da; border-radius: 5px; }
    .search-bar select { padding: 10px; border: 1px solid #ced4da; border-radius: 5px; background: white; }
    .search-bar button, .search-bar a.btn-reset { padding: 10px 20px; cursor: pointer; border: none; border-radius: 5px; font-size: 14px; font-weight: 600; text-decoration: none; transition: background-color 0.2s ease; display: inline-flex; align-items: center; gap: 5px; }
    .search-bar button { background-color: #3498db; color: white; }
    .search-bar a.btn-reset { background-color: #e9ecef; color: #333; }

    .claims-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; padding-top: 20px; }
    .claim-card { background: #fff; padding: 20px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); display: flex; flex-direction: column; justify-content: space-between; }
    .card-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .reward-thumbnail { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc; }
    
    .status-tag { display: inline-block; padding: 6px 14px; border-radius: 999px; font-size: .75rem; font-weight: 700; text-transform: uppercase; color: #fff; }
    .tag-pending { background: #ff9800; }
    .tag-delivered { background: #4caf50; }
    .tag-rejected { background: #f44336; }

    .card-action-row { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; }
    .btn-manage-details { background-color: var(--primary-color); color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
    
    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1000; overflow-y: auto; }
    .modal.show { display: block; }
    .modal-content { background: #fff; width: 90%; max-width: 600px; margin: 5vh auto; padding: 30px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); }
    #updateForm label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: bold; color: #555; }
    #updateForm input:not([type="hidden"]), #updateForm select, #updateForm textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    #updateForm button { padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px; }
    #updateForm button[type="submit"] { background-color: var(--primary-color); border: none; color: white; }
</style>
</head>
<body>
<?php include '../admin/sidebar.php'; ?>
<div class="main-content">
    <h2>📦 Manage Reward Claims</h2>
    
    <form method="GET" class="search-bar">
        <input type="text" name="search" placeholder="Search by recipient or user..." value="<?= h($search_query) ?>">
        <select name="filter_status">
            <option value="">All Statuses</option>
            <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Delivered" <?= $filter_status == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
            <option value="Rejected" <?= $filter_status == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
        <button type="submit"><i class="fas fa-search"></i> Search</button>
        <a href="manage_reward_claims.php" class="btn-reset"><i class="fas fa-undo"></i> Clear</a>
    </form>

    <?php if ($status_message): ?> <p class="success"><?= $status_message ?></p> <?php endif; ?>
    <?php if ($status_error): ?> <p class="error"><?= $status_error ?></p> <?php endif; ?>

    <div class="claims-grid">
        <?php if (empty($claimsData)): ?>
            <p style="grid-column: 1 / -1;">No reward claims found.</p>
        <?php else: ?>
            <?php foreach ($claimsData as $row): ?>
                <div class="claim-card">
                    <div>
                        <div class="card-header">
                            <img src="../<?= h($row['rewardImage']) ?>" class="reward-thumbnail">
                            <div>
                                <strong><?= h($row['rewardName']) ?></strong>
                                <p style="font-size: 0.9em; color: #555;">ID: <?= h($row['claimID']) ?></p>
                            </div>
                        </div>
                        <p><i class="fas fa-user-tag"></i> <b>Recipient:</b> <?= display($row['recipientName']) ?></p>
                        <p><i class="fas fa-calendar-alt"></i> <b>Claim Date:</b> <?= date('d M Y', strtotime($row['claimDate'])) ?></p>
                        <div class="card-action-row">
                            <?= renderStatusTag($row['status']) ?>
                            <?php if (strcasecmp($row['status'], 'Pending') === 0): ?>
                                <button class="btn-manage-details" onclick="openModal(<?= $row['claimID'] ?>)">Manage Details</button>
                            <?php else: ?>
                                <span style="font-style: italic; color: #888; font-size: 0.9em;">Finalized</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="claimModal" class="modal">
    <div class="modal-content">
        <h3>Manage Claim #<span id="modalClaimID"></span></h3>
        <form method="post" id="updateForm">
            <input type="hidden" name="claimID" id="formClaimID">
            <input type="hidden" name="update" value="1">
            
            <label for="formStatus">Status</label>
            <select name="status" id="formStatus" required>
                <option value="Pending">Pending</option>
                <option value="Delivered">Delivered</option>
                <option value="Rejected">Rejected</option>
            </select>

            <label>Recipient Name</label>
            <input type="text" name="recipientName" id="formRecipientName" readonly>

            <label>Phone Number</label>
            <input type="text" name="phoneNumber" id="formPhoneNumber" readonly>

            <label>Delivery Address</label>
            <input type="text" name="deliveryAddress" id="formDeliveryAddress" readonly>

            <label id="labelEta">ETA (e.g. 3-5 days)</label>
            <input type="text" name="etaText" id="formEtaText">

            <label id="labelComp">Delivery Company</label>
            <select name="deliveryCompany" id="formDeliveryCompany">
                <option value="">-- Select Company --</option>
                <?php foreach ($deliveryCompanies as $company): ?>
                    <option value="<?= h($company) ?>"><?= h($company) ?></option>
                <?php endforeach; ?>
            </select>

            <label id="labelTrack">Tracking Number</label>
            <input type="text" name="trackingNumber" id="formTrackingNumber">

            <div style="margin-top: 30px; text-align: right;">
                <button type="button" onclick="closeModal()">Cancel</button>
                <button type="submit" name="update">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    const CLAIMS = <?= json_encode($claimsData) ?>;
    const MAP = Object.fromEntries(CLAIMS.map(c => [c.claimID, c]));
    const modal = document.getElementById('claimModal');
    const statusSelect = document.getElementById('formStatus');

    const deliveryFields = [
        { input: document.getElementById('formEtaText'), label: document.getElementById('labelEta') },
        { input: document.getElementById('formDeliveryCompany'), label: document.getElementById('labelComp') },
        { input: document.getElementById('formTrackingNumber'), label: document.getElementById('labelTrack') }
    ];

    function toggleRequiredFields() {
        const isDelivered = statusSelect.value === 'Delivered';
        deliveryFields.forEach(field => {
            if (isDelivered) {
                field.input.setAttribute('required', 'required');
                field.label.classList.add('required-label');
            } else {
                field.input.removeAttribute('required');
                field.label.classList.remove('required-label');
            }
        });
    }

    statusSelect.addEventListener('change', toggleRequiredFields);

    function openModal(id) {
        const c = MAP[id];
        if (!c || (c.status.toLowerCase() !== 'pending')) return;
        
        document.getElementById('modalClaimID').textContent = id;
        document.getElementById('formClaimID').value = id;
        document.getElementById('formStatus').value = c.status || 'Pending';
        document.getElementById('formRecipientName').value = c.recipientName || ''; 
        document.getElementById('formPhoneNumber').value = c.phoneNumber || ''; 
        document.getElementById('formDeliveryAddress').value = c.deliveryAddress || ''; 
        document.getElementById('formEtaText').value = c.etaText || '';

        // Reset Delivery Company logic
        const companySelect = document.getElementById('formDeliveryCompany');
        if (c.deliveryCompany && c.deliveryCompany !== 'N/A') {
            companySelect.value = c.deliveryCompany;
        } else {
            companySelect.value = ""; // Shows placeholder
        }

        document.getElementById('formTrackingNumber').value = c.trackingNumber || ''; 
        
        toggleRequiredFields();
        modal.classList.add('show');
    }

    function closeModal() { modal.classList.remove('show'); }
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
</script>
</body>
</html>