<?php
session_start();
include("../db.php");

/* ================================
    ACCESS CONTROL
================================ */
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: ../login.php");
    exit();
}

// 🛑 Expecting 'courseID' from the URL
if (!isset($_GET['courseID']) || !is_numeric($_GET['courseID'])) {
    header("Location: select_training_review.php");
    exit();
}

$courseID = intval($_GET['courseID']);
$organizerID = $_SESSION['userID'];

/* ============================================
    VERIFY TRAINING & FETCH INFO
============================================= */
$stmt = $conn->prepare("
    SELECT courseName, courseLocation, courseCountry, startDate, endDate, startTime, endTime, organizerID
    FROM course
    WHERE courseID = ?
");
$stmt->bind_param("i", $courseID);
$stmt->execute();
$trainingResult = $stmt->get_result();

if ($trainingResult->num_rows === 0) {
    die("⚠️ Training not found.");
}

$training = $trainingResult->fetch_assoc();

// Organizer check
if ($_SESSION['role'] === 'organizer' && $training['organizerID'] != $organizerID) {
    die("⚠️ You are not authorized to view this training's reviews.");
}

/* ================================
    DELETE REVIEW
================================ */
$message = $error = "";

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "✅ Review deleted successfully!";
}

if (isset($_GET['delete'])) {
    $reviewID = intval($_GET['delete']);
    
    $check_review_stmt = $conn->prepare("SELECT reviewID FROM review WHERE reviewID = ? AND activityID = ? AND activityType = 'course'");
    $check_review_stmt->bind_param("ii", $reviewID, $courseID);
    $check_review_stmt->execute();
    
    if ($check_review_stmt->get_result()->num_rows > 0) {
        $del = $conn->prepare("DELETE FROM review WHERE reviewID = ?");
        $del->bind_param("i", $reviewID);
    
        if ($del->execute()) {
            header("Location: review_training.php?courseID=" . $courseID . "&success=1");
            exit();
        } else {
            $error = "❌ Error deleting review.";
        }
    } else {
        $error = "❌ Review not found or does not belong to this training.";
    }
}

/* =====================================
    HANDLE SORTING LOGIC
===================================== */
$sort = $_GET['sort'] ?? 'date_desc';
$orderBy = "r.reviewDate DESC";

switch ($sort) {
    case 'date_asc':    $orderBy = "r.reviewDate ASC"; break;
    case 'rating_desc': $orderBy = "r.rating DESC, r.reviewDate DESC"; break;
    case 'rating_asc':  $orderBy = "r.rating ASC, r.reviewDate ASC"; break;
    default:            $orderBy = "r.reviewDate DESC"; break;
}

/* ================================
    FETCH TRAINING REVIEWS
================================ */
$query = "
    SELECT r.reviewID, r.rating, r.comment, r.reviewDate, u.userName
    FROM review r
    JOIN user u ON r.userID = u.userID
    WHERE r.activityType = 'course'
      AND r.activityID = ?
    ORDER BY {$orderBy} 
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $courseID);
$stmt->execute();
$reviews = $stmt->get_result();

// Format Date Range safely
$startDateVal = $training['startDate'] ?? '';
$endDateVal = $training['endDate'] ?? '';

if (!empty($startDateVal)) {
    $startStr = date('d M Y', strtotime($startDateVal));
    $endStr = !empty($endDateVal) ? date('d M Y', strtotime($endDateVal)) : $startStr;
    $displayDate = ($startDateVal === $endDateVal) ? $startStr : "$startStr — $endStr";
} else {
    $displayDate = "Date not set";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Training Reviews - <?= htmlspecialchars($training['courseName']) ?></title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .back-btn { background-color: #333; color: white; padding: 8px 14px; text-decoration: none; border-radius: 6px; margin-bottom: 25px; display: inline-block; transition: 0.2s; }
    .back-btn:hover { background-color: #555; }

    .success { color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    .error { color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px; }

    /* --- INFO CARD STYLES (Matched to review_event.php) --- */
    .summary-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        padding: 25px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .info-group h3 { margin: 0 0 10px 0; font-size: 24px; color: #2c3e50; }
    .info-group p { margin: 5px 0; color: #636e72; font-size: 15px; display: flex; align-items: center; }
    .info-group i { color: #3498db; width: 25px; font-size: 1.1em; }

    .review-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .sort-controls { display: flex; align-items: center; gap: 10px; }
    .sort-controls select { padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; cursor: pointer; }
    
    .review-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; margin-top: 20px; }
    .review-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; justify-content: space-between; }
    .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; }
    .reviewer-name { font-weight: bold; color: #007bff; font-size: 1.1em; }
    .rating-stars { color: gold; font-size: 1.4em; }
    .review-comment { margin-bottom: 20px; line-height: 1.6; color: #333; }
    .review-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #f0f0f0; }
    .review-date { font-size: 0.85em; color: #777; }
    .btn-delete { background-color: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 6px; font-size: 0.9em; transition: 0.2s; }
    .btn-delete:hover { background-color: #c82333; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <a href="select_training_review.php" class="back-btn">← Back to Training List</a>

    <div class="summary-card">
        <div class="info-group">
            <h3><?= htmlspecialchars($training['courseName']); ?></h3>
            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($training['courseLocation'] ?? ''); ?>, <?= htmlspecialchars($training['courseCountry'] ?? ''); ?></p>
            <p><i class="fas fa-calendar-alt"></i> <?= $displayDate; ?></p>
            <p>
                <i class="fas fa-clock"></i> 
                <?php if(!empty($training['startTime']) && !empty($training['endTime'])): ?>
                    <?= date("h:i A", strtotime($training['startTime'] ?? '')) ?> - <?= date("h:i A", strtotime($training['endTime'] ?? '')) ?>
                <?php else: ?>
                    Time Not Set
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="review-header-controls">
        <h2>Reviews for This Training</h2>
        <div class="sort-controls">
            <label for="sortReviews">Sort By:</label>
            <select id="sortReviews" onchange="applySort()">
                <option value="date_desc" <?= $sort == 'date_desc' ? 'selected' : '' ?>>Date (Newest)</option>
                <option value="date_asc" <?= $sort == 'date_asc' ? 'selected' : '' ?>>Date (Oldest)</option>
                <option value="rating_desc" <?= $sort == 'rating_desc' ? 'selected' : '' ?>>Rating (Highest)</option>
                <option value="rating_asc" <?= $sort == 'rating_asc' ? 'selected' : '' ?>>Rating (Lowest)</option>
            </select>
        </div>
    </div>

    <?php if (!empty($message)) echo "<p class='success'>$message</p>"; ?>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

    <div class="review-grid">
        <?php if ($reviews->num_rows > 0): ?>
            <?php while ($row = $reviews->fetch_assoc()): ?>
            <div class="review-card">
                <div class="review-content">
                    <div class="review-header">
                        <span class="reviewer-name"><?= htmlspecialchars($row['userName']) ?></span>
                        <span class="rating-stars">
                            <?php 
                                echo str_repeat('⭐', $row['rating']);
                                echo str_repeat('☆', 5 - $row['rating']);
                            ?>
                        </span>
                    </div>
                    <p class="review-comment">"<?= nl2br(htmlspecialchars($row['comment'])) ?>"</p>
                </div>

                <div class="review-footer">
                    <span class="review-date">
                        Reviewed on: <?= date('M d, Y', strtotime($row['reviewDate'] ?? 'now')) ?>
                    </span>
                    <a href="review_training.php?courseID=<?= $courseID ?>&delete=<?= $row['reviewID'] ?>"
                       class="btn-delete"
                       onclick="return confirm('Are you sure you want to delete this review?');">
                        Delete Review
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No reviews found for this training yet.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    function applySort() {
        const selectElement = document.getElementById('sortReviews');
        const selectedSort = selectElement.value;
        const courseId = <?= $courseID ?>;
        window.location.href = `review_training.php?courseID=${courseId}&sort=${selectedSort}`;
    }
</script>
</body>
</html>