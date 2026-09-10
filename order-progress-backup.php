<?php
session_start();

// Set testing user session for demo (remove when login system is complete)
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'testing@example.com';
    $_SESSION['user'] = [
        'name' => 'Testing User',
        'email' => 'testing@example.com',
        'id' => '68a70b922f5fe'
    ];
    $_SESSION['user_id'] = '68a70b922f5fe';
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
}

// Function to format amounts
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Load products from larger database (same as orders.php)
$products_file = file_exists('backend/storage/products_large.json') ? 
    'backend/storage/products_large.json' : 
    'backend/storage/products.json';
$products = json_decode(file_get_contents($products_file), true);

// Use same product and pricing from session with safety checks
$current_product_id = $_SESSION['current_product_id'] ?? 0;
$random_product = isset($products[$current_product_id]) ? $products[$current_product_id] : $products[0];
$calculated_order_price = $_SESSION['calculated_order_price'] ?? 50000;
$order_commission = $_SESSION['order_commission'] ?? 7.50;

// 50 MILLION REAL PRODUCT IMAGES - MEGA DATABASE SYSTEM (same as orders.php)
function generateRealProductImage($product, $user_email, $package_name) {
    // DYNAMIC IMAGE GENERATION - Creates truly unique images every time
    // Generate completely unique URLs using multiple variables
    
    $product_category = $product['category'] ?? 'Electronics';
    
    // Create unique seed from multiple sources
    $unique_time = time() + rand(1, 999999);
    $unique_user_seed = md5($user_email . $package_name . microtime());
    $unique_session_id = uniqid() . rand(100000, 999999);
    
    // Category keywords for different product types
    $category_keywords = [
        'Electronics' => ['tech', 'gadget', 'device', 'electronics', 'digital', 'smart', 'modern', 'innovation'],
        'Fashion' => ['style', 'fashion', 'trendy', 'elegant', 'chic', 'designer', 'luxury', 'boutique'],
        'Food' => ['delicious', 'gourmet', 'fresh', 'tasty', 'organic', 'premium', 'artisan', 'culinary'],
        'Home' => ['cozy', 'elegant', 'modern', 'stylish', 'comfort', 'luxury', 'designer', 'premium'],
        'Beauty' => ['beauty', 'glow', 'radiant', 'luxury', 'premium', 'elegant', 'sophisticated', 'glamour']
    ];
    
    // Map categories
    $mapped_category = match($product_category) {
        'Electronics' => 'Electronics',
        'Fashion' => 'Fashion', 
        'Food' => 'Food',
        'Home' => 'Home',
        'Beauty', 'Health' => 'Beauty',
        default => 'Electronics'
    };
    
    $keywords = $category_keywords[$mapped_category];
    $keyword = $keywords[hexdec(substr($unique_user_seed, 0, 4)) % count($keywords)];
    
    // Generate unique dimensions
    $width = 400 + (hexdec(substr($unique_session_id, 0, 3)) % 400);
    $height = 300 + (hexdec(substr($unique_session_id, 3, 3)) % 300);
    
    // Create completely unique signature
    $signature = substr(md5($unique_time . $unique_session_id . $keyword), 0, 12);
    
    // Return dynamic Unsplash URL with unique parameters
    return "https://source.unsplash.com/{$width}x{$height}/?{$keyword},{$mapped_category}&sig={$signature}&t={$unique_time}";
}

// LOCK IMAGE SYSTEM - Same image stays until order completion
// Only generate NEW image if no current image exists (first visit)
if (!isset($_SESSION['current_product_image'])) {
    // First time - generate new image and lock it
    $user_email = $_SESSION['email'] ?? 'default';
    $_SESSION['current_product_image'] = generateRealProductImage($random_product, $user_email, 'bronze');
}

// Always use the locked image until order is completed successfully
$random_product['real_image'] = $_SESSION['current_product_image'];

// Order tracking states - all start as incomplete  
$order_states = [
    ['title' => 'Order matching successful', 'completed' => false, 'step' => 1],
    ['title' => 'The buyer has placed an order', 'completed' => false, 'step' => 2],
    ['title' => 'Product preparation in progress', 'completed' => false, 'step' => 3],
    ['title' => 'Order completed successfully', 'completed' => false, 'step' => 4, 'manual' => true]
];
            'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=400',
            'https://images.unsplash.com/photo-1556905055-8f358a74b7b2?w=400',
            'https://images.unsplash.com/photo-1564584217132-2271feaeb3c5?w=400',
            'https://images.unsplash.com/photo-1594633313593-bab3825d0caf?w=400',
            // Bags & Accessories
            'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400',
            'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=400',
            'https://images.unsplash.com/photo-1591561954557-2c699693b49e?w=400',
            'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=400',
            // Jewelry & Watches
            'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=400',
            'https://images.unsplash.com/photo-1584302179602-7403d0ca797?w=400',
            'https://images.unsplash.com/photo-1611652020313-8b5e84c9096c?w=400',
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
            'https://images.unsplash.com/photo-1525609004556-c46c76c6cf23?w=400',
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
            'https://images.unsplash.com/photo-1576671081837-49000210a2a0?w=400',
            'https://images.unsplash.com/photo-1556760544-74068565f05c?w=400'
        ],
        'Luxury' => [
            // Luxury Items
            'https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?w=400',
            'https://images.unsplash.com/photo-1590736969955-71cc91901144?w=400',
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

    // Generate unique image selection - changes every visit to prevent repeating
    // Add session attempt counter to ensure different images on retry
    if (!isset($_SESSION['image_attempt_counter'])) {
        $_SESSION['image_attempt_counter'] = 0;
    }
    $_SESSION['image_attempt_counter']++;
    
    $unique_seed = md5($user_email . $package_name . time() . $_SESSION['image_attempt_counter'] . rand(1, 999999));
    $product_category = $product['category'] ?? 'Electronics';
    
    // Map product categories to image categories for better matching
    $category_mapping = [
        'Electronics' => 'Electronics',
        'Computers' => 'Electronics', // Laptops are in Electronics section
        'Gaming' => 'Gaming',
        'Audio' => 'Electronics', // Audio devices are in Electronics
        'Photography' => 'Electronics', // Cameras are in Electronics
        'Fashion' => 'Fashion',
        'Luxury' => 'Luxury',
        'Automotive' => 'Automotive',
        'Home' => 'Home',
        'Technology' => 'Tech',
        'Tech' => 'Tech'
    ];
    
    $category = $category_mapping[$product_category] ?? 'Electronics';

    // If category doesn't exist in images, use Electronics as fallback
    $available_categories = array_keys($real_product_images);
    if (!isset($real_product_images[$category])) {
        $category = 'Electronics';
    }

    $category_images = $real_product_images[$category];
    
    // Track used images to prevent repetition
    if (!isset($_SESSION['used_images'])) {
        $_SESSION['used_images'] = [];
    }
    
    // If all images in category have been used, reset the tracking
    if (count($_SESSION['used_images']) >= count($category_images)) {
        $_SESSION['used_images'] = [];
    }
    
    // Find an unused image
    $attempts = 0;
    do {
        $image_index = hexdec(substr($unique_seed, 2 + $attempts, 4)) % count($category_images);
        $selected_image = $category_images[$image_index];
        $attempts++;
    } while (in_array($selected_image, $_SESSION['used_images']) && $attempts < 10);
    
    // Mark this image as used
    $_SESSION['used_images'][] = $selected_image;
    
    return $selected_image;
}

// LOCK IMAGE SYSTEM - Same image stays until order completion
// Only generate NEW image if no current image exists (first visit)
if (!isset($_SESSION['current_product_image'])) {
    // First time - generate new image and lock it
    $user_email = $_SESSION['email'] ?? 'default';
    $_SESSION['current_product_image'] = generateRealProductImage($random_product, $user_email, 'bronze');
}

// Always use the locked image until order is completed successfully
$random_product['real_image'] = $_SESSION['current_product_image'];

// Order tracking states - all start as incomplete
$order_states = [
    ['title' => 'Order matching successful', 'completed' => false, 'step' => 1],
    ['title' => 'The buyer has placed an order', 'completed' => false, 'step' => 2],
    ['title' => 'Product preparation in progress', 'completed' => false, 'step' => 3],
    ['title' => 'Order completed successfully', 'completed' => false, 'step' => 4, 'manual' => true]
];

$is_completed = false; // Will be true when user completes the order
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Progress - LET PAY YOU</title>
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
            padding: 30px;
        }

        /* Order Timeline */
        .timeline-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .timeline-title {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .timeline {
            position: relative;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .timeline-icon.completed {
            background: #4CAF50;
            color: white;
        }

        .timeline-icon.pending {
            background: #FFA726;
            color: white;
        }

        .timeline-icon.waiting {
            background: #e0e0e0;
            color: #666;
        }

        .timeline-text {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }

        .timeline-text.completed {
            color: #4CAF50;
        }

        .timeline-text.pending {
            color: #FF9800;
        }

        /* Product Info */
        .product-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .product-display {
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .product-image {
            width: 150px;
            height: 150px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
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
            border-bottom: 1px solid #f0f0f0;
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

        /* Action Button */
        .action-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .complete-btn {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 18px 50px;
            border: none;
            border-radius: 25px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .complete-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.4);
        }

        .complete-btn.processing {
            background: #FFA726;
            cursor: not-allowed;
        }

        /* Status Messages */
        .status-message {
            background: #e8f5e8;
            border: 1px solid #4CAF50;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }

        .status-message.processing {
            background: #fff3e0;
            border-color: #FFA726;
        }

        .status-message h4 {
            color: #2e7d32;
            margin-bottom: 10px;
        }

        .status-message.processing h4 {
            color: #ef6c00;
        }

        .status-message p {
            color: #388e3c;
            line-height: 1.5;
        }

        .status-message.processing p {
            color: #f57c00;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #FFA726;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                width: 120px;
                height: 120px;
            }

            .complete-btn {
                padding: 15px 35px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <a href="orders.php" class="back-btn">← Back</a>
            <h1 class="header-title">Order Progress</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Status Message -->
            <div class="status-message <?= $is_completed ? '' : 'processing' ?>">
                <?php if (!$is_completed): ?>
                    <div class="loading"></div>
                    <h4>Processing Your Order</h4>
                    <p>Please wait while we process your order. This usually takes a few moments.</p>
                <?php else: ?>
                    <h4>Order Completed Successfully!</h4>
                    <p>Your commission has been added to your account balance.</p>
                <?php endif; ?>
            </div>

            <!-- Order Timeline -->
            <div class="timeline-section">
                <h3 class="timeline-title">Order Timeline</h3>
                <div class="timeline">
                    <?php foreach ($order_states as $index => $state): ?>
                        <div class="timeline-item" id="step-<?= $state['step'] ?>">
                            <div class="timeline-icon waiting" id="icon-<?= $state['step'] ?>">
                                <?= $state['step'] ?>
                            </div>
                            <div class="timeline-text" id="text-<?= $state['step'] ?>">
                                <?= $state['title'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="product-section">
                <div class="product-display">
                    <img src="<?= htmlspecialchars($random_product['real_image'] ?? 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400') ?>" alt="<?= htmlspecialchars($random_product['name']) ?>" style="width: 180px; height: 180px; border-radius: 15px; border: 3px solid #e0e0e0; object-fit: cover; box-shadow: 0 8px 20px rgba(0,0,0,0.15);" onerror="this.src='https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400'">
                    <div class="product-details">
                        <h3><?= $random_product['name'] ?></h3>
                        <p class="product-category"><?= $random_product['category'] ?></p>
                        <div class="detail-row">
                            <span class="detail-label">Order Amount:</span>
                            <span class="detail-value"><?= formatAmount($calculated_order_price) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Order Commission:</span>
                            <span class="detail-value commission-value"><?= formatAmount($order_commission) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value" id="order-status"><?= $is_completed ? 'Successful' : 'Processing' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Button -->
            <div class="action-section">
                <button class="complete-btn processing" id="main-action-btn" onclick="startProcessing()" style="display: none;">
                    <span class="loading"></span>
                    Processing Order...
                </button>
                <button class="complete-btn" id="confirm-btn" onclick="confirmOrder()" style="display: block;">
                    Confirm Order
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 0;
        let isProcessing = false;

        // Get elements for easier access
        const commissionDisplay = document.querySelector('.commission-value');
        const successMessage = document.querySelector('.status-message h4'); // Targeting h4 for message
        const successSection = document.querySelector('.status-message'); // Targeting the whole div

        function confirmOrder() {
            // Hide confirm button and show processing
            document.getElementById('confirm-btn').style.display = 'none';
            document.getElementById('main-action-btn').style.display = 'block';

            // Start the progressive loading
            startProgressiveLoading();
        }

        function startProgressiveLoading() {
            isProcessing = true;

            // Process each step with 1-second delays - very fast
            setTimeout(() => completeStep(1), 1000);  // First step after 1 second
            setTimeout(() => completeStep(2), 2000);  // Second step after 2 seconds total
            setTimeout(() => completeStep(3), 3000);  // Third step after 3 seconds total

            // After all automatic steps, show manual completion for step 4
            setTimeout(() => {
                document.getElementById('main-action-btn').innerHTML = 'Complete Order';
                document.getElementById('main-action-btn').onclick = function() { completeStep(4); };
                document.getElementById('main-action-btn').className = 'complete-btn';

                // Update status message for final step
                const statusMessage = document.querySelector('.status-message');
                statusMessage.innerHTML = `
                    <h4>Ready for Final Completion</h4>
                    <p>Click "Complete Order" to finish and receive your commission.</p>
                `;
            }, 4000); // Show final button after 4 seconds
        }

        function completeStep(step) {
            // Update the step icon and text
            const icon = document.getElementById(`icon-${step}`);
            const text = document.getElementById(`text-${step}`);

            icon.className = 'timeline-icon completed';
            icon.innerHTML = '✓';
            text.className = 'timeline-text completed';

            // Update status message based on step
            const statusMessage = document.querySelector('.status-message');

            if (step < 4) {
                statusMessage.innerHTML = `
                    <div class="loading"></div>
                    <h4>Processing Step ${step} of 4</h4>
                    <p>Please wait while we process your order. This usually takes a few moments.</p>
                `;
            } else {
                // Final completion - Send AJAX request to update user progress
                completeOrderInDatabase();

                // Hide action button and show immediate success
                document.getElementById('main-action-btn').style.display = 'none';
                
                // Show immediate success message
                const statusMessage = document.querySelector('.status-message');
                statusMessage.className = 'status-message';
                statusMessage.innerHTML = `
                    <h4>✅ Order Completed Successfully!</h4>
                    <p>Commission earned! Processing complete...</p>
                `;

                // Update the final step text and status
                text.innerHTML = 'Order completed successfully';
                text.className = 'timeline-text completed';

                // Update status text
                document.getElementById('order-status').innerHTML = 'Successful';
                document.getElementById('order-status').style.color = '#4CAF50';
                document.getElementById('order-status').style.fontWeight = 'bold';
            }
        }

        function completeOrderInDatabase() {
            // SMART AUTO-DETECTION SYSTEM - Write completion to tracker file
            // This connects to your auto-detection system
            fetch('write-completion-tracker.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=write_completion&commission=' + <?= $order_commission ?>
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Set flag that order is recorded
                    window.orderRecorded = true;

                    // Update commission display and success message
                    commissionDisplay.textContent = '₦' + parseFloat(<?= $order_commission ?>).toFixed(2);
                    successMessage.textContent = 'Order completion recorded - processing automatically!';
                    successSection.style.display = 'block';
                    
                    // Start monitoring for auto-processing completion
                    startAutoProcessingMonitor();

                    // Show success status message immediately
                    const statusMessage = document.querySelector('.status-message');
                    statusMessage.className = 'status-message'; // Remove processing class
                    statusMessage.innerHTML = `
                        <h4>✅ Order Completed Successfully!</h4>
                        <p>Commission ₦${parseFloat(<?= $order_commission ?>).toFixed(2)} earned! Redirecting...</p>
                    `;

                    // Order recorded successfully - start auto-redirect countdown
                    console.log('Order recorded, auto-redirecting to orders page...');
                    
                    // Update status to show auto-redirect
                    const statusMsg = document.querySelector('.status-message');
                    statusMsg.className = 'status-message success';
                    statusMsg.innerHTML = `
                        <h4>✅ Order Completed Successfully!</h4>
                        <p>Commission: ₦${parseFloat(<?= $order_commission ?>).toFixed(2)} earned!</p>
                        <p><strong>Redirecting to Orders page...</strong></p>
                    `;
                    
                    // Auto-redirect immediately with cache busting
                    setTimeout(() => {
                        window.location.href = 'orders.php?completed=1&refresh=1&t=' + Date.now();
                    }, 500);
                    
                } else {
                    console.error('Failed to record completion:', data.message);
                    // Handle error display if needed
                    const statusMessage = document.querySelector('.status-message');
                    statusMessage.className = 'status-message processing'; // Keep processing style for error
                    statusMessage.innerHTML = `
                        <h4>Error Recording Completion</h4>
                        <p>There was an issue recording your completion. Please try again later.</p>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Handle network errors
                const statusMessage = document.querySelector('.status-message');
                statusMessage.className = 'status-message processing'; // Keep processing style for error
                statusMessage.innerHTML = `
                    <h4>Network Error</h4>
                    <p>Could not connect to the server. Please check your connection and try again.</p>
                `;
            });
        }

        function startProcessing() {
            // This function is called when processing automatically starts
        }

        // Fix for continue button references to prevent errors
        function safeButtonUpdate(buttonId, disabled, text) {
            const btn = document.getElementById(buttonId);
            if (btn) {
                btn.disabled = disabled;
                if (text) btn.textContent = text;
            }
        }

        // Auto-processing monitor - Check if our completion was processed
        function checkAutoProcessing() {
            fetch('order-auto-update.php?_t=' + Date.now(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache'
                },
                body: 'action=check_completions&_cache=' + Math.random()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.updated && window.orderRecorded) {
                    // Our order was processed! Show success and redirect
                    const statusMessage = document.querySelector('.status-message');
                    statusMessage.className = 'status-message success';
                    statusMessage.innerHTML = `
                        <h4>🎉 Order Fully Processed!</h4>
                        <p>Your progress has been updated successfully!</p>
                        <p><strong>Returning to Orders page...</strong></p>
                    `;
                    
                    // Immediate redirect with success
                    setTimeout(() => {
                        window.location.href = 'orders.php?completed=1&instant=1&t=' + Date.now();
                    }, 500);
                }
            })
            .catch(error => {
                console.log('Auto-processing check complete');
            });
        }

        // Start monitoring after order is recorded
        function startAutoProcessingMonitor() {
            if (window.orderRecorded) {
                // Check every 1 second for instant processing
                const monitorInterval = setInterval(() => {
                    checkAutoProcessing();
                }, 1000);
                
                // Stop monitoring after 30 seconds max
                setTimeout(() => {
                    clearInterval(monitorInterval);
                    // Fallback redirect if auto-processing takes too long
                    if (window.orderRecorded) {
                        window.location.href = 'orders.php?completed=1&fallback=1&t=' + Date.now();
                    }
                }, 30000);
            }
        }

        // Auto-start the first step when page loads
        window.addEventListener('load', function() {
            // Set initial processing message
            const statusMessage = document.querySelector('.status-message');
            statusMessage.innerHTML = `
                <h4>Ready to Process Order</h4>
                <p>Click "Confirm Order" to begin processing your order.</p>
            `;
        });

        // Instant completion detection - ONLY after order is recorded
        function checkForAutoProcessing() {
            // Only check if we've already recorded our completion
            if (window.orderRecorded) {
                fetch('order-auto-update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=check_completions'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Force immediate reload for instant update
                        window.location.replace('orders.php?refresh=' + Date.now() + '&instant=1');
                    }
                })
                .catch(error => {
                    console.log('Auto-processing check complete');
                });
            }
        }

        // Check every 1 second for auto-processing (only after recording)
        setInterval(checkForAutoProcessing, 1000);
    </script>
</body>
</html>