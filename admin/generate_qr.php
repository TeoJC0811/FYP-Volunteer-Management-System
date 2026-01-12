<?php
ob_start(); // Safety: prevents invisible spaces from breaking the page
session_start();
include("../db.php");

// Allow only admin / organizer
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    die("Unauthorized access.");
}

/* =====================================================
   1️⃣ DETERMINE ENTITY TYPE
===================================================== */
$type = $_GET['type'] ?? 'event';
$type = ($type === 'course') ? 'course' : 'event';
$isCourse = ($type === 'course');

if ($isCourse) {
    $idName    = 'courseID';
    $linkPage  = 'manage_training_registration.php';
    $dbTable   = 'course';
    $nameCol   = 'courseName';
    $idValue   = intval($_GET['courseID'] ?? 0);
} else {
    $idName    = 'eventID';
    $linkPage  = 'manage_event_registration.php';
    $dbTable   = 'event';
    $nameCol   = 'eventName';
    $idValue   = intval($_GET['eventID'] ?? 0);
}

if ($idValue <= 0) {
    die("❌ Invalid ID.");
}

/* =====================================================
   2️⃣ FETCH ENTITY NAME + CHECKIN TOKEN
===================================================== */
$stmt = $conn->prepare("SELECT {$nameCol}, checkinToken FROM {$dbTable} WHERE {$idName} = ?");
$stmt->bind_param("i", $idValue);
$stmt->execute();
$stmt->bind_result($entityName, $checkinToken);
$stmt->fetch();
$stmt->close();

if (empty($checkinToken)) {
    die("❌ Check-in token missing. Please refresh the registration page.");
}

/* =====================================================
   3️⃣ BASE URL SETUP
===================================================== */
$scheme = ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/servetogether';

/* =====================================================
   4️⃣ FINAL CHECK-IN URL
===================================================== */
$checkinUrl = $baseUrl . "/checkin.php?type={$type}&id={$idValue}&token={$checkinToken}";

/* =====================================================
   5️⃣ GOOGLE QR API (The "One-File Fix")
===================================================== */
// This replaces the need for local scripts and libraries
$qrCodeUrl = "https://chart.googleapis.com/chart?chs=450x450&cht=qr&chl=" . urlencode($checkinUrl) . "&choe=UTF-8";

ob_end_flush(); // Release the page content
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance QR - <?= htmlspecialchars($entityName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f4f4; text-align:center; padding:40px; }
        .qr-container { background:#fff; padding:40px; border-radius:16px; display:inline-block; box-shadow:0 10px 30px rgba(0,0,0,.1); max-width: 550px; }
        img { margin:25px auto; border:3px solid #f0f0f0; display: block; border-radius: 8px; }
        .btn-group { display: flex; gap: 12px; justify-content: center; margin-top: 25px; }
        .print-btn, .copy-btn { padding:12px 24px; border:none; border-radius:8px; cursor:pointer; font-size:15px; font-weight: bold; display: flex; align-items: center; gap: 10px; transition: 0.2s; }
        .print-btn { background:#007bff; color:white; }
        .copy-btn { background:#ffffff; color:#333; border: 1.5px solid #ddd; }
        .url-preview { background: #fafafa; padding: 12px 15px; border-radius: 8px; border: 1px solid #eee; margin-top: 20px; font-size: 13px; color: #555; display: flex; align-items: center; justify-content: space-between; gap: 15px; }
        .url-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 350px; }
        @media print { .print-btn, .copy-btn, .back-link, .url-preview, .btn-group { display: none; } body { background: white; padding: 0; } .qr-container { box-shadow: none; border: none; padding: 0; margin-top: 50px; } }
    </style>
</head>
<body>

<div class="qr-container">
    <h2 style="margin-top:0; color:#333;"><?= htmlspecialchars($entityName) ?></h2>
    <p style="color:#666; font-size: 16px;">Scan to check-in for attendance</p>

    <img src="<?= $qrCodeUrl ?>" width="450" height="450" alt="Attendance QR Code">

    <div class="url-preview">
        <span class="url-text" id="rawUrl"><?= htmlspecialchars($checkinUrl) ?></span>
        <button onclick="copyToClipboard()" class="copy-btn" id="copyBtn">
            <i class="fas fa-copy"></i> Copy Link
        </button>
    </div>

    <div class="btn-group">
        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print QR Code
        </button>
    </div>

    <a class="back-link" href="<?= $linkPage ?>?<?= $idName ?>=<?= $idValue ?>">
        <i class="fas fa-chevron-left"></i> Back to Registrations
    </a>
</div>

<script>
function copyToClipboard() {
    const url = document.getElementById('rawUrl').innerText;
    const btn = document.getElementById('copyBtn');
    navigator.clipboard.writeText(url).then(() => {
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => { btn.innerHTML = originalContent; }, 2000);
    });
}
</script>

</body>
</html>