<?php
// /servetogether/admin/generate_qr_image.php

declare(strict_types=1);

// 1. Clear any accidental whitespace from included files to prevent image corruption
if (ob_get_length()) ob_clean();

// 2. Use realpath to handle Linux/Render pathing issues strictly
$libPath = realpath(__DIR__ . "/../qrcode_lib/qrlib.php");

if (!$libPath || !file_exists($libPath)) {
    // If library is missing, show a text error instead of a broken image
    header('Content-Type: text/plain');
    die("❌ QR Library not found. Path checked: " . __DIR__ . "/../qrcode_lib/qrlib.php");
}

require_once $libPath;

/*
|---------------------------------------------------------
| 1️⃣ GET FULL CHECK-IN URL
|---------------------------------------------------------
*/
$data = $_GET['data'] ?? '';
$data = trim($data);

/*
|---------------------------------------------------------
| 2️⃣ BASIC VALIDATION
|---------------------------------------------------------
*/
if ($data === '' || strlen($data) < 10) {
    header('Content-Type: image/png');
    $img = imagecreatetruecolor(220, 80);
    $bg  = imagecolorallocate($img, 255, 255, 255);
    $red = imagecolorallocate($img, 200, 0, 0);

    imagefilledrectangle($img, 0, 0, 220, 80, $bg);
    imagestring($img, 4, 15, 30, 'INVALID QR DATA', $red);

    imagepng($img);
    imagedestroy($img);
    exit;
}

/*
|---------------------------------------------------------
| 3️⃣ OUTPUT QR IMAGE
|---------------------------------------------------------
*/
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Ensure no output has happened before this line
QRcode::png($data, false, QR_ECLEVEL_L, 10, 2);
exit;