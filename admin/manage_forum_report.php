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
        $del = $conn->prepare("DELETE FROM forum WHERE forumID = ?");
        $del->bind_param("i", $forumID);
        $del->execute();
    } elseif ($_GET['action'] === 'dismiss') {
        $upd = $conn->prepare("UPDATE forum_reports SET reportStatus = 'completed' WHERE forumID = ? AND reportStatus = 'pending'");
        $upd->bind_param("i", $forumID);
        $upd->execute();
    }
    
    header("Location: manage_forum_report.php?msg=success");
    exit();
}

/**
 * 2. FETCH GROUPED REPORTS
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
    <title>Forum Moderation Queue</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { padding: 40px; }
        
        .page-header { margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .page-header h2 { margin: 0; color: #1c1e21; display: flex; align-items: center; gap: 10px; }

        /* Card Container */
        .report-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }

        /* Report Card Style */
        .report-card { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 20px; display: flex; flex-direction: column; transition: transform 0.2s; border: 1px solid #e1e4e8; }
        .report-card:hover { transform: translateY(-3px); }

        .card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .post-title { font-size: 18px; font-weight: bold; color: #333; margin: 0; flex: 1; padding-right: 10px; line-height: 1.4; }
        
        /* Gravity/Severity Badge */
        .severity-badge { background: #ffebe9; color: #cf222e; padding: 4px 10px; border-radius: 20px; font-size: 13px; font-weight: 700; border: 1px solid #ffcfcc; white-space: nowrap; }

        .reason-container { margin-bottom: 20px; flex-grow: 1; }
        .reason-label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 8px; display: block; font-weight: 600; }
        .reason-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .tag { background: #f6f8fa; color: #24292f; border: 1px solid #d0d7de; padding: 4px 10px; border-radius: 6px; font-size: 12px; }
        .tag strong { color: #cf222e; margin-left: 4px; }

        .card-footer { border-top: 1px solid #eee; padding-top: 15px; display: flex; justify-content: space-between; align-items: center; }
        .report-time { font-size: 12px; color: #888; }
        
        /* Action Buttons */
        .action-group { display: flex; gap: 10px; }
        .btn-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: 0.2s; border: none; cursor: pointer; }
        .btn-view { background-color: #f0f7ff; color: #0969da; }
        .btn-dismiss { background-color: #dafbe1; color: #1a7f37; }
        .btn-delete { background-color: #ffebe9; color: #cf222e; }
        .btn-icon:hover { filter: brightness(0.9); }

        .empty-state { grid-column: 1 / -1; text-align: center; padding: 100px 20px; color: #666; }
        .empty-state i { font-size: 50px; color: #ccc; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include("sidebar.php"); ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fa-solid fa-shield-halved"></i> Forum Moderation Queue</h2>
        </div>

        <div class="report-grid">
            <?php if ($reports && $reports->num_rows > 0): ?>
                <?php while($row = $reports->fetch_assoc()): 
                    $reasonsArray = explode(',', $row['all_reasons']);
                    $reasonCounts = array_count_values($reasonsArray);
                ?>
                <div class="report-card">
                    <div class="card-top">
                        <h3 class="post-title"><?= htmlspecialchars($row['title']) ?></h3>
                        <div class="severity-badge">
                            <i class="fa-solid fa-circle-exclamation"></i> <?= $row['total_reports'] ?> Reports
                        </div>
                    </div>

                    <div class="reason-container">
                        <span class="reason-label">Reasons cited:</span>
                        <div class="reason-tags">
                            <?php foreach ($reasonCounts as $reason => $count): ?>
                                <span class="tag">
                                    <?= htmlspecialchars($reason) ?><?php if($count > 1): ?><strong>(x<?= $count ?>)</strong><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="report-time">
                            <i class="fa-regular fa-clock"></i> Last report: <?= date('M d, H:i', strtotime($row['latest_report'])) ?>
                        </div>
                        <div class="action-group">
                            <a href="../view_forum.php?id=<?= $row['forumID'] ?>" class="btn-icon btn-view" title="View Original Post" target="_blank">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="manage_forum_report.php?action=dismiss&forumID=<?= $row['forumID'] ?>" class="btn-icon btn-dismiss" title="Dismiss All Reports">
                                <i class="fa-solid fa-check"></i>
                            </a>
                            <a href="manage_forum_report.php?action=delete&forumID=<?= $row['forumID'] ?>" class="btn-icon btn-delete" title="Delete Post Permanently" onclick="return confirm('Delete this post?')">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-face-smile"></i>
                    <h3>Queue is empty</h3>
                    <p>All reported posts have been reviewed.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>