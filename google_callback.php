<?php
require_once 'vendor/autoload.php';
session_start();
include('db.php');

use Google\Client;
use Google\Service\Oauth2;

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($host, ['localhost', '127.0.0.1']);

if ($isLocal) {
    $baseUrl = 'http://localhost/servetogether';
} else {
    $baseUrl = 'https://' . $host; 
}

$client = new Client();
$client->setClientId('147195553585-4sj8v86c32216duh7jhn1jco1grt57lh.apps.googleusercontent.com');

// Professional Secret Logic
$googleSecret = getenv('GOOGLE_CLIENT_SECRET');
if (!$googleSecret && $isLocal) {
    $googleSecret = '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'; 
}
$client->setClientSecret($googleSecret);

$client->setRedirectUri($baseUrl . '/google_callback.php');
$client->addScope('email');
$client->addScope('profile');

if (!isset($_GET['code'])) {
    die("⚠️ Google login failed. No authorization code received.");
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) {
    die("⚠️ Google authentication error: " . htmlspecialchars($token['error']));
}

$client->setAccessToken($token['access_token']);
$oauth = new Oauth2($client);
$googleUser = $oauth->userinfo->get();

$userName = trim($googleUser->name ?? '');
$email    = trim($googleUser->email ?? '');

if (empty($email)) {
    die("⚠️ Unable to retrieve Google account email.");
}

// Check database
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
    $insert = $conn->prepare("INSERT INTO user (userName, userEmail, password, userRoles) VALUES (?, ?, '', 'user')");
    $insert->bind_param("ss", $userName, $email);
    $insert->execute();

    $_SESSION['userID']   = (int)$conn->insert_id;
    $_SESSION['userName'] = $userName;
    $_SESSION['role']     = 'user';
}

if (isset($_SESSION['redirect_after_login'])) {
    $redirectTo = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
    header("Location: " . $redirectTo);
} else {
    header("Location: $baseUrl/index.php");
}
exit;