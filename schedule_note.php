<?php
session_start();
include 'db.php';

$userID = $_SESSION['userID'] ?? null;
if (!$userID) {
    echo json_encode(['status' => 'error']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$note = $data['note'] ?? '';

if (empty($note)) {
    echo json_encode(['status' => 'error']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO scheduling (date, message, createdAt, userID) VALUES (NOW(), ?, NOW(), ?)");
$stmt->bind_param("si", $note, $userID);

if ($stmt->execute()) {
    echo json_encode(['status' => 'saved']);
} else {
    echo json_encode(['status' => 'error']);
}
