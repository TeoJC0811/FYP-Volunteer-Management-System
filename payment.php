<?php
session_start();
include("db.php");

// ✅ Check login
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}
$userID = $_SESSION['userID'];

// ✅ Validate course ID
if (!isset($_GET['courseID']) || !is_numeric($_GET['courseID'])) {
    die("⚠️ Invalid course ID.");
}
$courseID = intval($_GET['courseID']);

// Initialize variables
$successMessage = "";
$errorMessage = "";

/* ==========================
    ANTI-SABOTAGE / STRIKE CHECK
========================== */
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM courseregistration WHERE userID = ? AND courseID = ? AND registrationStatus = 'withdrawn'");
$countStmt->bind_param("ii", $userID, $courseID);
$countStmt->execute();
$withdrawCount = $countStmt->get_result()->fetch_assoc()['total'];

if ($withdrawCount >= 3) {
    die("❌ Registration Blocked: You have reached the withdrawal limit (3 strikes) for this course.");
}

/* ==========================
    FETCH COURSE + ORGANIZER
========================== */
$sql = "
    SELECT 
        c.courseID,
        c.courseName,
        c.courseLocation,
        c.courseCountry,
        c.startDate,
        c.endDate,
        c.startTime,
        c.endTime,
        c.deadline,
        c.fee,
        u.userName AS organizerName,
        u.qrCodeUrl
    FROM course c
    JOIN user u ON c.organizerID = u.userID
    WHERE c.courseID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $courseID);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    die("⚠️ Course not found.");
}

/* ==========================
    PREVENT DOUBLE REGISTRATION
========================== */
$checkReg = $conn->prepare("SELECT 1 FROM courseregistration WHERE userID = ? AND courseID = ? AND registrationStatus = 'active'");
$checkReg->bind_param("ii", $userID, $courseID);
$checkReg->execute();
if ($checkReg->get_result()->num_rows > 0) {
    $errorMessage = "⚠️ You are already registered for this course.";
}

/* ==========================
    HANDLE PAYMENT SUBMISSION
========================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($errorMessage)) {
    $uploadDir = "uploads/receipts/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!isset($_FILES["receipt"]) || $_FILES["receipt"]["error"] !== UPLOAD_ERR_OK) {
        $errorMessage = "⚠️ Please upload a valid receipt file.";
    } else {
        $fileExtension = strtolower(pathinfo($_FILES["receipt"]["name"], PATHINFO_EXTENSION));
        $newFileName = time() . "_" . $userID . "." . $fileExtension;
        $receiptPath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES["receipt"]["tmp_name"], $receiptPath)) {
            $regSQL = "INSERT INTO courseregistration (registrationDate, status, registrationStatus, userID, courseID) 
                       VALUES (NOW(), 'pending', 'active', ?, ?)
                       ON DUPLICATE KEY UPDATE registrationDate=NOW(), status='pending', registrationStatus='active'";
            $regStmt = $conn->prepare($regSQL);
            $regStmt->bind_param("ii", $userID, $courseID);

            if ($regStmt->execute()) {
                $courseRegisterID = $conn->insert_id ?: 0;
                if($courseRegisterID == 0) {
                    $findId = $conn->prepare("SELECT courseRegisterID FROM courseregistration WHERE userID = ? AND courseID = ?");
                    $findId->bind_param("ii", $userID, $courseID);
                    $findId->execute();
                    $courseRegisterID = $findId->get_result()->fetch_assoc()['courseRegisterID'];
                }

                $paySQL = "INSERT INTO coursepayment (paymentStatus, receiptImage, userID, courseRegisterID) VALUES ('pending', ?, ?, ?)";
                $payStmt = $conn->prepare($paySQL);
                $payStmt->bind_param("sii", $receiptPath, $userID, $courseRegisterID);
                $payStmt->execute();

                $conn->query("UPDATE course SET participantNum = participantNum + 1 WHERE courseID = $courseID");
                $successMessage = "Payment submitted successfully! Waiting for organizer approval.";
            } else {
                $errorMessage = "⚠️ Failed to register course.";
            }
        } else {
            $errorMessage = "⚠️ Failed to upload receipt.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment - <?= htmlspecialchars($course['courseName']) ?></title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ✅ Main Card Layout */
    .card-wrapper { 
        max-width: 650px; 
        margin: 40px auto; 
        padding: 30px; 
        background-color: #ffffff; 
        border-radius: 12px; 
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); 
        box-sizing: border-box;
    }

    .info-summary { 
        background-color: #fcfcfc; 
        border: 1px solid #f0f0f0; 
        border-radius: 10px; 
        padding: 20px; 
        margin-bottom: 25px; 
    }

    .info-item { 
        display: flex; 
        justify-content: space-between; 
        padding: 12px 0; 
        border-bottom: 1px solid #f5f5f5; 
    }

    .info-item:last-child { border-bottom: none; }
    .info-label { color: #666; font-weight: 600; font-size: 1rem; }
    .info-value { color: #333; text-align: right; font-size: 1rem; }
    .info-value.price { font-size: 1.2rem; font-weight: 800; color: #d9534f; } 

    /* ✅ Centered QR Section */
    .qr-section { text-align: center; margin-bottom: 25px; }
    #qr-image { 
        max-width: 220px; 
        border-radius: 12px; 
        margin: 15px auto; 
        display: block; 
        border: 1px solid #eee;
    }

    /* ✅ Button & Message Alignment Fix */
    .btn-full { 
        display: block;
        width: 100%; 
        padding: 15px; 
        background-color: #2575fc; 
        color: white !important; 
        border: none; 
        border-radius: 8px; 
        cursor: pointer; 
        font-weight: bold; 
        font-size: 1.1rem; 
        text-align: center;
        text-decoration: none;
        transition: 0.3s;
        box-sizing: border-box; /* ❗ Keeps button within container */
    }

    .btn-full:hover { background-color: #1a5ed8; transform: translateY(-1px); }

    .message { 
        width: 100%;
        padding: 15px; 
        border-radius: 8px; 
        margin-bottom: 15px; 
        text-align: center; 
        font-weight: 500;
        box-sizing: border-box; /* ❗ Keeps message within container */
    }
    .error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
    .success { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; display: flex; align-items: center; justify-content: center; gap: 10px; }

    .back-btn-top { text-decoration: none; color: white; background: #444; padding: 6px 12px; border-radius: 4px; font-size: 0.9rem; }
</style>
</head>
<body>
<?php include("user_navbar.php"); ?>
<div class="main-content">
    <div class="card-wrapper">
        <div class="header-row" style="display:flex; justify-content: space-between; align-items:center; margin-bottom:25px;">
            <a href="course_detail.php?id=<?= $courseID ?>" class="back-btn-top">← Back</a>
            <h1 style="margin:0; font-size: 1.8rem; flex-grow: 1; text-align: center;">Payment</h1>
            <div style="width: 60px;"></div> </div>

        <div class="info-summary">
            <div class="info-item">
                <span class="info-label">Course</span>
                <span class="info-value"><?= htmlspecialchars($course['courseName']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Location</span>
                <span class="info-value"><?= htmlspecialchars($course['courseLocation']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Date</span>
                <span class="info-value">
                    <?= date('d M', strtotime($course['startDate'])) ?> - <?= date('d M Y', strtotime($course['endDate'])) ?>
                </span>
            </div>
            <div class="info-item" style="border-top: 2px solid #eee; margin-top: 10px; padding-top: 15px;">
                <span class="info-label">Fee Amount</span>
                <span class="info-value price">RM<?= number_format($course['fee'], 2) ?></span>
            </div>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="message error"><?= $errorMessage ?></div>
            <a href="training_course.php" class="btn-full">Return to Courses</a>
        <?php elseif (!empty($successMessage)): ?>
            <div class="message success">
                <i class="fa-solid fa-square-check" style="font-size: 1.2rem; color: #38a169;"></i> 
                <?= $successMessage ?>
            </div>
            <a href="training_course.php" class="btn-full">Back to Courses</a>
        <?php else: ?>
            <div class="qr-section">
                <p style="color: #666;">Scan QR code below to pay organizer: <strong><?= htmlspecialchars($course['organizerName']) ?></strong></p>
                <?php if (!empty($course['qrCodeUrl'])): ?>
                    <img src="<?= htmlspecialchars($course['qrCodeUrl']) ?>" alt="QR" id="qr-image">
                <?php else: ?>
                    <p style="background:#fff3cd; padding:10px; border-radius:5px;">⚠️ QR code not available. Contact organizer.</p>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 25px;">
                    <label style="display:block; margin-bottom:10px; font-weight:bold; color: #444;">Upload Payment Receipt (JPG/PNG/PDF):</label>
                    <input type="file" name="receipt" accept="image/*,application/pdf" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; box-sizing: border-box; background: #fafafa;">
                </div>
                <button type="submit" class="btn-full">Confirm & Submit Payment</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>