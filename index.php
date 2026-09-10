<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect to dashboard
header("Location: dashboard.php");
exit();
?>