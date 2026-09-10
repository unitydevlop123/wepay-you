
<?php
require_once 'firebase_setup.php';

$test_email = 'unityodigie440@gmail.com';

echo "<h2>Testing Firebase Notifications System</h2>";

// Test 1: Check if functions exist
echo "<h3>1. Function Check:</h3>";
if (function_exists('getUserNotifications')) {
    echo "✅ getUserNotifications function exists<br>";
} else {
    echo "❌ getUserNotifications function missing<br>";
}

if (function_exists('saveUserNotifications')) {
    echo "✅ saveUserNotifications function exists<br>";
} else {
    echo "❌ saveUserNotifications function missing<br>";
}

// Test 2: Get current notifications
echo "<h3>2. Current Notifications:</h3>";
$current_notifications = getUserNotifications($test_email);
echo "Found: " . count($current_notifications) . " notifications<br>";

if (!empty($current_notifications)) {
    echo "<pre>" . json_encode($current_notifications, JSON_PRETTY_PRINT) . "</pre>";
}

// Test 3: Add a test notification
echo "<h3>3. Adding Test Notification:</h3>";
$test_notification = [
    'id' => uniqid('test_'),
    'type' => 'success',
    'title' => 'Firebase Test Notification',
    'message' => 'This is a test notification to verify Firebase is working correctly.',
    'amount' => 0,
    'status' => 'unread',
    'created_at' => date('Y-m-d H:i:s')
];

$notifications = getUserNotifications($test_email);
array_unshift($notifications, $test_notification);
$save_result = saveUserNotifications($test_email, $notifications);

if ($save_result) {
    echo "✅ Test notification saved successfully<br>";
} else {
    echo "❌ Failed to save test notification<br>";
}

// Test 4: Verify it was saved
echo "<h3>4. Verification:</h3>";
$updated_notifications = getUserNotifications($test_email);
echo "Now found: " . count($updated_notifications) . " notifications<br>";

// Check if our test notification is there
$found_test = false;
foreach ($updated_notifications as $notif) {
    if (strpos($notif['id'], 'test_') === 0) {
        $found_test = true;
        break;
    }
}

if ($found_test) {
    echo "✅ Test notification found in Firebase<br>";
} else {
    echo "❌ Test notification not found in Firebase<br>";
}

echo "<br><a href='notifications.php'>← Go to Notifications Page</a>";
?>
