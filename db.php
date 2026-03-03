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
        // Basic split to avoid errors on empty lines
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(trim($parts[0]) . "=" . trim($parts[1]));
        }
    }
}

$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($hostName, ['localhost', '127.0.0.1']);

// PULL FROM ENVIRONMENT (Local .env or Render Dashboard)
$host = "159.203.185.228"; // Forced IP
$port = 10553;
$user = "avnadmin";
$pass = "AVNS_r0S8JDyWQULHCVz98dY";
$db   = "defaultdb";

// Fallback for Localhost XAMPP if .env is missing or you want local MySQL
if ($isLocal && !$host) {
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

// Aiven and modern cloud DBs REQUIRE the port to be an integer (int)
// and usually REQUIRE an SSL connection to succeed.
$connection_success = mysqli_real_connect(
    $conn, 
    $host, 
    $user, 
    $pass, 
    $db, 
    (int)$port, // Fix: Ensure port is a number
    NULL, 
    MYSQLI_CLIENT_SSL // Fix: Required for Aiven
);

if (!$connection_success) {
    // Log the error for you, but show a generic message to users
    error_log("Connection failed: " . mysqli_connect_error());
    
    // While debugging, you can uncomment the line below to see the exact error on screen:
    // die("Debug Error: " . mysqli_connect_error());
    
    die("Database connection failed. Please check your configuration.");
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

// Ensure the Fpdi class is available if needed elsewhere
// use setasign\Fpdi\Fpdi; 
?>