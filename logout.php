<?php
session_start();
session_unset();  // remove all session variables
session_destroy(); // destroy the session

// Optional: redirect to login page or homepage
header("Location: index.php");
exit();
?>
