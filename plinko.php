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

// Function to save user data to Firebase
function saveUsers($users) {
    global $database; // Assuming $database is your Firebase Realtime Database instance
    $usersPath = 'users'; // Firebase path for users
    return $database->getReference($usersPath)->set($users);
}

// Function to get user data from Firebase
function getUsers() {
    global $database;
    $usersPath = 'users';
    $snapshot = $database->getReference($usersPath)->getSnapshot();
    return $snapshot->exists() ? $snapshot->getValue() : [];
}

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

    $users = getUsers() ?: [];
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

// Get user balance
$user_balance_main = 0.00;
if (isset($_SESSION['email'])) {
    $users = getUsers() ?: [];
    foreach ($users as $user) {
        if ($user['email'] === $_SESSION['email']) {
            $user_balance_main = (float)($user['balance'] ?? 0.00);
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET PAY YOU - Plinko</title>
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

        /* Plinko Game Area */
        .plinko-game {
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

        .risk-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 10px;
        }

        .risk-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .risk-btn.active {
            background: #4CAF50;
            color: white;
        }

        .drop-ball-btn {
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

        .drop-ball-btn:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
        }

        .drop-ball-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Plinko Board */
        .plinko-board {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            min-height: 400px;
            position: relative;
            overflow: hidden;
        }

        .drop-zone {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.3);
            border: 3px dashed rgba(255,255,255,0.7);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .drop-zone:hover {
            background: rgba(255,255,255,0.5);
            transform: scale(1.1);
        }

        .pegs-area {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px 0;
        }

        .peg {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            margin: 0 auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        .prize-slots {
            display: grid;
            grid-template-columns: repeat(9, 1fr);
            gap: 5px;
            margin-top: 20px;
        }

        .prize-slot {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.4);
            border-radius: 10px;
            padding: 10px 5px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: bold;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .prize-slot.high {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-color: #4CAF50;
        }

        .prize-slot.medium {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            border-color: #FF9800;
        }

        .prize-slot.low {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            border-color: #f44336;
        }

        .ball {
            width: 16px;
            height: 16px;
            background: #FFD700;
            border-radius: 50%;
            position: absolute;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            z-index: 10;
        }

        /* Game Info */
        .game-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .last-win {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .game-message {
            font-size: 1.1rem;
            opacity: 0.9;
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
            <img src="attached_assets/attached_assets/generated_images/Happy_man_with_luxury_car_4758f826.png" alt="Plinko" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;">
            <div class="header-content">
                <h1 class="game-title">🎯 Plinko</h1>
                <p class="game-subtitle">Drop the ball and watch it bounce to big wins!</p>
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
                    <div class="stat-value">99%</div>
                    <div class="stat-label">RTP Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="balls-dropped">0</div>
                    <div class="stat-label">Balls Dropped</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="total-wins">₦0</div>
                    <div class="stat-label">Total Wins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">1000x</div>
                    <div class="stat-label">Max Win</div>
                </div>
            </div>

            <!-- Plinko Game -->
            <div class="plinko-game">
                <!-- Game Controls -->
                <div class="game-controls">
                    <div class="controls-row">
                        <div class="control-group">
                            <div class="control-label">Bet Amount</div>
                            <input type="number" class="control-input" id="bet-amount" placeholder="₦100" min="100" max="5000000" value="100">
                        </div>

                        <div class="control-group">
                            <div class="control-label">Risk Level</div>
                            <input type="text" class="control-input" id="risk-level" value="Medium" readonly>
                            <div class="risk-selector">
                                <button class="risk-btn" onclick="setRisk('Low')">Low</button>
                                <button class="risk-btn active" onclick="setRisk('Medium')">Medium</button>
                                <button class="risk-btn" onclick="setRisk('High')">High</button>
                            </div>
                        </div>
                    </div>

                    <button class="drop-ball-btn" id="drop-ball-btn" onclick="dropBall()">
                        Drop Ball - ₦<span id="bet-display">20</span>
                    </button>
                </div>

                <!-- Game Info -->
                <div class="game-info">
                    <div class="last-win" id="last-win">Drop a ball to start!</div>
                    <div class="game-message" id="game-message">Choose your bet amount and risk level</div>
                </div>

                <!-- Plinko Board -->
                <div class="plinko-board" id="plinko-board">
                    <div class="drop-zone" onclick="dropBall()">
                        🏀
                    </div>

                    <!-- Pegs -->
                    <div class="pegs-area">
                        <div class="peg" style="grid-column: 2;"></div>
                        <div class="peg" style="grid-column: 4;"></div>
                        <div class="peg" style="grid-column: 6;"></div>
                        <div class="peg" style="grid-column: 8;"></div>

                        <div class="peg" style="grid-column: 1;"></div>
                        <div class="peg" style="grid-column: 3;"></div>
                        <div class="peg" style="grid-column: 5;"></div>
                        <div class="peg" style="grid-column: 7;"></div>

                        <div class="peg" style="grid-column: 2;"></div>
                        <div class="peg" style="grid-column: 4;"></div>
                        <div class="peg" style="grid-column: 6;"></div>
                        <div class="peg" style="grid-column: 8;"></div>

                        <div class="peg" style="grid-column: 1;"></div>
                        <div class="peg" style="grid-column: 3;"></div>
                        <div class="peg" style="grid-column: 5;"></div>
                        <div class="peg" style="grid-column: 7;"></div>
                    </div>

                    <!-- Prize Slots -->
                    <div class="prize-slots" id="prize-slots">
                        <div class="prize-slot high">1000x</div>
                        <div class="prize-slot medium">100x</div>
                        <div class="prize-slot low">10x</div>
                        <div class="prize-slot medium">5x</div>
                        <div class="prize-slot low">2x</div>
                        <div class="prize-slot medium">5x</div>
                        <div class="prize-slot low">10x</div>
                        <div class="prize-slot medium">100x</div>
                        <div class="prize-slot high">1000x</div>
                    </div>
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
            riskLevel: 'Medium',
            ballsDropped: 0,
            totalWins: 0,
            isDropping: false
        };

        // Set risk level
        function setRisk(level) {
            gameState.riskLevel = level;
            document.getElementById('risk-level').value = level;

            // Update active button
            document.querySelectorAll('.risk-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Update prize slots based on risk
            updatePrizeSlots(level);
        }

        // Update prize slots based on risk level
        function updatePrizeSlots(risk) {
            const slots = document.querySelectorAll('.prize-slot');
            let multipliers = [];

            switch(risk) {
                case 'Low':
                    multipliers = ['10x', '5x', '3x', '2x', '1.5x', '2x', '3x', '5x', '10x'];
                    break;
                case 'Medium':
                    multipliers = ['100x', '50x', '10x', '5x', '2x', '5x', '10x', '50x', '100x'];
                    break;
                case 'High':
                    multipliers = ['1000x', '100x', '10x', '5x', '2x', '5x', '10x', '100x', '1000x'];
                    break;
            }

            slots.forEach((slot, index) => {
                slot.textContent = multipliers[index];
            });
        }

        // Update bet display when input changes
        document.getElementById('bet-amount').addEventListener('input', function() {
            const amount = parseInt(this.value) || 20;
            document.getElementById('bet-display').textContent = amount;
            gameState.betAmount = amount;
        });

        // Drop ball with real balance deduction
        async function dropBall() {
            const betAmount = parseInt(document.getElementById('bet-amount').value) || 20;

            if (betAmount < 100 || betAmount > 5000000) {
                alert('Bet amount must be between ₦100 - ₦5,000,000');
                return;
            }

            if (gameState.isDropping) {
                return;
            }

            // Deduct bet amount from user balance
            try {
                const response = await fetch('plinko.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_action=deduct_balance&user_email=<?= $_SESSION['email'] ?>&amount=${betAmount}`
                });

                const result = await response.json();

                if (!result.success) {
                    alert(result.message || 'Failed to start game');
                    return;
                }

                // Update balance display instantly
                updateBalanceDisplay(result.new_balance);

            } catch (error) {
                alert('Error starting game: ' + error.message);
                return;
            }

            gameState.isDropping = true;
            gameState.betAmount = betAmount;
            gameState.ballsDropped++;

            document.getElementById('drop-ball-btn').disabled = true;
            document.getElementById('drop-ball-btn').textContent = 'Ball Dropping...';
            document.getElementById('balls-dropped').textContent = gameState.ballsDropped;
            document.getElementById('game-message').textContent = 'Ball is bouncing down...';

            // Create ball element
            const ball = document.createElement('div');
            ball.className = 'ball';
            ball.style.left = '50%';
            ball.style.top = '80px';
            ball.style.transform = 'translateX(-50%)';
            document.getElementById('plinko-board').appendChild(ball);

            // Animate ball drop
            animateBallDrop(ball);
        }

        // Animate ball drop
        function animateBallDrop(ball) {
            let position = 0;
            let leftOffset = 0;

            const dropInterval = setInterval(() => {
                position += 10;

                // Add random bounce effect
                if (position % 60 === 0) {
                    leftOffset += (Math.random() - 0.5) * 40;
                    leftOffset = Math.max(-150, Math.min(150, leftOffset));
                }

                ball.style.top = (80 + position) + 'px';
                ball.style.left = 'calc(50% + ' + leftOffset + 'px)';

                // Ball reached bottom
                if (position >= 320) {
                    clearInterval(dropInterval);

                    // Determine which slot ball landed in
                    const slotIndex = Math.floor((leftOffset + 180) / 40);
                    const finalSlot = Math.max(0, Math.min(8, slotIndex));

                    // Calculate win
                    calculateWin(finalSlot);

                    // Remove ball
                    setTimeout(() => {
                        ball.remove();
                        gameState.isDropping = false;
                        document.getElementById('drop-ball-btn').disabled = false;
                        document.getElementById('drop-ball-btn').innerHTML = 'Drop Ball - ₦<span id="bet-display">' + gameState.betAmount + '</span>';
                    }, 1000);
                }
            }, 50);
        }

        // Calculate win based on slot with real balance update
        async function calculateWin(slotIndex) {
            const slots = document.querySelectorAll('.prize-slot');
            const multiplierText = slots[slotIndex].textContent;
            const multiplier = parseFloat(multiplierText.replace('x', ''));

            const winAmount = gameState.betAmount * multiplier;
            gameState.totalWins += winAmount;

            // Add winnings to user balance
            if (winAmount > 0) {
                try {
                    const response = await fetch('plinko.php', {
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
                    console.error('Error adding winnings:', error);
                }
            }

            // Highlight winning slot
            slots[slotIndex].style.background = '#FFD700';
            slots[slotIndex].style.color = '#000';
            slots[slotIndex].style.transform = 'scale(1.2)';

            setTimeout(() => {
                slots[slotIndex].style.background = '';
                slots[slotIndex].style.color = '';
                slots[slotIndex].style.transform = '';
            }, 2000);

            // Update display
            document.getElementById('last-win').textContent = '🎉 Won ₦' + winAmount.toFixed(0) + '! (' + multiplierText + ')';
            document.getElementById('total-wins').textContent = '₦' + gameState.totalWins.toFixed(0);
            document.getElementById('game-message').textContent = 'Great drop! Try again for bigger wins!';
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
        updatePrizeSlots('Medium');
    </script>
</body>
</html>