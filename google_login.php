<?php
require_once 'vendor/autoload.php';
session_start();

/*
|--------------------------------------------------------------------------
| DYNAMIC BASE URL
|--------------------------------------------------------------------------
*/
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($host, ['localhost', '127.0.0.1']);
$scheme = $isLocal ? 'http' : 'https';

// Adjust this folder name if your project folder is different
$baseUrl = $scheme . '://' . $host . '/servetogether';

/*
|--------------------------------------------------------------------------
| GOOGLE CLIENT SETUP
|--------------------------------------------------------------------------
*/
$client = new Google_Client();
$client->setClientId('147195553585-4sj8v86c32216duh7jhn1jco1grt57lh.apps.googleusercontent.com');
$client->setClientSecret('REPLACE_WITH_YOUR_SECRET_ON_SERVER');
$client->setRedirectUri($baseUrl . '/google_callback.php');

$client->addScope('email');
$client->addScope('profile');
$client->setPrompt('select_account');

/*
|--------------------------------------------------------------------------
| QR CHECK-IN INTEGRATION
|--------------------------------------------------------------------------
| If the user was redirected here from a QR code, preserve that URL
*/
if (!empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

/* REDIRECT TO GOOGLE */
header('Location: ' . $client->createAuthUrl());
exit;