<?php
session_start();
include 'db.php';

$userID = $_SESSION['userID'] ?? null;
if (!$userID) {
    header('Location: login.php');
    exit;
}

// Helper function to calculate days remaining
function daysRemaining($endDate) {
    $now = new DateTime('today');
    $end = new DateTime($endDate);
    if ($now > $end) {
        return "Expired";
    }
    $interval = $now->diff($end);
    $days = $interval->days;
    
    if ($days === 0) return "Today";
    if ($days === 1) return "Tomorrow";
    if ($days < 30) return "$days days left";
    return round($days / 30) . " months left";
}

/* ==========================
    FETCH EVENT WISHLIST
========================== */
$sqlEvent = "SELECT 
            w.wishlistID, w.note, w.createdAt,
            e.eventID, e.eventName,
            e.coverImage AS eventImage,
            e.description AS eventDescription,
            cat1.categoryName AS eventCategory,
            e.eventLocation, e.eventCountry,
            e.startDate, e.endDate
        FROM wishlist w
        JOIN event e ON w.eventID = e.eventID
        LEFT JOIN category cat1 ON e.categoryID = cat1.categoryID
        WHERE w.userID = ?
        ORDER BY (e.endDate < CURDATE()) ASC, e.endDate ASC"; 
$stmtEvent = $conn->prepare($sqlEvent);
$stmtEvent->bind_param("i", $userID);
$stmtEvent->execute();
$resultEvent = $stmtEvent->get_result();

/* ==========================
    FETCH COURSE WISHLIST
========================== */
$sqlCourse = "SELECT 
            w.wishlistID, w.note, w.createdAt,
            c.courseID, c.courseName,
            c.coverImage AS courseImage,
            c.description AS courseDescription,
            cat2.categoryName AS courseCategory,
            c.startDate, c.endDate,
            c.courseLocation,
            c.courseCountry
        FROM wishlist w
        JOIN course c ON w.courseID = c.courseID
        LEFT JOIN category cat2 ON c.categoryID = cat2.categoryID
        WHERE w.userID = ?
        ORDER BY (c.endDate < CURDATE()) ASC, c.endDate ASC";
$stmtCourse = $conn->prepare($sqlCourse);
$stmtCourse->bind_param("i", $userID);
$stmtCourse->execute();
$resultCourse = $stmtCourse->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Wishlist</title>
<link rel="stylesheet" href="style.css">
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f5f7fb; margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
.container { max-width: 1250px; margin: auto; padding: 20px; flex: 1; }
h2 { margin-bottom: 25px; text-align: center; color: #222; font-size: 28px; }

.tabs { display: flex; justify-content: center; margin-bottom: 30px; }
.tabs button { padding: 14px 28px; border: none; cursor: pointer; background: #e9ecef; margin: 0 8px; border-radius: 10px; font-size: 16px; transition: all 0.3s; }
.tabs button.active { background: #007bff; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }

.tabContent { display: none; }
.tabContent.active { display: block; }

.card-wrapper { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; justify-items: center; }

.wishlist-item {
    background: #fff; border-radius: 16px; box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    margin-bottom: 30px; overflow: hidden; display: flex; flex-direction: column;
    transition: transform 0.2s ease-in-out; position: relative; width: 360px;
}
.wishlist-item:hover { transform: translateY(-6px); }

/* Expired Visuals */
.wishlist-item.expired { opacity: 0.75; filter: grayscale(0.2); }

.wishlist-header { width: 100%; height: 220px; background-size: cover; background-position: center; position: relative; }
.wishlist-body { padding: 20px; display: flex; flex-direction: column; gap: 10px; }
.wishlist-body .title-link { text-decoration: none; color: inherit; }
.wishlist-body h3 { margin: 0; font-size: 20px; color: #222; transition: color 0.2s; }
.wishlist-body .title-link:hover h3 { color: #007bff; }
.wishlist-body p { margin: 0; color: #555; font-size: 14px; line-height: 1.5; }

.category-tag { align-self: flex-start; background: #eee; color: #333; font-size: 12px; font-weight: 600; padding: 4px 8px; border-radius: 6px; margin-bottom: 5px; }

/* Time Labels */
.time-label { font-weight: 700; color: #e74c3c; background: #fdf3f2; padding: 4px 8px; border-radius: 4px; font-size: 13px; align-self: flex-end; }
.expired .time-label { color: #7f8c8d; background: #ecf0f1; text-transform: uppercase; letter-spacing: 0.5px; }

.note-box { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
.textarea-wrapper { position: relative; width: 100%; }
.textarea-wrapper textarea { box-sizing: border-box; width: 100%; height: 80px; padding: 10px 45px 10px 10px; border-radius: 6px; border: 1px solid #ccc; resize: none; font-size: 14px; }
.save-btn { position: absolute; bottom: 8px; right: 8px; background: #007b00; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; justify-content: center; align-items: center; }

.save-tooltip { position: absolute; bottom: 45px; right: 5px; background: #2ecc71; color: white; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; opacity: 0; transform: translateY(10px); transition: all 0.4s; pointer-events: none; }
.save-tooltip.visible { opacity: 1; transform: translateY(0); }

.note-actions { display: flex; justify-content: flex-end; align-items: center; }
.action-button { padding: 8px 14px; border-radius: 6px; font-size: 14px; font-weight: 600; color: #fff; background: #007bff; text-decoration: none; border: none; cursor: pointer; }

.wishlist-btn { position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,0.45); border: none; border-radius: 50%; width: 42px; height: 42px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.wishlist-btn svg { width: 22px; height: 22px; stroke: #fff; stroke-width: 2; fill: #fff; }

.empty-msg { text-align: center; color: #777; margin-top: 40px; }
</style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="container">
    <h2>📝 My Wishlist</h2>

    <div class="tabs">
        <button class="tabBtn active" onclick="showTab('eventTab', this)">Events</button>
        <button class="tabBtn" onclick="showTab('courseTab', this)">Courses</button>
    </div>

    <div id="eventTab" class="tabContent active">
        <?php if ($resultEvent->num_rows > 0): ?>
            <div class="card-wrapper">
                <?php while ($row = $resultEvent->fetch_assoc()): ?>
                    <?php
                        $eventImage = !empty($row['eventImage']) ? 'uploads/event_cover/' . $row['eventImage'] : 'https://via.placeholder.com/360x220/007bff/ffffff?text=Event+Cover';
                        $timeRemaining = daysRemaining($row['endDate']);
                        $isExpired = ($timeRemaining === "Expired");
                        $dateDisplay = ($row['startDate'] === $row['endDate']) ? date('d M Y', strtotime($row['startDate'])) : date('d M Y', strtotime($row['startDate'])) . " - " . date('d M Y', strtotime($row['endDate']));
                    ?>
                    <div class="wishlist-item <?= $isExpired ? 'expired' : '' ?>">
                        <div class="wishlist-header" style="background-image:url('<?= htmlspecialchars($eventImage) ?>');">
                            <button class="wishlist-btn" onclick="removeWishlist(<?= $row['eventID'] ?>,'event',this)">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M2.5 1.5h11a.5.5 0 0 1 .5.5v12.8a.3.3 0 0 1-.45.26L8 12.5l-5.55 2.56a.3.3 0 0 1-.45-.26V2a.5.5 0 0 1 .5-.5z"/></svg>
                            </button>
                        </div>
                        <div class="wishlist-body">
                            <span class="category-tag">#<?= htmlspecialchars($row['eventCategory'] ?? 'Uncategorized') ?></span>
                            <a href="event_detail.php?id=<?= $row['eventID'] ?>" class="title-link">
                                <h3><?= htmlspecialchars($row['eventName']) ?></h3>
                            </a>
                            <span class="time-label"><?= $isExpired ? 'Expired' : '⏳ ' . $timeRemaining ?></span>
                            <p>📅 <?= $dateDisplay ?></p>
                            <p>📍 <?= htmlspecialchars($row['eventLocation']) ?>, <?= htmlspecialchars($row['eventCountry']) ?></p>
                            <div class="note-box">
                                <div class="textarea-wrapper">
                                    <textarea id="note-<?= $row['wishlistID'] ?>" placeholder="Add a note..."><?= htmlspecialchars($row['note']) ?></textarea>
                                    <span class="save-tooltip" id="tooltip-<?= $row['wishlistID'] ?>">✓ Saved!</span>
                                    <button class="save-btn" data-wishlist-id="<?= $row['wishlistID'] ?>" onclick="saveNote(this)">💾</button> 
                                </div>
                                <div class="note-actions">
                                    <button onclick="window.location.href='event_detail.php?id=<?= $row['eventID'] ?>'" class="action-button">View Details</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-msg"><p>✨ Your event wishlist is empty!</p></div>
        <?php endif; ?>
    </div>

    <div id="courseTab" class="tabContent">
        <?php if ($resultCourse->num_rows > 0): ?>
            <div class="card-wrapper">
                <?php while ($row = $resultCourse->fetch_assoc()): ?>
                    <?php
                        $courseImage = !empty($row['courseImage']) ? 'uploads/course_cover/' . $row['courseImage'] : 'https://via.placeholder.com/360x220/2575fc/ffffff?text=Course+Cover';
                        $timeRemaining = daysRemaining($row['endDate']);
                        $isExpired = ($timeRemaining === "Expired");
                        $dateDisplay = ($row['startDate'] === $row['endDate']) ? date('d M Y', strtotime($row['startDate'])) : date('d M Y', strtotime($row['startDate'])) . " - " . date('d M Y', strtotime($row['endDate']));
                    ?>
                    <div class="wishlist-item <?= $isExpired ? 'expired' : '' ?>">
                        <div class="wishlist-header" style="background-image:url('<?= htmlspecialchars($courseImage) ?>');">
                            <button class="wishlist-btn" onclick="removeWishlist(<?= $row['courseID'] ?>,'course',this)">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M2.5 1.5h11a.5.5 0 0 1 .5.5v12.8a.3.3 0 0 1-.45.26L8 12.5l-5.55 2.56a.3.3 0 0 1-.45-.26V2a.5.5 0 0 1 .5-.5z"/></svg>
                            </button>
                        </div>
                        <div class="wishlist-body">
                            <span class="category-tag">#<?= htmlspecialchars($row['courseCategory'] ?? 'Uncategorized') ?></span>
                            <a href="course_detail.php?id=<?= $row['courseID'] ?>" class="title-link">
                                <h3><?= htmlspecialchars($row['courseName']) ?></h3>
                            </a>
                            <span class="time-label"><?= $isExpired ? 'Expired' : '⏳ ' . $timeRemaining ?></span>
                            <p>📅 <?= $dateDisplay ?></p>
                            <p>📍 <?= htmlspecialchars($row['courseLocation']) ?>, <?= htmlspecialchars($row['courseCountry'] ?? '') ?></p>
                            <div class="note-box">
                                <div class="textarea-wrapper">
                                    <textarea id="note-<?= $row['wishlistID'] ?>" placeholder="Add a note..."><?= htmlspecialchars($row['note']) ?></textarea>
                                    <span class="save-tooltip" id="tooltip-<?= $row['wishlistID'] ?>">✓ Saved!</span>
                                    <button class="save-btn" data-wishlist-id="<?= $row['wishlistID'] ?>" onclick="saveNote(this)">💾</button> 
                                </div>
                                <div class="note-actions">
                                    <button onclick="window.location.href='course_detail.php?id=<?= $row['courseID'] ?>'" class="action-button">View Details</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-msg"><p>✨ Your course wishlist is empty!</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tabContent').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tabBtn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}

function saveNote(btn) {
    const wishlistID = btn.getAttribute('data-wishlist-id');
    const note = document.getElementById(`note-${wishlistID}`).value;
    const tooltip = document.getElementById(`tooltip-${wishlistID}`);
    if (btn.classList.contains('saving')) return;
    btn.classList.add('saving');
    fetch('wishlist_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ wishlistID, note })
    })
    .then(r => r.json())
    .then(d => {
        btn.classList.remove('saving');
        if (d.status === 'saved') {
            tooltip.classList.add('visible');
            setTimeout(() => { tooltip.classList.remove('visible'); }, 2000);
        }
    }).catch(console.error);
}

function removeWishlist(id, type, btn) {
    if (!confirm('Remove this item from your wishlist?')) return;
    const idFieldName = (type === 'event') ? 'eventID' : 'courseID';
    fetch('wishlist_toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ [idFieldName]: id, type: type, action: 'remove' })
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'removed') {
            btn.closest('.wishlist-item').remove();
        }
    }).catch(console.error);
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>