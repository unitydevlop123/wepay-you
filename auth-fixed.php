<?php
/**
 * FIXED Authentication System
 * This version prevents white screen issues by fixing session configuration
 */

require_once 'firebase_setup.php';

// Configure session settings ONLY if session hasn't started yet
if (session_status() === PHP_SESSION_NONE) {
    // Use safe session settings that work on all hosting environments
    @ini_set('session.use_cookies', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_lifetime', '86400'); // 24 hours
    @ini_set('session.gc_maxlifetime', '86400');
    
    // Start session with error suppression to prevent warnings
    @session_start();
}

// Set timezone
date_default_timezone_set('Africa/Lagos');

/**
 * Simple login check
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && 
           isset($_SESSION['email']) && !empty($_SESSION['email']);
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    return true;
}

/**
 * Get current authenticated user
 * Uses actual session authentication from your login system
 */
function getCurrentUser() {
    // Get user email from session
    $userEmail = $_SESSION['email'] ?? null;
    
    if (empty($userEmail)) {
        return null;
    }

    $users = getUsers();
    if (!empty($users)) {
        foreach ($users as $user) {
            if ($user['email'] === $userEmail) {
                return $user;
            }
        }
    }

    // If user not found in storage, create user record
    $newUser = [
        'name' => $_SESSION['user']['name'] ?? 'User',
        'username' => $_SESSION['user']['username'] ?? 'user',
        'email' => $userEmail,
        'balance' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Add to Firebase
    $users = getUsers();
    $users[] = $newUser;
    saveUsers($users);

    return $newUser;
}

/**
 * Create user-specific data structure in Firebase
 */
function createUserDirectory($userEmail) {
    // Initialize user data in Firebase if not exists
    $transactions = getUserTransactions($userEmail);
    $notifications = getUserNotifications($userEmail);
    
    if (empty($transactions)) {
        saveUserTransactions($userEmail, []);
    }
    
    if (empty($notifications)) {
        saveUserNotifications($userEmail, []);
    }
    
    return true;
}

/**
 * Sanitize email for directory name
 */
function sanitizeEmail($email) {
    return preg_replace('/[^a-zA-Z0-9@._-]/', '', $email);
}

/**
 * Get user-specific transactions with UNLIMITED storage (using Firebase functions)
 */
function getUserTransactionsWithLimit($userEmail) {
    createUserDirectory($userEmail);
    
    $transactions = getUserTransactions($userEmail) ?: [];
    
    // Sort by date (newest first)
    if (!empty($transactions)) {
        usort($transactions, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
    
    // Return ALL transactions - unlimited storage for premium platform
    return $transactions;
}

/**
 * Get user-specific notifications with 50-item limit (using Firebase functions)
 */
function getUserNotificationsWithLimit($userEmail) {
    createUserDirectory($userEmail);
    
    $notifications = getUserNotifications($userEmail) ?: [];
    
    // Sort by date (newest first)
    if (!empty($notifications)) {
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
    
    // Return only the 50 most recent notifications
    return array_slice($notifications, 0, 50);
}

/**
 * Clear all user transactions in Firebase
 */
function clearUserTransactions($userEmail) {
    saveUserTransactions($userEmail, []);
    return true;
}

/**
 * Clear all user notifications in Firebase
 */
function clearUserNotifications($userEmail) {
    saveUserNotifications($userEmail, []);
    return true;
}

/**
 * Delete specific notification
 */
function deleteUserNotification($userEmail, $notificationId) {
    $userDir = 'backend/storage/users/' . sanitizeEmail($userEmail);
    $notificationsFile = $userDir . '/notifications.json';
    
    if (!file_exists($notificationsFile)) {
        return false;
    }
    
    $notifications = json_decode(file_get_contents($notificationsFile), true) ?: [];
    $updated = false;
    
    foreach ($notifications as $key => $notification) {
        if ($notification['id'] === $notificationId) {
            unset($notifications[$key]);
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        $notifications = array_values($notifications); // Re-index
        file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
        return true;
    }
    
    return false;
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($userEmail, $notificationId) {
    $userDir = 'backend/storage/users/' . sanitizeEmail($userEmail);
    $notificationsFile = $userDir . '/notifications.json';
    
    if (!file_exists($notificationsFile)) {
        return false;
    }
    
    $notifications = json_decode(file_get_contents($notificationsFile), true) ?: [];
    $updated = false;
    
    foreach ($notifications as &$notification) {
        if ($notification['id'] === $notificationId) {
            $notification['status'] = 'read';
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
        return true;
    }
    
    return false;
}

/**
 * Update existing notification status and message
 */
function updateUserNotification($userEmail, $reference, $newType, $newTitle, $newMessage) {
    $userDir = 'backend/storage/users/' . sanitizeEmail($userEmail);
    $notificationsFile = $userDir . '/notifications.json';
    
    if (!file_exists($notificationsFile)) {
        return false;
    }
    
    $notifications = json_decode(file_get_contents($notificationsFile), true) ?: [];
    $updated = false;
    
    foreach ($notifications as &$notification) {
        if (isset($notification['reference']) && $notification['reference'] === $reference) {
            $notification['type'] = $newType;
            $notification['title'] = $newTitle;
            $notification['message'] = $newMessage;
            $notification['status'] = 'unread'; // Make it unread so user sees the update
            $notification['updated_at'] = date('Y-m-d H:i:s');
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        file_put_contents($notificationsFile, json_encode($notifications, JSON_PRETTY_PRINT));
        return true;
    }
    
    return false;
}

/**
 * Check storage limits and show warning - DISABLED FOR UNLIMITED STORAGE
 */
function checkStorageLimits($userEmail) {
    // UNLIMITED STORAGE - No limits for premium platform
    return [];
}

/**
 * Update user's transaction history in Firebase
 */
function updateUserTransactions($userEmail, $transactions) {
    // Use the same storage method as getUserTransactions (from firebase_setup.php)
    return saveUserTransactions($userEmail, $transactions);
}

/**
 * Check if user has sufficient balance for transaction
 */
function checkUserBalance($userEmail, $amount) {
    $users = getUsers();
    if (empty($users)) {
        return false;
    }

    foreach ($users as $user) {
        if ($user['email'] === $userEmail) {
            return ($user['balance'] ?? 0) >= $amount;
        }
    }

    return false;
}

/**
 * Create new user with zero balance and empty transaction history
 */
function createNewUser($email, $name, $username) {
    $users = getUsers();

    $newUser = [
        'email' => $email,
        'name' => $name,
        'username' => $username,
        'balance' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $users[] = $newUser;
    saveUsers($users);
    
    // Create user's private directory
    createUserDirectory($email);

    return $newUser;
}
?>
