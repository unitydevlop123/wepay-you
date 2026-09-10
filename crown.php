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

// Load package data from Firebase
$package_data = readJsonFile('crown');

// Debug: Log what we get from Firebase
if ($package_data) {
    error_log("CROWN DEBUG: Firebase data loaded, image: " . ($package_data['image'] ?? 'no image'));
} else {
    error_log("CROWN DEBUG: No Firebase data, using fallback");
}

// Always use the correct centralized image path regardless of Firebase data
if ($package_data) {
    // Override the image with the correct centralized path
    $package_data['image'] = 'attached_assets/attached_assets/generated_images/Executive_dashboard_presentation_6cb8c8f8.png';
    error_log("CROWN DEBUG: Overriding image path to: " . $package_data['image']);
} else {
    // Fallback data with beautiful business celebration image
    $package_data = [
        'name' => 'Crown',
        'capital' => 100000,
        'daily_profit' => 10000,
        'duration' => 45,
        'orders' => 150,
        'total_profit' => 450000,
        'total_return' => 550000,
        'description' => 'Premium Crown package with excellent returns.',
        'image' => 'attached_assets/attached_assets/generated_images/Executive_dashboard_presentation_6cb8c8f8.png',
        'id' => 10,
        'popular' => false
    ];
}

// Get user balance and check if package already purchased
$user_balance = 0.00;
$already_purchased = false;
$package_expiry = null;
$current_user_email = ''; // Initialize variable for current user's email

if (isset($_SESSION['email'])) {
    $users = getUsers();
    if (!empty($users)) {
        foreach ($users as $user) {
            if ($user['email'] === $_SESSION['email']) {
                $current_user_email = $user['email']; // Set current user's email
                $user_balance = (float)($user['balance'] ?? 0.00);
                $user_data = $user; // Store user data for easier access

                // Check active packages for this package type
                $active_packages = $user['active_packages'] ?? [];
                foreach ($active_packages as $active_package) {
                    // Assuming $package_data['name'] is dynamically set for each package page.
                    // For this specific change, we are targeting 'Crown'.
                    if ($active_package['name'] === $package_data['name']) {
                        $expiry_date = new DateTime($active_package['expiry_date']);
                        $today = new DateTime();

                        if ($expiry_date > $today) {
                            $already_purchased = true;
                            $package_expiry = $active_package['expiry_date'];
                        }
                    }
                }
                break;
            }
        }
    }
}

// Determine if the user can afford the package and has not already purchased it.
$can_afford = $user_balance >= ($package_data['capital'] ?? 0) && !$already_purchased;

if (!empty($_POST['action'])) {
    if ($_POST['action'] === 'purchase' && $can_afford) {
        $users = getUsers();
        for ($i = 0; $i < count($users); $i++) {
            if ($users[$i]['email'] === $_SESSION['email']) {
                // Deduct from main balance (dashboard wallet)
                $users[$i]['balance'] = ($users[$i]['balance'] ?? 0) - $package_data['capital'];

                // Add new package to active packages
                $expiry_date = date('Y-m-d', strtotime('+' . $package_data['duration'] . ' days'));
                $users[$i]['active_packages'][] = [
                    'name' => $package_data['name'],
                    'id' => $package_data['id'],
                    'purchased_date' => date('Y-m-d'),
                    'expiry_date' => $expiry_date,
                    'daily_orders' => $package_data['orders'],
                    'daily_profit' => $package_data['daily_profit'],
                    'orders_completed_today' => 0,
                    'commission_today' => 0
                ];



                // Optional: Remove old single package entry if it exists, to avoid conflicts
                if (isset($users[$i]['active_package'])) {
                    unset($users[$i]['active_package']);
                }
                if (isset($users[$i]['package_id'])) {
                    unset($users[$i]['package_id']);
                }

                break; // Exit loop once user is found and updated
            }
        }
        saveUsers($users);

        // Add purchase notification
        $notification = [
            'id' => 'purchase_' . time() . '_' . rand(1000, 9999),
            'type' => 'package_purchased',
            'title' => 'Package Purchased!',
            'message' => "🎉 You've successfully purchased the {$package_data['name']} package for ₦" . number_format($package_data['capital'], 2) . ". Start completing orders to earn daily profits!",
            'amount' => 0,
            'status' => 'unread',
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null
        ];

        // Add notification using Firebase function
        addUserNotification($current_user_email, $notification);


        header('Location: dashboard.php?success=package_purchased');
        exit;
    } else if ($_POST['action'] === 'purchase' && !$can_afford && !$already_purchased) {
        // Redirect with error if user cannot afford
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=insufficient_balance');
        exit;
    } else if ($_POST['action'] === 'purchase' && $already_purchased) {
        // Redirect with error if already purchased
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=already_purchased');
        exit;
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
    <title><?= $package_data['name'] ?> Package - LET PAY YOU</title>
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
            max-width: 900px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
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
            height: 25px;
        }

        .logo-section h1 {
            color: #2d3748;
            font-size: 1rem;
            font-weight: 700;
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

        /* Banner Image */
        .banner-section {
            position: relative;
            height: 300px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .banner-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            background: transparent;
        }

        .banner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .banner-title {
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            text-shadow: 
                4px 4px 8px rgba(0,0,0,0.8),
                -2px -2px 4px rgba(255,255,255,0.3),
                0 0 30px rgba(255,255,255,0.4);
            background: linear-gradient(45deg, #ffffff, #f8f9fa, #e9ecef, #ffffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleShine 3s ease-in-out infinite alternate;
        }

        /* Content */
        .content {
            padding: 40px 30px;
        }

        .package-details {
            background: #f8f9ff;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .details-title {
            font-size: 1.2rem;
            color: #2d3748;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: #ffffff;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .detail-label {
            font-size: 0.9rem;
            color: #718096;
            margin-bottom: 8px;
        }

        .detail-value {
            font-size: 1rem;
            font-weight: 700;
            color: #000000;
        }

        .highlight-value {
            color: #10b981;
        }

        /* Warning Section */
        .warning-section {
            background: #fff5f5;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            border-left: 5px solid #f56565;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .warning-title {
            color: #c53030;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .warning-text {
            color: #742a2a;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* Confirm Button */
        .confirm-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .confirm-btn {
            background: #10b981;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
        }

        .confirm-btn:hover {
            background: #059669;
        }

        .info-section {
            background: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .info-title {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 700;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: #f8f9ff;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }

        .info-item h4 {
            color: #667eea;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .info-item p {
            color: #4a5568;
            font-size: 1rem;
            line-height: 1.5;
        }

        /* Animations */
        @keyframes crownGlow {
            0% {
                text-shadow: 
                    2px 2px 4px rgba(0,0,0,0.3),
                    -1px -1px 2px rgba(255,255,255,0.8),
                    0 0 15px rgba(102, 126, 234, 0.2);
            }
            100% {
                text-shadow: 
                    2px 2px 4px rgba(0,0,0,0.4),
                    -1px -1px 2px rgba(255,255,255,1),
                    0 0 25px rgba(102, 126, 234, 0.4);
            }
        }

        @keyframes titleShine {
            0% {
                text-shadow: 
                    4px 4px 8px rgba(0,0,0,0.8),
                    -2px -2px 4px rgba(255,255,255,0.3),
                    0 0 30px rgba(255,255,255,0.4);
            }
            100% {
                text-shadow: 
                    4px 4px 8px rgba(0,0,0,0.8),
                    -2px -2px 4px rgba(255,255,255,0.6),
                    0 0 50px rgba(255,255,255,0.7);
            }
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .content {
                padding: 20px 15px;
            }

            .banner-title {
                font-size: 2rem;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .confirm-btn {
                padding: 18px 40px;
                font-size: 1.1rem;
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
            <a href="invest.php" class="back-btn">Back to Packages</a>
        </div>

        <!-- Banner -->
        <div class="banner-section">
            <img src="<?= $package_data['image'] ?>" alt="<?= $package_data['name'] ?> Package" class="banner-image">
            <div class="banner-overlay">
                <h1 class="banner-title"><?= $package_data['name'] ?> Package</h1>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Package Details -->
            <div class="package-details">
                <h2 class="details-title">Package Details</h2>
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Capital Required</div>
                        <div class="detail-value highlight-value"><?= formatAmount($package_data['capital']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Daily Profit</div>
                        <div class="detail-value highlight-value"><?= formatAmount($package_data['daily_profit']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value"><?= $package_data['duration'] ?> Days</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Daily Orders</div>
                        <div class="detail-value"><?= $package_data['orders'] ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Profit</div>
                        <div class="detail-value highlight-value"><?= formatAmount($package_data['total_profit']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Return</div>
                        <div class="detail-value highlight-value"><?= formatAmount($package_data['total_return']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Confirm Investment Button -->
            <div class="confirm-section">
                <?php if ($already_purchased): ?>
                    <button class="confirm-btn" disabled style="background-color: #9ca3af;">Already Purchased</button>
                    <p style="margin-top: 10px; color: #6b7280;">Expires on: <?= $package_expiry ?></p>
                <?php elseif ($user_balance < $package_data['capital']): ?>
                    <button class="confirm-btn" disabled style="background-color: #f87171;">Insufficient Balance</button>
                    <p style="margin-top: 10px; color: #6b7280;">Your balance: <?= formatAmount($user_balance) ?></p>
                <?php else: ?>
                    <button class="confirm-btn" id="purchaseBtn" onclick="confirmInvestment()">
                        Secure Your Investment Now
                    </button>
                <?php endif; ?>
            </div>

            <!-- How It Works -->
            <div class="info-section">
                <h3 class="info-title">How It Works</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <h4>Daily Orders</h4>
                        <p>Complete <?= $package_data['orders'] ?> simple orders daily to earn <?= formatAmount($package_data['daily_profit']) ?></p>
                    </div>
                    <div class="info-item">
                        <h4>Work Time</h4>
                        <p>Each order takes 30 minutes. Work from your phone or computer anywhere</p>
                    </div>
                    <div class="info-item">
                        <h4>Payment</h4>
                        <p>Earn daily profits automatically. Consistency is key for <?= $package_data['duration'] ?> days</p>
                    </div>
                </div>
            </div>

            <!-- Important Warning -->
            <div class="warning-section">
                <h3 class="warning-title">⚠️ Important Notice</h3>
                <p class="warning-text">
                    You MUST complete ALL daily orders every day to maintain your investment. If you miss any day or fail to complete your required orders, you will lose your earnings for that specific day only. We are not responsible for lost daily earnings due to incomplete tasks. Consistency and daily participation is mandatory.
                </p>
            </div>

        </div>
    </div>

        <script>
        let isProcessing = false;

        function confirmInvestment() {
            // Check if already purchased or insufficient balance
            <?php if ($already_purchased): ?>
                alert('You have already purchased this package. It expires on <?= htmlspecialchars($package_expiry) ?>');
                return;
            <?php elseif (!$can_afford): ?>
                alert('Insufficient balance to purchase this package.');
                return;
            <?php endif; ?>

            if (isProcessing) {
                alert('Please wait... Your previous request is still being processed.');
                return;
            }

            if (confirm('Are you sure you want to invest in the <?= htmlspecialchars($package_data['name']) ?> package for <?= formatAmount($package_data['capital']) ?>?\n\nThis will be deducted from your wallet balance.')) {
                startPurchaseProcess();
            }
        }

        function startPurchaseProcess() {
            isProcessing = true;
            const btn = document.getElementById('purchaseBtn');

            // Disable button immediately
            btn.disabled = true;
            btn.style.cursor = 'not-allowed';
            btn.style.opacity = '0.7';

            // Step 1: Waiting Agent (5 seconds)
            btn.innerHTML = '⏳ Waiting Agent...';
            btn.style.background = '#FFA726';

            setTimeout(() => {
                // Step 2: Agent Reply (5 seconds)  
                btn.innerHTML = '📞 Agent Reply...';
                btn.style.background = '#FF7043';

                setTimeout(() => {
                    // Step 3: Processing (5 seconds)
                    btn.innerHTML = '⚙️ Processing...';
                    btn.style.background = '#42A5F5';

                    setTimeout(() => {
                        // Step 4: Purchasing (5 seconds)
                        btn.innerHTML = '💳 Purchasing...';
                        btn.style.background = '#AB47BC';

                        setTimeout(() => {
                            // NOW CHECK BALANCE AFTER LOADING IS DONE
                            const userBalance = <?= $user_balance ?>;
                            const packageCost = <?= $package_data['capital'] ?>;

                            if (userBalance < packageCost) {
                                // Insufficient balance - SHOW POPUP ALERT
                                alert('Agent couldn\'t purchase <?= htmlspecialchars($package_data['name']) ?> plan due to insufficient balance, please deposit now to complete your purchase');

                                // Reset button immediately
                                btn.innerHTML = 'Secure Your Investment Now';
                                btn.style.background = '';
                                btn.disabled = false;
                                btn.style.cursor = 'pointer';
                                btn.style.opacity = '1';
                                isProcessing = false;
                            } else {
                                // Step 5: Success message
                                btn.innerHTML = '✅ Congratulations successful, please complete your order in your order page thank you';
                                btn.style.background = '#10b981';
                                btn.style.opacity = '1';

                                setTimeout(() => {
                                    // Complete purchase and redirect
                                    const form = document.createElement('form');
                                    form.method = 'POST';
                                    form.innerHTML = '<input type="hidden" name="action" value="purchase">';
                                    document.body.appendChild(form);
                                    form.submit();
                                }, 3000);
                            }
                        }, 5000);
                    }, 5000);
                }, 5000);
            }, 5000);
        }
    </script>
</body>
</html>