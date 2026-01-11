<?php
require_once 'vendor/autoload.php';
session_start();

/*
|--------------------------------------------------------------------------
| DYNAMIC BASE URL
|--------------------------------------------------------------------------
| Logic to handle both Localhost (with subfolder) and Render (root)
*/
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($host, ['localhost', '127.0.0.1']);
$scheme = $isLocal ? 'http' : 'https';

if ($isLocal) {
    // Your local path with the subfolder
    $baseUrl = $scheme . '://' . $host . '/servetogether';
} else {
    // Your Render path (No subfolder)
    $baseUrl = $scheme . '://' . $host;
}

/*
|--------------------------------------------------------------------------
| GOOGLE CLIENT SETUP
|--------------------------------------------------------------------------
*/
$client = new Google_Client();
$client->setClientId('147195553585-4sj8v86c32216duh7jhn1jco1grt57lh.apps.googleusercontent.com');

/**
 * ⚠️ ACTION REQUIRED: 
 * Replace 'YOUR_ACTUAL_GOOGLE_SECRET' with your real Client Secret 
 * from the Google Cloud Console.
 */
$client->setClientSecret('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');

// This will now correctly be: https://servetogetherfyp.onrender.com/google_callback.php
$client->setRedirectUri($baseUrl . '/google_callback.php');

$client->addScope('email');
$client->addScope('profile');
$client->setPrompt('select_account');

/*
|--------------------------------------------------------------------------
| QR CHECK-IN INTEGRATION
|--------------------------------------------------------------------------
*/
if (!empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

/* REDIRECT TO GOOGLE */
header('Location: ' . $client->createAuthUrl());
exit;