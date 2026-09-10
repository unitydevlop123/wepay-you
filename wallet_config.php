<?php require_once 'firebase_setup.php'; ?>
<?php
// Shared wallet configuration for all investment pages
session_start();

// Function to format amounts with proper comma separation
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Get real user data from Firebase
$user_balance_main = 0.00;
$user_commission = 0.00;
$user_referral_earn = 0.00;

if (isset($_SESSION['email'])) {
    $users = getUsers();
    if (!empty($users)) {
        foreach ($users as $user) {
            if ($user['email'] === $_SESSION['email']) {
                $user_balance_main = $user['balance'] ?? 0.00;
                $user_commission = $user['commission_balance'] ?? 0.00;
                $user_referral_earn = $user['referral_balance'] ?? 0.00;
                break;
            }
        }
    }
}
$total_balance = $user_balance_main + $user_commission + $user_referral_earn;

// Available balance for investments (commission balance)
$available_balance = $user_commission;

// Check if user has sufficient balance for investment
function canInvest($package_price, $available_balance) {
    return $available_balance >= $package_price;
}

// Process investment purchase
function processInvestment($package_price, $package_name) {
    global $user_commission;
    
    if (canInvest($package_price, $user_commission)) {
        // In real app, this would update database
        $user_commission -= $package_price;
        return [
            'success' => true,
            'message' => "Successfully purchased {$package_name} package!",
            'new_balance' => $user_commission
        ];
    } else {
        return [
            'success' => false,
            'message' => "Insufficient balance. You need " . formatAmount($package_price - $user_commission) . " more.",
            'new_balance' => $user_commission
        ];
    }
}
?>