<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

// Get JSON data from request
$data = json_decode(file_get_contents("php://input"), true);
$notificationID = $data['id'] ?? null;
$userID = $_SESSION['userID'] ?? null;

if (!$userID || !$notificationID) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit();
}

// Soft delete the notification by setting isDeleted = 1
$sql = "UPDATE notification SET isDeleted = 1 WHERE notificationID = ? AND userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $notificationID, $userID);
$success = $stmt->execute();
$stmt->close();

echo json_encode(["success" => $success]);
?>
