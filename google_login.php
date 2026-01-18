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

if ($isLocal) {
    // 🏠 Localhost: Use http and ensure subfolder is included
    // Make sure 'servetogether' matches your folder name in htdocs exactly
    $baseUrl = 'http://localhost/servetogether';
} else {
    // 🌐 Render: Use https and no subfolder
    $baseUrl = 'https://' . $host;
}

/*
|--------------------------------------------------------------------------
| GOOGLE CLIENT SETUP
|--------------------------------------------------------------------------
*/
$client = new Google_Client();
$client->setClientId('147195553585-4sj8v86c32216duh7jhn1jco1grt57lh.apps.googleusercontent.com');

/**
 * 🔒 CLIENT SECRET LOGIC
 * Pulls from Render Environment Variables on live, 
 * or uses the hardcoded string on Localhost.
 */
$googleSecret = getenv('GOOGLE_CLIENT_SECRET');

if (!$googleSecret && $isLocal) {
    // ⚠️ PASTE YOUR ACTUAL SECRET HERE (The one ending in ...TSUL)
    $googleSecret = 'YOUR_ACTUAL_CLIENT_SECRET_HERE'; 
}

$client->setClientSecret($googleSecret);

// This MUST match the entry in Google Cloud Console exactly
$redirectUri = $baseUrl . '/google_callback.php';
$client->setRedirectUri($redirectUri);

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

/* REDIRECT TO GOOGLE AUTHORIZATION SERVER */
header('Location: ' . $client->createAuthUrl());
exit;