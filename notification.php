<?php
session_start();
require 'db.php';

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// ✅ Handle filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// ✅ Base query
$sql = "SELECT notificationID, message, activityType, activityID, isRead, createdAt
        FROM notification 
        WHERE userID = ? AND isDeleted = 0";

// ✅ Apply filter conditions
if ($filter === 'unread') {
    $sql .= " AND isRead = 0";
} elseif ($filter === '1week') {
    $sql .= " AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($filter === '1month') {
    $sql .= " AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} elseif ($filter === '1year') {
    $sql .= " AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
}

// ✅ Apply search
if (!empty($search)) {
    $sql .= " AND message LIKE ?";
    $stmt = $conn->prepare($sql . " ORDER BY createdAt DESC");
    $like = "%$search%";
    $stmt->bind_param("is", $userID, $like);
} else {
    $stmt = $conn->prepare($sql . " ORDER BY createdAt DESC");
    $stmt->bind_param("i", $userID);
}

$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Notifications</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* 💡 Page Setup */
        html, body {
            height: 100%; 
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        .page-content-wrapper { 
            flex: 1 0 auto;
        }

        .main-content {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        /* ✅ UPDATED: Search & Filter Bar Layout */
        .filter-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            width: 100%;
        }
        
        /* Make Search input longer */
        .filter-bar input[type="text"] {
            flex-grow: 1; /* Takes up remaining space */
            max-width: 500px;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        
        /* Make Filter dropdown shorter */
        .filter-bar select {
            width: 130px; /* Shorter width */
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            background-color: white;
            cursor: pointer;
        }

        .filter-bar button {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        /* --- Bulk Action Bar --- */
        .bulk-action-bar {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 5px;
            padding: 10px 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        /* ICON BUTTON STYLING */
        .icon-btn {
            background: transparent;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            color: #666;
            padding: 0;
        }
        .icon-btn:hover:not(:disabled) {
            background-color: rgba(0, 0, 0, 0.08);
            color: #666;
        }
        .icon-btn:disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        /* --- Notification List --- */
        .notification-list {
            list-style: none;
            padding: 0;
        }
        .notification-item {
            background: #fff;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center; 
            transition: background 0.2s;
        }
        .notification-item.unread {
            background: #f0f8ff;
            border-left: 5px solid #007BFF;
        }
        .notification-item input[type="checkbox"] {
            margin-right: 15px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .notification-content-wrapper {
            flex: 1; 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-content {
            flex: 1;
            padding-right: 15px;
        }
        .notification-time {
            font-size: 12px;
            color: #888;
            min-width: 100px;
            text-align: right;
            margin-right: 10px;
        }

        @media (max-width: 600px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar input[type="text"], .filter-bar select { max-width: none; width: 100%; }
        }
    </style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="page-content-wrapper"> 
    <div class="main-content">
        <h2>🔔 My Notifications</h2>

        <form class="filter-bar" method="GET">
            <input type="text" name="search" placeholder="Search notifications..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="filter">
                <option value="all" <?php if ($filter==='all') echo 'selected'; ?>>All</option>
                <option value="unread" <?php if ($filter==='unread') echo 'selected'; ?>>Unread</option>
                <option value="1week" <?php if ($filter==='1week') echo 'selected'; ?>>1 Week</option>
                <option value="1month" <?php if ($filter==='1month') echo 'selected'; ?>>1 Month</option>
            </select>
            <button type="submit"><i class="fas fa-filter"></i> Apply</button>
        </form>
        
        <?php if (count($notifications) > 0): ?>
        <div class="bulk-action-bar">
            <input type="checkbox" id="selectAllCheckbox">
            <span style="font-weight: 600; font-size: 14px; margin-right: 10px; margin-left: 5px;">Select All</span>
            
            <button id="markAsReadBtn" class="icon-btn" onclick="bulkAction('read')" title="Mark as Read" disabled>
                <i class="fas fa-envelope-open"></i>
            </button>
            
            <button id="deleteSelectedBtn" class="icon-btn" onclick="bulkAction('delete')" title="Delete" disabled>
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <?php endif; ?>

        <ul class="notification-list">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $n): ?>
                    <li class="notification-item <?php echo $n['isRead'] ? '' : 'unread'; ?>" 
                        data-id="<?php echo $n['notificationID']; ?>"
                        onclick="markAsRead(<?php echo $n['notificationID']; ?>)"> 
                        
                        <input type="checkbox" class="notification-checkbox" value="<?php echo $n['notificationID']; ?>" 
                               onclick="event.stopPropagation();"> 
                        
                        <div class="notification-content-wrapper">
                            <div class="notification-content">
                                <?php echo $n['message']; ?>
                            </div>
                            
                            <div class="notification-time">
                                <?php echo date("M d, H:i", strtotime($n['createdAt'])); ?>
                            </div>
                            
                            <button class="icon-btn" onclick="deleteNotification(<?php echo $n['notificationID']; ?>); event.stopPropagation();" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center;">No notifications found 📭</p>
            <?php endif; ?>
        </ul>
    </div>
</div>

<script>
// Bulk and Individual handlers
document.addEventListener('DOMContentLoaded', () => {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.notification-checkbox');
    const markAsReadBtn = document.getElementById('markAsReadBtn');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');

    function updateBulkButtons() {
        const checkedCount = document.querySelectorAll('.notification-checkbox:checked').length;
        markAsReadBtn.disabled = checkedCount === 0;
        deleteSelectedBtn.disabled = checkedCount === 0;
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', (e) => {
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            updateBulkButtons();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
            updateBulkButtons();
        });
    });
});

function bulkAction(type) {
    const ids = Array.from(document.querySelectorAll('.notification-checkbox:checked')).map(cb => cb.value);
    if (!ids.length) return;
    const endpoint = (type === 'read') ? "bulk_update_notification.php" : "bulk_delete_notification.php";
    if (confirm(`Perform ${type} on ${ids.length} items?`)) {
        fetch(endpoint, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ids: ids })
        }).then(res => res.json()).then(data => { if(data.success) location.reload(); });
    }
}

function markAsRead(id) {
    const item = document.querySelector(`.notification-item[data-id="${id}"]`);
    if (item && item.classList.contains('unread')) {
        fetch("update_notification.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: id })
        }).then(res => res.json()).then(data => { if(data.success) item.classList.remove('unread'); });
    }
}

function deleteNotification(id) {
    if (confirm("Delete this notification?")) {
        fetch("delete_notification.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: id })
        }).then(res => res.json()).then(data => { 
            if(data.success) document.querySelector(`.notification-item[data-id="${id}"]`).remove(); 
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>