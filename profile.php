<?php
session_start();
require 'db.php';

if (!isset($_SESSION['userID'])) {
    die("User not logged in.");
}

$userID = $_SESSION['userID'];

// Fetch user data - renamed variable to $profileUser to avoid conflicts
$query = "SELECT userName, userEmail, dateOfBorn, gender, address, country, phoneNumber 
          FROM user WHERE userID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$profileUser = $result->fetch_assoc();

if (!$profileUser) {
    die("⚠️ No user found with userID = $userID");
}

// Fetch interests
$interests = [];
$categoryQuery = "
    SELECT categoryName 
    FROM usercategory 
    JOIN category ON usercategory.categoryID = category.categoryID 
    WHERE usercategory.userID = ?
";
$catStmt = $conn->prepare($categoryQuery);
$catStmt->bind_param("i", $userID);
$catStmt->execute();
$catResult = $catStmt->get_result();
while ($row = $catResult->fetch_assoc()) {
    $interests[] = $row['categoryName'];
}

function safe($value) {
    if ($value === '0000-00-00') {
        return "<span class='empty'>Not set</span>";
    }
    return $value !== null && $value !== '' 
        ? htmlspecialchars($value) 
        : "<span class='empty'>Not provided</span>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile</title>
<link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
/* ... (Keep all your existing styles exactly as they are) ... */
body { background: #f4f6fb; font-family: 'Helvetica Neue', Arial, sans-serif; color: #111827; }
.profile-wrapper { max-width: 900px; margin: 40px auto; padding: 20px; }
.profile-header { background: #2575fc; color: white; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.15); margin-bottom: 30px; }
.profile-header h2 { margin: 0; font-size: 28px; font-weight: 700; color: white; }
.profile-header p { margin-top: 6px; opacity: .9; }
.profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
.profile-card { background: white; border-radius: 14px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,.05); overflow: hidden; }
.profile-card h4 { background: #e6f0ff; margin: 0; padding: 10px 15px; font-size: 14px; color: #111827; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; border-bottom: 1px solid #ddd; }
.profile-card-content { padding: 15px; }
.profile-card p { margin: 0; font-size: 16px; color: #111827; font-weight: 500; }
.empty { color: #9ca3af; font-style: italic; }
.interest-tags { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 5px; }
.interest-tag { background: #e6f0ff; color: #111827; padding: 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 500; }
.profile-actions { margin-top: 30px; text-align: right; }
.profile-actions a { background: #2575fc; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 600; transition: background .2s; }
.profile-actions a:hover { background: #1b5ed8; }
@media (min-width: 900px) { .profile-card.span-two { grid-column: span 2; } }
</style>
</head>

<body>

<?php include("user_navbar.php"); ?>

<div class="profile-wrapper">

    <div class="profile-header">
        <h2><?= htmlspecialchars($profileUser['userName']) ?></h2>
        <p><?= htmlspecialchars($profileUser['userEmail']) ?></p>
    </div>

    <div class="profile-grid">

        <div class="profile-card">
            <h4>Date of Birth</h4>
            <div class="profile-card-content">
                <p><?= $profileUser['dateOfBorn'] !== '0000-00-00' ? safe($profileUser['dateOfBorn']) : "<span class='empty'>Not set</span>" ?></p>
            </div>
        </div>

        <div class="profile-card">
            <h4>Gender</h4>
            <div class="profile-card-content">
                <p><?= safe($profileUser['gender']) ?></p>
            </div>
        </div>
        
        <div class="profile-card">
            <h4>Phone Number</h4>
            <div class="profile-card-content">
                <p><?= safe($profileUser['phoneNumber']) ?></p>
            </div>
        </div>
        
        <div class="profile-card">
            <h4>Country</h4>
            <div class="profile-card-content">
                <p><?= safe($profileUser['country']) ?></p>
            </div>
        </div>

        <div class="profile-card span-two">
            <h4>Address</h4>
            <div class="profile-card-content">
                <p><?= safe($profileUser['address']) ?></p>
            </div>
        </div>

        <div class="profile-card span-two">
            <h4>Interests</h4>
            <div class="profile-card-content">
                <div class="interest-tags">
                    <?php if (!empty($interests)): ?>
                        <?php foreach ($interests as $interest): ?>
                            <span class="interest-tag"><?= htmlspecialchars($interest) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="empty">No interests selected.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <div class="profile-actions">
        <a href="edit_profile.php">✏️ Edit Profile</a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>