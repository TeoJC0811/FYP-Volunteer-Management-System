<?php
session_start();
include("db.php");

// 🔒 Require login
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: community_forum.php");
    exit;
}

$forumID = intval($_GET['id']);
$userID = $_SESSION['userID'];

// Handle post deletion (only owner can delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_forum'])) {
    $del = $conn->prepare("DELETE FROM forum WHERE forumID = ? AND userID = ?");
    $del->bind_param("ii", $forumID, $userID);
    $del->execute();
    header("Location: community_forum.php");
    exit;
}

// Handle forum post edit (only owner)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_forum'])) {
    $newContent = trim($_POST['forum_content']);
    if (!empty($newContent)) {
        $stmt = $conn->prepare("UPDATE forum SET content = ? WHERE forumID = ? AND userID = ?");
        $stmt->bind_param("sii", $newContent, $forumID, $userID);
        $stmt->execute();
        header("Location: view_forum.php?id=$forumID");
        exit;
    }
}

// Handle comment deletion (only owner can delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $commentID = intval($_POST['commentID']);
    $delC = $conn->prepare("DELETE FROM comment WHERE commentID = ? AND userID = ?");
    $delC->bind_param("ii", $commentID, $userID);
    $delC->execute();
    header("Location: view_forum.php?id=$forumID");
    exit;
}

// Fetch forum post - includes forumImage
$sql = "
    SELECT f.forumID, f.title, f.content, f.forumImage, f.createdDate, f.userID, u.userName
    FROM forum f
    JOIN user u ON f.userID = u.userID
    WHERE f.forumID = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $forumID);
$stmt->execute();
$forum = $stmt->get_result()->fetch_assoc();

if (!$forum) {
    echo "Post not found.";
    exit;
}

// Fetch tags
$tagResult = $conn->prepare("
    SELECT t.tagName
    FROM forumtag ft
    JOIN tag t ON ft.tagID = t.tagID
    WHERE ft.forumID = ?
");
$tagResult->bind_param("i", $forumID);
$tagResult->execute();
$tags = $tagResult->get_result();

// Fetch comments
$commentResult = $conn->prepare("
    SELECT c.commentID, c.comment, c.createdDate, c.updatedAt, u.userName, c.userID
    FROM comment c
    JOIN user u ON c.userID = u.userID
    WHERE c.forumID = ?
    ORDER BY c.createdDate ASC
");
$commentResult->bind_param("i", $forumID);
$commentResult->execute();
$comments = $commentResult->get_result();
$commentCount = $comments->num_rows;

// Fetch votes
$voteResult = $conn->prepare("
    SELECT COALESCE(SUM(voteValue),0) as totalVotes
    FROM forumvote
    WHERE forumID = ?
");
$voteResult->bind_param("i", $forumID);
$voteResult->execute();
$totalVotes = $voteResult->get_result()->fetch_assoc()['totalVotes'];

// Check user’s vote
$userVote = 0;
$checkVote = $conn->prepare("SELECT voteValue FROM forumvote WHERE forumID=? AND userID=?");
$checkVote->bind_param("ii", $forumID, $userID);
$checkVote->execute();
$resultVote = $checkVote->get_result();
if ($resultVote->num_rows > 0) {
    $userVote = $resultVote->fetch_assoc()['voteValue'];
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        $stmt = $conn->prepare("INSERT INTO comment (comment, userID, forumID) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $comment, $userID, $forumID);
        $stmt->execute();
        header("Location: view_forum.php?id=$forumID");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($forum['title'] ?? '') ?></title>
<link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
<style>
html, body { height: 100%; margin: 0; }
body { display: flex; flex-direction: column; min-height: 100vh; background-color: #f5f7f9; }
main { flex: 1; padding: 40px 20px; }

.content-wrapper {
    max-width: 800px; 
    margin: 0 auto; 
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.container-box { 
    width: 100%;
    padding: 30px; 
    border: 1px solid #ddd;
    border-radius: 10px; 
    background: #fff; 
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    box-sizing: border-box;
}

.back-btn { 
    text-decoration: none; 
    color: white; 
    background-color: #555; 
    padding: 8px 15px; 
    border-radius: 5px; 
    font-weight: bold; 
    width: fit-content; 
    display: flex;
    align-items: center;
}
.back-btn:hover { background-color: #333; }

.forum-header { display: flex; justify-content: space-between; align-items: center; }
.forum-title { font-size: 24px; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; font-weight: bold;}
.forum-meta { font-size: 13px; color: #666; margin-bottom: 10px; }
.tag-container { margin-bottom: 15px; }
.tag { display: inline-block; background: #f0f0f0; padding: 4px 10px; border-radius: 15px; margin-right: 5px; font-size: 12px; color: #555; border: 1px solid #e0e0e0; }

.forum-image-display { width: 100%; max-height: 500px; object-fit: contain; border-radius: 8px; margin-bottom: 20px; background-color: #fafafa; border: 1px solid #eee; }
.forum-content { white-space: pre-line; margin-bottom: 20px; }

.three-dots, .three-dots-horizontal { background: none; border: none; font-size: 20px; cursor: pointer; padding: 5px; }
.dropdown { position: relative; display: inline-block; }
.dropdown-content { display: none; position: absolute; right: 0; background: #fff; border: 1px solid #ccc; border-radius: 5px; min-width: 100px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 10; }
.dropdown-content button { width: 100%; padding: 8px 10px; border: none; background: none; text-align: left; cursor: pointer; }
.dropdown-content button:hover { background: #f5f5f5; color: red; }

.edit-box { margin: 15px 0; width: 100%; }
.edit-box textarea { width: 100%; min-height: 100px; padding: 12px; border: 1px solid #007bff; border-radius: 6px; resize: vertical; box-sizing: border-box; font-family: inherit; font-size: 14px; line-height: 1.5; background-color: #fcfdff; }
.edit-actions { margin-top: 8px; display: flex; justify-content: flex-end; gap: 8px; }
.edit-actions button { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
.save-edit { background: #28a745; color: #fff; }
.cancel-edit { background: #6c757d; color: #fff; }

.vote-box { display: inline-flex; align-items: center; gap: 6px; user-select: none; margin-bottom: 20px; }
.vote-btn { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; border: 1px solid transparent; background: transparent; cursor: pointer; font-size: 16px; color: #222; transition: background 0.12s, color 0.12s; }
.vote-btn.upvote.active { background: #007bff; color: #fff; }
.vote-btn.downvote.active { background: #dc3545; color: #fff; }
.vote-count { width: 35px; text-align: center; font-weight: bold; font-size: 15px; }

.comments { margin-top: 30px; }
.comment { border-bottom: 1px solid #ddd; padding: 10px 0; position: relative; }
.comment small { color: #777; }
.comment-form form { display: flex; flex-direction: column; gap: 8px; }
.comment-form textarea { width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; resize: none; box-sizing: border-box; }
.comment-form button { align-self: flex-end; padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }

.comment-text-container { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 5; transition: all 0.3s ease; }
.comment-text-container.expanded { display: block; max-height: none; }
.read-more-btn { color: #000; background: none; border: none; cursor: pointer; font-size: 13px; padding: 5px 0; font-weight: bold; display: none; }
</style>
</head>
<body>
<?php include("user_navbar.php"); ?>

<main>
    <div class="content-wrapper">
        <a href="community_forum.php" class="back-btn">← Back</a>

        <div class="container-box">
            <div class="forum-header">
                <h2 class="forum-title"><?= htmlspecialchars($forum['title'] ?? '') ?></h2>
                <?php if ($userID == $forum['userID']): ?>
                    <div class="dropdown">
                        <button class="three-dots">⋮</button>
                        <div class="dropdown-content">
                            <button onclick="enableForumEdit()">Edit</button>
                            <form method="POST" onsubmit="return confirm('Delete this post?');">
                                <input type="hidden" name="delete_forum" value="1">
                                <button type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="forum-meta">
                Posted by <?= htmlspecialchars($forum['userName'] ?? '') ?> on <?= $forum['createdDate'] ?>
            </div>

            <div class="tag-container">
                <?php $tags->data_seek(0); while ($tag = $tags->fetch_assoc()): ?>
                    <span class="tag">#<?= htmlspecialchars($tag['tagName'] ?? '') ?></span>
                <?php endwhile; ?>
            </div>

            <?php if (!empty($forum['forumImage'])): ?>
                <img src="<?= htmlspecialchars($forum['forumImage']) ?>" class="forum-image-display" alt="Forum Image">
            <?php endif; ?>

            <div class="forum-content" id="forum-content"><?= nl2br(htmlspecialchars($forum['content'] ?? '')) ?></div>

            <div class="vote-box">
                <button class="vote-btn upvote <?= $userVote == 1 ? 'active' : '' ?>"
                        onclick="castVote(<?= $forumID ?>, 1, this)">▲</button>
                <span class="vote-count" id="vote-count-<?= $forumID ?>"><?= (int)$totalVotes ?></span>
                <button class="vote-btn downvote <?= $userVote == -1 ? 'active' : '' ?>"
                        onclick="castVote(<?= $forumID ?>, -1, this)">▼</button>
            </div>

            <div class="comments">
                <div class="comment-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3>💬 Comments</h3>
                    <span style="background:#eee; padding:3px 8px; border-radius:12px; font-size:12px;"><?= $commentCount ?> comments</span>
                </div>

                <div class="comment-form" style="margin-top:10px;">
                    <form method="POST">
                        <textarea name="comment" placeholder="Write your comment..." rows="5" required></textarea>
                        <button type="submit">Post Comment</button>
                    </form>
                </div>

                <?php if ($commentCount > 0): ?>
                    <?php while ($c = $comments->fetch_assoc()): ?>
                        <div class="comment" id="comment-<?= $c['commentID'] ?>">
                            <div class="comment-text-container" id="comment-container-<?= $c['commentID'] ?>">
                                <p class="comment-text"><?= nl2br(htmlspecialchars($c['comment'] ?? '')) ?></p>
                            </div>
                            <button class="read-more-btn" id="read-more-<?= $c['commentID'] ?>" onclick="toggleComment(<?= $c['commentID'] ?>)">Read More</button>
                            
                            <div class="comment-meta-footer">
                                <small>
                                    — <?= htmlspecialchars($c['userName'] ?? '') ?>, <?= $c['createdDate'] ?>
                                </small>
                            </div>

                            <?php if ($userID == $c['userID']): ?>
                                <div class="comment-dropdown" style="position: absolute; top: 10px; right: 0;">
                                    <button class="three-dots-horizontal">⋯</button>
                                    <div class="dropdown-content">
                                        <button onclick="enableEdit(<?= $c['commentID'] ?>)">Edit</button>
                                        <form method="POST" onsubmit="return confirm('Delete this comment?');">
                                            <input type="hidden" name="commentID" value="<?= $c['commentID'] ?>">
                                            <button type="submit" name="delete_comment">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No comments yet. Be the first to reply!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function initReadMore() {
    document.querySelectorAll('.comment-text-container').forEach(container => {
        const commentId = container.id.replace('comment-container-', '');
        const btn = document.getElementById('read-more-' + commentId);
        const textHeight = container.querySelector('.comment-text').scrollHeight;
        const containerHeight = container.clientHeight;
        if (textHeight > containerHeight) btn.style.display = 'block';
        else btn.style.display = 'none';
    });
}

function toggleComment(id) {
    const container = document.getElementById('comment-container-' + id);
    const btn = document.getElementById('read-more-' + id);
    if (container.classList.contains('expanded')) {
        container.classList.remove('expanded');
        btn.innerText = 'Read More';
    } else {
        container.classList.add('expanded');
        btn.innerText = 'Show Less';
    }
}

window.addEventListener('load', initReadMore);

function castVote(forumID, value, btn) {
    const voteBox = btn.closest('.vote-box');
    const upBtn = voteBox.querySelector('.upvote');
    const downBtn = voteBox.querySelector('.downvote');
    const countEl = document.getElementById('vote-count-' + forumID);
    upBtn.disabled = true; downBtn.disabled = true;
    fetch("vote_forum.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ forumID: forumID, voteValue: value })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            countEl.innerText = data.totalVotes;
            if (value === 1) {
                upBtn.classList.toggle('active', !(upBtn.classList.contains('active')));
                downBtn.classList.remove('active');
            } else {
                downBtn.classList.toggle('active', !(downBtn.classList.contains('active')));
                upBtn.classList.remove('active');
            }
        } else alert(data.message || "Failed to vote.");
    })
    .catch(err => { console.error(err); alert("Error sending vote."); })
    .finally(() => { upBtn.disabled = false; downBtn.disabled = false; });
}

document.addEventListener('click', e => {
    if (e.target.matches('.three-dots, .three-dots-horizontal')) {
        e.stopPropagation();
        const menu = e.target.nextElementSibling;
        document.querySelectorAll('.dropdown-content').forEach(dc => {
            if (dc !== menu) dc.style.display = 'none';
        });
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    } else {
        document.querySelectorAll('.dropdown-content').forEach(dc => dc.style.display = 'none');
    }
});

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

function enableEdit(commentID) {
    const commentDiv = document.getElementById("comment-" + commentID);
    const textEl = commentDiv.querySelector(".comment-text");
    const containerEl = commentDiv.querySelector(".comment-text-container");
    const footer = commentDiv.querySelector(".comment-meta-footer");
    
    const currentText = textEl.innerText.trim();
    
    containerEl.style.display = "none";
    footer.style.display = "none";
    
    const editBox = document.createElement("div");
    editBox.classList.add("edit-box");
    editBox.innerHTML = `
        <textarea id="edit-input-${commentID}" oninput="autoResize(this)">${currentText}</textarea>
        <div class="edit-actions">
            <button class="save-edit" onclick="saveEdit(${commentID})">Save</button>
            <button class="cancel-edit" onclick="cancelEdit(${commentID})">Cancel</button>
        </div>`;
    
    commentDiv.insertBefore(editBox, footer);
    autoResize(document.getElementById("edit-input-" + commentID));
}

function cancelEdit(commentID) {
    const commentDiv = document.getElementById("comment-" + commentID);
    const eb = commentDiv.querySelector(".edit-box");
    if (eb) eb.remove();
    
    const container = commentDiv.querySelector(".comment-text-container");
    container.style.display = "-webkit-box"; // Restore CSS property
    
    commentDiv.querySelector(".comment-meta-footer").style.display = "block";
    initReadMore(); 
}

function saveEdit(commentID) {
    const inputEl = document.getElementById("edit-input-" + commentID);
    const newText = inputEl.value.trim();
    
    if (newText === "") {
        alert("Comment cannot be empty");
        return;
    }

    fetch("update_comment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ commentID: commentID, comment: newText })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update the text in the UI
            const textElement = document.getElementById("comment-" + commentID).querySelector(".comment-text");
            textElement.innerText = newText;
            cancelEdit(commentID);
        } else {
            alert(data.message || "Failed to update comment.");
        }
    })
    .catch(err => {
        console.error("Fetch error:", err);
        alert("An error occurred.");
    });
}

function enableForumEdit() {
    const forumContent = document.getElementById("forum-content");
    const currentText = forumContent.innerText.trim();
    forumContent.style.display = "none";
    const editBox = document.createElement("div");
    editBox.classList.add("edit-box");
    editBox.innerHTML = `
        <form method="POST">
            <textarea name="forum_content" oninput="autoResize(this)">${currentText}</textarea>
            <div class="edit-actions">
                <button type="submit" name="edit_forum" class="save-edit">Save</button>
                <button type="button" class="cancel-edit" onclick="cancelForumEdit()">Cancel</button>
            </div>
        </form>`;
    forumContent.parentNode.insertBefore(editBox, forumContent.nextSibling);
    autoResize(editBox.querySelector('textarea'));
}

function cancelForumEdit() {
    document.getElementById("forum-content").style.display = "block";
    const eb = document.querySelector(".container-box > .edit-box");
    if (eb) eb.remove();
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>