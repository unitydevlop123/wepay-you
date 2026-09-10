<?php
// Include authentication system
require_once 'auth.php';

// Check if user is logged in and set appropriate back URL
$backUrl = 'login.php'; // Default to login
$userName = 'Guest';

if (isLoggedIn()) {
    // User is logged in, back button goes to dashboard
    $backUrl = 'dashboard.php';
    
    // Get user information from authenticated session
    $current_user_email = $_SESSION['email'];
    $userName = 'Elite Member';
    
    if (isset($_SESSION['user']['name'])) {
        $userName = $_SESSION['user']['name'];
    }
    
    // Get user data from Firebase
    $users = getUsers();
    if (!empty($users) && is_array($users)) {
        foreach ($users as $user) {
            if (is_array($user) && isset($user['email']) && $user['email'] === $current_user_email) {
                $userName = $user['name'] ?? 'Elite Member';
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
    <title>Support Center - LET PAY YOU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            overflow-x: hidden;
        }

        .main-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            position: relative;
            box-shadow: 0 0 50px rgba(0,0,0,0.1);
        }

        /* Header */
        .header {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo img {
            height: 40px;
        }

        .back-btn {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }

        .back-btn:hover {
            background: #0056b3;
        }

        /* Content Area */
        .content {
            padding: 25px 20px;
        }

        /* Page Title */
        .page-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .page-title p {
            color: #666;
            font-size: 1.1rem;
        }

        /* Support Team Section */
        .support-team {
            background: linear-gradient(135deg, #f8f9ff, #e6edff);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
            border: 1px solid #e0e7ff;
        }

        .team-photos {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .team-member {
            text-align: center;
        }

        .team-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid #667eea;
            object-fit: cover;
        }

        .team-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: #667eea;
            margin-top: 8px;
        }

        .team-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .team-subtitle {
            color: #666;
            font-size: 1rem;
            line-height: 1.5;
        }

        /* Guide Sections */
        .guide-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }

        .guide-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f5f5;
        }

        .guide-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            font-weight: bold;
        }

        .guide-icon.orders {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
        }

        .guide-icon.invest {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
        }

        .guide-icon.withdraw {
            background: linear-gradient(135deg, #ffd93d, #ffcd3c);
        }

        .guide-icon.games {
            background: linear-gradient(135deg, #a8edea, #fed6e3);
            color: #333;
        }

        .guide-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .guide-subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .guide-content {
            line-height: 1.6;
            color: #555;
        }

        .guide-steps {
            list-style: none;
            margin: 15px 0;
        }

        .guide-steps li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }

        .guide-steps li:before {
            content: counter(step-counter);
            counter-increment: step-counter;
            position: absolute;
            left: 0;
            top: 8px;
            background: #667eea;
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .guide-steps {
            counter-reset: step-counter;
        }

        .screenshot-container {
            margin: 20px 0;
            text-align: center;
        }

        .screenshot {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .screenshot-caption {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
            font-style: italic;
        }

        /* Premium Contact Section */
        .premium-contact-section {
            background: linear-gradient(145deg, #1a1a2e, #16213e, #0f3460);
            border-radius: 25px;
            padding: 35px;
            color: white;
            text-align: center;
            margin-top: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
        }

        .premium-contact-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #00d4ff, #ff6b6b, #ffd93d, #4ecdc4);
            animation: shimmer 2s linear infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .contact-header {
            margin-bottom: 30px;
        }

        .contact-icon-main {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .contact-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, #00d4ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .contact-subtitle {
            font-size: 1.2rem;
            margin-bottom: 20px;
            opacity: 0.95;
        }

        .live-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .live-dot {
            width: 12px;
            height: 12px;
            background: #00ff88;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .live-text {
            font-size: 0.95rem;
            font-weight: 600;
            color: #00ff88;
        }

        .support-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: #00d4ff;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .contact-options-premium {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
        }

        .contact-option-premium {
            background: rgba(255,255,255,0.08);
            border: 2px solid rgba(255,255,255,0.15);
            border-radius: 20px;
            padding: 25px;
            text-decoration: none;
            color: white;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .contact-option-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }

        .contact-option-premium:hover::before {
            left: 100%;
        }

        .contact-option-premium:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.3);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .option-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .contact-icon-premium {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .whatsapp .contact-icon-premium {
            background: linear-gradient(135deg, #25d366, #128c7e);
        }

        .phone .contact-icon-premium {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .email .contact-icon-premium {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
        }

        .contact-info-premium {
            text-align: left;
        }

        .contact-method-premium {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .contact-details-premium {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .contact-number {
            font-size: 0.9rem;
            font-weight: 600;
            color: #00d4ff;
        }

        .option-right {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .contact-badge-premium {
            padding: 6px 14px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .contact-badge-premium.recommended {
            background: linear-gradient(135deg, #00ff88, #00cc6a);
            color: #000;
        }

        .contact-badge-premium.personal {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .contact-badge-premium.detailed {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }

        .arrow-icon {
            font-size: 1.5rem;
            font-weight: bold;
            opacity: 0.7;
            transition: transform 0.3s ease;
        }

        .contact-option-premium:hover .arrow-icon {
            transform: translateX(5px);
        }

        .emergency-contact {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-align: left;
        }

        .emergency-icon {
            font-size: 2rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .emergency-text {
            flex: 1;
            font-size: 1rem;
        }


        /* Responsive */
        @media (max-width: 768px) {
            .content {
                padding: 20px 15px;
            }
            
            .team-photos {
                gap: 10px;
            }
            
            .team-photo {
                width: 70px;
                height: 70px;
            }
        }

        /* Animation */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <img src="logo.svg" alt="LET PAY YOU">
            </div>
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-btn">← Back</a>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Page Title -->
            <div class="page-title fade-in">
                <h1>Support Center</h1>
                <p>We're here to help you succeed</p>
            </div>

            <!-- Support Team Section -->
            <div class="support-team fade-in">
                <div class="team-photos">
                    <div class="team-member">
                        <img src="attached_assets/generated_images/Professional_customer_service_rep_79ce51b8.png" alt="Support Agent" class="team-photo">
                        <div class="team-name">Sarah M.</div>
                    </div>
                    <div class="team-member">
                        <img src="attached_assets/generated_images/Nigerian_customer_support_agent_90d108bb.png" alt="Support Agent" class="team-photo">
                        <div class="team-name">Kemi A.</div>
                    </div>
                </div>
                <h2 class="team-title">Professional Support Team</h2>
                <p class="team-subtitle">Our dedicated experts are ready to assist you with any questions or concerns. Get personalized help from real people who understand your needs.</p>
            </div>

            <!-- Premium Contact Support -->
            <div class="premium-contact-section fade-in">
                <div class="contact-header">
                    <div class="contact-icon-main">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h2 class="contact-title">Get Expert Help!</h2>
                    <p class="contact-subtitle">🔥 Premium Support Team</p>
                    <div class="live-indicator">
                        <span class="live-dot"></span>
                        <span class="live-text">Live Support Available</span>
                    </div>
                </div>

                <div class="support-stats">
                    <div class="stat-item">
                        <div class="stat-number">98%</div>
                        <div class="stat-label">Issues Resolved</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">&lt;2min</div>
                        <div class="stat-label">Response Time</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Always Online</div>
                    </div>
                </div>
                
                <div class="contact-options-premium">
                    <!-- WhatsApp -->
                    <a href="https://wa.me/61468302435" class="contact-option-premium whatsapp">
                        <div class="option-left">
                            <div class="contact-icon-premium">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <div class="contact-info-premium">
                                <div class="contact-method-premium">💬 WhatsApp Support</div>
                                <div class="contact-details-premium">Instant chat solutions</div>
                                <div class="contact-number">+61468302435</div>
                            </div>
                        </div>
                        <div class="option-right">
                            <div class="contact-badge-premium recommended">FASTEST</div>
                            <div class="arrow-icon">→</div>
                        </div>
                    </a>

                    <!-- Phone -->
                    <a href="tel:+2349051880966" class="contact-option-premium phone">
                        <div class="option-left">
                            <div class="contact-icon-premium">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="contact-info-premium">
                                <div class="contact-method-premium">📞 Phone Support</div>
                                <div class="contact-details-premium">Talk to experts</div>
                                <div class="contact-number">+2349051880966</div>
                            </div>
                        </div>
                        <div class="option-right">
                            <div class="contact-badge-premium personal">PERSONAL</div>
                            <div class="arrow-icon">→</div>
                        </div>
                    </a>

                    <!-- Email -->
                    <a href="mailto:Odigieunity81@gmail.com" class="contact-option-premium email">
                        <div class="option-left">
                            <div class="contact-icon-premium">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <div class="contact-info-premium">
                                <div class="contact-method-premium">✉️ Email Support</div>
                                <div class="contact-details-premium">Detailed responses</div>
                                <div class="contact-number">Odigieunity81@gmail.com</div>
                            </div>
                        </div>
                        <div class="option-right">
                            <div class="contact-badge-premium detailed">DETAILED</div>
                            <div class="arrow-icon">→</div>
                        </div>
                    </a>
                </div>

                <div class="emergency-contact">
                    <div class="emergency-icon">🚨</div>
                    <div class="emergency-text">
                        <strong>Emergency Issues?</strong> Use WhatsApp for urgent matters!
                    </div>
                </div>
            </div>

            <!-- How to Place Orders -->
            <div class="guide-section fade-in">
                <div class="guide-header">
                    <div class="guide-icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div>
                        <div class="guide-title">How to Complete Orders</div>
                        <div class="guide-subtitle">Step-by-step guide to earning with tasks</div>
                    </div>
                </div>
                <div class="guide-content">
                    <p><strong>Orders are your daily tasks that generate profits from your investment packages. This is the core activity that transforms your investment into consistent daily earnings.</strong></p>
                    
                    <p>The order completion system is designed to be simple yet rewarding. Each investment package comes with a specific number of daily orders that must be completed to unlock your full profit potential. These orders simulate real e-commerce activities and help you understand online business operations while earning money.</p>
                    
                    <ul class="guide-steps">
                        <li>Navigate to the Orders page from your main dashboard by clicking the "Orders" button in the navigation menu</li>
                        <li>Select your active investment package from the dropdown list - each package has different order requirements and profit margins</li>
                        <li>Click "Start Orders" to begin your daily task sequence and view the first product order</li>
                        <li>Complete each order by reviewing the product information and clicking "Submit Order" when ready</li>
                        <li>Monitor your progress through the order counter and watch your daily commission accumulate in real-time</li>
                        <li>Complete all required orders for your package level to unlock the full daily profit amount</li>
                        <li>Return the next day to complete fresh orders and continue building your earnings consistently</li>
                    </ul>
                    
                    <p>Remember that order completion must be done daily to maximize your investment returns. The system tracks your progress automatically, and completed orders contribute directly to your main account balance. Each package level offers different commission rates, so higher-tier packages provide better daily earning potential.</p>
                    
                    <div class="screenshot-container">
                        <img src="https://via.placeholder.com/350x200/667eea/ffffff?text=Orders+Page+Screenshot" alt="Orders Page" class="screenshot">
                        <div class="screenshot-caption">📱 Orders Page Interface</div>
                        <p style="color: #666; font-style: italic; margin-top: 10px;">Navigate to Orders → Select Package → Complete Tasks → Earn Commission</p>
                    </div>
                </div>
            </div>

            <!-- How to Invest -->
            <div class="guide-section fade-in">
                <div class="guide-header">
                    <div class="guide-icon invest">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="guide-title">How to Invest</div>
                        <div class="guide-subtitle">Choose the right package for your goals</div>
                    </div>
                </div>
                <div class="guide-content">
                    <p><strong>Investment packages are the foundation of your earning journey on our platform. Each package unlocks daily earning opportunities through structured task completion and provides different levels of profit potential based on your investment capacity.</strong></p>
                    
                    <p>Our investment system offers multiple tiers designed to accommodate different financial goals and risk appetites. From Bronze level for beginners to Diamond and Crown packages for serious investors, each tier provides specific daily order requirements and corresponding commission rates. The beauty of our system lies in its simplicity - once you invest, you earn daily by completing simple tasks.</p>
                    
                    <ul class="guide-steps">
                        <li>Navigate to the Investment page from your main dashboard to view all available package options</li>
                        <li>Carefully review each package tier including Bronze, Silver, Gold, Platinum, Diamond, and premium levels</li>
                        <li>Study the package requirements, daily order limits, and potential profit margins for each level</li>
                        <li>Check the minimum investment amount required and ensure you have sufficient funds in your account</li>
                        <li>Select your preferred package based on your investment capacity and earning goals</li>
                        <li>Fund your account using the deposit feature if needed to meet the package requirements</li>
                        <li>Complete the package purchase process and begin earning daily through order completion immediately</li>
                    </ul>
                    
                    <p>Remember that higher-tier packages require larger investments but offer significantly better daily returns and lower order completion requirements. Each package comes with detailed information about expected daily profits, order quotas, and investment duration. Choose wisely based on your financial situation and long-term earning objectives.</p>
                    
                    <div class="screenshot-container">
                        <img src="https://via.placeholder.com/350x200/4ecdc4/ffffff?text=Investment+Packages" alt="Investment Page" class="screenshot">
                        <div class="screenshot-caption">📱 Investment Package Selection</div>
                        <p style="color: #666; font-style: italic; margin-top: 10px;">Choose Package → Review Details → Fund Account → Start Earning</p>
                    </div>
                </div>
            </div>

            <!-- How to Withdraw -->
            <div class="guide-section fade-in">
                <div class="guide-header">
                    <div class="guide-icon withdraw">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <div class="guide-title">How to Withdraw</div>
                        <div class="guide-subtitle">Get your earnings quickly and securely</div>
                    </div>
                </div>
                <div class="guide-content">
                    <p><strong>Withdrawing your earnings is straightforward and secure. Our platform supports direct transfers to all major Nigerian banks, ensuring you receive your profits quickly and safely without unnecessary delays or complications.</strong></p>
                    
                    <p>The withdrawal process has been optimized for Nigerian users with support for all major banking institutions including GTBank, Access Bank, First Bank, UBA, Zenith Bank, and many others. We use advanced security protocols to protect your financial information and ensure all transactions are processed safely. Minimum withdrawal amounts apply to ensure efficient processing.</p>
                    
                    <ul class="guide-steps">
                        <li>Navigate to your Wallet page from the main dashboard and locate the "Withdraw" button</li>
                        <li>Enter your complete Nigerian bank account details including account number, bank name, and account holder name</li>
                        <li>Specify the exact withdrawal amount ensuring it meets the minimum withdrawal requirement for your account level</li>
                        <li>Double-check all entered information for accuracy as incorrect details may delay processing</li>
                        <li>Verify your registered phone number through the SMS verification system for security</li>
                        <li>Review the withdrawal summary including any applicable fees and processing timeframes</li>
                        <li>Submit your withdrawal request and monitor the status through your transaction history</li>
                        <li>Receive your funds directly in your bank account within 24-48 hours during business days</li>
                    </ul>
                    
                    <p>For faster processing, ensure your account is fully verified with complete KYC documentation. Weekend withdrawals may experience slight delays due to banking hours. Our customer support team monitors all withdrawal requests to ensure smooth processing and can assist with any issues that may arise during the transfer process.</p>
                    
                    <div class="screenshot-container">
                        <img src="https://via.placeholder.com/350x200/ffd93d/333333?text=Withdrawal+Interface" alt="Withdrawal Page" class="screenshot">
                        <div class="screenshot-caption">📱 Secure Withdrawal Process</div>
                        <p style="color: #666; font-style: italic; margin-top: 10px;">Wallet → Withdraw → Enter Bank Details → Verify → Receive Funds</p>
                    </div>
                </div>
            </div>

            <!-- How to Play Games -->
            <div class="guide-section fade-in">
                <div class="guide-header">
                    <div class="guide-icon games">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div>
                        <div class="guide-title">How to Play Games</div>
                        <div class="guide-subtitle">Bonus earning through skill-based games</div>
                    </div>
                </div>
                <div class="guide-content">
                    <p><strong>Our gaming section provides exciting entertainment opportunities while offering additional earning potential beyond your regular investment profits. These games are designed to be fun, engaging, and potentially rewarding when played responsibly.</strong></p>
                    
                    <p>The gaming platform features popular games including Aviator, Dice, Mines, Plinko, Spin Wheel, and many others. Each game offers different risk-reward ratios and gameplay mechanics, allowing you to choose games that match your preference and risk tolerance. All games use certified random number generation to ensure fair play and transparent results.</p>
                    
                    <ul class="guide-steps">
                        <li>Access the Games section from your main dashboard navigation menu to view all available gaming options</li>
                        <li>Browse through different game categories including Aviator, Dice, Mines, Plinko, Crash, and specialty games</li>
                        <li>Select a game that interests you and familiarize yourself with its rules and gameplay mechanics</li>
                        <li>Set your bet amount conservatively - always start with small amounts to understand the game dynamics</li>
                        <li>Place your bets using funds from your main account balance and enjoy the gaming experience</li>
                        <li>Monitor your gameplay statistics and set personal limits to ensure responsible gaming habits</li>
                        <li>Celebrate your winnings as they are automatically added to your main account balance immediately</li>
                        <li>Practice responsible gaming by setting daily limits and never betting more than you can afford to lose</li>
                    </ul>
                    
                    <p>Remember that gaming should be treated as entertainment first, with any winnings considered bonus income. We strongly encourage responsible gaming practices including setting daily limits, taking regular breaks, and never chasing losses. Our platform includes built-in tools to help you maintain healthy gaming habits and enjoy the experience safely.</p>
                    
                    <div class="screenshot-container">
                        <img src="https://via.placeholder.com/350x200/a8edea/333333?text=Gaming+Hub" alt="Games Page" class="screenshot">
                        <div class="screenshot-caption">📱 Interactive Gaming Platform</div>
                        <p style="color: #666; font-style: italic; margin-top: 10px;">Games → Choose Game → Set Bet → Play Responsibly → Enjoy Winnings</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Add fade-in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all fade-in elements
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Contact option click tracking
        document.querySelectorAll('.contact-option').forEach(option => {
            option.addEventListener('click', function(e) {
                // Add click animation
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
</body>
</html>