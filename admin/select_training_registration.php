<?php
session_start();
include("../db.php");

// ✅ Allow only admin or organizer
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];
$role   = $_SESSION['role'];

/* ==========================
    FILTERS
   ========================== */
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'upcoming'; 

/* ==========================
    BASE SQL
   ========================== */
$sql = "
    SELECT 
        c.courseID,
        c.courseName AS displayName,
        c.startDate,
        c.endDate,
        c.courseLocation,
        c.organizerID,
        (SELECT COUNT(*) FROM courseregistration cr WHERE cr.courseID = c.courseID) AS participantCount
    FROM course c
    WHERE 1=1
";

if ($role === 'organizer') {
    $sql .= " AND c.organizerID = " . intval($userID);
}

if (!empty($search)) {
    $searchTerm = $conn->real_escape_string($search);
    $sql .= "
        AND (c.courseName LIKE '%$searchTerm%' 
        OR c.courseLocation LIKE '%$searchTerm%')
    ";
}

$today = date('Y-m-d');

if ($statusFilter === 'upcoming') {
    $sql .= " AND c.endDate >= '$today'";
}
elseif ($statusFilter === 'past') {
    $sql .= " AND c.endDate < '$today'";
}

$sql .= " ORDER BY c.startDate DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Select Training to Manage Registrations</title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body { background-color: #f4f7f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

/* Adjusted main container for better grid fit */
.main-content {
    max-width: 1300px;
    margin: 40px 40px 40px 260px; /* Aligned with sidebar width */
    padding: 20px;
}

h2 {
    color: #333;
    text-align: center;
    margin-bottom: 30px;
    font-weight: 700;
}

/* 🔍 SEARCH BAR STYLE */
.search-filter-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 20px 0 30px;
    padding: 15px;
    background: #f4f7f9;
    border-radius: 8px;
}

.search-filter-bar input[type="text"] {
    padding: 10px 12px;
    flex-grow: 1;
    max-width: 350px;
    border: 1px solid #ced4da;
    border-radius: 5px;
    outline: none;
}

.search-filter-bar select {
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 5px;
    background: white;
    cursor: pointer;
}

.search-filter-bar button {
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

.search-filter-bar button:hover { background-color: #2980b9; }

.search-filter-bar a.btn-clear {
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

/* 📋 Training List updated for 3 columns */
.training-list { 
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
    gap: 25px;
    width: 100%;
}

.training-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s, transform 0.3s;
    position: relative;
    display: flex;
    flex-direction: column;
}

.training-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    transform: translateY(-3px);
}

.training-card h3 { 
    margin: 0 0 10px 0; 
    color: #34495e; 
    font-size: 1.3rem;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    padding-right: 75px; /* Space for status badge */
}

.course-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 15px;
    flex-grow: 1; /* Pushes button to bottom */
}

.detail-row {
    display: flex;
    align-items: flex-start;
    font-size: 14px;
    color: #555;
}

.detail-row i {
    width: 20px;
    color: #3498db;
    margin-top: 3px;
}

.participant-count {
    margin-top: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #2ecc71;
}

.status-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-upcoming { background-color: #3498db; color: white; }
.status-past { background-color: #95a5a6; color: white; }

.btn-manage {
    margin-top: 15px;
    padding: 12px 15px;
    background-color: #e67e22;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    text-align: center;
    transition: background 0.2s;
}

.btn-manage:hover { background-color: #d35400; }

.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 50px;
    background: white;
    border-radius: 12px;
    color: #777;
}

@media (max-width: 1150px) {
    .main-content { margin: 40px auto; max-width: 95%; }
}
</style>
</head>
<body>
<?php include("../admin/sidebar.php"); ?>

<div class="main-content">
    <h2><i class="fas fa-graduation-cap"></i> Manage Registrations</h2>
    <p style="text-align: center; color: #555;">Dashboard for <?php echo strtoupper($role); ?>: Manage participant details for your training courses.</p>

    <div class="search-filter-bar">
        <form method="get" action="" style="display: flex; width: 100%; gap: 10px; align-items: center; justify-content: center;">
            <input type="hidden" name="courseID" value="<?= isset($_GET['courseID']) ? htmlspecialchars($_GET['courseID']) : '' ?>">
            <input type="text" name="search" placeholder="Search by training name or location..." value="<?= htmlspecialchars($search) ?>">
            
            <select name="status">
                <option value="upcoming" <?= $statusFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming Trainings</option>
                <option value="past" <?= $statusFilter === 'past' ? 'selected' : '' ?>>Past Trainings</option>
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Trainings</option>
            </select>

            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <a href="select_training_registration.php" class="btn-clear"><i class="fas fa-undo"></i> Clear</a>
        </form>
    </div>

    <div class="training-list">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $isUpcoming = (new DateTime($row['endDate']))->format('Y-m-d') >= $today;
                    $statusClass = $isUpcoming ? 'status-upcoming' : 'status-past';
                    $statusText = $isUpcoming ? 'Upcoming' : 'Past';

                    // Format Date Range
                    $startStr = date('d M Y', strtotime($row['startDate']));
                    $endStr = date('d M Y', strtotime($row['endDate']));
                    $displayDate = ($row['startDate'] === $row['endDate']) ? $startStr : $startStr . " - " . $endStr;
                ?>
                <div class="training-card">
                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                    <h3><?= htmlspecialchars($row['displayName']); ?></h3>
                    
                    <div class="course-details">
                        <div class="detail-row">
                            <i class="fas fa-calendar-alt"></i> 
                            <span><?= $displayDate ?></span>
                        </div>
                        <div class="detail-row">
                            <i class="fas fa-map-marker-alt"></i> 
                            <span><?= htmlspecialchars($row['courseLocation']); ?></span>
                        </div>
                        
                        <p class="participant-count">
                            <i class="fas fa-users"></i> 
                            <?= number_format(intval($row['participantCount'])); ?> Participants
                        </p>
                    </div>

                    <a href="manage_training_registration.php?courseID=<?= $row['courseID']; ?>" class="btn-manage">
                        <i class="fas fa-user-edit"></i> Manage Registrations
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search fa-3x" style="color:#ddd; margin-bottom:15px;"></i>
                <p>No trainings found matching your criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>