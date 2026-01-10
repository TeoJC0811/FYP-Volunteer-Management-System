<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit();
}

$userID = $_SESSION['userID'];

// Get JSON body
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['scheduleID'])) {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
    exit();
}

$scheduleID = (int)$data['scheduleID'];

// Delete note only if it belongs to this user
$stmt = $conn->prepare("DELETE FROM scheduling WHERE scheduleID = ? AND userID = ?");
$stmt->bind_param("ii", $scheduleID, $userID);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Note not found or not yours"]);
}

$stmt->close();
$conn->close();
?>
