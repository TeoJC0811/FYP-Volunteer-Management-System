<?php
/* 🌐 Database Connection using Render Environment Variables */
$host = getenv('DB_HOST');
$port = getenv('DB_PORT'); // Added port for Aiven (10553)
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

// Connect to Aiven MySQL Cloud
$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Ensure fast and correct character encoding
mysqli_set_charset($conn, "utf8mb4");

/* 🧩 Include FPDF & FPDI for certificate generation */
require_once __DIR__ . '/fpdf/fpdf.php';
require_once __DIR__ . '/fpdi/src/autoload.php';

// Use FPDI namespace
use setasign\Fpdi\Fpdi;
?>