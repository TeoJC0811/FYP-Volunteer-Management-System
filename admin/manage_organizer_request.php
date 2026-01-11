<?php
session_start();
include("../db.php");

// Only allow admins
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$message = $error = "";

// Handle Approval/Rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $targetID = intval($_GET['id']);
    $action = $_GET['action'];
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';

    // 1. Update the user status in the User table
    $stmt = $conn->prepare("UPDATE user SET status = ? WHERE userID = ? AND userRoles = 'organizer'");
    $stmt->bind_param("si", $newStatus, $targetID);
    
    if ($stmt->execute()) {
        // 2. If approved, send notification using YOUR table structure
        if ($newStatus === 'approved') {
            // We combine title and message since your table only has 'message'
            $notifMessage = "Application Approved! 🎉 Congratulations, your organizer application has been approved. You can now access the Organizer Portal.";
            $activityType = "account_approval";
            $activityID = 0; // No specific event ID for account approval

            // Matching your columns: message, userID, activityType, activityID, isRead, createdAt, isDeleted
            $stmtNotif = $conn->prepare("INSERT INTO notification (message, userID, activityType, activityID, isRead, createdAt, isDeleted) VALUES (?, ?, ?, ?, 0, NOW(), 0)");
            $stmtNotif->bind_param("sisi", $notifMessage, $targetID, $activityType, $activityID);
            $stmtNotif->execute();
            $stmtNotif->close();
        }

        $message = "✅ Organizer application " . $newStatus . " successfully!";
    } else {
        $error = "❌ Error updating organizer status.";
    }
    $stmt->close();
}

// Filter & Search Logic
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : "";

$sql = "SELECT * FROM user WHERE userRoles = 'organizer' AND status = 'pending'";
$params = [];
$types = "";

if (!empty($searchQuery)) {
    $sql .= " AND (userName LIKE ? OR userEmail LIKE ?)";
    $searchParam = "%" . $searchQuery . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}
$sql .= " ORDER BY userID ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Organizer Requests</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .search-filter-bar { display: flex; justify-content: center; align-items: center; gap: 10px; margin: 20px 0 30px; padding: 15px; border-radius: 8px; }
        .search-filter-bar input { padding: 10px 12px; flex-grow: 1; max-width: 400px; border: 1px solid #ced4da; border-radius: 5px; }
        .search-filter-bar button, .search-filter-bar a.btn-clear { padding: 10px 20px; cursor: pointer; border: none; border-radius: 5px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .search-filter-bar button { background-color: #3498db; color: white; }
        .search-filter-bar a.btn-clear { background-color: #e9ecef; color: #333; }

        .styled-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .styled-table th, .styled-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; word-wrap: break-word; }
        .styled-table th:nth-child(1), .styled-table td:nth-child(1) { width: 60px; } 
        .styled-table th:nth-child(2), .styled-table td:nth-child(2) { width: 220px; } 
        .styled-table th:nth-child(3), .styled-table td:nth-child(3) { width: 230px; } 
        .styled-table th:nth-child(4), .styled-table td:nth-child(4) { width: 100px; } 
        .styled-table th:nth-child(5), .styled-table td:nth-child(5) { width: 190px; } 

        .btn-approve { background-color: #2ecc71; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px; font-weight: bold; }
        .btn-reject { background-color: #e74c3c; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-weight: bold; }
        .status-pending { color: #f39c12; font-weight: bold; }
    </style>
</head>
<body>
    <?php include("sidebar.php"); ?>

    <div class="main-content">
        <h2>Organizer Application Requests</h2>

        <?php if (!empty($message)) echo "<p style='color: green; font-weight: bold;'>$message</p>"; ?>
        <?php if (!empty($error)) echo "<p style='color: red; font-weight: bold;'>$error</p>"; ?>

        <div class="search-filter-bar">
            <form method="get" action="manage_organizer_request.php" style="display: flex; gap: 10px; width: 100%; justify-content: center; align-items: center;">
                <input type="text" name="search" placeholder="Search by organization name or email..." value="<?= htmlspecialchars($searchQuery) ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
                <a href="manage_organizer_request.php" class="btn-clear"><i class="fas fa-undo"></i> Clear</a>
            </form>
        </div>

        <table class="styled-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Organization Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['userID']; ?></td>
                        <td><?= htmlspecialchars($row['userName']); ?></td>
                        <td><?= htmlspecialchars($row['userEmail']); ?></td>
                        <td><span class="status-pending"><?= strtoupper($row['status']); ?></span></td>
                        <td>
                            <a href="manage_organizer_request.php?action=approve&id=<?= $row['userID']; ?>" class="btn-approve" onclick="return confirm('Approve this organization?');">Approve</a>
                            <a href="manage_organizer_request.php?action=reject&id=<?= $row['userID']; ?>" class="btn-reject" onclick="return confirm('Reject this organization?');">Reject</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">No pending organizer requests found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>