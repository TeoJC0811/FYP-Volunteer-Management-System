<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['userName'] ?? null;
?>

<div class="sidebar">

    <a href="index.php">
        <img src="../uploads/ServeTogetherIcon1.png"
            alt="ServeTogether Logo" style="height:40px; margin-bottom:15px; padding-left: 15px;">
    </a>

    <div class="sidebar-links">
        <a href="../index.php" style="color: #ffd700; font-weight: bold; border-bottom: 1px solid #444; padding-bottom: 15px; margin-bottom: 10px;">
            <i class="fa-solid fa-arrow-left-short-circle"></i> Switch to User View
        </a>

        <a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">Dashboard</a>

        <?php if ($role === 'organizer'): ?>
            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, [
                    'select_event_registration.php','manage_event_registration.php',
                    'select_training_registration.php','manage_training_registration.php'
                ]) ? 'active' : '' ?>">
                Registration
            </a>
            <div class="dropdown-container" style="<?= in_array($currentPage, [
                    'select_event_registration.php','manage_event_registration.php',
                    'select_training_registration.php','manage_training_registration.php'
                ]) ? 'display:block;' : '' ?>">
                <a href="select_event_registration.php"
                    class="<?= in_array($currentPage, ['select_event_registration.php','manage_event_registration.php']) ? 'active-child' : '' ?>">Event</a>
                <a href="select_training_registration.php"
                    class="<?= in_array($currentPage, ['select_training_registration.php','manage_training_registration.php']) ? 'active-child' : '' ?>">Training</a>
            </div>

            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, ['manage_event.php','add_event.php']) ? 'active' : '' ?>">Event</a>
            <div class="dropdown-container" style="<?= in_array($currentPage, ['manage_event.php','add_event.php']) ? 'display:block;' : '' ?>">
                <a href="manage_event.php" class="<?= $currentPage == 'manage_event.php' ? 'active-child' : '' ?>">Manage Event</a>
                <a href="add_event.php" class="<?= $currentPage == 'add_event.php' ? 'active-child' : '' ?>">Add Event</a>
            </div>

            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, ['manage_training.php','add_training.php']) ? 'active' : '' ?>">Training</a>
            <div class="dropdown-container" style="<?= in_array($currentPage, ['manage_training.php','add_training.php']) ? 'display:block;' : '' ?>">
                <a href="manage_training.php" class="<?= $currentPage == 'manage_training.php' ? 'active-child' : '' ?>">Manage Training</a>
                <a href="add_training.php" class="<?= $currentPage == 'add_training.php' ? 'active-child' : '' ?>">Add Training</a>
            </div>

            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, ['select_event_review.php','select_training_review.php']) ? 'active' : '' ?>">Review</a>
            <div class="dropdown-container" style="<?= in_array($currentPage, ['select_event_review.php','select_training_review.php']) ? 'display:block;' : '' ?>">
                <a href="select_event_review.php" class="<?= $currentPage == 'select_event_review.php' ? 'active-child' : '' ?>">Review Event</a>
                <a href="select_training_review.php" class="<?= $currentPage == 'select_training_review.php' ? 'active-child' : '' ?>">Review Training</a>
            </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>

            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, ['manage_user.php','add_user.php', 'manage_organizer_request.php']) ? 'active' : '' ?>">User</a>
            <div class="dropdown-container" style="<?= in_array($currentPage, ['manage_user.php','add_user.php', 'manage_organizer_request.php']) ? 'display:block;' : '' ?>">
                <a href="manage_user.php" class="<?= $currentPage == 'manage_user.php' ? 'active-child' : '' ?>">Manage User</a>
                <a href="add_user.php" class="<?= $currentPage == 'add_user.php' ? 'active-child' : '' ?>">Add User</a>
                <a href="manage_organizer_request.php" class="<?= $currentPage == 'manage_organizer_request.php' ? 'active-child' : '' ?>">Organizer Application</a>
            </div>

            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, ['manage_event_approval.php']) ? 'active' : '' ?>">Approvals</a>
            <div class="dropdown-container" style="<?= in_array($currentPage, ['manage_event_approval.php']) ? 'display:block;' : '' ?>">
                <a href="manage_event_approval.php" class="<?= $currentPage == 'manage_event_approval.php' ? 'active-child' : '' ?>">Event Approvals</a>
            </div>

            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, ['manage_forum.php', 'manage_forum_report.php']) ? 'active' : '' ?>">Forum</a>
            <div class="dropdown-container" style="<?= in_array($currentPage, ['manage_forum.php', 'manage_forum_report.php']) ? 'display:block;' : '' ?>">
                <a href="manage_forum.php" class="<?= $currentPage == 'manage_forum.php' ? 'active-child' : '' ?>">Manage Forum</a>
                <a href="manage_forum_report.php" class="<?= $currentPage == 'manage_forum_report.php' ? 'active-child' : '' ?>">Forum Reports</a>
            </div>

            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, ['manage_reward.php','add_reward.php','manage_reward_claims.php']) ? 'active' : '' ?>">Reward</a>
            <div class="dropdown-container" style="<?= in_array($currentPage, ['manage_reward.php','add_reward.php','manage_reward_claims.php']) ? 'display:block;' : '' ?>">
                <a href="manage_reward.php" class="<?= $currentPage == 'manage_reward.php' ? 'active-child' : '' ?>">Manage Reward</a>
                <a href="add_reward.php" class="<?= $currentPage == 'add_reward.php' ? 'active-child' : '' ?>">Add Reward</a>
                <a href="manage_reward_claims.php" class="<?= $currentPage == 'manage_reward_claims.php' ? 'active-child' : '' ?>">Manage Reward Claims</a>
            </div>

            <a href="javascript:void(0)" class="dropdown-btn 
                <?= in_array($currentPage, ['manage_interest.php','add_interest.php']) ? 'active' : '' ?>">Interest</a>
            <div class="dropdown-container" style="<?= in_array($currentPage, ['manage_interest.php','add_interest.php']) ? 'display:block;' : '' ?>">
                <a href="manage_interest.php" class="<?= $currentPage == 'manage_interest.php' ? 'active-child' : '' ?>">Manage Interest</a>
                <a href="add_interest.php" class="<?= $currentPage == 'add_interest.php' ? 'active-child' : '' ?>">Add Interest</a>
            </div>

        <?php endif; ?>
    </div>

    <div class="sidebar-bottom">
        <?php if ($userName): ?>
            <div class="sidebar-user">
                <p>👤 <?= htmlspecialchars($userName) ?></p>
                <small>(<?= htmlspecialchars($role) ?>)</small>
            </div>
        <?php endif; ?>
        <a href="logout.php" class="logout">Logout</a>
    </div>

</div>

<style>
/* --- NO CHANGES TO YOUR ORIGINAL STYLES --- */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 220px;
    height: 100vh;
    background-color: #333;
    color: #fff;
    padding-top: 20px;
    display: flex;
    flex-direction: column;
}

.sidebar a {
    padding: 10px 15px;
    text-decoration: none;
    color: #fff;
    display: block;
}

.sidebar a:hover {
    background-color: #444;
}

.sidebar-links {
    overflow-y: auto;
    flex: 1 1 auto;
    padding-right: 5px;
}

.dropdown-container {
    display: none;
    background-color: #444;
    padding-left: 15px;
}

.sidebar-bottom {
    flex-shrink: 0;
    padding: 10px 0 20px 0;
    text-align: center;
}

.sidebar-user {
    margin: 0 8px 10px 8px;
    padding: 10px;
    background-color: #2e3b4e;
    border-radius: 10px;
    text-align: center;
}

.main-content {
    margin-left: 220px;
    padding: 20px;
}

a.active {
    background-color: #555;
    font-weight: bold;
}
a.active-child {
    font-weight: bold;
}

.logout {
    color: #ff4d4d !important;
}
</style>

<script>
document.querySelectorAll(".dropdown-btn").forEach(btn => {
    btn.addEventListener("click", function () {
        this.classList.toggle("active");
        let content = this.nextElementSibling;
        content.style.display =
            content.style.display === "block" ? "none" : "block";
    });
});
</script>