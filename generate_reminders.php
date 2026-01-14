<?php
// generate_reminders.php
include("db.php");

// Get today's date and +7 days
$today = date("Y-m-d");
$nextWeek = date("Y-m-d", strtotime("+7 days"));

// --- 1. Upcoming Events ---
// (Removed template table; use eventName instead)
$sql = "
    SELECT er.userID, ev.eventID, ev.startDate, ev.eventName
    FROM eventregistration er
    JOIN event ev ON er.eventID = ev.eventID
    WHERE ev.startDate BETWEEN ? AND ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $today, $nextWeek);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- 2. Upcoming Courses ---
// (Removed template table; use courseName instead)
$sql = "
    SELECT cr.userID, co.courseID, co.courseDate, co.courseName
    FROM courseregistration cr
    JOIN course co ON cr.courseID = co.courseID
    WHERE co.courseDate BETWEEN ? AND ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $today, $nextWeek);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Insert Event Reminders ---
foreach ($events as $ev) {
    $message = "📌 Reminder: Event '" . $ev['eventName'] . "' is happening on " . $ev['startDate'];

    // Avoid duplicate reminders
    $check = $conn->prepare("SELECT COUNT(*) FROM notification WHERE userID=? AND activityID=? AND message=?");
    $check->bind_param("iis", $ev['userID'], $ev['eventID'], $message);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count == 0) {
        $insert = $conn->prepare("INSERT INTO notification (message, userID, activityID, scheduleID) VALUES (?, ?, ?, NULL)");
        $insert->bind_param("sii", $message, $ev['userID'], $ev['eventID']);
        $insert->execute();
        $insert->close();
    }
}

// --- Insert Course Reminders ---
foreach ($courses as $co) {
    $message = "🎓 Reminder: Course '" . $co['courseName'] . "' is happening on " . $co['courseDate'];

    $check = $conn->prepare("SELECT COUNT(*) FROM notification WHERE userID=? AND activityID=? AND message=?");
    $check->bind_param("iis", $co['userID'], $co['courseID'], $message);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count == 0) {
        $insert = $conn->prepare("INSERT INTO notification (message, userID, activityID, scheduleID) VALUES (?, ?, ?, NULL)");
        $insert->bind_param("sii", $message, $co['userID'], $co['courseID']);
        $insert->execute();
        $insert->close();
    }
}

echo "✅ Reminders generated successfully.\n";
?>
