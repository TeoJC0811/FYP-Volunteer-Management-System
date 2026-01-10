<?php
session_start();
require 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id'])) {
    $id = intval($data['id']);
    $stmt = $conn->prepare("UPDATE notification SET isRead = 1 WHERE notificationID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["success" => true]);
    exit();
}

echo json_encode(["success" => false]);
