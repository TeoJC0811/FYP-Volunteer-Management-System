<?php
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    echo json_encode(["success" => false, "message" => "Login required"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$forumID = intval($data['forumID']);
$voteValue = intval($data['voteValue']);
$userID = $_SESSION['userID'];

if (!in_array($voteValue, [1, -1])) {
    echo json_encode(["success" => false, "message" => "Invalid vote"]);
    exit;
}

// Check if user has already voted
$check = $conn->prepare("SELECT voteValue FROM forumvote WHERE forumID=? AND userID=?");
$check->bind_param("ii", $forumID, $userID);
$check->execute();
$existingVote = $check->get_result()->fetch_assoc();

if ($existingVote) {
    if ($existingVote['voteValue'] == $voteValue) {
        // Same vote clicked again → remove vote
        $del = $conn->prepare("DELETE FROM forumvote WHERE forumID=? AND userID=?");
        $del->bind_param("ii", $forumID, $userID);
        $del->execute();
    } else {
        // Different vote → update
        $upd = $conn->prepare("UPDATE forumvote SET voteValue=? WHERE forumID=? AND userID=?");
        $upd->bind_param("iii", $voteValue, $forumID, $userID);
        $upd->execute();
    }
} else {
    // New vote
    $stmt = $conn->prepare("INSERT INTO forumvote (forumID, userID, voteValue) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $forumID, $userID, $voteValue);
    $stmt->execute();
}

// Get updated total votes
$res = $conn->query("SELECT COALESCE(SUM(voteValue),0) as totalVotes FROM forumvote WHERE forumID=$forumID");
$totalVotes = $res->fetch_assoc()['totalVotes'];

echo json_encode(["success" => true, "totalVotes" => $totalVotes]);
?>
