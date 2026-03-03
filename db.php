<?php
/* |--------------------------------------------------------------------------
| SMART DATABASE CONNECTION (Local + Render + Aiven)
|--------------------------------------------------------------------------
*/

// 1️⃣ Set PHP Global Timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

/* --------------------------------------------------------------------------
   LOAD .env FILE (ONLY FOR LOCAL DEVELOPMENT)
-------------------------------------------------------------------------- */

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

/* --------------------------------------------------------------------------
   DETECT ENVIRONMENT
-------------------------------------------------------------------------- */

$hostName = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = ($hostName === 'localhost' || $hostName === '127.0.0.1');

/* --------------------------------------------------------------------------
   DATABASE CONNECTION
-------------------------------------------------------------------------- */

if ($isLocal) {

    // 🟢 LOCAL XAMPP (NO SSL)
    $conn = mysqli_connect(
        "localhost",
        "root",
        "",
        "servetogether_db",
        3306
    );

} else {

    // 🔵 RENDER + AIVEN (SSL REQUIRED)
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $db   = getenv('DB_NAME');

    if (!$host || !$user || !$db) {
        die("Environment variables not set properly.");
    }

    $conn = mysqli_init();

    if (!$conn) {
        die("mysqli_init failed");
    }

    // Enable SSL (Aiven requires SSL)
    mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

    $connected = mysqli_real_connect(
        $conn,
        $host,
        $user,
        $pass,
        $db,
        (int)$port,
        NULL,
        MYSQLI_CLIENT_SSL
    );

    if (!$connected) {
        error_log("DATABASE CONNECTION ERROR: " . mysqli_connect_error());
        die("Database connection failed.");
    }
}

/* --------------------------------------------------------------------------
   POST CONNECTION CONFIG
-------------------------------------------------------------------------- */

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_query($conn, "SET time_zone = '+08:00'");
mysqli_set_charset($conn, "utf8mb4");

/* --------------------------------------------------------------------------
   OPTIONAL LIBRARIES
-------------------------------------------------------------------------- */

if (file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    require_once __DIR__ . '/fpdf/fpdf.php';
}

if (file_exists(__DIR__ . '/fpdi/src/autoload.php')) {
    require_once __DIR__ . '/fpdi/src/autoload.php';
}

?>