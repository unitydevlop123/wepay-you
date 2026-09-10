<?php require_once 'firebase_setup.php'; ?>
<?php
date_default_timezone_set('Africa/Lagos');

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load user data from Firebase
$users = getUsers();

// Include authentication
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Enable proper authentication
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Get current user email from authenticated session
$userEmail = $_SESSION['email'];


// Get current authenticated user
$currentUser = getCurrentUser();
$userBalance = $currentUser['balance'];

// Function to update user balance in Firebase
function updateUserBalance($email, $newBalance) {
    $users = getUsers();

    foreach ($users as &$user) {
        if ($user['email'] === $email) {
            $user['balance'] = $newBalance;
            $user['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }

    saveUsers($users);
}



// Bank accounts configuration for withdrawals
$bankAccounts = [
    'gtbank' => [
        'name' => 'GTBank - Guaranty Trust Bank',
        'account_number' => '0123456789',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'access' => [
        'name' => 'Access Bank',
        'account_number' => '0987654321',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'zenith' => [
        'name' => 'Zenith Bank',
        'account_number' => '1234567890',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'firstbank' => [
        'name' => 'First Bank of Nigeria',
        'account_number' => '2345678901',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'uba' => [
        'name' => 'United Bank for Africa (UBA)',
        'account_number' => '3456789012',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'fidelity' => [
        'name' => 'Fidelity Bank',
        'account_number' => '4567890123',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'fcmb' => [
        'name' => 'First City Monument Bank (FCMB)',
        'account_number' => '5678901234',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'union' => [
        'name' => 'Union Bank of Nigeria',
        'account_number' => '6789012345',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'sterling' => [
        'name' => 'Sterling Bank',
        'account_number' => '7890123456',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'stanbic' => [
        'name' => 'Stanbic IBTC Bank',
        'account_number' => '8901234567',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'ecobank' => [
        'name' => 'Ecobank Nigeria',
        'account_number' => '9012345678',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'wema' => [
        'name' => 'Wema Bank',
        'account_number' => '0123456780',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'polaris' => [
        'name' => 'Polaris Bank',
        'account_number' => '1234567801',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'keystone' => [
        'name' => 'Keystone Bank',
        'account_number' => '2345678012',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'unity' => [
        'name' => 'Unity Bank',
        'account_number' => '3456780123',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'heritage' => [
        'name' => 'Heritage Bank',
        'account_number' => '4567801234',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'kuda' => [
        'name' => 'Kuda Microfinance Bank',
        'account_number' => '5678012345',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'moniepoint' => [
        'name' => 'Moniepoint MFB',
        'account_number' => '6780123456',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'opay' => [
        'name' => 'Opay Digital Services',
        'account_number' => '7801234567',
        'account_name' => 'LET PAY YOU LIMITED'
    ],
    'palmpay' => [
        'name' => 'PalmPay Limited',
        'account_number' => '8012345678',
        'account_name' => 'LET PAY YOU LIMITED'
    ]
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $user = $_POST['user'] ?? 'Unknown';
    $phone = $_POST['phone'] ?? 'N/A';
    $amount = (float)($_POST['amount'] ?? 0);
    $bank = $_POST['bank'] ?? 'N/A';
    $accountNumber = $_POST['account_number'] ?? '';
    $accountName = $_POST['account_name'] ?? '';

    // Generate unique reference
    $reference = 'LPY-WTH-' . date('Ymd') . '-' . rand(1000, 9999);

    // Validate minimum amount and user balance
    if ($amount < 5000) {
        $error = "Minimum withdrawal amount is ₦5,000";
    } elseif ($amount > 5000000) {
        $error = "Maximum withdrawal amount is ₦5,000,000";
    } elseif ($amount > $userBalance) {
        $error = "Insufficient balance. Your current balance is ₦" . number_format($userBalance, 2);
    } else {
        // DEDUCT MONEY FROM USER BALANCE IMMEDIATELY
        $newBalance = $currentUser['balance'] - $amount;
        updateUserBalance($currentUser['email'], $newBalance);

        // Update current user balance for display
        $currentUser['balance'] = $newBalance;
        $currentUser['updated_at'] = date('Y-m-d H:i:s');

        // Save withdrawal request to Firebase (using correct Firebase paths)
        $withdrawalData = readJsonFile('withdrawals') ?: [];

        $newWithdrawal = [
            'id' => uniqid('wth_'),
            'user' => $currentUser['name'] ?? $user,
            'email' => $currentUser['email'],
            'phone' => $phone,
            'amount' => $amount,
            'bank' => $bank,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'reference' => $reference,
            'status' => 'pending',
            'type' => 'withdrawal',
            'transaction_type' => 'withdrawal',
            'description' => 'Wallet Withdrawal',
            'fee' => 0,
            'total' => $amount,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $withdrawalData[] = $newWithdrawal;
        writeJsonFile('withdrawals', $withdrawalData);

        // SAVE TO TRANSACTION HISTORY IMMEDIATELY AS PENDING (using Firebase path)
        $transactions = readJsonFile('transactions') ?: [];

        $transactions[] = [
            'id' => $newWithdrawal['id'],
            'user' => $currentUser['name'] ?? $user,
            'email' => $currentUser['email'],
            'phone' => $phone,
            'type' => 'withdrawal',
            'transaction_type' => 'withdrawal',
            'description' => 'Wallet Withdrawal',
            'amount' => $amount,
            'fee' => 0,
            'total' => $amount,
            'status' => 'pending',
            'reference' => $reference,
            'bank' => $bank,
            'account_number' => $accountNumber,
            'created_at' => date('Y-m-d H:i:s')
        ];

        writeJsonFile('transactions', $transactions);

        // ADD TO LIVE TRANSACTION HISTORY using Firebase
        $transaction = [
            'id' => 'TXN_' . strtoupper(bin2hex(random_bytes(4))),
            'transaction_type' => 'withdrawal',
            'description' => 'Wallet Withdrawal to ' . ucfirst($bank),
            'amount' => $amount, // Keep positive for display
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'reference' => $reference,
            'bank' => $bank,
            'account_number' => $accountNumber,
            'account_name' => $accountName
        ];

        // Add to user's transaction history using Firebase function
        addUserTransaction($currentUser['email'], $transaction);

        // Create withdrawal notification (Firebase format)
        $notification = [
            'id' => 'withdrawal_' . time() . '_' . rand(1000, 9999),
            'type' => 'withdrawal',
            'title' => 'Withdrawal Pending!',
            'message' => "💸 Your withdrawal request of ₦" . number_format($amount, 2) . " is being processed and will be sent to your bank account once approved.",
            'amount' => $amount,
            'status' => 'unread',
            'reference' => $reference,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null
        ];

        // Add notification using Firebase function
        addUserNotification($currentUser['email'], $notification);

        // ADMIN APPROVAL/REJECTION NOTIFICATIONS (will be triggered by admin actions)
        // Success notification template (created when admin approves)
        $successNotification = [
            'id' => 'withdrawal_success_' . time() . '_' . rand(1000, 9999),
            'type' => 'withdrawal',
            'title' => 'Withdrawal Successful! 💸',
            'message' => "✅ Your withdrawal of ₦" . number_format($amount, 2) . " has been approved and sent to your bank account (" . $bank . " - " . $accountNumber . "). Thank you!",
            'amount' => $amount,
            'status' => 'unread',
            'reference' => $reference,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null,
            'trigger' => 'approval' // This will be used by admin backend
        ];

        // Rejection notification template (created when admin rejects)
        $rejectionNotification = [
            'id' => 'withdrawal_rejected_' . time() . '_' . rand(1000, 9999),
            'type' => 'warning',
            'title' => 'Withdrawal Rejected ❌',
            'message' => "❌ Your withdrawal request of ₦" . number_format($amount, 2) . " has been rejected. Funds have been returned to your wallet. Please contact support for assistance.",
            'amount' => $amount,
            'status' => 'unread',
            'reference' => $reference,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null,
            'trigger' => 'rejection' // This will be used by admin backend
        ];

        // Send email notification
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'unityodigie0@gmail.com';
            $mail->Password = 'xfrpqrvluldratkb';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->SMTPDebug = 0;
            $mail->Debugoutput = 'html';

            $mail->setFrom('unityodigie0@gmail.com', 'LET PAY YOU');
            $mail->addAddress('odigieunity81@gmail.com');

            $mail->isHTML(true);
            $mail->Subject = '💸 New Withdrawal Request - LET PAY YOU';

            // Use your live hosting domain for email links
            $liveBaseUrl = 'https://unitytechdata.rf.gd/unity-tech';

            // Create approve and decline URLs pointing to backend folder
            $approveUrl = $liveBaseUrl . '/backend/approve-withdrawal.php?id=' . $newWithdrawal['id'];
            $declineUrl = $liveBaseUrl . '/backend/decline-withdrawal.php?id=' . $newWithdrawal['id'];

            // Premium company email template
            $mail->Body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>New Withdrawal Request</title>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
    <table cellpadding='0' cellspacing='0' width='100%' style='background-color: #f5f5f5; padding: 20px;'>
        <tr>
            <td align='center'>
                <table cellpadding='0' cellspacing='0' width='600' style='background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>

                    <!-- Header with Logo -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                            <img src='https://unitytechdata.rf.gd/unity-tech/logo.svg' alt='LET PAY YOU' style='height: 50px; margin-bottom: 15px;'>
                            <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>New Withdrawal Request</h1>
                            <p style='margin: 10px 0 0 0; font-size: 16px;'>Amount: ₦" . number_format($amount, 2) . "</p>
                        </td>
                    </tr>

                    <!-- Customer Info -->
                    <tr>
                        <td style='padding: 30px;'>
                            <h2 style='margin: 0 0 20px 0; color: #333; font-size: 18px;'>Customer Details</h2>
                            <table cellpadding='0' cellspacing='0' width='100%'>
                                <tr>
                                    <td style='padding: 8px 0; color: #666; font-size: 14px;'>Name:</td>
                                    <td style='padding: 8px 0; color: #333; font-weight: bold; font-size: 14px;'>$user</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #666; font-size: 14px;'>Phone:</td>
                                    <td style='padding: 8px 0; color: #333; font-weight: bold; font-size: 14px;'>$phone</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #666; font-size: 14px;'>Bank:</td>
                                    <td style='padding: 8px 0; color: #333; font-weight: bold; font-size: 14px;'>$bank</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #666; font-size: 14px;'>Account Number:</td>
                                    <td style='padding: 8px 0; color: #333; font-weight: bold; font-size: 14px;'>$accountNumber</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #666; font-size: 14px;'>Account Name:</td>
                                    <td style='padding: 8px 0; color: #333; font-weight: bold; font-size: 14px;'>$accountName</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #666; font-size: 14px;'>Reference:</td>
                                    <td style='padding: 8px 0; color: #333; font-weight: bold; font-size: 14px;'>$reference</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #666; font-size: 14px;'>Time:</td>
                                    <td style='padding: 8px 0; color: #333; font-weight: bold; font-size: 14px;'>" . date('M j, Y \a\t g:i A') . "</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Action Buttons -->
                    <tr>
                        <td style='padding: 0 30px 30px 30px;'>
                            <table cellpadding='0' cellspacing='0' width='100%'>
                                <tr>
                                    <td width='48%' style='padding-right: 2%;'>
                                        <table cellpadding='0' cellspacing='0' width='100%' style='background-color: #00C851; border-radius: 5px;'>
                                            <tr>
                                                <td style='padding: 15px; text-align: center;'>
                                                    <a href='{$approveUrl}' style='color: white; text-decoration: none; font-weight: bold; font-size: 16px; display: block;'>APPROVE</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width='48%' style='padding-left: 2%;'>
                                        <table cellpadding='0' cellspacing='0' width='100%' style='background-color: #FF4444; border-radius: 5px;'>
                                            <tr>
                                                <td style='padding: 15px; text-align: center;'>
                                                    <a href='{$declineUrl}' style='color: white; text-decoration: none; font-weight: bold; font-size: 16px; display: block;'>DECLINE</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style='background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eee;'>
                            <p style='margin: 0; color: #666; font-size: 12px;'>LET PAY YOU - Premium Investment Platform</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
            ";

            $mail->send();
            $emailSent = true;
        } catch (Exception $e) {
            $emailSent = false;
            $emailError = $e->getMessage();
        }

        if ($emailSent ?? false) {
            $success = "✅ Withdrawal request submitted successfully! Reference: $reference. Admin has been notified and will process within 24 hours.";
        } else {
            $success = "✅ Withdrawal request saved! Reference: $reference. Note: Admin notification may be delayed but your request is recorded.";
        }

        $receiptData = [
            'name' => $user,
            'phone' => $phone,
            'reference' => $reference,
            'bank' => $bank,
            'account' => $accountNumber,
            'accountName' => $accountName,
            'amount' => '₦' . number_format($amount, 2),
            'date' => date('j M Y \a\t g:i A'),
            'status' => 'Pending Approval'
        ];
    }
}

// Helper function to format amount
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Funds - LET PAY YOU</title>
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
            width: auto;
        }

        .company-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
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

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .form-control::placeholder {
            color: #aaa;
        }

        /* Amount Display */
        .amount-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-weight: 600;
            text-align: center;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        /* Security Notice */
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #856404;
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

        .receipt-buttons {
            text-align: center;
            margin-top: 20px;
        }

        .receipt-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .receipt-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }

        /* Feature Highlights */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .feature-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: 1px solid #f0f0f0;
        }

        .feature-icon {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .feature-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .feature-desc {
            font-size: 0.9rem;
            color: #666;
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

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
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
                <span class="company-name">LET PAY YOU</span>
            </div>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Banner Section -->
        <div class="banner-section">
            <img src="attached_assets/attached_assets/generated_images/Successful_family_winnings_moment_0a65f163.png" alt="Withdraw Funds" class="banner-image">
            <div class="banner-overlay">
                <h1 class="banner-title">💸 Withdraw Funds</h1>
            </div>
        </div>

        <!-- Features -->
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                <div class="feature-title">Fast Processing</div>
                <div class="feature-desc">Withdrawals processed within 24 hours</div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
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

            <!-- Withdrawal Form -->
            <div class="form-container">
                <h2 class="form-title">💸 Withdraw to Bank Account</h2>

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
                           min="5000" max="<?= min($userBalance, 5000000) ?>" step="1" 
                           placeholder="Enter amount (minimum ₦5,000)" 
                           required oninput="updateAmountDisplay()">

                    <div id="amountDisplay" class="amount-display" style="display: none;">
                        You are withdrawing: <span id="formattedAmount">₦0.00</span>
                    </div>
                    <small style="color: #666; font-size: 0.9rem;">
                        Minimum: ₦5,000 • Maximum: ₦<?= number_format(min($userBalance, 5000000), 2) ?>
                    </small>
                </div>

                <div class="form-group">
                    <label for="bank"><i class="fas fa-university"></i> Select Bank</label>
                    <select class="form-control" id="bank" name="bank" required>
                        <option value="">Choose your bank</option>
                        <?php foreach ($bankAccounts as $key => $bank): ?>
                            <option value="<?= htmlspecialchars($bank['name']) ?>"><?= htmlspecialchars($bank['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="account_number"><i class="fas fa-credit-card"></i> Account Number</label>
                    <input type="text" class="form-control" id="account_number" name="account_number" 
                           maxlength="10" placeholder="0123456789" required>
                </div>

                <div class="form-group">
                    <label for="account_name"><i class="fas fa-user-check"></i> Account Name</label>
                    <input type="text" class="form-control" id="account_name" name="account_name" 
                           placeholder="Account holder name" required>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit Withdrawal Request
                </button>
            </form>

            <div class="security-notice">
                <i class="fas fa-shield-alt"></i> 
                <strong>Security Notice:</strong> Please ensure your bank details are correct. All withdrawals are processed securely and cannot be reversed once completed.
            </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <?php if (isset($receiptData)): ?>
    <div class="receipt-modal" id="receiptModal">
        <div class="receipt-content">
            <div class="receipt-header">
                <i class="fas fa-check-circle"></i>
                <h3>Withdrawal Request Submitted</h3>
            </div>

            <div class="receipt-item">
                <span>Service:</span>
                <span>Withdrawal Request</span>
            </div>
            <div class="receipt-item">
                <span>Full Name:</span>
                <span><?= $receiptData['name'] ?? 'N/A' ?></span>
            </div>
            <div class="receipt-item">
                <span>Phone Number:</span>
                <span><?= $receiptData['phone'] ?? 'N/A' ?></span>
            </div>
            <div class="receipt-item">
                <span>Bank Used:</span>
                <span><?= $receiptData['bank'] ?></span>
            </div>
            <div class="receipt-item">
                <span>Account Number:</span>
                <span><?= $receiptData['account'] ?></span>
            </div>
            <div class="receipt-item">
                <span>Account Name:</span>
                <span><?= $receiptData['accountName'] ?></span>
            </div>
            <div class="receipt-item">
                <span>Amount:</span>
                <span><?= $receiptData['amount'] ?></span>
            </div>
            <div class="receipt-item">
                <span>Reference:</span>
                <span><?= $receiptData['reference'] ?></span>
            </div>
            <div class="receipt-item">
                <span>Date & Time:</span>
                <span><?= $receiptData['date'] ?></span>
            </div>
            <div class="receipt-item">
                <span>Status:</span>
                <span style="color: #d97706;"><?= $receiptData['status'] ?></span>
            </div>

            <div class="receipt-buttons">
                <button onclick="closeReceipt()" class="receipt-btn">Close</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function updateAmountDisplay() {
            const amountInput = document.getElementById('amount');
            const amountDisplay = document.getElementById('amountDisplay');
            const formattedAmount = document.getElementById('formattedAmount');

            const amount = parseFloat(amountInput.value) || 0;
            const userBalance = <?= $userBalance ?>;

            if (amount >= 5000 && amount <= Math.min(userBalance, 5000000)) {
                formattedAmount.textContent = '₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                amountDisplay.style.display = 'block';
                amountInput.style.borderColor = '#667eea';
            } else {
                amountDisplay.style.display = 'none';
                if (amountInput.value && (amount < 5000 || amount > Math.min(userBalance, 5000000))) {
                    amountInput.style.borderColor = '#dc3545';
                }
            }
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const userBalance = <?= $userBalance ?>;

            if (!amount || amount < 5000) {
                e.preventDefault();
                alert('Minimum withdrawal amount is ₦5,000');
                return false;
            }
            if (amount > 5000000) {
                e.preventDefault();
                alert('Maximum withdrawal amount is ₦5,000,000');
                return false;
            }
            if (amount > userBalance) {
                e.preventDefault();
                alert('Insufficient balance. Your current balance is ₦' + userBalance.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
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

        // Format account number
        document.getElementById('account_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.slice(0, 10);
            e.target.value = value;
        });

        // Receipt modal functions
        <?php if (isset($receiptData)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('receiptModal').style.display = 'flex';
        });
        <?php endif; ?>

        function closeReceipt() {
            document.getElementById('receiptModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('receipt-modal')) {
                closeReceipt();
            }
        });
    </script>
</body>
</html>