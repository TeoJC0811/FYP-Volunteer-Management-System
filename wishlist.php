<?php
session_start();
include 'db.php';

$userID = $_SESSION['userID'] ?? null;
if (!$userID) {
    header('Location: login.php');
    exit;
}

/**
 * Helper function to calculate time remaining with human-friendly text
 */
function daysRemaining($endDate) {
    $now = new DateTime('today');
    $end = new DateTime($endDate);
    
    if ($now > $end) {
        return "Expired";
    }
    
    $interval = $now->diff($end);
    $days = $interval->days;
    
    if ($days === 0) return "Happening Today";
    if ($days === 1) return "Happening Tomorrow";
    
    // Within 1 Week
    if ($days <= 7) return "Within this week ($days days left)";
    
    // Within 1 Month
    if ($days <= 30) return "Within the next month";
    
    // Multi-month logic
    $months = round($days / 30);
    if ($months <= 1) return "About 1 month left";
    if ($months <= 3) return "Within the next 3 months";
    if ($months <= 6) return "Within the next 6 months";
    
    return "Over 6 months left";
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #f5f7fb; margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
    .container { max-width: 1250px; margin: auto; padding: 20px; flex: 1; }
    h2 { margin-bottom: 25px; text-align: center; color: #222; font-size: 28px; font-weight: 700; }

    .tabs { display: flex; justify-content: center; margin-bottom: 30px; }
    .tabs button { padding: 14px 28px; border: none; cursor: pointer; background: #e9ecef; margin: 0 8px; border-radius: 10px; font-size: 16px; transition: all 0.3s; font-weight: 600; }
    .tabs button.active { background: #007bff; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }

    .tabContent { display: none; }
    .tabContent.active { display: block; }

    .card-wrapper { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 25px; justify-items: center; }

    .wishlist-item {
        background: #fff; border-radius: 16px; box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        margin-bottom: 30px; overflow: hidden; display: flex; flex-direction: column;
        transition: transform 0.2s ease-in-out; position: relative; width: 100%; max-width: 380px;
    }
    .wishlist-item:hover { transform: translateY(-6px); }

    .wishlist-item.expired { opacity: 0.75; filter: grayscale(0.2); }

    .wishlist-header { width: 100%; height: 220px; background-size: cover; background-position: center; position: relative; }
    .wishlist-body { padding: 20px; display: flex; flex-direction: column; gap: 10px; flex-grow: 1; }
    .wishlist-body .title-link { text-decoration: none; color: inherit; }
    .wishlist-body h3 { margin: 0; font-size: 20px; color: #222; font-weight: 700; line-height: 1.3; }
    .wishlist-body .title-link:hover h3 { color: #007bff; }
    .wishlist-body p { margin: 0; color: #555; font-size: 14px; line-height: 1.5; }

    .category-tag { align-self: flex-start; background: #e7f1ff; color: #007bff; font-size: 12px; font-weight: 700; padding: 5px 10px; border-radius: 6px; text-transform: uppercase; }

    .time-label { font-weight: 700; color: #e74c3c; background: #fdf3f2; padding: 6px 12px; border-radius: 6px; font-size: 13px; display: inline-block; width: fit-content; }
    .expired .time-label { color: #7f8c8d; background: #ecf0f1; text-transform: uppercase; }

    .note-box { margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px; }
    .textarea-wrapper { position: relative; width: 100%; }
    .textarea-wrapper textarea { 
        box-sizing: border-box; width: 100%; height: 80px; padding: 12px; 
        border-radius: 8px; border: 1px solid #dee2e6; resize: none; font-size: 14px; 
        background: #fcfcfc; transition: border 0.2s;
    }
    .textarea-wrapper textarea:focus { border-color: #007bff; outline: none; background: #fff; }
    
    .save-btn { 
        position: absolute; bottom: 8px; right: 8px; background: #28a745; 
        color: white; border: none; border-radius: 50%; width: 34px; height: 34px; 
        cursor: pointer; display: flex; justify-content: center; align-items: center;
        transition: transform 0.2s;
    }
    .save-btn:hover { transform: scale(1.1); background: #218838; }

    .save-tooltip { 
        position: absolute; bottom: 50px; right: 5px; background: #2ecc71; 
        color: white; padding: 6px 14px; border-radius: 20px; font-size: 12px; 
        font-weight: 700; opacity: 0; transform: translateY(10px); transition: all 0.3s; pointer-events: none; 
    }
    .save-tooltip.visible { opacity: 1; transform: translateY(0); }

    .note-actions { display: flex; justify-content: center; margin-top: 10px; }
    .action-button { 
        width: 100%; padding: 10px; border-radius: 8px; font-size: 14px; 
        font-weight: 600; color: #fff; background: #007bff; text-decoration: none; 
        border: none; cursor: pointer; text-align: center;
    }
    .action-button:hover { background: #0056b3; }

    .wishlist-btn { 
        position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,0.5); 
        border: none; border-radius: 50%; width: 40px; height: 40px; 
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: background 0.2s;
    }
    .wishlist-btn:hover { background: rgba(231, 76, 60, 0.9); }
    .wishlist-btn svg { width: 20px; height: 20px; stroke: #fff; stroke-width: 2; fill: #fff; }

    .empty-msg { text-align: center; color: #777; margin-top: 60px; font-size: 18px; }
</style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="container">
    <h2>📝 My Wishlist</h2>

    <div class="tabs">
        <button class="tabBtn active" onclick="showTab('eventTab', this)">Volunteer Events</button>
        <button class="tabBtn" onclick="showTab('courseTab', this)">Training Courses</button>
    </div>

    <div id="eventTab" class="tabContent active">
        <?php if ($resultEvent->num_rows > 0): ?>
            <div class="card-wrapper">
                <?php while ($row = $resultEvent->fetch_assoc()): ?>
                    <?php
                        $eventImage = !empty($row['eventImage']) ? $row['eventImage'] : 'https://via.placeholder.com/360x220/007bff/ffffff?text=Event+Cover';
                        $timeStatus = daysRemaining($row['endDate']);
                        $isExpired = ($timeStatus === "Expired");
                        $dateDisplay = ($row['startDate'] === $row['endDate']) ? date('d M Y', strtotime($row['startDate'])) : date('d M Y', strtotime($row['startDate'])) . " - " . date('d M Y', strtotime($row['endDate']));
                    ?>
                    <div class="wishlist-item <?= $isExpired ? 'expired' : '' ?>">
                        <div class="wishlist-header" style="background-image:url('<?= htmlspecialchars($eventImage) ?>');">
                            <button class="wishlist-btn" title="Remove from Wishlist" onclick="removeWishlist(<?= $row['eventID'] ?>,'event',this)">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M2.5 1.5h11a.5.5 0 0 1 .5.5v12.8a.3.3 0 0 1-.45.26L8 12.5l-5.55 2.56a.3.3 0 0 1-.45-.26V2a.5.5 0 0 1 .5-.5z"/></svg>
                            </button>
                        </div>
                        <div class="wishlist-body">
                            <span class="category-tag"><?= htmlspecialchars($row['eventCategory'] ?? 'General') ?></span>
                            <a href="event_detail.php?id=<?= $row['eventID'] ?>" class="title-link">
                                <h3><?= htmlspecialchars($row['eventName']) ?></h3>
                            </a>
                            <div class="time-label"><?= $isExpired ? 'Status: Expired' : '⏳ ' . $timeStatus ?></div>
                            <p><i class="fa-regular fa-calendar"></i> <?= $dateDisplay ?></p>
                            <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($row['eventLocation']) ?>, <?= htmlspecialchars($row['eventCountry']) ?></p>
                            
                            <div class="note-box">
                                <div class="textarea-wrapper">
                                    <textarea id="note-<?= $row['wishlistID'] ?>" placeholder="Personal notes (e.g. Bring a water bottle)"><?= htmlspecialchars($row['note']) ?></textarea>
                                    <span class="save-tooltip" id="tooltip-<?= $row['wishlistID'] ?>">✓ Note Saved!</span>
                                    <button class="save-btn" title="Save Note" data-wishlist-id="<?= $row['wishlistID'] ?>" onclick="saveNote(this)">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button> 
                                </div>
                                <div class="note-actions">
                                    <a href="event_detail.php?id=<?= $row['eventID'] ?>" class="action-button">View Full Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-msg"><p>No saved events yet. Start exploring!</p></div>
        <?php endif; ?>
    </div>

    <div id="courseTab" class="tabContent">
        <?php if ($resultCourse->num_rows > 0): ?>
            <div class="card-wrapper">
                <?php while ($row = $resultCourse->fetch_assoc()): ?>
                    <?php
                        $courseImage = !empty($row['courseImage']) ? $row['courseImage'] : 'https://via.placeholder.com/360x220/2575fc/ffffff?text=Course+Cover';
                        $timeStatus = daysRemaining($row['endDate']);
                        $isExpired = ($timeStatus === "Expired");
                        $dateDisplay = ($row['startDate'] === $row['endDate']) ? date('d M Y', strtotime($row['startDate'])) : date('d M Y', strtotime($row['startDate'])) . " - " . date('d M Y', strtotime($row['endDate']));
                    ?>
                    <div class="wishlist-item <?= $isExpired ? 'expired' : '' ?>">
                        <div class="wishlist-header" style="background-image:url('<?= htmlspecialchars($courseImage) ?>');">
                            <button class="wishlist-btn" title="Remove from Wishlist" onclick="removeWishlist(<?= $row['courseID'] ?>,'course',this)">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M2.5 1.5h11a.5.5 0 0 1 .5.5v12.8a.3.3 0 0 1-.45.26L8 12.5l-5.55 2.56a.3.3 0 0 1-.45-.26V2a.5.5 0 0 1 .5-.5z"/></svg>
                            </button>
                        </div>
                        <div class="wishlist-body">
                            <span class="category-tag"><?= htmlspecialchars($row['courseCategory'] ?? 'Training') ?></span>
                            <a href="course_detail.php?id=<?= $row['courseID'] ?>" class="title-link">
                                <h3><?= htmlspecialchars($row['courseName']) ?></h3>
                            </a>
                            <div class="time-label"><?= $isExpired ? 'Status: Expired' : '⏳ ' . $timeStatus ?></div>
                            <p><i class="fa-regular fa-calendar"></i> <?= $dateDisplay ?></p>
                            <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($row['courseLocation']) ?>, <?= htmlspecialchars($row['courseCountry'] ?? '') ?></p>
                            
                            <div class="note-box">
                                <div class="textarea-wrapper">
                                    <textarea id="note-<?= $row['wishlistID'] ?>" placeholder="Personal notes..."><?= htmlspecialchars($row['note']) ?></textarea>
                                    <span class="save-tooltip" id="tooltip-<?= $row['wishlistID'] ?>">✓ Note Saved!</span>
                                    <button class="save-btn" title="Save Note" data-wishlist-id="<?= $row['wishlistID'] ?>" onclick="saveNote(this)">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button> 
                                </div>
                                <div class="note-actions">
                                    <a href="course_detail.php?id=<?= $row['courseID'] ?>" class="action-button">View Course Info</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-msg"><p>No saved courses yet.</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
/**
 * Switch between Events and Courses tabs
 */
function showTab(tabId, btn) {
    document.querySelectorAll('.tabContent').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tabBtn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}

/**
 * Save notes using AJAX
 */
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

/**
 * Remove item from wishlist using AJAX
 */
function removeWishlist(id, type, btn) {
    if (!confirm('Are you sure you want to remove this from your wishlist?')) return;
    
    const idFieldName = (type === 'event') ? 'eventID' : 'courseID';
    
    fetch('wishlist_toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ [idFieldName]: id, type: type, action: 'remove' })
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'removed') {
            // Smoothly remove the card from UI
            const item = btn.closest('.wishlist-item');
            item.style.transform = 'scale(0.8)';
            item.style.opacity = '0';
            setTimeout(() => { item.remove(); }, 300);
        }
    }).catch(console.error);
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>