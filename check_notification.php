<?php
// Ensure no extra whitespace before this tag
session_start();
include 'db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

$userID = $_SESSION['userID'] ?? null;
$lastToastedID = isset($_SESSION['last_toasted_id']) ? (int)$_SESSION['last_toasted_id'] : 0;

$response = [
    'new_notification' => false,
    'message' => '',
    'unread_count' => 0
];

if ($userID) {
    // We check for the NEWEST unread notification that is higher than our last shown ID
    $stmt = $conn->prepare("SELECT notificationID, message FROM notification WHERE userID = ? AND notificationID > ? ORDER BY notificationID DESC LIMIT 1");
    $stmt->bind_param("ii", $userID, $lastToastedID);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($data = $res->fetch_assoc()) {
        $response['new_notification'] = true;
        $response['message'] = $data['message'];
        
        // Update the session tracker to the new ID
        $_SESSION['last_toasted_id'] = (int)$data['notificationID'];

        $stmtCount = $conn->prepare("SELECT COUNT(*) as unread FROM notification WHERE userID = ? AND isRead = 0");
        $stmtCount->bind_param("i", $userID);
        $stmtCount->execute();
        $response['unread_count'] = $stmtCount->get_result()->fetch_assoc()['unread'] ?? 0;
        $stmtCount->close();
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($response);
exit();