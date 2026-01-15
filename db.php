<?php
/* |--------------------------------------------------------------------------
| SMART DATABASE CONNECTION & CLOUDINARY CONFIGURATION
|--------------------------------------------------------------------------
*/

// 1. Set PHP Global Timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// 2. Load Composer Autoloader (Required for Cloudinary)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// 3. Initialize Cloudinary Configuration with your keys
use Cloudinary\Configuration\Configuration;

Configuration::instance([
    'cloud' => [
        'cloud_name' => 'dc8pbufsu', 
        'api_key'    => '966352221533298', 
        'api_secret' => '01MGAvggFDVF56n44h-bFtR9o80'
    ],
    'url' => [
        'secure' => true
    ]
]);

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

// 4. Set MySQL Session Timezone to Malaysia (UTC+8)
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