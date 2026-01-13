<?php
session_start();
require 'db.php';
require 'fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

// Validate access
if (!isset($_SESSION['userID'])) {
    die("Invalid access: User not logged in.");
}

$userID = $_SESSION['userID'];

// Detect type (event or course) based on registration ID
$isEvent = isset($_GET['eventRegisterID']);
$isCourse = isset($_GET['courseRegisterID']);

if (!$isEvent && !$isCourse) {
    die("Invalid request: No registration ID provided.");
}

// Fetch event or course data using registration ID
if ($isEvent) {
    $regID = intval($_GET['eventRegisterID']);
    $stmt = $conn->prepare("
        SELECT e.eventName AS title, e.endDate AS endDate, er.userID
        FROM eventregistration er
        JOIN event e ON er.eventID = e.eventID
        WHERE er.eventRegisterID = ?
    ");
} else {
    $regID = intval($_GET['courseRegisterID']);
    $stmt = $conn->prepare("
        SELECT c.courseName AS title, c.courseDate AS endDate, cr.userID
        FROM courseregistration cr
        JOIN course c ON cr.courseID = c.courseID
        WHERE cr.courseRegisterID = ?
    ");
}

$stmt->bind_param("i", $regID);
$stmt->execute();
$stmt->bind_result($title, $endDate, $regUserID);
if (!$stmt->fetch()) {
    die("⚠️ Record not found for this registration ID.");
}
$stmt->close();

// Ensure the logged-in user owns this registration
if ($regUserID != $userID) {
    die("⚠️ You are not authorized to download this certificate.");
}

// Template PDF path
$templatePath = __DIR__ . "/certificate/certificate_template.pdf";
if (!file_exists($templatePath)) {
    die("❌ Template does not exist! Check file path: $templatePath");
}

// Fetch user info
$stmt = $conn->prepare("SELECT userName FROM user WHERE userID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$stmt->bind_result($userName);
if (!$stmt->fetch()) {
    die("⚠️ User not found.");
}
$stmt->close();

// Generate PDF
$pdf = new FPDI();
$pdf->AddPage('L');
$pdf->setSourceFile($templatePath);
$tplIdx = $pdf->importPage(1);
$pdf->useTemplate($tplIdx, 0, 0, 297);

// Text styling
$pdf->SetTextColor(0, 0, 0);

// Participant name
$pdf->SetFont('Arial', 'B', 24);
$pdf->SetXY(10, 95);
$pdf->Cell(0, 10, strtoupper($userName), 0, 1, 'C');

// Display eventName or courseName
$pdf->SetFont('Arial', '', 18);
$pdf->SetXY(10, 120);
$label = $isEvent ? "Event" : "Training Course";
$pdf->Cell(0, 10, "$label: $title", 0, 1, 'C');

// Date
$pdf->SetFont('Arial', '', 14);
$pdf->SetXY(10, 145);
$pdf->Cell(0, 10, "$label Date: " . date("F d, Y", strtotime($endDate)), 0, 1, 'C');

// Filename
$cleanTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title);
$fileName = "certificate_" . strtolower($label) . "_" . $cleanTitle . ".pdf";

// Download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
$pdf->Output('D');
exit;
?>
