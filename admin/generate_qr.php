<?php
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
   3️⃣ SMART BASE URL (Fix for Render vs Local)
===================================================== */
$scheme = (
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
) ? 'https' : 'http';

$host = $_SERVER['HTTP_HOST'];

// RENDER FIX: If on Render, the files are in root, so no '/servetogether'
if (strpos($host, 'onrender.com') !== false) {
    $baseUrl = $scheme . '://' . $host;
} else {
    // LOCALHOST FIX: Usually needs the subfolder name
    $baseUrl = $scheme . '://' . $host . '/servetogether';
}

/* =====================================================
   4️⃣ FINAL CHECK-IN URL (QR CONTENT)
===================================================== */
$checkinUrl = $baseUrl . "/checkin.php"
    . "?type={$type}"
    . "&id={$idValue}"
    . "&token={$checkinToken}";

/* =====================================================
   5️⃣ QR IMAGE GENERATOR
===================================================== */
// We pass the checkinUrl to our generator script
$qrCodeUrl = "generate_qr_image.php?data=" . urlencode($checkinUrl);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance QR - <?= htmlspecialchars($entityName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background:#f4f7f6; text-align:center; padding:40px; margin:0; }
    .qr-container { 
        background:#fff; padding:40px; border-radius:20px; 
        display:inline-block; box-shadow:0 15px 35px rgba(0,0,0,0.1); 
        max-width: 550px; width: 100%; box-sizing: border-box;
    }
    h2 { margin: 0 0 10px 0; color: #333; font-size: 24px; }
    .sub-text { color:#666; font-size: 16px; margin-bottom: 20px; }
    
    .qr-image-wrapper {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 12px;
        border: 2px dashed #ddd;
        display: inline-block;
        margin-bottom: 20px;
    }
    img { display: block; max-width: 100%; height: auto; border-radius: 4px; }

    .url-preview {
        background: #f1f3f5; padding: 12px 15px; border-radius: 8px;
        margin-top: 10px; font-size: 13px; color: #495057;
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        border: 1px solid #dee2e6;
    }
    .url-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: left; flex: 1; }

    .btn-group { display: flex; gap: 12px; justify-content: center; margin-top: 25px; }
    .btn {
        padding:12px 24px; border:none; border-radius:8px; cursor:pointer;
        font-size:15px; font-weight: 600; display: flex; align-items: center;
        gap: 8px; transition: all 0.2s;
    }
    .print-btn { background:#007bff; color:white; }
    .print-btn:hover { background:#0056b3; transform: translateY(-2px); }
    .copy-btn { background:#fff; color:#333; border: 1px solid #ced4da; }
    .copy-btn:hover { background:#f8f9fa; border-color: #adb5bd; }

    .back-link { display:inline-block; margin-top:30px; text-decoration:none; color:#6c757d; font-size: 14px; }
    .back-link:hover { color: #007bff; }

    @media print {
        .btn-group, .back-link, .url-preview { display: none; }
        body { background: white; padding: 0; }
        .qr-container { box-shadow: none; border: none; padding: 0; margin-top: 20px; }
        .qr-image-wrapper { border: none; padding: 0; }
    }
</style>
</head>
<body>

<div class="qr-container">
    <h2><?= htmlspecialchars($entityName) ?></h2>
    <p class="sub-text">Scan this QR code to check-in for attendance</p>

    <div class="qr-image-wrapper">
        <img src="<?= $qrCodeUrl ?>" width="400" height="400" alt="Attendance QR Code">
    </div>

    <div class="url-preview">
        <span class="url-text" id="rawUrl"><?= htmlspecialchars($checkinUrl) ?></span>
        <button onclick="copyToClipboard()" class="btn copy-btn" style="padding: 6px 12px; font-size: 12px;" id="copyBtn">
            <i class="fas fa-copy"></i> Copy
        </button>
    </div>

    <div class="btn-group">
        <button class="btn print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print QR Code
        </button>
    </div>

    <a class="back-link" href="<?= $linkPage ?>?<?= $idName ?>=<?= $idValue ?>">
        <i class="fas fa-chevron-left"></i> Return to Registration Management
    </a>
</div>

<script>
function copyToClipboard() {
    const url = document.getElementById('rawUrl').innerText;
    const btn = document.getElementById('copyBtn');
    
    navigator.clipboard.writeText(url).then(() => {
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.color = '#28a745';
        
        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.style.color = '';
        }, 2000);
    }).catch(err => {
        alert('Failed to copy link.');
    });
}
</script>

</body>
</html>