<?php
// Include authentication system
require_once 'auth.php';
require_once 'firebase_setup.php';

// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Enable proper authentication  
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Prevent caching for development
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Function to format amounts with proper comma separation
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Get user data from Firebase
$user_data = null;

if (isset($_SESSION['email'])) {
    $users = getUsers();
    
    // Ensure $users is an array
    if (!is_array($users)) {
        $users = [];
    }
    
    if (!empty($users)) {
        foreach ($users as $user) {
            if (is_array($user) && isset($user['email']) && $user['email'] === $_SESSION['email']) {
                $user_data = $user;
                break;
            }
        }
    }
}

// Get current user email from session (real user data)
$current_user_email = $_SESSION['email'] ?? null;

// If no session, redirect to login
if (!$current_user_email) {
    header('Location: login.php');
    exit;
}

// Get referral earnings directly from Firebase (same as dashboard)
$referral_earnings = 0.00;
$user_main_balance = 0.00;
$users = getUsers();
if (!empty($users)) {
    foreach ($users as $user) {
        // Ensure $user is an array before accessing its elements  
        if (is_array($user) && isset($user['email']) && $user['email'] === $current_user_email) {
            $referral_earnings = (float)($user['referral_balance'] ?? 0.00);
            $user_main_balance = (float)($user['balance'] ?? 0.00);
            break;
        }
    }
}

// Get NEW referral code stats
$referral_count = 0;
$total_commissions = 0;
$referral_code = '';
$code_referrals = [];
$pending_claims = [];

// Get NEW code-based referral data from Firebase
$referral_codes_data = readJsonFile('referral_codes') ?: [];

// Debug: Always get fresh data and ensure arrays are properly initialized
$code_referrals = [];
$pending_claims = [];
$referral_count = 0;

// Get all referral codes created by this user
foreach ($referral_codes_data as $code => $referral_info) {
    // Ensure $referral_info is an array before accessing its elements
    if (is_array($referral_info) && isset($referral_info['inviter_email']) && $referral_info['inviter_email'] === $current_user_email) {
        $status = $referral_info['status'] ?? 'unknown';
        $reward_claimed = $referral_info['reward_claimed'] ?? false;
        
        if ($status === 'used') {
            $referral_count++;
            
            // Check if referred person has invested
            $referred_email = $referral_info['invited_email'] ?? '';
            $has_invested = false;
            
            if (!empty($users)) {
                foreach ($users as $user) {
                    if (is_array($user) && isset($user['email']) && $user['email'] === $referred_email) {
                        if (isset($user['active_packages']) && !empty($user['active_packages'])) {
                            $has_invested = true;
                        }
                        break;
                    }
                }
            }
            
            $code_referrals[] = [
                'code' => $code,
                'invited_email' => $referral_info['invited_email'] ?? 'Unknown',
                'status' => $status,
                'used_at' => $referral_info['used_at'] ?? '',
                'reward_claimed' => $reward_claimed,
                'reward_amount' => $reward_claimed ? 200 : 0,
                'can_claim' => ($status === 'used' && !$reward_claimed && $has_invested),
                'has_invested' => $has_invested
            ];
        } elseif ($status === 'pending') {
            $pending_claims[] = [
                'code' => $code,
                'invited_email' => $referral_info['invited_email'] ?? 'Unknown',
                'created_at' => $referral_info['created_at'] ?? date('Y-m-d H:i:s'),
                'status' => $status
            ];
        }
    }
}

// Convert dates to Nigeria time
function convertToNigeriaTime($dateString) {
    $date = new DateTime($dateString);
    $date->setTimezone(new DateTimeZone('Africa/Lagos'));
    return $date->format('M d, Y \a\t g:i A');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET PAY YOU - Referral Program</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo img {
            height: 40px;
        }

        .back-btn {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #0056b3;
        }

        /* Hero Banner */
        .hero-banner {
            position: relative;
            height: 200px;
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)),
                       url('attached_assets/attached_assets/generated_images/Friends_celebrating_gaming_win_ad648a5b.png');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .hero-content h1 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .hero-content p {
            font-size: 1rem;
            opacity: 0.9;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        /* Content */
        .content {
            padding: 30px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .referral-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        .referral-code-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }

        .referral-link {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #495057;
            background: white;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin: 10px 0;
            word-break: break-all;
        }

        .copy-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .copy-btn:hover {
            background: #218838;
        }

        .referral-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .referral-table th,
        .referral-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .referral-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .referral-table tr:hover {
            background: #f8f9fa;
        }

        .level-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }

        .level-1 {
            background: #4CAF50;
        }

        .level-2 {
            background: #FF9800;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .earnings-highlight {
            font-weight: bold;
            color: #4CAF50;
        }

        .commission-breakdown {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .commission-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #bbdefb;
        }

        .commission-item:last-child {
            border-bottom: none;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

        .nav-icon {
            width: 24px;
            height: 24px;
            margin-bottom: 4px;
            fill: currentColor;
        }

        /* New Referral System Styles */
        .invite-btn, .code-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(76, 175, 80, 0.3);
        }

        .code-btn {
            background: #FF9800;
            box-shadow: 0 2px 10px rgba(255, 152, 0, 0.3);
        }

        .invite-btn:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
        }

        .code-btn:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.4);
        }

        /* Popup Overlay */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .popup-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
        }

        .popup-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            color: #333;
        }

        .popup-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            margin-bottom: 15px;
            transition: border-color 0.3s ease;
        }

        .popup-input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .popup-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .popup-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .popup-btn.primary {
            background: #4CAF50;
            color: white;
        }

        .popup-btn.primary:hover {
            background: #45a049;
        }

        .popup-btn.secondary {
            background: #f0f0f0;
            color: #333;
        }

        .popup-btn.secondary:hover {
            background: #e0e0e0;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            text-align: center;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 20px 15px 100px;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .referral-table {
                font-size: 0.9rem;
            }

            .referral-table th,
            .referral-table td {
                padding: 10px 8px;
            }

            .popup-content {
                padding: 20px;
                max-width: 350px;
            }

            [style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <img src="logo.svg" alt="LET PAY YOU">
            </div>
            <a href="profile.php" class="back-btn">← Back to Profile</a>
        </div>

        <!-- Hero Banner -->
        <div class="hero-banner">
            <div class="hero-content">
                <h1>Referral Program</h1>
                <p>Earn commissions by inviting friends and family to join LET PAY YOU</p>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Debug Info (remove this after testing) -->
            <?php if (isset($_GET['debug'])): ?>
            <div style="background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #dee2e6;">
                <h3>🔧 Debug Information</h3>
                <p><strong>Current User:</strong> <?= htmlspecialchars($current_user_email) ?></p>
                <p><strong>Total Referral Codes in Firebase:</strong> <?= count($referral_codes_data) ?></p>
                <p><strong>Used Codes Count:</strong> <?= count($code_referrals) ?></p>
                <p><strong>Pending Invitations Count:</strong> <?= count($pending_claims) ?></p>
                
                <details>
                    <summary>Raw Referral Codes Data</summary>
                    <pre><?= htmlspecialchars(json_encode($referral_codes_data, JSON_PRETTY_PRINT)) ?></pre>
                </details>
                
                <details>
                    <summary>Processed Used Codes</summary>
                    <pre><?= htmlspecialchars(json_encode($code_referrals, JSON_PRETTY_PRINT)) ?></pre>
                </details>
                
                <details>
                    <summary>Processed Pending Claims</summary>
                    <pre><?= htmlspecialchars(json_encode($pending_claims, JSON_PRETTY_PRINT)) ?></pre>
                </details>
            </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number"><?= count($code_referrals) ?></div>
                    <div class="stat-label">Successful Referrals</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($pending_claims) ?></div>
                    <div class="stat-label">Pending Invitations</div>
                </div>
            </div>

            <!-- New Referral System -->
            <div class="referral-section">
                <h2 class="section-title">💰 Referral System</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                    <!-- Invite Friends -->
                    <div style="background: #e8f5e8; padding: 20px; border-radius: 12px; text-align: center; border: 2px solid #4CAF50;">
                        <h3 style="color: #2e7d32; margin-bottom: 15px;">👥 Invite Friends & Family</h3>
                        <p style="color: #555; margin-bottom: 15px; font-size: 0.9rem;">Send invitation codes to your friends</p>
                        <button class="invite-btn" onclick="openInvitePopup()">Send Invitation</button>
                    </div>

                    <!-- Enter Code -->
                    <div style="background: #fff3e0; padding: 20px; border-radius: 12px; text-align: center; border: 2px solid #FF9800;">
                        <h3 style="color: #e65100; margin-bottom: 15px;">🎁 Enter Referral Code</h3>
                        <p style="color: #555; margin-bottom: 15px; font-size: 0.9rem;">Got a code from a friend? Enter it here</p>
                        <button class="code-btn" onclick="openCodePopup()">Enter Code</button>
                    </div>
                </div>

                <div class="commission-breakdown">
                    <h4 style="margin-bottom: 15px; color: #333;">💸 Reward Structure:</h4>
                    <div class="commission-item">
                        <span>🎁 When you use someone's code:</span>
                        <span class="earnings-highlight">₦20 instant reward</span>
                    </div>
                    <div class="commission-item">
                        <span>💰 When someone uses your code:</span>
                        <span class="earnings-highlight">₦200 (claim after they invest)</span>
                    </div>
                </div>
            </div>

            <!-- Referral Details -->
            <div class="referral-section">
                <h2 class="section-title">👥 Referral Details</h2>

                <div class="tabs">
                    <div class="tab active" onclick="showTab('used')">Used Codes (<?= count($code_referrals) ?>)</div>
                    <div class="tab" onclick="showTab('pending')">Pending Invitations (<?= count($pending_claims) ?>)</div>
                </div>

                <!-- Used Codes Tab -->
                <div id="used" class="tab-content active">
                    <?php if (!empty($code_referrals)): ?>
                        <table class="referral-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Friend's Email</th>
                                    <th>Used Date</th>
                                    <th>Reward Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($code_referrals as $referral): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($referral['code']) ?></strong></td>
                                    <td><?= htmlspecialchars($referral['invited_email']) ?></td>
                                    <td><?= $referral['used_at'] ? date('M d, Y \a\t g:i A', strtotime($referral['used_at'])) : '-' ?></td>
                                    <td>
                                        <?php if ($referral['reward_claimed']): ?>
                                            <span class="status-badge status-active">✅ Claimed ₦200</span>
                                        <?php elseif ($referral['has_invested']): ?>
                                            <span class="status-badge" style="background: #fff3cd; color: #856404;">⏳ Ready to Claim</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">📦 Friend must invest first</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($referral['can_claim']): ?>
                                            <button onclick="claimReward('<?= $referral['code'] ?>')" 
                                                    style="background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                                Claim ₦200
                                            </button>
                                        <?php elseif ($referral['reward_claimed']): ?>
                                            <span style="color: #28a745; font-weight: bold;">✓ Done</span>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 0.8rem;">⏳ Waiting for investment</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                            <h3>No codes used yet</h3>
                            <p>Send invitation codes to start earning ₦100 rewards!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Invitations Tab -->
                <div id="pending" class="tab-content">
                    <?php if (!empty($pending_claims)): ?>
                        <table class="referral-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Friend's Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_claims as $pending): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($pending['code']) ?></strong></td>
                                    <td><?= htmlspecialchars($pending['invited_email']) ?></td>
                                    <td>
                                        <span class="status-badge" style="background: #e2e3e5; color: #6c757d; font-size: 0.8rem;">⏳ Pending</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                            <h3>No pending invitations</h3>
                            <p>Start sending invitation codes to your friends!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <div class="nav-container">
                <a href="dashboard.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="invest.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                    </svg>
                    <span>Invest</span>
                </a>

                <a href="orders.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                    <span>Orders</span>
                </a>

                <a href="games.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M15 7.5V2H9v5.5l3 3 3-3zM7.5 9H2v6h5.5l3-3-3-3zM9 16.5V22h6v-5.5l-3-3-3 3zM16.5 9l-3 3 3 3H22V9h-5.5z"/>
                    </svg>
                    <span>Games</span>
                </a>

                <a href="history.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                    </svg>
                    <span>History</span>
                </a>

                <a href="profile.php" class="nav-item active">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <span>Profile</span>
                </a>

                <a href="more.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                    </svg>
                    <span>More</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Invite Popup -->
    <div id="invitePopup" class="popup-overlay">
        <div class="popup-content">
            <h3 class="popup-title">👥 Invite Friends & Family</h3>
            <input type="email" id="inviteEmail" class="popup-input" placeholder="Enter friend's email address">
            <div class="popup-buttons">
                <button class="popup-btn secondary" onclick="closePopup('invitePopup')">Cancel</button>
                <button class="popup-btn primary" onclick="sendInvitation()">Send Invitation</button>
            </div>
        </div>
    </div>

    <!-- Enter Code Popup -->
    <div id="codePopup" class="popup-overlay">
        <div class="popup-content">
            <h3 class="popup-title">🎁 Enter Referral Code</h3>
            <input type="text" id="referralCode" class="popup-input" placeholder="Enter code (e.g., wepay-you12345)">
            <div class="popup-buttons">
                <button class="popup-btn secondary" onclick="closePopup('codePopup')">Cancel</button>
                <button class="popup-btn primary" onclick="enterReferralCode()">Submit Code</button>
            </div>
        </div>
    </div>

    <script>
        // New Referral System Functions - Fixed scope
        window.openInvitePopup = function() {
            document.getElementById('invitePopup').style.display = 'block';
        }

        window.openCodePopup = function() {
            document.getElementById('codePopup').style.display = 'block';
        }

        function closePopup(popupId) {
            document.getElementById(popupId).style.display = 'none';
            // Clear inputs and messages
            const popup = document.getElementById(popupId);
            const inputs = popup.querySelectorAll('.popup-input');
            const messages = popup.querySelectorAll('.success-message, .error-message');
            inputs.forEach(input => input.value = '');
            messages.forEach(msg => msg.remove());
        }

        function generateCode() {
            const randomNum = Math.floor(Math.random() * 99999) + 10000;
            return 'wepay-you' + randomNum;
        }

        function sendInvitation() {
            const email = document.getElementById('inviteEmail').value.trim();
            
            if (!email) {
                showMessage('invitePopup', 'Please enter an email address', 'error');
                return;
            }

            if (!isValidEmail(email)) {
                showMessage('invitePopup', 'Please enter a valid email address', 'error');
                return;
            }

            // Send to real PHP backend
            const formData = new FormData();
            formData.append('action', 'send_invitation');
            formData.append('email', email);

            fetch('referral-backend.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessageWithCopy('invitePopup', data.message, data.code, 'success');
                    document.getElementById('inviteEmail').value = '';
                    // Refresh page immediately after 2 seconds to show updated data
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showMessage('invitePopup', data.message, 'error');
                }
            })
            .catch(error => {
                showMessage('invitePopup', 'Error: Could not send invitation', 'error');
            });
        }

        function enterReferralCode() {
            const code = document.getElementById('referralCode').value.trim();
            
            if (!code) {
                showMessage('codePopup', 'Please enter a referral code', 'error');
                return;
            }

            if (!code.startsWith('wepay-you')) {
                showMessage('codePopup', '❌ Invalid code format. Please ask inviter for a valid code.', 'error');
                return;
            }

            // Send to real PHP backend
            const formData = new FormData();
            formData.append('action', 'enter_code');
            formData.append('code', code);

            fetch('referral-backend.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('codePopup', data.message, 'success');
                    document.getElementById('referralCode').value = '';
                    // Refresh page after 3 seconds to show updated balance
                    setTimeout(() => location.reload(), 3000);
                } else {
                    showMessage('codePopup', data.message, 'error');
                }
            })
            .catch(error => {
                showMessage('codePopup', 'Error: Could not validate code', 'error');
            });
        }

        function showMessage(popupId, message, type) {
            // Remove existing messages
            const popup = document.getElementById(popupId);
            const existingMessages = popup.querySelectorAll('.success-message, .error-message');
            existingMessages.forEach(msg => msg.remove());

            // Create new message
            const messageDiv = document.createElement('div');
            messageDiv.className = type === 'success' ? 'success-message' : 'error-message';
            messageDiv.innerHTML = message;

            // Add message after the input
            const input = popup.querySelector('.popup-input');
            input.parentNode.insertBefore(messageDiv, input.nextSibling);
        }

        function showMessageWithCopy(popupId, message, code, type) {
            // Remove existing messages
            const popup = document.getElementById(popupId);
            const existingMessages = popup.querySelectorAll('.success-message, .error-message');
            existingMessages.forEach(msg => msg.remove());

            // Create new message with copy button
            const messageDiv = document.createElement('div');
            messageDiv.className = type === 'success' ? 'success-message' : 'error-message';
            messageDiv.innerHTML = `
                ${message}
                <div style="margin-top: 15px; text-align: center;">
                    <button onclick="copyCode('${code}')" 
                            style="background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                        📋 Copy Code
                    </button>
                </div>
            `;

            // Add message after the input
            const input = popup.querySelector('.popup-input');
            input.parentNode.insertBefore(messageDiv, input.nextSibling);
        }

        function copyCode(code) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    alert('✅ Code copied: ' + code);
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✅ Code copied: ' + code);
            }
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function claimReward(code) {
            const formData = new FormData();
            formData.append('action', 'claim_reward');
            formData.append('code', code);

            fetch('referral-backend.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload(); // Refresh to show updated data
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: Could not claim reward');
            });
        }

        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));

            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Close popup when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('popup-overlay')) {
                event.target.style.display = 'none';
            }
        });
    </script>
</body>
</html>