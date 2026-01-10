<?php
session_start();
include("db.php");

/* ===============================
    SAVE CHECK-IN IF NOT LOGGED IN
================================ */
if (!isset($_SESSION['userID'])) {

    // Save pending check-in info
    $_SESSION['pending_checkin'] = [
        'type'  => $_GET['type'] ?? '',
        'id'    => intval($_GET['id'] ?? 0),
        'token' => $_GET['token'] ?? ''
    ];

    $_SESSION['redirect_after_login'] = 'checkin.php';

    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// Clear pending check-in to prevent double processing
unset($_SESSION['pending_checkin']);

/* ===============================
    HELPER: UI MESSAGE
================================ */
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
            body {
                font-family: Arial, sans-serif;
                background: #f4f4f4;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .card {
                background: white;
                padding: 30px;
                border-radius: 12px;
                text-align: center;
                box-shadow: 0 6px 18px rgba(0,0,0,0.15);
                max-width: 360px;
                width: 100%;
            }
            h1 { color: {$color}; margin-bottom: 10px; }
            p { color: #555; line-height: 1.5; }
        </style>
    </head>
    <body>
        <div class='card'>
            <h1>{$title}</h1>
            <p>{$message}</p>
            <p style='font-size:13px;color:#888;'>Redirecting to home…</p>
        </div>
        <script>
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 4000);
        </script>
    </body>
    </html>
    ";
    exit();
}

/* ===============================
    VALIDATE PARAMETERS
================================ */
$type  = $_GET['type'] ?? '';
$id    = intval($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

if (!in_array($type, ['event', 'course']) || $id <= 0 || empty($token)) {
    showMessage("❌ Invalid Request", "This check-in link is invalid or incomplete.");
}

/* ===============================
    EVENT CHECK-IN
================================ */
if ($type === 'event') {

    // 1. Validate the QR Token exists for this event
    $stmt = $conn->prepare("SELECT eventName, point FROM Event WHERE eventID = ? AND checkinToken = ?");
    $stmt->bind_param("is", $id, $token);
    $stmt->execute();
    $stmt->bind_result($eventTitle, $points);
    if (!$stmt->fetch()) {
        showMessage("❌ Invalid QR Code", "This QR code is invalid or has expired.");
    }
    $stmt->close();

    // 2. Check if user is registered (Prevents User B problem)
    $stmtReg = $conn->prepare("SELECT status FROM EventRegistration WHERE userID = ? AND eventID = ?");
    $stmtReg->bind_param("ii", $userID, $id);
    $stmtReg->execute();
    $stmtReg->bind_result($currentStatus);
    
    if (!$stmtReg->fetch()) {
        showMessage("❌ Access Denied", "You cannot take attendance because you have not joined this event.");
    }
    $stmtReg->close();

    // 3. Prevent double scanning
    if ($currentStatus === 'Completed') {
        showMessage("✅ Already Done", "Your attendance for <b>{$eventTitle}</b> has already been recorded.", true);
    }

    // 4. Update status to Completed
    $stmtUpdate = $conn->prepare("UPDATE EventRegistration SET status = 'Completed' WHERE userID = ? AND eventID = ?");
    $stmtUpdate->bind_param("ii", $userID, $id);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    // 5. UNIFIED Notification (Text only)
    $notifMsg = "🎉 You successfully completed the event <b>{$eventTitle}</b> and earned {$points} points!";
    $stmtNotif = $conn->prepare("INSERT INTO Notification (message, activityType, activityID, userID, isRead, createdAt) VALUES (?, 'event', ?, ?, 0, NOW())");
    $stmtNotif->bind_param("sii", $notifMsg, $id, $userID);
    $stmtNotif->execute();
    $stmtNotif->close();

    showMessage("✅ Check-in Successful", "Your attendance for <b>{$eventTitle}</b> has been recorded.", true);
}

/* ===============================
    COURSE CHECK-IN (Flexible Way)
================================ */
if ($type === 'course') {

    // 1. Validate QR Token
    $stmt = $conn->prepare("SELECT courseName FROM Course WHERE courseID = ? AND checkinToken = ?");
    $stmt->bind_param("is", $id, $token);
    $stmt->execute();
    $stmt->bind_result($courseName);
    if (!$stmt->fetch()) {
        showMessage("❌ Invalid QR Code", "This QR code is invalid or has expired.");
    }
    $stmt->close();

    // 2. Check if user is registered (Prevents unregistered users from scanning)
    $stmtReg = $conn->prepare("SELECT status FROM CourseRegistration WHERE userID = ? AND courseID = ?");
    $stmtReg->bind_param("ii", $userID, $id);
    $stmtReg->execute();
    $stmtReg->bind_result($currentStatus);

    if (!$stmtReg->fetch()) {
        showMessage("❌ Access Denied", "You are not registered for this training course.");
    }
    $stmtReg->close();

    // 3. Check if already completed
    if ($currentStatus === 'Completed') {
        showMessage("✅ Already Done", "Your attendance for this course was already recorded.", true);
    }

    // 4. Update status to Completed (Flexible: Don't wait for payment approval)
    $stmtUpdate = $conn->prepare("UPDATE CourseRegistration SET status = 'Completed' WHERE userID = ? AND courseID = ?");
    $stmtUpdate->bind_param("ii", $userID, $id);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    // 5. UNIFIED Notification (Text only)
    $notifMsg = "🎉 You successfully completed the course <b>{$courseName}</b>!";
    $stmtNotif = $conn->prepare("INSERT INTO Notification (message, activityType, activityID, userID, isRead, createdAt) VALUES (?, 'course', ?, ?, 0, NOW())");
    $stmtNotif->bind_param("sii", $notifMsg, $id, $userID);
    $stmtNotif->execute();
    $stmtNotif->close();

    showMessage("✅ Check-in Successful", "Your attendance for <b>{$courseName}</b> has been recorded.", true);
}
?>