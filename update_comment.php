<?php
session_start();
header("Content-Type: application/json");
include("db.php");

// Require login
if (!isset($_SESSION['userID'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$userID = $_SESSION['userID'];

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['commentID']) || !isset($data['comment'])) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

$commentID = intval($data['commentID']);
$comment   = trim($data['comment']);

// Basic validation
if ($comment === "") {
    echo json_encode(["success" => false, "message" => "Comment cannot be empty"]);
    exit;
}

// Update query (update updatedAt as edit timestamp)
$stmt = $conn->prepare("UPDATE Comment SET comment = ?, updatedAt = NOW() WHERE commentID = ? AND userID = ?");
$stmt->bind_param("sii", $comment, $commentID, $userID);

if ($stmt->execute()) {
    echo json_encode([
        "success"   => true,
        "updatedAt" => date("Y-m-d H:i:s")
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Update failed"]);
}
?>
