
<?php
// Set Nigerian timezone
date_default_timezone_set('Africa/Lagos');

// Include authentication system first (handles session properly)
require_once 'auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userEmail = $_SESSION['email'];
$bonusAmount = 20.00; // ₦20 bonus
$today = date('Y-m-d'); // Nigeria timezone date
$currentTime = date('Y-m-d H:i:s'); // Nigeria timezone

// Firebase Storage URLs (direct file access via PHP)
$firebaseBaseUrl = 'https://wepayyou-c100d-default-rtdb.firebaseio.com/';
$usersUrl = $firebaseBaseUrl . 'users.json';
$bonusUrl = $firebaseBaseUrl . 'daily_bonus.json';

// Load users data from Firebase via PHP file functions
$usersJson = @file_get_contents($usersUrl);
$users = $usersJson ? json_decode($usersJson, true) : [];

if (empty($users)) {
    echo json_encode(['success' => false, 'message' => 'User data not found']);
    exit;
}

// Load daily bonus data from Firebase via PHP file functions
$bonusJson = @file_get_contents($bonusUrl);
$bonusData = $bonusJson ? json_decode($bonusJson, true) : [];

// Check if user already claimed today
$userBonusKey = $userEmail . '_' . $today;
error_log("BONUS DEBUG: Checking key '$userBonusKey' in data");

if (isset($bonusData[$userBonusKey])) {
    error_log("BONUS DEBUG: User already claimed today - blocking");
    echo json_encode(['success' => false, 'message' => 'You have already claimed your daily bonus today. Come back tomorrow at 12:00 AM (Lagos time) for your next bonus!']);
    exit;
}
error_log("BONUS DEBUG: User hasn't claimed today - allowing claim");

// Find and update user
$userFound = false;
foreach ($users as $key => $user) {
    if (is_array($user) && isset($user['email']) && $user['email'] === $userEmail) {
        $users[$key]['balance'] = ($user['balance'] ?? 0.00) + $bonusAmount;
        
        // Add bonus tracking to user profile
        if (!isset($users[$key]['daily_bonus_claims'])) {
            $users[$key]['daily_bonus_claims'] = [];
        }
        $users[$key]['daily_bonus_claims'][$today] = [
            'claimed_at' => $currentTime,
            'amount' => $bonusAmount
        ];
        $users[$key]['last_bonus_claim'] = $today;
        
        $userFound = true;
        break;
    }
}

if (!$userFound) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Record the bonus claim
$bonusData[$userBonusKey] = [
    'email' => $userEmail,
    'date' => $today,
    'amount' => $bonusAmount,
    'claimed_at' => $currentTime,
    'timezone' => 'Africa/Lagos'
];

// Save user-specific transaction record
$firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $userEmail);
$transactionsUrl = $firebaseBaseUrl . "users_data/$firebase_key/transactions.json";

$transactionsJson = @file_get_contents($transactionsUrl);
$transactions = $transactionsJson ? json_decode($transactionsJson, true) : [];

$transaction = [
    'id' => 'BONUS_' . strtoupper(bin2hex(random_bytes(4))),
    'transaction_type' => 'bonus',
    'description' => 'Daily Check-in Bonus',
    'amount' => $bonusAmount,
    'status' => 'completed',
    'created_at' => $currentTime,
    'reference' => 'DAILY-BONUS-' . $today
];
array_unshift($transactions, $transaction);

// Save notifications
$notificationsUrl = $firebaseBaseUrl . "users_data/$firebase_key/notifications.json";
$notificationsJson = @file_get_contents($notificationsUrl);
$notifications = $notificationsJson ? json_decode($notificationsJson, true) : [];

$notification = [
    'id' => 'bonus_' . time() . '_' . rand(1000, 9999),
    'type' => 'bonus',
    'title' => 'Daily Bonus Claimed!',
    'message' => "🎁 Congratulations! You've successfully claimed your daily bonus of ₦" . number_format($bonusAmount, 2) . ". Come back tomorrow for another bonus!",
    'amount' => $bonusAmount,
    'status' => 'unread',
    'created_at' => $currentTime,
    'read_at' => null
];
array_unshift($notifications, $notification);

// Save all data to Firebase using PHP file functions (NO API CALLS)
$userSaveResult = @file_put_contents($usersUrl, json_encode($users));
$bonusSaveResult = @file_put_contents($bonusUrl, json_encode($bonusData));
$transactionSaveResult = @file_put_contents($transactionsUrl, json_encode($transactions));
$notificationSaveResult = @file_put_contents($notificationsUrl, json_encode($notifications));

error_log("BONUS DEBUG: User save result: " . ($userSaveResult ? 'SUCCESS' : 'FAILED'));
error_log("BONUS DEBUG: Bonus tracking save: " . ($bonusSaveResult ? 'SUCCESS' : 'FAILED'));
error_log("BONUS DEBUG: Transaction save: " . ($transactionSaveResult ? 'SUCCESS' : 'FAILED'));
error_log("BONUS DEBUG: Notification save: " . ($notificationSaveResult ? 'SUCCESS' : 'FAILED'));

// Also save to local file as backup
$localPath = 'backend/storage/daily_bonus.json';
if (!file_exists(dirname($localPath))) {
    @mkdir(dirname($localPath), 0777, true);
}
@file_put_contents($localPath, json_encode($bonusData, JSON_PRETTY_PRINT));

if (!$userSaveResult) {
    echo json_encode(['success' => false, 'message' => 'Failed to update your balance. Please try again.']);
    exit;
}

echo json_encode([
    'success' => true, 
    'message' => 'Daily bonus claimed successfully!',
    'amount' => $bonusAmount
]);
?>
