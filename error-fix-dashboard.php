<?php
/**
 * ERROR FIX FOR DASHBOARD WHITE SCREEN ISSUE
 * 
 * This file fixes the white screen problem that happens after logout/session expiry
 * 
 * INSTRUCTIONS:
 * 1. Include this file at the VERY TOP of your dashboard.php (line 1)
 * 2. Change: <?php require_once 'firebase_setup.php'; ?>
 *    To:     <?php require_once 'error-fix-dashboard.php'; ?>
 * 
 * What this fixes:
 * - Enables error display so you can see what's wrong
 * - Fixes session configuration issues
 * - Adds error handling for Firebase connections
 * - Prevents white screen by catching errors
 */

// STEP 1: Enable error reporting to see what's causing the white screen
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// STEP 2: Fix session configuration BEFORE starting session
// Clear any existing session settings that might conflict
if (session_status() === PHP_SESSION_NONE) {
    // Use compatible session settings for all hosting environments
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_lifetime', '86400'); // 24 hours
    ini_set('session.gc_maxlifetime', '86400');
    
    // Remove problematic settings that cause issues on some hosts
    // Don't force specific paths or secure settings that may not work
    
    // Start session with error handling
    try {
        session_start();
    } catch (Exception $e) {
        die("Session Error: " . $e->getMessage() . "<br>Please clear your browser cookies and try again.");
    }
}

// STEP 3: Set timezone
date_default_timezone_set('Africa/Lagos');

// STEP 4: Include Firebase with error handling
try {
    if (file_exists('firebase_setup.php')) {
        require_once 'firebase_setup.php';
    } else {
        throw new Exception("Firebase setup file not found!");
    }
} catch (Exception $e) {
    die("Firebase Connection Error: " . $e->getMessage() . "<br>Please contact support if this persists.");
}

// STEP 5: Include auth with error handling
try {
    if (file_exists('auth.php')) {
        require_once 'auth.php';
    } else {
        throw new Exception("Authentication file not found!");
    }
} catch (Exception $e) {
    die("Authentication Error: " . $e->getMessage() . "<br>Please contact support if this persists.");
}

// STEP 6: Add helpful debugging function
function debugInfo($message) {
    // Only show debug info if errors are enabled
    if (ini_get('display_errors') == '1') {
        echo "<div style='background:#f0f0f0; border-left:4px solid #ff6b6b; padding:10px; margin:10px 0;'>";
        echo "<strong>Debug:</strong> " . htmlspecialchars($message);
        echo "</div>";
    }
}

// STEP 7: Verify session is working
if (!isset($_SESSION)) {
    die("Session initialization failed! Please enable sessions on your hosting server.");
}

// Success - Firebase and Auth are now loaded with error handling
?>
