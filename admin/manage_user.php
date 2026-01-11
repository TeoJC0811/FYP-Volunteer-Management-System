<?php
session_start();
include("../db.php");

// Only allow admins
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$message = $error = "";

// Handle success/error from edit_user.php redirects
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "✅ User updated successfully!";
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case "UserNotFound":
            $error = "⚠️ User not found.";
            break;
        case "UpdateFailed":
            $error = "❌ Error updating user.";
            break;
        default:
            $error = "❌ An unknown error occurred.";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $userID = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM user WHERE userID = ?");
    $stmt->bind_param("i", $userID);

    if ($stmt->execute()) {
        $message = "✅ User deleted successfully!";
    } else {
        $error = "❌ Error deleting user.";
    }
}

// Filter & Search
$filterRole = isset($_GET['role']) ? $_GET['role'] : 'all';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : "";

$sql = "SELECT * FROM user WHERE 1=1";
$params = [];
$types = "";

if ($filterRole !== "all") {
    $sql .= " AND userRoles = ?";
    $params[] = $filterRole;
    $types .= "s";
}
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
    <title>Manage Users</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* --- UPDATED SEARCH BAR STYLING (FORUM STYLE) --- */
        .search-filter-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0 30px;
            padding: 15px;
            border-radius: 8px;
        }

        .search-filter-bar input {
            padding: 10px 12px;
            flex-grow: 1;
            max-width: 400px;
            border: 1px solid #ced4da;
            border-radius: 5px;
        }

        .search-filter-bar select {
            padding: 9px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            background: white;
        }

        .search-filter-bar button,
        .search-filter-bar a.btn-clear {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .search-filter-bar button {
            background-color: #3498db;
            color: white;
        }

        .search-filter-bar button:hover {
            background-color: #2980b9;
        }

        .search-filter-bar a.btn-clear {
            background-color: #e9ecef;
            color: #333;
        }

        .search-filter-bar a.btn-clear:hover {
            background-color: #dee2e6;
        }

        /* --- TABLE LAYOUT (UNCHANGED) --- */
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .styled-table th,
        .styled-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            word-wrap: break-word;
        }

        .styled-table th:nth-child(1),
        .styled-table td:nth-child(1) { width: 60px; } /* ID */
        .styled-table th:nth-child(2),
        .styled-table td:nth-child(2) { width: 200px; } /* Name */
        .styled-table th:nth-child(3),
        .styled-table td:nth-child(3) { width: 250px; } /* Email */
        .styled-table th:nth-child(4),
        .styled-table td:nth-child(4) { width: 120px; } /* Role */
        .styled-table th:nth-child(5),
        .styled-table td:nth-child(5) { width: 180px; } /* Actions */

        .btn-edit { background-color: #3f51b5; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px; }
        .btn-delete { background-color: #f44336; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; }
    </style>
</head>
<body>
    <?php include("sidebar.php"); ?>

    <div class="main-content">
        <h2>Manage Users</h2>

        <?php if (!empty($message)) echo "<p class='success' style='color: green; font-weight: bold;'>$message</p>"; ?>
        <?php if (!empty($error)) echo "<p class='error' style='color: red; font-weight: bold;'>$error</p>"; ?>

        <div class="search-filter-bar">
            <form method="get" action="manage_user.php" style="display: flex; gap: 10px; width: 100%; justify-content: center; align-items: center;">
                <input type="text" name="search" placeholder="Search by name or email..." 
                    value="<?= htmlspecialchars($searchQuery) ?>">
                
                <select name="role">
                    <option value="all" <?= ($filterRole=='all') ? 'selected' : '' ?>>All Roles</option>
                    <option value="admin" <?= ($filterRole=='admin') ? 'selected' : '' ?>>Admin</option>
                    <option value="organizer" <?= ($filterRole=='organizer') ? 'selected' : '' ?>>Organizer</option>
                    <option value="user" <?= ($filterRole=='user') ? 'selected' : '' ?>>User</option>
                </select>

                <button type="submit"><i class="fas fa-search"></i> Search</button>
                <a href="manage_user.php" class="btn-clear"><i class="fas fa-undo"></i> Clear</a>
            </form>
        </div>

        <table class="styled-table">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['userID']; ?></td>
                <td><?= htmlspecialchars($row['userName']); ?></td>
                <td><?= htmlspecialchars($row['userEmail']); ?></td>
                <td><?= htmlspecialchars($row['userRoles']); ?></td>
                <td class="action-buttons">
                    <a href="edit_user.php?id=<?= $row['userID']; ?>" class="btn-edit">Edit</a>
                    <a href="manage_user.php?delete=<?= $row['userID']; ?>" 
                       onclick="return confirm('Are you sure you want to delete this user?');" 
                       class="btn-delete">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>