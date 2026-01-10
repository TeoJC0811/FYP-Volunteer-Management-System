<?php
// /servetogether/admin/generate_qr_image.php

declare(strict_types=1);

// Include QR library
require_once __DIR__ . "/../qrcode_lib/qrlib.php";

/*
|---------------------------------------------------------
| 1️⃣ GET FULL CHECK-IN URL (ALREADY URL-ENCODED)
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

// Error correction: L
// Size: 10 (clear scan on phone)
// Margin: 2
QRcode::png($data, false, QR_ECLEVEL_L, 10, 2);
exit;
