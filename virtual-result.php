<?php require_once 'firebase_setup.php'; ?>
<?php
session_start();

// Include authentication system
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Require login before accessing results
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Function to add a new winner to the recent winners list
function addVirtualWinner($playerName, $winAmount, $matchDetails) {
    $winnersFile = 'virtual_winners.json';
    $winners = [];

    if (file_exists($winnersFile)) {
        $winners = json_decode(file_get_contents($winnersFile), true) ?: [];
    }

    // Add new winner
    $winners[] = [
        'player_name' => $playerName,
        'win_amount' => $winAmount,
        'match_details' => $matchDetails,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Keep only last 50 winners to prevent file from growing too large
    if (count($winners) > 50) {
        $winners = array_slice($winners, -50);
    }

    return file_put_contents($winnersFile, json_encode($winners, JSON_PRETTY_PRINT));
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

// Get bet details from URL parameters
$betTeam = $_GET['team'] ?? 'Unknown Team';
$betAmount = floatval($_GET['amount'] ?? 100);
$betOdds = floatval($_GET['odds'] ?? 2.0);
$matchId = $_GET['match'] ?? 'match-1';

// Load virtual sports settings from Firebase
$virtualSettings = readJsonFile('virtual-sporty') ?: [];

// Determine result based on house edge (85% house wins, 15% user wins - REDUCED for business protection)
$houseEdge = $virtualSettings['settings']['house_edge_percentage'] ?? 85;
$userWinChance = 100 - $houseEdge; // Only 15% chance to win
$won = (rand(1, 100) <= $userWinChance);

// Check daily earnings limit
$maxDailyEarnings = 5000;
$profit = $won ? ($betAmount * $betOdds) - $betAmount : 0;

// Cap the profit to not exceed daily limit (simulate with session for demo)
if (!isset($_SESSION['daily_earnings'])) {
    $_SESSION['daily_earnings'] = 0;
    $_SESSION['earnings_date'] = date('Y-m-d');
}

// Reset if new day
if ($_SESSION['earnings_date'] !== date('Y-m-d')) {
    $_SESSION['daily_earnings'] = 0;
    $_SESSION['earnings_date'] = date('Y-m-d');
}

// If win would exceed daily limit, make it a loss
if ($won && ($_SESSION['daily_earnings'] + $profit) > $maxDailyEarnings) {
    $won = false;
    $profit = 0;
}

// Track earnings if won
if ($won) {
    $_SESSION['daily_earnings'] += $profit;

    // Add winnings to user balance
    $users = getUsers();
    if (!empty($users)) {
        foreach ($users as $index => $user) {
            if ($user['email'] === $_SESSION['email']) {
                $currentBalance = (float)($user['balance'] ?? 0.00);
                $newBalance = $currentBalance + ($betAmount * $betOdds);
                $users[$index]['balance'] = $newBalance;
                $users[$index]['updated_at'] = date('Y-m-d H:i:s');

                // Save to Firebase
                if (saveUsers($users)) {
                    // Update session balance if needed
                    if (isset($_SESSION['user'])) {
                        $_SESSION['user']['balance'] = $newBalance;
                    }

                    // Record this winner for the recent winners display
                    $playerName = $user['name'] ?? 'Anonymous Player';
                    $winnings = ($betAmount * $betOdds) - $betAmount; // Net profit
                    addVirtualWinner($playerName, $winnings, $betTeam . ' - ' . $matchId);
                }
                break;
            }
        }
    }
}

$winAmount = $won ? ($betAmount + $profit) : 0;
$profitLoss = $won ? $profit : -$betAmount;

// Simulate match result
$matchResults = [
    'Arsenal vs Chelsea' => $won ? 'Arsenal 2-1 Chelsea' : 'Arsenal 0-2 Chelsea',
    'Man United vs Liverpool' => $won ? 'Man United 3-1 Liverpool' : 'Man United 1-3 Liverpool',
    'Lakers vs Warriors' => $won ? 'Lakers 108-95 Warriors' : 'Lakers 89-112 Warriors',
    'Horse Racing' => $won ? 'Thunder Bolt wins!' : 'Speed King wins!',
];

$finalScore = $matchResults[array_rand($matchResults)];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET PAY YOU - Virtual Sports Result</title>
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
            max-width: 500px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
            position: relative;
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

        /* Result Content */
        .result-content {
            padding: 20px;
            text-align: center;
        }

        .result-header {
            margin-bottom: 30px;
        }

        .result-status {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .result-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .result-title.win {
            color: #4CAF50;
        }

        .result-title.loss {
            color: #f44336;
        }

        .match-score {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        /* Bet Summary */
        .bet-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .summary-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .summary-label {
            color: #666;
            font-weight: 500;
        }

        .summary-value {
            font-weight: bold;
            color: #333;
        }

        .amount-large {
            font-size: 1.4rem;
            font-weight: bold;
        }

        .amount-large.win {
            color: #4CAF50;
        }

        .amount-large.loss {
            color: #f44336;
        }

        /* Live Animation */
        .live-ticker {
            background: linear-gradient(135deg, #FF6B35, #F7931E);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            animation: flashBg 2s infinite;
            margin-bottom: 20px;
        }

        @keyframes flashBg {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }

        .action-btn {
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-play-again {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .btn-play-again:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
            transform: translateY(-2px);
        }

        .btn-back {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .btn-back:hover {
            background: linear-gradient(135deg, #5a6268, #6c757d);
            transform: translateY(-2px);
        }

        /* Confetti Animation for Win */
        .confetti {
            position: fixed;
            top: -10px;
            left: 50%;
            width: 10px;
            height: 10px;
            background: #4CAF50;
            animation: confetti-fall 3s linear infinite;
        }

        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
            }
        }

        /* Bottom Stats */
        .bottom-stats {
            background: #f8f9fa;
            padding: 20px;
            margin-top: 30px;
            border-radius: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            text-align: center;
        }

        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
        }

        .loading-animation {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            flex-direction: column;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .calculating-text {
            font-size: 1.1rem;
            color: #666;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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
                <a href="virtual-sporty.php" style="background: #4CAF50; color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-size: 0.9rem; font-weight: 500;">← Back to Games</a>
                <div class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            </div>
        </div>

        <!-- Result Content -->
        <div class="result-content">
            <!-- Loading Animation (will be hidden after 3 seconds) -->
            <div class="loading-animation" id="loadingSection">
                <div class="spinner"></div>
                <div class="calculating-text">🏃‍♂️ Match in progress...</div>
                <div style="margin-top: 10px; color: #999; font-size: 0.9rem;">Calculating results...</div>
            </div>

            <!-- Actual Results (will be shown after loading) -->
            <div id="resultSection" style="display: none;">
                <div class="live-ticker">
                    🔴 LIVE RESULT - MATCH COMPLETED
                </div>

                <div class="result-header">
                    <div class="result-status">
                        <?php echo $won ? '🎉' : '😔'; ?>
                    </div>
                    <div class="result-title <?php echo $won ? 'win' : 'loss'; ?>">
                        <?php echo $won ? 'CONGRATULATIONS!' : 'BETTER LUCK NEXT TIME'; ?>
                    </div>
                </div>

                <div class="match-score">
                    Final Score: <?php echo $finalScore; ?>
                </div>

                <!-- Bet Summary -->
                <div class="bet-summary">
                    <h3 style="margin-bottom: 20px; color: #333;">📊 Bet Summary</h3>

                    <div class="summary-row">
                        <span class="summary-label">Selection:</span>
                        <span class="summary-value"><?php echo htmlspecialchars($betTeam); ?></span>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Odds:</span>
                        <span class="summary-value"><?php echo number_format($betOdds, 2); ?></span>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Stake:</span>
                        <span class="summary-value"><?php echo formatAmount($betAmount); ?></span>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Status:</span>
                        <span class="summary-value <?php echo $won ? 'win' : 'loss'; ?>">
                            <?php echo $won ? 'WON ✅' : 'LOST ❌'; ?>
                        </span>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Payout:</span>
                        <span class="summary-value amount-large <?php echo $won ? 'win' : 'loss'; ?>">
                            <?php echo formatAmount($winAmount); ?>
                        </span>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Profit/Loss:</span>
                        <span class="summary-value amount-large <?php echo $profitLoss > 0 ? 'win' : 'loss'; ?>">
                            <?php echo ($profitLoss > 0 ? '+' : '') . formatAmount($profitLoss); ?>
                        </span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="virtual-sporty.php" class="action-btn btn-play-again">
                        🎯 Play Again
                    </a>
                    <a href="games.php" class="action-btn btn-back">
                        🏠 All Games
                    </a>
                </div>

                <!-- Bottom Stats -->
                <div class="bottom-stats">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value">3 min</div>
                            <div class="stat-label">Match Duration</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($betOdds, 2); ?>x</div>
                            <div class="stat-label">Final Odds</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $won ? 'WIN' : 'LOSS'; ?></div>
                            <div class="stat-label">Your Result</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show loading for 3 seconds, then show results
        setTimeout(() => {
            document.getElementById('loadingSection').style.display = 'none';
            document.getElementById('resultSection').style.display = 'block';

            // Add confetti for wins
            <?php if ($won): ?>
            createConfetti();
            <?php endif; ?>
        }, 3000);

        // Create confetti animation for wins
        function createConfetti() {
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + '%';
                    confetti.style.backgroundColor = ['#4CAF50', '#FF9800', '#2196F3', '#f44336'][Math.floor(Math.random() * 4)];
                    confetti.style.animationDelay = Math.random() * 2 + 's';
                    document.body.appendChild(confetti);

                    setTimeout(() => {
                        confetti.remove();
                    }, 3000);
                }, i * 100);
            }
        }

        // Update loading text
        const loadingTexts = [
            '🏃‍♂️ Match in progress...',
            '⚽ Analyzing teams...',
            '🎯 Calculating odds...',
            '📊 Finalizing results...'
        ];

        let textIndex = 0;
        const textInterval = setInterval(() => {
            const textElement = document.querySelector('.calculating-text');
            if (textElement) {
                textElement.textContent = loadingTexts[textIndex];
                textIndex = (textIndex + 1) % loadingTexts.length;
            }
        }, 800);

        setTimeout(() => {
            clearInterval(textInterval);
        }, 3000);
    </script>
</body>
</html>