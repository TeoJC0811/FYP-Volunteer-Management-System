<?php
session_start();
require '../db.php'; 
require '../fpdi/src/autoload.php'; // Path to your existing FPDI library

use setasign\Fpdi\Fpdi;

$regID = intval($_GET['registerID'] ?? 0);
$userID = $_SESSION['userID'];

// Fetch data for the bill
$stmt = $conn->prepare("
    SELECT u.userName, c.courseName, c.fee, cr.registrationDate 
    FROM courseregistration cr
    JOIN user u ON cr.userID = u.userID
    JOIN course c ON cr.courseID = c.courseID
    WHERE cr.courseRegisterID = ?
");
$stmt->bind_param("i", $regID);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) die("⚠️ Billing record not found.");

// Create PDF
$pdf = new Fpdi();
$pdf->AddPage('P'); // Portrait
// Use a simple template or start blank if you don't have a billing PDF yet
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor(46, 204, 113); // ServeTogether Green
$pdf->Cell(0, 20, "ServeTogether Foundation", 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, "Official Payment Receipt", 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, "Receipt ID:"); $pdf->SetFont('Arial', '', 12); $pdf->Cell(0, 10, "#ST-INV-" . $regID, 0, 1);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, "Name:"); $pdf->SetFont('Arial', '', 12); $pdf->Cell(0, 10, strtoupper($data['userName']), 0, 1);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, "Course:"); $pdf->SetFont('Arial', '', 12); $pdf->Cell(0, 10, $data['courseName'], 0, 1);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, "Amount Paid:"); $pdf->SetFont('Arial', 'B', 12); $pdf->SetTextColor(0, 128, 0); $pdf->Cell(0, 10, "RM " . number_format($data['fee'], 2), 0, 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(20);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, "This is a computer-generated receipt. No signature required.", 0, 1, 'C');

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Receipt_#'.$regID.'.pdf"');
$pdf->Output('I'); 
exit;