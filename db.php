<?php
/* |--------------------------------------------------------------------------
| SMART DATABASE CONNECTION (Professional Secret Management)
|--------------------------------------------------------------------------
*/

// 1. Set PHP Global Timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Helper function to load .env variables locally for XAMPP
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}

$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($hostName, ['localhost', '127.0.0.1']);

// PULL FROM ENVIRONMENT (Local .env or Render Dashboard)
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

// Fallback for Localhost XAMPP if .env is missing or you want local MySQL
if ($isLocal && !$host) {
    $host = "localhost";
    $port = 3306; 
    $user = "root";
    $pass = "";
    $db   = "servetogether_db";
}

// Connect to Database
$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    // Professional security: don't show full error details to public
    error_log("Connection failed: " . mysqli_connect_error());
    die("Database connection failed. Please check your configuration.");
}

// 2. Set MySQL Session Timezone to Malaysia (UTC+8)
mysqli_query($conn, "SET time_zone = '+08:00'");
mysqli_set_charset($conn, "utf8mb4");

/* 🧩 Include FPDF & FPDI */
if (file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    require_once __DIR__ . '/fpdf/fpdf.php';
}
if (file_exists(__DIR__ . '/fpdi/src/autoload.php')) {
    require_once __DIR__ . '/fpdi/src/autoload.php';
}

use setasign\Fpdi\Fpdi;
?>