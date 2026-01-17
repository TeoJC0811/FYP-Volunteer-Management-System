<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['userID'])) {
    $forumID = intval($_POST['forumID']);
    $userID = $_SESSION['userID']; // The person reporting
    $reason = $_POST['reason'];

    // Validate reason against your ENUM values
    $validReasons = ['Spam', 'Harassment', 'Inappropriate Content', 'Hate Speech', 'Other'];
    if (!in_array($reason, $validReasons)) {
        echo json_encode(['success' => false, 'message' => 'Invalid reason selected.']);
        exit;
    }

    // Check if user already reported this post (to prevent spamming reports)
    $check = $conn->prepare("SELECT reportID FROM forum_reports WHERE forumID = ? AND userID = ? AND reportStatus = 'pending'");
    $check->bind_param("ii", $forumID, $userID);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reported this post.']);
        exit;
    }

    // Insert report
    $stmt = $conn->prepare("INSERT INTO forum_reports (forumID, userID, reason, reportStatus) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("iis", $forumID, $userID, $reason);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
}
?>