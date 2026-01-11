<?php
session_start();
include 'db.php';

// Dependencies
$fa_link = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">';
$swal_link = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];
$errorMessage = ""; 

// --- Get activity details ---
$type = $_GET['activityType'] ?? null;
$activityID = $_GET['activityID'] ?? null;

if (!in_array($type, ['event', 'course']) || !is_numeric($activityID)) {
    die("Invalid activity parameters.");
}
$activityID = intval($activityID);

// --- Lookup Activity ---
if ($type === 'event') {
    $stmt = $conn->prepare("
        SELECT er.eventRegisterID AS registerID, 
               e.eventName AS activityName, 
               e.description AS activityDescription
        FROM eventregistration er
        JOIN event e ON er.eventID = e.eventID
        WHERE e.eventID = ? AND er.userID = ? AND er.status = 'Completed'
    ");
} else {
    $stmt = $conn->prepare("
        SELECT cr.courseRegisterID AS registerID, 
               c.courseName AS activityName, 
               c.description AS activityDescription
        FROM courseregistration cr
        JOIN course c ON cr.courseID = c.courseID
        WHERE c.courseID = ? AND cr.userID = ? AND cr.status = 'Completed'
    ");
}
$stmt->bind_param("ii", $activityID, $userID);
$stmt->execute();
$activityData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$activityData) {
    die("You must complete this activity before reviewing.");
}

$activityName = $activityData['activityName'];
$activityDescription = $activityData['activityDescription'] ?? "No description available.";

// --- Check for existing review ---
$stmt = $conn->prepare("
    SELECT reviewID, rating, comment
    FROM review 
    WHERE userID = ? AND activityType = ? AND activityID = ?
");
$stmt->bind_param("isi", $userID, $type, $activityID);
$stmt->execute();
$existingReview = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isUpdate = !empty($existingReview);
$initialRating = $existingReview['rating'] ?? 0;
$initialComment = $existingReview['comment'] ?? "";

// --- Handle submission ---
if (isset($_POST['submitReview'])) {
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? "");

    // Validation: Require both Star Rating and Comment
    if ($rating < 1 || empty($comment)) {
        $errorMessage = "❌ Please provide both a star rating and a detailed comment before submitting.";
        $initialRating = $rating;
        $initialComment = $comment;
    } else {
        if ($isUpdate) {
            // Update existing review silently in the background
            $stmt = $conn->prepare("
                UPDATE review SET rating = ?, comment = ?, reviewDate = NOW()
                WHERE reviewID = ?
            ");
            $stmt->bind_param("isi", $rating, $comment, $existingReview['reviewID']);
        } else {
            // Insert new review
            $stmt = $conn->prepare("
                INSERT INTO review (rating, comment, reviewDate, userID, activityType, activityID)
                VALUES (?, ?, NOW(), ?, ?, ?)
            ");
            $stmt->bind_param("isssi", $rating, $comment, $userID, $type, $activityID);
        }
        $stmt->execute();
        $stmt->close();

        // Redirect to success state to trigger the pop-up
        header("Location: leave_review.php?activityType=$type&activityID=$activityID&success=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leave Review: <?= htmlspecialchars($activityName) ?></title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<?= $fa_link ?>
<?= $swal_link ?>

<style>
/* ================= GLOBAL STYLES ================= */
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    padding: 0; 
    background-color: #f4f7f9;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
.main-content {
    flex: 1;
    padding: 40px 20px; 
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

/* ================= FIXED SIZE CONTAINER ================= */
.review-container {
    width: 1100px; 
    min-width: 1100px;
    background: white;
    padding: 40px; 
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    box-sizing: border-box;
}

h2 {
    color: #2c3e50; 
    margin-top: 20px; 
    border-bottom: 2px solid #ecf0f1; 
    padding-bottom: 15px; 
    margin-bottom: 0; 
    font-size: 1.8rem;
    font-weight: 700;
}

.back-btn {
    display: inline-block; 
    background: #95a5a6;
    color: white;
    padding: 8px 18px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.95em;
    font-weight: 600;
    margin-left: 100px;
}
.back-btn:hover { background: #7f8c8d; }

.review-form-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 40px;
    margin-top: 30px;
    margin-bottom: 20px;
}

.left-panel {
    padding-right: 20px;
    border-right: 1px solid #e0e0e0;
}

.activity-info-box {
    background: #fafafa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border: 1px solid #eee;
}

.activity-name-header {
    font-size: 1.2rem;
    font-weight: 700;
    color: #34495e;
    margin-bottom: 10px;
}

.activity-info-box em {
    display: block;
    font-style: normal;
    color: #7f8c8d;
    font-size: 0.95rem;
    line-height: 1.6;
}

.rating-section {
    padding-top: 20px;
    text-align: center;
}

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    font-size: 2.8rem;
    gap: 8px;
    padding: 15px 0;
}
.star-rating input { display:none; }
.star-rating label { cursor:pointer; color:#ccc; transition: color 0.2s; }
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label {
    color:#f39c12;
}

label.form-label { 
    display: block; 
    font-weight: bold; 
    margin-bottom: 10px; 
    color: #34495e; 
    font-size: 1.1rem;
}

/* ================= COMMENT BOX (FIXED) ================= */
textarea { 
    width: 100%; 
    min-height: 250px; 
    padding: 15px; 
    border-radius: 8px; 
    border: 1px solid #bdc3c7; 
    resize: none; 
    box-sizing: border-box; 
    font-size: 1rem;
}

.button-footer {
    padding-top: 25px;
    text-align: right;
}

button { 
    background: #2ecc71; 
    color: white; 
    border: none; 
    padding: 14px 35px; 
    border-radius: 8px; 
    font-weight: bold; 
    font-size: 1rem;
    cursor: pointer; 
    transition: 0.3s; 
}
button:hover { background: #27ae60; transform: translateY(-2px); }

.alert-error {
    padding: 15px;
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6fb;
    border-radius: 8px;
    margin: 20px 0;
    text-align: center;
    font-weight: 600;
}

@media (max-width: 1150px) {
    .review-container { width: 95%; min-width: unset; }
    .review-form-grid { grid-template-columns: 1fr; }
    .left-panel { border-right: none; padding-right: 0; border-bottom: 1px solid #eee; }
}
</style>
</head>

<body>

<?php include 'user_navbar.php'; ?>

<div class="main-content review-page">
    <div class="review-container">

        <a href="history.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>

        <h2>
            <i class="fas fa-comment-dots" style="color:#007bff;"></i> 
            Leave Feedback for <?= htmlspecialchars($activityName) ?>
        </h2>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="review-form-grid">
                
                <div class="left-panel">
                    <div class="activity-info-box">
                        <div class="activity-name-header">
                            <?= ucfirst($type) ?> Details
                        </div>
                        <p style="font-weight:600; margin-bottom:5px; color:#2c3e50;"><?= htmlspecialchars($activityName) ?></p>
                        <em><?= nl2br(htmlspecialchars($activityDescription)) ?></em>
                    </div>
                    
                    <div class="rating-section">
                        <label class="form-label">Your Rating <span style="color:red">*</span></label>
                        <div class="star-rating">
                            <?php for ($i=5; $i>=1; $i--): ?>
                            <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>"
                                <?= $initialRating == $i ? 'checked' : '' ?>>
                            <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                
                <div class="right-panel">
                    <label class="form-label">Detailed Comment <span style="color:red">*</span></label>
                    <textarea name="comment" placeholder="Tell us what you liked or how we can improve..."><?= htmlspecialchars($initialComment) ?></textarea>
                    
                    <div class="button-footer">
                        <button type="submit" name="submitReview">
                            <i class="fas fa-save"></i> Submit Review
                        </button>
                    </div>
                </div>
                
            </div>
        </form>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

<?php if (isset($_GET['success'])): ?>
<script>
    Swal.fire({
        title: 'Success!',
        text: 'Review submitted successfully.',
        icon: 'success',
        confirmButtonText: 'Confirm',
        confirmButtonColor: '#2ecc71'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'history.php';
        }
    });
</script>
<?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>