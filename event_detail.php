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
   SECURE AUTHORIZATION CHECK
   --------------------------------------------------------- */
$isOwner = ($userID && $userID == $event['organizerID']);
$isAdmin = ($userRole === 'admin');

// If the event is NOT approved, only Admin or Owner can see it
if ($event['status'] !== 'approved' && !$isOwner && !$isAdmin) {
    echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h2>⚠️ Access Restricted</h2>
            <p>This event is currently pending approval and is not public yet.</p>
            <a href='volunteer_event.php' style='color:#007BFF;'>Back to Events</a>
          </div>";
    exit();
}

/* ✅ Fetch Organizer Info */
$organizer = null;
if (!empty($event['organizerID'])) {
    $orgStmt = $conn->prepare("SELECT userName, userEmail, phoneNumber FROM user WHERE userID = ?");
    $orgStmt->bind_param("i", $event['organizerID']);
    $orgStmt->execute();
    $orgResult = $orgStmt->get_result();
    if ($orgResult->num_rows > 0) $organizer = $orgResult->fetch_assoc();
}

/* ✅ Anti-Sabotage Logic: Withdrawal strikes */
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

// ✅ Handle Join Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_event'])) {
    if (!$userID) {
        header("Location: login.php");
        exit();
    }

    if ($event['status'] !== 'approved') {
        echo "<script>alert('❌ Cannot join an event that is pending approval.');</script>";
    } elseif ($isBannedFromEvent) {
        echo "<script>alert('❌ Registration Blocked: Limit reached.'); window.location.href='volunteer_event.php';</script>";
        exit();
    } else {
        // Schedule conflict check logic
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
            echo "<script>alert('❌ Schedule Conflict! Registered for: \"" . addslashes($conflict['activityName']) . "\"');</script>";
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

// ✅ Handle Withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_event'])) {
    $update = $conn->prepare("UPDATE eventregistration SET registrationStatus = 'withdrawn' WHERE userID = ? AND eventID = ?");
    $update->bind_param("ii", $userID, $eventID);
    if ($update->execute()) {
        header("Location: event_detail.php?id=$eventID");
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

/* ✅ Check if User Registered */
$isRegistered = false;
if ($userID) {
    $checkReg = $conn->prepare("SELECT 1 FROM eventregistration WHERE userID = ? AND eventID = ? AND registrationStatus = 'active' LIMIT 1");
    $checkReg->bind_param("ii", $userID, $eventID);
    $checkReg->execute();
    $isRegistered = $checkReg->get_result()->num_rows > 0;
}

/* ✅ Wishlist & Gallery & Reviews */
$isWishlisted = false;
if ($userID) {
    $wishCheck = $conn->prepare("SELECT 1 FROM wishlist WHERE userID = ? AND eventID = ? LIMIT 1");
    $wishCheck->bind_param("ii", $userID, $eventID);
    $wishCheck->execute();
    $isWishlisted = $wishCheck->get_result()->num_rows > 0;
}

$sqlGallery = "SELECT imageUrl, caption FROM activitygallery WHERE activityType = 'event' AND activityID = ?";
$stmtGallery = $conn->prepare($sqlGallery);
$stmtGallery->bind_param("i", $eventID);
$stmtGallery->execute();
$galleryResult = $stmtGallery->get_result();
$galleryImages = $galleryResult->fetch_all(MYSQLI_ASSOC);

$page = isset($_GET['review_page']) ? intval($_GET['review_page']) : 1;
$reviewsPerPage = 5;
$offset = ($page - 1) * $reviewsPerPage;
$avgSQL = "SELECT AVG(rating) AS avgRating, COUNT(*) AS totalReviews FROM review WHERE activityType = 'event' AND activityID = ?";
$avgStmt = $conn->prepare($avgSQL);
$avgStmt->bind_param("i", $eventID);
$avgStmt->execute();
$avgResult = $avgStmt->get_result()->fetch_assoc();
$avgRating = round($avgResult['avgRating'] ?? 0, 1);
$totalReviews = $avgResult['totalReviews'] ?? 0;
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
.admin-preview-bar { background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeeba; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; font-weight: 600; }
.wishlist-btn { position:absolute; top:12px; right:18px; background:rgba(0,0,0,0.45); border:none; border-radius:50%; width:42px; height:42px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition: transform 0.2s ease, background 0.2s ease;}
.gallery-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; position:relative;}
.photo-box { position:relative; overflow:hidden; border-radius:8px; cursor:pointer;}
.gallery-grid img { width:100%; height:160px; object-fit:cover;}
#lightboxOverlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); justify-content:center; align-items:center; z-index:9999;}
#lightboxOverlay img { max-width:90%; max-height:80%; border-radius:10px;}
.reviews-section { margin-top: 40px; padding: 12px 14px; background: #f9f9f9; border-radius: 10px; }
.avg-rating { font-size: 2rem; font-weight: 700; color: #333; background: #fff; padding: 6px 8px; border-radius: 8px; border: 1px solid #e6e6e6; min-width: 56px; text-align: center; }
.button-container .btn-join { display: inline-block; padding: 16px 28px; border-radius: 8px; font-size: 16px; background: #007BFF; color: #fff; border: none; cursor: pointer; width: 100%; text-align: center; font-weight: 600; text-decoration: none;}
.button-container .btn-join:disabled { background: #ccc; cursor: not-allowed; color: #666; }
.button-container .btn-withdraw { background-color: #dc3545; }
.button-container .btn-blocked { background-color: #444 !important; color: #fff !important; cursor: not-allowed; opacity: 1; }
.past-event-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 15px; display: flex; align-items: flex-start; gap: 20px; text-decoration: none; color: inherit; transition: 0.2s;}
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
                <br><span style="font-size: 12px; font-weight: normal;">Volunteers cannot see this page.</span>
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="22" height="22" fill="<?= $isWishlisted ? 'white' : 'none' ?>" stroke="white" stroke-width="2">
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
                    <p><strong>Time:</strong> <?= date('h:i A', strtotime($event['startTime'])) ?> – <?= date('h:i A', strtotime($event['endTime'])) ?></p>
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
                                <form method="POST">
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
        <h3>Gallery & Location</h3>
        <div class="event-gallery">
            <?php if (!empty($galleryImages)): ?>
            <div class="gallery-grid">
                <?php foreach ($galleryImages as $i => $photo): ?>
                    <?php if ($i < 3): ?>
                        <div class="photo-box"><img src="<?= htmlspecialchars($photo['imageUrl']) ?>" onclick="showLightbox(<?= $i ?>)"></div>
                    <?php elseif ($i === 3): ?>
                        <div class="photo-box" onclick="showLightbox(<?= $i ?>)">
                            <img src="<?= htmlspecialchars($photo['imageUrl']) ?>">
                            <div class="overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); color:white; display:flex; justify-content:center; align-items:center;">View All</div>
                        </div>
                        <?php break; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="event-map" style="margin-top:20px;">
            <iframe src="https://maps.google.com/maps?q=<?= urlencode($event['eventLocation'] . ',' . $event['eventCountry']) ?>&output=embed" width="100%" height="300" style="border:0;" allowfullscreen></iframe>
        </div>
    </div>

    <?php if ($totalReviews > 0): ?>
        <div class="reviews-section" id="reviews">
            <h3>Participant Reviews (<?= $totalReviews ?>)</h3>
            <div class="review-summary">
                <span class="avg-rating"><?= $avgRating ?></span>
                <span class="avg-stars"><?= str_repeat('⭐', (int)round($avgRating)) ?></span>
            </div>
            <?php while ($review = $reviews->fetch_assoc()): ?>
                <div class="review-card">
                    <p><strong><?= htmlspecialchars($review['userName']) ?></strong> (<?= str_repeat('⭐', (int)$review['rating']) ?>)</p>
                    <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<div id="lightboxOverlay">
    <span style="position:absolute; top:20px; right:30px; color:white; font-size:40px; cursor:pointer;" onclick="document.getElementById('lightboxOverlay').style.display='none'">×</span>
    <img id="lightboxImg" src="" style="max-width:90%; max-height:80%;">
</div>

<?php include 'includes/footer.php'; ?>

<script>
function goBack(e) { e.preventDefault(); window.history.back(); }
function toggleWishlist(id, type, btn) {
    fetch('wishlist_toggle.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, type: type }) })
    .then(res => res.json()).then(data => {
        if (data.status === 'added') btn.classList.add('active');
        else btn.classList.remove('active');
        location.reload(); // Quick refresh for icon color
    });
}
function showLightbox(index) {
    const gallery = <?= json_encode($galleryImages) ?>;
    document.getElementById('lightboxImg').src = gallery[index].imageUrl;
    document.getElementById('lightboxOverlay').style.display = 'flex';
}
</script>
</body>
</html>