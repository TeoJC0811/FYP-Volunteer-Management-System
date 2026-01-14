<?php
session_start();
include("../db.php"); // Include DB for potential future stats

// Only allow logged-in admins or organizers
if (!isset($_SESSION['userID']) || !in_array($_SESSION['role'], ['admin', 'organizer'])) {
    header("Location: login.php");
    exit();
}

// Safely get username
$userName = isset($_SESSION['userName']) ? htmlspecialchars($_SESSION['userName']) : "User";

// Get role (capitalize first letter for display)
$userRole = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : "User";
$isOrganizer = (strtolower($userRole) === 'organizer');

// --- MOCK-UP DATA ---
$stats = [
    'events' => ['count' => 12, 'label' => 'Total Events', 'icon' => '📅', 'color' => '#3498db'],
    'courses' => ['count' => 5, 'label' => 'Total Courses', 'icon' => '📚', 'color' => '#2ecc71'],
];

if (!$isOrganizer) {
    // Add admin-specific stats
    $stats['total_users'] = ['count' => 150, 'label' => 'Registered Users', 'icon' => '👥', 'color' => '#9b59b6'];
    $stats['rewards'] = ['count' => 8, 'label' => 'Active Rewards', 'icon' => '🎁', 'color' => '#e74c3c'];
}
// --- END MOCK-UP DATA ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $userRole ?> Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Container for dashboard cards */
        .dashboard-header {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        .dashboard-header h2 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 1.8em;
        }
        .welcome {
            font-size: 1.1em;
            color: #7f8c8d;
            margin-bottom: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 20px;
            margin-top: 20px;
        }

        /* Stat Card - Changed to Div behavior (No hover effect, no pointer) */
        .stat-card {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: default; /* Arrow cursor instead of hand pointer */
        }

        .stat-card .icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .stat-card .count {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
        }
        .stat-card .label {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-top: 5px;
        }

        /* Quick Links Area */
        .quick-links {
            margin-top: 30px;
            padding: 20px;
            background: #ecf0f1;
            border-radius: 8px;
            border: 1px solid #bdc3c7;
        }
        .quick-links h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .action-button {
            display: inline-block;
            padding: 12px 20px;
            margin: 10px 10px 10px 0;
            text-decoration: none;
            color: white;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.2s;
            text-align: center;
        }
        .action-button:hover {
            opacity: 0.9;
        }
        .btn-event { background-color: #3498db; }
        .btn-course { background-color: #2ecc71; }
        .btn-users { background-color: #9b59b6; }
        .btn-rewards { background-color: #e74c3c; }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="main-content">
    <div class="dashboard-header">
        <p class="welcome">👋 Welcome, **<?= $userName ?>** (<?= $userRole ?>)</p>
        <h2><?= $userRole ?> Dashboard Overview</h2>
        <p>Summary of key activities and resources.</p>
    </div>

    <div class="stats-grid">
        <?php foreach ($stats as $key => $stat): ?>
        <div class="stat-card" style="border-left: 5px solid <?= $stat['color']; ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="icon" style="color: <?= $stat['color']; ?>;"><?= $stat['icon']; ?></div>
                <div class="count"><?= $stat['count']; ?></div>
            </div>
            <div class="label"><?= $stat['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="quick-links">
        <h3>Quick Access Actions</h3>
        
        <?php if ($isOrganizer): ?>
            <a href="select_event_registration.php" class="action-button btn-event">📅 Go to Event Management</a>
            <a href="select_training_registration.php" class="action-button btn-course">📚 Go to Course Management</a>
        <?php else: ?>
            <a href="manage_user.php" class="action-button btn-users">👥 Manage Users & Roles</a>
            <a href="manage_reward.php" class="action-button btn-rewards">🎁 Manage Rewards Program</a>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleMenu(id) {
    const menu = document.getElementById(id);
    menu.style.display = (menu.style.display === "block") ? "none" : "block";
}
</script>

</body>
</html>