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
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid bet amount']);
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
        
        // Add winnings
        $newBalance = $currentBalance + $amount;
        $users[$userIndex]['balance'] = $newBalance;
        $users[$userIndex]['updated_at'] = date('Y-m-d H:i:s');
        
        if (saveUsers($users)) {
            echo json_encode([
                'success' => true,
                'message' => 'Winnings added successfully',
                'new_balance' => $newBalance,
                'winnings' => $amount
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add winnings']);
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
    <title>LET PAY YOU - Step Climber</title>
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
            position: relative;
            height: 250px;
            overflow: hidden;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
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

        /* Climbing Game Area */
        .climbing-game {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            text-align: center;
        }

        .ladder-container {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 20px;
            margin: 20px auto;
            max-width: 400px;
            position: relative;
            min-height: 600px;
        }

        .step {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .step.completed {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-color: #4CAF50;
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.5);
        }

        .step.current {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            border-color: #FF9800;
            box-shadow: 0 0 20px rgba(255, 152, 0, 0.5);
            animation: pulse 2s infinite;
        }

        .step.failed {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            border-color: #f44336;
            box-shadow: 0 0 20px rgba(244, 67, 54, 0.5);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .step-number {
            background: rgba(255,255,255,0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .step-multiplier {
            font-size: 1.4rem;
            font-weight: bold;
        }

        .step-potential {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .player-icon {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #FF6B35, #F7931E);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            transition: all 0.5s ease;
            z-index: 10;
            bottom: -30px;
        }

        /* Game Controls */
        .game-controls {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            color: #333;
            margin-bottom: 20px;
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

        .climb-btn {
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
            margin-bottom: 10px;
        }

        .climb-btn:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
            transform: translateY(-2px);
        }

        .climb-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .cashout-btn {
            width: 100%;
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
        }

        .cashout-btn:hover {
            background: linear-gradient(135deg, #F57C00, #FF9800);
            transform: translateY(-2px);
        }

        .current-win {
            background: rgba(76, 175, 80, 0.1);
            border: 2px solid #4CAF50;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            display: none;
        }

        .win-amount {
            font-size: 1.8rem;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }

        .win-message {
            font-size: 0.9rem;
            color: #666;
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
            
            .ladder-container {
                min-height: 500px;
            }
            
            .quick-bets {
                justify-content: center;
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
            <img src="attached_assets/attached_assets/generated_images/Successful_woman_new_car_purchase_05236ed1.png" alt="Step Climber">
            <div class="header-content">
                <h1 class="game-title">⛰️ Step Climber</h1>
                <p class="game-subtitle">Climb the ladder of success! Each step multiplies your bet, but don't fall!</p>
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
                    <div class="stat-value">10</div>
                    <div class="stat-label">Steps</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">5x</div>
                    <div class="stat-label">Max Win</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">50%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>

            <!-- Climbing Game -->
            <div class="climbing-game">
                <h2 style="margin-bottom: 20px;">🏔️ Climb to the Top!</h2>
                
                <div class="ladder-container" id="ladder">
                    <!-- Steps will be generated by JavaScript -->
                    <div class="player-icon" id="player">🧗‍♂️</div>
                </div>

                <p style="margin-top: 20px; opacity: 0.9;">
                    Each step doubles your potential win, but the risk increases too!
                </p>
            </div>

            <!-- Current Win Display -->
            <div class="current-win" id="currentWin">
                <div class="win-amount" id="winAmount">₦0</div>
                <div class="win-message">Current potential win</div>
            </div>

            <!-- Game Controls -->
            <div class="game-controls">
                <div class="bet-section">
                    <div class="control-label">Enter Your Bet Amount</div>
                    <input type="number" class="bet-input" id="betAmount" placeholder="₦100 - ₦5,000,000" min="100" max="5000000" value="100">
                    
                    <div class="quick-bets">
                        <button class="quick-bet" onclick="setBet(100)">₦100</button>
                        <button class="quick-bet" onclick="setBet(1000)">₦1K</button>
                        <button class="quick-bet" onclick="setBet(10000)">₦10K</button>
                        <button class="quick-bet" onclick="setBet(100000)">₦100K</button>
                        <button class="quick-bet" onclick="setBet(5000000)">₦5M</button>
                    </div>
                </div>

                <button class="climb-btn" id="startBtn" onclick="startClimbing()">
                    🧗‍♂️ START CLIMBING
                </button>

                <button class="climb-btn" id="nextBtn" onclick="climbNext()" style="display: none;">
                    ⬆️ CLIMB NEXT STEP
                </button>

                <button class="cashout-btn" id="cashoutBtn" onclick="cashOut()">
                    💰 CASH OUT NOW
                </button>
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
        let currentStep = 0;
        let betAmount = 100;
        let isClimbing = false;
        let gameStarted = false;

        const multipliers = [1.1, 1.2, 1.4, 1.6, 1.8, 2.0, 2.5, 3.0, 4.0, 5.0];

        function initializeLadder() {
            const ladder = document.getElementById('ladder');
            const player = document.getElementById('player');
            
            // Clear existing steps
            const existingSteps = ladder.querySelectorAll('.step');
            existingSteps.forEach(step => step.remove());
            
            // Create 10 steps (reverse order so step 10 is at top)
            for (let i = 9; i >= 0; i--) {
                const step = document.createElement('div');
                step.className = 'step';
                step.id = `step-${i}`;
                
                const stepNumber = document.createElement('div');
                stepNumber.className = 'step-number';
                stepNumber.textContent = i + 1;
                
                const stepInfo = document.createElement('div');
                stepInfo.innerHTML = `
                    <div class="step-multiplier">${multipliers[i]}x</div>
                    <div class="step-potential">+${((multipliers[i] - 1) * 100).toFixed(0)}%</div>
                `;
                
                step.appendChild(stepNumber);
                step.appendChild(stepInfo);
                ladder.insertBefore(step, player);
            }
            
            // Position player at bottom
            updatePlayerPosition();
        }

        function updatePlayerPosition() {
            const player = document.getElementById('player');
            const steps = document.querySelectorAll('.step');
            
            if (currentStep === 0) {
                // Position at bottom
                player.style.bottom = '-30px';
            } else {
                // Position relative to current step
                const currentStepElement = document.getElementById(`step-${currentStep - 1}`);
                if (currentStepElement) {
                    const stepRect = currentStepElement.getBoundingClientRect();
                    const ladderRect = document.getElementById('ladder').getBoundingClientRect();
                    const relativeTop = stepRect.top - ladderRect.top;
                    player.style.bottom = `${document.getElementById('ladder').offsetHeight - relativeTop - 30}px`;
                }
            }
        }

        function updateStepPotentials() {
            for (let i = 0; i < 10; i++) {
                const step = document.getElementById(`step-${i}`);
                if (step) {
                    const potential = step.querySelector('.step-potential');
                    potential.textContent = `+${((multipliers[i] - 1) * 100).toFixed(0)}%`;
                }
            }
        }

        function setBet(amount) {
            betAmount = amount;
            document.getElementById('betAmount').value = amount;
            updateStepPotentials();
            updateCurrentWin();
        }

        function updateCurrentWin() {
            const winDisplay = document.getElementById('currentWin');
            const winAmount = document.getElementById('winAmount');
            
            if (gameStarted && currentStep > 0) {
                const currentWin = betAmount * multipliers[currentStep - 1];
                winAmount.textContent = `₦${Math.round(currentWin).toLocaleString()}`;
                winDisplay.style.display = 'block';
            } else {
                winDisplay.style.display = 'none';
            }
        }

        async function startClimbing() {
            const betInput = document.getElementById('betAmount').value;
            betAmount = parseInt(betInput) || 100;
            
            if (betAmount < 100 || betAmount > 5000000) {
                alert('Bet amount must be between ₦100 - ₦5,000,000');
                return;
            }

            // Deduct balance first
            try {
                const response = await fetch('step-climber.php', {
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
                console.log('❌ Step climber bet failed:', error);
                alert('Error placing bet: ' + error.message);
                return;
            }
            
            // Reset game state
            currentStep = 0;
            gameStarted = true;
            isClimbing = false;
            
            // Update UI
            document.getElementById('startBtn').style.display = 'none';
            document.getElementById('nextBtn').style.display = 'block';
            document.getElementById('cashoutBtn').style.display = 'none';
            
            // Reset ladder
            const steps = document.querySelectorAll('.step');
            steps.forEach(step => {
                step.classList.remove('completed', 'current', 'failed');
            });
            
            initializeLadder();
            updateCurrentWin();
            
            alert(`Climbing started with ₦${betAmount}! Good luck!`);
        }

        function climbNext() {
            if (isClimbing) return;
            
            isClimbing = true;
            const nextBtn = document.getElementById('nextBtn');
            nextBtn.disabled = true;
            nextBtn.textContent = 'CLIMBING...';
            
            // Mark current step as current
            if (currentStep < 10) {
                const currentStepElement = document.getElementById(`step-${currentStep}`);
                currentStepElement.classList.add('current');
            }
            
            setTimeout(() => {
                // Lower success chance (gets much harder as you go higher)
                const successChance = Math.max(0.15, 0.6 - (currentStep * 0.08));
                const success = Math.random() < successChance;
                
                const currentStepElement = document.getElementById(`step-${currentStep}`);
                currentStepElement.classList.remove('current');
                
                if (success) {
                    // Success - move up
                    currentStepElement.classList.add('completed');
                    currentStep++;
                    updatePlayerPosition();
                    updateCurrentWin();
                    
                    if (currentStep >= 10) {
                        // Reached the top!
                        gameWon();
                    } else {
                        // Can continue or cash out
                        nextBtn.disabled = false;
                        nextBtn.textContent = '⬆️ CLIMB NEXT STEP';
                        document.getElementById('cashoutBtn').style.display = 'block';
                    }
                } else {
                    // Failed - fell down
                    currentStepElement.classList.add('failed');
                    gameLost();
                }
                
                isClimbing = false;
            }, 2000);
        }

        async function cashOut() {
            if (currentStep > 0) {
                const winAmount = betAmount * multipliers[currentStep - 1];
                
                // Add winnings to balance
                try {
                    const response = await fetch('step-climber.php', {
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
                    console.log('❌ Adding step climber winnings failed:', error);
                }
                
                alert(`Congratulations! You cashed out ₦${Math.round(winAmount).toLocaleString()}!`);
                resetGame();
            }
        }

        async function gameWon() {
            const winAmount = betAmount * multipliers[9]; // Max multiplier
            
            // Add winnings to balance
            try {
                const response = await fetch('step-climber.php', {
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
                console.log('❌ Adding step climber max winnings failed:', error);
            }
            
            alert(`AMAZING! You reached the top and won ₦${Math.round(winAmount).toLocaleString()}!`);
            resetGame();
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

        function gameLost() {
            alert(`You fell! Better luck next time. You lost ₦${betAmount}.`);
            resetGame();
        }

        function resetGame() {
            gameStarted = false;
            currentStep = 0;
            isClimbing = false;
            
            document.getElementById('startBtn').style.display = 'block';
            document.getElementById('nextBtn').style.display = 'none';
            document.getElementById('cashoutBtn').style.display = 'none';
            
            updateCurrentWin();
            initializeLadder();
        }

        // Initialize game on load
        window.onload = function() {
            initializeLadder();
            
            document.getElementById('betAmount').addEventListener('input', function() {
                const value = parseInt(this.value) || 100;
                setBet(value);
            });
        };
    </script>
</body>
</html>