<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userID'])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit();
}

$userID = $_SESSION['userID'];

// Get JSON body
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['date'], $data['message'])) {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
    exit();
}

$date = $data['date'];
$message = $data['message'];

// Insert into scheduling table
$stmt = $conn->prepare("INSERT INTO scheduling (date, message, userID, createdAt) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("ssi", $date, $message, $userID);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "scheduleID" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
