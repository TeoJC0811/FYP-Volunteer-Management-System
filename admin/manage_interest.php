<?php
session_start();
include("../db.php");

// Only allow admins
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

$message = $error = "";

// Handle success/error from edit_interest.php redirects
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "✅ Interest updated successfully!";
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case "InterestNotFound":
            $error = "⚠️ Interest not found.";
            break;
        case "UpdateFailed":
            $error = "❌ Error updating interest.";
            break;
        default:
            $error = "❌ An unknown error occurred.";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $categoryID = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM category WHERE categoryID = ?");
    $stmt->bind_param("i", $categoryID);

    if ($stmt->execute()) {
        $message = "✅ Interest deleted successfully!";
    } else {
        $error = "❌ Error deleting interest.";
    }
}

// Fetch all interests
$result = $conn->query("SELECT * FROM category ORDER BY categoryID ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Interests</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include("sidebar.php"); ?>

    <div class="main-content">
        <h2>Manage Interests</h2>

        <?php if (!empty($message)) echo "<p class='success'>$message</p>"; ?>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

        <table class="styled-table">
            <tr>
                <th>ID</th>
                <th>Interest Name</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['categoryID']; ?></td>
                <td><?php echo htmlspecialchars($row['categoryName']); ?></td>
                <td class="action-buttons">
                    <a href="edit_interest.php?id=<?php echo $row['categoryID']; ?>" class="btn btn-edit">Edit</a>
                    <a href="manage_interest.php?delete=<?php echo $row['categoryID']; ?>" 
                       onclick="return confirm('Are you sure you want to delete this interest?');" 
                       class="btn btn-delete">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
