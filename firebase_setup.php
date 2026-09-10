<?php
// firebase_setup.php - Include this at the top of ALL your main site pages
require_once 'firebase_connector.php';

// Initialize Firebase connection
date_default_timezone_set('Africa/Lagos');

// Helper function to check if file exists in Firebase (optional)
function firebaseFileExists($path) {
    global $firebase;
    $data = $firebase->get($path);
    return !empty($data);
}

// User-specific data functions for backward compatibility
function getUserTransactions($email) {
    $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $email);
    return readJsonFile("users_data/$firebase_key/transactions");
}

function saveUserTransactions($email, $transactions) {
    $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $email);
    return writeJsonFile("users_data/$firebase_key/transactions", $transactions);
}

function getUserNotifications($email) {
    $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $email);
    $notifications = readJsonFile("users_data/$firebase_key/notifications");
    
    // Ensure we always return an array
    if (!is_array($notifications)) {
        return [];
    }
    
    return $notifications;
}

function saveUserNotifications($email, $notifications) {
    $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $email);
    
    // Ensure we're saving an array
    if (!is_array($notifications)) {
        $notifications = [];
    }
    
    $result = writeJsonFile("users_data/$firebase_key/notifications", $notifications);
    
    if ($result) {
        error_log("Successfully saved " . count($notifications) . " notifications to Firebase for user: $email");
    } else {
        error_log("Failed to save notifications to Firebase for user: $email");
    }
    
    return $result;
}

// Add single transaction to user's transaction history
function addUserTransaction($email, $transaction) {
    $current_transactions = getUserTransactions($email);
    array_unshift($current_transactions, $transaction);
    return saveUserTransactions($email, $current_transactions);
}

// Add single notification to user's notification history
function addUserNotification($email, $notification) {
    $current_notifications = getUserNotifications($email);
    array_unshift($current_notifications, $notification);
    return saveUserNotifications($email, $current_notifications);
}

// Helper function to ensure array return
function ensureArray($data) {
    if (is_array($data)) return $data;
    if (is_string($data)) return json_decode($data, true) ?: [];
    return [];
}

// That's it! Now all your pages use Firebase automatically
?>