<?php
session_start();
include("../db.php");

// Allow only admin & organizer
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: login.php");
    exit();
}

$message = $error = "";

// Handle delete forum
if (isset($_GET['delete'])) {
    $forumID = intval($_GET['delete']);
    
    $imgStmt = $conn->prepare("SELECT forumImage FROM forum WHERE forumID = ?");
    $imgStmt->bind_param("i", $forumID);
    $imgStmt->execute();
    $imgRes = $imgStmt->get_result();
    if ($imgRow = $imgRes->fetch_assoc()) {
        $dbImagePath = $imgRow['forumImage'] ?? '';
        $filePath = (strpos($dbImagePath, 'uploads/') === 0) ? "../" . $dbImagePath : "../uploads/forum_images/" . $dbImagePath;
        if (!empty($dbImagePath) && file_exists($filePath)) {
            unlink($filePath); 
        }
    }
    $imgStmt->close();

    $stmt = $conn->prepare("DELETE FROM forum WHERE forumID = ?");
    $stmt->bind_param("i", $forumID);
    if ($stmt->execute()) {
        $message = "✅ Forum post deleted successfully!";
    } else {
        $error = "❌ Error deleting forum post.";
    }
}

// Search handling
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$queryStr = "SELECT f.forumID, f.title, f.content, f.forumImage, f.createdDate, u.userName FROM forum f JOIN user u ON f.userID = u.userID";

if (!empty($search)) {
    $queryStr .= " WHERE f.title LIKE ? OR f.content LIKE ? ORDER BY f.createdDate DESC";
    $stmt = $conn->prepare($queryStr);
    $searchTerm = "%" . $search . "%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $queryStr .= " ORDER BY f.createdDate DESC";
    $result = $conn->query($queryStr);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Forums</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .main-content { 
            max-width: 95%; 
            margin: 40px 20px 40px 350px; 
            padding: 20px; 
        }
        
        h2 { margin-bottom: 20px; color: #333; text-align: center; font-weight: 700;}
        
        .search-bar { display: flex; justify-content: center; align-items: center; gap: 10px; margin: 20px 0 30px; padding: 15px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .search-bar input { padding: 10px 12px; flex-grow: 1; max-width: 500px; border: 1px solid #ced4da; border-radius: 5px; }
        .search-bar button { background-color: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .search-bar a.btn { background-color: #e9ecef; color: #333; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; }

        /* UPDATED: Changed to Grid with 2 columns */
        .forum-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 25px; 
            width: 100%; 
        }
        
        /* UPDATED: Card now stacks vertically to fit 2-column layout */
        .forum-card { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
            overflow: hidden; 
            display: flex; 
            flex-direction: column; 
            border: 1px solid #eee; 
            transition: transform 0.2s;
        }

        .forum-card:hover { transform: translateY(-5px); }
        
        .forum-img-box { 
            width: 100%; 
            height: 200px; 
            background: #f0f2f5; 
            overflow: hidden; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-bottom: 1px solid #eee; 
        }
        .forum-img-box img { width: 100%; height: 100%; object-fit: cover; }

        .card-body-content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 1.25rem; color: #1a202c; margin: 0 0 10px 0; font-weight: 700; }
        .card-text { color: #4a5568; line-height: 1.5; margin-bottom: 15px; flex-grow: 1; font-size: 0.95rem; min-height: 60px;}

        .card-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #edf2f7; }
        .card-meta { font-size: 0.85em; color: #718096; display: flex; flex-direction: column; gap: 4px; }
        .card-meta i { color: #3498db; margin-right: 6px;}

        .btn-delete { background-color: #fff5f5; color: #c53030; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; transition: 0.2s; border: 1px solid #feb2b2;}
        .btn-delete:hover { background-color: #c53030; color: white; }

        .success { background: #f0fff4; color: #276749; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid #c6f6d5; }
        .error { background: #fff5f5; color: #9b2c2c; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid #fed7d7; }

        @media (max-width: 1200px) { .forum-grid { grid-template-columns: 1fr; } .main-content { margin-left: 350px; } }
        @media (max-width: 900px) { .main-content { margin-left: 20px; } }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <h2><i class="fas fa-comments"></i> Manage Community Forum Posts</h2>

    <?php if ($message) echo "<div class='success'>$message</div>"; ?>
    <?php if ($error) echo "<div class='error'>$error</div>"; ?>

    <form method="get" action="manage_forum.php" class="search-bar">
        <input type="text" name="search" placeholder="Search title or content..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
        <a href="manage_forum.php" class="btn">Clear</a>
    </form>

    <div class="forum-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): 
                $content = $row['content'] ?? '';
                $dbImg = $row['forumImage'] ?? '';
                
                if (!empty($dbImg)) {
                    $imagePath = (strpos($dbImg, 'uploads/') === 0) ? "../" . $dbImg : "../uploads/forum_images/" . $dbImg;
                    $hasValidImage = file_exists($imagePath);
                } else {
                    $hasValidImage = false;
                }
            ?>
            <div class="forum-card">
                <div class="forum-img-box">
                    <?php if ($hasValidImage): ?>
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="Post Image">
                    <?php else: ?>
                        <div style="text-align: center; color: #ccc;">
                            <i class="fas fa-image fa-3x"></i><br>
                            <span style="font-size: 12px;">No Image</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-body-content">
                    <h3 class="card-title"><?= htmlspecialchars($row['title'] ?? 'No Title') ?></h3>
                    <div class="card-text">
                        <?php 
                            $truncated = substr($content, 0, 150); 
                            echo nl2br(htmlspecialchars($truncated)); 
                            if (strlen($content) > 150) echo "...";
                        ?>
                    </div>

                    <div class="card-footer">
                        <div class="card-meta">
                            <span><i class="fas fa-user-circle"></i> <?= htmlspecialchars($row['userName'] ?? 'User') ?></span>
                            <span><i class="fas fa-calendar-alt"></i> <?= date('d M Y', strtotime($row['createdDate'] ?? 'now')) ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="manage_forum.php?delete=<?= $row['forumID'] ?>" class="btn-delete" onclick="return confirm('Delete this post?');">Delete</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align:center; padding:50px; background:white; border-radius:12px; color: #718096;">No posts found matching your criteria.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>