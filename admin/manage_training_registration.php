<?php
session_start();
include("../db.php");

// Allow only admin/organizer
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];
$role = $_SESSION['role'];
$message = $error = "";

// ============================
// FILTERS & COURSE ID INITIALIZATION
// ============================
$searchQuery = $_GET['search'] ?? "";
$filterStatus = $_GET['status'] ?? "all";
$selectedCourse = isset($_GET['courseID']) ? intval($_GET['courseID']) : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['courseID'])) {
    $selectedCourse = intval($_POST['courseID']);
}

if ($selectedCourse <= 0) {
    header("Location: select_training_registration.php");
    exit();
}

/* =====================================================
    FETCH COURSE INFO (INCLUDING NEW DATE COLUMNS)
===================================================== */
$stmtCourse = $conn->prepare("
    SELECT courseName, courseLocation, startDate, endDate, startTime, endTime, organizerID, checkinToken 
    FROM course
    WHERE courseID = ?
");
$stmtCourse->bind_param("i", $selectedCourse);
$stmtCourse->execute();
$stmtCourse->bind_result($courseName, $courseLocation, $startDate, $endDate, $startTime, $endTime, $courseOrganizer, $checkinToken);
$stmtCourse->fetch();
$stmtCourse->close();

if (empty($courseName)) $courseName = "Untitled Course";

if ($role === "organizer" && $courseOrganizer != $userID) {
    die("❌ You are not authorized to manage this course.");
}

/* =====================================================
    QR CODE TOKEN GENERATION LOGIC
===================================================== */
if (empty($checkinToken)) {
    $checkinToken = bin2hex(random_bytes(32)); 
    $stmtUpdate = $conn->prepare("UPDATE course SET checkinToken = ? WHERE courseID = ?");
    $stmtUpdate->bind_param("si", $checkinToken, $selectedCourse);
    $stmtUpdate->execute();
    $stmtUpdate->close();
}

// Logic for Date Display
$startDisp = date("d M Y", strtotime($startDate));
$endDisp = date("d M Y", strtotime($endDate));
$dateText = ($startDate === $endDate) ? $startDisp : "$startDisp - $endDisp";

$courseInfo = [
    "title" => $courseName,
    "location" => $courseLocation,
    "dateText" => $dateText,
    "start" => $startTime,
    "end" => $endTime,
    "token" => $checkinToken 
];

/* =====================================================
    DELETE REGISTRATION
===================================================== */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM courseregistration WHERE courseRegisterID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "✅ Registration deleted successfully!";
    } else {
        $error = "❌ Error deleting registration.";
    }
    $stmt->close();
    header("Location: manage_training_registration.php?courseID={$selectedCourse}&status={$filterStatus}&search={$searchQuery}&message=" . urlencode($message));
    exit();
}

/* =====================================================
    UPDATE REGISTRATION & PAYMENT (WITH NOTIFICATIONS)
===================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update'])) {
    $id = intval($_POST['courseRegisterID']);
    $newStatus = trim($_POST['status']);
    $paymentStatus = $_POST['paymentStatus'] ?? "";
    $courseID_from_form = intval($_POST['courseID']);

    // Auto-Cancel if payment is rejected
    if ($paymentStatus === 'rejected') {
        $newStatus = 'Cancelled';
    }

    $stmt = $conn->prepare("
        SELECT cr.status, cr.userID, COALESCE(cp.paymentStatus, 'pending') 
        FROM courseregistration cr 
        LEFT JOIN coursepayment cp ON cr.courseRegisterID = cp.courseRegisterID 
        WHERE cr.courseRegisterID = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($currentStatus, $participantID, $currentPaymentStatus);
    $stmt->fetch();
    $stmt->close();

    // Safety check removed to allow reverting status if needed. 
    // Only check if marking as completed without approved payment.
    if ($newStatus === "Completed" && $paymentStatus !== "approved") { 
        $error = "❌ Cannot mark as Completed until payment is Approved.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE courseregistration SET status = ? WHERE courseRegisterID = ?");
            $stmt->bind_param("si", $newStatus, $id);
            $stmt->execute();
            $stmt->close();

            if ($paymentStatus) {
                $check = $conn->prepare("SELECT coursePaymentID FROM coursepayment WHERE courseRegisterID = ?");
                $check->bind_param("i", $id);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $stmt2 = $conn->prepare("UPDATE coursepayment SET paymentStatus = ? WHERE courseRegisterID = ?");
                    $stmt2->bind_param("si", $paymentStatus, $id);
                } else {
                    $stmt2 = $conn->prepare("INSERT INTO coursepayment (paymentStatus, userID, courseRegisterID) VALUES (?, ?, ?)");
                    $stmt2->bind_param("sii", $paymentStatus, $participantID, $id);
                }
                $stmt2->execute();
                $stmt2->close();
                $check->close();

                if ($paymentStatus !== $currentPaymentStatus) {
                    $pNotifMsg = "";
                    if ($paymentStatus === 'approved') {
                        $pNotifMsg = "💳 Your payment for the course <b>{$courseName}</b> has been <b>Approved</b>.";
                    } elseif ($paymentStatus === 'rejected') {
                        $pNotifMsg = "❌ Your payment for the course <b>{$courseName}</b> was <b>Rejected</b>. Your attendance is now <b>Cancelled</b>.";
                    }

                    if ($pNotifMsg !== "") {
                        $stmtPNotif = $conn->prepare("INSERT INTO Notification (message, activityType, activityID, userID, isRead, createdAt) VALUES (?, 'course', ?, ?, 0, NOW())");
                        $stmtPNotif->bind_param("sii", $pNotifMsg, $courseID_from_form, $participantID);
                        $stmtPNotif->execute();
                        $stmtPNotif->close();
                    }
                }
            }

            if ($newStatus === "Completed" && $currentStatus !== "Completed") {
                $notifMsg = "🎉 You successfully completed the course <b>{$courseName}</b>!";
                $stmtNotif = $conn->prepare("INSERT INTO Notification (message, activityType, activityID, userID, isRead, createdAt) VALUES (?, 'course', ?, ?, 0, NOW())");
                $stmtNotif->bind_param("sii", $notifMsg, $courseID_from_form, $participantID); 
                $stmtNotif->execute();
                $stmtNotif->close();
            }

            $conn->commit();
            header("Location: manage_training_registration.php?courseID={$courseID_from_form}&status={$filterStatus}&search={$searchQuery}&message=" . urlencode("✅ Updated successfully!"));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['message'])) $message = htmlspecialchars(urldecode($_GET['message']));
if (isset($_GET['error'])) $error = htmlspecialchars(urldecode($_GET['error']));

/* =====================================================
    FETCH REGISTRATION LIST
===================================================== */
$sql = "SELECT cr.courseRegisterID, cr.registrationDate, cr.status, u.userName, cp.paymentStatus, cp.receiptImage
        FROM courseregistration cr
        JOIN user u ON cr.userID = u.userID
        LEFT JOIN coursepayment cp ON cr.courseRegisterID = cp.courseRegisterID
        WHERE cr.courseID = ?";
$params = [$selectedCourse];
$types = "i";

if (!empty($searchQuery)) {
    $sql .= " AND u.userName LIKE ?";
    $params[] = "%$searchQuery%";
    $types .= "s";
}
if ($filterStatus !== "all") {
    $sql .= " AND cr.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}
$sql .= " ORDER BY cr.courseRegisterID DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Training Registrations</title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
.back-btn { display:inline-block; background:#4a4a4a; color:white; padding:10px 18px; border-radius:6px; text-decoration:none; margin-bottom:15px; transition: 0.3s; }
.back-btn:hover { background:#2f2f2f; }
.search-filter-bar { text-align:center; margin:15px 0; }
.search-filter-bar input, .search-filter-bar select, .search-filter-bar button { padding:6px 12px; margin-right:5px; border:1px solid #ccc; border-radius:5px; background:#f0f0f0; cursor:pointer; }
.search-filter-bar a.btn { padding:6px 12px; margin-right:5px; border:1px solid #ccc; border-radius:5px; background:#f0f0f0; text-decoration:none; color: black; font-size: 14px; }
.qr-btn { background: #3498db; color: white !important; padding: 12px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; transition: 0.3s; text-align: center; }
.qr-btn:hover { background: #2980b9; transform: translateY(-2px); }
.training-info-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
.training-info-group h3 { margin: 0 0 10px 0; font-size: 24px; color: #2c3e50; }
.training-info-group p { margin: 5px 0; color: #636e72; font-size: 15px; display: flex; align-items: center; }
.training-info-group i { color: #3498db; width: 25px; font-size: 1.1em; }
.action-group { text-align: center; border-left: 2px solid #f1f2f6; padding-left: 30px; }
.btn-view-receipt { background-color: #343a40; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 0.9em; font-weight: bold; }
.styled-table select { padding: 6px 8px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; background-color: #f8f8f8; }
.btn-edit { background-color: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-delete { background-color: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-weight: bold; }
.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6fb; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>
<div class="main-content">
<a href="select_training_registration.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Training List</a>

<h2>Manage Training Registrations</h2>

<?php if ($courseInfo): ?>
<div class="training-info-card">
    <div class="training-info-group">
        <h3><?= htmlspecialchars($courseInfo['title']); ?></h3>
        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($courseInfo['location']); ?></p>
        <p><i class="fas fa-calendar-alt"></i> <?= $courseInfo['dateText']; ?></p>
        <p><i class="fas fa-clock"></i> <?= date("h:i A", strtotime($courseInfo['start'])); ?> - <?= date("h:i A", strtotime($courseInfo['end'])); ?></p>
    </div>
    <div class="action-group">
        <a href="generate_qr.php?type=course&courseID=<?= $selectedCourse ?>&token=<?= $courseInfo['token'] ?>" target="_blank" class="qr-btn">
            <i class="fas fa-qrcode fa-lg"></i><br>
            <span>Generate QR Code</span>
        </a>
    </div>
</div>
<?php endif; ?>

<?php if ($message) echo "<p class='success'>$message</p>"; ?>
<?php if ($error) echo "<p class='error'>$error</p>"; ?>

<div class="search-filter-bar">
<form method="get">
    <input type="hidden" name="courseID" value="<?= $selectedCourse ?>">
    <input type="text" name="search" placeholder="Search user..." value="<?= htmlspecialchars($searchQuery) ?>">
    <select name="status">
        <option value="all" <?= $filterStatus=='all'?'selected':'' ?>>All Status</option>
        <option value="Pending" <?= $filterStatus=='Pending'?'selected':'' ?>>Pending</option>
        <option value="Completed" <?= $filterStatus=='Completed'?'selected':'' ?>>Completed</option>
        <option value="Cancelled" <?= $filterStatus=='Cancelled'?'selected':'' ?>>Cancelled</option>
    </select>
    <button type="submit"><i class="fas fa-search"></i> Search</button>
    <a href="manage_training_registration.php?courseID=<?= $selectedCourse ?>" class="btn"><i class="fas fa-undo"></i> Clear</a>
</form>
</div>

<table class="styled-table">
<tr>
    <th>ID</th>
    <th>User</th>
    <th>Registered At</th>
    <th>Receipt</th>
    <th>Payment</th>
    <th>Status</th>
    <th>Actions</th>
</tr>

<?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php 
            $curStatus = $row['status'] ?: "Pending"; 
            $curPayStatus = $row['paymentStatus'] ?: "pending";
        ?>
        <tr>
        <form method="post">
            <input type="hidden" name="courseID" value="<?= $selectedCourse ?>">
            <input type="hidden" name="courseRegisterID" value="<?= $row['courseRegisterID'] ?>">

            <td><?= $row['courseRegisterID'] ?></td>
            <td><?= htmlspecialchars($row['userName']) ?></td>
            <td><?= $row['registrationDate'] ?></td>

            <td>
                <?php if (!empty($row['receiptImage'])): ?>
                    <a href="/servetogether/<?= htmlspecialchars($row['receiptImage']); ?>" target="_blank" class="btn-view-receipt">
                        <i class="fas fa-file-invoice"></i> View Receipt
                    </a>
                <?php else: ?>
                    <span style="color: #6c757d; font-style: italic;">No Receipt</span>
                <?php endif; ?>
            </td>

            <td>
                <?php if ($curPayStatus === 'approved'): ?>
                    <span style="color:green;font-weight:bold;"><i class="fas fa-check-circle"></i> Approved</span>
                    <input type="hidden" name="paymentStatus" value="approved">
                <?php elseif ($curPayStatus === 'rejected'): ?>
                    <span style="color:red;font-weight:bold;"><i class="fas fa-times-circle"></i> Rejected</span>
                    <input type="hidden" name="paymentStatus" value="rejected">
                <?php else: ?>
                    <select name="paymentStatus">
                        <option value="pending" <?= $curPayStatus=='pending'?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $curPayStatus=='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $curPayStatus=='rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                <?php endif; ?>
            </td>

            <td>
                <?php if ($curStatus === "Completed"): ?>
                    <span style="color:green;font-weight:bold;">Completed</span>
                    <input type="hidden" name="status" value="Completed">
                <?php elseif ($curStatus === "Cancelled"): ?>
                    <span style="color:red;font-weight:bold;">Cancelled</span>
                    <input type="hidden" name="status" value="Cancelled">
                <?php else: ?>
                    <select name="status">
                        <option value="Pending" <?= $curStatus=='Pending'?'selected':'' ?>>Pending</option>
                        <option value="Completed" <?= $curStatus=='Completed'?'selected':'' ?>>Completed</option>
                        <option value="Cancelled" <?= $curStatus=='Cancelled'?'selected':'' ?>>Cancelled</option>
                    </select>
                <?php endif; ?>
            </td>

            <td>
                <button type="submit" name="update" class="btn btn-edit"><i class="fas fa-save"></i> Update</button>
                <a href="manage_training_registration.php?delete=<?= $row['courseRegisterID'] ?>&courseID=<?= $selectedCourse ?>"
                   class="btn btn-delete" onclick="return confirm('Delete this registration?');">
                    <i class="fas fa-trash-alt"></i> Delete
                </a>
            </td>
        </form>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr><td colspan="7" style="text-align:center;">No registrations found for this training course.</td></tr>
<?php endif; ?>
</table>
</div>
</body>
</html>