<?php
/* |--------------------------------------------------------------------------
| SMART DATABASE CONNECTION (Localhost & Render)
|--------------------------------------------------------------------------
*/

// 1. Set PHP Global Timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($hostName, ['localhost', '127.0.0.1']);

if ($isLocal) {
    // --- 🏠 LOCALHOST SETTINGS (XAMPP) ---
    $host = "localhost";
    $port = 3306; 
    $user = "root";             // Default XAMPP user
    $pass = "";                 // Default XAMPP password
    $db   = "servetogether_db"; // Your local DB name
} else {
    // --- 🌐 RENDER SETTINGS (Aiven Cloud) ---
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $db   = getenv('DB_NAME');
}

// Connect to Database
$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// 2. Set MySQL Session Timezone to Malaysia (UTC+8)
// This ensures SQL functions like NOW() return 11:11 PM instead of 3:11 PM
mysqli_query($conn, "SET time_zone = '+08:00'");

// Ensure fast and correct character encoding
mysqli_set_charset($conn, "utf8mb4");

/* 🧩 Include FPDF & FPDI for certificate generation */
if (file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    require_once __DIR__ . '/fpdf/fpdf.php';
}
if (file_exists(__DIR__ . '/fpdi/src/autoload.php')) {
    require_once __DIR__ . '/fpdi/src/autoload.php';
}

// Use FPDI namespace
use setasign\Fpdi\Fpdi;
?>