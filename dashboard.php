<?php require_once 'firebase_setup.php'; ?>
<?php
// Include authentication system
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Enable proper authentication
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Function to format amounts with proper comma separation
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Get user information from authenticated session
$userName = 'Elite Member';
$current_user_email = $_SESSION['email']; // Get from authenticated session

if (isset($_SESSION['user']['name'])) {
    $userName = $_SESSION['user']['name'];
}

// Get user data from Firebase
$users = getUsers();
if (!empty($users) && is_array($users)) {
    foreach ($users as $user) {
        // Ensure $user is an array before processing
        if (!is_array($user)) {
            continue; // Skip non-array entries
        }
        
        if (isset($user['email']) && $user['email'] === $current_user_email) {
            $userName = $user['name'] ?? 'Elite Member';
            break;
        }
    }
}

// Get real user data from Firebase storage
$user_balance_main = 0.00;
$user_referral_earn = 0.00;
$total_referral_earned = 0.00;

// Get user balance from Firebase (use current_user_email from above)
$users = getUsers();
if (!empty($users) && is_array($users)) {
    foreach ($users as $user) {
        // Ensure $user is an array before processing
        if (!is_array($user)) {
            continue; // Skip non-array entries
        }
        
        if (isset($user['email']) && $user['email'] === $current_user_email) {
            $user_balance_main = (float)($user['balance'] ?? 0.00);
            $user_referral_earn = (float)($user['referral_balance'] ?? 0.00);
            break;
        }
    }
}

// Calculate total referral earnings SAFELY (protected from transaction deletion)
// Method 1: Use persistent referral balance from user profile (main source)
$total_referral_earned += $user_referral_earn;

// Method 2: Check referral codes system for earned rewards (backup calculation)
$referral_codes_data = readJsonFile('referral_codes') ?: [];
foreach ($referral_codes_data as $code => $referral_info) {
    if (is_array($referral_info) && isset($referral_info['inviter_email']) && $referral_info['inviter_email'] === $current_user_email) {
        // Add ₦200 for each successful referral (used codes with claimed rewards)
        if ($referral_info['status'] === 'used' && isset($referral_info['reward_claimed']) && $referral_info['reward_claimed']) {
            $total_referral_earned += 200;
        }
    }
}

// Method 3: Only add transaction history bonuses if they still exist (optional)
$user_transactions = getUserTransactions($current_user_email) ?: [];
$transaction_referral_bonus = 0;
foreach ($user_transactions as $transaction) {
    if (isset($transaction['transaction_type']) && $transaction['transaction_type'] === 'referral_bonus') {
        $transaction_referral_bonus += (float)($transaction['amount'] ?? 0);
    }
}
// Only add transaction bonuses if they provide additional value beyond the persistent balance
if ($transaction_referral_bonus > $total_referral_earned) {
    $total_referral_earned = $transaction_referral_bonus;
}
// Real-time activity tracking handled by JavaScript
$daily_time_required = 30; // minutes required
// GET SELECTED PACKAGE FROM SESSION OR URL - Check what package user is working on in orders page
$selected_package = $_GET['selected_package'] ?? $_SESSION['selected_package'] ?? null;

// Also check if there's a package stored in user's current session data
if (!$selected_package) {
    // Try to get the last selected package from user data
    $users = getUsers();
    if (!empty($users) && is_array($users)) {
        foreach ($users as $user) {
            if (is_array($user) && isset($user['email']) && $user['email'] === $current_user_email) {
                $selected_package = $user['current_selected_package'] ?? null;
                break;
            }
        }
    }
}

// FORCE FRESH DATA RELOAD - Get real user investment packages and orders  

// AUTO-SYNC TRANSACTION STATUS FROM NOTIFICATIONS (Dashboard version)
$user_notifications = getUserNotifications($current_user_email);

if (!empty($user_notifications) && is_array($user_notifications)) {
    $userTransactions = getUserTransactions($current_user_email) ?: [];
    $updated = false;
    
    foreach ($user_notifications as $notification) {
        if (!is_array($notification)) continue;
        
        if (isset($notification['reference']) && isset($notification['type'])) {
            $reference = $notification['reference'];
            $newStatus = null;
            
            if (strpos($notification['title'], 'Successful') !== false || 
                strpos($notification['title'], 'Approved') !== false ||
                $notification['type'] === 'deposit' && strpos($notification['message'], 'approved') !== false) {
                $newStatus = 'successful';
            } elseif (strpos($notification['title'], 'Rejected') !== false ||
                      strpos($notification['message'], 'rejected') !== false) {
                $newStatus = 'rejected';
            }
            
            if ($newStatus) {
                foreach ($userTransactions as &$transaction) {
                    if (isset($transaction['reference']) && $transaction['reference'] === $reference) {
                        if ($transaction['status'] !== $newStatus) {
                            $transaction['status'] = $newStatus;
                            $updated = true;
                        }
                    }
                }
            }
        }
    }
    
    if ($updated && function_exists('saveUserTransactions')) {
        saveUserTransactions($current_user_email, $userTransactions);
    }
}

// Get user notifications for popup system
$user_notifications = getUserNotifications($current_user_email);
$unread_notifications = [];

// Filter unread notifications and sort by newest first
if (!empty($user_notifications) && is_array($user_notifications)) {
    foreach ($user_notifications as $notification) {
        // SAFETY: Ensure notification is an array before processing
        if (!is_array($notification)) {
            continue; // Skip non-array entries
        }
        
        if (!isset($notification['status']) || $notification['status'] === 'unread') {
            $unread_notifications[] = $notification;
        }
    }
    // Sort by created_at date (newest first) - only if we have valid notifications
    if (!empty($unread_notifications)) {
        usort($unread_notifications, function($a, $b) {
            // Safety check for created_at field
            $time_a = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
            $time_b = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $time_b - $time_a;
        });
    }
}
clearstatcache(); // Clear file stat cache
$active_packages = [];
$selected_package_data = null;
$todays_orders_completed = 0;
$todays_orders_total = 0;
$todays_commission = 0.00;
$user_has_packages = false;

// Get package data directly from Firebase (no session)
$users = getUsers();
if (!empty($users) && is_array($users)) {
    foreach ($users as $user) {
        // Ensure $user is an array before processing
        if (!is_array($user)) {
            continue; // Skip non-array entries
        }
        
        if (isset($user['email']) && $user['email'] === $current_user_email) {
            $active_packages = $user['active_packages'] ?? [];

            // CHECK IF USER HAS ANY ACTIVE PACKAGES
            if (!empty($active_packages)) {
                $user_has_packages = true;

                // Filter out expired packages
                $valid_packages = [];
                foreach ($active_packages as $index => $package) {
                    if (isset($package['expiry_date'])) {
                        $expiry_date = new DateTime($package['expiry_date']);
                        $today_dt = new DateTime();
                        if ($expiry_date > $today_dt) {
                            $valid_packages[] = [
                                'package' => $package,
                                'index' => $index
                            ];
                        }
                    }
                }

                // Update user_has_packages based on valid packages
                $user_has_packages = !empty($valid_packages);

                // AUTO-SELECT FIRST PACKAGE IF NONE SELECTED
                if ($selected_package) {
                    foreach ($valid_packages as $pkg_info) {
                        if ($pkg_info['package']['name'] === $selected_package) {
                            $selected_package_data = $pkg_info;
                            break;
                        }
                    }
                } else {
                    // If no package selected, auto-select the first valid package
                    if (!empty($valid_packages)) {
                        $selected_package_data = $valid_packages[0];
                        $selected_package = $selected_package_data['package']['name'];
                        
                        // Store in session for consistency
                        $_SESSION['selected_package'] = $selected_package;
                    }
                }

                // PACKAGE-SPECIFIC PROGRESS TRACKING - Show selected package data
                if ($selected_package_data) {
                    // Show specific package progress using LIVE data
                    $package_name = $selected_package_data['package']['name'];
                    // Get live package data from Firebase
                    $live_package_data = readJsonFile(strtolower($package_name));

                    if (!empty($live_package_data)) {
                        $todays_orders_total = $live_package_data['orders'] ?? 20;
                        $target_daily_profit = $live_package_data['daily_profit'] ?? 0;
                    } else {
                        $todays_orders_total = $selected_package_data['package']['daily_orders'] ?? 20;
                        $target_daily_profit = $selected_package_data['package']['daily_profit'] ?? 0;
                    }

                    // Use package-specific progress (not global counters)
                    $package_index = $selected_package_data['index'];
                    $current_package = (is_array($user) && isset($user['active_packages'][$package_index])) ? $user['active_packages'][$package_index] : [];
                    $todays_orders_completed = $current_package['orders_completed_today'] ?? 0;
                    $todays_commission = $current_package['commission_today'] ?? 0.00;

                    // Check if package is complete and reset if needed
                    $package_orders_complete = ($todays_orders_completed >= $todays_orders_total);
                    $package_earnings_complete = ($todays_commission >= $target_daily_profit);

                    if ($package_orders_complete && $package_earnings_complete) {
                        // Package completed - show completion message instead
                        $todays_orders_completed = $todays_orders_total;
                        $todays_commission = $target_daily_profit;
                    }
                }
            }

            // Session disabled for debugging - no session update needed
            break;
        }
    }
}

// Check if daily check-in is available (Nigeria timezone)
date_default_timezone_set('Africa/Lagos');
$today = date('Y-m-d');
$userEmail = $current_user_email; // Use the correct user email from above

$daily_checkin_available = false;
if (!empty($userEmail)) {
    $userBonusKey = $userEmail . '_' . $today;
    $hasClaimedToday = false;
    
    // Method 1: Check Firebase data
    $bonusData = readJsonFile('daily_bonus') ?: [];
    if (isset($bonusData[$userBonusKey])) {
        $hasClaimedToday = true;
        error_log("DASHBOARD DEBUG: Found claim in Firebase data");
    }
    
    // Method 2: Check local file as backup
    if (!$hasClaimedToday) {
        $localPath = 'backend/storage/daily_bonus.json';
        if (file_exists($localPath)) {
            $localData = json_decode(file_get_contents($localPath), true) ?: [];
            if (isset($localData[$userBonusKey])) {
                $hasClaimedToday = true;
                error_log("DASHBOARD DEBUG: Found claim in local file");
            }
        }
    }
    
    // Method 3: Check user profile data as additional verification
    if (!$hasClaimedToday) {
        $users = getUsers();
        foreach ($users as $user) {
            // Ensure $user is an array before processing
            if (!is_array($user)) {
                continue; // Skip non-array entries
            }
            
            if (isset($user['email']) && $user['email'] === $userEmail) {
                if (isset($user['daily_bonus_claims'][$today]) || (isset($user['last_bonus_claim']) && $user['last_bonus_claim'] === $today)) {
                    $hasClaimedToday = true;
                    error_log("DASHBOARD DEBUG: Found claim in user profile");
                    break;
                }
            }
        }
    }
    
    $daily_checkin_available = !$hasClaimedToday;
    error_log("DASHBOARD DEBUG: User '$userEmail' - Date '$today' - Available: " . ($daily_checkin_available ? 'YES' : 'NO'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET PAY YOU - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }

        /* Top Banner with Slideshow */
        .hero-banner {
            position: relative;
            height: 300px;
            overflow: hidden;
            border-radius: 0 0 20px 20px;
        }

        .slideshow-container {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .slide {
            display: none;
            position: relative;
            width: 100%;
            height: 100%;
        }

        .slide.active {
            display: block;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .slide-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6));
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
        }

        .top-header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-section img {
            height: 40px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            font-weight: 500;
        }

        .notification-bell {
            position: relative;
            color: #333;
            font-size: 22px;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
            text-decoration: none;
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
        }

        .notification-bell:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.2);
            transform: scale(1.1);
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 6px;
            border-radius: 12px;
            min-width: 18px;
            text-align: center;
            line-height: 1;
            border: 2px solid white;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .slide-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .slide-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        /* Slideshow navigation */
        .slide-nav {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }

        .slide-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .slide-dot.active {
            background: white;
            transform: scale(1.2);
        }

        /* Main Content */
        .content {
            padding: 30px 20px 100px;
        }



        /* Tablet responsive adjustments */
        @media (max-width: 768px) {
            .content {
                padding: 25px 15px 100px;
            }
        }

        /* Mobile responsive adjustments */
        @media (max-width: 480px) {
            .content {
                padding: 20px 10px 80px;
            }
        }



        /* Wallet Section */
        .wallet-section {
            background: white;
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .wallet-balances {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 15px 0;
        }

        .balance-card {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            border-left: 3px solid #4CAF50;
            transition: all 0.3s ease;
        }

        .balance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .balance-card.commission {
            border-left-color: #2196F3;
        }

        .balance-card.referral {
            border-left-color: #FF9800;
        }



        .balance-label {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .balance-amount {
            font-size: 1rem;
            font-weight: bold;
            color: #333;
        }

        .wallet-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 15px;
        }

        .wallet-action-btn {
            background: #f0f0f0;
            border: 1px solid #e0e0e0;
            color: #333;
            padding: 8px 5px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
        }

        .wallet-action-btn:hover {
            background: #e8e8e8;
            border-color: #d0d0d0;
            transform: translateY(-2px);
        }

        .wallet-action-btn.deposit {
            background: #e8f5e8;
            border-color: #4CAF50;
            color: #2e7d32;
        }

        .wallet-action-btn.deposit:hover {
            background: #d4edda;
            border-color: #4CAF50;
        }

        .wallet-action-btn.withdraw {
            background: #fff3e0;
            border-color: #FF9800;
            color: #e65100;
        }

        .wallet-action-btn.withdraw:hover {
            background: #ffe0b2;
            border-color: #FF9800;
        }

        /* Daily Progress */
        .daily-progress {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
        }

        .progress-ring {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }

        .progress-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(#4CAF50 0deg, #f0f0f0 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-inner {
            width: 90px;
            height: 90px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            font-weight: bold;
        }

        .progress-time {
            font-size: 1.4rem;
            color: #4CAF50;
        }

        .progress-label {
            font-size: 0.8rem;
            color: #666;
        }

        /* Active Packages */
        .section-title {
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
        }

        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .package-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #2196F3;
            transition: all 0.3s ease;
        }

        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .package-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2196F3;
            margin-bottom: 10px;
        }

        .package-profit {
            font-size: 1.3rem;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 8px;
        }

        .package-days {
            color: #666;
            font-size: 0.9rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .action-btn {
            background: white;
            border: 2px solid #f1f5f9;
            color: #1e293b;
            padding: 20px 15px;
            border-radius: 16px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border-color: #e2e8f0;
        }

        .action-btn.invest {
            border-color: #10b981;
            background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
            color: #047857;
        }

        .action-btn.invest:hover {
            background: linear-gradient(135deg, #d1fae5, #dcfce7);
            border-color: #059669;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25);
        }

        .action-btn.games {
            border-color: #ef4444;
            background: linear-gradient(135deg, #fef2f2, #fef2f2);
            color: #dc2626;
        }

        .action-btn.games:hover {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-color: #dc2626;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.25);
        }

        .action-btn.history {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, #faf5ff, #f3e8ff);
            color: #7c3aed;
        }

        .action-btn.history:hover {
            background: linear-gradient(135deg, #ede9fe, #e9d5ff);
            border-color: #7c3aed;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.25);
        }

        .action-btn.profile {
            border-color: #f97316;
            background: linear-gradient(135deg, #fff7ed, #fef3c7);
            color: #ea580c;
        }

        .action-btn.profile:hover {
            background: linear-gradient(135deg, #fed7aa, #fde68a);
            border-color: #ea580c;
            box-shadow: 0 8px 20px rgba(249, 115, 22, 0.25);
        }

        .action-btn.about {
            border-color: #06b6d4;
            background: linear-gradient(135deg, #ecfeff, #cffafe);
            color: #0891b2;
        }

        .action-btn.about:hover {
            background: linear-gradient(135deg, #a5f3fc, #67e8f9);
            border-color: #0891b2;
            box-shadow: 0 8px 20px rgba(6, 182, 212, 0.25);
        }

        .action-btn.support {
            border-color: #9333ea;
            background: linear-gradient(135deg, #faf5ff, #f3e8ff);
            color: #7c3aed;
        }

        .action-btn.support:hover {
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            border-color: #7c3aed;
            box-shadow: 0 8px 20px rgba(147, 51, 234, 0.25);
        }

        /* Today's Tasks */
        .tasks-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .task-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .task-counter {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2196F3;
        }

        .continue-btn {
            background: #FF9800;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .continue-btn:hover {
            background: #F57C00;
            transform: translateY(-2px);
        }

        .continue-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Daily Check-in */
        .checkin-card {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 25px;
        }

        .checkin-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .checkin-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Notification Popup Styles */
        .notification-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 350px;
            max-width: calc(100vw - 40px);
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            z-index: 10000;
            opacity: 0;
            transform: translateY(-20px) scale(0.9);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 1px solid #e2e8f0;
        }

        .notification-popup.show {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .popup-content {
            padding: 0;
            border-radius: 15px;
            overflow: hidden;
        }

        .popup-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .notification-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            animation: bell-ring 2s infinite;
        }

        @keyframes bell-ring {
            0%, 50%, 100% { transform: rotate(0deg); }
            10%, 30% { transform: rotate(-10deg); }
            20%, 40% { transform: rotate(10deg); }
        }

        .popup-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            flex: 1;
        }

        .popup-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .popup-close:hover {
            background: rgba(255,255,255,0.2);
        }

        .popup-body {
            padding: 20px;
        }

        .notification-item {
            border-left: 4px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
        }

        .notification-text {
            font-size: 0.95rem;
            color: #333;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #666;
            font-style: italic;
        }

        .popup-actions {
            padding: 0 20px 20px;
            display: flex;
            gap: 10px;
        }

        .popup-btn {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .view-btn {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .view-btn:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }

        .mark-read-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .mark-read-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Mobile responsive */
        @media (max-width: 480px) {
            .notification-popup {
                top: 10px;
                right: 10px;
                left: 10px;
                width: auto;
                max-width: none;
            }
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 12px 0;
            z-index: 1000;
            backdrop-filter: blur(20px);
        }

        .nav-container {
            max-width: 500px;
            margin: 0 auto;
            display: flex;
            justify-content: space-around;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 16px;
            text-decoration: none;
            color: #64748b;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 12px;
        }

        .nav-item.active {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }

        .nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .nav-icon {
            width: 24px;
            height: 24px;
            margin-bottom: 4px;
            fill: currentColor;
        }

        .nav-label {
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .slide-title {
                font-size: 2rem;
            }

            .content {
                padding: 20px 15px 100px;
            }

            .packages-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .success-animation {
            animation: pulse 2s infinite, glow 3s ease-in-out infinite alternate;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes glow {
            0% { 
                box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
            }
            100% { 
                box-shadow: 0 10px 40px rgba(255, 215, 0, 0.6), 0 0 20px rgba(255, 215, 0, 0.4);
            }
        }

        /* Claiming animation */
        .claiming-animation {
            animation: claiming-pulse 1s infinite, claiming-shimmer 2s linear infinite;
        }

        @keyframes claiming-pulse {
            0%, 100% { 
                transform: scale(1);
                opacity: 1;
            }
            50% { 
                transform: scale(1.02);
                opacity: 0.9;
            }
        }

        @keyframes claiming-shimmer {
            0% { 
                background-position: -200% center;
            }
            100% { 
                background-position: 200% center;
            }
        }

        /* Success celebration animation */
        .celebration-animation {
            animation: celebration-bounce 0.8s ease-out, celebration-glow 2s ease-in-out;
            background: linear-gradient(135deg, #4CAF50, #45a049, #4CAF50) !important;
            background-size: 200% 100%;
        }

        @keyframes celebration-bounce {
            0% { 
                transform: scale(1);
            }
            25% { 
                transform: scale(1.1) rotate(2deg);
            }
            50% { 
                transform: scale(1.05) rotate(-1deg);
            }
            75% { 
                transform: scale(1.08) rotate(1deg);
            }
            100% { 
                transform: scale(1) rotate(0deg);
            }
        }

        @keyframes celebration-glow {
            0% { 
                box-shadow: 0 5px 20px rgba(76, 175, 80, 0.3);
            }
            50% { 
                box-shadow: 0 10px 40px rgba(76, 175, 80, 0.8), 0 0 30px rgba(76, 175, 80, 0.6);
            }
            100% { 
                box-shadow: 0 5px 20px rgba(76, 175, 80, 0.4);
            }
        }

        /* Money rain effect */
        .money-rain {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            border-radius: 12px;
        }

        .money-symbol {
            position: absolute;
            font-size: 20px;
            font-weight: bold;
            color: #FFD700;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            animation: money-fall 2s linear forwards;
            opacity: 0;
        }

        @keyframes money-fall {
            0% { 
                transform: translateY(-20px) rotate(0deg);
                opacity: 1;
            }
            100% { 
                transform: translateY(150px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Button hover animation enhancement */
        .checkin-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .checkin-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,255,255,0.4);
        }

        .checkin-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .checkin-btn:hover:not(:disabled)::before {
            left: 100%;
        }

        /* Notification Popup Styles */
        .notification-popup {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            max-width: 90vw;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transform: translateX(100%);
            transition: all 0.3s ease;
            border-left: 4px solid #4CAF50;
            max-height: 200px;
            overflow: hidden;
        }

        .notification-popup.show {
            transform: translateX(0);
        }

        .popup-content {
            padding: 0;
        }

        .popup-header {
            display: flex;
            align-items: center;
            padding: 15px 20px 10px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 15px 15px 0 0;
        }

        .notification-icon {
            width: 30px;
            height: 30px;
            background: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 14px;
        }

        .popup-title {
            flex: 1;
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .popup-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .popup-close:hover {
            color: #333;
        }

        .popup-body {
            padding: 15px 20px;
        }

        .notification-text {
            font-size: 14px;
            color: #555;
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .notification-time {
            font-size: 12px;
            color: #888;
            margin-bottom: 15px;
        }

        .popup-actions {
            display: flex;
            gap: 10px;
            padding: 0 20px 15px;
        }

        .popup-btn {
            flex: 1;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .view-btn {
            background: #f0f0f0;
            color: #333;
        }

        .view-btn:hover {
            background: #e0e0e0;
        }

        .mark-read-btn {
            background: #4CAF50;
            color: white;
        }

        .mark-read-btn:hover {
            background: #45a049;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .notification-popup {
                width: 300px;
                bottom: 15px;
                right: 15px;
            }

            .popup-header {
                padding: 12px 15px 8px;
            }

            .popup-body {
                padding: 12px 15px;
            }

            .popup-actions {
                padding: 0 15px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Top Header with Logo -->
        <div class="top-header">
            <div class="logo-section">
                <img src="logo.svg" alt="LET PAY YOU">
            </div>
            <div class="user-info">
                <span>Welcome back, <?php echo htmlspecialchars($userName); ?>!</span>
                <?php 
                // Get notification count
                $notifications = getUserNotifications($current_user_email) ?: [];
                $unread_count = 0;
                foreach ($notifications as $notification) {
                    if (!isset($notification['status']) || $notification['status'] === 'unread') {
                        $unread_count++;
                    }
                }
                ?>
                <a href="notifications.php" class="notification-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <div class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            </div>
        </div>

        <!-- Hero Banner with Slideshow -->
        <div class="hero-banner">
            <div class="slideshow-container">
                <div class="slide active">
                    <img src="attached_assets/attached_assets/generated_images/Business_team_collaboration_703fc79a.png" alt="Professional Success">
                    <div class="slide-overlay">
                        <h1 class="slide-title">Welcome to LET PAY YOU</h1>
                        <p class="slide-subtitle">Earn money daily with part-time tasks and investment opportunities</p>
                    </div>
                </div>
                <div class="slide">
                    <img src="attached_assets/attached_assets/generated_images/Businessman_champagne_celebration_success_85461b3b.png" alt="Success Story">
                    <div class="slide-overlay">
                        <h1 class="slide-title">Daily Task Opportunities</h1>
                        <p class="slide-subtitle">Complete orders and tasks to unlock daily profits from your investments</p>
                    </div>
                </div>
                <div class="slide">
                    <img src="attached_assets/attached_assets/generated_images/Family_investment_success_celebration_bcefedab.png" alt="Professional Team">
                    <div class="slide-overlay">
                        <h1 class="slide-title">Smart Investment Packages</h1>
                        <p class="slide-subtitle">Choose an investment plan and earn daily by completing simple tasks</p>
                    </div>
                </div>
                <div class="slide">
                    <img src="attached_assets/attached_assets/generated_images/Friends_champagne_success_celebration_a32bf813.png" alt="Financial Growth">
                    <div class="slide-overlay">
                        <h1 class="slide-title">Guaranteed Daily Returns</h1>
                        <p class="slide-subtitle">Complete your daily tasks and receive your investment profits</p>
                    </div>
                </div>

                <!-- Slideshow Navigation -->
                <div class="slide-nav">
                    <div class="slide-dot active" onclick="currentSlide(1)"></div>
                    <div class="slide-dot" onclick="currentSlide(2)"></div>
                    <div class="slide-dot" onclick="currentSlide(3)"></div>
                    <div class="slide-dot" onclick="currentSlide(4)"></div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Wallet Section -->
            <div class="wallet-section">
                <h3 class="section-title">My Wallet</h3>

                <div class="wallet-balances">
                    <div class="balance-card commission">
                        <div class="balance-label">Balance</div>
                        <div class="balance-amount"><?= formatAmount($user_balance_main) ?></div>
                    </div>

                    <div class="balance-card referral">
                        <div class="balance-label">Total Referral Earned</div>
                        <div class="balance-amount"><?= formatAmount($total_referral_earned) ?></div>
                    </div>
                </div>

                

                <div class="wallet-actions">
                    <a href="deposit.php" class="wallet-action-btn deposit">Deposit</a>
                    <a href="withdraw.php" class="wallet-action-btn withdraw">Withdraw</a>
                </div>
            </div>

            <!-- Daily Progress -->
            <div class="daily-progress">
                <h3 class="section-title">Daily Activity Progress</h3>
                <div class="progress-ring">
                    <div class="progress-circle">
                        <div class="progress-inner">
                            <div class="progress-time" id="daily-progress-time">0/<?= $daily_time_required ?></div>
                            <div class="progress-label" id="time-unit-label">minutes</div>
                        </div>
                    </div>
                </div>
                <p id="daily-progress-message">You have spend <span id="time-spent-text">0 minutes</span> on system today.</p>
            </div>

            <!-- Today's Tasks -->
            <div class="tasks-section">
                <h3 class="section-title">Today's Tasks</h3>

                <?php if (!$user_has_packages): ?>
                    <div class="task-progress">
                        <span class="task-counter" style="color: #666;">No Investment Packages</span>
                        <span style="color: #FF9800; font-weight: bold;" id="task-status">
                            Please invest first to start earning
                        </span>
                    </div>
                    <button class="continue-btn" style="background: #4CAF50;" 
                            onclick="location.href='invest.php'">
                        Start Investing
                    </button>
                <?php else: ?>
                    <!-- SELECTED PACKAGE DISPLAY - Shows specific package user is working on -->
                    <?php if ($selected_package_data): ?>
                        <div style="text-align: center; margin-bottom: 15px; color: #4CAF50; font-weight: bold; background: #f0f9f0; padding: 15px; border-radius: 10px;">
                            <strong>🎯 Currently Working On:</strong> <?= htmlspecialchars($selected_package_data['package']['name']) ?> Package
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; margin-bottom: 15px; color: #FF9800; font-weight: bold; background: #fff8e1; padding: 15px; border-radius: 10px;">
                            <strong>📦 No Package Selected:</strong> Go to <a href="orders.php" style="color: #FF9800; text-decoration: underline;">Orders Page</a> to choose which package to work on today<br>
                            <small style="color: #666; font-weight: normal;">Dashboard will show progress for the package you select in Orders</small>
                        </div>
                    <?php endif; ?>
                    <div class="task-progress">
                        <span class="task-counter" id="task-counter"><?= $todays_orders_completed ?>/<?= $todays_orders_total ?> Orders Completed</span>
                        <span style="color: #4CAF50; font-weight: bold;" id="task-status">
                            <?php 
                            if (!$selected_package_data) {
                                echo 'No Package Selected - Go to Orders Page';
                            } elseif ($todays_orders_total == 0) {
                                echo 'No Progress - Package Not Found';
                            } else {
                                // Calculate percentage properly (cap at 100%)
                                $percentage = min(100, round(($todays_orders_completed / $todays_orders_total) * 100));

                                // Get target earnings ONLY for selected package
                                $total_earnings_target = 0;
                                $package_name = $selected_package_data['package']['name'];
                                // Get live package data from Firebase
                                $live_package_data = readJsonFile(strtolower($package_name));

                                if (!empty($live_package_data)) {
                                    $total_earnings_target = $live_package_data['daily_profit'] ?? 0;
                                } else {
                                    $total_earnings_target = $selected_package_data['package']['daily_profit'] ?? 0;
                                }

                                // Check completion status for SELECTED package only
                                $orders_complete = ($todays_orders_completed >= $todays_orders_total);
                                $package_earnings_complete = ($todays_commission >= $total_earnings_target); // Renamed for clarity

                                if ($orders_complete && $package_earnings_complete) {
                                    echo htmlspecialchars($selected_package_data['package']['name']) . ' Package Complete! (100%) - ₦' . number_format($total_earnings_target, 2);
                                } else {
                                    $orders_left = max(0, $todays_orders_total - $todays_orders_completed);
                                    echo htmlspecialchars($selected_package_data['package']['name']) . ' Package: (' . $percentage . '%) - ' . $orders_left . ' orders left';
                                }
                            }
                            ?>
                        </span>
                    </div>
                    <?php
                    // Check current completion status based on context
                    $orders_complete = ($todays_orders_completed >= $todays_orders_total);

                    // Get the correct earnings target
                    $total_earnings_target = 0;
                    if ($selected_package_data) {
                        // Use selected package target
                        $package_name = $selected_package_data['package']['name'];
                        // Get live package data from Firebase
                        $live_package_data = readJsonFile('backend/storage/' . strtolower($package_name) . '.json');

                        if (!empty($live_package_data)) {
                            $total_earnings_target = $live_package_data['daily_profit'] ?? 0;
                        } else {
                            $total_earnings_target = $selected_package_data['package']['daily_profit'] ?? 0;
                        }
                    } else {
                        // Use combined target from all packages
                        foreach ($active_packages as $package) {
                            $package_name = $package['name'];
                            // Get live package data from Firebase
                            $live_package_data = readJsonFile('backend/storage/' . strtolower($package_name) . '.json');

                            if (!empty($live_package_data)) {
                                $total_earnings_target += $live_package_data['daily_profit'] ?? 0;
                            } else {
                                $total_earnings_target += $package['daily_profit'] ?? 0;
                            }
                        }
                    }

                    $earnings_complete = ($todays_commission >= $total_earnings_target);
                    $daily_complete = ($orders_complete && $earnings_complete);
                    ?>

                    <button class="continue-btn" id="continue-tasks-btn" 
                            onclick="location.href='orders.php<?= $selected_package ? '?selected_package=' . urlencode($selected_package) : '' ?>'" 
                            <?= ($selected_package_data && $daily_complete) ? 'disabled style="background: #ccc; cursor: not-allowed;"' : '' ?>>
                        <?php 
                        if (!$selected_package_data) {
                            echo 'Go to Orders Page';
                        } elseif ($daily_complete) {
                            echo htmlspecialchars($selected_package_data['package']['name']) . ' Package Completed!';
                        } else {
                            echo 'Complete 30 Minutes First';
                        }
                        ?>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Daily Check-in -->
            <?php if ($daily_checkin_available): ?>
            <div class="checkin-card success-animation">
                <h3>Daily Check-in Bonus</h3>
                <p>Claim your daily ₦20 bonus!</p>
                <button class="checkin-btn" onclick="claimCheckin()">Claim Now</button>
            </div>
            <?php else: ?>
            <div class="checkin-card" style="background: linear-gradient(135deg, #6c757d, #5a6268);">
                <h3>Daily Check-in Bonus</h3>
                <p>Claim your daily ₦20 bonus!</p>
                <button class="checkin-btn" disabled style="background: rgba(255,255,255,0.1); cursor: not-allowed;">
                    Already Claimed Today
                </button>
            </div>
            <?php endif; ?>



            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3 class="section-title" style="margin-bottom: 20px;">Quick Actions</h3>
                <div class="actions-grid">
                    <button class="action-btn invest" onclick="location.href='invest.php'">
                        Invest
                    </button>
                    <button class="action-btn games" onclick="location.href='games.php'">
                        Games
                    </button>
                    <button class="action-btn history" onclick="location.href='history.php'">
                        History
                    </button>
                    <button class="action-btn profile" onclick="location.href='profile.php'">
                        Profile
                    </button>
                    <button class="action-btn about" onclick="location.href='about.php'">
                        About
                    </button>
                    <button class="action-btn support" onclick="location.href='support.php'">
                        Support
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification Popup System -->
        <div id="notification-popup" class="notification-popup" style="display: none;">
            <div class="popup-content">
                <div class="popup-header">
                    <div class="notification-icon">🔔</div>
                    <h3 class="popup-title">New Notification</h3>
                    <button class="popup-close" onclick="closeNotificationPopup()">×</button>
                </div>
                <div class="popup-body">
                    <div class="notification-item">
                        <div class="notification-text" id="notification-message">
                            <!-- Notification message will be populated here -->
                        </div>
                        <div class="notification-time" id="notification-time">
                            <!-- Notification time will be populated here -->
                        </div>
                    </div>
                </div>
                <div class="popup-actions">
                    <button class="popup-btn view-btn" onclick="viewAllNotifications()">View Messages</button>
                    <button class="popup-btn mark-read-btn" onclick="markCurrentAsRead()">Mark as Read</button>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <div class="nav-container">
                <a href="dashboard.php" class="nav-item active">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                    </svg>
                    <span class="nav-label">Dashboard</span>
                </a>

                <a href="invest.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                    </svg>
                    <span class="nav-label">Invest</span>
                </a>

                <a href="orders.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                    <span class="nav-label">Orders</span>
                </a>

                <a href="games.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M15 7.5V2H9v5.5l3 3 3-3zM7.5 9H2v6h5.5l3-3-3-3zM9 16.5V22h6v-5.5l-3-3-3 3zM16.5 9l-3 3 3 3H22V9h-5.5z"/>
                    </svg>
                    <span class="nav-label">Games</span>
                </a>

                <a href="history.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                    </svg>
                    <span class="nav-label">History</span>
                </a>

                <a href="profile.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <span class="nav-label">Profile</span>
                </a>

                <a href="more.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                    </svg>
                    <span class="nav-label">More</span>
                </a>
            </div>
        </nav>
    </div>

    <script>
        let slideIndex = 0;

        // PHP notification data passed to JavaScript
        const unreadNotifications = <?php echo json_encode($unread_notifications); ?>;

        // Notification Popup Controller (using PHP data instead of API)
        class NotificationPopupController {
            constructor() {
                this.notifications = [...unreadNotifications];
                this.currentIndex = 0;
                this.popup = document.getElementById('notification-popup');
                this.intervalId = null;
                this.isShowing = false;

                // Start the popup system if there are unread notifications
                if (this.notifications.length > 0) {
                    this.startPopupCycle();
                }
            }

            startPopupCycle() {
                // Show first notification immediately
                this.showNotification();

                // Set interval for 10 seconds (8 seconds display + 2 seconds gap)
                this.intervalId = setInterval(() => {
                    this.showNextNotification();
                }, 10000);
            }

            showNotification() {
                if (this.notifications.length === 0) {
                    this.stopPopupCycle();
                    return;
                }

                const notification = this.notifications[this.currentIndex];

                // Update popup content
                document.getElementById('notification-message').innerHTML = notification.title + '<br><small>' + notification.message + '</small>';
                document.getElementById('notification-time').textContent = this.formatTime(notification.created_at);

                // Store current notification ID for mark as read
                this.popup.dataset.notificationId = notification.id;

                // Show popup with animation
                this.popup.style.display = 'block';
                setTimeout(() => {
                    this.popup.classList.add('show');
                }, 100);

                this.isShowing = true;

                // Hide after 8 seconds, then show next after 2 more seconds
                setTimeout(() => {
                    this.hidePopup();
                }, 8000);
            }

            showNextNotification() {
                if (this.notifications.length === 0) {
                    this.stopPopupCycle();
                    return;
                }

                this.currentIndex = (this.currentIndex + 1) % this.notifications.length;
                this.showNotification();
            }

            hidePopup() {
                this.popup.classList.remove('show');
                setTimeout(() => {
                    if (!this.popup.classList.contains('show')) {
                        this.popup.style.display = 'none';
                    }
                }, 300);
                this.isShowing = false;
            }

            formatTime(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diff = Math.floor((now - date) / 1000);

                if (diff < 60) return 'Just now';
                if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
                if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
                return Math.floor(diff / 86400) + ' days ago';
            }

            markCurrentAsRead() {
                const notificationId = this.popup.dataset.notificationId;
                if (!notificationId) return;

                // Send POST request to mark as read
                const formData = new FormData();
                formData.append('action', 'mark_read');
                formData.append('notification_id', notificationId);

                fetch('notifications.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove from local notifications array
                        this.notifications = this.notifications.filter(n => n.id !== notificationId);

                        // Adjust current index if necessary
                        if (this.currentIndex >= this.notifications.length) {
                            this.currentIndex = 0;
                        }

                        // Hide current popup
                        this.hidePopup();

                        // If no more notifications, stop the cycle
                        if (this.notifications.length === 0) {
                            this.stopPopupCycle();
                        }
                    }
                })
                .catch(error => {
                    console.log('Error marking notification as read:', error);
                });
            }

            stopPopupCycle() {
                if (this.intervalId) {
                    clearInterval(this.intervalId);
                    this.intervalId = null;
                }
                this.hidePopup();
            }
        }

        // Global notification popup controller
        let notificationController;

        // Initialize popup system when page loads
        document.addEventListener('DOMContentLoaded', function() {
            notificationController = new NotificationPopupController();
        });

        // Global functions for popup buttons
        function closeNotificationPopup() {
            if (notificationController) {
                notificationController.hidePopup();
            }
        }

        function viewAllNotifications() {
            window.location.href = 'notifications.php';
        }

        function markCurrentAsRead() {
            if (notificationController) {
                notificationController.markCurrentAsRead();
            }
        }

        // Slideshow functionality
        function showSlide(n) {
            const slides = document.querySelectorAll('.slide');
            const dots = document.querySelectorAll('.slide-dot');

            if (n >= slides.length) slideIndex = 0;
            if (n < 0) slideIndex = slides.length - 1;

            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));

            slides[slideIndex].classList.add('active');
            dots[slideIndex].classList.add('active');
        }

        function currentSlide(n) {
            slideIndex = n - 1;
            showSlide(slideIndex);
        }

        function nextSlide() {
            slideIndex++;
            showSlide(slideIndex);
        }

        // Auto-advance slideshow
        setInterval(nextSlide, 5000);



        // Daily check-in claim - ENHANCED WITH ANIMATIONS
        function claimCheckin() {
            const checkinCard = document.querySelector('.checkin-card');
            const claimBtn = document.querySelector('.checkin-btn');

            // Start claiming animation
            checkinCard.classList.remove('success-animation');
            checkinCard.classList.add('claiming-animation');
            checkinCard.style.background = 'linear-gradient(135deg, #FF9800, #F57C00, #FF9800)';
            checkinCard.style.backgroundSize = '200% 100%';

            // Disable button with animation
            claimBtn.disabled = true;
            claimBtn.textContent = '⏳ Claiming...';
            claimBtn.style.transform = 'scale(0.95)';

            // Send real request to server
            fetch('/claim-daily-bonus.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'claim_daily_bonus' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Stop claiming animation
                    checkinCard.classList.remove('claiming-animation');
                    
                    // Start celebration animation
                    checkinCard.classList.add('celebration-animation');
                    
                    // Create money rain effect
                    createMoneyRain(checkinCard);
                    
                    // Show success message with celebration
                    setTimeout(() => {
                        checkinCard.innerHTML = `
                            <div class="money-rain" id="money-rain"></div>
                            <h3>🎉 Bonus Claimed Successfully!</h3>
                            <p style="font-size: 1.1rem; font-weight: bold; color: #FFD700;">₦20 has been added to your balance!</p>
                            <p style="font-size: 0.9rem; opacity: 0.9;">Come back tomorrow at 12:00 AM (Lagos time) for your next bonus!</p>
                            <button class="checkin-btn" disabled style="background: rgba(255,255,255,0.1); cursor: not-allowed;">
                                ✅ Already Claimed Today
                            </button>
                        `;
                        
                        // Continue money rain in new content
                        const newMoneyRain = document.getElementById('money-rain');
                        if (newMoneyRain) {
                            createMoneyRain(newMoneyRain.parentElement);
                        }
                    }, 800);

                    // Update displayed balance after celebration
                    setTimeout(() => {
                        location.reload(); // Refresh to show updated balance and proper state
                    }, 4000);
                    
                } else {
                    // Stop claiming animation
                    checkinCard.classList.remove('claiming-animation');
                    
                    // If already claimed today, show already claimed message
                    if (data.message && data.message.includes('already claimed')) {
                        checkinCard.innerHTML = `
                            <h3>ℹ️ Already Claimed Today</h3>
                            <p style="font-size: 0.9rem; opacity: 0.8;">You have already claimed your daily bonus today.</p>
                            <p style="font-size: 0.9rem; opacity: 0.8;">Come back tomorrow at 12:00 AM (Lagos time) for your next bonus!</p>
                            <button class="checkin-btn" disabled style="background: rgba(255,255,255,0.1); cursor: not-allowed;">
                                Already Claimed Today
                            </button>
                        `;
                        checkinCard.style.background = 'linear-gradient(135deg, #6c757d, #5a6268)';
                    } else {
                        // Re-enable button for other errors with animation
                        claimBtn.disabled = false;
                        claimBtn.textContent = 'Claim Now';
                        claimBtn.style.transform = 'scale(1)';
                        checkinCard.style.background = 'linear-gradient(135deg, #FFD700, #FFA500)';
                        
                        // Show error with shake animation
                        checkinCard.style.animation = 'shake 0.5s ease-in-out';
                        setTimeout(() => {
                            checkinCard.style.animation = '';
                            checkinCard.classList.add('success-animation');
                        }, 500);
                        
                        alert('❌ Failed to claim bonus: ' + (data.message || 'Unknown error'));
                    }
                }
            })
            .catch(error => {
                // Stop claiming animation
                checkinCard.classList.remove('claiming-animation');
                
                // Re-enable button for network errors with animation
                claimBtn.disabled = false;
                claimBtn.textContent = 'Claim Now';
                claimBtn.style.transform = 'scale(1)';
                checkinCard.style.background = 'linear-gradient(135deg, #FFD700, #FFA500)';
                checkinCard.classList.add('success-animation');
                
                alert('🌐 Network error. Please check your connection and try again.');
            });
        }

        // Create money rain animation
        function createMoneyRain(container) {
            const moneySymbols = ['₦', '💰', '💵', '💎', '⭐'];
            const rainContainer = container.querySelector('.money-rain') || container;
            
            // Clear existing money rain
            const existingMoney = rainContainer.querySelectorAll('.money-symbol');
            existingMoney.forEach(symbol => symbol.remove());
            
            // Create new money rain
            for (let i = 0; i < 15; i++) {
                setTimeout(() => {
                    const money = document.createElement('div');
                    money.className = 'money-symbol';
                    money.textContent = moneySymbols[Math.floor(Math.random() * moneySymbols.length)];
                    money.style.left = Math.random() * 100 + '%';
                    money.style.animationDelay = Math.random() * 0.5 + 's';
                    money.style.animationDuration = (Math.random() * 1 + 1.5) + 's';
                    
                    if (rainContainer === container) {
                        // Create temporary container if none exists
                        let tempRain = container.querySelector('.temp-money-rain');
                        if (!tempRain) {
                            tempRain = document.createElement('div');
                            tempRain.className = 'money-rain temp-money-rain';
                            container.appendChild(tempRain);
                        }
                        tempRain.appendChild(money);
                    } else {
                        rainContainer.appendChild(money);
                    }
                    
                    // Remove after animation
                    setTimeout(() => {
                        money.remove();
                    }, 2500);
                }, i * 100);
            }
        }

        // Add shake animation keyframes
        const shakeKeyframes = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        `;
        
        // Add shake keyframes to document
        if (!document.querySelector('#shake-keyframes')) {
            const style = document.createElement('style');
            style.id = 'shake-keyframes';
            style.textContent = shakeKeyframes;
            document.head.appendChild(style);
        }

        // Simulate real-time updates
        function updateDashboard() {
            // Add subtle animations to show the app is "live"
            const now = new Date();
            if (now.getSeconds() % 10 === 0) {
                document.querySelector('.success-animation')?.classList.add('pulse');
                setTimeout(() => {
                    document.querySelector('.success-animation')?.classList.remove('pulse');
                }, 1000);
            }
        }

        // Real-time Activity Tracker
        class ActivityTracker {
            constructor() {
                this.requiredMinutes = <?= $daily_time_required ?>;
                this.startTime = Date.now();
                this.init();
            }

            init() {
                this.loadDailyTime();
                this.startTracking();
                this.updateDisplay();
                setInterval(() => this.updateTracking(), 1000);
            }

            loadDailyTime() {
                const today = new Date().toDateString();
                const stored = localStorage.getItem('daily_activity');

                if (stored) {
                    const data = JSON.parse(stored);
                    if (data.date === today) {
                        this.totalMinutes = data.minutes || 0;
                        this.lastActiveTime = data.lastActive || Date.now();
                    } else {
                        // Reset for new day
                        this.totalMinutes = 0;
                        this.lastActiveTime = Date.now();
                        this.saveDailyTime();
                    }
                } else {
                    this.totalMinutes = 0;
                    this.lastActiveTime = Date.now();
                }
            }

            saveDailyTime() {
                const today = new Date().toDateString();
                const data = {
                    date: today,
                    minutes: this.totalMinutes,
                    lastActive: this.lastActiveTime
                };
                localStorage.setItem('daily_activity', JSON.stringify(data));
            }

            startTracking() {
                this.lastActiveTime = Date.now();

                // Track user interaction
                ['click', 'keypress', 'scroll', 'mousemove'].forEach(event => {
                    document.addEventListener(event, () => {
                        this.lastActiveTime = Date.now();
                    });
                });
            }

            updateTracking() {
                const now = Date.now();
                const timeDiff = now - this.lastActiveTime;

                // Only count time if user was active in last 5 seconds
                if (timeDiff < 5000) {
                    this.totalMinutes += (1 / 60); // Add 1 second worth of minutes
                    this.saveDailyTime();
                }

                this.updateDisplay();
            }

            updateDisplay() {
                const minutes = Math.floor(this.totalMinutes);
                const hours = Math.floor(minutes / 60);
                const remainingMins = minutes % 60;

                let timeText, progressText;

                if (hours > 0) {
                    timeText = `${hours} hour${hours > 1 ? 's' : ''} ${remainingMins > 0 ? ` ${remainingMins} minute${remainingMins !== 1 ? 's' : ''}` : ''}`;
                    progressText = `${hours}h ${remainingMins}m / 30m`;
                    document.getElementById('time-unit-label').textContent = 'required';
                } else {
                    timeText = `${minutes} minute${minutes !== 1 ? 's' : ''}`;
                    progressText = `${minutes}/${this.requiredMinutes}`;
                    document.getElementById('time-unit-label').textContent = 'minutes';
                }

                document.getElementById('daily-progress-time').textContent = progressText;
                const timeSpentElement = document.getElementById('time-spent-text');
                if (timeSpentElement) {
                    timeSpentElement.textContent = timeText;
                }

                // Update progress circle
                const progressPercent = Math.min((minutes / this.requiredMinutes) * 100, 100);
                const progressDegrees = (progressPercent / 100) * 360;
                const progressCircle = document.querySelector('.progress-circle');
                progressCircle.style.background = `conic-gradient(#4CAF50 ${progressDegrees}deg, #f0f0f0 0deg)`;

                // Update button and message
                const continueBtn = document.getElementById('continue-tasks-btn');
                const totalOrders = <?= $todays_orders_total ?>;

                if (continueBtn) { // Only update if button exists
                    if (totalOrders === 0) {
                        // No packages bought - show 0
                        continueBtn.disabled = true;
                        continueBtn.textContent = 'Start Investing';
                        const taskCounter = document.getElementById('task-counter');
                        const taskStatus = document.getElementById('task-status');
                        if (taskCounter) taskCounter.textContent = 'No Investment Packages';
                        if (taskStatus) {
                            taskStatus.textContent = 'Please invest first to start earning';
                            taskStatus.style.color = '#FF9800';
                        }
                    } else if (minutes >= this.requiredMinutes) {
                        continueBtn.disabled = false;
                        continueBtn.textContent = 'Continue Tasks';
                    } else {
                        continueBtn.disabled = true;
                        continueBtn.textContent = `Complete ${Math.ceil(this.requiredMinutes - minutes)} More Minutes`;
                    }
                }
            }
        }

        // Initialize activity tracker
        const tracker = new ActivityTracker();

        // Initialize
        setInterval(updateDashboard, 1000);

        // Add loading states for buttons
        document.querySelectorAll('button, .action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.onclick || this.href) {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                }
            });
        });
    </script>
</body>
</html>