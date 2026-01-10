<?php
session_start();
include 'db.php';

// ✅ Ensure user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// ✅ Use POST since the form submits via POST
$rewardID = $_POST['rewardID'] ?? null;
$recipientName = trim($_POST['recipientName'] ?? '');
$deliveryAddress = trim($_POST['deliveryAddress'] ?? '');
$phoneNumber = trim($_POST['phoneNumber'] ?? '');

if (!$rewardID) {
    header("Location: reward.php?error=Invalid+reward");
    exit();
}

// --- Validate recipient name ---
if (empty($recipientName)) {
    header("Location: reward_claim.php?id=$rewardID&error=Recipient+name+required");
    exit();
}

// --- 🔥 FIXED: Validate phone number (Allows digits and the dash '-') ---
// This matches formats like 012-3456789 or 011-12345678
if (!preg_match("/^[0-9\-]{10,15}$/", $phoneNumber)) {
    header("Location: reward_claim.php?id=$rewardID&error=Invalid+phone+number+format");
    exit();
}

// --- Validate address ---
if (empty($deliveryAddress)) {
    header("Location: reward_claim.php?id=$rewardID&error=Address+required");
    exit();
}

// --- Get user points ---
// Note: Ensure column name matches your DB exactly (totalPoints vs totalpoints)
$userQuery = $conn->prepare("SELECT totalPoints FROM user WHERE userID = ?");
$userQuery->bind_param("i", $userID);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();
$totalPoints = $user['totalPoints'] ?? 0;

// --- Get reward details ---
$rewardQuery = $conn->prepare("SELECT * FROM reward WHERE rewardID = ?");
$rewardQuery->bind_param("i", $rewardID);
$rewardQuery->execute();
$rewardResult = $rewardQuery->get_result();
$reward = $rewardResult->fetch_assoc();

if (!$reward) {
    header("Location: reward.php?error=Reward+not+found");
    exit();
}

$pointsRequired = $reward['pointRequired'];

// --- Check if enough points ---
if ($totalPoints < $pointsRequired) {
    header("Location: reward_claim.php?id=$rewardID&error=Not+enough+points");
    exit();
}

// --- Deduct points & insert claim ---
$conn->begin_transaction();

try {
    // Deduct points from user
    $updatePoints = $conn->prepare("
        UPDATE user 
        SET totalPoints = totalPoints - ? 
        WHERE userID = ?
    ");
    $updatePoints->bind_param("ii", $pointsRequired, $userID);
    $updatePoints->execute();

    // Insert into rewardclaims
    $status = "Pending"; 
    $claimQuery = $conn->prepare("
        INSERT INTO rewardclaims 
        (claimDate, recipientName, phoneNumber, deliveryAddress, status, userID, rewardID) 
        VALUES (NOW(), ?, ?, ?, ?, ?, ?)
    ");
    $claimQuery->bind_param("ssssii", $recipientName, $phoneNumber, $deliveryAddress, $status, $userID, $rewardID);
    $claimQuery->execute();

    $conn->commit();

    // ✅ SUCCESS: Redirect to success page
    header("Location: reward_success.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    // Redirect back with the specific error for debugging if needed
    header("Location: reward_claim.php?id=$rewardID&error=Failed+to+claim+reward");
    exit();
}