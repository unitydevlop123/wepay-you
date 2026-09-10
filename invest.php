<?php require_once 'firebase_setup.php'; ?>
<?php

// Include authentication system
require_once 'auth.php';


// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Enable proper authentication
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Get user data from authenticated session
$current_user_email = $_SESSION['email'];


// LET PAY YOU investment packages data - LOADED FROM FIREBASE
$package_names = ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Ruby', 'Sapphire', 'Emerald', 'Topaz', 'Crown', 'Legend'];
$investment_packages = [];

foreach ($package_names as $index => $name) {
    $packageData = readJsonFile(strtolower($name));
    if ($packageData && !empty($packageData)) {
        // Define unique celebration images for each package (no duplicates with other pages)
        $celebration_images = [
            'Bronze' => 'attached_assets/attached_assets/generated_images/Investment_advisor_portrait_46777176.png',
            'Silver' => 'attached_assets/attached_assets/generated_images/Luxury_investment_advisor_46491d9b.png',
            'Gold' => 'attached_assets/attached_assets/generated_images/Professional_investment_advisor_headshot_9cd4e93e.png',
            'Platinum' => 'attached_assets/attached_assets/generated_images/Distinguished_investment_banker_executive_7f8f3e33.png',
            'Diamond' => 'attached_assets/attached_assets/generated_images/Elite_female_financial_consultant_ff395274.png',
            'Ruby' => 'attached_assets/attached_assets/generated_images/Business_presentation_scene_44488820.png',
            'Sapphire' => 'attached_assets/attached_assets/generated_images/Investment_team_collaboration_86961e9e.png',
            'Emerald' => 'attached_assets/attached_assets/generated_images/Elite_wealth_manager_portrait_03302be9.png',
            'Topaz' => 'attached_assets/attached_assets/generated_images/Young_woman_money_celebration_5b11c4af.png',
            'Crown' => 'attached_assets/attached_assets/generated_images/Executive_dashboard_presentation_6cb8c8f8.png',
            'Legend' => 'attached_assets/attached_assets/generated_images/Wealth_management_executive_ccd05c9a.png'
        ];
        
        // Use real data from Firebase - Always use index-based ID for reliable navigation
        $investment_packages[] = [
            'id' => $index + 1, // Fixed ID based on position for reliable routing
            'name' => $packageData['name'] ?? $name,
            'capital' => $packageData['capital'] ?? 0,
            'daily_profit' => $packageData['daily_profit'] ?? 0,
            'duration' => $packageData['duration'] ?? 0,
            'orders' => $packageData['orders'] ?? 0,
            'total_profit' => $packageData['total_profit'] ?? 0,
            'total_return' => $packageData['total_return'] ?? 0,
            'description' => $packageData['description'] ?? "Premium {$name} package with excellent returns.",
            'image' => $celebration_images[$name] ?? 'attached_assets/attached_assets/generated_images/Investment_advisor_portrait_46777176.png',
            'popular' => $packageData['popular'] ?? false
        ];
    } else {
        // Comprehensive fallback for all packages
        $fallback_data = [
            'Bronze' => ['capital' => 1500, 'daily_profit' => 150, 'duration' => 10, 'orders' => 20, 'total_profit' => 1500, 'total_return' => 3000],
            'Silver' => ['capital' => 2500, 'daily_profit' => 250, 'duration' => 12, 'orders' => 25, 'total_profit' => 3000, 'total_return' => 5500],
            'Gold' => ['capital' => 3000, 'daily_profit' => 300, 'duration' => 12, 'orders' => 30, 'total_profit' => 3600, 'total_return' => 6600],
            'Platinum' => ['capital' => 5000, 'daily_profit' => 500, 'duration' => 15, 'orders' => 40, 'total_profit' => 7500, 'total_return' => 12500],
            'Diamond' => ['capital' => 10000, 'daily_profit' => 1000, 'duration' => 20, 'orders' => 50, 'total_profit' => 20000, 'total_return' => 30000],
            'Ruby' => ['capital' => 15000, 'daily_profit' => 1500, 'duration' => 25, 'orders' => 60, 'total_profit' => 37500, 'total_return' => 52500],
            'Sapphire' => ['capital' => 25000, 'daily_profit' => 2500, 'duration' => 30, 'orders' => 75, 'total_profit' => 75000, 'total_return' => 100000],
            'Emerald' => ['capital' => 50000, 'daily_profit' => 5000, 'duration' => 35, 'orders' => 100, 'total_profit' => 175000, 'total_return' => 225000],
            'Topaz' => ['capital' => 75000, 'daily_profit' => 7500, 'duration' => 40, 'orders' => 125, 'total_profit' => 300000, 'total_return' => 375000],
            'Crown' => ['capital' => 100000, 'daily_profit' => 10000, 'duration' => 45, 'orders' => 150, 'total_profit' => 450000, 'total_return' => 550000],
            'Legend' => ['capital' => 200000, 'daily_profit' => 20000, 'duration' => 50, 'orders' => 200, 'total_profit' => 1000000, 'total_return' => 1200000]
        ];

        $data = $fallback_data[$name] ?? ['capital' => 1000, 'daily_profit' => 100, 'duration' => 10, 'orders' => 10, 'total_profit' => 1000, 'total_return' => 2000];
        // Define unique celebration images for each package (no duplicates with other pages)
        $celebration_images = [
            'Bronze' => 'attached_assets/attached_assets/generated_images/Investment_advisor_portrait_46777176.png',
            'Silver' => 'attached_assets/attached_assets/generated_images/Luxury_investment_advisor_46491d9b.png',
            'Gold' => 'attached_assets/attached_assets/generated_images/Professional_investment_advisor_headshot_9cd4e93e.png',
            'Platinum' => 'attached_assets/attached_assets/generated_images/Distinguished_investment_banker_executive_7f8f3e33.png',
            'Diamond' => 'attached_assets/attached_assets/generated_images/Elite_female_financial_consultant_ff395274.png',
            'Ruby' => 'attached_assets/attached_assets/generated_images/Business_presentation_scene_44488820.png',
            'Sapphire' => 'attached_assets/attached_assets/generated_images/Investment_team_collaboration_86961e9e.png',
            'Emerald' => 'attached_assets/attached_assets/generated_images/Elite_wealth_manager_portrait_03302be9.png',
            'Topaz' => 'attached_assets/attached_assets/generated_images/Young_woman_money_celebration_5b11c4af.png',
            'Crown' => 'attached_assets/attached_assets/generated_images/Executive_dashboard_presentation_6cb8c8f8.png',
            'Legend' => 'attached_assets/attached_assets/generated_images/Wealth_management_executive_ccd05c9a.png'
        ];
        
        $investment_packages[] = [
            'id' => $index + 1,
            'name' => $name,
            'capital' => $data['capital'],
            'daily_profit' => $data['daily_profit'],
            'duration' => $data['duration'],
            'orders' => $data['orders'],
            'total_profit' => $data['total_profit'],
            'total_return' => $data['total_return'],
            'description' => "Premium {$name} package with excellent returns.",
            'image' => $celebration_images[$name] ?? 'attached_assets/attached_assets/generated_images/Investment_advisor_portrait_46777176.png',
            'popular' => false
        ];
    }
}

function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment Packages - LET PAY YOU</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            overflow-x: hidden;
            overflow-y: scroll;
            scrollbar-gutter: stable;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
            position: relative;
            width: 100%;
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: white;
            padding: 20px;
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

        .back-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Page Title */
        .page-header {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #f8f9ff, #e8f0ff);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 1rem;
            color: #718096;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.4;
        }

        /* Content */
        .content {
            padding: 40px 20px 100px;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 25px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .package-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .package-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .package-card.popular {
            border: 3px solid #10b981;
            transform: scale(1.02);
        }

        .popular-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 2;
        }

        .package-image {
            width: 100%;
            height: 160px;
            position: relative;
            overflow: hidden;
        }

        .package-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .package-card:hover .package-image img {
            transform: scale(1.05);
        }



        .package-content {
            padding: 20px;
        }

        .package-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
            text-align: center;
        }

        .package-description {
            color: #4a5568;
            line-height: 1.4;
            margin-bottom: 15px;
            font-size: 0.9rem;
            text-align: center;
        }

        .package-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .detail-item {
            text-align: center;
            padding: 12px;
            background: #f7fafc;
            border-radius: 10px;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #718096;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d3748;
        }

        .return-highlight {
            color: #10b981;
            font-size: 1rem;
        }

        .capital-highlight {
            background: linear-gradient(135deg, #e6fffa, #f0fff4);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
            border: 2px solid #10b981;
        }

        .capital-label {
            font-size: 0.85rem;
            color: #047857;
            margin-bottom: 5px;
        }

        .capital-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #065f46;
        }

        .profit-summary {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
        }

        .profit-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .profit-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #28a745;
        }

        .invest-btn {
            width: 100%;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .invest-btn:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .packages-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .page-title {
                font-size: 1.6rem;
            }

            .package-card.popular {
                transform: none;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 15px;
            }

            .content {
                padding: 30px 15px 100px;
            }

            .package-content {
                padding: 18px;
            }

            .page-header {
                padding: 30px 15px;
            }

            .packages-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <img src="logo.svg" alt="LET PAY YOU">
                <h1>LET PAY YOU</h1>
            </div>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">LET PAY YOU - Investment Plans</h1>
            <p class="page-subtitle">Choose from our 11 premium investment packages. Complete daily tasks, earn daily profits, and grow your income with simple orders.</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="packages-grid">
                <?php foreach ($investment_packages as $package): ?>
                <div class="package-card <?= $package['popular'] ? 'popular' : '' ?>">
                    <?php if ($package['popular']): ?>
                    <div class="popular-badge">Most Popular</div>
                    <?php endif; ?>

                    <div class="package-image">
                        <img src="<?= htmlspecialchars($package['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="package-content">
                        <h2 class="package-name"><?= htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="package-description"><?= htmlspecialchars($package['description'], ENT_QUOTES, 'UTF-8') ?></p>

                        <div class="capital-highlight">
                            <div class="capital-label">Capital Required</div>
                            <div class="capital-value"><?= formatAmount($package['capital']) ?></div>
                        </div>

                        <div class="package-details">
                            <div class="detail-item">
                                <div class="detail-label">Daily Profit</div>
                                <div class="detail-value return-highlight"><?= formatAmount($package['daily_profit']) ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Duration</div>
                                <div class="detail-value"><?= htmlspecialchars($package['duration'], ENT_QUOTES, 'UTF-8') ?> Days</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Daily Orders</div>
                                <div class="detail-value"><?= htmlspecialchars($package['orders'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Total Profit</div>
                                <div class="detail-value return-highlight"><?= formatAmount($package['total_profit']) ?></div>
                            </div>
                        </div>

                        <div class="profit-summary">
                            <div class="profit-label">Total Return</div>
                            <div class="profit-value"><?= formatAmount($package['total_return']) ?></div>
                        </div>

                        <button class="invest-btn" onclick="selectPackage(<?= $package['id'] ?>)">
                            Start Working
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function selectPackage(packageId) {
            const packagePages = {
                1: 'bronze.php',
                2: 'silver.php', 
                3: 'gold.php',
                4: 'platinum.php',
                5: 'diamond.php',
                6: 'ruby.php',
                7: 'sapphire.php',
                8: 'emerald.php',
                9: 'topaz.php',
                10: 'crown.php',
                11: 'legend.php'
            };

            if (packagePages[packageId]) {
                window.location.href = packagePages[packageId];
            }
        }
    </script>
</body>
</html>