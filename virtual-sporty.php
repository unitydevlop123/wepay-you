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

// Function to get recent virtual sports winners
function getRecentVirtualWinners($limit = 3) {
    $winnersFile = 'virtual_winners.json';
    
    if (!file_exists($winnersFile)) {
        return [];
    }
    
    $winners = json_decode(file_get_contents($winnersFile), true) ?: [];
    
    // Sort by timestamp (newest first) and limit results
    usort($winners, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return array_slice($winners, 0, $limit);
}

// Function to add a new winner to the list
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

// Function to mask player names for privacy
function maskPlayerName($name) {
    if (strlen($name) <= 2) {
        return $name;
    }
    
    $firstName = explode(' ', $name)[0];
    if (strlen($firstName) <= 3) {
        return $firstName . ' ' . strtoupper(substr(explode(' ', $name)[1] ?? '', 0, 1)) . '.';
    }
    
    return substr($firstName, 0, -2) . '**' . ' ' . strtoupper(substr(explode(' ', $name)[1] ?? '', 0, 1)) . '.';
}

// Function to show time ago
function timeAgo($timestamp) {
    $time = time() - strtotime($timestamp);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}

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

// Get user balance from Firebase storage
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
    <title>LET PAY YOU - Instant Virtual Sports</title>
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

        .main-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* Top Header - Same as Profile Page */
        .top-header {
            background: white;
            color: #333;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
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

        .balance-info {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Round Timer Section */
        .round-timer-section {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            text-align: center;
            padding: 20px;
        }

        .timer-display {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
            font-family: 'Courier New', monospace;
        }

        .round-info {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Content Area */
        .content-area {
            padding: 15px;
            background: #f8f9fa;
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
        }

        .live-badge {
            background: #dc2626;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Match Cards - Sporty Bet Style */
        .matches-container {
            display: grid;
            gap: 12px;
        }

        .match-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .match-header {
            background: #f8f9fa;
            padding: 8px 15px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .league-name {
            font-size: 0.8rem;
            color: #666;
            font-weight: 500;
        }

        .match-number {
            font-size: 0.75rem;
            color: #999;
        }

        .match-teams {
            padding: 12px 15px;
        }

        .team-matchup {
            text-align: center;
            margin-bottom: 12px;
            font-size: 0.9rem;
            color: #333;
            font-weight: 600;
        }

        .betting-options {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }

        .bet-option {
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .bet-option:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .bet-option:active {
            transform: scale(0.98);
        }

        .bet-label {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 4px;
        }

        .bet-option:hover .bet-label {
            color: rgba(255,255,255,0.8);
        }

        .bet-odds {
            font-size: 0.95rem;
            font-weight: bold;
            color: #dc2626;
        }

        .bet-option:hover .bet-odds {
            color: white;
        }

        /* Betting Slip */
        .betting-slip {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 500px;
            background: white;
            border-top: 3px solid #dc2626;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.2);
            padding: 15px;
            display: none;
            z-index: 1000;
        }

        .betting-slip.active {
            display: block;
        }

        .slip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .slip-title {
            font-weight: bold;
            color: #dc2626;
        }

        .close-slip {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #666;
        }

        .selected-bet {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
        }

        .bet-details {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .bet-odds-display {
            font-weight: bold;
            color: #dc2626;
        }

        .stake-section {
            margin-bottom: 15px;
        }

        .stake-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }

        .stake-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 1rem;
            text-align: center;
        }

        .quick-stakes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 10px 0;
        }

        .quick-stake {
            padding: 8px;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .quick-stake:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .potential-win {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f0f9ff;
            border-radius: 6px;
            border: 1px solid #bfdbfe;
        }

        .win-label {
            font-size: 0.8rem;
            color: #666;
        }

        .win-amount {
            font-size: 1.1rem;
            font-weight: bold;
            color: #16a34a;
        }

        .place-bet-btn {
            width: 100%;
            background: #dc2626;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
        }

        .place-bet-btn:hover {
            background: #b91c1c;
        }

        .place-bet-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 500px;
            background: white;
            border-top: 1px solid #e5e7eb;
            padding: 10px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            z-index: 999;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px;
            text-decoration: none;
            color: #666;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .nav-item.active {
            color: #dc2626;
            background: #fef2f2;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            margin-bottom: 4px;
            fill: currentColor;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .main-container {
                max-width: 100%;
            }

            .betting-slip, .bottom-nav {
                max-width: 100%;
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
                INSTANT VIRTUALS
            </div>
            <div class="balance-info">
                <?php echo formatAmount($user_balance_main); ?>
                <div style="font-size: 0.7rem; opacity: 0.8; margin-top: 2px; display: none;" id="earningsInfo">
                    Daily Limit Reached - Try Tomorrow!
                </div>
            </div>
        </div>

        <!-- Round Timer Section -->
        <div class="round-timer-section">
            <div class="timer-display" id="roundTimer">03:45</div>
            <div class="round-info">Next Round Starts In | Round #1247</div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Featured Players Section -->
            <div style="display: flex; gap: 10px; margin-bottom: 15px; overflow-x: auto; padding: 10px 0;">
                <img src="attached_assets/attached_assets/generated_images/Businessman_champagne_celebration_success_85461b3b.png" 
                     alt="Virtual Sports Expert" 
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #dc2626; flex-shrink: 0;">
                <img src="attached_assets/attached_assets/generated_images/Elite_female_financial_consultant_ff395274.png" 
                     alt="Sports Analyst" 
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #dc2626; flex-shrink: 0;">
                <img src="attached_assets/attached_assets/generated_images/Investment_advisor_portrait_46777176.png" 
                     alt="Betting Expert" 
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #dc2626; flex-shrink: 0;">
                <img src="attached_assets/attached_assets/generated_images/Female_financial_trader_2a8acb05.png" 
                     alt="Virtual Sports Pro" 
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #dc2626; flex-shrink: 0;">
                <img src="attached_assets/attached_assets/generated_images/Elite_portfolio_manager_5fc2d9d6.png" 
                     alt="Sports Commentator" 
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #dc2626; flex-shrink: 0;">
            </div>

            <div class="section-header">
                <div class="section-title">⚽ INSTANT FOOTBALL</div>
                <div class="live-badge">LIVE</div>
            </div>

            <div class="matches-container">
                <!-- Match 1 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🏴󠁧󠁢󠁥󠁮󠁧󠁿 Premier League</div>
                        <div class="match-number">Match 1/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Arsenal vs Chelsea</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Arsenal Win', '2.10', 'Arsenal vs Chelsea')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">2.10</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.20', 'Arsenal vs Chelsea')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.20</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Chelsea Win', '2.85', 'Arsenal vs Chelsea')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">2.85</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 2 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🏴󠁧󠁢󠁥󠁮󠁧󠁿 Premier League</div>
                        <div class="match-number">Match 2/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Man United vs Liverpool</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Man United Win', '1.95', 'Man United vs Liverpool')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">1.95</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.40', 'Man United vs Liverpool')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.40</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Liverpool Win', '3.10', 'Man United vs Liverpool')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">3.10</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 3 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🇪🇸 La Liga</div>
                        <div class="match-number">Match 3/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Real Madrid vs Barcelona</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Real Madrid Win', '1.80', 'Real Madrid vs Barcelona')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">1.80</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.60', 'Real Madrid vs Barcelona')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.60</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Barcelona Win', '3.90', 'Real Madrid vs Barcelona')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">3.90</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 4 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🇮🇹 Serie A</div>
                        <div class="match-number">Match 4/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Juventus vs AC Milan</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Juventus Win', '2.20', 'Juventus vs AC Milan')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">2.20</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.10', 'Juventus vs AC Milan')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.10</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('AC Milan Win', '2.95', 'Juventus vs AC Milan')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">2.95</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 5 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🇩🇪 Bundesliga</div>
                        <div class="match-number">Match 5/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Bayern Munich vs Borussia Dortmund</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Bayern Munich Win', '1.75', 'Bayern Munich vs Borussia Dortmund')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">1.75</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.80', 'Bayern Munich vs Borussia Dortmund')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.80</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Dortmund Win', '4.20', 'Bayern Munich vs Borussia Dortmund')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">4.20</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 6 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🏴󠁧󠁢󠁥󠁮󠁧󠁿 Premier League</div>
                        <div class="match-number">Match 6/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Man City vs Tottenham</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Man City Win', '1.85', 'Man City vs Tottenham')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">1.85</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.50', 'Man City vs Tottenham')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.50</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Tottenham Win', '3.80', 'Man City vs Tottenham')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">3.80</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 7 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🇪🇸 La Liga</div>
                        <div class="match-number">Match 7/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Atletico Madrid vs Valencia</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Atletico Madrid Win', '2.40', 'Atletico Madrid vs Valencia')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">2.40</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.00', 'Atletico Madrid vs Valencia')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.00</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Valencia Win', '2.90', 'Atletico Madrid vs Valencia')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">2.90</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 8 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🇮🇹 Serie A</div>
                        <div class="match-number">Match 8/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Inter Milan vs AS Roma</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Inter Milan Win', '2.10', 'Inter Milan vs AS Roma')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">2.10</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.20', 'Inter Milan vs AS Roma')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.20</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('AS Roma Win', '3.30', 'Inter Milan vs AS Roma')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">3.30</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 9 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🇩🇪 Bundesliga</div>
                        <div class="match-number">Match 9/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">RB Leipzig vs Bayer Leverkusen</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('RB Leipzig Win', '2.60', 'RB Leipzig vs Bayer Leverkusen')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">2.60</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '2.90', 'RB Leipzig vs Bayer Leverkusen')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">2.90</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Bayer Leverkusen Win', '2.80', 'RB Leipzig vs Bayer Leverkusen')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">2.80</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 10 -->
                <div class="match-card">
                    <div class="match-header">
                        <div class="league-name">🏴󠁧󠁢󠁥󠁮󠁧󠁿 Premier League</div>
                        <div class="match-number">Match 10/10</div>
                    </div>
                    <div class="match-teams">
                        <div class="team-matchup">Newcastle vs Aston Villa</div>
                        <div class="betting-options">
                            <div class="bet-option" onclick="placeBet('Newcastle Win', '2.25', 'Newcastle vs Aston Villa')">
                                <div class="bet-label">1</div>
                                <div class="bet-odds">2.25</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Draw', '3.10', 'Newcastle vs Aston Villa')">
                                <div class="bet-label">X</div>
                                <div class="bet-odds">3.10</div>
                            </div>
                            <div class="bet-option" onclick="placeBet('Aston Villa Win', '3.05', 'Newcastle vs Aston Villa')">
                                <div class="bet-label">2</div>
                                <div class="bet-odds">3.05</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Winners Section - Dynamic Real Winners -->
            <div style="background: white; border-radius: 8px; padding: 15px; margin: 15px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="color: #dc2626; font-size: 1rem; margin-bottom: 15px; text-align: center;">🏆 Recent Winners</h3>
                <div id="recentWinners">
                    <?php
                    // Get real recent winners from virtual sports results
                    $recentWinners = getRecentVirtualWinners();
                    $winnerImages = [
                        'attached_assets/attached_assets/generated_images/Friends_champagne_success_celebration_a32bf813.png',
                        'attached_assets/attached_assets/generated_images/Family_celebrating_game_wins_2a6b2e3f.png',
                        'attached_assets/attached_assets/generated_images/Young_woman_money_celebration_5b11c4af.png',
                        'attached_assets/attached_assets/generated_images/Businessman_champagne_celebration_success_85461b3b.png',
                        'attached_assets/attached_assets/generated_images/Elite_female_financial_consultant_ff395274.png'
                    ];
                    
                    if (empty($recentWinners)) {
                        // Show placeholder when no real winners yet
                        echo '<div style="text-align: center; color: #666; font-style: italic; padding: 20px;">
                                🎯 Be the first winner! Place your bets now!
                              </div>';
                    } else {
                        foreach ($recentWinners as $index => $winner) {
                            $imageIndex = $index % count($winnerImages);
                            $maskedName = maskPlayerName($winner['player_name']);
                            $winAmount = formatAmount($winner['win_amount']);
                            
                            echo '<div style="display: flex; gap: 15px; align-items: center; margin-bottom: 12px;">
                                    <img src="' . $winnerImages[$imageIndex] . '" 
                                         alt="Winner" 
                                         style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <div style="font-weight: bold; font-size: 0.9rem; color: #333;">' . htmlspecialchars($maskedName) . '</div>
                                        <div style="font-size: 0.8rem; color: #16a34a;">Won ' . $winAmount . ' • ' . htmlspecialchars($winner['match_details']) . '</div>
                                        <div style="font-size: 0.7rem; color: #999;">' . timeAgo($winner['timestamp']) . '</div>
                                    </div>
                                  </div>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div style="height: 80px;"></div> <!-- Spacer for bottom nav -->
        </div>

        <!-- Betting Slip -->
        <div class="betting-slip" id="bettingSlip">
            <div class="slip-header">
                <div class="slip-title">BET SLIP</div>
                <button class="close-slip" onclick="closeBettingSlip()">&times;</button>
            </div>

            <div class="selected-bet">
                <div class="bet-details" id="selectedBetDetails">No selection</div>
                <div class="bet-odds-display" id="selectedBetOdds">-</div>
            </div>

            <div class="stake-section">
                <div class="stake-label">Stake Amount</div>
                <input type="number" class="stake-input" id="stakeInput" placeholder="₦30" min="30" step="10" value="100">
                <div class="quick-stakes">
                    <div class="quick-stake" onclick="setStake(30)">₦30</div>
                    <div class="quick-stake" onclick="setStake(50)">₦50</div>
                    <div class="quick-stake" onclick="setStake(100)">₦100</div>
                    <div class="quick-stake" onclick="setStake(200)">₦200</div>
                </div>
            </div>

            <div class="potential-win">
                <div class="win-label">Potential Win</div>
                <div class="win-amount" id="potentialWin">₦200.00</div>
            </div>

            <button class="place-bet-btn" onclick="confirmBet()">PLACE BET</button>
        </div>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="games.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
                Games
            </a>

            <a href="virtual-sporty.php" class="nav-item active">
                <svg class="nav-icon" viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                Virtual
            </a>

            <a href="dashboard.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24">
                    <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                </svg>
                Dashboard
            </a>

            <a href="profile.php" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                Profile
            </a>
        </nav>
    </div>

    <script>
        let currentBet = {
            selection: '',
            odds: 0,
            match: '',
            stake: 100
        };

        // Live system state
        let systemState = {
            currentRound: 1247,
            timeRemaining: 225, // 3:45 in seconds
            lastOddsUpdate: Date.now(),
            totalBetsToday: 15420,
            roundStarted: false
        };

        // Teams and leagues for dynamic generation
        const leagues = {
            'Premier League': ['Arsenal', 'Chelsea', 'Manchester United', 'Liverpool', 'Manchester City', 'Tottenham', 'Newcastle', 'Aston Villa', 'Brighton', 'Crystal Palace'],
            'La Liga': ['Real Madrid', 'Barcelona', 'Atletico Madrid', 'Valencia', 'Sevilla', 'Real Sociedad', 'Athletic Bilbao', 'Villarreal'],
            'Serie A': ['Juventus', 'AC Milan', 'Inter Milan', 'AS Roma', 'Napoli', 'Lazio', 'Atalanta', 'Fiorentina'],
            'Bundesliga': ['Bayern Munich', 'Borussia Dortmund', 'RB Leipzig', 'Bayer Leverkusen', 'Eintracht Frankfurt', 'Borussia Monchengladbach', 'VfB Stuttgart']
        };

        const leagueFlags = {
            'Premier League': '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
            'La Liga': '🇪🇸',
            'Serie A': '🇮🇹',
            'Bundesliga': '🇩🇪'
        };

        // Place bet function
        function placeBet(selection, odds, match) {
            currentBet = {
                selection: selection,
                odds: parseFloat(odds),
                match: match,
                stake: parseInt(document.getElementById('stakeInput').value) || 100
            };

            document.getElementById('selectedBetDetails').textContent = selection + ' - ' + match;
            document.getElementById('selectedBetOdds').textContent = 'Odds: ' + odds;
            updatePotentialWin();

            document.getElementById('bettingSlip').classList.add('active');
        }

        // Set stake amount
        function setStake(amount) {
            document.getElementById('stakeInput').value = amount;
            currentBet.stake = amount;
            updatePotentialWin();
        }

        // Update potential win
        function updatePotentialWin() {
            const stake = parseInt(document.getElementById('stakeInput').value) || 0;
            const potentialWin = (stake * currentBet.odds).toFixed(2);
            document.getElementById('potentialWin').textContent = '₦' + parseFloat(potentialWin).toLocaleString();
            currentBet.stake = stake;
        }

        // Close betting slip
        function closeBettingSlip() {
            document.getElementById('bettingSlip').classList.remove('active');
        }

        // Confirm bet with instant balance deduction
        async function confirmBet() {
            const stake = currentBet.stake;

            if (stake < 30) {
                alert('Minimum bet is ₦30');
                return;
            }

            // Check daily earnings limit
            if (!checkEarningsLimit()) {
                alert('Daily earnings limit of ₦5,000 reached! Come back tomorrow at 12:00 AM to continue playing.');
                return;
            }

            const remaining = getRemainingEarnings();
            const potentialWin = (stake * currentBet.odds) - stake;
            
            if (potentialWin > remaining) {
                alert(`You can only earn ₦${remaining.toFixed(2)} more today to stay within your ₦5,000 daily limit.`);
                return;
            }

            // Deduct balance first
            try {
                const response = await fetch('virtual-sporty.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_action=deduct_balance&user_email=<?= $_SESSION['email'] ?>&amount=${stake}`
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    alert(result.message || 'Failed to place bet');
                    return;
                }

                // Update balance display instantly
                updateBalanceDisplay(result.new_balance);
                
            } catch (error) {
                console.log('❌ Virtual sports bet failed:', error);
                alert('Error placing bet: ' + error.message);
                return;
            }

            // Update bet statistics
            systemState.totalBetsToday++;

            // Redirect to result page exactly like Sporty Bet instant virtual
            const resultUrl = `virtual-result.php?team=${encodeURIComponent(currentBet.selection)}&amount=${stake}&odds=${currentBet.odds}&match=${encodeURIComponent(currentBet.match)}`;
            window.location.href = resultUrl;
        }

        // Update balance display instantly
        function updateBalanceDisplay(newBalance) {
            const formattedBalance = '₦' + newBalance.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            // Update balance in header
            const balanceElement = document.querySelector('.balance-info');
            if (balanceElement) {
                balanceElement.textContent = formattedBalance;
            }
        }

        // Check daily earnings limit (₦5,000 max per day)
        let dailyEarnings = parseFloat(localStorage.getItem('dailyEarnings_' + getTodayDate()) || '0');
        const MAX_DAILY_EARNINGS = 5000;

        function getTodayDate() {
            // Get current Lagos time (UTC+1)
            const now = new Date();
            const lagosTime = new Date(now.getTime() + (1 * 60 * 60 * 1000)); // UTC+1
            return lagosTime.toDateString();
        }

        function checkEarningsLimit() {
            const today = getTodayDate();
            const storedDate = localStorage.getItem('earningsDate');
            
            // Reset earnings if new day
            if (storedDate !== today) {
                dailyEarnings = 0;
                localStorage.setItem('dailyEarnings_' + today, '0');
                localStorage.setItem('earningsDate', today);
            }
            
            return dailyEarnings < MAX_DAILY_EARNINGS;
        }

        function updateDailyEarnings(amount) {
            const today = getTodayDate();
            dailyEarnings += amount;
            localStorage.setItem('dailyEarnings_' + today, dailyEarnings.toString());
        }

        function getRemainingEarnings() {
            return Math.max(0, MAX_DAILY_EARNINGS - dailyEarnings);
        }

        // Generate random match with shuffled teams
        function generateRandomMatch(leagueName, matchNumber) {
            const teams = leagues[leagueName];
            
            // Shuffle teams array to get different matchups each round
            const shuffledTeams = [...teams].sort(() => Math.random() - 0.5);
            const homeTeam = shuffledTeams[Math.floor(Math.random() * shuffledTeams.length)];
            let awayTeam = shuffledTeams[Math.floor(Math.random() * shuffledTeams.length)];
            
            // Ensure different teams
            while (awayTeam === homeTeam) {
                awayTeam = shuffledTeams[Math.floor(Math.random() * shuffledTeams.length)];
            }

            // Generate realistic odds
            const homeOdds = (1.75 + Math.random() * 2.5).toFixed(2);
            const drawOdds = (2.90 + Math.random() * 0.9).toFixed(2);
            const awayOdds = (1.85 + Math.random() * 2.35).toFixed(2);

            return {
                league: leagueName,
                homeTeam,
                awayTeam,
                homeOdds: parseFloat(homeOdds),
                drawOdds: parseFloat(drawOdds),
                awayOdds: parseFloat(awayOdds),
                matchNumber
            };
        }

        // Generate new round of matches
        function generateNewRound() {
            systemState.currentRound++;
            const matchesContainer = document.querySelector('.matches-container');
            matchesContainer.innerHTML = '';

            const leagueNames = Object.keys(leagues);
            
            for (let i = 1; i <= 10; i++) {
                const randomLeague = leagueNames[Math.floor(Math.random() * leagueNames.length)];
                const match = generateRandomMatch(randomLeague, i);
                
                const matchCard = createMatchCard(match);
                matchesContainer.appendChild(matchCard);
            }

            // Update round info
            document.querySelector('.round-info').textContent = `Next Round Starts In | Round #${systemState.currentRound}`;
            
            console.log(`🔄 Generated new round #${systemState.currentRound} with fresh matches`);
        }

        // Create match card element
        function createMatchCard(match) {
            const card = document.createElement('div');
            card.className = 'match-card';
            card.innerHTML = `
                <div class="match-header">
                    <div class="league-name">${leagueFlags[match.league]} ${match.league}</div>
                    <div class="match-number">Match ${match.matchNumber}/10</div>
                </div>
                <div class="match-teams">
                    <div class="team-matchup">${match.homeTeam} vs ${match.awayTeam}</div>
                    <div class="betting-options">
                        <div class="bet-option" onclick="placeBet('${match.homeTeam} Win', '${match.homeOdds}', '${match.homeTeam} vs ${match.awayTeam}')">
                            <div class="bet-label">1</div>
                            <div class="bet-odds">${match.homeOdds}</div>
                        </div>
                        <div class="bet-option" onclick="placeBet('Draw', '${match.drawOdds}', '${match.homeTeam} vs ${match.awayTeam}')">
                            <div class="bet-label">X</div>
                            <div class="bet-odds">${match.drawOdds}</div>
                        </div>
                        <div class="bet-option" onclick="placeBet('${match.awayTeam} Win', '${match.awayOdds}', '${match.homeTeam} vs ${match.awayTeam}')">
                            <div class="bet-label">2</div>
                            <div class="bet-odds">${match.awayOdds}</div>
                        </div>
                    </div>
                </div>
            `;
            return card;
        }

        // Update odds dynamically (simulate market movement)
        function updateOddsLive() {
            const now = Date.now();
            if (now - systemState.lastOddsUpdate < 10000) return; // Update every 10 seconds
            
            const oddsElements = document.querySelectorAll('.bet-odds');
            oddsElements.forEach(element => {
                let currentOdds = parseFloat(element.textContent);
                
                // Small random change (-0.05 to +0.05)
                const change = (Math.random() - 0.5) * 0.1;
                let newOdds = currentOdds + change;
                
                // Keep odds within realistic bounds
                newOdds = Math.max(1.20, Math.min(5.00, newOdds));
                element.textContent = newOdds.toFixed(2);
                
                // Add visual flash effect
                element.style.background = '#fff3cd';
                setTimeout(() => {
                    element.style.background = '';
                }, 1000);
            });
            
            systemState.lastOddsUpdate = now;
            console.log('📈 Live odds updated - market movement detected');
        }

        // Round timer countdown with live functionality
        function updateRoundTimer() {
            systemState.timeRemaining--;
            
            const minutes = Math.floor(systemState.timeRemaining / 60);
            const seconds = systemState.timeRemaining % 60;
            
            document.getElementById('roundTimer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            // When timer reaches 0, start new round
            if (systemState.timeRemaining <= 0) {
                systemState.timeRemaining = 240; // Reset to 4:00 (240 seconds)
                
                // Show "MATCHES STARTING!" message
                const timer = document.getElementById('roundTimer');
                timer.style.color = '#ffeb3b';
                timer.textContent = 'STARTING!';
                
                setTimeout(() => {
                    timer.style.color = '';
                    generateNewRound();
                }, 2000);
                
                systemState.roundStarted = true;
            }
        }

        // Simulate live betting activity
        function simulateLiveActivity() {
            // Random bet count increases
            if (Math.random() < 0.3) {
                systemState.totalBetsToday += Math.floor(Math.random() * 3) + 1;
            }
            
            // Update live statistics occasionally
            if (Math.random() < 0.1) {
                console.log(`🎲 Live activity: ${systemState.totalBetsToday} total bets today, Round #${systemState.currentRound}`);
            }
        }

        // Add live data indicators
        function addLiveIndicators() {
            // Add pulsing effect to live badge
            const liveBadge = document.querySelector('.live-badge');
            if (liveBadge) {
                setInterval(() => {
                    liveBadge.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        liveBadge.style.transform = 'scale(1)';
                    }, 500);
                }, 3000);
            }

            // Add connection status indicator
            const topHeader = document.querySelector('.top-header');
            const indicator = document.createElement('div');
            indicator.innerHTML = '🟢';
            indicator.style.position = 'absolute';
            indicator.style.right = '10px';
            indicator.style.top = '5px';
            indicator.style.fontSize = '8px';
            indicator.title = 'Live Connection Active';
            topHeader.style.position = 'relative';
            topHeader.appendChild(indicator);
        }

        // Update stake when input changes
        document.getElementById('stakeInput').addEventListener('input', updatePotentialWin);

        // Update earnings display
        function updateEarningsDisplay() {
            checkEarningsLimit();
            const remaining = getRemainingEarnings();
            const earningsInfo = document.getElementById('earningsInfo');
            if (earningsInfo) {
                if (remaining <= 0) {
                    earningsInfo.style.display = 'block';
                    earningsInfo.style.color = '#ff4444';
                    earningsInfo.textContent = 'Daily Limit Reached - Try Tomorrow!';
                } else {
                    earningsInfo.style.display = 'none';
                }
            }
        }

        // Main system initialization
        function initializeLiveSystem() {
            console.log('🚀 Virtual Sports Live System Initialized');
            console.log(`📊 Current Round: ${systemState.currentRound}`);
            console.log(`⏰ Time Remaining: ${Math.floor(systemState.timeRemaining/60)}:${(systemState.timeRemaining%60).toString().padStart(2, '0')}`);
            
            // Initialize earnings tracking
            checkEarningsLimit();
            updateEarningsDisplay();
            
            // Start all live updates
            setInterval(updateRoundTimer, 1000);           // Timer every second
            setInterval(updateOddsLive, 8000);             // Odds every 8 seconds
            setInterval(simulateLiveActivity, 5000);       // Activity every 5 seconds
            setInterval(updateEarningsDisplay, 30000);     // Update earnings display every 30 seconds
            
            addLiveIndicators();
            updatePotentialWin();
            
            // Start with some initial activity
            setTimeout(() => {
                console.log('🎯 System is now fully live - odds will update automatically');
                console.log(`💰 Daily earnings tracking active - ₦${getRemainingEarnings().toFixed(2)} remaining today`);
            }, 3000);
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', initializeLiveSystem);
    </script>
</body>
</html>