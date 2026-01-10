<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

$userID = $_SESSION['userID'] ?? null;

if (!$userID) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Handle different ID keys sent by various pages (id, eventID, or courseID)
$type = $input['type'] ?? null; 
$activityID = $input['id'] ?? ($input['eventID'] ?? ($input['courseID'] ?? null));

if (!$activityID || !in_array($type, ['event', 'course'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid activity ID or type']);
    exit;
}

// Determine which database column to use
$column = $type === 'event' ? 'eventID' : 'courseID';

// ✅ 1. Check if already wishlisted
$stmt = $conn->prepare("SELECT 1 FROM wishlist WHERE userID = ? AND $column = ?");
$stmt->bind_param("ii", $userID, $activityID);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // ✅ 2. Already wishlisted → remove it
    $delete = $conn->prepare("DELETE FROM wishlist WHERE userID = ? AND $column = ?");
    $delete->bind_param("ii", $userID, $activityID);
    
    if ($delete->execute()) {
        echo json_encode(['status' => 'removed']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
    }
    $delete->close();
} else {
    // ✅ 3. Not wishlisted → add it
    $now = date('Y-m-d H:i:s');
    $note = ''; 
    $insert = $conn->prepare("INSERT INTO wishlist (userID, $column, createdAt, note) VALUES (?, ?, ?, ?)");
    
    // Param types: i (userID), i (activityID), s (now), s (note)
    $insert->bind_param("iiss", $userID, $activityID, $now, $note);
    
    if ($insert->execute()) {
        echo json_encode(['status' => 'added']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add item']);
    }
    $insert->close();
}

$stmt->close();
$conn->close();
?>