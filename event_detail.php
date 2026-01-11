<?php
session_start();
include("db.php");

// ✅ Show success alert ONLY if the user actually just joined and isn't currently withdrawing
if (isset($_GET['joined']) && $_GET['joined'] == 1 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('✅ Successfully joined the event!');</script>";
}

// ✅ Validate event ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("⚠️ Invalid event ID.");
}

$eventID = intval($_GET['id']);
$userID = $_SESSION['userID'] ?? null;

/* ✅ Fetch Event Details FIRST */
$sql = "SELECT * FROM event WHERE eventID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eventID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) die("⚠️ Event not found.");
$event = $result->fetch_assoc();

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

    if ($isBannedFromEvent) {
        echo "<script>alert('❌ Registration Blocked: You have reached the withdrawal limit (3 strikes) for this event.'); window.location.href='volunteer_event.php';</script>";
        exit();
    }

    // Check if already joined and active
    $check = $conn->prepare("SELECT 1 FROM eventregistration WHERE userID = ? AND eventID = ? AND registrationStatus = 'active'");
    $check->bind_param("ii", $userID, $eventID);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        echo "<script>alert('⚠️ You already joined this event.');</script>";
    } else {
        // ✅ UPDATED: CHECK FOR TIME OVERLAP with both Events AND Courses
        $overlapSql = "
            SELECT activityName FROM (
                -- Check Other Registered Events
                SELECT e.eventName AS activityName, e.startDate, e.endDate 
                FROM EventRegistration er
                JOIN event e ON er.eventID = e.eventID
                WHERE er.userID = ? AND er.registrationStatus = 'active'
                
                UNION ALL
                
                -- Check Registered Courses
                SELECT c.courseName AS activityName, c.startDate, c.endDate 
                FROM courseregistration cr
                JOIN Course c ON cr.courseID = c.courseID
                WHERE cr.userID = ? AND cr.registrationStatus = 'active'
            ) AS combined_schedule
            WHERE (startDate <= ? AND endDate >= ?)
        ";

        $checkOverlap = $conn->prepare($overlapSql);
        // We bind userID twice because it's used in both parts of the UNION
        $checkOverlap->bind_param("iiss", $userID, $userID, $event['endDate'], $event['startDate']);
        $checkOverlap->execute();
        $overlapResult = $checkOverlap->get_result();

        if ($overlapResult->num_rows > 0) {
            $conflict = $overlapResult->fetch_assoc();
            echo "<script>alert('❌ Schedule Conflict! You are already registered for: \"" . addslashes($conflict['activityName']) . "\" during this same period.');</script>";
        } else {
            // INSERT or RE-ACTIVATE Registration
            $insert = $conn->prepare("INSERT INTO eventregistration (userID, eventID, registrationStatus, status, registrationDate) 
                                    VALUES (?, ?, 'active', 'Pending', NOW()) 
                                    ON DUPLICATE KEY UPDATE registrationStatus='active', status='Pending', registrationDate=NOW()");
            $insert->bind_param("ii", $userID, $eventID);
            if ($insert->execute()) {
                header("Location: event_detail.php?id=$eventID&joined=1");
                exit();
            } else {
                echo "<script>alert('❌ Failed to join event. Please try again.');</script>";
            }
        }
    }
}

// ✅ Handle Withdrawal (Soft delete by changing registrationStatus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_event'])) {
    $update = $conn->prepare("UPDATE eventregistration SET registrationStatus = 'withdrawn' WHERE userID = ? AND eventID = ?");
    $update->bind_param("ii", $userID, $eventID);
    if ($update->execute()) {
        $newStrikeCount = $withdrawCount + 1;
        echo "<script>alert('✅ Successfully withdrawn. You have used $newStrikeCount/3 withdrawal strikes for this event.'); window.location.href='event_detail.php?id=$eventID';</script>";
        exit();
    }
}

$imagePath = !empty($event['coverImage'])
    ? 'uploads/event_cover/' . $event['coverImage']
    : 'https://via.placeholder.com/600x350';


/* ✅ Participant Count (Count Active Only) */
$sqlCount = $conn->prepare("SELECT COUNT(*) AS total FROM eventregistration WHERE eventID = ? AND registrationStatus = 'active'");
$sqlCount->bind_param("i", $eventID);
$sqlCount->execute();
$countResult = $sqlCount->get_result();
$countRow = $countResult->fetch_assoc();
$participantCount = $countRow['total'] ?? 0;

$isDeadlinePassed = !empty($event['deadline']) && strtotime($event['deadline']) < time();
$isFull = $participantCount >= ($event['maxParticipant'] ?? 0);

/* ✅ Check if User Registered and Active */
$isRegistered = false;
if ($userID) {
    $checkReg = $conn->prepare("SELECT 1 FROM eventregistration WHERE userID = ? AND eventID = ? AND registrationStatus = 'active' LIMIT 1");
    $checkReg->bind_param("ii", $userID, $eventID);
    $checkReg->execute();
    $regResult = $checkReg->get_result();
    $isRegistered = $regResult->num_rows > 0;
}

$disableButton = $isFull || $isDeadlinePassed;

/* ✅ Check Wishlist Status */
$isWishlisted = false;
if ($userID) {
    $wishCheck = $conn->prepare("SELECT 1 FROM Wishlist WHERE userID = ? AND eventID = ? LIMIT 1");
    $wishCheck->bind_param("ii", $userID, $eventID);
    $wishCheck->execute();
    $wishResult = $wishCheck->get_result();
    $isWishlisted = $wishResult->num_rows > 0;
}

/* ✅ Fetch Gallery */
$sqlGallery = "SELECT imageUrl, caption FROM activitygallery WHERE activityType = 'event' AND activityID = ?";
$stmtGallery = $conn->prepare($sqlGallery);
$stmtGallery->bind_param("i", $eventID);
$stmtGallery->execute();
$galleryResult = $stmtGallery->get_result();
$galleryImages = $galleryResult->fetch_all(MYSQLI_ASSOC);


/* --- Pagination Setup for Reviews --- */
$page = isset($_GET['review_page']) && is_numeric($_GET['review_page']) ? intval($_GET['review_page']) : 1;
$reviewsPerPage = 5;
$offset = ($page - 1) * $reviewsPerPage;

/* ✅ Total Reviews & Average */
$avgSQL = "SELECT AVG(rating) AS avgRating, COUNT(*) AS totalReviews FROM review WHERE activityType = 'event' AND activityID = ?";
$avgStmt = $conn->prepare($avgSQL);
$avgStmt->bind_param("i", $eventID);
$avgStmt->execute();
$avgResult = $avgStmt->get_result()->fetch_assoc();

$avgRating = round($avgResult['avgRating'] ?? 0, 1);
$totalReviews = $avgResult['totalReviews'] ?? 0;
$totalPages = ceil($totalReviews / $reviewsPerPage);

/* ✅ Fetch Paginated Reviews */
$sqlReviews = "SELECT r.rating, r.comment, r.reviewDate, u.userName FROM review r JOIN user u ON r.userID = u.userID WHERE r.activityType = 'event' AND r.activityID = ? ORDER BY r.reviewDate DESC LIMIT ? OFFSET ?";
$stmt2 = $conn->prepare($sqlReviews);
$stmt2->bind_param("iii", $eventID, $reviewsPerPage, $offset);
$stmt2->execute();
$reviews = $stmt2->get_result();


/* ✅ Fetch Past Events linked to this event */
$pastEventQuery = "SELECT e.eventID, e.eventName, e.coverImage, e.startDate, e.endDate FROM eventpast ep JOIN Event e ON ep.pastEventID = e.eventID WHERE ep.eventID = ?";
$pastStmt = $conn->prepare($pastEventQuery);
$pastStmt->bind_param("i", $eventID);
$pastStmt->execute();
$pastEventsResult = $pastStmt->get_result();
$pastEvents = [];
while ($row = $pastEventsResult->fetch_assoc()) { $pastEvents[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($event['eventName'] ?? 'Event Details') ?> - Event Details</title>
<link rel="stylesheet" href="style.css?v=<?= time() ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* --- Wishlist --- */
.wishlist-btn { position:absolute; top:12px; right:18px; background:rgba(0,0,0,0.45); border:none; border-radius:50%; width:42px; height:42px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition: transform 0.2s ease, background 0.2s ease;}
.wishlist-btn:hover { transform: scale(1.1); background: rgba(0,0,0,0.65);}
.wishlist-btn svg { width:22px; height:22px; stroke:#fff; stroke-width:2; fill:none;}
.wishlist-btn.active svg { fill:#fff; stroke:#fff; }

/* --- Gallery --- */
.gallery-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; position:relative;}
.gallery-grid .photo-box { position:relative; overflow:hidden; border-radius:8px; cursor:pointer;}
.gallery-grid img { width:100%; height:160px; object-fit:cover; transition: transform 0.3s;}
.gallery-grid img:hover { transform: scale(1.05); }
.photo-box .overlay { position:absolute; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.5); color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; font-weight:600; font-size:1.1rem; border-radius:8px;}
.photo-box .overlay i { font-size:1.6rem; margin-bottom:5px; }

/* --- Lightbox --- */
#lightboxOverlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); justify-content:center; align-items:center; z-index:9999;}
#lightboxOverlay img { max-width:90%; max-height:80%; border-radius:10px; box-shadow:0 0 20px rgba(0,0,0,0.5);}
#lightboxClose, #lightboxPrev, #lightboxNext { position:absolute; color:#fff; font-size:2rem; cursor:pointer; padding:10px; user-select:none;}
#lightboxClose { top:20px; right:30px; font-size:2.5rem;}
#lightboxPrev { left:30px; top:50%; transform:translateY(-50%);}
#lightboxNext { right:30px; top:50%; transform:translateY(-50%);}

/* --- Compact Review Section --- */
.reviews-section { margin-top: 40px; padding: 12px 14px; background: #f9f9f9; border-radius: 10px; }
.reviews-header h3 { margin-bottom: 8px; font-size: 18px; }
.review-summary { display: flex; align-items: center; gap: 10px; padding: 6px 0; font-size: 13px; color: #444; }
.rating-left { display:flex; align-items:center; gap:8px; }
.avg-rating { font-size: 2rem; font-weight: 700; color: #333; background: #fff; padding: 6px 8px; border-radius: 8px; border: 1px solid #e6e6e6; min-width: 56px; text-align: center; }
.avg-stars { font-size: 1.2rem; color: #ffb400; }
.review-card { background: #fff; border: 1px solid #e6e6e6; border-radius: 8px; padding: 8px 10px; margin-bottom: 8px; }
.review-card p { margin: 4px 0; font-size: 13px; line-height: 1.3; }
.review-meta { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.review-meta .review-name { font-weight: 600; font-size: 13px; }
.review-meta .review-rating { font-size: 12px; color: #ffb400; }
.review-card small { font-size: 11px; color: #777; }

/* --- Buttons --- */
.button-container .btn-join { display: inline-block; padding: 16px 28px; border-radius: 8px; font-size: 16px; background: #007BFF; color: #fff; border: none; cursor: pointer; box-shadow: 0 3px 8px rgba(0,0,0,0.12); transition: transform 0.12s ease, box-shadow 0.12s ease; width: 100%; text-align: center; font-weight: 600; }
.button-container .btn-join:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.15); }
.button-container .btn-join:disabled { background: #ccc; cursor: not-allowed; color: #666; }
.button-container .btn-withdraw { background-color: #dc3545; }
.button-container .btn-withdraw:hover { background-color: #c82333; }
.button-container .btn-blocked { background-color: #444 !important; color: #fff !important; cursor: not-allowed; opacity: 1; }

.review-pagination { margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 8px; }
.page-btn { padding: 6px 10px; border: 1px solid #dcdcdc; border-radius: 6px; text-decoration: none; color: #333; background: #fff; font-size: 13px; }
.page-btn.active { background: #333; color: #fff; border-color: #333; }

/* Past Event Section */
.past-event-section { margin-top: 55px; padding: 25px; background: #f8f8f8; border-radius: 14px; margin-bottom: 50px; }
.past-event-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 25px; margin-top: 20px; }
.past-event-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 15px; display: flex; align-items: flex-start; gap: 20px; cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease; text-decoration: none; color: inherit; }
.past-event-card:hover { transform: translateY(-3px); box-shadow: 0 4px 14px rgba(0,0,0,0.14); }
.past-event-card img { width: 150px; height: 100px; object-fit: cover; border-radius: 10px; flex-shrink: 0; }
.past-event-info { text-align: left; display: flex; flex-direction: column; justify-content: flex-start; flex: 1;}
.past-event-info p strong { font-size: 18px; font-weight: 700; display: block; margin-bottom: 5px; color: #222;}
.past-event-info p { margin: 2px 0; font-size: 14px; color: #555;}
</style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="main-content">
    <div class="detail-container">
        <div class="header-row">
            <a href="volunteer_event.php" class="back-btn" onclick="goBack(event)">← Back</a>
            <h2><?= htmlspecialchars($event['eventName'] ?? '') ?></h2>
        </div>

        <div class="event-layout">
            <div class="event-left">
                <div class="detail-image-container">
                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="Event Image" class="detail-image">
                    <?php if ($userID): ?>
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
                    <p><strong>Start Date:</strong> <?= !empty($event['startDate']) ? date('d M Y', strtotime($event['startDate'])) : 'Not set' ?></p>
                    <p><strong>End Date:</strong> <?= !empty($event['endDate']) ? date('d M Y', strtotime($event['endDate'])) : 'Not set' ?></p>
                    
                    <p><strong>Time:</strong> 
                        <?php if(!empty($event['startTime']) && !empty($event['endTime'])): ?>
                            <?= date('h:i A', strtotime($event['startTime'])) ?> – <?= date('h:i A', strtotime($event['endTime'])) ?>
                        <?php else: ?>
                            Time Not Set
                        <?php endif; ?>
                    </p>

                    <p><strong>Deadline:</strong> <?= !empty($event['deadline']) ? date('d M Y', strtotime($event['deadline'])) : 'Not set' ?></p>
                </div>

                <div class="button-container">
                    <?php if ($userID): ?>
                        <?php if ($isBannedFromEvent): ?>
                            <button class="btn-join btn-blocked" disabled>Registration Blocked</button>
                        
                        <?php elseif ($isRegistered): ?>
                            <form method="POST" action="" onsubmit="return confirm('⚠️ Withdrawing counts as 1 strike. You have <?= $strikesLeft ?> strikes left before being blocked from this event. Are you sure you want to withdraw?')">
                                <button type="submit" name="withdraw_event" class="btn-join btn-withdraw">
                                    Withdraw from Event
                                </button>
                            </form>
                        
                        <?php else: ?>
                            <form method="POST" action="" class="action-form">
                                <button type="submit" name="join_event" class="btn-join" <?= ($participantCount >= $event['maxParticipant'] || $isDeadlinePassed) ? 'disabled' : '' ?>>
                                    <?php 
                                        if ($isFull) echo 'Event Full';
                                        elseif ($isDeadlinePassed) echo 'Deadline Passed';
                                        else echo 'Join Event';
                                    ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" action="" class="action-form">
                            <button type="submit" name="join_event" class="btn-join">Join Event</button>
                        </form>
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
                            <img src="<?= htmlspecialchars($photo['imageUrl']) ?>" alt="" data-index="<?= $i ?>">
                        </div>
                    <?php elseif ($i === 3): ?>
                        <div class="photo-box" data-index="<?= $i ?>">
                            <img src="<?= htmlspecialchars($photo['imageUrl']) ?>" alt="">
                            <div class="overlay">
                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                                View All <?= count($galleryImages) ?> Photos
                            </div>
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
            <?php else: ?>
                <p>No gallery images available.</p>
            <?php endif; ?>
        </div>

        <div class="event-map">
            <h4>Event Location</h4>
            <iframe src="https://www.google.com/maps?q=<?= urlencode(($event['eventLocation'] ?? '') . ', ' . ($event['eventCountry'] ?? '')) ?>&output=embed" width="100%" height="300" style="border:0;" allowfullscreen loading="lazy"></iframe>
        </div>
    </div>

    <?php if ($totalReviews > 0): ?>
        <div class="reviews-section" id="reviews">
            <div class="reviews-header"><h3>Participant Reviews</h3></div>
            <div class="review-summary">
                <div class="rating-left" style="display:flex; align-items:center; gap:6px;">
                    <span class="avg-rating"><?= $avgRating ?></span>
                    <span class="avg-stars"><?= str_repeat('⭐', (int)round($avgRating)) ?></span>
                </div>
                <p><?= $totalReviews ?> total review<?= $totalReviews > 1 ? 's' : '' ?></p>
            </div>

            <?php while ($review = $reviews->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="review-meta">
                        <span class="review-name"><?= htmlspecialchars($review['userName'] ?? 'Anonymous') ?></span>
                        <span class="review-rating"><?= str_repeat('⭐', (int)$review['rating']) ?></span>
                    </div>
                    <p><?= nl2br(htmlspecialchars($review['comment'] ?? '')) ?></p>
                    <small>Reviewed on <?= date('d M Y', strtotime($review['reviewDate'] ?? 'now')) ?></small>
                </div>
            <?php endwhile; ?>

            <?php if ($totalPages > 1): ?>
                <div class="review-pagination">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?= $eventID ?>&review_page=<?= $page - 1 ?>#reviews" class="page-btn">« Prev</a>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="?id=<?= $eventID ?>&review_page=<?= $p ?>#reviews" class="page-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?id=<?= $eventID ?>&review_page=<?= $page + 1 ?>#reviews" class="page-btn">Next »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pastEvents)): ?>
    <div class="past-event-section">
        <h3>Related Past Events</h3>
        <div class="past-event-list">
            <?php foreach ($pastEvents as $past): 
                $pastImg = !empty($past['coverImage']) ? 'uploads/event_cover/' . $past['coverImage'] : 'https://via.placeholder.com/150x100';
            ?>
                <a href="event_detail.php?id=<?= $past['eventID'] ?>" class="past-event-card">
                    <img src="<?= htmlspecialchars($pastImg) ?>" alt="Past Event">
                    <div class="past-event-info">
                        <p><strong><?= htmlspecialchars($past['eventName']) ?></strong></p>
                        <p><i class="fa fa-calendar"></i> <?= date('M Y', strtotime($past['startDate'])) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<div id="lightboxOverlay">
    <span id="lightboxClose">×</span>
    <span id="lightboxPrev">❮</span>
    <img id="lightboxImg" src="" alt="Gallery">
    <span id="lightboxNext">❯</span>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// ✅ SMART BACK LOGIC
function goBack(e) {
    e.preventDefault(); 
    if (document.referrer !== "") {
        window.history.back();
    } else {
        window.location.href = "volunteer_event.php";
    }
}

function toggleWishlist(id, type, btn) {
    fetch('wishlist_toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, type: type })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'added') btn.classList.add('active');
        else if (data.status === 'removed') btn.classList.remove('active');
    });
}

const galleryImages = document.querySelectorAll('#hiddenGallery img');
const lightboxOverlay = document.getElementById('lightboxOverlay');
const lightboxImg = document.getElementById('lightboxImg');
const closeBtn = document.getElementById('lightboxClose');
const prevBtn = document.getElementById('lightboxPrev');
const nextBtn = document.getElementById('lightboxNext');
let currentIndex = 0;

function showLightbox(index) {
    currentIndex = index;
    lightboxImg.src = galleryImages[currentIndex].src;
    lightboxOverlay.style.display = 'flex';
}

document.querySelectorAll('.photo-box').forEach((box, idx) => {
    box.addEventListener('click', () => showLightbox(idx));
});

closeBtn.addEventListener('click', () => lightboxOverlay.style.display = 'none');
prevBtn.addEventListener('click', () => {
    currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length;
    lightboxImg.src = galleryImages[currentIndex].src;
});
nextBtn.addEventListener('click', () => {
    currentIndex = (currentIndex + 1) % galleryImages.length;
    lightboxImg.src = galleryImages[currentIndex].src;
});

document.querySelectorAll('form.action-form').forEach(form => {
    form.addEventListener('submit', function (e) {
        const btn = form.querySelector('button[name="join_event"]');
        if (btn && !btn.disabled && <?= $userID ? 'true' : 'false' ?>) {
            if (!confirm("Are you sure you want to join this event?")) {
                e.preventDefault();
            }
        }
    });
});
</script>
</body>
</html>