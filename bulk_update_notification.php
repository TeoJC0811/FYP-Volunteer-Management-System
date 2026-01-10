<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// 1. INPUT HANDLING
$data = json_decode(file_get_contents('php://input'), true);
$notificationIDs = $data['ids'] ?? [];
$userID = $_SESSION['userID'];

if (empty($notificationIDs) || !is_array($notificationIDs)) {
    echo json_encode(['success' => false, 'error' => 'Invalid IDs provided']);
    exit();
}

$validIDs = array_filter($notificationIDs, 'is_numeric');

if (empty($validIDs)) {
    echo json_encode(['success' => false, 'error' => 'No valid notifications selected.']);
    exit();
}

// 2. DYNAMIC SQL CONSTRUCTION (Using IN clause)
$placeholders = implode(',', array_fill(0, count($validIDs), '?'));
$bindTypes = str_repeat('i', count($validIDs)) . 'i'; // IDs are 'i', plus one 'i' for the userID

$sql = "UPDATE notification SET isRead = 1 WHERE notificationID IN ($placeholders) AND userID = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
    exit();
}

// 3. DYNAMIC BINDING (Safe method for variable argument lists)
$params = array_merge([$bindTypes], $validIDs, [$userID]);
$refs = [];
foreach ($params as $key => $value) {
    $refs[$key] = &$params[$key];
}

if (call_user_func_array([$stmt, 'bind_param'], $refs) === false) {
    echo json_encode(['success' => false, 'error' => 'Binding failed: ' . $stmt->error]);
    $stmt->close();
    exit();
}

// 4. EXECUTION
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Execution failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>