<?php
ob_start(); 
session_start();
include("../db.php");

// FIXED: Changed back to 'role' to match your current session setup
if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    die("Unauthorized access. Access Level: " . ($_SESSION['role'] ?? 'None'));
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
$stmt = $conn->prepare("
    SELECT {$nameCol}, checkinToken
    FROM {$dbTable}
    WHERE {$idName} = ?
");
$stmt->bind_param("i", $idValue);
$stmt->execute();
$stmt->bind_result($entityName, $checkinToken);
$stmt->fetch();
$stmt->close();

if (empty($checkinToken)) {
    die("❌ Check-in token missing. Please refresh the registration page.");
}

/* =====================================================
    3️⃣ SMART BASE URL (Local vs Render)
===================================================== */
$scheme = (
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
) ? 'https' : 'http';

// Detect if we are running on localhost or Render
$folder = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/servetogether' : '';
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $folder;

/* =====================================================
    4️⃣ FINAL CHECK-IN URL (QR CONTENT)
===================================================== */
$checkinUrl = $baseUrl . "/checkin.php?type={$type}&id={$idValue}&token={$checkinToken}";

/* =====================================================
    5️⃣ GOOGLE QR API
===================================================== */
// This ensures the image generates correctly without needing local scripts
$qrCodeUrl = "https://chart.googleapis.com/chart?cht=qr&chs=450x450&chl=" . rawurlencode($checkinUrl);

ob_end_flush(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance QR</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    font-family: Arial, sans-serif;
    background:#f4f4f4;
    text-align:center;
    padding:40px;
}
.qr-container {
    background:#fff;
    padding:40px;
    border-radius:16px;
    display:inline-block;
    box-shadow:0 10px 30px rgba(0,0,0,.1);
    max-width: 550px; 
}
img {
    margin:25px 0;
    border:3px solid #f0f0f0;
    display: block;
    margin-left: auto;
    margin-right: auto;
    border-radius: 8px;
}
.btn-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 25px;
}
.print-btn, .copy-btn {
    padding:12px 24px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:15px;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: 0.2s;
}
.print-btn { background:#007bff; color:white; }
.print-btn:hover { background:#0056b3; }

.copy-btn { background:#ffffff; color:#333; border: 1.5px solid #ddd; }
.copy-btn:hover { background:#f8f9fa; border-color: #bbb; }

.back-link {
    display:inline-block;
    margin-top:25px;
    text-decoration:none;
    color:#777;
    font-size: 14px;
}
.back-link:hover { color: #000; text-decoration: underline; }

.url-preview {
    background: #fafafa;
    padding: 12px 15px;
    border-radius: 8px;
    border: 1px solid #eee;
    margin-top: 20px;
    font-size: 13px;
    color: #555;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}
.url-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 350px;
}

@media print {
    .print-btn, .copy-btn, .back-link, .url-preview, .btn-group { display: none; }
    body { background: white; padding: 0; }
    .qr-container { box-shadow: none; border: none; padding: 0; margin-top: 50px; }
    h2 { font-size: 28px; margin-bottom: 10px; }
}
</style>
</head>
<body>

<div class="qr-container">
    <h2 style="margin-top:0; color:#333;"><?= htmlspecialchars($entityName) ?></h2>
    <p style="color:#666; font-size: 16px;">Scan to check-in for attendance</p>

    <img src="<?= $qrCodeUrl ?>" width="450" height="450" alt="Attendance QR Code">

    <div class="url-preview">
        <span class="url-text" id="rawUrl"><?= htmlspecialchars($checkinUrl) ?></span>
        <button onclick="copyToClipboard()" class="copy-btn" style="padding: 6px 12px; font-size: 12px;" id="copyBtn">
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
        btn.style.backgroundColor = '#e8f5e9';
        btn.style.color = '#2e7d32';
        btn.style.borderColor = '#c8e6c9';
        
        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.style.backgroundColor = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    }).catch(err => {
        alert('Could not copy text.');
    });
}
</script>

</body>
</html>