<?php
session_start();
include("../db.php");

// Only allow admins + organizers
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: login.php");
    exit();
}

$message = $error = "";
$userID = $_SESSION['userID'];
$role   = $_SESSION['role'];

/* ==========================
    SUCCESS / ERROR MESSAGES
    ========================== */
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "✅ Event updated successfully!";
}
if (isset($_GET['error'])) {
    $error = match ($_GET['error']) {
        "EventNotFound"       => "⚠️ Event not found.",
        "UpdateFailed"        => "❌ Error updating event.",
        "UnauthorizedAccess"  => "❌ You are not authorized to perform this action.",
        default               => "❌ An unknown error occurred.",
    };
}

/* ==========================
    DELETE EVENT (UPDATED)
    ========================== */
if (isset($_GET['delete'])) {
    $eventID = intval($_GET['delete']);
    $canDelete = false;

    // 1. Authorization Check
    if ($role === 'organizer') {
        $check = $conn->prepare("SELECT eventID FROM Event WHERE eventID = ? AND organizerID = ?");
        $check->bind_param("ii", $eventID, $userID);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $canDelete = true;
        } else {
            $error = "❌ You are not authorized to delete this event.";
        }
        $check->close();
    } else {
        $canDelete = true; // Admins can delete anything
    }

    if ($canDelete) {
        // Start transaction to ensure all or nothing is deleted
        $conn->begin_transaction();

        try {
            // A. Delete from eventpast where this is the main event
            $delPast1 = $conn->prepare("DELETE FROM eventpast WHERE eventID = ?");
            $delPast1->bind_param("i", $eventID);
            $delPast1->execute();

            // B. Delete from eventpast where this is listed as a past event for others
            $delPast2 = $conn->prepare("DELETE FROM eventpast WHERE pastEventID = ?");
            $delPast2->bind_param("i", $eventID);
            $delPast2->execute();

            // C. Delete registrations for this event
            $delReg = $conn->prepare("DELETE FROM EventRegistration WHERE eventID = ?");
            $delReg->bind_param("i", $eventID);
            $delReg->execute();

            // D. Finally, delete the event itself
            $stmt = $conn->prepare("DELETE FROM Event WHERE eventID = ?");
            $stmt->bind_param("i", $eventID);
            $stmt->execute();

            $conn->commit();
            $message = "✅ Event and its related records deleted successfully!";
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = "❌ Error deleting event: " . $e->getMessage();
        }
    }
}

/* ==========================
    SEARCH & FILTER INPUTS
    ========================== */
$searchQuery    = $_GET['search']   ?? "";
$filterCountry  = $_GET['country']  ?? "all";
$filterCategory = $_GET['category'] ?? "all";
$filterStatus   = $_GET['status']   ?? "all"; 

/* ==========================
    FETCH CATEGORY LIST
    ========================== */
$categories = [];
$resCat = $conn->query("SELECT categoryID, categoryName FROM Category ORDER BY categoryName ASC");
while ($row = $resCat->fetch_assoc()) { $categories[] = $row; }
$resCat->close(); 

/* ==========================
    BASE QUERY
    ========================== */
$params = [];
$paramTypes = "";
$today = date('Y-m-d'); 

$sql_select = "
    SELECT e.*, c.categoryName,
        (SELECT COUNT(*) FROM EventRegistration er WHERE er.eventID = e.eventID) AS currentParticipants
    FROM Event e
    LEFT JOIN Category c ON e.categoryID = c.categoryID
";

$sql_where = " WHERE 1 "; 

if ($role === 'organizer') {
    $sql_where .= " AND e.organizerID = ?";
    $params[] = $userID;
    $paramTypes .= "i";
}

if ($filterStatus === 'upcoming_ongoing') {
    $sql_where .= " AND e.endDate >= ?";
    $params[] = $today;
    $paramTypes .= "s";
} elseif ($filterStatus === 'past') {
    $sql_where .= " AND e.endDate < ?";
    $params[] = $today;
    $paramTypes .= "s";
}

if (!empty($searchQuery)) {
    $sql_where .= " AND (e.eventLocation LIKE ? OR e.eventCountry LIKE ? OR e.eventName LIKE ?)";
    $searchParam = "%" . $searchQuery . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= "sss";
}

if ($filterCountry !== "all") {
    $sql_where .= " AND e.eventCountry = ?";
    $params[] = $filterCountry;
    $paramTypes .= "s";
}

if ($filterCategory !== "all") {
    $sql_where .= " AND e.categoryID = ?";
    $params[] = intval($filterCategory);
    $paramTypes .= "i";
}

$sql = $sql_select . $sql_where . " ORDER BY e.startDate DESC"; 
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($paramTypes, ...$params); }
$stmt->execute(); 
$result = $stmt->get_result();
$todayDT = new DateTime(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Events</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ===== Variables & Root ===== */
        :root { 
            --primary-color: #3f51b5; 
            --border-radius: 8px; 
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
        }

        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; }
        .main-content { padding: 20px; max-width: 1300px; margin: 40px 40px 40px 350px; }
        h2 { color: #333; text-align: center; margin-bottom: 25px; font-weight: 700; }

        /* ===== Success/Error Boxes ===== */
        .success, .error { padding: 12px 15px; margin-bottom: 20px; border-radius: var(--border-radius); font-weight: bold; }
        .success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c3e6cb; }
        .error { background-color: #ffebee; color: #c62828; border: 1px solid #f5c6cb; }

        /* ===== Search Bar (Forum Style) ===== */
        .filter-container {
            max-width: 1200px;
            margin: 0 auto 30px;
            padding: 15px;
            background: #f4f7f9;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
        }

        .filter-bar-form {
            display: flex;
            flex-wrap: nowrap;
            width: 100%;
            gap: 10px;
            align-items: center;
        }

        .filter-bar-form input[type="text"] {
            flex: 2;
            min-width: 150px;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 13px;
        }

        .filter-bar-form select {
            flex: 1;
            min-width: 100px;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ced4da;
            font-size: 13px;
            background-color: white;
        }

        .filter-bar-form button, 
        .btn-clear, 
        .btn-create {
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-filter { background-color: #3498db; color: white; }
        .btn-filter:hover { background-color: #2980b9; }
        
        .btn-clear { background-color: #e9ecef; color: #333; }
        .btn-clear:hover { background-color: #dee2e6; }

        .btn-create { background-color: #3f51b5; color: white; margin-left: auto; }
        .btn-create:hover { background-color: #303f9f; }

        /* ===== Event Grid & Cards ===== */
        .event-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .event-card { background: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow); overflow: hidden; display: flex; flex-direction: column; transition: 0.3s; }
        .event-card:hover { transform: translateY(-5px); }

        .card-image { height: 160px; background-size: cover; background-position: center; position: relative; }
        
        .status-badge { position: absolute; top: 12px; right: 12px; padding: 4px 10px; border-radius: 20px; font-weight: bold; color: white; font-size: 10px; text-transform: uppercase; }
        .status-upcoming { background: #3498db; }
        .status-ongoing { background: #2ecc71; }
        .status-past { background: #95a5a6; }
        .status-full { background: #e67e22; }

        .card-content { padding: 18px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-content h3 { margin: 0 0 12px; font-size: 1.25em; color: var(--primary-color); border-bottom: 1px solid #f1f1f1; padding-bottom: 10px; font-weight: 700; }

        .card-metrics { font-size: 0.85rem; color: #666; margin-bottom: 15px; display: flex; flex-direction: column; gap: 7px; }
        .metric-item i { color: var(--primary-color); width: 18px; text-align: center; margin-right: 5px; }

        /* ===== Reward-style Actions ===== */
        .card-actions {
            display: flex;
            gap: 10px;
            padding: 15px;
            background: #fafafa;
            border-top: 1px solid #eee;
            margin: 0 -18px -18px -18px; /* Alignment fix for nested card structure */
        }

        .card-actions a {
            flex: 1;
            text-align: center;
            padding: 8px 0;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-edit { background: var(--primary-color); color: #fff; }
        .btn-edit:hover { background: #303f9f; }

        .btn-delete { background: #f44336; color: #fff; }
        .btn-delete:hover { background: #d32f2f; }

        @media (max-width: 1150px) {
            .main-content { margin: 40px auto; max-width: 95%; }
            .filter-container { overflow-x: auto; }
        }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <h2><i class="fas fa-calendar-alt"></i> Manage Events</h2>

    <?php if ($message) echo "<p class='success'>$message</p>"; ?>
    <?php if ($error) echo "<p class='error'>$error</p>"; ?>

    <div class="filter-container">
        <form method="get" action="" class="filter-bar-form">
            <input type="text" name="search" placeholder="Search event, location..." value="<?= htmlspecialchars($searchQuery) ?>">
            <select name="status">
                <option value="all" <?= ($filterStatus=='all')?'selected':'' ?>>All Status</option>
                <option value="upcoming_ongoing" <?= ($filterStatus=='upcoming_ongoing')?'selected':'' ?>>Active</option>
                <option value="past" <?= ($filterStatus=='past')?'selected':'' ?>>Past</option>
            </select>
            <select name="country">
                <option value="all">Country</option>
                <?php foreach (["Malaysia","Thailand","Singapore","Indonesia"] as $ct): ?>
                <option value="<?= $ct ?>" <?= ($filterCountry==$ct)?"selected":"" ?>><?= $ct ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category">
                <option value="all">Category</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['categoryID'] ?>" <?= ($filterCategory == $cat['categoryID']) ? "selected" : "" ?>>
                    <?= htmlspecialchars($cat['categoryName']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Search</button>
            <a href="manage_event.php" class="btn-clear"><i class="fas fa-undo"></i> Clear</a>
            <a href="add_event.php" class="btn-create"><i class="fas fa-plus"></i> Create New</a>
        </form>
    </div>

    <div class="event-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): 
                $curr = $row['currentParticipants'];
                $max = $row['maxParticipant'];
                $start = new DateTime($row['startDate']);
                $end = new DateTime($row['endDate']);
                
                if ($todayDT > $end) { $sClass = 'status-past'; $sText = 'Past'; }
                elseif ($todayDT >= $start && $todayDT <= $end) { $sClass = 'status-ongoing'; $sText = 'Ongoing'; }
                else { $sClass = 'status-upcoming'; $sText = 'Upcoming'; }

                if ($curr >= $max && $sText != 'Past') { $sClass = 'status-full'; $sText = 'Full'; }
                $img = !empty($row['coverImage']) ? '../uploads/event_cover/' . $row['coverImage'] : '../images/default.jpg';
            ?>
            <div class="event-card">
                <div class="card-image" style="background-image: url('<?= htmlspecialchars($img) ?>');">
                    <span class="status-badge <?= $sClass ?>"><?= $sText ?></span>
                </div>
                <div class="card-content">
                    <h3><?= htmlspecialchars($row['eventName']) ?></h3>
                    <div class="card-metrics">
                        <div class="metric-item"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['eventLocation']) ?>, <?= htmlspecialchars($row['eventCountry']) ?></div>
                        <div class="metric-item"><i class="fas fa-calendar-day"></i> <?= $start->format('d M') ?> - <?= $end->format('d M Y') ?></div>
                        <div class="metric-item"><i class="fas fa-users"></i> Participants: <strong><?= $curr ?> / <?= $max ?></strong></div>
                    </div>
                    <div class="card-actions">
                        <a href="edit_event.php?id=<?= $row['eventID'] ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="manage_event.php?delete=<?= $row['eventID'] ?>" 
                           class="btn-delete"
                           onclick="return confirm('Are you sure you want to delete this event? This will also remove participant registrations and past event links.');">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align:center; padding: 50px; background:white; border-radius:8px; box-shadow: var(--box-shadow);">
                <i class="fas fa-search fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
                <p style="color:#777;">No events found matching your search.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
if (isset($stmt)) $stmt->close();
$conn->close(); 
?>
</body>
</html>