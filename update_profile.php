<?php
session_start();
require 'db.php';

// 1. Security Check: Ensure user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userID = $_SESSION['userID'];

    // 2. Sanitize and Collect Input
    // Note: userEmail is NOT collected because it is readonly on the form
    $userName    = mysqli_real_escape_string($conn, $_POST['userName']);
    $dateOfBorn  = mysqli_real_escape_string($conn, $_POST['dateOfBorn']);
    $gender      = mysqli_real_escape_string($conn, $_POST['gender']);
    $address     = mysqli_real_escape_string($conn, $_POST['address']);
    $country     = mysqli_real_escape_string($conn, $_POST['country']);
    $phoneNumber = mysqli_real_escape_string($conn, $_POST['phoneNumber']);
    
    // Categories/Interests array
    $selectedCategories = isset($_POST['categories']) ? $_POST['categories'] : [];

    // 3. Update User Table (Excluding Email)
    $updateUserSql = "UPDATE user SET 
                        userName = ?, 
                        dateOfBorn = ?, 
                        gender = ?, 
                        address = ?, 
                        country = ?, 
                        phoneNumber = ? 
                      WHERE userID = ?";
    
    $stmt = $conn->prepare($updateUserSql);
    $stmt->bind_param("ssssssi", $userName, $dateOfBorn, $gender, $address, $country, $phoneNumber, $userID);

    if ($stmt->execute()) {
        
        // 4. Update Interests (Categories)
        // Step A: Remove all old interests for this user
        $deleteOldCats = $conn->prepare("DELETE FROM usercategory WHERE userID = ?");
        $deleteOldCats->bind_param("i", $userID);
        $deleteOldCats->execute();
        $deleteOldCats->close();

        // Step B: Insert the new checked interests
        if (!empty($selectedCategories)) {
            $insertCat = $conn->prepare("INSERT INTO usercategory (userID, categoryID) VALUES (?, ?)");
            foreach ($selectedCategories as $categoryID) {
                $insertCat->bind_param("ii", $userID, $categoryID);
                $insertCat->execute();
            }
            $insertCat->close();
        }

        // 5. Success! Redirect to profile page
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();

    } else {
        // Handle database errors
        echo "Error updating profile: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
} else {
    // If someone tries to access this file directly via URL
    header("Location: edit_profile.php");
    exit();
}
?>