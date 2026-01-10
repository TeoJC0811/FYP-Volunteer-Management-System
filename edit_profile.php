<?php
session_start();
require 'db.php';

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// Fetch user info
$stmt = $conn->prepare("SELECT userName, userEmail, dateOfBorn, gender, address, country, phoneNumber 
                        FROM user WHERE userID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$profileUser = $result->fetch_assoc();
$stmt->close();

if (!$profileUser) {
    die("⚠️ No user found.");
}

// Fetch categories
$categories = [];
$categoryQuery = $conn->query("SELECT categoryID, categoryName FROM category");
while ($row = $categoryQuery->fetch_assoc()) {
    $categories[$row['categoryID']] = $row['categoryName'];
}

// Fetch selected categories
$selectedCategories = [];
$catResult = $conn->query("SELECT categoryID FROM usercategory WHERE userID = $userID");
while ($row = $catResult->fetch_assoc()) {
    $selectedCategories[] = $row['categoryID'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        html, body { height: 100%; margin: 0; display: flex; flex-direction: column; background-color: #f4f4f4; }
        .container { flex: 1; }
        .form-container { max-width: 600px; margin: 30px auto 50px; padding: 25px; background: #f9f9f9; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        fieldset { border: 1px solid #ccc; border-radius: 8px; padding: 15px; margin-top: 20px; }
        legend { font-weight: bold; padding: 0 10px; }
        .profile-buttons { display: flex; justify-content: center; gap: 15px; margin-top: 25px; }
        .btn, button { padding: 10px 18px; border: none; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none; color: #fff; }
        .btn-update { background-color: #007b00; }
        .btn-update:hover { background-color: #009900; }
        .btn-cancel { background-color: #b22222; }
        .btn-cancel:hover { background-color: #8b1a1a; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 10px; }
        .category-box { padding: 15px; border: 2px solid #ccc; border-radius: 15px; cursor: pointer; transition: 0.3s; background-color: white; text-align: center; font-weight: bold; user-select: none; }
        .category-box.selected { background-color: #007BFF; color: white; border-color: #007BFF; }
        .hidden-checkbox { display: none; }
        .back-btn{ margin-top: 30px; margin-left: 270px; text-decoration: none;}
        
        /* Hint text style */
        .input-hint { font-size: 12px; color: #666; margin-top: 4px; display: block; }
        
        /* New styling for Readonly field */
        .readonly-field { background-color: #eeeeee; cursor: not-allowed; border: 1px solid #ddd; color: #777; }
    </style>
</head>
<body>

<?php include("user_navbar.php"); ?>

<div class="container">
    <div class="back-btn-container">
        <a href="profile.php" class="back-btn">← Back to Profile</a>
    </div>

    <div class="form-container">
        <h2 style="text-align: center; margin-bottom: 25px;">✏️ Edit Profile</h2>

        <form action="update_profile.php" method="post">
            <fieldset>
                <legend>👤 Personal Information</legend>

                <label for="userName">Username *</label>
                <input type="text" name="userName" id="userName" value="<?= htmlspecialchars($profileUser['userName'] ?? '') ?>" required>

                <label for="userEmail">Email</label>
                <input type="email" id="userEmail" class="readonly-field" value="<?= htmlspecialchars($profileUser['userEmail'] ?? '') ?>" readonly>
                <span class="input-hint">Your email cannot be changed.</span>

                <label for="dateOfBorn">Date of Birth</label>
                <input type="date" name="dateOfBorn" id="dateOfBorn" value="<?= htmlspecialchars($profileUser['dateOfBorn'] ?? '') ?>">

                <label for="gender">Gender</label>
                <select name="gender" id="gender">
                    <option value="" <?= empty($profileUser['gender']) ? 'selected' : '' ?>>Not specified</option>
                    <option value="Male" <?= (($profileUser['gender'] ?? '') == 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= (($profileUser['gender'] ?? '') == 'Female') ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= (($profileUser['gender'] ?? '') == 'Other') ? 'selected' : '' ?>>Other</option>
                </select>
            </fieldset>

            <fieldset>
                <legend>🌍 Contact Information</legend>

                <label for="address">Address</label>
                <input type="text" name="address" id="address" value="<?= htmlspecialchars($profileUser['address'] ?? '') ?>">

                <label for="country">Country</label>
                <select name="country" id="country">
                    <option value="" <?= empty($profileUser['country']) ? 'selected' : '' ?>>Choose Country...</option>
                    <?php
                    $enumQuery = $conn->query("SHOW COLUMNS FROM user LIKE 'country'");
                    $row = $enumQuery->fetch_assoc();
                    preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
                    $enumValues = explode(",", $matches[1]);
                    foreach ($enumValues as $value) {
                        $cleanValue = trim($value, "'");
                        $selected = (($profileUser['country'] ?? '') == $cleanValue) ? 'selected' : '';
                        echo "<option value=\"$cleanValue\" $selected>$cleanValue</option>";
                    }
                    ?>
                </select>

                <label for="phoneNumber">Phone Number</label>
                <input type="text" name="phoneNumber" id="phoneNumber" 
                       placeholder="011-1234567" 
                       value="<?= htmlspecialchars($profileUser['phoneNumber'] ?? '') ?>">
                <span class="input-hint">Format: 011-12345678 (Dashes will be added automatically)</span>
            </fieldset>

            <fieldset>
                <legend>⭐ Interests</legend>
                <div class="grid">
                    <?php foreach ($categories as $id => $name):
                        $isSelected = in_array($id, $selectedCategories);
                    ?>
                        <label class="category-box <?= $isSelected ? 'selected' : '' ?>" onclick="toggleSelection(this)">
                            <input type="checkbox" name="categories[]" value="<?= $id ?>" class="hidden-checkbox" <?= $isSelected ? 'checked' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="profile-buttons">
                <button type="submit" class="btn btn-update">Update Profile</button>
                <a href="profile.php" class="btn btn-cancel">❌ Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleSelection(label) {
        const checkbox = label.querySelector('input[type=checkbox]');
        checkbox.checked = !checkbox.checked;
        label.classList.toggle('selected', checkbox.checked);
    }

    document.getElementById('phoneNumber').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 3) {
            value = value.slice(0, 3) + '-' + value.slice(3);
        }
        e.target.value = value.slice(0, 12);
    });
</script>

<?php include 'includes/footer.php'; ?>

</body>
</html>