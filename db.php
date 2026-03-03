<?php
/* |--------------------------------------------------------------------------
| SMART DATABASE CONNECTION (ServeTogether Deployment Fix)
|--------------------------------------------------------------------------
*/

// 1. Set PHP Global Timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Helper function to load .env variables locally for XAMPP
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(trim($parts[0]) . "=" . trim($parts[1]));
        }
    }
}

$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($hostName, ['localhost', '127.0.0.1']);

// PULL FROM ENVIRONMENT (Local .env or Render Dashboard)
// Added a fallback to the IP address directly in case getenv() is empty
$host = getenv('DB_HOST') ?: "159.203.185.228"; 
$port = getenv('DB_PORT') ?: 10553;
$user = getenv('DB_USER') ?: "avnadmin";
$pass = getenv('DB_PASS') ?: "AVNS_r0S8JDyWQULHCVz98dY";
$db   = getenv('DB_NAME') ?: "defaultdb";

// Fallback for Localhost XAMPP ONLY if truly on localhost
if ($isLocal && getenv('DB_HOST') === false) {
    $host = "localhost";
    $port = 3306; 
    $user = "root";
    $pass = "";
    $db   = "servetogether_db";
}

// --- DATABASE CONNECTION START ---

$conn = mysqli_init();

if (!$conn) {
    die("mysqli_init failed");
}

// RELAXED SSL: Tell PHP to use SSL but skip strict certificate chain verification
// This prevents the "502 Gateway Timeout" hang on Render.
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

$connection_success = mysqli_real_connect(
    $conn, 
    $host, 
    $user, 
    $pass, 
    $db, 
    (int)$port, 
    NULL, 
    MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
);

if (!$connection_success) {
    // Detailed error logging for Render Logs
    $error_msg = mysqli_connect_error();
    error_log("DATABASE CONNECTION ERROR: " . $error_msg);
    
    // On screen message
    die("Database connection failed: " . $error_msg);
}

// --- DATABASE CONNECTION END ---

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
?>