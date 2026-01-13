<?php
/* |--------------------------------------------------------------------------
| SMART DATABASE CONNECTION (Localhost & Render)
|--------------------------------------------------------------------------
*/

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

// Ensure fast and correct character encoding
mysqli_set_charset($conn, "utf8mb4");

/* 🧩 Include FPDF & FPDI for certificate generation */
// We use file_exists locally to prevent crashes if libraries aren't downloaded
if (file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    require_once __DIR__ . '/fpdf/fpdf.php';
}
if (file_exists(__DIR__ . '/fpdi/src/autoload.php')) {
    require_once __DIR__ . '/fpdi/src/autoload.php';
}

// Use FPDI namespace
use setasign\Fpdi\Fpdi;
?>