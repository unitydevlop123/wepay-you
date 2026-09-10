
<?php 
require_once 'firebase_setup.php';

// Script to fix Victor's incomplete account
echo "<h2>🔧 Fixing Victor's Account</h2>";

try {
    // Get all users from Firebase
    $users = getUsers();
    
    if (empty($users)) {
        echo "<p style='color:red;'>❌ No users data found in Firebase</p>";
        exit;
    }
    
    $victor_found = false;
    $victor_index = -1;
    
    // Find Victor by email
    foreach ($users as $index => $user) {
        if (isset($user['email']) && strtolower($user['email']) === 'unityodigie65@gmail.com') {
            $victor_found = true;
            $victor_index = $index;
            break;
        }
    }
    
    if (!$victor_found) {
        echo "<p style='color:red;'>❌ Victor not found</p>";
        exit;
    }
    
    echo "<h3>📋 Victor's Current Data:</h3>";
    echo "<pre>" . print_r($users[$victor_index], true) . "</pre>";
    
    // Complete Victor's account
    $users[$victor_index]['username'] = 'Victor';  // Set username
    $users[$victor_index]['phone'] = '08012345678';  // Set a valid Nigerian phone
    $users[$victor_index]['password'] = 'Boss123@';  // Ensure password is correct
    $users[$victor_index]['status'] = 'active';  // Change from pending_verification to active
    $users[$victor_index]['balance'] = 0.00;
    $users[$victor_index]['commission_balance'] = 5000.00;
    $users[$victor_index]['referral_balance'] = 0.00;
    $users[$victor_index]['updated_at'] = date('Y-m-d H:i:s');
    $users[$victor_index]['current_package_cycle'] = 0;
    $users[$victor_index]['todays_orders_completed'] = 0;
    $users[$victor_index]['todays_commission'] = 0;
    $users[$victor_index]['last_order_date'] = date('Y-m-d');
    
    // Remove verification codes if they exist
    unset($users[$victor_index]['verification_code']);
    unset($users[$victor_index]['verification_expiry']);
    unset($users[$victor_index]['login_verification_code']);
    unset($users[$victor_index]['login_verification_expiry']);
    
    // Save the updated users array
    if (saveUsers($users)) {
        echo "<div style='background: #d4edda; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
        echo "<h3>✅ Victor's Account Fixed Successfully!</h3>";
        echo "<table style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'><strong>Name:</strong></td><td style='border: 1px solid #ddd; padding: 8px;'>" . ($users[$victor_index]['name'] ?? 'Not Set') . "</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'><strong>Username:</strong></td><td style='border: 1px solid #ddd; padding: 8px; background: #c3e6cb;'>" . $users[$victor_index]['username'] . " ✅ FIXED</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'><strong>Email:</strong></td><td style='border: 1px solid #ddd; padding: 8px;'>" . $users[$victor_index]['email'] . "</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'><strong>Phone:</strong></td><td style='border: 1px solid #ddd; padding: 8px; background: #c3e6cb;'>" . $users[$victor_index]['phone'] . " ✅ FIXED</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'><strong>Password:</strong></td><td style='border: 1px solid #ddd; padding: 8px; background: #c3e6cb;'>" . $users[$victor_index]['password'] . " ✅ CONFIRMED</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'><strong>Status:</strong></td><td style='border: 1px solid #ddd; padding: 8px; background: #c3e6cb;'>" . $users[$victor_index]['status'] . " ✅ FIXED</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'><strong>Balance:</strong></td><td style='border: 1px solid #ddd; padding: 8px;'>₦" . number_format($users[$victor_index]['balance'], 2) . "</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'><strong>Commission Balance:</strong></td><td style='border: 1px solid #ddd; padding: 8px;'>₦" . number_format($users[$victor_index]['commission_balance'], 2) . "</td></tr>";
        echo "</table>";
        echo "</div>";
        
        echo "<div style='background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 8px;'>";
        echo "<h4>🔐 Login Instructions for Victor:</h4>";
        echo "<ul>";
        echo "<li><strong>Username/Email:</strong> Victor OR unityodigie65@gmail.com</li>";
        echo "<li><strong>Password:</strong> Boss123@</li>";
        echo "<li><strong>Status:</strong> Account is now ACTIVE and ready for login</li>";
        echo "</ul>";
        echo "</div>";
        
    } else {
        echo "<p style='color:red;'>❌ Failed to save Victor's updated account</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>→ Test Victor's Login</a> | <a href='check-victor-password.php'>→ Check Victor Again</a></p>";
?>
