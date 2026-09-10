<?php require_once 'firebase_setup.php'; ?>
<?php
// Include authentication system
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Require login before accessing games
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Function to format amounts with proper comma separation
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Get current authenticated user
$currentUser = getCurrentUser();
$userName = $currentUser['name'] ?? 'Elite Member';
$user_balance_main = (float)($currentUser['balance'] ?? 0.00);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET PAY YOU - Games</title>
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

        /* Top Header with Logo */
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
            filter: brightness(0) invert(1) contrast(100%);
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

        /* Hero Banner */
        .hero-banner {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: linear-gradient(135deg, #FF6B35, #F7931E);
        }

        .hero-content {
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

        .hero-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .balance-display {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 25px;
            margin-top: 15px;
            font-size: 1.1rem;
            font-weight: bold;
        }

        /* Content */
        .content {
            padding: 30px 20px 100px;
        }

        .section-title {
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
            text-align: center;
        }

        /* Games Grid */
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .game-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .game-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
        }

        .game-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .game-content {
            padding: 20px;
        }

        .game-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .game-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .game-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .game-stat {
            text-align: center;
        }

        .stat-value {
            font-weight: bold;
            color: #4CAF50;
            font-size: 1.1rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
        }

        .play-btn {
            width: 100%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .play-btn:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
            transform: translateY(-2px);
        }

        .play-btn.premium {
            background: linear-gradient(135deg, #FF9800, #F57C00);
        }

        .play-btn.premium:hover {
            background: linear-gradient(135deg, #F57C00, #FF9800);
        }

        /* Game Categories */
        .game-categories {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding: 10px 0;
        }

        .category-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #495057;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .category-btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        /* Special Badge */
        .new-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #FF4444;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .hot-badge {
            background: #FF9800;
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

        .nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
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
            .hero-title {
                font-size: 2rem;
            }
            
            .content {
                padding: 20px 15px 100px;
            }
            
            .games-grid {
                grid-template-columns: 1fr;
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
                <span>Welcome, <?php echo htmlspecialchars($userName); ?>!</span>
                <div class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            </div>
        </div>

        <!-- Hero Banner -->
        <div class="hero-banner">
            <img src="attached_assets/attached_assets/generated_images/Happy_family_with_SUV_801a6fa1.png" alt="Gaming Hub" style="width: 100%; height: 100%; object-fit: cover;">
            <div class="hero-content">
                <h1 class="hero-title">Gaming Hub</h1>
                <p class="hero-subtitle">Play, Win, and Earn Real Money!</p>
                <div class="balance-display" id="balance-amount">
                    Your Balance: <?= formatAmount($user_balance_main) ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Game Categories -->
            <div class="game-categories">
                <button class="category-btn active" onclick="filterGames('all', this)">All Games</button>
                <button class="category-btn" onclick="filterGames('sporty', this)">Sporty Games</button>
                <button class="category-btn" onclick="filterGames('original', this)">Original Games</button>
                <button class="category-btn" onclick="filterGames('classic', this)">Classic Games</button>
            </div>

            <h2 class="section-title">🎮 Choose Your Game</h2>

            <!-- Games Grid -->
            <div class="games-grid" id="games-grid">
                <!-- Virtual Sporty -->
                <div class="game-card" data-category="sporty" onclick="location.href='virtual-sporty.php'">
                    <div class="new-badge hot-badge">HOT</div>
                    <img src="attached_assets/attached_assets/generated_images/Family_celebrating_game_wins_2a6b2e3f.png" alt="Virtual Sporty" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">🏈 Virtual Sporty</h3>
                        <p class="game-description">Bet on virtual football, basketball, and racing. Fast results every 2 minutes!</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">₦50+</div>
                                <div class="stat-label">Min Bet</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">98%</div>
                                <div class="stat-label">Payout</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">2min</div>
                                <div class="stat-label">Results</div>
                            </div>
                        </div>
                        <button class="play-btn">Play Now</button>
                    </div>
                </div>

                <!-- Aviator Clone -->
                <div class="game-card" data-category="sporty" onclick="location.href='aviator.php'">
                    <div class="new-badge">NEW</div>
                    <img src="attached_assets/attached_assets/generated_images/Young_gamers_celebration_party_79bdc445.png" alt="Aviator" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">✈️ Aviator</h3>
                        <p class="game-description">Fly high and cash out before the plane crashes! Most popular Nigerian betting game.</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">₦100</div>
                                <div class="stat-label">Min Bet</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">97%</div>
                                <div class="stat-label">RTP</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">30s</div>
                                <div class="stat-label">Round</div>
                            </div>
                        </div>
                        <button class="play-btn premium">Play Aviator</button>
                    </div>
                </div>

                <!-- Mines Game -->
                <div class="game-card" data-category="sporty" onclick="location.href='mines.php'">
                    <img src="attached_assets/attached_assets/generated_images/Casino_jackpot_winner_celebration_1bda733a.png" alt="Mines" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">💎 Mines</h3>
                        <p class="game-description">Find diamonds, avoid mines. Choose your difficulty and win big!</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">₦50</div>
                                <div class="stat-label">Min Bet</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">96%</div>
                                <div class="stat-label">RTP</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">Custom</div>
                                <div class="stat-label">Mines</div>
                            </div>
                        </div>
                        <button class="play-btn">Play Mines</button>
                    </div>
                </div>

                <!-- Plinko -->
                <div class="game-card" data-category="sporty" onclick="location.href='plinko.php'">
                    <img src="attached_assets/attached_assets/generated_images/Young_woman_slot_winner_fcf9b4df.png" alt="Plinko" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">🎯 Plinko</h3>
                        <p class="game-description">Drop the ball and watch it bounce to big wins! Classic casino game.</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">₦20</div>
                                <div class="stat-label">Min Bet</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">99%</div>
                                <div class="stat-label">RTP</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">1000x</div>
                                <div class="stat-label">Max Win</div>
                            </div>
                        </div>
                        <button class="play-btn">Play Plinko</button>
                    </div>
                </div>

                <!-- Crash Rocket -->
                <div class="game-card" data-category="sporty" onclick="location.href='crash.php'">
                    <img src="attached_assets/attached_assets/generated_images/Friends_celebrating_gaming_win_ad648a5b.png" alt="Crash" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">🚀 Crash Rocket</h3>
                        <p class="game-description">Rocket to the moon! Cash out before the crash for maximum profits.</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">₦100</div>
                                <div class="stat-label">Min Bet</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">97%</div>
                                <div class="stat-label">RTP</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">∞</div>
                                <div class="stat-label">Max Win</div>
                            </div>
                        </div>
                        <button class="play-btn">Play Crash</button>
                    </div>
                </div>

                <!-- Lucky Dice -->
                <div class="game-card" data-category="sporty" onclick="location.href='dice.php'">
                    <img src="attached_assets/attached_assets/generated_images/Successful_family_winnings_moment_0a65f163.png" alt="Dice" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">🎲 Lucky Dice</h3>
                        <p class="game-description">Roll high, roll low, or hit the exact number. Simple but exciting!</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">₦20</div>
                                <div class="stat-label">Min Bet</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">98%</div>
                                <div class="stat-label">RTP</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">Fast</div>
                                <div class="stat-label">Results</div>
                            </div>
                        </div>
                        <button class="play-btn">Play Dice</button>
                    </div>
                </div>

                <!-- Package Treasure Hunt (Original) -->
                <div class="game-card" data-category="original" onclick="location.href='treasure-hunt.php'">
                    <div class="new-badge">ORIGINAL</div>
                    <img src="attached_assets/attached_assets/generated_images/Young_man_celebrating_Tesla_purchase_745526ba.png" alt="Treasure Hunt" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">🏆 Package Treasure Hunt</h3>
                        <p class="game-description">Open treasure boxes to find hidden rewards. Hit ❌ and lose instantly! LET PAY YOU exclusive game!</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">₦30</div>
                                <div class="stat-label">Min Play</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">95%</div>
                                <div class="stat-label">RTP</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">20</div>
                                <div class="stat-label">Boxes</div>
                            </div>
                        </div>
                        <button class="play-btn">Hunt Treasure</button>
                    </div>
                </div>

                <!-- Spin & Earn Wheel (Original) -->
                <div class="game-card" data-category="original" onclick="location.href='spin-wheel.php'">
                    <div class="new-badge">ORIGINAL</div>
                    <img src="attached_assets/attached_assets/generated_images/Happy_man_with_luxury_car_4758f826.png" alt="Spin Wheel" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">🎡 Spin & Earn Wheel</h3>
                        <p class="game-description">Spin our branded wheel for instant cash prizes and bonuses!</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">FREE</div>
                                <div class="stat-label">Daily Spin</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">₦50</div>
                                <div class="stat-label">Paid Spin</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">₦5000</div>
                                <div class="stat-label">Max Prize</div>
                            </div>
                        </div>
                        <button class="play-btn">Spin Now</button>
                    </div>
                </div>

                <!-- Step Climber (Original) -->
                <div class="game-card" data-category="original" onclick="location.href='step-climber.php'">
                    <div class="new-badge">ORIGINAL</div>
                    <img src="attached_assets/attached_assets/generated_images/Successful_woman_new_car_purchase_05236ed1.png" alt="Step Climber" class="game-image">
                    <div class="game-content">
                        <h3 class="game-name">⛰️ Step Climber</h3>
                        <p class="game-description">Climb the ladder of success! Each step multiplies your bet, but don't fall!</p>
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="stat-value">₦100</div>
                                <div class="stat-label">Min Bet</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">10</div>
                                <div class="stat-label">Steps</div>
                            </div>
                            <div class="game-stat">
                                <div class="stat-value">512x</div>
                                <div class="stat-label">Max Win</div>
                            </div>
                        </div>
                        <button class="play-btn">Start Climbing</button>
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
        function filterGames(category, element) {
            const cards = document.querySelectorAll('.game-card');
            const buttons = document.querySelectorAll('.category-btn');
            
            // Remove active class from all buttons
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            element.classList.add('active');
            
            // Show/hide cards based on category
            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Add click animations
        document.querySelectorAll('.game-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
</body>
</html>
