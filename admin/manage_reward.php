<?php
session_start();
include("../db.php");

$message = $error = "";

// Handle delete
if (isset($_GET['delete'])) {
    $rewardID = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM reward WHERE rewardID = ?");
    $stmt->bind_param("i", $rewardID);
    if ($stmt->execute()) {
        $message = "✅ Reward deleted successfully!";
    } else {
        $error = "❌ Error deleting reward.";
    }
}

// Messages
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "✅ Reward updated successfully!";
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case "RewardNotFound": $error = "⚠️ Reward not found."; break;
        case "UploadFailed": $error = "⚠️ Failed to upload image."; break;
        case "UpdateFailed": $error = "❌ Error updating reward."; break;
        default: $error = "❌ An unknown error occurred.";
    }
}

// --- SORTING LOGIC ---
$search = $_GET['search'] ?? "";
$sort = $_GET['sort'] ?? "newest";

// Define sorting options based on selection
switch ($sort) {
    case 'oldest':
        $order_by = "rewardID ASC";
        break;
    case 'points_desc':
        $order_by = "pointRequired DESC";
        break;
    case 'points_asc':
        $order_by = "pointRequired ASC";
        break;
    case 'name_asc':
        $order_by = "rewardName ASC";
        break;
    case 'newest':
    default:
        $order_by = "rewardID DESC";
        break;
}

// --- FETCH DATA ---
if (!empty($search)) {
    $stmt = $conn->prepare("
        SELECT * FROM reward
        WHERE rewardName LIKE ? OR description LIKE ?
        ORDER BY $order_by
    ");
    $term = "%$search%";
    $stmt->bind_param("ss", $term, $term);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM reward ORDER BY $order_by");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Rewards - Admin</title>
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ===== Variables & Root ===== */
:root { 
    --primary-color: #3f51b5; 
    --border-radius: 8px; 
    --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
}

body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; }

/* ===== Success/Error Boxes ===== */
.success, .error { padding: 12px 15px; margin-bottom: 20px; border-radius: var(--border-radius); font-weight: bold; }
.success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c3e6cb; }
.error { background-color: #ffebee; color: #c62828; border: 1px solid #f5c6cb; }

/* ===== Search Bar (Updated with Sort) ===== */
.search-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 20px 0 30px;
    padding: 15px;
    background: #f4f7f9;
    border-radius: var(--border-radius);
}

.search-bar input {
    padding: 10px 12px;
    flex-grow: 1;
    max-width: 400px;
    border: 1px solid #ced4da;
    border-radius: 5px;
}

/* Added select styling to match your buttons */
.search-bar select {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 5px;
    background: white;
    cursor: pointer;
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
    transition: background 0.2s;
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
    transition: background 0.2s;
}

.search-bar a.btn-clear:hover { background-color: #dee2e6; }

/* ===== Reward Grid ===== */
.reward-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    padding-top: 10px;
}

/* ===== Reward Card ===== */
.reward-card {
    background: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s;
}

.reward-card:hover { transform: translateY(-3px); }

.reward-img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    background: #f9f9f9;
    border-bottom: 1px solid #eee;
}

.reward-body {
    padding: 16px;
    flex: 1;
}

.reward-title {
    font-size: 1.1em;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 8px;
}

.reward-desc {
    font-size: 0.9em;
    color: #666;
    line-height: 1.5;
    margin-bottom: 15px;
    min-height: 40px;
}

/* ===== Points Badge ===== */
.point-badge {
    display: inline-block;
    background: #fff8e1;
    color: #ff8f00;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.85rem;
    border: 1px solid #ffe082;
}

/* ===== Card Actions ===== */
.reward-actions {
    display: flex;
    gap: 10px;
    padding: 15px;
    background: #fafafa;
    border-top: 1px solid #eee;
}

.reward-actions a {
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

.main-content { padding: 20px; }
</style>
</head>

<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <h2><i class="fas fa-gift"></i> Manage Reward Catalog</h2>

    <?php if ($message) echo "<p class='success'>$message</p>"; ?>
    <?php if ($error) echo "<p class='error'>$error</p>"; ?>

    <form method="get" class="search-bar">
        <input type="text" name="search" placeholder="Search by reward name or description..." value="<?= htmlspecialchars($search) ?>">
        
        <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
            <option value="points_desc" <?= $sort == 'points_desc' ? 'selected' : '' ?>>Points (High-Low)</option>
            <option value="points_asc" <?= $sort == 'points_asc' ? 'selected' : '' ?>>Points (Low-High)</option>
        </select>

        <button type="submit"><i class="fas fa-search"></i> Search</button>
        <a href="manage_reward.php" class="btn-clear"><i class="fas fa-undo"></i> Clear</a>
    </form>

    <div class="reward-grid">
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="reward-card">
            <?php if (!empty($row['rewardImage'])): ?>
                <img src="../<?= htmlspecialchars($row['rewardImage']) ?>" class="reward-img" alt="Reward">
            <?php else: ?>
                <div class="reward-img" style="display:flex; align-items:center; justify-content:center; color:#ccc;">
                    <i class="fas fa-image fa-3x"></i>
                </div>
            <?php endif; ?>

            <div class="reward-body">
                <div class="reward-title"><?= htmlspecialchars($row['rewardName']) ?></div>
                <div class="reward-desc"><?= htmlspecialchars($row['description']) ?></div>
                <span class="point-badge"><i class="fas fa-star"></i> <?= $row['pointRequired'] ?> pts</span>
            </div>

            <div class="reward-actions">
                <a href="edit_reward.php?id=<?= $row['rewardID'] ?>" class="btn-edit">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="manage_reward.php?delete=<?= $row['rewardID'] ?>"
                   class="btn-delete"
                   onclick="return confirm('Are you sure you want to delete this reward?');">
                    <i class="fas fa-trash-alt"></i> Delete
                </a>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="grid-column: 1 / -1; text-align:center; padding: 50px; background:white; border-radius:8px; box-shadow: var(--box-shadow);">
            <i class="fas fa-search fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
            <p style="color:#777;">No rewards found matching your search.</p>
        </div>
    <?php endif; ?>
    </div>
</div>
</body>
</html>