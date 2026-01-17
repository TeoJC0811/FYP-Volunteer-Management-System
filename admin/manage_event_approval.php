<?php
session_start();
include("../db.php");

// Only allow Admins
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle Approval/Rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $eventID = intval($_GET['id']);
    $status = ($_GET['action'] === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE event SET status = ? WHERE eventID = ?");
    $stmt->bind_param("si", $status, $eventID);
    $stmt->execute();
    header("Location: manage_event_approval.php?msg=updated");
    exit();
}

$sql = "SELECT e.*, u.userName FROM event e 
        JOIN user u ON e.organizerID = u.userID 
        WHERE e.status = 'pending' ORDER BY e.startDate ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Approvals</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .approval-card { background: white; padding: 20px; border-radius: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn-approve { background: #2ecc71; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; }
        .btn-reject { background: #e74c3c; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <?php include("sidebar.php"); ?>
    <div class="main-content">
        <h2>Pending Event Approvals</h2>
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="approval-card">
                    <div>
                        <strong><?= htmlspecialchars($row['eventName']) ?></strong><br>
                        <small>Organizer: <?= htmlspecialchars($row['userName']) ?> | Date: <?= $row['startDate'] ?></small>
                    </div>
                    <div>
                        <a href="../view_event.php?id=<?= $row['eventID'] ?>" target="_blank" style="margin-right:15px;">Review Details</a>
                        <a href="manage_event_approval.php?action=approve&id=<?= $row['eventID'] ?>" class="btn-approve">Approve</a>
                        <a href="manage_event_approval.php?action=reject&id=<?= $row['eventID'] ?>" class="btn-reject" onclick="return confirm('Reject this event?')">Reject</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No pending events to approve.</p>
        <?php endif; ?>
    </div>
</body>
</html>