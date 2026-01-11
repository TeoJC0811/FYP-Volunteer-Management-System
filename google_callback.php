<?php
require_once 'vendor/autoload.php';
session_start();
include('db.php');

use Google\Client;
use Google\Service\Oauth2;

/*
|--------------------------------------------------------------------------
| DYNAMIC BASE URL (Handles Local vs Render)
|--------------------------------------------------------------------------
*/
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($host, ['localhost', '127.0.0.1']);
$scheme = $isLocal ? 'http' : 'https';

if ($isLocal) {
    $baseUrl = $scheme . '://' . $host . '/servetogether';
} else {
    $baseUrl = $scheme . '://' . $host; // No subfolder on Render
}

/*
|--------------------------------------------------------------------------
| GOOGLE CLIENT CONFIG
|--------------------------------------------------------------------------
*/
$client = new Client();
$client->setClientId('147195553585-4sj8v86c32216duh7jhn1jco1grt57lh.apps.googleusercontent.com');

// ⚠️ REPLACE THIS with your actual secret ending in TSUL
$client->setClientSecret('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');

/* MUST match the logic used in google_login.php */
$client->setRedirectUri($baseUrl . '/google_callback.php');

$client->addScope('email');
$client->addScope('profile');

/*
|--------------------------------------------------------------------------
| VALIDATE CALLBACK
|--------------------------------------------------------------------------
*/
if (!isset($_GET['code'])) {
    die("⚠️ Google login failed. No authorization code received.");
}

/*
|--------------------------------------------------------------------------
| EXCHANGE CODE FOR TOKEN
|--------------------------------------------------------------------------
*/
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    // This is where 'invalid_client' was being caught
    die("⚠️ Google authentication error: " . htmlspecialchars($token['error']));
}

$client->setAccessToken($token['access_token']);

/*
|--------------------------------------------------------------------------
| FETCH GOOGLE USER INFO
|--------------------------------------------------------------------------
*/
$oauth = new Oauth2($client);
$googleUser = $oauth->userinfo->get();

$userName = trim($googleUser->name ?? '');
$email    = trim($googleUser->email ?? '');

if (empty($email)) {
    die("⚠️ Unable to retrieve Google account email.");
}

/*
|--------------------------------------------------------------------------
| LOGIN OR REGISTER USER
|--------------------------------------------------------------------------
| Note: Check your table name case! (user vs User)
*/
$stmt = $conn->prepare("SELECT userID, userName, userRoles FROM user WHERE userEmail = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $_SESSION['userID']   = (int)$user['userID'];
    $_SESSION['userName'] = $user['userName'];
    $_SESSION['role']     = $user['userRoles'];
} else {
    $insert = $conn->prepare("
        INSERT INTO user (userName, userEmail, password, userRoles)
        VALUES (?, ?, '', 'user')
    ");
    $insert->bind_param("ss", $userName, $email);
    $insert->execute();

    $_SESSION['userID']   = (int)$conn->insert_id;
    $_SESSION['userName'] = $userName;
    $_SESSION['role']     = 'user';
}

/*
|--------------------------------------------------------------------------
| REDIRECTS
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['pending_checkin']) && is_array($_SESSION['pending_checkin'])) {
    $query = http_build_query($_SESSION['pending_checkin']);
    unset($_SESSION['pending_checkin'], $_SESSION['oauth_redirect'], $_SESSION['redirect_after_login']);
    header("Location: $baseUrl/checkin.php?$query");
    exit;
}

header("Location: $baseUrl/index.php");
exit;