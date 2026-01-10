<?php 
session_start(); 
include("db.php"); 

$userID = $_SESSION['userID'] ?? 0;
$search = $_GET['search'] ?? ''; 
$tag = $_GET['tag'] ?? ''; 

// --- Fetch all tags for filter dropdown --- 
$tagResult = $conn->query("SELECT * FROM tag ORDER BY tagName ASC"); 

// --- Fetch forum posts with Subqueries --- 
$sql = "SELECT f.*, 
                u.userName, 
                -- 1. Fetch tags
                (SELECT GROUP_CONCAT(DISTINCT t.tagName SEPARATOR ',') 
                 FROM forumtag ft 
                 JOIN tag t ON ft.tagID = t.tagID 
                 WHERE ft.forumID = f.forumID) AS tags, 
                -- 2. Calculate votes
                (SELECT COALESCE(SUM(voteValue), 0) 
                 FROM forumvote 
                 WHERE forumID = f.forumID) AS totalVotes, 
                -- 3. Comment count
                (SELECT COUNT(*) FROM comment c WHERE c.forumID = f.forumID) AS commentCount,
                -- 4. User vote check
                (SELECT voteValue FROM forumvote WHERE forumID = f.forumID AND userID = ?) AS userVote
        FROM forum f 
        JOIN user u ON f.userID = u.userID 
        WHERE 1=1"; 

if (!empty($search)) { 
    $s = $conn->real_escape_string($search); 
    $sql .= " AND (f.title LIKE '%$s%' OR f.content LIKE '%$s%')"; 
} 

if (!empty($tag)) { 
    $tagSafe = intval($tag); 
    $sql .= " AND f.forumID IN (SELECT forumID FROM forumtag WHERE tagID = $tagSafe)"; 
} 

$sql .= " ORDER BY f.createdDate DESC"; 

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
?> 

<!DOCTYPE html> 
<html lang="en"> 
<head> 
    <meta charset="UTF-8"> 
    <title>Community Forum</title> 
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style> 
        body {  font-family: 'Helvetica Neue', Arial, sans-serif; color: #333; margin: 0; display: flex; flex-direction: column; }
        .main-content { flex: 1; padding: 20px; max-width: 1200px; width: 100%; margin: auto; }
        .page-header { padding: 30px 20px; text-align: center; }
        .page-header h1 { margin: 0 0 8px; font-size: 30px; font-weight: 800; color: #222; }
        .page-header p { margin: 0; font-size: 15px; color: #666; }
        
        .search-bar { border-radius: 8px; padding: 20px 25px; margin-bottom: 30px; display: flex; justify-content: center; }
        .search-bar form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: center; }
        .search-bar input, .search-bar select, .search-bar button { padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
        .search-bar input[type="text"] { min-width: 250px; }
        .search-bar button { background: #007bff; color: white; border: none; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .create-forum-btn { padding: 8px 15px; background: #2ecc71; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }

        /* CHANGED: forum-post is now an <a> tag */
        .forum-post { 
            position: relative; 
            display: block; 
            background: #fff; 
            border-radius: 8px; 
            padding: 20px 25px; 
            margin-bottom: 15px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            border: 1px solid #eee; 
            cursor: pointer; 
            transition: transform 0.2s; 
            text-decoration: none; /* Remove underline from link */
            color: inherit; /* Keep text color same as body */
        } 
        .forum-post:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        .tag-list { position: absolute; top: 20px; right: 20px; display: flex; gap: 5px; flex-wrap: wrap; justify-content: flex-end; max-width: 35%; }
        .tag-label { background: #eef2f7; color: #5a67d8; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; border: 1px solid #d1d9e6; } 

        .forum-title { font-size: 22px; font-weight: 700; margin: 0 0 10px 0; color: #333; text-align: left; padding-right: 120px; } 
        .post-excerpt { color: #555; font-size: 15px; line-height: 1.5; margin-bottom: 15px; text-align: left; padding-right: 120px; }
        
        .forum-img-preview {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
            display: block;
            background-color: #f0f0f0;
        }

        .forum-meta { font-size: 13px; color: #7f8c8d; margin-bottom: 10px; text-align: left; } 
        .forum-stats-bar { display: flex; align-items: center; gap: 20px; margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee; }
        .vote-box { display: flex; align-items: center; gap: 8px; color: #333; font-weight: 700; }
        
        /* Ensure buttons don't inherit link behavior too much */
        .vote-btn { width: 30px; height: 30px; border-radius: 50%; border: 1px solid #ccc; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #333; }
        .vote-btn.upvote.active { background: #007bff; color: #fff; border-color: #007bff; } 
        .vote-btn.downvote.active { background: #dc3545; color: #fff; border-color: #dc3545; } 
        .comment-stats { display: flex; align-items: center; gap: 5px; font-size: 14px; font-weight: 500; color: #666; }
    </style> 
</head> 
<body> 
<?php include("user_navbar.php"); ?> 
<div class="main-content"> 
    <div class="page-header">
        <h1>💬 Community Discussion Forum</h1>
        <p>Share your thoughts, ask questions, and engage with other members.</p>
    </div>

    <div class="search-bar">
        <form method="GET">
            <input type="text" name="search" placeholder="Search title or content..." value="<?= htmlspecialchars($search ?? '') ?>">
            <select name="tag">
                <option value="">Filter: All Topics</option>
                <?php if($tagResult): ?>
                    <?php while ($tagRow = $tagResult->fetch_assoc()): ?>
                        <option value="<?= $tagRow['tagID'] ?>" <?= ($tag == $tagRow['tagID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tagRow['tagName'] ?? '') ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <?php if (isset($_SESSION['userID'])): ?> 
                <a href="create_forum.php" class="create-forum-btn">+ Create New Post</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="forum-container"> 
        <?php if ($result && $result->num_rows > 0): ?> 
            <?php while ($row = $result->fetch_assoc()): ?> 
                <a href="view_forum.php?id=<?= $row['forumID'] ?>" class="forum-post"> 
                    
                    <div class="tag-list"> 
                        <?php if (!empty($row['tags'])): ?> 
                            <?php foreach (explode(',', $row['tags']) as $tagName): ?> 
                                <span class="tag-label">#<?= htmlspecialchars($tagName ?? '') ?></span> 
                            <?php endforeach; ?> 
                        <?php endif; ?> 
                    </div> 

                    <div class="forum-content-area">
                        <h3 class="forum-title"><?= htmlspecialchars($row['title'] ?? '') ?></h3> 
                        
                        <?php 
                        $imgPath = $row['forumImage'] ?? '';
                        $content = $row['content'] ?? '';

                        if (!empty($imgPath)): ?>
                            <img src="<?= htmlspecialchars($imgPath) ?>" class="forum-img-preview" alt="Forum Image">
                        <?php elseif (!empty(trim($content))): ?>
                            <p class="post-excerpt"><?= nl2br(htmlspecialchars(substr($content, 0, 150))) ?>...</p> 
                        <?php endif; ?>

                        <div class="forum-meta"> 
                            Posted by <strong><?= htmlspecialchars($row['userName'] ?? '') ?></strong> on <?= date('d M Y', strtotime($row['createdDate'])) ?> 
                        </div> 
                    </div>

                    <div class="forum-stats-bar">
                        <div class="vote-box">
                            <button type="button" class="vote-btn upvote <?= ($row['userVote'] ?? 0) == 1 ? 'active' : '' ?>"
                                    onclick="castVote(event, <?= $row['forumID'] ?>, 1, this)">▲</button>
                            <span class="vote-count" id="vote-count-<?= $row['forumID'] ?>"><?= $row['totalVotes'] ?? 0 ?></span>
                            <button type="button" class="vote-btn downvote <?= ($row['userVote'] ?? 0) == -1 ? 'active' : '' ?>"
                                    onclick="castVote(event, <?= $row['forumID'] ?>, -1, this)">▼</button>
                        </div>
                        <span style="color: #eee;">|</span>
                        <div class="comment-stats">
                            <i class="fas fa-comment"></i> <?= $row['commentCount'] ?? 0 ?> comments 
                        </div>
                    </div>
                </a>
            <?php endwhile; ?> 
        <?php else: ?> 
            <p style="text-align: center; color: #7f8c8d; margin-top: 40px;">No forum posts found matching your criteria.</p> 
        <?php endif; ?> 
    </div>
</div> 

<?php include 'includes/footer.php'; ?> 

<script>
function castVote(event, forumID, value, btn) {
    // CRITICAL: prevents the <a> tag from navigating to view_forum.php when clicking vote
    event.preventDefault();
    event.stopPropagation();
    
    const card = btn.closest('.forum-post');
    const countEl = document.getElementById('vote-count-' + forumID);
    
    fetch("vote_forum.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ forumID: forumID, voteValue: value })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            countEl.innerText = data.totalVotes;
            const upBtn = card.querySelector('.vote-btn.upvote');
            const downBtn = card.querySelector('.vote-btn.downvote');
            if (value === 1) {
                upBtn.classList.toggle('active');
                downBtn.classList.remove('active');
            } else {
                downBtn.classList.toggle('active');
                upBtn.classList.remove('active');
            }
        } else {
            alert(data.message || "Failed to vote.");
        }
    })
    .catch(err => console.error("Error:", err));
}
</script>
</body> 
</html> 
<?php $conn->close(); ?>