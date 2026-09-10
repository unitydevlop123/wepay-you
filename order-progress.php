<?php require_once 'firebase_setup.php'; ?>
<?php
// Include authentication system
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Require login before accessing order progress
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Get current authenticated user data from Firebase
$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: login.php');
    exit();
}

// Include the auto-order system for commission calculation
require_once 'order-auto-update.php';

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
$selected_package = $_SESSION['selected_package'] ?? $_GET['package'] ?? 'Bronze';

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

// Update session with current package
if ($selected_package) {
    $_SESSION['selected_package'] = $selected_package;
}

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
    <?php 
    // No auto-refresh on order progress page - Users need time to complete orders
    ?>
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
            overflow: hidden;
            max-width: 100%;
        }

        .product-display {
            display: flex;
            gap: 25px;
            align-items: flex-start;
            flex-wrap: nowrap;
            overflow: hidden;
        }

        .product-image-container {
            width: 150px !important;
            height: 150px !important;
            flex-shrink: 0 !important;
            overflow: hidden !important;
            border-radius: 12px;
            position: relative !important;
        }

        .product-image {
            width: 150px !important;
            height: 150px !important;
            border-radius: 12px;
            object-fit: cover !important;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            max-width: 150px !important;
            max-height: 150px !important;
            min-width: 150px !important;
            min-height: 150px !important;
            display: block !important;
            flex-shrink: 0 !important;
            overflow: hidden !important;
            position: relative !important;
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

            .product-image-container {
                width: 120px !important;
                height: 120px !important;
                margin: 0 auto !important;
            }

            .product-image {
                width: 120px !important;
                height: 120px !important;
                max-width: 120px !important;
                max-height: 120px !important;
                min-width: 120px !important;
                min-height: 120px !important;
                object-fit: cover !important;
                display: block !important;
                overflow: hidden !important;
            }

            .complete-btn {
                padding: 15px 35px;
                font-size: 1rem;
            }

            .product-section {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <a href="orders.php<?= $selected_package ? '?selected_package=' . urlencode($selected_package) : '' ?>" class="back-btn">← Back</a>
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
                    <div class="product-image-container">
                        <img src="<?= htmlspecialchars($current_product['real_image'] ?? 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400') ?>" alt="<?= htmlspecialchars($current_product['name']) ?>" class="product-image" onerror="this.src='https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400'">
                    </div>
                    <div class="product-details">
                        <h3><?= htmlspecialchars($current_product['real_name']) ?></h3>
                        <p class="product-category"><?= htmlspecialchars($current_product['category']) ?></p>
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
            // Store commission value for hosting compatibility
            const commissionAmount = <?= json_encode($order_commission) ?>;

            // DIRECT FORM SUBMISSION - No AJAX, no iframe - Pure hosting compatible
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'orders.php'; // Direct redirect to orders page
            form.style.display = 'none';

            // Add completion action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'complete_order_direct';
            form.appendChild(actionInput);

            const commissionInput = document.createElement('input');
            commissionInput.type = 'hidden';
            commissionInput.name = 'commission';
            commissionInput.value = commissionAmount;
            form.appendChild(commissionInput);

            const completedInput = document.createElement('input');
            completedInput.type = 'hidden';
            completedInput.name = 'order_completed';
            completedInput.value = '1';
            form.appendChild(completedInput);

            // Include selected package info
            const selectedPackage = '<?= htmlspecialchars($selected_package ?? '') ?>';
            if (selectedPackage) {
                const packageInput = document.createElement('input');
                packageInput.type = 'hidden';
                packageInput.name = 'selected_package';
                packageInput.value = selectedPackage;
                form.appendChild(packageInput);
            }

            document.body.appendChild(form);

            // Show immediate success message before redirect
            const statusMessage = document.querySelector('.status-message');
            statusMessage.className = 'status-message';
            statusMessage.innerHTML = `
                <h4>✅ Order Completed Successfully!</h4>
                <p>Commission ₦${parseFloat(commissionAmount).toFixed(2)} earned!</p>
                <p><strong>Updating progress...</strong></p>
            `;

            // Submit form directly - no iframe needed
            setTimeout(() => {
                form.submit();
            }, 500);
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

        // No automatic refresh on order progress page
        function checkAutoProcessing() {
            console.log('Order progress - manual completion system');
            // Users control when to complete orders
        }

        // No automatic monitoring needed
        function startAutoProcessingMonitor() {
            console.log('Manual order completion - no auto-refresh');
            // Users click to complete when ready
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

        // PHP handles completion detection on page refresh
        function checkForAutoProcessing() {
            console.log('Order completion handled by PHP refresh system');
            // No AJAX fetch needed - PHP handles this
        }

        // PHP meta refresh handles automatic updates - no setInterval needed
        console.log('PHP refresh system active - Free hosting compatible');
    </script>
</body>
</html>