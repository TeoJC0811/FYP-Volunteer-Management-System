<?php
session_start();
include 'db.php';

// Set header to JSON so the frontend can parse the response correctly
header('Content-Type: application/json');

$userID = $_SESSION['userID'] ?? null;
$data = json_decode(file_get_contents("php://input"), true);

$wishlistID = $data['wishlistID'] ?? null;
$note = $data['note'] ?? '';

// Basic validation: must be logged in and have a wishlistID
if (!$wishlistID || !$userID) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized or missing ID']);
    exit;
}

// SECURE UPDATE: Ensure the wishlist item actually belongs to the logged-in user
$stmt = $conn->prepare("UPDATE wishlist SET note = ? WHERE wishlistID = ? AND userID = ?");
$stmt->bind_param("sii", $note, $wishlistID, $userID);

if ($stmt->execute()) {
    // If no rows were affected, it means the ID didn't match the user
    if ($stmt->affected_rows >= 0) {
        echo json_encode(['status' => 'saved']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'No changes made or item not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Database error']);
}

$stmt->close();
$conn->close();
?>