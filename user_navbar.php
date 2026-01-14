<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db.php'; 

// Set timezone to match your system/database
date_default_timezone_set('Asia/Kuala_Lumpur');

$userID = $_SESSION['userID'] ?? null;
$userName = $_SESSION['userName'] ?? null;
$role = $_SESSION['role'] ?? null;

$unreadCount = 0;
$isApprovedOrganizer = false;

if ($userID) {
    // 1. Initialize ID Tracker: Capture the current max ID so we don't popup old notifications on page load
    if (!isset($_SESSION['last_toasted_id'])) {
        $stmtInit = $conn->prepare("SELECT MAX(notificationID) as maxID FROM notification WHERE userID = ?");
        $stmtInit->bind_param("i", $userID);
        $stmtInit->execute();
        $resInit = $stmtInit->get_result();
        $initData = $resInit->fetch_assoc();
        $_SESSION['last_toasted_id'] = $initData['maxID'] ?? 0;
        $stmtInit->close();
    }

    // 2. Initial Notification Count for the Bell Icon
    $stmtNoti = $conn->prepare("SELECT COUNT(*) as unread FROM notification WHERE userID = ? AND isRead = 0");
    $stmtNoti->bind_param("i", $userID);
    $stmtNoti->execute();
    $resNoti = $stmtNoti->get_result();
    $notiData = $resNoti->fetch_assoc(); 
    $unreadCount = $notiData['unread'] ?? 0;
    $stmtNoti->close();

    // 3. Check if the user is an Approved Organizer
    $stmtStatus = $conn->prepare("SELECT userRoles, status FROM user WHERE userID = ?");
    $stmtStatus->bind_param("i", $userID);
    $stmtStatus->execute();
    $resStatus = $stmtStatus->get_result();
    if ($statusData = $resStatus->fetch_assoc()) {
        // Check if role contains organizer and status is approved
        if (strpos($statusData['userRoles'], 'organizer') !== false && $statusData['status'] === 'approved') {
            $isApprovedOrganizer = true;
        }
    }
    $stmtStatus->close();
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<audio id="notifSound" src="notificationaudio.wav" preload="auto"></audio>

<div id="toastContainer" class="toast-container"></div>

<nav>
    <div class="nav-left">
        <div class="nav-logo">
            <a href="index.php">
                <img src="uploads/ServeTogetherIcon1.png" alt="ServeTogether Logo" style="height:40px;">
            </a>
        </div>
        <div class="nav-links">
            <a href="volunteer_event.php">Volunteer Event</a>
            <a href="training_course.php">Training Course</a>
            <a href="community_forum.php">Community Forum</a>
            <a href="reward.php">Reward</a>
        </div>
    </div>

    <div class="nav-right">
        <?php if ($userName): ?>
            <div class="nav-item-wrapper">
                <a href="notification.php" class="noti-bell" id="notiBell" onclick="hideRedDot()">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="noti-dot" id="notiDot"></span>
                    <?php endif; ?>
                </a>

                <a href="#" onclick="toggleProfileMenu(event)" class="profile-toggle">
                    <i class="fa-solid fa-circle-user"></i> 
                    <span>Welcome, <?= htmlspecialchars($userName) ?></span>
                </a>
            </div>

            <div class="profile-menu" id="profileMenu" style="display:none;">
                <a href="profile.php">Profile</a>
                <a href="history.php">History</a>
                <a href="schedule.php">Schedule</a>
                <a href="wishlist.php">Wishlist</a>

                <?php if ($isApprovedOrganizer): ?>
                    <a href="admin/index.php" style="color: #2ecc71; font-weight: bold;">Organizer Portal</a>
                <?php endif; ?>

                <?php if ($role === 'admin'): ?>
                    <a href="admin/index.php" style="color: #3498db; font-weight: bold;">Admin Panel</a>
                <?php endif; ?>

                <a href="logout.php">Logout</a>
            </div>

        <?php else: ?>
            <div class="auth-buttons">
                <a href="login.php" class="btn-login">Login</a>
                <a href="register.php" class="btn-signup">Sign Up</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<style>
/* --- TOAST STYLING --- */
.toast-container { position: fixed; top: 20px; right: 20px; z-index: 10002; }
.toast-box { 
    background: white; 
    width: 320px; 
    border-radius: 8px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
    border: 1px solid #eee; 
    overflow: hidden; 
    animation: slideIn 0.5s ease forwards; 
}
.toast-header { 
    background: #ffffff; 
    padding: 12px 15px; 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    font-size: 14px; 
    font-weight: 600; 
    border-bottom: 1px solid #f0f0f0; 
}
.header-left { display: flex; align-items: center; gap: 8px; color: #333; }
.header-left i { color: #555; }
.toast-header button { background: none; border: none; font-size: 20px; cursor: pointer; color: #bbb; line-height: 1; }
.toast-body { padding: 15px; font-size: 13px; color: #555; line-height: 1.5; background: #fff; }

@keyframes slideIn { from { transform: translateX(120%); } to { transform: translateX(0); } }
@keyframes slideOut { from { transform: translateX(0); } to { transform: translateX(120%); } }

/* --- NAVIGATION STYLES --- */
nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; position: relative; }
nav a, .profile-menu a { text-decoration: none !important; color: #333 !important; transition: background 0.2s ease; display: flex; align-items: center; }
.nav-links a:hover, .noti-bell:hover, .profile-toggle:hover, .profile-menu a:hover { background-color: rgba(0, 0, 0, 0.05) !important; border-radius: 8px; }
.nav-left { display: flex; align-items: center; gap: 20px; }
.nav-links { display: flex; align-items: center; gap: 5px; }
.nav-item-wrapper { display: flex; align-items: center; gap: 10px; }
.nav-links a, .profile-toggle, .noti-bell { padding: 8px 12px; }
.profile-toggle i { margin-right: 10px; }
.noti-bell { position: relative; font-size: 20px; }
.noti-dot { position: absolute; top: 6px; right: 8px; height: 10px; width: 10px; background-color: #ff4d4d; border-radius: 50%; border: 2px solid white; }

.profile-menu { position: absolute; right: 20px; top: 58px; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 9999; width: 185px; overflow: hidden; }
.profile-menu a { display: block; padding: 12px 15px; font-size: 14px; }

.auth-buttons { display: flex; gap: 15px; align-items: center; }
.btn-login { background: transparent !important; border: none !important; padding: 8px 10px !important; color: #555 !important; font-weight: 600 !important; }
.btn-login:hover { color: #000 !important; background: transparent !important; text-decoration: underline !important; }
.btn-signup { background-color: #333 !important; color: #fff !important; padding: 10px 24px !important; border-radius: 50px !important; font-weight: 600 !important; border: none !important; }
.btn-signup:hover { background-color: #000 !important; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
</style>

<script>
function hideRedDot() {
    const dot = document.getElementById('notiDot');
    if (dot) dot.style.display = 'none';
}

function closeToast() {
    const toast = document.getElementById('notifToast');
    if (toast) {
        toast.style.animation = "slideOut 0.5s ease forwards";
        setTimeout(() => { document.getElementById('toastContainer').innerHTML = ''; }, 500);
    }
}

function fetchNewNotifications() {
    fetch('check_notification.php')
    .then(response => response.json())
    .then(data => {
        if (data.new_notification) {
            updateBellDot(data.unread_count);
            showToastMessage(data.message);
            const sound = document.getElementById('notifSound');
            if(sound) {
                sound.play().catch(e => console.log("Sound playback requires interaction."));
            }
        }
    })
    .catch(err => console.error("Error checking notifications:", err));
}

function showToastMessage(msg) {
    const container = document.getElementById('toastContainer');
    container.innerHTML = `
        <div class="toast-box" id="notifToast">
            <div class="toast-header">
                <div class="header-left">
                    <i class="fa-solid fa-bell"></i>
                    <span>New Notification</span>
                </div>
                <button onclick="closeToast()">&times;</button>
            </div>
            <div class="toast-body">${msg}</div>
        </div>`;
    setTimeout(closeToast, 8000);
}

function updateBellDot(count) {
    const bell = document.getElementById('notiBell');
    let dot = document.getElementById('notiDot');
    if (count > 0) {
        if (!dot) {
            dot = document.createElement('span');
            dot.id = 'notiDot';
            dot.className = 'noti-dot';
            bell.appendChild(dot);
        } else {
            dot.style.display = 'block';
        }
    }
}

if (<?= $userID ? 'true' : 'false' ?>) {
    setInterval(fetchNewNotifications, 5000); 
}

function toggleProfileMenu(e) {
    e.preventDefault(); e.stopPropagation();
    const menu = document.getElementById('profileMenu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

window.onclick = function(event) {
    if (!event.target.closest('.profile-toggle') && !event.target.closest('.profile-menu')) {
        const menu = document.getElementById('profileMenu');
        if (menu) menu.style.display = 'none';
    }
}
</script>