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
    <title>LET PAY YOU - Treasure Hunt</title>
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

        /* Treasure Hunt Game Area */
        .treasure-game {
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

        .bet-amount-section {
            margin-bottom: 20px;
        }

        .control-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }

        .bet-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 10px;
        }

        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }

        .quick-amount {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 8px;
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

        .start-hunt-btn {
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

        .start-hunt-btn:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
        }

        .start-hunt-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Treasure Boxes */
        .treasure-boxes {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            max-width: 500px;
            margin: 0 auto 20px;
        }

        .treasure-box {
            aspect-ratio: 1;
            background: rgba(255,255,255,0.2);
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .treasure-box:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }

        .treasure-box.opened {
            background: rgba(255,255,255,0.9);
            color: #333;
            cursor: not-allowed;
        }

        .treasure-box.treasure {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
        }

        .treasure-box.bonus {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .treasure-box.empty {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
        }

        .treasure-box.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Game Info */
        .game-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .current-win {
            font-size: 2rem;
            font-weight: bold;
            color: #FFD700;
            margin-bottom: 10px;
        }

        .boxes-opened {
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .collect-btn {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
        }

        .collect-btn:hover {
            background: linear-gradient(135deg, #F57C00, #FF9800);
            transform: scale(1.05);
        }

        .collect-btn.active {
            display: inline-block;
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
            
            .game-content {
                padding: 20px 15px 100px;
            }
            
            .treasure-boxes {
                grid-template-columns: repeat(4, 1fr);
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
            <img src="attached_assets/attached_assets/generated_images/Family_celebrating_game_wins_2a6b2e3f.png" alt="Treasure Hunt" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;">
            <div class="header-content">
                <h1 class="game-title">🏆 Treasure Hunt</h1>
                <p class="game-subtitle">Find hidden treasures in mysterious boxes!</p>
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
                    <div class="stat-value">95%</div>
                    <div class="stat-label">RTP Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="hunts-played">0</div>
                    <div class="stat-label">Hunts Played</div>
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

            <!-- Treasure Hunt Game -->
            <div class="treasure-game">
                <!-- Game Controls -->
                <div class="game-controls">
                    <div class="bet-amount-section">
                        <div class="control-label">Bet Amount</div>
                        <input type="number" class="bet-input" id="bet-amount" placeholder="₦100" min="100" max="5000000" value="100">
                        
                        <div class="quick-amounts">
                            <button class="quick-amount" onclick="setBetAmount(100)">₦100</button>
                            <button class="quick-amount" onclick="setBetAmount(1000)">₦1K</button>
                            <button class="quick-amount" onclick="setBetAmount(50000)">₦50K</button>
                            <button class="quick-amount" onclick="setBetAmount(5000000)">₦5M</button>
                        </div>
                    </div>

                    <button class="start-hunt-btn" id="start-hunt-btn" onclick="startHunt()">
                        Start Treasure Hunt
                    </button>
                </div>

                <!-- Game Info -->
                <div class="game-info">
                    <div class="current-win" id="current-win">₦0</div>
                    <div class="boxes-opened">Boxes Opened: <span id="boxes-opened">0</span>/20</div>
                    <button class="collect-btn" id="collect-btn" onclick="collectWinnings()">
                        Collect Winnings
                    </button>
                </div>

                <!-- Treasure Boxes -->
                <div class="treasure-boxes" id="treasure-boxes">
                    <!-- 20 boxes will be generated by JavaScript -->
                </div>

                <div style="text-align: center; margin-top: 20px; opacity: 0.8;" id="game-status">
                    Set your bet amount and start the treasure hunt!
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
            isPlaying: false,
            betAmount: 100,
            boxesOpened: 0,
            currentWinnings: 0,
            treasureBoxes: [],
            huntsPlayed: 0,
            totalWins: 0
        };

        // Initialize boxes
        function initializeBoxes() {
            const container = document.getElementById('treasure-boxes');
            container.innerHTML = '';
            
            for (let i = 0; i < 20; i++) {
                const box = document.createElement('div');
                box.className = 'treasure-box';
                box.dataset.index = i;
                box.textContent = '📦';
                box.addEventListener('click', () => openBox(i));
                container.appendChild(box);
            }
        }

        // Set bet amount
        function setBetAmount(amount) {
            document.getElementById('bet-amount').value = amount;
            gameState.betAmount = amount;
        }

        // Start hunt with balance deduction
        async function startHunt() {
            const betAmount = parseInt(document.getElementById('bet-amount').value) || 30;
            
            if (betAmount < 100 || betAmount > 5000000) {
                alert('Bet amount must be between ₦100 - ₦5,000,000');
                return;
            }

            // Deduct balance first
            try {
                const response = await fetch('treasure-hunt.php', {
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
                console.log('❌ Treasure hunt bet failed:', error);
                alert('Error placing bet: ' + error.message);
                return;
            }
            
            gameState.betAmount = betAmount;
            gameState.isPlaying = true;
            gameState.boxesOpened = 0;
            gameState.currentWinnings = 0;
            gameState.huntsPlayed++;
            
            // Generate treasure distribution
            gameState.treasureBoxes = Array(20).fill('empty');
            
            // Add treasures (30% chance of treasure)
            for (let i = 0; i < 6; i++) {
                let pos;
                do {
                    pos = Math.floor(Math.random() * 20);
                } while (gameState.treasureBoxes[pos] !== 'empty');
                gameState.treasureBoxes[pos] = 'treasure';
            }
            
            // Add bonuses (20% chance of bonus)
            for (let i = 0; i < 4; i++) {
                let pos;
                do {
                    pos = Math.floor(Math.random() * 20);
                } while (gameState.treasureBoxes[pos] !== 'empty');
                gameState.treasureBoxes[pos] = 'bonus';
            }
            
            // Update UI
            document.getElementById('start-hunt-btn').disabled = true;
            document.getElementById('start-hunt-btn').textContent = 'Hunt in Progress...';
            document.getElementById('hunts-played').textContent = gameState.huntsPlayed;
            document.getElementById('game-status').textContent = 'Click on boxes to discover treasures!';
            
            // Reset boxes
            initializeBoxes();
            updateDisplay();
        }

        // Open box
        function openBox(index) {
            if (!gameState.isPlaying) return;
            
            const box = document.querySelector(`[data-index="${index}"]`);
            if (box.classList.contains('opened')) return;
            
            box.classList.add('opened');
            gameState.boxesOpened++;
            
            const contents = gameState.treasureBoxes[index];
            
            switch(contents) {
                case 'treasure':
                    box.classList.add('treasure');
                    box.textContent = '💎';
                    gameState.currentWinnings += gameState.betAmount * 2;
                    break;
                case 'bonus':
                    box.classList.add('bonus');
                    box.textContent = '🎁';
                    gameState.currentWinnings += gameState.betAmount * 0.5;
                    break;
                case 'empty':
                    box.classList.add('empty');
                    box.textContent = '❌';
                    // GAME OVER - User hit empty box, end game immediately
                    endGameWithLoss();
                    return;
            }
            
            updateDisplay();
            
            // Check if game should end (only for treasure/bonus wins)
            if (gameState.boxesOpened >= 10 || gameState.currentWinnings >= gameState.betAmount * 5) {
                document.getElementById('collect-btn').classList.add('active');
            }
        }

        // Update display
        function updateDisplay() {
            document.getElementById('current-win').textContent = '₦' + gameState.currentWinnings.toFixed(0);
            document.getElementById('boxes-opened').textContent = gameState.boxesOpened;
        }

        // Collect winnings with balance update
        async function collectWinnings() {
            if (!gameState.isPlaying) return;
            
            const winAmount = gameState.currentWinnings;
            
            // Add winnings to balance if any
            if (winAmount > 0) {
                try {
                    const response = await fetch('treasure-hunt.php', {
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
                    }
                } catch (error) {
                    console.log('❌ Adding treasure hunt winnings failed:', error);
                }
            }
            
            gameState.totalWins += gameState.currentWinnings;
            gameState.isPlaying = false;
            
            // Disable all boxes
            document.querySelectorAll('.treasure-box').forEach(box => {
                box.classList.add('disabled');
            });
            
            document.getElementById('game-status').innerHTML = 
                `🎉 Hunt complete!<br>You collected ₦${gameState.currentWinnings.toFixed(0)}!`;
            document.getElementById('total-wins').textContent = '₦' + gameState.totalWins.toFixed(0);
            document.getElementById('collect-btn').classList.remove('active');
            document.getElementById('start-hunt-btn').disabled = false;
            document.getElementById('start-hunt-btn').textContent = 'Start New Hunt';
        }

        // End game immediately with loss (when user hits ❌)
        function endGameWithLoss() {
            gameState.isPlaying = false;
            gameState.currentWinnings = 0; // No winnings for hitting empty box
            
            // Disable all remaining boxes
            document.querySelectorAll('.treasure-box').forEach(box => {
                box.classList.add('disabled');
            });
            
            // Update game status
            document.getElementById('game-status').innerHTML = 
                `💥 GAME OVER!<br>You hit an empty box ❌<br>Better luck next time!`;
            document.getElementById('current-win').textContent = '₦0';
            document.getElementById('collect-btn').classList.remove('active');
            document.getElementById('start-hunt-btn').disabled = false;
            document.getElementById('start-hunt-btn').textContent = 'Try Again';
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

        // Update bet amount when input changes
        document.getElementById('bet-amount').addEventListener('input', function() {
            gameState.betAmount = parseInt(this.value) || 100;
        });

        // Initialize
        initializeBoxes();
    </script>
</body>
</html>
