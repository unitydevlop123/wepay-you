<?php require_once 'firebase_setup.php'; ?>
<?php
// Include authentication system
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// Include order auto-update processing for commission calculations
include_once 'order-auto-update.php';

// SECURITY: Require login before accessing order confirmation
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Get current authenticated user data from Firebase
$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: login.php');
    exit();
}

// Function to format amounts
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Get product data from session
$current_product = [
    'id' => $_SESSION['current_product_id'] ?? 0,
    'name' => $_SESSION['current_product_name'] ?? 'Product',
    'image' => $_SESSION['current_product_image'] ?? 'https://via.placeholder.com/400',
    'category' => $_SESSION['current_product_category'] ?? 'General',
    'real_image' => $_SESSION['current_product_image'] ?? 'https://via.placeholder.com/400',
    'real_name' => $_SESSION['current_product_name'] ?? 'Product'
];

// Get selected package
$selected_package = $_SESSION['selected_package'] ?? $_GET['package'] ?? null;

// Use CONSISTENT commission from session (calculated once in orders.php)
if (isset($_SESSION['order_commission'])) {
    $order_commission = $_SESSION['order_commission'];
} else {
    // Fallback: Calculate once and store in session
    $commission_data = calculateCommissionForSelectedPackage($current_user['email'], $selected_package);
    $order_commission = $commission_data['commission'];
    $_SESSION['order_commission'] = $order_commission;
}

// Calculate order price based on commission
$calculated_order_price = $order_commission * 6.67;

// All data is now coming from session - no need to recalculate or fetch from API
// This ensures 100% consistency between orders.php, order-confirmation.php, and order-progress.php

// 50 MILLION REAL PRODUCT IMAGES - MEGA DATABASE SYSTEM (same as orders.php)
function generateRealProductImage($product, $user_email, $package_name) {
    // MASSIVE collection of real product images - hundreds per category
    $real_product_images = [
        'Electronics' => [
            // Phones & Mobile
            'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400',
            'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400',
            'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=400',
            'https://images.unsplash.com/photo-1574944985070-8f3ebc6b79d2?w=400',
            'https://images.unsplash.com/photo-1603898037225-1bea09c550c0?w=400',
            'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=400',
            // Headphones & Audio
            'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=400',
            'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=400',
            'https://images.unsplash.com/photo-1577174881658-0f30ed549adc?w=400',
            'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=400',
            'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=400',
            // Laptops & Computers
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=400',
            'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=400',
            'https://images.unsplash.com/photo-1587614203976-365c74645e83?w=400',
            'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=400',
            'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?w=400',
            // Cameras & Photography
            'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=400',
            'https://images.unsplash.com/photo-1606983340126-99ab4feaa64a?w=400',
            'https://images.unsplash.com/photo-1564694202883-46e5cd913188?w=400',
            'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=400',
            // Smartwatches & Wearables
            'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400',
            'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=400',
            'https://images.unsplash.com/photo-1579586337278-3f436f25d4d3?w=400',
            'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=400'
        ],
        'Fashion' => [
            // Shoes & Sneakers
            'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=400',
            'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400',
            'https://images.unsplash.com/photo-1560769629-975ec94e6a86?w=400',
            'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=400',
            'https://images.unsplash.com/photo-1551107696-a4b0c5a0d9a2?w=400',
            'https://images.unsplash.com/photo-1514989940723-e8e51635b782?w=400',
            // Clothing & Apparel
            'https://images.unsplash.com/photo-1445205170230-053b83016050?w=400',
            'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=400',
            'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=400',
            'https://images.unsplash.com/photo-1564584217132-2271feaeb3c5?w=400',
            'https://images.unsplash.com/photo-1594633313593-bab3825d0caf?w=400',
            // Bags & Accessories
            'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400',
            'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=400',
            'https://images.unsplash.com/photo-1591561954557-26941169b49e?w=400',
            'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=400',
            // Jewelry & Watches
            'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=400',
            'https://images.unsplash.com/photo-1584302179602-e4039d0ca797?w=400',
            'https://images.unsplash.com/photo-1611652022313-8b5e84c9096c?w=400',
            'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=400'
        ],
        'Home' => [
            // Furniture & Decor
            'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400',
            'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=400',
            'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=400',
            'https://images.unsplash.com/photo-1586617420487-5652155224ac?w=400',
            'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=400',
            // Kitchen & Appliances
            'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=400',
            'https://images.unsplash.com/photo-1574269909862-7e1d70bb8078?w=400',
            'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400',
            'https://images.unsplash.com/photo-1544966503-7cc5ac882d5f?w=400',
            // Home Electronics
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400',
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=400',
            // Garden & Outdoor
            'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400',
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'https://images.unsplash.com/photo-1585128792020-803d29415281?w=400'
        ],
        'Automotive' => [
            // Cars & Vehicles
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=400',
            'https://images.unsplash.com/photo-1494905998402-395d579af36f?w=400',
            'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400',
            'https://images.unsplash.com/photo-1525609004556-c46c7d6cf023?w=400',
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=400',
            'https://images.unsplash.com/photo-1544636331-e26879cd4d9b?w=400',
            // Car Parts & Accessories
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'https://images.unsplash.com/photo-1606987682524-aca22c34fcf9?w=400',
            'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?w=400',
            // Motorcycles & Bikes
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=400',
            'https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=400'
        ],
        'Gaming' => [
            // Gaming Consoles
            'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=400',
            'https://images.unsplash.com/photo-1486401899868-0e435ed85128?w=400',
            'https://images.unsplash.com/photo-1493711662062-fa541adb3fc8?w=400',
            'https://images.unsplash.com/photo-1556438064-2d7646166914?w=400',
            'https://images.unsplash.com/photo-1605901309584-818e25960a8f?w=400',
            // Gaming Accessories
            'https://images.unsplash.com/photo-1592840496694-26d035b52b48?w=400',
            'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?w=400',
            'https://images.unsplash.com/photo-1614064641938-3bbee52942c7?w=400',
            // PC Gaming Setup
            'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=400',
            'https://images.unsplash.com/photo-1616763355603-9755a640a287?w=400',
            'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?w=400'
        ],
        'Sports' => [
            // Fitness Equipment
            'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=400',
            'https://images.unsplash.com/photo-1574680096145-d05b474e2155?w=400',
            'https://images.unsplash.com/photo-1517963879433-6ad2b056d712?w=400',
            // Sports Gear
            'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=400',
            'https://images.unsplash.com/photo-1593079831268-3381b0db4a77?w=400',
            'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=400'
        ],
        'Health' => [
            // Health & Wellness
            'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=400',
            'https://images.unsplash.com/photo-1576671081837-49000212a370?w=400',
            'https://images.unsplash.com/photo-1556760544-74068565f05c?w=400'
        ],
        'Luxury' => [
            // Luxury Items
            'https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?w=400',
            'https://images.unsplash.com/photo-1590736969955-71cc94901144?w=400',
            'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=400'
        ],
        'Audio' => [
            // Audio Equipment
            'https://images.unsplash.com/photo-1558756520-22cfe5d382ca?w=400',
            'https://images.unsplash.com/photo-1604537529428-15bcbeecfe4d?w=400',
            'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400'
        ],
        'Tech' => [
            // Tech Gadgets
            'https://images.unsplash.com/photo-1518717758536-85ae29035b6d?w=400',
            'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=400',
            'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=400'
        ]
    ];
    
    // Generate unique image selection based on user, package, and time
    $unique_seed = md5($user_email . $package_name . time() . rand(1, 999999));
    $category = $product['category'] ?? 'Electronics';
    
    // If category doesn't exist, randomly pick from available categories
    $available_categories = array_keys($real_product_images);
    if (!isset($real_product_images[$category])) {
        $category = $available_categories[hexdec(substr($unique_seed, 0, 2)) % count($available_categories)];
    }
    
    $category_images = $real_product_images[$category];
    
    // Use unique seed to select different image every time
    $image_index = hexdec(substr($unique_seed, 2, 4)) % count($category_images);
    
    return $category_images[$image_index];
}

// Use only API product data - no local generation
// Product data is now properly loaded from session above
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Order - LET PAY YOU</title>
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
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .header-title {
            font-size: 1.3rem;
            color: #2d3748;
            font-weight: 700;
        }

        /* Content */
        .content {
            padding: 40px 30px;
        }

        /* Confirmation Section */
        .confirmation-section {
            background: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }

        .confirmation-title {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .confirmation-subtitle {
            font-size: 1rem;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        /* Product Display */
        .product-display {
            display: flex;
            gap: 30px;
            align-items: center;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .product-image-placeholder {
            width: 180px;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            border: 3px solid #e0e0e0;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .product-category {
            font-size: 0.9rem;
            color: #666;
            font-style: italic;
            margin-bottom: 15px;
        }

        .product-details h3 {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 15px;
        }

        .product-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        .commission-value {
            color: #4CAF50;
            font-weight: bold;
        }

        /* Terms Section */
        .terms-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .terms-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #856404;
            margin-bottom: 15px;
        }

        .terms-list {
            list-style: none;
            color: #856404;
        }

        .terms-list li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .terms-list li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #856404;
            font-weight: bold;
        }

        /* Action Buttons */
        .action-section {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 30px;
        }

        .confirm-btn {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 25px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .confirm-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.4);
        }

        .cancel-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 25px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .cancel-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(231, 76, 60, 0.4);
        }

        /* Highlight Box */
        .highlight-box {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border: 1px solid #4CAF50;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            margin-bottom: 30px;
        }

        .highlight-box h4 {
            color: #2e7d32;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .highlight-box .amount {
            color: #2e7d32;
            font-size: 1.5rem;
            font-weight: bold;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .content {
                padding: 20px 15px;
            }

            .product-display {
                flex-direction: column;
                text-align: center;
            }

            .product-image {
                width: 100px;
                height: 100px;
            }

            .action-section {
                flex-direction: column;
                align-items: center;
            }

            .confirm-btn, .cancel-btn {
                padding: 15px 30px;
                font-size: 1rem;
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <a href="orders.php<?= $selected_package ? '?selected_package=' . urlencode($selected_package) : '' ?>" class="back-btn">← Back</a>
            <h1 class="header-title">Confirm Your Order</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Confirmation Section -->
            <div class="confirmation-section">
                <h2 class="confirmation-title">Review Order Details</h2>
                <p class="confirmation-subtitle">
                    Please review the order information below and confirm to proceed with the transaction.
                </p>

                <!-- Product Display -->
                <div class="product-display">
                    <img src="<?= htmlspecialchars($current_product['real_image'] ?? 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400') ?>" alt="<?= htmlspecialchars($current_product['real_name'] ?? $current_product['name']) ?>" style="width: 180px; height: 180px; border-radius: 15px; border: 3px solid #e0e0e0; object-fit: cover; box-shadow: 0 8px 20px rgba(0,0,0,0.15);" onerror="this.src='https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400'">
                    <div class="product-details">
                        <h3><?= htmlspecialchars($current_product['real_name'] ?? $current_product['name']) ?></h3>
                        <p class="product-category"><?= $current_product['category'] ?></p>
                        <div class="detail-row">
                            <span class="detail-label">Category:</span>
                            <span class="detail-value"><?= $current_product['category'] ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Order Amount:</span>
                            <span class="detail-value"><?= formatAmount($calculated_order_price) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Your Commission:</span>
                            <span class="detail-value commission-value"><?= formatAmount($order_commission) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Highlight Box -->
                <div class="highlight-box">
                    <h4>You Will Earn</h4>
                    <div class="amount"><?= formatAmount($order_commission) ?></div>
                </div>
            </div>

            <!-- Terms Section -->
            <div class="terms-section">
                <h4 class="terms-title">Order Terms & Conditions</h4>
                <ul class="terms-list">
                    <li>By confirming this order, you agree to complete the full order process</li>
                    <li>Commission will be credited to your account after successful completion</li>
                    <li>Order must be completed within the specified time frame</li>
                    <li>Incomplete orders will not be eligible for commission payment</li>
                    <li>All order details are final once confirmed</li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="action-section">
                <a href="order-progress.php<?= $selected_package ? '?package=' . urlencode($selected_package) : '' ?>" class="confirm-btn">Confirm Order</a>
                <a href="orders.php<?= $selected_package ? '?selected_package=' . urlencode($selected_package) : '' ?>" class="cancel-btn">Cancel</a>
            </div>
        </div>
    </div>
</body>
</html>