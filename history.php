<?php 
session_start();
include("db.php");

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// Function to format dates
function formatDateRange($start, $end = null) {
    if (!$start) return "Date not set";
    $startDate = new DateTime($start);
    if ($end && $start !== $end) {
        $endDate = new DateTime($end);
        if ($startDate->format('Y') === $endDate->format('Y')) {
            return $startDate->format('d M') . ' — ' . $endDate->format('d M Y');
        }
        return $startDate->format('d M Y') . ' — ' . $endDate->format('d M Y');
    }
    return $startDate->format('d M Y'); 
}

// ===================== EVENT HISTORY =====================
$sqlEvents = "
SELECT 
    er.eventRegisterID AS registerID,
    e.eventID AS activityID,
    e.eventName AS activityName,
    e.eventLocation AS location,
    e.description,
    e.startDate,
    e.endDate,
    e.coverImage,
    r.reviewID
FROM EventRegistration er
JOIN Event e ON er.eventID = e.eventID
LEFT JOIN Review r 
    ON r.activityType = 'event'
    AND r.activityID = e.eventID
    AND r.userID = er.userID
WHERE er.userID = ?
    AND e.endDate < CURDATE()
    AND er.status = 'Completed'
ORDER BY e.startDate DESC
";

$stmt = $conn->prepare($sqlEvents);
$stmt->bind_param("i", $userID);
$stmt->execute();
$eventHistory = $stmt->get_result();

// ===================== COURSE HISTORY =====================
// UPDATED: Changed courseDate to startDate and included endDate
$sqlCourses = "
SELECT 
    cr.courseRegisterID AS registerID,
    c.courseID AS activityID,
    c.courseName AS activityName,
    c.courseLocation AS location,
    c.description,
    c.startDate,
    c.endDate,
    c.coverImage,
    r.reviewID
FROM CourseRegistration cr
JOIN Course c ON cr.courseID = c.courseID
LEFT JOIN Review r 
    ON r.activityType = 'course'
    AND r.activityID = c.courseID
    AND r.userID = cr.userID
WHERE cr.userID = ?
    AND c.endDate < CURDATE()
    AND cr.status = 'Completed'
ORDER BY c.startDate DESC
";

$stmt2 = $conn->prepare($sqlCourses);
$stmt2->bind_param("i", $userID);
$stmt2->execute();
$trainingHistory = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My History | Journey</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #4f46e5;
            --bg: #f9fafb;
            --card-bg: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --accent-green: #10b981;
            --accent-yellow: #f59e0b;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg); color: var(--text-main); margin: 0; line-height: 1.5; }
        .main-content { padding: 60px 20px; max-width: 1100px; margin: 0 auto; min-height: 80vh; }
        
        h2 { font-size: 2rem; font-weight: 800; letter-spacing: -0.025em; margin-bottom: 30px; }

        .tabs { 
            display: flex; background: #e5e7eb; padding: 4px; border-radius: 12px; 
            width: fit-content; margin: 0 auto 32px auto; border: none;
        }
        .tabs button {
            padding: 10px 28px; border: none; cursor: pointer; background: transparent;
            border-radius: 8px; font-weight: 600; font-size: 0.95rem; color: var(--text-muted); transition: 0.2s;
        }
        .tabs button.active { background: white; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

        .history-grid { 
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; 
        }

        .history-card { 
            background: var(--card-bg); border-radius: 16px; overflow: hidden; 
            box-shadow: var(--shadow); display: flex; flex-direction: column; 
            transition: transform 0.3s ease; border: 1px solid var(--border);
        }
        .history-card:hover { transform: translateY(-8px); }

        .img-wrapper { position: relative; width: 100%; height: 180px; overflow: hidden; }
        .card-img { width: 100%; height: 100%; object-fit: cover; }
        
        .card-content { padding: 24px; flex: 1; display: flex; flex-direction: column; }
        .card-title { 
            font-size: 1.15rem; font-weight: 700; color: var(--text-main); 
            margin-bottom: 8px; line-height: 1.3;
        }
        
        .card-description {
            font-size: 0.88rem; color: var(--text-muted); margin-bottom: 15px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden; text-overflow: ellipsis; line-height: 1.5;
        }

        .card-body p { margin: 6px 0; font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
        .card-body i { color: var(--primary); width: 14px; }

        .card-actions { 
            margin-top: auto; padding-top: 16px; display: flex; gap: 10px; 
            border-top: 1px solid var(--border); align-items: center; justify-content: space-between;
        }
        
        .btn-cert, .btn-review, .reviewed-text {
            flex: 0 0 48%; padding: 10px 0; border-radius: 10px; text-decoration: none;
            font-weight: 600; font-size: 0.82rem; text-align: center;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            box-sizing: border-box;
        }

        .btn-cert { border: 1.5px solid var(--accent-green); color: var(--accent-green); transition: 0.2s; }
        .btn-cert:hover { background: var(--accent-green); color: white; }

        .btn-review { background: var(--accent-yellow); color: #000; transition: 0.2s; }
        .btn-review:hover { background: #d97706; color: white; }

        .reviewed-text { color: var(--accent-green); cursor: default; }

        .empty-state { 
            grid-column: 1 / -1; text-align: center; padding: 60px; 
            background: white; border-radius: 16px; border: 2px dashed var(--border); 
            color: var(--text-muted); 
        }
    </style>
</head>

<body>

<?php include("user_navbar.php"); ?>

<div class="main-content">
    <h2>My History</h2>

    <div class="tabs">
        <button id="btn-event" class="active" onclick="showTab('event', this)">Events</button>
        <button id="btn-training" onclick="showTab('training', this)">Courses</button>
    </div>

    <div id="event">
        <div class="history-grid">
            <?php if ($eventHistory->num_rows > 0): ?>
            <?php while ($row = $eventHistory->fetch_assoc()): ?>
                <div class="history-card">
                    <div class="img-wrapper">
                        <img src="uploads/event_cover/<?= htmlspecialchars($row['coverImage'] ?? '') ?>" alt="Cover" class="card-img" onerror="this.src='images/default.jpg';">
                    </div>
                    
                    <div class="card-content">
                        <div class="card-title"><?= htmlspecialchars($row['activityName']) ?></div>
                        
                        <div class="card-description">
                            <?= htmlspecialchars(strip_tags($row['description'])) ?>
                        </div>

                        <div class="card-body">
                            <p><i class="fas fa-location-dot"></i> <?= htmlspecialchars($row['location']) ?></p>
                            <p><i class="fas fa-calendar-days"></i> <?= formatDateRange($row['startDate'], $row['endDate']) ?></p>
                        </div>
                        
                        <div class="card-actions">
                            <a class="btn-cert" target="_blank" href="generate_certificate.php?eventRegisterID=<?= $row['registerID'] ?>&userID=<?= $userID ?>">
                                <i class="fas fa-file-export"></i> Certificate
                            </a>
                            <?php if (!$row['reviewID']): ?>
                                <a class="btn-review" href="leave_review.php?activityType=event&activityID=<?= $row['activityID'] ?>">
                                    <i class="fas fa-star"></i> Review
                                </a>
                            <?php else: ?>
                                <span class="reviewed-text"><i class="fas fa-circle-check"></i> Reviewed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="empty-state">No completed events found in your history.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="training" style="display:none;">
        <div class="history-grid">
            <?php if ($trainingHistory->num_rows > 0): ?>
            <?php while ($row = $trainingHistory->fetch_assoc()): ?>
                <div class="history-card">
                    <div class="img-wrapper">
                        <img src="uploads/course_cover/<?= htmlspecialchars($row['coverImage'] ?? '') ?>" alt="Cover" class="card-img" onerror="this.src='images/default.jpg';">
                    </div>
                    
                    <div class="card-content">
                        <div class="card-title"><?= htmlspecialchars($row['activityName']) ?></div>
                        
                        <div class="card-description">
                            <?= htmlspecialchars(strip_tags($row['description'])) ?>
                        </div>

                        <div class="card-body">
                            <p><i class="fas fa-location-dot"></i> <?= htmlspecialchars($row['location']) ?></p>
                            <p><i class="fas fa-calendar-days"></i> <?= formatDateRange($row['startDate'], $row['endDate']) ?></p>
                        </div>
                        
                        <div class="card-actions">
                            <a class="btn-cert" target="_blank" href="generate_certificate.php?courseRegisterID=<?= $row['registerID'] ?>&userID=<?= $userID ?>">
                                <i class="fas fa-file-export"></i> Certificate
                            </a>
                            <?php if (!$row['reviewID']): ?>
                                <a class="btn-review" href="leave_review.php?activityType=course&activityID=<?= $row['activityID'] ?>">
                                    <i class="fas fa-star"></i> Review
                                </a>
                            <?php else: ?>
                                <span class="reviewed-text"><i class="fas fa-circle-check"></i> Reviewed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="empty-state">No completed courses found in your history.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function showTab(tabId, btn) {
    document.getElementById('event').style.display = tabId === 'event' ? 'block' : 'none';
    document.getElementById('training').style.display = tabId === 'training' ? 'block' : 'none';
    document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
</script>

</body>
</html>