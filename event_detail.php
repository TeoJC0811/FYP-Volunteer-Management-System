<?php
session_start();
include("db.php");

// ✅ Show success alert ONLY if the user actually just joined
if (isset($_GET['joined']) && $_GET['joined'] == 1 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('✅ Successfully joined the event!');</script>";
}

// ✅ Validate event ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("⚠️ Invalid event ID.");
}

$eventID = intval($_GET['id']);
$userID = $_SESSION['userID'] ?? null;
$userRole = $_SESSION['role'] ?? 'guest';

/* ✅ Fetch Event Details FIRST */
$sql = "SELECT * FROM event WHERE eventID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eventID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) die("⚠️ Event not found.");
$event = $result->fetch_assoc();

/* ---------------------------------------------------------
   NEW: AUTHORIZATION & ADMIN PREVIEW LOGIC
   --------------------------------------------------------- */
$isOwner = ($userID && $userID == $event['organizerID']);
$isAdmin = ($userRole === 'admin');

// 🔒 Security: Only Admin or Owner can view if not approved
if ($event['status'] !== 'approved' && !$isOwner && !$isAdmin) {
    echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h2>⚠️ Access Restricted</h2>
            <p>This event is currently pending approval and is not public yet.</p>
            <a href='volunteer_event.php' style='color:#007BFF;'>Back to Events</a>
          </div>";
    exit();
}

/* ✅ Fetch Organizer Info (Fixed Undefined Variable Bug) */
$organizer = null;
if (!empty($event['organizerID'])) {
    $orgStmt = $conn->prepare("SELECT userName, userEmail, phoneNumber FROM user WHERE userID = ?");
    $orgStmt->bind_param("i", $event['organizerID']);
    $orgStmt->execute();
    $orgResult = $orgStmt->get_result();
    if ($orgResult->num_rows > 0) $organizer = $orgResult->fetch_assoc();
}

/* ✅ Anti-Sabotage Logic: Count withdrawals for this specific user and event */
$withdrawCount = 0;
if ($userID) {
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM eventregistration WHERE userID = ? AND eventID = ? AND registrationStatus = 'withdrawn'");
    $countStmt->bind_param("ii", $userID, $eventID);
    $countStmt->execute();
    $withdrawCount = $countStmt->get_result()->fetch_assoc()['total'];
}

$maxStrikes = 3;
$isBannedFromEvent = ($withdrawCount >= $maxStrikes);
$strikesLeft = $maxStrikes - $withdrawCount;

// ✅ Handle Join Event button with Schedule Conflict Check (Events + Courses)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_event'])) {
    if (!$userID) {
        header("Location: login.php");
        exit();
    }

    if ($event['status'] !== 'approved') {
        echo "<script>alert('❌ You cannot join an event that has not been approved yet.');</script>";
    } elseif ($isBannedFromEvent) {
        echo "<script>alert('❌ Registration Blocked: Withdrawal limit reached.'); window.location.href='volunteer_event.php';</script>";
        exit();
    } else {
        // Check if already joined and active
        $check = $conn->prepare("SELECT 1 FROM eventregistration WHERE userID = ? AND eventID = ? AND registrationStatus = 'active'");
        $check->bind_param("ii", $userID, $eventID);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            echo "<script>alert('⚠️ You already joined this event.');</script>";
        } else {
            $overlapSql = "
                SELECT activityName FROM (
                    SELECT e.eventName AS activityName, e.startDate, e.endDate 
                    FROM eventregistration er
                    JOIN event e ON er.eventID = e.eventID
                    WHERE er.userID = ? AND er.registrationStatus = 'active'
                    UNION ALL
                    SELECT c.courseName AS activityName, c.startDate, c.endDate 
                    FROM courseregistration cr
                    JOIN course c ON cr.courseID = c.courseID
                    WHERE cr.userID = ? AND cr.registrationStatus = 'active'
                ) AS combined_schedule
                WHERE (startDate <= ? AND endDate >= ?)
            ";

            $checkOverlap = $conn->prepare($overlapSql);
            $checkOverlap->bind_param("iiss", $userID, $userID, $event['endDate'], $event['startDate']);
            $checkOverlap->execute();
            $overlapResult = $checkOverlap->get_result();

            if ($overlapResult->num_rows > 0) {
                $conflict = $overlapResult->fetch_assoc();
                echo "<script>alert('❌ Schedule Conflict! Already registered for: \"" . addslashes($conflict['activityName']) . "\"');</script>";
            } else {
                $insert = $conn->prepare("INSERT INTO eventregistration (userID, eventID, registrationStatus, status, registrationDate) 
                                        VALUES (?, ?, 'active', 'Pending', NOW()) 
                                        ON DUPLICATE KEY UPDATE registrationStatus='active', status='Pending', registrationDate=NOW()");
                $insert->bind_param("ii", $userID, $eventID);
                if ($insert->execute()) {
                    header("Location: event_detail.php?id=$eventID&joined=1");
                    exit();
                }
            }
        }
    }
}

// ✅ Handle Withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_event'])) {
    $update = $conn->prepare("UPDATE eventregistration SET registrationStatus = 'withdrawn' WHERE userID = ? AND eventID = ?");
    $update->bind_param("ii", $userID, $eventID);
    if ($update->execute()) {
        $newStrikeCount = $withdrawCount + 1;
        echo "<script>alert('✅ Successfully withdrawn. Strikes used: $newStrikeCount/3.'); window.location.href='event_detail.php?id=$eventID';</script>";
        exit();
    }
}

$imagePath = !empty($event['coverImage']) ? 'uploads/event_cover/' . $event['coverImage'] : 'https://via.placeholder.com/600x350';

/* ✅ Participant Count */
$sqlCount = $conn->prepare("SELECT COUNT(*) AS total FROM eventregistration WHERE eventID = ? AND registrationStatus = 'active'");
$sqlCount->bind_param("i", $eventID);
$sqlCount->execute();
$participantCount = $sqlCount->get_result()->fetch_assoc()['total'] ?? 0;

$isDeadlinePassed = !empty($event['deadline']) && strtotime($event['deadline']) < time();
$isFull = $participantCount >= ($event['maxParticipant'] ?? 0);

/* ✅ Registered Check */
$isRegistered = false;
if ($userID) {
    $checkReg = $conn->prepare("SELECT 1 FROM eventregistration WHERE userID = ? AND eventID = ? AND registrationStatus = 'active' LIMIT 1");
    $checkReg->bind_param("ii", $userID, $eventID);
    $checkReg->execute();
    $isRegistered = $checkReg->get_result()->num_rows > 0;
}

/* ✅ Wishlist & Gallery & Reviews logic remains exactly as provided */
$isWishlisted = false;
if ($userID) {
    $wishCheck = $conn->prepare("SELECT 1 FROM wishlist WHERE userID = ? AND eventID = ? LIMIT 1");
    $wishCheck->bind_param("ii", $userID, $eventID);
    $wishCheck->execute();
    $isWishlisted = $wishCheck->get_result()->num_rows > 0;
}
$sqlGallery = "SELECT imageUrl, caption FROM activitygallery WHERE activityType = 'event' AND activityID = ?";
$stmtG = $conn->prepare($sqlGallery);
$stmtG->bind_param("i", $eventID);
$stmtG->execute();
$galleryImages = $stmtG->get_result()->fetch_all(MYSQLI_ASSOC);

$page = isset($_GET['review_page']) ? intval($_GET['review_page']) : 1;
$reviewsPerPage = 5;
$offset = ($page - 1) * $reviewsPerPage;
$avgSQL = "SELECT AVG(rating) AS avgRating, COUNT(*) AS totalReviews FROM review WHERE activityType = 'event' AND activityID = ?";
$avgStmt = $conn->prepare($avgSQL);
$avgStmt->bind_param("i", $eventID);
$avgStmt->execute();
$avgRes = $avgStmt->get_result()->fetch_assoc();
$avgRating = round($avgRes['avgRating'] ?? 0, 1);
$totalReviews = $avgRes['totalReviews'] ?? 0;
$totalPages = ceil($totalReviews / $reviewsPerPage);
$sqlReviews = "SELECT r.rating, r.comment, r.reviewDate, u.userName FROM review r JOIN user u ON r.userID = u.userID WHERE r.activityType = 'event' AND r.activityID = ? ORDER BY r.reviewDate DESC LIMIT ? OFFSET ?";
$stmt2 = $conn->prepare($sqlReviews);
$stmt2->bind_param("iii", $eventID, $reviewsPerPage, $offset);
$stmt2->execute();
$reviews = $stmt2->get_result();

$pastEventQuery = "SELECT e.eventID, e.eventName, e.coverImage, e.startDate, e.endDate FROM eventpast ep JOIN event e ON ep.pastEventID = e.eventID WHERE ep.eventID = ?";
$pastStmt = $conn->prepare($pastEventQuery);
$pastStmt->bind_param("i", $eventID);
$pastStmt->execute();
$pastEvents = $pastStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($event['eventName'] ?? 'Event Details') ?></title>
<link rel="stylesheet" href="style.css?v=<?= time() ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* --- Admin Preview Bar Style --- */
.admin-preview-bar { background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeeba; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; font-weight: 600; }
.admin-preview-bar i { font-size: 20px; }

/* --- Your Original Styles --- */
.wishlist-btn { position:absolute; top:12px; right:18px; background:rgba(0,0,0,0.45); border:none; border-radius:50%; width:42px; height:42px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition: transform 0.2s ease, background 0.2s ease;}
.wishlist-btn svg { width:22px; height:22px; stroke:#fff; stroke-width:2; fill:none;}
.wishlist-btn.active svg { fill:#fff; stroke:#fff; }
.gallery-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; position:relative;}
.gallery-grid .photo-box { position:relative; overflow:hidden; border-radius:8px; cursor:pointer;}
.gallery-grid img { width:100%; height:160px; object-fit:cover; transition: transform 0.3s;}
.gallery-grid img:hover { transform: scale(1.05); }
.photo-box .overlay { position:absolute; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.5); color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; font-weight:600; font-size:1.1rem; border-radius:8px;}
#lightboxOverlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); justify-content:center; align-items:center; z-index:9999;}
#lightboxOverlay img { max-width:90%; max-height:80%; border-radius:10px;}
.reviews-section { margin-top: 40px; padding: 12px 14px; background: #f9f9f9; border-radius: 10px; }
.avg-rating { font-size: 2rem; font-weight: 700; color: #333; background: #fff; padding: 6px 8px; border-radius: 8px; border: 1px solid #e6e6e6; min-width: 56px; text-align: center; }
.avg-stars { font-size: 1.2rem; color: #ffb400; }
.button-container .btn-join { display: inline-block; padding: 16px 28px; border-radius: 8px; font-size: 16px; background: #007BFF; color: #fff; border: none; cursor: pointer; box-shadow: 0 3px 8px rgba(0,0,0,0.12); width: 100%; text-align: center; font-weight: 600; text-decoration: none; }
.button-container .btn-join:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.15); }
.button-container .btn-join:disabled { background: #ccc; cursor: not-allowed; color: #666; }
.button-container .btn-withdraw { background-color: #dc3545; }
.button-container .btn-blocked { background-color: #444 !important; color: #fff !important; cursor: not-allowed; opacity: 1; }
.past-event-section { margin-top: 55px; padding: 25px; background: #f8f8f8; border-radius: 14px; margin-bottom: 50px; }
.past-event-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 15px; display: flex; align-items: flex-start; gap: 20px; cursor: pointer; text-decoration: none; color: inherit; transition: 0.15s ease;}
</style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="main-content">

    <?php if ($isAdmin && $event['status'] === 'pending'): ?>
        <div class="admin-preview-bar">
            <i class="fa-solid fa-user-shield"></i>
            <div>
                ADMIN PREVIEW: This event is currently PENDING. 
                <br><span style="font-size: 12px; font-weight: normal;">Volunteers cannot see this page yet.</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="detail-container">
        <div class="header-row">
            <a href="volunteer_event.php" class="back-btn" onclick="goBack(event)">← Back</a>
            <h2><?= htmlspecialchars($event['eventName'] ?? '') ?></h2>
        </div>

        <div class="event-layout">
            <div class="event-left">
                <div class="detail-image-container">
                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="Event Image" class="detail-image">
                    <?php if ($userID && $event['status'] === 'approved'): ?>
                    <button class="wishlist-btn <?= $isWishlisted ? 'active' : '' ?>" onclick="toggleWishlist(<?= $eventID ?>, 'event', this)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                            <path d="M2.5 1.5h11a.5.5 0 0 1 .5.5v12.8a.3.3 0 0 1-.45.26L8 12.5l-5.55 2.56a.3.3 0 0 1-.45-.26V2a.5.5 0 0 1 .5-.5z"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="event-info-below">
                    <?php if ($organizer): ?>
                        <p><strong>Organizer:</strong> <?= htmlspecialchars($organizer['userName'] ?? '') ?></p>
                    <?php endif; ?>
                    <p class="event-desc"><strong>Description:</strong><br><?= nl2br(htmlspecialchars($event['description'] ?? 'No description')) ?></p>
                </div>
            </div>

            <div class="event-right">
                <div>
                    <p><strong>Location:</strong> <?= htmlspecialchars($event['eventLocation'] ?? '') ?>, <?= htmlspecialchars($event['eventCountry'] ?? '') ?></p>
                    <p><strong>Participants:</strong> <?= htmlspecialchars($participantCount) ?> / <?= htmlspecialchars($event['maxParticipant'] ?? 0) ?></p>
                    <p><strong>Points:</strong> <?= htmlspecialchars($event['point'] ?? 0) ?></p>
                    <p><strong>Start Date:</strong> <?= date('d M Y', strtotime($event['startDate'])) ?></p>
                    <p><strong>End Date:</strong> <?= date('d M Y', strtotime($event['endDate'])) ?></p>
                    <p><strong>Time:</strong> <?= !empty($event['startTime']) ? date('h:i A', strtotime($event['startTime'])) : '00:00' ?> – <?= !empty($event['endTime']) ? date('h:i A', strtotime($event['endTime'])) : '00:00' ?></p>
                    <p><strong>Deadline:</strong> <?= date('d M Y', strtotime($event['deadline'])) ?></p>
                </div>

                <div class="button-container">
                    <?php if ($event['status'] === 'approved'): ?>
                        <?php if ($userID): ?>
                            <?php if ($isBannedFromEvent): ?>
                                <button class="btn-join btn-blocked" disabled>Registration Blocked</button>
                            <?php elseif ($isRegistered): ?>
                                <form method="POST" onsubmit="return confirm('⚠️ Strike Check: Strikes Left: <?= $strikesLeft ?>. Sure?')">
                                    <button type="submit" name="withdraw_event" class="btn-join btn-withdraw">Withdraw from Event</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="action-form">
                                    <button type="submit" name="join_event" class="btn-join" <?= ($isFull || $isDeadlinePassed) ? 'disabled' : '' ?>>
                                        <?php 
                                            if ($isFull) echo 'Event Full';
                                            elseif ($isDeadlinePassed) echo 'Deadline Passed';
                                            else echo 'Join Event';
                                        ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="btn-join">Login to Join</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn-join btn-blocked" disabled>Pending Approval</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="event-extra-section">
        <h3>Event Gallery & Location</h3>
        <div class="event-gallery">
            <h4>Photo Gallery</h4>
            <?php if (!empty($galleryImages)): ?>
            <div class="gallery-grid">
                <?php foreach ($galleryImages as $i => $photo): ?>
                    <?php if ($i < 3): ?>
                        <div class="photo-box">
                            <img src="<?= htmlspecialchars($photo['imageUrl']) ?>" alt="" onclick="showLightbox(<?= $i ?>)">
                        </div>
                    <?php elseif ($i === 3): ?>
                        <div class="photo-box" onclick="showLightbox(<?= $i ?>)">
                            <img src="<?= htmlspecialchars($photo['imageUrl']) ?>" alt="">
                            <div class="overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); color:white; display:flex; justify-content:center; align-items:center;">View All</div>
                        </div>
                        <?php break; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div id="hiddenGallery" style="display:none;">
                <?php foreach ($galleryImages as $photo): ?>
                    <img src="<?= htmlspecialchars($photo['imageUrl']) ?>" alt="">
                <?php endforeach; ?>
            </div>
            <?php else: ?><p>No gallery images available.</p><?php endif; ?>
        </div>
        <div class="event-map">
            <h4>Event Location</h4>
            <iframe src="https://maps.google.com/maps?q=<?= urlencode(($event['eventLocation'] ?? '') . ', ' . ($event['eventCountry'] ?? '')) ?>&output=embed" width="100%" height="300" style="border:0;" allowfullscreen></iframe>
        </div>
    </div>

    <?php if ($totalReviews > 0): ?>
        <div class="reviews-section" id="reviews">
            <div class="reviews-header"><h3>Participant Reviews</h3></div>
            <div class="review-summary">
                <span class="avg-rating"><?= $avgRating ?></span>
                <span class="avg-stars"><?= str_repeat('⭐', (int)round($avgRating)) ?></span>
                <p><?= $totalReviews ?> reviews</p>
            </div>
            <?php while ($review = $reviews->fetch_assoc()): ?>
                <div class="review-card">
                    <p><strong><?= htmlspecialchars($review['userName']) ?></strong> (<?= str_repeat('⭐', (int)$review['rating']) ?>)</p>
                    <p><?= nl2br(htmlspecialchars($review['comment'] ?? '')) ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pastEvents)): ?>
    <div class="past-event-section">
        <h3>Related Past Events</h3>
        <div class="past-event-list" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:20px;">
            <?php foreach ($pastEvents as $past): 
                $pImg = !empty($past['coverImage']) ? 'uploads/event_cover/' . $past['coverImage'] : 'https://via.placeholder.com/150x100';
            ?>
                <a href="event_detail.php?id=<?= $past['eventID'] ?>" class="past-event-card">
                    <img src="<?= htmlspecialchars($pImg) ?>" alt="Past Event" width="100">
                    <div>
                        <strong><?= htmlspecialchars($past['eventName']) ?></strong>
                        <p><?= date('M Y', strtotime($past['startDate'])) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="lightboxOverlay">
    <span style="position:absolute; top:20px; right:30px; color:white; font-size:40px; cursor:pointer;" onclick="document.getElementById('lightboxOverlay').style.display='none'">×</span>
    <img id="lightboxImg" src="" style="max-width:90%; max-height:80%; border-radius:10px;">
</div>

<?php include 'includes/footer.php'; ?>

<script>
function goBack(e) { e.preventDefault(); window.history.back(); }
function toggleWishlist(id, type, btn) {
    fetch('wishlist_toggle.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, type: type }) })
    .then(res => res.json()).then(data => {
        if (data.status === 'added') btn.classList.add('active');
        else btn.classList.remove('active');
    });
}
function showLightbox(index) {
    const gallery = <?= json_encode($galleryImages) ?>;
    document.getElementById('lightboxImg').src = gallery[index].imageUrl;
    document.getElementById('lightboxOverlay').style.display = 'flex';
}
document.querySelectorAll('form.action-form').forEach(form => {
    form.addEventListener('submit', function (e) {
        if (!confirm("Are you sure you want to join this event?")) e.preventDefault();
    });
});
</script>
</body>
</html>