<?php
/**
 * UNIVERSAL BAN CHECKING SYSTEM
 * Checks if user is banned and handles the response
 */

require_once 'firebase_setup.php';

/**
 * Check if user is banned
 * Returns true if banned, false if not
 */
function isUserBanned($userEmail) {
    if (empty($userEmail)) {
        return false;
    }
    
    // Get users from Firebase
    $users = getUsers();
    
    if (empty($users)) {
        return false;
    }
    
    // Search for user and check ban status
    foreach ($users as $user) {
        if (isset($user['email']) && strtolower($user['email']) === strtolower($userEmail)) {
            // Check if user has banned status
            if (isset($user['banned']) && $user['banned'] === true) {
                return true;
            }
            // Also check status field
            if (isset($user['status']) && $user['status'] === 'banned') {
                return true;
            }
            // Also check account_status field (for admin compatibility)
            if (isset($user['account_status']) && $user['account_status'] === 'banned') {
                return true;
            }
            break;
        }
    }
    
    return false;
}

/**
 * Force logout banned user
 * Destroys session and redirects to login with ban message
 */
function forceLogoutBannedUser() {
    // Destroy session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    // Clear cookies
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time()-1000);
            setcookie($name, '', time()-1000, '/');
        }
    }
    
    // Redirect to login with ban flag
    header('Location: login.php?banned=1');
    exit();
}

/**
 * Check if current logged-in user is banned
 * Call this on every protected page to force logout if banned
 */
function checkBanStatus() {
    // Only check if user is logged in
    if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
        $userEmail = $_SESSION['email'];
        
        if (isUserBanned($userEmail)) {
            forceLogoutBannedUser();
        }
    }
}

/**
 * Get ban message for display
 */
function getBanMessage() {
    return "Your account has been banned for not following our rules and policies. Please contact our support team for assistance.";
}
?>
