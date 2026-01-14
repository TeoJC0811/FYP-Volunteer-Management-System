<?php
session_start();
header("Content-Type: application/json");

// Ensure PHP uses the correct timezone for returning the timestamp in the JSON
date_default_timezone_set('Asia/Kuala_Lumpur');

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

/* | Update query
| We use NOW() in SQL. Because of the mysqli_query($conn, "SET time_zone = '+08:00'"); 
| in your db.php, SQL's NOW() will correctly use Malaysia time.
*/
$stmt = $conn->prepare("UPDATE comment SET comment = ?, updatedAt = NOW() WHERE commentID = ? AND userID = ?");
$stmt->bind_param("sii", $comment, $commentID, $userID);

if ($stmt->execute()) {
    // Return success and the formatted timestamp for the frontend to display immediately
    echo json_encode([
        "success"   => true,
        "updatedAt" => date("Y-m-d H:i:s") 
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Update failed"]);
}

$stmt->close();
$conn->close();
?>