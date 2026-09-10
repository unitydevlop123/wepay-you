<?php require_once 'firebase_setup.php'; ?>
<?php
session_start();

// Include authentication and ban checking
require_once 'auth.php';
require_once 'ban-checker.php';

// Check authentication and ban status
if (isset($_SESSION['email'])) {
    checkBanStatus();
}

// Include game winnings tracker for house edge and daily limits
include_once 'game-winnings-tracker.php';

// Handle AJAX requests for balance updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    handleBalanceUpdate();
    exit;
}

// Balance update function
function handleBalanceUpdate() {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'] ?? '';
    $user_email = $_POST['user_email'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    
    if (empty($user_email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    $users = getUsers();
    if (empty($users)) {
        echo json_encode(['success' => false, 'message' => 'User database not found']);
        return;
    }
    $userIndex = -1;
    
    // Find user
    foreach ($users as $index => $user) {
        if ($user['email'] === $user_email) {
            $userIndex = $index;
            break;
        }
    }
    
    if ($userIndex === -1) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    $currentBalance = floatval($users[$userIndex]['balance'] ?? 0);
    
    if ($action === 'deduct_balance') {
        // Check bet amount limits (₦100 - ₦5,000,000)
        if ($amount < 100 || $amount > 5000000) {
            echo json_encode(['success' => false, 'message' => 'Bet amount must be between ₦100 - ₦5,000,000']);
            return;
        }
        
        // Check daily game winnings limit
        if (hasReachedDailyGameLimit($user_email)) {
            echo json_encode(['success' => false, 'message' => 'Daily game winnings limit of ₦1,000,000 reached. Try again tomorrow!']);
            return;
        }
        
        if ($currentBalance < $amount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
            return;
        }
        
        // Deduct balance
        $newBalance = $currentBalance - $amount;
        $users[$userIndex]['balance'] = $newBalance;
        $users[$userIndex]['updated_at'] = date('Y-m-d H:i:s');
        
        if (saveUsers($users)) {
            echo json_encode([
                'success' => true,
                'message' => 'Bet placed successfully',
                'new_balance' => $newBalance
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update balance']);
        }
        
    } elseif ($action === 'add_winnings') {
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid winnings amount']);
            return;
        }
        
        // Apply house edge (15% house edge = 85% payout)
        $final_payout = applyHouseEdge($amount, 15);
        
        // Check if adding this would exceed daily limit
        $current_daily_winnings = getDailyGameWinnings($user_email);
        if (($current_daily_winnings + $final_payout) > 1000000.00) {
            $final_payout = max(0, 1000000.00 - $current_daily_winnings);
        }
        
        if ($final_payout > 0) {
            // Add winnings with house edge applied
            $newBalance = $currentBalance + $final_payout;
            $users[$userIndex]['balance'] = $newBalance;
            $users[$userIndex]['updated_at'] = date('Y-m-d H:i:s');
            
            // Track daily game winnings
            addGameWinnings($user_email, $final_payout);
            
            if (saveUsers($users)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Winnings added successfully',
                    'new_balance' => $newBalance,
                    'winnings' => $final_payout,
                    'original_amount' => $amount,
                    'house_edge_applied' => true
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add winnings']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Daily limit reached or no winnings to add']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

// Include authentication system
require_once 'auth.php';

// SECURITY: Require login before accessing games
requireLogin();

// Function to format amounts
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Get user information
$userName = 'Elite Member';
if (isset($_SESSION['user']) && isset($_SESSION['user']['name'])) {
    $userName = $_SESSION['user']['name'];
}

// Get user balance from Firebase
$user_balance_main = 0.00;
if (isset($_SESSION['email'])) {
    $users = getUsers();
    if (!empty($users)) {
        foreach ($users as $user) {
            if ($user['email'] === $_SESSION['email']) {
                $user_balance_main = (float)($user['balance'] ?? 0.00);
                break;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET PAY YOU - Spin & Earn Wheel</title>
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

        /* Top Header */
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

        /* Game Header */
        .game-header {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: linear-gradient(135deg, #FF6B35, #F7931E);
        }

        .game-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .header-content {
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
            padding: 20px;
        }

        .game-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .game-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .balance-display {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: bold;
        }

        /* Game Content */
        .game-content {
            padding: 30px 20px 100px;
        }

        /* Game Stats */
        .game-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        /* Wheel Game Area */
        .wheel-game {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            text-align: center;
        }

        .wheel-container {
            position: relative;
            width: 300px;
            height: 300px;
            margin: 20px auto;
            border-radius: 50%;
            background: conic-gradient(
                #FF6B35 0deg 36deg,
                #F7931E 36deg 72deg,
                #4CAF50 72deg 108deg,
                #2196F3 108deg 144deg,
                #9C27B0 144deg 180deg,
                #FF5722 180deg 216deg,
                #FFC107 216deg 252deg,
                #E91E63 252deg 288deg,
                #00BCD4 288deg 324deg,
                #8BC34A 324deg 360deg
            );
            border: 8px solid white;
            box-shadow: 0 0 30px rgba(0,0,0,0.3);
            transition: transform 2s ease-out;
        }

        .wheel-pointer {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-top: 30px solid #333;
            z-index: 10;
        }

        .wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #333;
            border: 4px solid #333;
        }

        /* Game Controls */
        .game-controls {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            color: #333;
            margin-bottom: 20px;
        }

        .spin-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .spin-option {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .spin-option:hover {
            border-color: #4CAF50;
            transform: translateY(-2px);
        }

        .spin-option.free {
            border-color: #4CAF50;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .spin-type {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .spin-cost {
            font-size: 1rem;
            color: #666;
            margin-bottom: 10px;
        }

        .spin-option.free .spin-cost {
            color: rgba(255,255,255,0.9);
        }

        .spin-btn {
            width: 100%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .spin-btn:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
            transform: translateY(-2px);
        }

        .spin-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .spin-btn.premium {
            background: linear-gradient(135deg, #FF9800, #F57C00);
        }

        .spin-btn.premium:hover {
            background: linear-gradient(135deg, #F57C00, #FF9800);
        }

        /* Results */
        .spin-result {
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-top: 20px;
            display: none;
        }
        
        .spin-result.win {
            background: rgba(76, 175, 80, 0.1);
            border: 2px solid #4CAF50;
        }
        
        .spin-result.lose {
            background: rgba(244, 67, 54, 0.1);
            border: 2px solid #f44336;
        }
        
        .bet-section {
            margin-bottom: 20px;
        }
        
        .control-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .bet-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .bet-input:focus {
            border-color: #4CAF50;
            outline: none;
        }
        
        .quick-bets {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .quick-bet {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            color: #495057;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .quick-bet:hover {
            border-color: #4CAF50;
            background: #4CAF50;
            color: white;
        }

        .result-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .result-message {
            font-size: 1.1rem;
            color: #333;
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

        /* Responsive */
        @media (max-width: 768px) {
            .game-title {
                font-size: 2rem;
            }
            
            .game-content {
                padding: 20px 15px 100px;
            }
            
            .wheel-container {
                width: 250px;
                height: 250px;
            }
            
            .spin-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Top Header -->
        <div class="top-header">
            <div class="logo-section">
                <img src="logo.svg" alt="LET PAY YOU">
            </div>
            <div class="user-info">
                <a href="games.php" style="background: #4CAF50; color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-size: 0.9rem; font-weight: 500;">← Back to Games</a>
                <div class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            </div>
        </div>

        <!-- Game Header -->
        <div class="game-header">
            <img src="attached_assets/attached_assets/generated_images/Young_woman_money_celebration_5b11c4af.png" alt="Spin & Earn Wheel">
            <div class="header-content">
                <h1 class="game-title">🎡 Spin & Earn Wheel</h1>
                <p class="game-subtitle">Spin our branded wheel for instant cash prizes and bonuses!</p>
                <div class="balance-display">
                    Your Balance: <?= formatAmount($user_balance_main) ?>
                </div>
            </div>
        </div>

        <!-- Game Content -->
        <div class="game-content">
            <!-- Game Stats -->
            <div class="game-stats">
                <div class="stat-card">
                    <div class="stat-value">₦100</div>
                    <div class="stat-label">Min Bet</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₦5M</div>
                    <div class="stat-label">Max Bet</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₦1500</div>
                    <div class="stat-label">Max Prize</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">35%</div>
                    <div class="stat-label">Lose Rate</div>
                </div>
            </div>

            <!-- Wheel Game -->
            <div class="wheel-game">
                <h2 style="margin-bottom: 20px;">🎯 Spin to Win Big!</h2>
                
                <div class="wheel-container" id="wheel">
                    <div class="wheel-pointer"></div>
                    <div class="wheel-center">SPIN</div>
                </div>

                <p style="margin-top: 20px; opacity: 0.9;">
                    Spin our exclusive LET PAY YOU wheel to win instant cash prizes!
                </p>
            </div>

            <!-- Game Controls -->
            <div class="game-controls">
                <h3 style="margin-bottom: 20px; text-align: center;">Place Your Bet & Spin</h3>
                
                <div class="bet-section">
                    <div class="control-label">Enter Your Bet Amount</div>
                    <input type="number" class="bet-input" id="betAmount" placeholder="Min ₦100 - Max ₦5,000,000" min="100" max="5000000" value="100">
                    
                    <div class="quick-bets">
                        <button class="quick-bet" onclick="setBet(100)">₦100</button>
                        <button class="quick-bet" onclick="setBet(1000)">₦1K</button>
                        <button class="quick-bet" onclick="setBet(10000)">₦10K</button>
                        <button class="quick-bet" onclick="setBet(100000)">₦100K</button>
                        <button class="quick-bet" onclick="setBet(5000000)">₦5M</button>
                    </div>
                </div>

                <button class="spin-btn" id="spinButton" onclick="spinWheel()">
                    🎡 SPIN TO WIN (Bet: ₦<span id="currentBet">50</span>)
                </button>

                <div class="spin-result" id="result">
                    <div class="result-amount" id="resultAmount">₦0</div>
                    <div class="result-message" id="resultMessage">Congratulations!</div>
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
                
                <a href="games.php" class="nav-item active">
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
            </div>
        </nav>
    </div>

    <script>
        let betAmount = 50;
        let isSpinning = false;

        function setBet(amount) {
            betAmount = amount;
            document.getElementById('betAmount').value = amount;
            document.getElementById('currentBet').textContent = amount;
        }

        async function spinWheel() {
            if (isSpinning) return;
            
            const betInput = document.getElementById('betAmount').value;
            betAmount = parseInt(betInput) || 50;
            
            if (betAmount < 100) {
                alert('Minimum bet is ₦100');
                return;
            }
            
            if (betAmount > 5000000) {
                alert('Maximum bet is ₦5,000,000');
                return;
            }

            // Deduct balance first
            try {
                const response = await fetch('spin-wheel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_action=deduct_balance&user_email=<?= $_SESSION['email'] ?>&amount=${betAmount}`
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    alert(result.message || 'Failed to place bet');
                    return;
                }

                // Update balance display instantly
                updateBalanceDisplay(result.new_balance);
                
            } catch (error) {
                console.log('❌ Spin wheel bet failed:', error);
                alert('Error placing bet: ' + error.message);
                return;
            }
            
            isSpinning = true;
            const wheel = document.getElementById('wheel');
            const button = document.getElementById('spinButton');
            const result = document.getElementById('result');
            
            // Disable button
            button.disabled = true;
            button.textContent = 'SPINNING...';
            
            // Hide previous result
            result.style.display = 'none';
            
            // Generate random rotation (multiple full spins + random position)
            const spins = 5 + Math.random() * 5; // 5-10 full spins
            const finalRotation = spins * 360;
            
            // Apply rotation
            wheel.style.transform = `rotate(${finalRotation}deg)`;
            
            // Calculate result after spin (using JSON probabilities)
            setTimeout(async () => {
                const random = Math.random() * 100;
                let wonAmount = 0;
                let resultMessage = '';
                
                if (random <= 35) {
                    // 35% chance to lose
                    wonAmount = 0;
                    resultMessage = 'Better luck next time! You lost your bet.';
                } else if (random <= 65) {
                    // 30% chance small reward
                    wonAmount = Math.round(betAmount * 1.5);
                    resultMessage = `Nice! You won ₦${wonAmount}!`;
                } else if (random <= 85) {
                    // 20% chance medium reward
                    wonAmount = Math.round(betAmount * 3);
                    resultMessage = `Great! You won ₦${wonAmount}!`;
                } else if (random <= 95) {
                    // 10% chance large reward
                    wonAmount = Math.round(betAmount * 6);
                    resultMessage = `Excellent! You won ₦${wonAmount}!`;
                } else if (random <= 99) {
                    // 4% chance bonus
                    wonAmount = Math.round(betAmount * 10);
                    resultMessage = `BONUS WIN! You won ₦${wonAmount}!`;
                } else {
                    // 1% chance jackpot
                    wonAmount = 1500;
                    resultMessage = `JACKPOT! You won ₦${wonAmount}!`;
                }

                // Add winnings if player won
                if (wonAmount > 0) {
                    try {
                        const response = await fetch('spin-wheel.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `ajax_action=add_winnings&user_email=<?= $_SESSION['email'] ?>&amount=${wonAmount}`
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Update balance display instantly
                            updateBalanceDisplay(result.new_balance);
                        }
                    } catch (error) {
                        console.log('❌ Adding spin wheel winnings failed:', error);
                    }
                }
                
                // Show result
                document.getElementById('resultAmount').textContent = `₦${wonAmount}`;
                document.getElementById('resultMessage').textContent = resultMessage;
                
                result.style.display = 'block';
                result.className = wonAmount > 0 ? 'spin-result win' : 'spin-result lose';
                
                // Reset button
                isSpinning = false;
                button.disabled = false;
                button.textContent = `🎡 SPIN TO WIN (Bet: ₦${betAmount})`;
                
                // Show alert
                setTimeout(() => {
                    if (wonAmount > 0) {
                        alert(`Congratulations! ₦${wonAmount} has been added to your balance!`);
                    } else {
                        alert(`You lost ₦${betAmount}. Try again!`);
                    }
                }, 500);
                
            }, 2000);
        }

        // Update balance display instantly
        function updateBalanceDisplay(newBalance) {
            const formattedBalance = '₦' + newBalance.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            // Update balance in header
            const balanceDisplay = document.querySelector('.balance-display');
            if (balanceDisplay) {
                balanceDisplay.innerHTML = 'Your Balance: ' + formattedBalance;
            }
        }

        // Initialize
        setBet(100);
        
        document.getElementById('betAmount').addEventListener('input', function() {
            const value = parseInt(this.value) || 50;
            setBet(value);
        });
    </script>
</body>
</html>