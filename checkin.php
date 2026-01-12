<?php
session_start();
include("db.php");

/* =====================================================
    1️⃣ SESSION & REDIRECTION LOGIC
===================================================== */
// If not logged in, save the URL parameters and go to login
if (!isset($_SESSION['userID'])) {
    $_SESSION['pending_checkin'] = [
        'type'  => $_GET['type'] ?? '',
        'id'    => intval($_GET['id'] ?? 0),
        'token' => $_GET['token'] ?? ''
    ];
    $_SESSION['redirect_after_login'] = 'checkin.php';
    header("Location: login.php");
    exit();
}

// RESTORE DATA: If we just logged in, pull data from session memory
if (isset($_SESSION['pending_checkin'])) {
    $type  = $_SESSION['pending_checkin']['type'];
    $id    = $_SESSION['pending_checkin']['id'];
    $token = $_SESSION['pending_checkin']['token'];
    unset($_SESSION['pending_checkin']); // Clear memory after use
} else {
    // Normal flow: get data from the URL
    $type  = $_GET['type'] ?? '';
    $id    = intval($_GET['id'] ?? 0);
    $token = $_GET['token'] ?? '';
}

$userID = $_SESSION['userID'];

/* =====================================================
    2️⃣ HELPER: UI MESSAGE FUNCTION
===================================================== */
function showMessage($title, $message, $success = false) {
    $color = $success ? "#28a745" : "#dc3545";
    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Attendance Check-in</title>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background: white; padding: 30px; border-radius: 12px; text-align: center; box-shadow: 0 6px 18px rgba(0,0,0,0.15); max-width: 360px; width: 100%; }
            h1 { color: {$color}; margin-bottom: 10px; }
            p { color: #555; line-height: 1.5; }
        </style>
    </head>
    <body>
        <div class='card'>
            <h1>{$title}</h1>
            <p>{$message}</p>
            <p style='font-size:13px;color:#888;'>Redirecting to home...</p>
        </div>
        <script>
            setTimeout(() => { window.location.href = 'index.php'; }, 4000);
        </script>
    </body>
    </html>";
    exit();
}

/* =====================================================
    3️⃣ VALIDATE PARAMETERS
===================================================== */
if (!in_array($type, ['event', 'course']) || $id <= 0 || empty($token)) {
    showMessage("❌ Invalid Request", "This check-in link is invalid or incomplete.");
}

/* =====================================================
    4️⃣ PROCESS ATTENDANCE
==================================================== */

// Map type to database details (lowercase table names for Render/Linux compatibility)
if ($type === 'event') {
    $tableMain = 'event';
    $tableReg  = 'eventregistration';
    $idCol     = 'eventID';
    $nameCol   = 'eventName';
} else {
    $tableMain = 'course';
    $tableReg  = 'courseregistration';
    $idCol     = 'courseID';
    $nameCol   = 'courseName';
}

// 1. Validate the QR Token exists
$stmt = $conn->prepare("SELECT {$nameCol}, " . ($type === 'event' ? "point" : "0") . " FROM {$tableMain} WHERE {$idCol} = ? AND checkinToken = ?");
$stmt->bind_param("is", $id, $token);
$stmt->execute();
$stmt->bind_result($entityName, $points);
if (!$stmt->fetch()) {
    $stmt->close();
    showMessage("❌ Invalid QR Code", "This QR code is invalid or has expired.");
}
$stmt->close();

// 2. Check if user is registered
$stmtReg = $conn->prepare("SELECT status FROM {$tableReg} WHERE userID = ? AND {$idCol} = ?");
$stmtReg->bind_param("ii", $userID, $id);
$stmtReg->execute();
$stmtReg->bind_result($currentStatus);

if (!$stmtReg->fetch()) {
    $stmtReg->close();
    showMessage("❌ Access Denied", "You cannot take attendance because you have not joined this activity.");
}
$stmtReg->close();

// 3. Prevent double scanning
if ($currentStatus === 'Completed') {
    showMessage("✅ Already Done", "Your attendance for <b>{$entityName}</b> has already been recorded.", true);
}

// 4. Update status to Completed
$stmtUpdate = $conn->prepare("UPDATE {$tableReg} SET status = 'Completed' WHERE userID = ? AND {$idCol} = ?");
$stmtUpdate->bind_param("ii", $userID, $id);
$stmtUpdate->execute();
$stmtUpdate->close();

// 5. Notification (Insert into notification table)
$notifMsg = "🎉 You successfully completed <b>{$entityName}</b>" . ($type === 'event' ? " and earned {$points} points!" : "!");
$stmtNotif = $conn->prepare("INSERT INTO notification (message, activityType, activityID, userID, isRead, createdAt) VALUES (?, ?, ?, ?, 0, NOW())");
$stmtNotif->bind_param("ssii", $notifMsg, $type, $id, $userID);
$stmtNotif->execute();
$stmtNotif->close();

showMessage("✅ Check-in Successful", "Your attendance for <b>{$entityName}</b> has been recorded.", true);
?>