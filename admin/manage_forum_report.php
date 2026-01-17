<?php
session_start();
include("../db.php");

// Security Check
$sessionRole = $_SESSION['role'] ?? '';
if (!isset($_SESSION['userID']) || $sessionRole !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/**
 * 1. HANDLE ADMIN ACTIONS
 */
if (isset($_GET['action']) && isset($_GET['forumID'])) {
    $forumID = intval($_GET['forumID']);

    if ($_GET['action'] === 'delete') {
        // Delete the post - Cascade will handle forum_reports table
        $del = $conn->prepare("DELETE FROM forum WHERE forumID = ?");
        $del->bind_param("i", $forumID);
        $del->execute();
    } elseif ($_GET['action'] === 'dismiss') {
        // Just mark all pending reports for THIS forum post as completed
        $upd = $conn->prepare("UPDATE forum_reports SET reportStatus = 'completed' WHERE forumID = ? AND reportStatus = 'pending'");
        $upd->bind_param("i", $forumID);
        $upd->execute();
    }
    
    header("Location: manage_forum_report.php?msg=success");
    exit();
}

/**
 * 2. FETCH GROUPED REPORTS
 * We use GROUP_CONCAT to merge all reasons for the same forumID into one field
 * We also count the total reports per post
 */
$sql = "SELECT 
            f.forumID, 
            f.title, 
            COUNT(r.reportID) as total_reports,
            GROUP_CONCAT(r.reason) as all_reasons,
            MAX(r.createdAt) as latest_report
        FROM forum_reports r
        JOIN forum f ON r.forumID = f.forumID
        WHERE r.reportStatus = 'pending'
        GROUP BY f.forumID
        ORDER BY total_reports DESC, latest_report DESC";

$reports = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Forum Reports</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f8f9fa; }
        .main-content { padding: 30px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        th, td { padding: 15px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #333; color: white; text-transform: uppercase; font-size: 13px; letter-spacing: 1px; }
        
        /* Summary Badge for total reports */
        .report-count { background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-left: 5px; }
        
        /* Reason Badges */
        .reason-list { display: flex; flex-wrap: wrap; gap: 5px; }
        .reason-badge { background: #e9ecef; color: #495057; padding: 4px 10px; border-radius: 4px; font-size: 12px; border: 1px solid #dee2e6; }
        
        .btn { padding: 8px 12px; text-decoration: none; border-radius: 5px; color: white; font-size: 12px; font-weight: bold; transition: 0.2s; display: inline-block; }
        .btn-view { background: #3498db; }
        .btn-delete { background: #e74c3c; }
        .btn-dismiss { background: #2ecc71; }
        .btn:hover { opacity: 0.8; transform: translateY(-1px); }
        
        .empty-state { text-align: center; padding: 50px; background: white; border-radius: 8px; color: #666; }
    </style>
</head>
<body>
    <?php include("sidebar.php"); ?>

    <div class="main-content">
        <h2 style="margin-bottom: 20px;">🚩 Forum Moderation Queue</h2>

        <?php if ($reports && $reports->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th width="30%">Reported Post</th>
                        <th width="40%">Reasons for Report</th>
                        <th width="15%">Latest Report</th>
                        <th width="15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $reports->fetch_assoc()): 
                        // Process the concatenated reasons to show counts (e.g., "Spam x3")
                        $reasonsArray = explode(',', $row['all_reasons']);
                        $reasonCounts = array_count_values($reasonsArray);
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($row['title']) ?></strong>
                            <span class="report-count" title="Total Reports"><?= $row['total_reports'] ?></span>
                        </td>
                        <td>
                            <div class="reason-list">
                                <?php foreach ($reasonCounts as $reason => $count): ?>
                                    <span class="reason-badge">
                                        <?= htmlspecialchars($reason) ?> <?= $count > 1 ? "<strong>(x$count)</strong>" : "" ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="font-size: 13px; color: #666;">
                            <?= date('d M Y, H:i', strtotime($row['latest_report'])) ?>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <a href="../view_forum.php?id=<?= $row['forumID'] ?>" class="btn btn-view" target="_blank">View Post</a>
                                <a href="manage_forum_report.php?action=dismiss&forumID=<?= $row['forumID'] ?>" class="btn btn-dismiss">Dismiss All</a>
                                <a href="manage_forum_report.php?action=delete&forumID=<?= $row['forumID'] ?>" class="btn btn-delete" onclick="return confirm('Permanently delete this post?')">Delete Post</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 18px;">✅ No pending reports. The forum is clean!</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>