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
    <title>LET PAY YOU - Lucky Dice</title>
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

        /* Dice Game Area */
        .dice-game {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
        }

        /* Game Controls */
        .game-controls {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            color: #333;
            margin-bottom: 20px;
        }

        .controls-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .control-group {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .control-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }

        .control-input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1.1rem;
            text-align: center;
        }

        .bet-type-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 10px;
        }

        .bet-type-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .bet-type-btn.active {
            background: #4CAF50;
            color: white;
        }

        .number-selector {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-top: 10px;
        }

        .number-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .number-btn.active {
            background: #FF9800;
            color: white;
        }

        .roll-dice-btn {
            width: 100%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .roll-dice-btn:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
        }

        .roll-dice-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Dice Display */
        .dice-display {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            margin-bottom: 20px;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .dice-cube {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: all 0.5s ease;
        }

        .dice-cube.rolling {
            animation: roll 1s ease-in-out;
        }

        @keyframes roll {
            0% { transform: rotateX(0) rotateY(0); }
            25% { transform: rotateX(90deg) rotateY(0); }
            50% { transform: rotateX(180deg) rotateY(90deg); }
            75% { transform: rotateX(270deg) rotateY(180deg); }
            100% { transform: rotateX(360deg) rotateY(270deg); }
        }

        .dice-result {
            font-size: 1.5rem;
            font-weight: bold;
            color: #FFD700;
            margin-bottom: 10px;
        }

        .game-message {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Payout Info */
        .payout-info {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .payout-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .payout-item {
            text-align: center;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }

        .payout-type {
            font-size: 0.9rem;
            margin-bottom: 5px;
            opacity: 0.8;
        }

        .payout-odds {
            font-size: 1.2rem;
            font-weight: bold;
            color: #FFD700;
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
            
            .controls-row {
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
            <img src="attached_assets/attached_assets/generated_images/Happy_family_with_SUV_801a6fa1.png" alt="Dice" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;">
            <div class="header-content">
                <h1 class="game-title">🎲 Lucky Dice</h1>
                <p class="game-subtitle">Roll high, roll low, or hit the exact number!</p>
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
                    <div class="stat-value">98%</div>
                    <div class="stat-label">RTP Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="rolls-count">0</div>
                    <div class="stat-label">Dice Rolled</div>
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

            <!-- Dice Game -->
            <div class="dice-game">
                <!-- Game Controls -->
                <div class="game-controls">
                    <div class="controls-row">
                        <div class="control-group">
                            <div class="control-label">Bet Amount</div>
                            <input type="number" class="control-input" id="bet-amount" placeholder="₦100" min="100" max="5000000" value="100">
                        </div>
                        
                        <div class="control-group">
                            <div class="control-label">Bet Type</div>
                            <input type="text" class="control-input" id="bet-type" value="High (4-6)" readonly>
                            <div class="bet-type-selector">
                                <button class="bet-type-btn active" onclick="setBetType('high', this)">High</button>
                                <button class="bet-type-btn" onclick="setBetType('low', this)">Low</button>
                                <button class="bet-type-btn" onclick="setBetType('exact', this)">Exact</button>
                            </div>
                        </div>
                    </div>

                    <div class="control-group" id="number-selection" style="display: none;">
                        <div class="control-label">Choose Number (Exact Bet)</div>
                        <div class="number-selector">
                            <button class="number-btn" onclick="setNumber(1, this)">1</button>
                            <button class="number-btn" onclick="setNumber(2, this)">2</button>
                            <button class="number-btn" onclick="setNumber(3, this)">3</button>
                            <button class="number-btn" onclick="setNumber(4, this)">4</button>
                            <button class="number-btn" onclick="setNumber(5, this)">5</button>
                            <button class="number-btn" onclick="setNumber(6, this)">6</button>
                        </div>
                    </div>

                    <button class="roll-dice-btn" id="roll-dice-btn" onclick="rollDice()">
                        Roll Dice - ₦<span id="bet-display">20</span>
                    </button>
                </div>

                <!-- Payout Info -->
                <div class="payout-info">
                    <div class="payout-grid">
                        <div class="payout-item">
                            <div class="payout-type">High (4-6)</div>
                            <div class="payout-odds">2.0x</div>
                        </div>
                        <div class="payout-item">
                            <div class="payout-type">Low (1-3)</div>
                            <div class="payout-odds">2.0x</div>
                        </div>
                        <div class="payout-item">
                            <div class="payout-type">Exact Number</div>
                            <div class="payout-odds">6.0x</div>
                        </div>
                    </div>
                </div>

                <!-- Dice Display -->
                <div class="dice-display">
                    <div class="dice-cube" id="dice-cube">🎲</div>
                    <div class="dice-result" id="dice-result">Ready to roll!</div>
                    <div class="game-message" id="game-message">Choose your bet type and amount, then roll the dice!</div>
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

    <script>
        let gameState = {
            betAmount: 100,
            betType: 'high',
            selectedNumber: null,
            rollsCount: 0,
            totalWins: 0,
            isRolling: false
        };

        const diceNumbers = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];

        // Set bet type
        function setBetType(type, clickedElement) {
            gameState.betType = type;
            
            // Update active button
            document.querySelectorAll('.bet-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Find the clicked button if not passed
            if (!clickedElement) {
                clickedElement = document.querySelector(`.bet-type-btn[onclick*="${type}"]`);
            }
            if (clickedElement) {
                clickedElement.classList.add('active');
            }
            
            // Update display and show/hide number selection
            const numberSelection = document.getElementById('number-selection');
            const betTypeInput = document.getElementById('bet-type');
            
            switch(type) {
                case 'high':
                    betTypeInput.value = 'High (4-6)';
                    numberSelection.style.display = 'none';
                    break;
                case 'low':
                    betTypeInput.value = 'Low (1-3)';
                    numberSelection.style.display = 'none';
                    break;
                case 'exact':
                    betTypeInput.value = 'Exact Number';
                    numberSelection.style.display = 'block';
                    break;
            }
        }

        // Set exact number
        function setNumber(number, clickedElement) {
            gameState.selectedNumber = number;
            
            // Update active button
            document.querySelectorAll('.number-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Find the clicked button if not passed
            if (!clickedElement) {
                clickedElement = document.querySelector(`.number-btn[onclick*="${number}"]`);
            }
            if (clickedElement) {
                clickedElement.classList.add('active');
            }
            
            document.getElementById('bet-type').value = `Exact Number (${number})`;
        }

        // Update bet display when input changes
        document.getElementById('bet-amount').addEventListener('input', function() {
            const amount = parseInt(this.value) || 20;
            document.getElementById('bet-display').textContent = amount;
            gameState.betAmount = amount;
        });

        // Roll dice with balance deduction
        async function rollDice() {
            const betAmount = parseInt(document.getElementById('bet-amount').value) || 20;
            
            if (betAmount < 100 || betAmount > 5000000) {
                alert('Bet amount must be between ₦100 - ₦5,000,000');
                return;
            }
            
            if (gameState.betType === 'exact' && !gameState.selectedNumber) {
                alert('Please select a number for exact bet');
                return;
            }
            
            if (gameState.isRolling) {
                return;
            }

            // Deduct balance first
            try {
                const response = await fetch('dice.php', {
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
                console.log('❌ Dice bet failed:', error);
                alert('Error placing bet: ' + error.message);
                return;
            }
            
            gameState.isRolling = true;
            gameState.betAmount = betAmount;
            gameState.rollsCount++;
            
            document.getElementById('roll-dice-btn').disabled = true;
            document.getElementById('roll-dice-btn').textContent = 'Rolling...';
            document.getElementById('rolls-count').textContent = gameState.rollsCount;
            document.getElementById('game-message').textContent = 'Rolling the dice...';
            
            // Start rolling animation
            const diceCube = document.getElementById('dice-cube');
            diceCube.classList.add('rolling');
            
            // Show random dice faces during roll
            let rollCount = 0;
            const rollInterval = setInterval(() => {
                const randomFace = diceNumbers[Math.floor(Math.random() * 6)];
                diceCube.textContent = randomFace;
                rollCount++;
                
                if (rollCount >= 10) {
                    clearInterval(rollInterval);
                    finishRoll();
                }
            }, 100);
        }

        // Finish roll and determine result
        async function finishRoll() {
            const diceResult = Math.floor(Math.random() * 6) + 1;
            const diceCube = document.getElementById('dice-cube');
            
            diceCube.classList.remove('rolling');
            diceCube.textContent = diceNumbers[diceResult - 1];
            
            // Determine if win
            let isWin = false;
            let multiplier = 0;
            
            switch(gameState.betType) {
                case 'high':
                    isWin = diceResult >= 4;
                    multiplier = 2.0;
                    break;
                case 'low':
                    isWin = diceResult <= 3;
                    multiplier = 2.0;
                    break;
                case 'exact':
                    isWin = diceResult === gameState.selectedNumber;
                    multiplier = 6.0;
                    break;
            }
            
            // Calculate winnings
            const winAmount = isWin ? gameState.betAmount * multiplier : 0;
            
            if (isWin) {
                // Add winnings to balance
                try {
                    const response = await fetch('dice.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax_action=add_winnings&user_email=<?= $_SESSION['email'] ?>&amount=${winAmount}`
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Update balance display instantly
                        updateBalanceDisplay(result.new_balance);
                        gameState.totalWins += winAmount;
                    }
                } catch (error) {
                    console.log('❌ Adding Dice winnings failed:', error);
                }
            }
            
            // Update display
            const resultText = `Rolled: ${diceResult}`;
            const messageText = isWin ? 
                `🎉 You won ₦${winAmount.toFixed(0)}! (${multiplier}x)` : 
                `💔 You lost! Better luck next roll!`;
            
            document.getElementById('dice-result').textContent = resultText;
            document.getElementById('game-message').textContent = messageText;
            document.getElementById('total-wins').textContent = '₦' + gameState.totalWins.toFixed(0);
            
            // Highlight result
            if (isWin) {
                diceCube.style.background = '#4CAF50';
                diceCube.style.color = 'white';
                setTimeout(() => {
                    diceCube.style.background = 'white';
                    diceCube.style.color = '#333';
                }, 2000);
            }
            
            // Reset for next roll
            setTimeout(() => {
                gameState.isRolling = false;
                document.getElementById('roll-dice-btn').disabled = false;
                document.getElementById('roll-dice-btn').innerHTML = 'Roll Dice - ₦<span id="bet-display">' + gameState.betAmount + '</span>';
                document.getElementById('dice-result').textContent = 'Ready to roll!';
                document.getElementById('game-message').textContent = 'Choose your bet and roll again!';
            }, 3000);
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

        // Update bet display when input changes
        document.getElementById('bet-amount').addEventListener('input', function() {
            const amount = parseInt(this.value) || 100;
            document.getElementById('bet-display').textContent = amount;
            gameState.betAmount = amount;
        });

        // Initialize
        document.getElementById('bet-display').textContent = gameState.betAmount;
    </script>
</body>
</html>
