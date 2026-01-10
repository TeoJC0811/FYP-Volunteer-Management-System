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
    SUCCESS / ERROR
    ========================== */
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "✅ Training updated successfully!";
}

if (isset($_GET['error'])) {
    $error = match ($_GET['error']) {
        "TrainingNotFound"      => "⚠️ Training not found.",
        "UpdateFailed"          => "❌ Error updating training.",
        "UnauthorizedAccess"    => "❌ You are not authorized to perform this action.",
        default                  => "❌ An unknown error occurred.",
    };
}

/* ==========================
    DELETE COURSE
    ========================== */
if (isset($_GET['delete'])) {
    $courseID = intval($_GET['delete']);
    if ($role === 'organizer') {
        $check = $conn->prepare("SELECT courseID FROM Course WHERE courseID = ? AND organizerID = ?");
        $check->bind_param("ii", $courseID, $userID);
        $check->execute();
        $res = $check->get_result();
        $check->close(); 

        if ($res->num_rows === 0) {
            $error = "❌ You are not authorized to delete this training.";
        } else {
            $stmt = $conn->prepare("DELETE FROM Course WHERE courseID = ?");
            $stmt->bind_param("i", $courseID);
            if ($stmt->execute()) {
                $message = "✅ Training deleted successfully!";
            } else {
                $error = "❌ Error deleting training.";
            }
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM Course WHERE courseID = ?");
        $stmt->bind_param("i", $courseID);
        if ($stmt->execute()) {
            $message = "✅ Training deleted successfully!";
        } else {
            $error = "❌ Error deleting training.";
        }
        $stmt->close();
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
    BASE QUERY & PARAMETERS
    ========================== */
$params = [];
$paramTypes = "";
$todayStr = date('Y-m-d'); 

$enrollmentSubquery = "(SELECT COUNT(*) FROM CourseRegistration cr WHERE cr.courseID = c.courseID) AS currentEnrollment";

if ($role === 'organizer') {
    $sql = "SELECT c.*, cat.categoryName, {$enrollmentSubquery} FROM Course c LEFT JOIN Category cat ON c.categoryID = cat.categoryID WHERE c.organizerID = ?";
    $params[] = $userID;
    $paramTypes .= "i";
} else {
    $sql = "SELECT c.*, cat.categoryName, {$enrollmentSubquery} FROM Course c LEFT JOIN Category cat ON c.categoryID = cat.categoryID WHERE 1";
}

// Status Filter logic for Date Ranges
if ($filterStatus === 'upcoming_ongoing') {
    $sql .= " AND c.endDate >= ?";
    $params[] = $todayStr;
    $paramTypes .= "s";
} elseif ($filterStatus === 'past') {
    $sql .= " AND c.endDate < ?";
    $params[] = $todayStr;
    $paramTypes .= "s";
}

if (!empty($searchQuery)) {
    $sql .= " AND (c.courseLocation LIKE ? OR c.courseCountry LIKE ? OR c.courseName LIKE ?)";
    $searchParam = "%" . $searchQuery . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= "sss";
}

if ($filterCountry !== "all") {
    $sql .= " AND c.courseCountry = ?";
    $params[] = $filterCountry;
    $paramTypes .= "s";
}

if ($filterCategory !== "all") {
    $sql .= " AND c.categoryID = ?";
    $params[] = intval($filterCategory);
    $paramTypes .= "i";
}

$sql .= " ORDER BY c.startDate DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($paramTypes, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
$todayDT = new DateTime($todayStr);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Training Course</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f7f6; }
        .main-content { padding: 20px; max-width: 1300px; margin: 40px 40px 40px 350px; }
        h2 { color: #333; text-align: center; margin-bottom: 25px; font-weight: 700; }
        .filter-container { max-width: 1200px; margin: 0 auto 30px; padding: 12px 15px; background: #f4f7f9; border-radius: 8px; display: flex; align-items: center; }
        .filter-bar-form { display: flex; flex-wrap: nowrap; width: 100%; gap: 8px; align-items: center; }
        .filter-bar-form input[type="text"] { flex: 2; min-width: 150px; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 5px; font-size: 13px; }
        .filter-bar-form select { flex: 1; min-width: 100px; padding: 10px; border-radius: 5px; border: 1px solid #ced4da; font-size: 13px; background-color: white; }
        .filter-bar-form button, .btn-clear, .btn-create { padding: 10px 20px; border-radius: 5px; font-size: 14px; font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; border: none; cursor: pointer; transition: 0.2s; }
        .btn-filter { background-color: #3498db; color: white; }
        .btn-filter:hover { background-color: #2980b9; }
        .btn-clear { background-color: #e9ecef; color: #333; }
        .btn-create { background-color: #3f51b5; color: white; margin-left: auto; }
        .event-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; }
        .event-card { background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden; display: flex; flex-direction: column; transition: 0.3s; }
        .event-card:hover { transform: translateY(-5px); }
        .card-image { height: 160px; background-size: cover; background-position: center; position: relative; }
        .status-badge { position: absolute; top: 12px; right: 12px; padding: 4px 10px; border-radius: 20px; font-weight: bold; color: white; font-size: 10px; text-transform: uppercase; }
        .status-upcoming { background: #007BFF; }
        .status-ongoing { background: #4CAF50; }
        .status-past { background: #9E9E9E; }
        .status-full { background: #FF9800; }
        .card-content { padding: 18px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-content h3 { margin: 0 0 12px; font-size: 1.25em; color: #3f51b5; border-bottom: 1px solid #f1f1f1; padding-bottom: 10px; font-weight: 700;}
        .card-metrics { font-size: 0.85rem; color: #666; margin-bottom: 15px; display: flex; flex-direction: column; gap: 7px; }
        .metric-item i { color: #3f51b5; width: 18px; text-align: center; margin-right: 5px; }
        .card-actions { display: flex; gap: 10px; padding: 15px; background: #fafafa; border-top: 1px solid #eee; margin: 0 -18px -18px -18px; }
        .card-actions a { flex: 1; text-align: center; padding: 8px 0; border-radius: 5px; font-size: 13px; font-weight: 600; text-decoration: none; transition: 0.2s; }
        .btn-edit { background: #3f51b5; color: #fff; }
        .btn-delete { background: #f44336; color: #fff; }
        @media (max-width: 1150px) { .main-content { margin: 40px auto; max-width: 95%; } }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <h2><i class="fas fa-graduation-cap"></i> Manage Training Course</h2>

    <?= $message ? "<p style='color:green; text-align:center; font-weight:bold;'>$message</p>" : "" ?>
    <?= $error ? "<p style='color:red; text-align:center; font-weight:bold;'>$error</p>" : "" ?>

    <div class="filter-container">
        <form method="get" action="" class="filter-bar-form">
            <input type="text" name="search" placeholder="Search course, location..." value="<?= htmlspecialchars($searchQuery) ?>">

            <select name="status">
                <option value="all" <?= ($filterStatus=='all')?'selected':'' ?>>All Status</option>
                <option value="upcoming_ongoing" <?= ($filterStatus=='upcoming_ongoing')?'selected':'' ?>>Active</option>
                <option value="past" <?= ($filterStatus=='past')?'selected':'' ?>>Past Courses</option>
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
            <a href="manage_training.php" class="btn-clear"><i class="fas fa-undo"></i> Clear</a>
            <a href="add_training.php" class="btn-create"><i class="fas fa-plus"></i> Create New</a>
        </form>
    </div>

    <div class="event-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): 
                // FIX: Fallback to 'now' if null to prevent PHP 8.1 error
                $startDT = new DateTime($row['startDate'] ?? 'now');
                $endDT = new DateTime($row['endDate'] ?? 'now');
                $curr = $row['currentEnrollment'] ?? 0;
                $max = $row['maxParticipant'] ?? 0;
                
                // Status Badge Logic
                if ($todayDT > $endDT) { 
                    $sClass = 'status-past'; $sText = 'Past'; 
                } elseif ($todayDT >= $startDT && $todayDT <= $endDT) { 
                    $sClass = 'status-ongoing'; $sText = 'Ongoing'; 
                } else { 
                    $sClass = 'status-upcoming'; $sText = 'Upcoming'; 
                }

                if ($curr >= $max && $sText != 'Past') { $sClass = 'status-full'; $sText = 'Full'; }
                
                $img = !empty($row['coverImage']) ? '../uploads/course_cover/' . $row['coverImage'] : '../images/default.jpg';
                
                // FIX: Fallback for strtotime null values
                $timeRange = (!empty($row['startTime']) && !empty($row['endTime'])) 
                    ? date('h:i A', strtotime($row['startTime'])) . ' - ' . date('h:i A', strtotime($row['endTime'])) 
                    : 'Time Not Set';
                
                $dateDisplay = (($row['startDate'] ?? '') === ($row['endDate'] ?? '')) 
                    ? $startDT->format('d M Y') 
                    : $startDT->format('d M') . ' - ' . $endDT->format('d M Y');
            ?>
            <div class="event-card">
                <div class="card-image" style="background-image: url('<?= htmlspecialchars($img) ?>');">
                    <span class="status-badge <?= $sClass ?>"><?= $sText ?></span>
                </div>
                <div class="card-content">
                    <h3><?= htmlspecialchars($row['courseName'] ?? 'Untitled Course') ?></h3>
                    <div class="card-metrics">
                        <div class="metric-item"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['courseLocation'] ?? 'N/A') ?>, <?= htmlspecialchars($row['courseCountry'] ?? '') ?></div>
                        <div class="metric-item"><i class="fas fa-calendar-day"></i> <?= $dateDisplay ?></div>
                        <div class="metric-item"><i class="fas fa-clock"></i> <?= $timeRange ?></div>
                        <div class="metric-item"><i class="fas fa-users"></i> Enrollment: <strong><?= $curr ?> / <?= $max ?></strong></div>
                    </div>
                    <div class="card-actions">
                        <a href="edit_training.php?id=<?= $row['courseID']; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="manage_training.php?delete=<?= $row['courseID']; ?>" 
                           class="btn-delete"
                           onclick="return confirm('Are you sure you want to delete this course?');">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align:center; padding: 50px; background:white; border-radius:8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <i class="fas fa-search fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
                <p style="color:#777;">No training courses found matching your criteria.</p>
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