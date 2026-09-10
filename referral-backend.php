<?php
// Include authentication system
require_once 'auth.php';
require_once 'firebase_setup.php';

// SECURITY: Enable proper authentication
requireLogin();

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");

// Get current user email from session (real user data)
$current_user_email = $_SESSION['email'] ?? null;

// If no session, return error
if (!$current_user_email) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if ($_POST['action'] === 'send_invitation') {
    $friend_email = trim($_POST['email']);

    if (!$friend_email || !filter_var($friend_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }

    // Prevent self-referral and similar emails
    if (strtolower($friend_email) === strtolower($current_user_email)) {
        echo json_encode(['success' => false, 'message' => '❌ You cannot invite yourself! Please invite friends and family instead.']);
        exit;
    }

    // Check if the friend's email is already registered
    $users = getUsers();
    $emailAlreadyRegistered = false;
    if (!empty($users)) {
        foreach ($users as $user) {
            if (isset($user['email']) && strtolower($user['email']) === strtolower($friend_email)) {
                $emailAlreadyRegistered = true;
                break;
            }
        }
    }

    if ($emailAlreadyRegistered) {
        echo json_encode(['success' => false, 'message' => '❌ This email is already registered on our platform! Please invite someone who hasn\'t joined yet.']);
        exit;
    }

    // Prevent similar email addresses (same username with different numbers)
    $user_base = preg_replace('/\d+/', '', explode('@', strtolower($current_user_email))[0]);
    $friend_base = preg_replace('/\d+/', '', explode('@', strtolower($friend_email))[0]);
    if ($user_base === $friend_base && strlen($user_base) > 3) {
        echo json_encode(['success' => false, 'message' => '❌ You cannot invite similar email addresses! Please invite different people.']);
        exit;
    }

    // Generate unique referral code
    $code = 'wepay-you' . mt_rand(10000, 99999);

    // Store referral invitation in Firebase
    $referral_data = [
        'code' => $code,
        'inviter_email' => $current_user_email,
        'invited_email' => $friend_email,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'reward_claimed' => false
    ];

    // Save to Firebase
    $current_referrals = readJsonFile('referral_codes') ?: [];
    $current_referrals[$code] = $referral_data;
    writeJsonFile('referral_codes', $current_referrals);

    echo json_encode([
        'success' => true, 
        'message' => "✅ Invitation sent! Share this code with your friend: <strong>$code</strong>",
        'code' => $code
    ]);
    exit;
}

if ($_POST['action'] === 'enter_code') {
    $code = trim($_POST['code']);

    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Please enter a referral code']);
        exit;
    }

    if (!preg_match('/^wepay-you\d+$/', $code)) {
        echo json_encode(['success' => false, 'message' => '❌ Invalid code format. Please ask inviter for a valid code.']);
        exit;
    }

    // Check if code exists in Firebase
    $referral_codes = readJsonFile('referral_codes') ?: [];

    if (!isset($referral_codes[$code])) {
        echo json_encode(['success' => false, 'message' => '❌ Code not found. Please check with inviter.']);
        exit;
    }

    $referral_data = $referral_codes[$code];

    // Check if user already used this code
    if ($referral_data['invited_email'] !== $current_user_email) {
        echo json_encode(['success' => false, 'message' => '❌ This code was not meant for your email.']);
        exit;
    }

    if ($referral_data['status'] === 'used') {
        echo json_encode(['success' => false, 'message' => '❌ Code already used.']);
        exit;
    }

    // Mark code as used and give reward
    $referral_codes[$code]['status'] = 'used';
    $referral_codes[$code]['used_at'] = date('Y-m-d H:i:s');
    writeJsonFile('referral_codes', $referral_codes);

    // Add ₦20 to user balance
    $users = getUsers();
    $invitee = null;
    $inviteeIndex = -1;
    foreach ($users as $key => $user) {
        if ($user['email'] === $current_user_email) {
            $invitee = $user;
            $inviteeIndex = $key;
            break;
        }
    }

    if ($invitee) {
        $instant_bonus = 20; // Amount for instant bonus

        // Award instant bonus to new user
        $invitee['balance'] += $instant_bonus;
        $invitee['total_earned'] = ($invitee['total_earned'] ?? 0) + $instant_bonus;

        // Store referral earnings persistently in user profile (protected from transaction deletion)
        $invitee['referral_balance'] = ($invitee['referral_balance'] ?? 0) + $instant_bonus;
        $invitee['total_referral_earnings'] = ($invitee['total_referral_earnings'] ?? 0) + $instant_bonus;

        // Save updated user data
        $users[$inviteeIndex] = $invitee;
        saveUsers($users);

        // Generate referral bonus notification (Firebase format)
        $referral_notification = [
            'id' => 'referral_' . time() . '_' . rand(1000, 9999),
            'type' => 'referral',
            'title' => 'Referral Bonus Earned!',
            'message' => "👥 Congratulations! You've earned ₦" . number_format($instant_bonus, 2) . " for joining us using a referral code.",
            'amount' => $instant_bonus,
            'status' => 'unread',
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null
        ];

        // Add notification using Firebase function
        addUserNotification($invitee['email'], $referral_notification);

        // Create referral transaction record (Firebase format)
        $referral_transaction = [
            'id' => 'REF_' . strtoupper(bin2hex(random_bytes(4))),
            'transaction_type' => 'referral_bonus',
            'description' => 'Instant Referral Bonus - Code: ' . $code,
            'amount' => $instant_bonus,
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s'),
            'reference' => 'REFERRAL-' . $code
        ];

        // Add transaction using Firebase function
        addUserTransaction($invitee['email'], $referral_transaction);
    }


    echo json_encode([
        'success' => true, 
        'message' => '🎉 Code accepted! You earned ₦20 instantly!'
    ]);
    exit;
}

if ($_POST['action'] === 'claim_reward') {
    $code = trim($_POST['code']);

    $referral_codes = readJsonFile('referral_codes') ?: [];

    if (!isset($referral_codes[$code])) {
        echo json_encode(['success' => false, 'message' => 'Code not found']);
        exit;
    }

    $referral_data = $referral_codes[$code];

    if ($referral_data['inviter_email'] !== $current_user_email) {
        echo json_encode(['success' => false, 'message' => 'Not your referral code']);
        exit;
    }

    if ($referral_data['status'] !== 'used') {
        echo json_encode(['success' => false, 'message' => 'Code not used yet']);
        exit;
    }

    if ($referral_data['reward_claimed']) {
        echo json_encode(['success' => false, 'message' => 'Reward already claimed']);
        exit;
    }

    // Check if the referred person has invested in any package
    $referred_email = $referral_data['invited_email'];
    $users = getUsers();
    $has_invested = false;
    $referred_user_index = -1;

    foreach ($users as $key => $user) {
        if ($user['email'] === $referred_email) {
            $referred_user_index = $key;
            // Check if user has any active packages
            if (isset($user['active_packages']) && !empty($user['active_packages'])) {
                $has_invested = true;
            }
            break;
        }
    }

    if (!$has_invested) {
        echo json_encode(['success' => false, 'message' => '❌ Your referred friend must invest in a package first before you can claim this reward.']);
        exit;
    }

    // Mark reward as claimed and give ₦100
    $referral_codes[$code]['reward_claimed'] = true;
    $referral_codes[$code]['reward_claimed_at'] = date('Y-m-d H:i:s');
    writeJsonFile('referral_codes', $referral_codes);

    // Add ₦100 to inviter balance
    $users = getUsers(); // Re-fetch users to ensure we have the latest data
    $referrer = null;
    $referrerIndex = -1;
    foreach ($users as $key => $user) {
        if ($user['email'] === $current_user_email) {
            $referrer = $user;
            $referrerIndex = $key;
            break;
        }
    }

    if ($referrer) {
        $referral_bonus = 100; // Amount for referral bonus

        // Award referral bonus to referrer
        $referrer['balance'] += $referral_bonus;
        $referrer['total_earned'] = ($referrer['total_earned'] ?? 0) + $referral_bonus;
        $referrer['total_referrals'] = ($referrer['total_referrals'] ?? 0) + 1;
        // Also update total referral earnings to be persistent
        $referrer['total_referral_earnings'] = ($referrer['total_referral_earnings'] ?? 0) + $referral_bonus;


        // Save updated user data
        $users[$referrerIndex] = $referrer;
        saveUsers($users);

        // Generate referral bonus notification (Firebase format)
        $referral_notification = [
            'id' => 'referral_' . time() . '_' . rand(1000, 9999),
            'type' => 'referral',
            'title' => 'Referral Bonus Earned!',
            'message' => "👥 Congratulations! You've earned ₦" . number_format($referral_bonus, 2) . " for successfully referring a new user to our platform.",
            'amount' => $referral_bonus,
            'status' => 'unread',
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null
        ];

        // Add notification using Firebase function
        addUserNotification($referrer['email'], $referral_notification);

        // Create referral transaction record (Firebase format)
        $referral_transaction = [
            'id' => 'REF_' . strtoupper(bin2hex(random_bytes(4))),
            'transaction_type' => 'referral_bonus',
            'description' => 'Referral Bonus - Code: ' . $code,
            'amount' => $referral_bonus,
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s'),
            'reference' => 'REFERRAL-' . $code
        ];

        // Add transaction using Firebase function
        addUserTransaction($referrer['email'], $referral_transaction);
    }

    echo json_encode([
        'success' => true, 
        'message' => '💰 Reward claimed! You earned ₦100!'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>