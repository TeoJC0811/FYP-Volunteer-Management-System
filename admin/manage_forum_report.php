<?php
session_start();
include("../db.php");

// Security Check
$sessionRole = $_SESSION['role'] ?? '';
if (!isset($_SESSION['userID']) || $sessionRole !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle Admin Actions (Delete Post or Mark as Completed)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $reportID = intval($_GET['id']);
    $forumID = intval($_GET['forumID']);

    if ($_GET['action'] === 'delete') {
        // Delete the actual forum post (Reports will delete automatically via CASCADE)
        $del = $conn->prepare("DELETE FROM forum WHERE forumID = ?");
        $del->bind_param("i", $forumID);
        $del->execute();
    } 
    
    // In both cases (delete or just dismiss), we mark the report as completed
    $upd = $conn->prepare("UPDATE forum_reports SET reportStatus = 'completed' WHERE reportID = ?");
    $upd->bind_param("i", $reportID);
    $upd->execute();
    
    header("Location: manage_reports.php?msg=success");
    exit();
}

// Fetch all pending reports
$sql = "SELECT r.*, f.title, u.userName as reporterName 
        FROM forum_reports r
        JOIN forum f ON r.forumID = f.forumID
        JOIN user u ON r.userID = u.userID
        WHERE r.reportStatus = 'pending'
        ORDER BY r.createdAt DESC";
$reports = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Forum Reports</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #f4f4f4; }
        .btn { padding: 5px 10px; text-decoration: none; border-radius: 4px; color: white; font-size: 12px; }
        .btn-view { background: #3498db; }
        .btn-delete { background: #e74c3c; }
        .btn-dismiss { background: #2ecc71; }
    </style>
</head>
<body>
    <?php include("sidebar.php"); ?>
    <div class="main-content">
        <h2>Forum Reports</h2>
        <table>
            <tr>
                <th>Post Title</th>
                <th>Reporter</th>
                <th>Reason</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php while($row = $reports->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['reporterName']) ?></td>
                <td><strong><?= $row['reason'] ?></strong></td>
                <td><?= $row['createdAt'] ?></td>
                <td>
                    <a href="../view_forum.php?id=<?= $row['forumID'] ?>" class="btn btn-view" target="_blank">View Post</a>
                    <a href="manage_reports.php?action=delete&id=<?= $row['reportID'] ?>&forumID=<?= $row['forumID'] ?>" class="btn btn-delete" onclick="return confirm('Delete this post permanently?')">Delete Post</a>
                    <a href="manage_reports.php?action=dismiss&id=<?= $row['reportID'] ?>" class="btn btn-dismiss">Dismiss Report</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>