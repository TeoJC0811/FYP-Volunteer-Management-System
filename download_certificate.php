<?php
session_start();
require 'db.php';

// ✅ Ensure user is logged in
if (!isset($_SESSION['userID'])) {
    http_response_code(403);
    die("Access denied");
}

// ✅ Validate file parameter
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    die("File not specified");
}

// ✅ Sanitize file name to prevent directory traversal
$file = basename($_GET['file']);

// ✅ Define full file path
$filePath = __DIR__ . "/certificate/generated/$file";

// ✅ Check file existence
if (!file_exists($filePath)) {
    http_response_code(404);
    die("File not found");
}

// ✅ Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// ✅ Output file content
readfile($filePath);
exit;
