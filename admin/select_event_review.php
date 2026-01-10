<?php
session_start();
include("../db.php");

// ✅ Only allow organizers/admin
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: ../login.php");
    exit();
}

$organizerID = $_SESSION['userID'];

/* ==========================
    SEARCH INPUT
    ========================== */
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

/* ==========================
    FETCH EVENTS WITH REVIEW STATISTICS
    ========================== */
// UPDATED: Included startDate and endDate in SELECT
$query = "
    SELECT 
        e.eventID,
        e.eventName,
        e.startDate,             
        e.endDate,             
        e.eventLocation,         
        e.maxParticipant,
        COUNT(r.reviewID) AS totalReviews,
        ROUND(AVG(r.rating), 2) AS avgRating
    FROM Event e
    LEFT JOIN Review r 
        ON r.activityID = e.eventID
        AND r.activityType = 'event'
    WHERE e.organizerID = ?
";

// Apply Search Filter
if (!empty($searchQuery)) {
    $query .= " AND (e.eventName LIKE ? OR e.eventLocation LIKE ?)";
}

// UPDATED: Added endDate to GROUP BY
$query .= " GROUP BY e.eventID, e.eventName, e.startDate, e.endDate, e.eventLocation, e.maxParticipant
            ORDER BY e.startDate DESC";

$stmt = $conn->prepare($query);

if (!empty($searchQuery)) {
    $searchTerm = "%$searchQuery%";
    $stmt->bind_param("iss", $organizerID, $searchTerm, $searchTerm);
} else {
    $stmt->bind_param("i", $organizerID);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Event Review</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root { 
            --primary-color: #3f51b5; 
            --border-radius: 8px; 
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }

        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }

        .main-content {
            padding: 20px;
            max-width: 1300px;
            margin: 40px 40px 40px 350px; 
        }

        h2 { color: #333; text-align: center; margin-bottom: 25px; font-weight: 700; }

        .search-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0 30px;
            padding: 15px;
            
            border-radius: var(--border-radius);
            
        }

        .search-bar input {
            padding: 10px 12px;
            flex-grow: 1;
            max-width: 450px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            outline: none;
        }

        .search-bar button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-bar button:hover { background-color: #2980b9; }

        .search-bar a.btn-clear {
            background-color: #e9ecef;
            color: #333;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 25px;
        }

        .event-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: 0.3s;
            border: 1px solid #f1f1f1;
        }
        .event-card:hover { transform: translateY(-5px); }

        .card-content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-content h3 { 
            margin: 0 0 12px; 
            font-size: 1.25em; 
            color: var(--primary-color); 
            border-bottom: 1px solid #f1f1f1; 
            padding-bottom: 10px; 
            font-weight: 700;
        }

        .card-metrics { font-size: 0.85rem; color: #666; margin-bottom: 15px; display: flex; flex-direction: column; gap: 7px; }
        .metric-item i { color: var(--primary-color); width: 18px; text-align: center; margin-right: 5px; }

        .card-actions { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding-top: 15px; 
            border-top: 1px solid #f1f1f1; 
            margin-top: auto;
        }

        .rating-block { display: flex; flex-direction: column; }
        .rating-score {
            font-size: 1.6em;
            font-weight: 800;
            color: #f39c12;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .total-reviews { font-size: 0.85rem; color: #888; margin: 0; }

        .btn-analyze {
            background-color: var(--primary-color);
            color: white;
            padding: 9px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-analyze:hover { background-color: #303f9f; }

        @media (max-width: 1150px) {
            .main-content { margin: 40px auto; max-width: 95%; }
        }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <h2><i class="fas fa-star-half-stroke"></i> Manage Event Reviews</h2>

    <form method="get" action="" class="search-bar">
        <input type="text" name="search" placeholder="Search event name or location..." value="<?= htmlspecialchars($searchQuery) ?>">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
        <a href="select_event_review.php" class="btn-clear"><i class="fas fa-undo"></i> Clear</a>
    </form>

    <div class="event-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): 
                $avgRating = $row['avgRating'] ?? 0;
                $totalReviews = (int)$row['totalReviews'];
                
                // UPDATED: Date Logic
                $start = $row['startDate'];
                $end = $row['endDate'];
                $formattedStart = !empty($start) ? date('d M Y', strtotime($start)) : 'N/A';
                $formattedEnd = !empty($end) ? date('d M Y', strtotime($end)) : 'N/A';

                $dateDisplay = ($start === $end) ? $formattedStart : $formattedStart . " - " . $formattedEnd;
            ?>
            <div class="event-card">
                <div class="card-content">
                    <h3><?= htmlspecialchars($row['eventName']) ?></h3>
                    
                    <div class="card-metrics">
                        <div class="metric-item">
                            <i class="fas fa-calendar-day"></i> 
                            Date: <?= $dateDisplay ?>
                        </div>
                        <div class="metric-item">
                            <i class="fas fa-map-marker-alt"></i> 
                            Location: <?= htmlspecialchars($row['eventLocation']) ?>
                        </div>
                        <div class="metric-item">
                            <i class="fas fa-users"></i> 
                            Max Capacity: <strong><?= htmlspecialchars($row['maxParticipant']) ?></strong>
                        </div>
                    </div>

                    <div class="card-actions">
                        <div class="rating-block">
                            <p class="rating-score">
                                <i class="fas fa-star"></i>
                                <?= $totalReviews > 0 ? htmlspecialchars($avgRating) : '0.0' ?>
                            </p>
                            <p class="total-reviews">
                                <strong><?= $totalReviews ?></strong> Reviews
                            </p>
                        </div>

                        <a href="review_event.php?eventID=<?= $row['eventID'] ?>" class="btn-analyze">
                            Analyze <i class="fas fa-chart-bar"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align: center; background: white; padding: 40px; border-radius: 8px; box-shadow: var(--box-shadow);">
                <p style="color: #777;"><i class="fas fa-info-circle"></i> No events found matching your search.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>