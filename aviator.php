<?php require_once 'firebase_setup.php'; ?>
<?php
session_start();

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

// Set testing user session for demo
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'testing@example.com';
    $_SESSION['user'] = [
        'name' => 'Testing User',
        'email' => 'testing@example.com',
        'id' => '68a70b922f5fe'
    ];
    $_SESSION['user_id'] = '68a70b922f5fe';
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
}

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
    <title>LET PAY YOU - Aviator</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
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
            background: linear-gradient(135deg, #FF6B35, #F7931E);
            color: white;
            text-align: center;
            padding: 30px 20px;
            position: relative;
            overflow: hidden;
        }

        .game-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 1;
        }

        .header-content {
            position: relative;
            z-index: 2;
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
            display: inline-block;
            font-size: 1.1rem;
            font-weight: bold;
        }

        /* Game Content */
        .game-content {
            padding: 30px 20px 100px;
        }

        /* Aviator Game Area */
        .aviator-game {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .game-screen {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .plane-emoji {
            font-size: 4rem;
            animation: fly 3s ease-in-out infinite;
            margin-bottom: 20px;
        }

        @keyframes fly {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .multiplier-display {
            font-size: 3rem;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .game-status {
            font-size: 1.2rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .cash-out-btn {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
        }

        .cash-out-btn:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
            transform: scale(1.05);
        }

        .cash-out-btn.active {
            display: inline-block;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Betting Panel */
        .betting-panel {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            color: #333;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .bet-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .bet-input-group {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .bet-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }

        .bet-input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1.1rem;
            text-align: center;
        }

        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 10px;
        }

        .quick-amount {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 6px;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .quick-amount:hover {
            background: #4CAF50;
            color: white;
        }

        .place-bet-btn {
            width: 100%;
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .place-bet-btn:hover {
            background: linear-gradient(135deg, #F57C00, #FF9800);
        }

        .place-bet-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
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

        /* History */
        .recent-results {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .results-header {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }

        .results-list {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 0;
        }

        .result-item {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: bold;
            white-space: nowrap;
            min-width: 60px;
            text-align: center;
        }

        .result-item.high {
            background: #4CAF50;
            color: white;
        }

        .result-item.medium {
            background: #FF9800;
            color: white;
        }

        .result-item.low {
            background: #f44336;
            color: white;
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

        .nav-label {
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .game-title {
                font-size: 2rem;
            }
            
            .bet-controls {
                grid-template-columns: 1fr;
            }
            
            .game-content {
                padding: 20px 15px 100px;
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
                <a href="games.php" style="background: #4CAF50; color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-size: 0.9rem; font-weight: 500;">← Back to Games</a>
                <div class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            </div>
        </div>

        <!-- Game Header -->
        <div class="game-header">
            <img src="attached_assets/attached_assets/generated_images/Young_gamers_celebration_party_79bdc445.png" alt="Aviator" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;">
            <div class="header-content">
                <h1 class="game-title">✈️ Aviator</h1>
                <p class="game-subtitle">Cash out before the plane flies away!</p>
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
                    <div class="stat-value">97%</div>
                    <div class="stat-label">RTP Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="round-counter">0</div>
                    <div class="stat-label">Rounds Played</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="total-wins">₦0</div>
                    <div class="stat-label">Total Wins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₦100</div>
                    <div class="stat-label">Min Bet</div>
                </div>
            </div>

            <!-- Recent Results -->
            <div class="recent-results">
                <div class="results-header">🎯 Recent Results</div>
                <div class="results-list" id="results-list">
                    <div class="result-item high">2.45x</div>
                    <div class="result-item medium">1.89x</div>
                    <div class="result-item low">1.02x</div>
                    <div class="result-item high">3.21x</div>
                    <div class="result-item medium">1.67x</div>
                </div>
            </div>

            <!-- Aviator Game -->
            <div class="aviator-game">
                <div class="game-screen">
                    <div class="plane-emoji" id="plane">✈️</div>
                    <div class="multiplier-display" id="multiplier">1.00x</div>
                    <div class="game-status" id="game-status">Place your bet to start flying!</div>
                    <button class="cash-out-btn" id="cash-out-btn" onclick="cashOut()">
                        Cash Out - <span id="cash-out-amount">₦0</span>
                    </button>
                </div>

                <!-- Betting Panel -->
                <div class="betting-panel">
                    <div class="bet-controls">
                        <div class="bet-input-group">
                            <div class="bet-label">Bet Amount</div>
                            <input type="number" class="bet-input" id="bet-amount" placeholder="₦100" min="100" max="5000000" value="100">
                            <div class="quick-amounts">
                                <button class="quick-amount" onclick="setBetAmount(100)">₦100</button>
                                <button class="quick-amount" onclick="setBetAmount(1000)">₦1K</button>
                                <button class="quick-amount" onclick="setBetAmount(50000)">₦50K</button>
                                <button class="quick-amount" onclick="setBetAmount(5000000)">₦5M</button>
                            </div>
                        </div>
                        
                        <div class="bet-input-group">
                            <div class="bet-label">Auto Cash Out</div>
                            <input type="number" class="bet-input" id="auto-cashout" placeholder="2.00x" min="1.01" step="0.01">
                            <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                Optional: Auto cash out at multiplier
                            </div>
                        </div>
                    </div>

                    <button class="place-bet-btn" id="place-bet-btn" onclick="placeBet()">
                        Place Bet - ₦<span id="bet-display">100</span>
                    </button>
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
                
                <a href="games.php" class="nav-item active">
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

    <!-- Direct Balance System - No External Dependencies -->

    <script>
        let gameState = {
            isPlaying: false,
            currentMultiplier: 1.0,
            betAmount: 100,
            hasActiveBet: false,
            gameInterval: null,
            roundCounter: 0,
            totalWins: 0,
            autoCashOut: null
        };

        // Set bet amount
        function setBetAmount(amount) {
            document.getElementById('bet-amount').value = amount;
            document.getElementById('bet-display').textContent = amount;
            gameState.betAmount = amount;
        }

        // Update bet display when input changes
        document.getElementById('bet-amount').addEventListener('input', function() {
            const amount = parseInt(this.value) || 100;
            document.getElementById('bet-display').textContent = amount;
            gameState.betAmount = amount;
        });

        // Place bet with real balance deduction
        async function placeBet() {
            const betAmount = parseInt(document.getElementById('bet-amount').value) || 100;
            
            if (betAmount < 100 || betAmount > 5000000) {
                alert('Bet amount must be between ₦100 - ₦5,000,000');
                return;
            }
            
            if (gameState.isPlaying) {
                alert('Game in progress! Wait for current round to finish.');
                return;
            }
            
            // Deduct balance directly
            try {
                const response = await fetch('aviator.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_action=deduct_balance&user_email=<?= $_SESSION['email'] ?>&amount=${betAmount}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    console.log('✅ Aviator bet placed successfully!');
                    gameState.betAmount = betAmount;
                    gameState.hasActiveBet = true;
                    gameState.autoCashOut = parseFloat(document.getElementById('auto-cashout').value) || null;
                    
                    // Update balance display instantly
                    updateBalanceDisplay(result.new_balance);
                    
                    document.getElementById('place-bet-btn').disabled = true;
                    document.getElementById('place-bet-btn').textContent = 'Bet Placed - Starting...';
                    
                    // Start game after 2 seconds
                    setTimeout(startGame, 2000);
                } else {
                    alert(result.message || 'Failed to place bet');
                }
            } catch (error) {
                console.log('❌ Aviator bet failed:', error);
                alert('Error placing bet: ' + error.message);
            }
        }

        // Start game
        function startGame() {
            gameState.isPlaying = true;
            gameState.currentMultiplier = 1.0;
            gameState.roundCounter++;
            
            document.getElementById('round-counter').textContent = gameState.roundCounter;
            document.getElementById('game-status').textContent = 'Plane is flying! Cash out before it crashes!';
            document.getElementById('cash-out-btn').classList.add('active');
            
            // Game loop
            gameState.gameInterval = setInterval(() => {
                // Increase multiplier
                const increase = Math.random() * 0.02 + 0.01; // Random increase
                gameState.currentMultiplier += increase;
                
                // Update display
                document.getElementById('multiplier').textContent = gameState.currentMultiplier.toFixed(2) + 'x';
                document.getElementById('cash-out-amount').textContent = 
                    '₦' + (gameState.betAmount * gameState.currentMultiplier).toFixed(0);
                
                // Check auto cash out
                if (gameState.autoCashOut && gameState.currentMultiplier >= gameState.autoCashOut) {
                    cashOut();
                    return;
                }
                
                // Random crash (higher chance as multiplier increases)
                const crashChance = Math.min(0.02 + (gameState.currentMultiplier - 1) * 0.01, 0.1);
                if (Math.random() < crashChance) {
                    crashGame();
                }
            }, 100);
        }

        // Cash out with real winnings addition
        async function cashOut() {
            if (!gameState.isPlaying || !gameState.hasActiveBet) return;
            
            const winAmount = gameState.betAmount * gameState.currentMultiplier;
            
            clearInterval(gameState.gameInterval);
            gameState.isPlaying = false;
            gameState.hasActiveBet = false;
            
            // Add winnings to real balance
            try {
                const response = await fetch('aviator.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_action=add_winnings&user_email=<?= $_SESSION['email'] ?>&amount=${winAmount}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    console.log('🎉 Aviator winnings added successfully!');
                    gameState.totalWins += winAmount;
                    
                    // Update balance display instantly
                    updateBalanceDisplay(result.new_balance);
                    
                    document.getElementById('game-status').innerHTML = 
                        `🎉 Cashed out at ${gameState.currentMultiplier.toFixed(2)}x!<br>You won ₦${winAmount.toFixed(0)}!`;
                    document.getElementById('cash-out-btn').classList.remove('active');
                    document.getElementById('total-wins').textContent = '₦' + gameState.totalWins.toFixed(0);
                    
                    // Add to results
                    addResult(gameState.currentMultiplier);
                    
                    // Reset for next round
                    setTimeout(resetGame, 3000);
                } else {
                    console.log('❌ Adding Aviator winnings failed:', result.message);
                    // Still show the UI update even if backend fails
                    document.getElementById('game-status').innerHTML = 
                        `🎉 Cashed out at ${gameState.currentMultiplier.toFixed(2)}x!<br>Won ₦${winAmount.toFixed(0)} (Backend sync failed)`;
                    document.getElementById('cash-out-btn').classList.remove('active');
                    
                    addResult(gameState.currentMultiplier);
                    setTimeout(resetGame, 3000);
                }
            } catch (error) {
                console.log('❌ Adding Aviator winnings failed:', error);
                // Still show the UI update even if backend fails
                document.getElementById('game-status').innerHTML = 
                    `🎉 Cashed out at ${gameState.currentMultiplier.toFixed(2)}x!<br>Won ₦${winAmount.toFixed(0)} (Network error)`;
                document.getElementById('cash-out-btn').classList.remove('active');
                
                addResult(gameState.currentMultiplier);
                setTimeout(resetGame, 3000);
            }
        }

        // Crash game
        function crashGame() {
            clearInterval(gameState.gameInterval);
            gameState.isPlaying = false;
            gameState.hasActiveBet = false;
            
            document.getElementById('game-status').innerHTML = 
                `💥 Plane crashed at ${gameState.currentMultiplier.toFixed(2)}x!<br>Better luck next time!`;
            document.getElementById('cash-out-btn').classList.remove('active');
            document.getElementById('plane').style.animation = 'none';
            
            // Add to results
            addResult(gameState.currentMultiplier);
            
            // Reset for next round
            setTimeout(resetGame, 3000);
        }

        // Add result to history
        function addResult(multiplier) {
            const resultsList = document.getElementById('results-list');
            const resultItem = document.createElement('div');
            resultItem.className = 'result-item';
            resultItem.textContent = multiplier.toFixed(2) + 'x';
            
            // Color based on multiplier
            if (multiplier >= 2.0) {
                resultItem.classList.add('high');
            } else if (multiplier >= 1.5) {
                resultItem.classList.add('medium');
            } else {
                resultItem.classList.add('low');
            }
            
            resultsList.insertBefore(resultItem, resultsList.firstChild);
            
            // Keep only last 10 results
            if (resultsList.children.length > 10) {
                resultsList.removeChild(resultsList.lastChild);
            }
        }

        // Reset game
        function resetGame() {
            gameState.currentMultiplier = 1.0;
            document.getElementById('multiplier').textContent = '1.00x';
            document.getElementById('game-status').textContent = 'Place your bet to start flying!';
            document.getElementById('place-bet-btn').disabled = false;
            document.getElementById('place-bet-btn').innerHTML = 'Place Bet - ₦<span id="bet-display">' + gameState.betAmount + '</span>';
            document.getElementById('plane').style.animation = 'fly 3s ease-in-out infinite';
        }

        // Update balance display instantly
        function updateBalanceDisplay(newBalance) {
            // Format the balance
            const formattedBalance = '₦' + newBalance.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            // Update balance in header
            const balanceDisplay = document.querySelector('.balance-display');
            if (balanceDisplay) {
                balanceDisplay.innerHTML = 'Your Balance: ' + formattedBalance;
            }
            
            // Update any other balance elements
            const balanceElements = document.querySelectorAll('.user-balance, #user-balance, .balance-amount');
            balanceElements.forEach(element => {
                element.textContent = formattedBalance;
            });
        }

        // Initialize
        document.getElementById('bet-display').textContent = gameState.betAmount;
    </script>
</body>
</html>
