<?php
session_start();
require 'db.php';

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// Fetch schedules (manual notes) + registered events/courses
// Filtered to exclude 'withdrawn' status
$stmt = $conn->prepare("
    SELECT s.scheduleID, s.date AS start_date, s.date AS end_date, s.message, 'note' AS type
    FROM scheduling s
    WHERE s.userID = ?

    UNION

    SELECT NULL AS scheduleID, ev.startDate AS start_date, ev.endDate AS end_date, CONCAT('📌 Event: ', ev.eventName) AS message, 'event' AS type
    FROM eventregistration er
    JOIN event ev ON er.eventID = ev.eventID
    WHERE er.userID = ? AND er.registrationStatus != 'withdrawn'

    UNION

    SELECT NULL AS scheduleID, co.startDate AS start_date, co.endDate AS end_date, CONCAT('🎓 Course: ', co.courseName) AS message, 'course' AS type
    FROM courseregistration cr
    JOIN course co ON cr.courseID = co.courseID
    WHERE cr.userID = ? AND cr.registrationStatus != 'withdrawn'

    ORDER BY start_date ASC
");

$stmt->bind_param("iii", $userID, $userID, $userID);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $endDate = $row['end_date'];
    // FullCalendar 'end' date is exclusive for display, so we add 1 day for ranges
    if ($row['type'] !== 'note' && $row['start_date'] !== $row['end_date']) {
        $endDate = date('Y-m-d', strtotime($row['end_date'] . ' +1 day'));
    }

    $events[] = [
        "id" => $row['scheduleID'] ?? uniqid(),
        "title" => $row['message'],
        "start" => $row['start_date'],
        "end" => $endDate,
        "allDay" => true,
        "color" => ($row['type'] === 'event') ? '#4f46e5' : (($row['type'] === 'course') ? '#10b981' : '#f59e0b'),
        "extendedProps" => [
            "isNote" => ($row['type'] === 'note'),
            "scheduleID" => $row['scheduleID']
        ]
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Schedule</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <style>
        .main-content { max-width: 1200px; margin: 30px auto; padding: 20px; }
        .schedule-container { display: flex; gap: 20px; margin-top: 20px; }
        .calendar-box { flex: 2; }
        .notes-box { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        #calendar { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0px 2px 10px rgba(0,0,0,0.1); }
        .note-box { background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        .note-box textarea { width: 100%; height: 80px; border-radius: 5px; padding: 10px; margin-top: 8px; resize: none; border: 1px solid #ddd; box-sizing: border-box; }
        .save-note-btn { margin-top: 10px; background: #007BFF; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; width: 100%; font-weight: bold; }
        .selected-date { font-weight: bold; color: #333; margin-bottom: 6px; font-size: 1.1rem; }
        #notesList ul { padding: 0; list-style: none; margin-top: 10px; }
        #notesList li { background: #f8f9fa; padding: 12px; margin-bottom: 8px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid #f59e0b; font-size: 14px; }
        .note-delete-btn { background: #fee2e2; color: #b91c1c; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 12px; }
        .note-delete-btn:hover { background: #fca5a5; }
        @media (max-width: 900px) { .schedule-container { flex-direction: column; } }
    </style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="main-content">
    <h2 style="text-align:center;">📅 My Schedule</h2>

    <div class="schedule-container">
        <div class="calendar-box">
            <div id="calendar"></div>
        </div>

        <div class="notes-box">
            <div class="note-box">
                <h3>📝 Add Note</h3>
                <div id="selectedDateText" class="selected-date">Click a date on the calendar</div>
                <input type="hidden" id="noteDate">
                <textarea id="newNote" placeholder="Write your note..."></textarea>
                <button id="saveNoteBtn" class="save-note-btn" onclick="saveNote()">Save Note</button>
            </div>

            <div id="notesList" class="note-box" style="display:none;">
                <h3>📖 Items for this day</h3>
                <ul id="notesUl"></ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const noteTextarea = document.getElementById('newNote');
    const noteDateInput = document.getElementById('noteDate');
    const selectedDateText = document.getElementById('selectedDateText');
    const notesBox = document.getElementById('notesList');
    const notesUl = document.getElementById('notesUl');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        events: <?php echo json_encode($events); ?>,
        dateClick: function(info) {
            updateNotesDisplay(info.dateStr);
        }
    });
    calendar.render();

    function updateNotesDisplay(dateStr) {
        noteDateInput.value = dateStr;
        selectedDateText.innerText = "📅 Selected Date: " + dateStr;
        notesUl.innerHTML = "";

        const allEvents = calendar.getEvents();
        const filtered = allEvents.filter(ev => {
            const checkDate = new Date(dateStr).setHours(0,0,0,0);
            const startDate = new Date(ev.startStr).setHours(0,0,0,0);
            let endDate = ev.end ? new Date(ev.endStr).setHours(0,0,0,0) : startDate;
            
            if (ev.allDay && ev.end) {
                let d = new Date(ev.endStr);
                d.setDate(d.getDate() - 1);
                endDate = d.setHours(0,0,0,0);
            }
            return checkDate >= startDate && checkDate <= endDate;
        });

        if (filtered.length > 0) {
            notesBox.style.display = "block";
            filtered.forEach(ev => {
                const li = document.createElement("li");
                li.style.borderLeftColor = ev.backgroundColor || '#f59e0b';
                
                const text = document.createElement("span");
                text.innerHTML = ev.title;
                li.appendChild(text);

                if (ev.extendedProps.isNote) {
                    const delBtn = document.createElement("button");
                    delBtn.className = "note-delete-btn";
                    delBtn.textContent = "Delete";
                    delBtn.onclick = function() {
                        if (confirm("Delete this note?")) {
                            fetch("delete_schedule.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify({ scheduleID: ev.extendedProps.scheduleID })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    ev.remove();
                                    updateNotesDisplay(dateStr);
                                }
                            });
                        }
                    };
                    li.appendChild(delBtn);
                }
                notesUl.appendChild(li);
            });
        } else {
            notesBox.style.display = "none";
        }
    }

    window.saveNote = function() {
        const note = noteTextarea.value.trim();
        const date = noteDateInput.value;
        if (!date || !note) { alert("Please select a date and write a note."); return; }

        fetch("save_schedule.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ date: date, message: note })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                calendar.addEvent({ 
                    id: data.scheduleID, 
                    title: note, 
                    start: date, 
                    allDay: true,
                    color: '#f59e0b',
                    extendedProps: { isNote: true, scheduleID: data.scheduleID }
                });
                noteTextarea.value = "";
                updateNotesDisplay(date);
                alert("✅ Note saved!");
            }
        });
    };
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>