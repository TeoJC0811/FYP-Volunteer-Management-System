<?php
$host = "127.0.0.1"; 
$user = "root";
$pass = "";
$db = "servetogether_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Added this to ensure fast and correct character encoding
mysqli_set_charset($conn, "utf8mb4");

/* 🧩 Include FPDF & FPDI for certificate generation */
require_once __DIR__ . '/fpdf/fpdf.php';
require_once __DIR__ . '/fpdi/src/autoload.php';

// Use FPDI namespace
use setasign\Fpdi\Fpdi;
?>