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
// FILTERS & EVENT ID INITIALIZATION
// ============================
$searchQuery = $_GET['search'] ?? "";
$filterStatus = $_GET['status'] ?? "all";
$selectedEvent = isset($_GET['eventID']) ? intval($_GET['eventID']) : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['eventID'])) {
    $selectedEvent = intval($_POST['eventID']);
}

if ($selectedEvent <= 0) {
    header("Location: select_event_registration.php");
    exit();
}

/* =====================================================
    FETCH EVENT INFORMATION (INCLUDING TIME & TOKEN)
===================================================== */
$eventInfo = null;
$eventTitle = "Untitled Event"; 
$eventOrganizer = null;

// Fetch values from DB
$stmtEvent = $conn->prepare("
    SELECT eventName, startDate, endDate, startTime, endTime, eventLocation, organizerID, checkinToken 
    FROM event 
    WHERE eventID = ?
");
$stmtEvent->bind_param("i", $selectedEvent);
$stmtEvent->execute();
$stmtEvent->bind_result($eventTitle, $eventStart, $eventEnd, $startTime, $endTime, $eventLocation, $eventOrganizer, $checkinToken);
$stmtEvent->fetch();
$stmtEvent->close();

if (empty($eventTitle)) $eventTitle = "Untitled Event";

if ($role === "organizer" && $eventOrganizer != $userID) {
    die("❌ You are not authorized to manage this event.");
}

/* =====================================================
    QR CODE TOKEN GENERATION LOGIC
===================================================== */
if (empty($checkinToken)) {
    $checkinToken = bin2hex(random_bytes(32)); 
    $stmtUpdate = $conn->prepare("UPDATE event SET checkinToken = ? WHERE eventID = ?");
    $stmtUpdate->bind_param("si", $checkinToken, $selectedEvent);
    $stmtUpdate->execute();
    $stmtUpdate->close();
}

// Ensure times are strings to avoid Deprecation errors
$eventInfo = [
    'title'    => $eventTitle,
    'start'    => $eventStart,
    'end'      => $eventEnd,
    'startTime'=> $startTime ?? '00:00', // FIX: Ensure not null
    'endTime'  => $endTime ?? '00:00',   // FIX: Ensure not null
    'location' => $eventLocation,
    'token'    => $checkinToken 
];

/* =====================================================
    DELETE REGISTRATION
===================================================== */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM eventregistration WHERE eventRegisterID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "✅ Registration deleted successfully!";
    } else {
        $error = "❌ Error deleting registration.";
    }
    $stmt->close();
    header("Location: manage_event_registration.php?eventID={$selectedEvent}&status={$filterStatus}&search={$searchQuery}&message=" . urlencode($message) . "&error=" . urlencode($error));
    exit();
}

/* =====================================================
    UPDATE REGISTRATION STATUS
===================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {

    $id = intval($_POST['eventRegisterID']); 
    $newStatus = trim($_POST['status']);
    $eventID_from_form = intval($_POST['eventID']); 

    $stmt = $conn->prepare("SELECT status, userID, eventID FROM eventregistration WHERE eventRegisterID = ? AND eventID = ?");
    $stmt->bind_param("ii", $id, $eventID_from_form); 
    $stmt->execute();
    $stmt->bind_result($currentStatus, $participantID, $eventID_db);
    $stmt->fetch();
    $stmt->close();

    if (!$eventID_db) {
        $error = "❌ Registration not found.";
    } elseif ($currentStatus === "Completed" && $newStatus !== "Completed") {
        $error = "❌ Already completed. Cannot revert status.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE eventregistration SET status = ? WHERE eventRegisterID = ?");
            $stmt->bind_param("si", $newStatus, $id);
            $stmt->execute();
            $stmt->close();

            if ($newStatus === "Completed" && $currentStatus !== "Completed") {
                $stmtEventDetail = $conn->prepare("SELECT eventName, point FROM event WHERE eventID = ?");
                $stmtEventDetail->bind_param("i", $eventID_db);
                $stmtEventDetail->execute();
                $stmtEventDetail->bind_result($eventTitleForNotif, $pointsEarned);
                $stmtEventDetail->fetch();
                $stmtEventDetail->close();

                $notifMsg = "🎉 You successfully completed the event <b>{$eventTitleForNotif}</b> and earned {$pointsEarned} points!";

                $stmtNotif = $conn->prepare("INSERT INTO notification (message, activityType, activityID, userID, isRead, createdAt) VALUES (?, 'event', ?, ?, 0, NOW())");
                $stmtNotif->bind_param("sii", $notifMsg, $eventID_db, $participantID);
                $stmtNotif->execute();
                $stmtNotif->close();
            }

            $conn->commit();
            header("Location: manage_event_registration.php?eventID={$eventID_from_form}&status={$filterStatus}&search={$searchQuery}&message=" . urlencode("✅ Status updated successfully!"));
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
$sql = "SELECT er.eventRegisterID, er.registrationDate, er.status, u.userName FROM eventregistration er JOIN user u ON er.userID = u.userID WHERE er.eventID = ?";
$params = [$selectedEvent];
$types = "i";

if (!empty($searchQuery)) {
    $sql .= " AND u.userName LIKE ?";
    $params[] = "%" . $searchQuery . "%";
    $types .= "s";
}
if ($filterStatus !== "all") {
    $sql .= " AND er.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}
$sql .= " ORDER BY er.eventRegisterID DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Event Registrations</title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
.back-btn { display:inline-block; background:#4a4a4a; color:white; padding:8px 14px; border-radius:6px; text-decoration:none; margin-bottom:10px; }
.back-btn:hover { background:#2f2f2f; }
.search-filter-bar { text-align:center; margin:15px 0; }
.search-filter-bar input, .search-filter-bar select, .search-filter-bar button { padding:6px 12px; margin-right:5px; border:1px solid #ccc; border-radius:5px; background:#f0f0f0; cursor:pointer; }
.search-filter-bar a.btn { padding:6px 12px; margin-right:5px; border:1px solid #ccc; border-radius:5px; background:#f0f0f0; text-decoration:none; color: black; font-size: 14px; }

.qr-btn { background: #3498db; color: white !important; padding: 12px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; transition: 0.3s; }
.qr-btn:hover { background: #2980b9; transform: translateY(-2px); }

/* --- INFO CARD STYLES --- */
.event-info-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    padding: 25px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.event-info-group h3 { margin: 0 0 10px 0; font-size: 24px; color: #2c3e50; }
.event-info-group p { margin: 5px 0; color: #636e72; font-size: 15px; display: flex; align-items: center; }
.event-info-group i { color: #3498db; width: 25px; font-size: 1.1em; }
.action-group { text-align: center; border-left: 2px solid #f1f2f6; padding-left: 30px; }

.styled-table select { appearance: none; padding: 6px 8px; border-radius: 4px; font-weight: 500; min-width: 130px; border: 1px solid #ccc; cursor: pointer; background-color: #f8f8f8; color: #333; transition: all 0.2s; }
.btn-edit { background-color: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-delete { background-color: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: bold; }
.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6fb; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>
<div class="main-content">
    <a href="select_event_registration.php" class="back-btn">⬅ Back to Event List</a>
    <h2>Manage Event Registrations</h2>

    <?php if ($eventInfo): ?>
    <div class="event-info-card">
        <div class="event-info-group">
            <h3><?= htmlspecialchars($eventInfo['title'] ?? ''); ?></h3>
            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($eventInfo['location'] ?? ''); ?></p>
            <p><i class="fas fa-calendar-alt"></i> <?= date("d M Y", strtotime($eventInfo['start'] ?? 'now')); ?> – <?= date("d M Y", strtotime($eventInfo['end'] ?? 'now')); ?></p>
            <p><i class="fas fa-clock"></i> <?= date("h:i A", strtotime($eventInfo['startTime'] ?? '00:00')); ?> – <?= date("h:i A", strtotime($eventInfo['endTime'] ?? '00:00')); ?></p>
        </div>
        <div class="action-group">
            <a href="generate_qr.php?type=event&eventID=<?= $selectedEvent ?>&token=<?= $eventInfo['token'] ?>" target="_blank" class="qr-btn">
                <i class="fas fa-qrcode fa-lg"></i><br>
                <span>Generate QR Code</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="search-filter-bar">
        <form method="get">
            <input type="hidden" name="eventID" value="<?= $selectedEvent ?>">
            <input type="text" name="search" placeholder="Search user..." value="<?= htmlspecialchars($searchQuery) ?>">
            <select name="status">
                <option value="all" <?= $filterStatus=='all'?'selected':'' ?>>All</option>
                <option value="Pending" <?= $filterStatus=='Pending'?'selected':'' ?>>Pending</option>
                <option value="Completed" <?= $filterStatus=='Completed'?'selected':'' ?>>Completed</option>
                <option value="Cancelled" <?= $filterStatus=='Cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <a href="manage_event_registration.php?eventID=<?= $selectedEvent ?>" class="btn"><i class="fas fa-undo"></i> Clear</a>
        </form>
    </div>

    <table class="styled-table">
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Registered At</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <?php $currentStatus = $row['status'] ?: "Pending"; ?>
            <tr>
            <form method="post" action="manage_event_registration.php">
                <input type="hidden" name="eventID" value="<?= $selectedEvent ?>">
                <input type="hidden" name="eventRegisterID" value="<?= $row['eventRegisterID'] ?>">
                <td><?= $row['eventRegisterID'] ?></td>
                <td><?= htmlspecialchars($row['userName'] ?? '') ?></td>
                <td><?= $row['registrationDate'] ?></td>
                <td>
                    <?php if ($currentStatus === "Completed"): ?>
                        <span style="color: green; font-weight:bold;"> Completed</span>
                        <input type="hidden" name="status" value="Completed">
                    <?php else: ?>
                        <select name="status">
                            <option value="Pending" <?= $currentStatus=="Pending" ? 'selected' : '' ?>>Pending</option>
                            <option value="Completed" <?= $currentStatus=="Completed" ? 'selected' : '' ?>>Completed</option>
                            <option value="Cancelled" <?= $currentStatus=="Cancelled" ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($currentStatus !== "Completed"): ?>
                        <button type="submit" name="update" class="btn btn-edit"><i class="fas fa-save"></i> Update</button>
                    <?php endif; ?>
                    <a href="manage_event_registration.php?delete=<?= $row['eventRegisterID'] ?>&eventID=<?= $selectedEvent ?>" 
                        class="btn btn-delete" onclick="return confirm('Delete this registration?');">
                        <i class="fas fa-trash-alt"></i> Delete
                    </a>
                </td>
            </form>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5" style="text-align:center;">No registrations found for this event.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>