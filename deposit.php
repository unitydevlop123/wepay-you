<?php require_once 'firebase_setup.php'; ?>
<?php
date_default_timezone_set('Africa/Lagos');

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load bank account details from Firebase
$bankAccounts = readJsonFile('bank_details') ?: [];

// Load user data from Firebase
$users = getUsers();

// Include authentication system
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Enable proper authentication
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Function to generate a unique transaction ID
function generateTransactionId() {
    return 'TXN' . date('YmdHis') . rand(1000, 9999);
}

// Get user email from authenticated session
$userEmail = $_SESSION['email'];

// Get current authenticated user
// $currentUser = getCurrentUser(); // This line might be redundant if $userEmail is used directly to fetch user data

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form data
    $user = $_POST['user'] ?? 'Unknown';
    $phone = $_POST['phone'] ?? 'N/A';
    $amount = (float)($_POST['amount'] ?? 0);
    $bank = $_POST['bank'] ?? 'N/A';
    $method = $_POST['method'] ?? 'manual';

    // Generate unique reference
    $reference = 'LETPAY-DEP-' . date('Ymd') . '-' . rand(1000, 9999);

    // Validate minimum amount
    if ($amount < 100) {
        $error = "Minimum deposit amount is ₦100";
    } elseif ($amount > 1000000) {
        $error = "Maximum deposit amount is ₦1,000,000";
    } else {
        // Save to Firebase storage
        $fundingData = readJsonFile('funding') ?: [];

        $newDeposit = [
            'id' => uniqid('dep_'),
            'user' => $user,
            'phone' => $phone,
            'amount' => $amount,
            'method' => $method,
            'bank' => $bank,
            'reference' => $reference,
            'status' => 'pending',
            'type' => 'deposit',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $fundingData[] = $newDeposit;
        writeJsonFile('funding', $fundingData);

        // ADD TO LIVE TRANSACTION HISTORY
        $transaction = [
            'id' => 'TXN_' . strtoupper(bin2hex(random_bytes(4))),
            'transaction_type' => 'deposit',
            'description' => 'Wallet Deposit via ' . ucfirst($bank),
            'amount' => $amount,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'reference' => $reference,
            'method' => $method,
            'bank' => $bank
        ];

        // Add to user's transaction history
        addUserTransaction($userEmail, $transaction);

        // Find the current user's data using $userEmail
        $currentUser = null;
        foreach ($users as $u) {
            if ($u['email'] === $userEmail) {
                $currentUser = $u;
                break;
            }
        }

        // Create pending deposit notification (don't credit balance yet)
        $notification = [
            'id' => 'deposit_' . time() . '_' . rand(1000, 9999),
            'type' => 'deposit',
            'title' => 'Deposit Pending!',
            'message' => "💰 Your deposit of ₦" . number_format($amount, 2) . " is being processed and will be credited once approved.",
            'amount' => $amount,
            'status' => 'unread',
            'reference' => $reference,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null
        ];

        // Add notification using Firebase function
        addUserNotification($userEmail, $notification);

        // ADMIN APPROVAL/REJECTION NOTIFICATIONS (will be triggered by admin actions)
        // Success notification template (created when admin approves)
        $successNotification = [
            'id' => 'deposit_success_' . time() . '_' . rand(1000, 9999),
            'type' => 'deposit',
            'title' => 'Deposit Successful! 💰',
            'message' => "✅ Your deposit of ₦" . number_format($amount, 2) . " has been approved and credited to your wallet. Happy investing!",
            'amount' => $amount,
            'status' => 'unread',
            'reference' => $reference,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null,
            'trigger' => 'approval' // This will be used by admin backend
        ];

        // Rejection notification template (created when admin rejects)
        $rejectionNotification = [
            'id' => 'deposit_rejected_' . time() . '_' . rand(1000, 9999),
            'type' => 'warning',
            'title' => 'Deposit Rejected ❌',
            'message' => "❌ Your deposit of ₦" . number_format($amount, 2) . " has been rejected. Please contact support for assistance or try again with correct details.",
            'amount' => $amount,
            'status' => 'unread',
            'reference' => $reference,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null,
            'trigger' => 'rejection' // This will be used by admin backend
        ];


        // Set success message and receipt data
        $success = "Your deposit request has been submitted successfully! Reference: $reference";
        $showReceipt = true;
        $receiptData = [
            'name' => $currentUser['name'] ?? $currentUser['email'], // Fix: Use current user's name instead of array
            'phone' => $phone,
            'amount' => '₦' . number_format($amount, 2),
            'bank' => $bank,
            'reference' => $reference,
            'date' => date('M j, Y \a\t g:i A'),
            'status' => 'Pending Approval'
        ];
    }
}

// Function to format amounts
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Funds - LET PAY YOU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Banner Section */
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
        }

        .banner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .banner-title {
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }

        /* Content */
        .content {
            padding: 40px 30px;
        }

        /* Feature highlights */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .feature-card {
            background: #f8f9ff;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
        }

        .feature-icon {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 15px;
        }

        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .feature-desc {
            color: #718096;
            font-size: 0.9rem;
        }

        /* Form Container */
        .form-container {
            background: #f8f9ff;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .form-title {
            text-align: center;
            color: #2d3748;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            background: white;
            color: #333;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control::placeholder {
            color: #a0aec0;
        }

        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 16px 30px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Alert styles */
        .alert {
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
        }

        .alert-success {
            background-color: #f0fff4;
            color: #38a169;
            border-color: #9ae6b4;
        }

        .alert-danger {
            background-color: #fed7d7;
            color: #e53e3e;
            border-color: #fc8181;
        }

        /* Bank details */
        .bank-details {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
            border: 2px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: none;
        }

        .bank-details h4 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .amount-display {
            font-size: 1.1rem;
            font-weight: 600;
            background: #f8f9ff;
            color: #667eea;
            padding: 12px 16px;
            border-radius: 10px;
            margin-top: 10px;
            border: 2px solid #e2e8f0;
            text-align: center;
        }

        .security-warning {
            background: #fffbeb;
            border: 2px solid #fbbf24;
            color: #d97706;
            padding: 15px;
            border-radius: 15px;
            margin-top: 20px;
            font-size: 14px;
        }

        select option {
            background: white;
            color: #333;
        }

        /* Receipt Modal */
        .receipt-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .receipt-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt-header i {
            color: #38a169;
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .receipt-item:last-child {
            border-bottom: none;
            font-weight: bold;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                margin: 0;
                border-radius: 0;
            }

            .content {
                padding: 20px 15px;
            }

            .banner-title {
                font-size: 2rem;
            }

            .features-grid {
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
            </div>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <!-- Banner Section -->
        <div class="banner-section">
            <img src="attached_assets/attached_assets/generated_images/Young_woman_slot_winner_fcf9b4df.png" alt="Deposit Funds" class="banner-image">
            <div class="banner-overlay">
                <h1 class="banner-title">💰 Deposit Funds</h1>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Feature highlights -->
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <div class="feature-title">Instant Processing</div>
                    <div class="feature-desc">Your funds are credited immediately after bank transfer confirmation</div>
                </div>
            </div>

            <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Deposit Form -->
            <div class="form-container">
                <h2 class="form-title">💰 Fund Your Account</h2>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="user"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" class="form-control" id="user" name="user" 
                               value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" 
                               placeholder="Enter your full name" required>
                    </div>

                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               placeholder="0801 234 5678" required>
                    </div>

                    <div class="form-group">
                        <label for="amount"><i class="fas fa-money-bill-wave"></i> Amount (₦)</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               min="100" max="1000000" step="1" 
                               placeholder="Enter amount (minimum ₦100)" 
                               required oninput="updateAmountDisplay()">

                        <div id="amountDisplay" class="amount-display" style="display: none;">
                            You are depositing: <span id="formattedAmount">₦0.00</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bank"><i class="fas fa-university"></i> Select Bank</label>
                        <select class="form-control" id="bank" name="bank" required onchange="showBankDetails()">
                            <option value="">Choose your preferred bank</option>
                            <?php if (!empty($bankAccounts) && is_array($bankAccounts)): ?>
                                <?php foreach ($bankAccounts as $key => $bank): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $bank['name'] ?? 'Unknown Bank'; ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">No banks available</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div id="bankDetails" class="bank-details">
                        <h4><i class="fas fa-info-circle"></i> Bank Transfer Details</h4>
                        <div id="accountInfo"></div>
                        <p style="margin-top: 15px; color: #d97706; font-weight: 600;">
                            <i class="fas fa-arrow-right"></i> Transfer the exact amount to the account above, then click "Confirm Payment" below.
                        </p>
                    </div>

                    <input type="hidden" name="method" value="manual">

                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Confirm Payment
                    </button>
                </form>
            </div>

            <div class="security-warning">
                <i class="fas fa-shield-alt"></i> 
                <strong>Security Notice:</strong> Please double-check your bank details before submitting. LET PAY YOU is not responsible for errors due to incorrect information.
            </div>
        </div>
    </div>

    <?php if (isset($showReceipt) && $showReceipt): ?>
    <!-- Receipt Modal -->
    <div class="receipt-modal" id="receiptModal">
        <div class="receipt-content">
            <div class="receipt-header">
                <i class="fas fa-check-circle"></i>
                <h3>Deposit Request Submitted</h3>
            </div>

            <div class="receipt-item">
                <span>Service:</span>
                <span>Deposit Request</span>
            </div>
            <div class="receipt-item">
                <span>Full Name:</span>
                <span><?php echo $receiptData['name']; ?></span>
            </div>
            <div class="receipt-item">
                <span>Phone Number:</span>
                <span><?php echo $receiptData['phone']; ?></span>
            </div>
            <div class="receipt-item">
                <span>Bank Used:</span>
                <span><?php echo $receiptData['bank']; ?></span>
            </div>
            <div class="receipt-item">
                <span>Amount:</span>
                <span><?php echo $receiptData['amount']; ?></span>
            </div>
            <div class="receipt-item">
                <span>Reference:</span>
                <span><?php echo $receiptData['reference']; ?></span>
            </div>
            <div class="receipt-item">
                <span>Date & Time:</span>
                <span><?php echo $receiptData['date']; ?></span>
            </div>
            <div class="receipt-item">
                <span>Status:</span>
                <span style="color: #d97706;">Pending Approval</span>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <button onclick="closeReceipt()" class="btn" style="width: auto; padding: 12px 24px;">Close</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const bankAccounts = <?php echo json_encode($bankAccounts); ?>;

        function showBankDetails() {
            const bankSelect = document.getElementById('bank');
            const bankDetails = document.getElementById('bankDetails');
            const accountInfo = document.getElementById('accountInfo');

            if (bankSelect.value) {
                const selectedBank = bankAccounts[bankSelect.value];
                accountInfo.innerHTML = `
                    <p><strong>Bank Name:</strong> ${selectedBank.name}</p>
                    <p><strong>Account Number:</strong> <span style="font-size: 1.3em; color: #667eea; font-weight: bold; background: #f8f9ff; padding: 8px 12px; border-radius: 8px; display: inline-block; margin: 5px 0;">${selectedBank.account}</span></p>
                    <p><strong>Account Name:</strong> ${selectedBank.account_name}</p>
                `;
                bankDetails.style.display = 'block';
            } else {
                bankDetails.style.display = 'none';
            }
        }

        function updateAmountDisplay() {
            const amountInput = document.getElementById('amount');
            const amountDisplay = document.getElementById('amountDisplay');
            const formattedAmount = document.getElementById('formattedAmount');

            const amount = parseFloat(amountInput.value) || 0;

            if (amount && amount >= 100 && amount <= 1000000) {
                formattedAmount.textContent = '₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                amountDisplay.style.display = 'block';
            } else {
                amountDisplay.style.display = 'none';
            }
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            if (!amount || amount < 100) {
                e.preventDefault();
                alert('Minimum deposit amount is ₦100');
                return false;
            }
            if (amount > 1000000) {
                e.preventDefault();
                alert('Maximum deposit amount is ₦1,000,000');
                return false;
            }
        });

        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length >= 4) {
                value = value.slice(0, 4) + ' ' + value.slice(4, 7) + ' ' + value.slice(7);
            }
            e.target.value = value;
        });

        <?php if (isset($showReceipt) && $showReceipt): ?>
        // Show receipt modal
        document.getElementById('receiptModal').style.display = 'block';
        <?php endif; ?>

        function closeReceipt() {
            document.getElementById('receiptModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.id === 'receiptModal') {
                closeReceipt();
            }
        });
    </script>
</body>
</html>