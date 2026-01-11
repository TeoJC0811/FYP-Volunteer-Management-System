<?php
include("../db.php"); // adjust path if needed

// 1. Choose a password
$password_plain = "admin123";

// 2. Hash it securely
$hashed = password_hash($password_plain, PASSWORD_DEFAULT);

// 3. Insert admin into DB
$sql = "INSERT INTO user 
(userName, userEmail, password, dateOfBorn, gender, country, phoneNumber, totalPoints, userRoles, qrCodeUrl)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$userName   = "admin2";
$userEmail  = "admin2@gmail.com";
$dateOfBorn = "2000-01-01";
$gender     = "Male";
$country    = "Malaysia";
$phone      = "0123456789";
$totalPoints= 0;
$userRoles  = "admin";
$qrCodeUrl  = NULL;

$stmt->bind_param(
    "ssssssisss",
    $userName,
    $userEmail,
    $hashed,
    $dateOfBorn,
    $gender,
    $country,
    $phone,
    $totalPoints,
    $userRoles,
    $qrCodeUrl
);

if ($stmt->execute()) {
    echo "✅ Admin account created! Email: $userEmail | Password: $password_plain";
} else {
    echo "❌ Error: " . $stmt->error;
}
