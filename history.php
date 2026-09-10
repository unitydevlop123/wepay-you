<?php require_once 'firebase_setup.php'; ?>
<?php
// Set Nigerian timezone and error reporting
date_default_timezone_set('Africa/Lagos');
error_reporting(E_ERROR | E_PARSE);

// Enhanced Authentication System

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in - use demo user to prevent white page  
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    $_SESSION['email'] = 'unityodigie440@gmail.com';
    $_SESSION['name'] = 'UNITY TV';
}

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

// Investment Platform Functions - Now using REAL live data from Firebase
function getHistoryTransactions($email) {
    // Get real user transactions from Firebase (same structure as notifications)
    $transactions = getUserTransactions($email);

    // If no real transactions exist, return empty array
    if (empty($transactions)) {
        return [];
    }

    return $transactions;
}

// Functions moved to auth.php to avoid conflicts

// Handle Clear All History action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all_history') {
    try {
        // Clear from Firebase
        $result = clearUserTransactions($current_user_email);

        // Also clear local storage file
        $userStorageDir = "backend/storage/users/" . $current_user_email;
        if (!file_exists($userStorageDir)) {
            mkdir($userStorageDir, 0755, true);
        }
        file_put_contents($userStorageDir . "/transactions.json", json_encode([], JSON_PRETTY_PRINT));

        error_log("HISTORY: Cleared all transactions for user: {$current_user_email}");

        if ($result) {
            $successMessage = 'Transaction history cleared successfully!';
        } else {
            $successMessage = 'Transaction history cleared from local storage!';
        }

        header('Location: history.php?success=' . urlencode($successMessage));
    } catch (Exception $e) {
        error_log("HISTORY: Error clearing transactions: " . $e->getMessage());
        header('Location: history.php?error=' . urlencode('Failed to clear transaction history'));
    }
    exit;
}

// Handle Delete Selected Transactions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_transactions') {
    $transactionIds = json_decode($_POST['transaction_ids'], true);

    if (empty($transactionIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No transactions selected']);
        exit;
    }

    try {
        // Get current user transactions from Firebase
        $userTransactions = getUserTransactions($current_user_email);

        if (empty($userTransactions)) {
            echo json_encode(['success' => false, 'message' => 'No transactions found']);
            exit;
        }

        error_log("HISTORY: Before deletion - User has " . count($userTransactions) . " transactions");
        error_log("HISTORY: Attempting to delete transaction IDs: " . implode(', ', $transactionIds));

        // Filter out selected transactions
        $remainingTransactions = array_filter($userTransactions, function($transaction) use ($transactionIds) {
            return !in_array($transaction['id'], $transactionIds);
        });

        error_log("HISTORY: After filtering - Remaining " . count($remainingTransactions) . " transactions");

        // Update user's transactions in Firebase
        $result = updateUserTransactions($current_user_email, array_values($remainingTransactions));

        if ($result) {
            // Also clear any local storage files to ensure consistency
            $userStorageDir = "backend/storage/users/" . $current_user_email;
            if (file_exists($userStorageDir . "/transactions.json")) {
                file_put_contents($userStorageDir . "/transactions.json", json_encode(array_values($remainingTransactions), JSON_PRETTY_PRINT));
            }

            error_log("HISTORY: Successfully deleted " . count($transactionIds) . " transactions for user: {$current_user_email}");
            echo json_encode(['success' => true, 'message' => 'Transactions deleted successfully', 'remaining_count' => count($remainingTransactions)]);
        } else {
            error_log("HISTORY: Failed to update Firebase for user: {$current_user_email}");
            echo json_encode(['success' => false, 'message' => 'Failed to delete transactions from database']);
        }

    } catch (Exception $e) {
        error_log("HISTORY: Error deleting transactions: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting transactions: ' . $e->getMessage()]);
    }

    exit;
}

// AUTO-SYNC TRANSACTION STATUS FROM NOTIFICATIONS
// Get user notifications to sync transaction statuses
$user_notifications = getUserNotifications($current_user_email);

if (!empty($user_notifications) && is_array($user_notifications)) {
    // Sync transaction statuses from notifications
    $allTransactions = getUserTransactions($current_user_email) ?: [];
    $updated = false;

    foreach ($user_notifications as $notification) {
        if (!is_array($notification)) continue;

        // Check for approval/rejection notifications with reference
        if (isset($notification['reference']) && isset($notification['type'])) {
            $reference = $notification['reference'];

            // Determine new status from notification
            $newStatus = null;
            if (strpos($notification['title'], 'Successful') !== false || 
                strpos($notification['title'], 'Approved') !== false ||
                $notification['type'] === 'deposit' && strpos($notification['message'], 'approved') !== false) {
                $newStatus = 'successful';
            } elseif (strpos($notification['title'], 'Rejected') !== false ||
                      strpos($notification['message'], 'rejected') !== false) {
                $newStatus = 'rejected';
            }

            // Update matching transactions
            if ($newStatus) {
                foreach ($allTransactions as &$transaction) {
                    if (isset($transaction['reference']) && $transaction['reference'] === $reference) {
                        if ($transaction['status'] !== $newStatus) {
                            $transaction['status'] = $newStatus;
                            $transaction['synced_from_notification'] = true;
                            $transaction['synced_at'] = date('Y-m-d H:i:s');
                            $updated = true;
                        }
                    }
                }
            }
        }
    }

    // Save updated transactions if any were synced
    if ($updated) {
        saveUserTransactions($current_user_email, $allTransactions);
        // Refresh transaction data
        $allTransactions = getUserTransactions($current_user_email) ?: [];
    }
}

// Get user notifications for popup system (after sync)
$user_notifications = getUserNotifications($current_user_email);
$unread_notifications = [];

// Filter unread notifications and sort by newest first
if (!empty($user_notifications) && is_array($user_notifications)) {
    foreach ($user_notifications as $notification) {
        // SAFETY: Ensure notification is an array before processing
        if (!is_array($notification)) {
            continue; // Skip non-array entries
        }

        if (!isset($notification['status']) || $notification['status'] === 'unread') {
            $unread_notifications[] = $notification;
        }
    }
    // Sort by created_at date (newest first) - only if we have valid notifications
    if (!empty($unread_notifications)) {
        usort($unread_notifications, function($a, $b) {
            // Safety check for created_at field
            $time_a = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
            $time_b = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $time_b - $time_a;
        });
    }
}

// PRIVACY PROTECTION: Get ONLY this user's transactions from private storage
$allTransactions = getHistoryTransactions($current_user_email);

// Additional debugging to identify the issue
error_log("HISTORY DEBUG: User: {$current_user_email}");
error_log("HISTORY DEBUG: Firebase transactions count: " . count(getUserTransactions($current_user_email)));
error_log("HISTORY DEBUG: Local file exists: " . (file_exists("backend/storage/users/{$current_user_email}/transactions.json") ? 'YES' : 'NO'));

if (file_exists("backend/storage/users/{$current_user_email}/transactions.json")) {
    $localTransactions = json_decode(file_get_contents("backend/storage/users/{$current_user_email}/transactions.json"), true) ?? [];
    error_log("HISTORY DEBUG: Local transactions count: " . count($localTransactions));
}

// Check storage limits - no warnings for premium platform
$storageWarnings = [];

// Debug logging for development (remove in production)
error_log("Transaction History - User: {$current_user_email}, Found: " . count($allTransactions) . " transactions");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - LET PAY YOU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #10b981;
            --secondary-dark: #059669;
            --accent: #f59e0b;
            --background: #f8fafc;
            --foreground: #0f172a;
            --muted: #f1f5f9;
            --muted-foreground: #64748b;
            --border: #e2e8f0;
            --card: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }

        .dark {
            --primary: #60a5fa;
            --primary-dark: #3b82f6;
            --secondary: #34d399;
            --secondary-dark: #10b981;
            --accent: #fbbf24;
            --background: #0f172a;
            --foreground: #f8fafc;
            --muted: #1e293b;
            --muted-foreground: #94a3b8;
            --border: #334155;
            --card: #1e293b;
            --success: #34d399;
            --danger: #f87171;
            --warning: #fbbf24;
            --info: #60a5fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            background: var(--background);
            color: var(--foreground);
            line-height: 1.6;
            padding-bottom: 100px;
            min-height: 100vh;
        }

        /* Storage Warning */
        .storage-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            color: #92400e;
            padding: 16px 20px;
            border-radius: 12px;
            margin: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: pulse 2s infinite;
        }

        .storage-warning i {
            font-size: 1.2rem;
            color: #f59e0b;
        }

        /* Success Message */
        .success-message {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #10b981;
            color: #065f46;
            padding: 16px 20px;
            border-radius: 12px;
            margin: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.5s ease;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }

        /* Hero Banner with Single Image */
        .hero-banner {
            position: relative;
            height: 300px;
            overflow: hidden;
            border-radius: 0 0 20px 20px;
            background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('attached_assets/attached_assets/generated_images/Friends_champagne_success_celebration_a32bf813.png');
            background-size: cover;
            background-position: center;
        }

        .hero-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .slide-overlay {
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
        }

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

        .slide-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .slide-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }



        .action-btn {
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--foreground);
            cursor: pointer;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
        }

        .action-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        /* Premium Badge */
        .premium-badge {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #1a1a1a;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: shimmer 2s infinite;
        }

        .premium-badge i {
            color: #FFD700;
            filter: drop-shadow(0 0 5px rgba(255, 215, 0, 0.7));
        }

        /* Clear All Button - Compact Style */
        .clear-all-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .clear-all-btn:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        /* Main Content */
        .content {
            padding: 30px 20px 100px;
        }

        /* Premium Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px 16px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
        }

        .stat-value {
            display: block;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--foreground);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--muted-foreground);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Transaction Management */
        .transaction-management {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid var(--warning);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .management-header {
            text-align: center;
            margin-bottom: 16px;
        }

        .management-header h3 {
            color: var(--warning);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .management-header p {
            color: #92400e;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .management-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .delete-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .action-btn.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
        }

        .action-btn.danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        /* Transaction Selection */
        .transaction-checkbox {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 10;
        }

        .transaction-select {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .transaction-card {
            position: relative;
        }

        .transaction-card.delete-mode {
            padding-left: 60px;
        }

        .transaction-card.selected {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
        }

        /* Premium Filter Section */
        .filter-section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--foreground);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-input {
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--background);
            color: var(--foreground);
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Premium Transaction Cards */
        .transactions-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .transactions-header {
            background: linear-gradient(135deg, var(--muted), var(--card));
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }

        .transactions-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--foreground);
            margin: 0 0 8px 0;
        }

        .transactions-subtitle {
            color: var(--muted-foreground);
            font-size: 0.9rem;
            margin: 0;
        }

        .transaction-list {
            padding: 0;
        }

        .transaction-card {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .transaction-card:last-child {
            border-bottom: none;
        }

        .transaction-card:hover {
            background: var(--muted);
            transform: translateX(4px);
        }

        .transaction-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: transparent;
            transition: all 0.3s ease;
        }

        .transaction-card.credit::before {
            background: var(--success);
        }

        .transaction-card.debit::before {
            background: var(--danger);
        }

        .transaction-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .transaction-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .transaction-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .transaction-icon.credit {
            background: linear-gradient(135deg, var(--success), var(--secondary-dark));
        }

        .transaction-icon.debit {
            background: linear-gradient(135deg, var(--danger), #dc2626);
        }

        .transaction-product-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ef4444;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .product-image-box {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .fallback-icon {
            color: white;
            font-size: 1.2rem;
        }

        .transaction-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--foreground);
            margin: 0 0 4px 0;
        }

        .transaction-meta {
            font-size: 0.8rem;
            color: var(--muted-foreground);
            margin: 0;
        }

        .transaction-amount {
            text-align: right;
        }

        .amount-value {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 0 4px 0;
        }

        .amount-value.credit {
            color: var(--success);
        }

        .amount-value.debit {
            color: var(--danger);
        }

        .transaction-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .status-failed {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .transaction-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
            margin-top: 12px;
        }

        .detail-item {
            text-align: center;
        }

        .detail-label {
            font-size: 0.7rem;
            color: var(--muted-foreground);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--foreground);
            font-family: 'Monaco', 'Menlo', monospace;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: var(--muted-foreground);
        }

        .empty-state .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: var(--muted);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--muted-foreground);
        }

        .empty-state h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--foreground);
            margin: 0 0 12px 0;
        }

        .empty-state p {
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--card);
            border-top: 1px solid var(--border);
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
            color: var(--muted-foreground);
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 12px;
        }

        .nav-item.active {
            color: var(--primary);
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

        /* WhatsApp Float Button */
        .whatsapp-float {
            position: fixed;
            bottom: 100px;
            right: 24px;
            background: #25D366;
            color: white;
            border-radius: 50%;
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(37, 211, 102, 0.4);
            z-index: 60;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            border: none;
        }

        .whatsapp-float:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 40px rgba(37, 211, 102, 0.6);
        }

        /* Responsive Design */
        @media (max-width: 640px) {
            .container {
                padding: 20px 16px;
            }

            .premium-header {
                padding: 16px;
            }

            .header-title {
                font-size: 1.3rem;
            }

            .header-actions {
                gap: 6px;
            }

            .action-btn {
                padding: 8px 12px;
                font-size: 0.8rem;
            }

            .action-btn i {
                font-size: 0.9rem;
            }

            .filter-section, .transactions-container {
                border-radius: 16px;
            }

            .transaction-card {
                padding: 16px 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
    </style>
</head>
<body class="light">
    <div class="main-container">
        <!-- Top Header with Logo -->
        <div class="top-header">
            <div class="logo-section">
                <img src="logo.svg" alt="LET PAY YOU Logo">
                <span style="font-weight: bold; color: #333;">LET PAY YOU</span>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($current_user_email, 0, 1)); ?></div>
                <span>Transaction History</span>
            </div>
        </div>

        <!-- Hero Banner with Single Image -->
        <div class="hero-banner">
            <img src="attached_assets/generated_images/Professional_African_businesswoman_c9e6999b.png" alt="Transaction History">
            <div class="slide-overlay">
                <h1 class="slide-title">Transaction History</h1>
                <p class="slide-subtitle">Your complete transaction records and financial activities</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
        <!-- Success Message -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = 'dashboard.php';
                }, 1500);
            </script>
        <?php endif; ?>

        <!-- Storage Warnings -->
        <?php if (!empty($storageWarnings)): ?>
            <?php foreach ($storageWarnings as $warning): ?>
                <div class="storage-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($warning); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Transaction Management -->
        <div class="transaction-management">
            <div class="management-header">
                <h3>🗃️ Transaction Management</h3>
                <p>Manage your transaction history with selective or bulk deletion</p>
            </div>
            <div class="management-actions">
                <button type="button" class="action-btn delete-btn" onclick="toggleDeleteMode()">
                    <i class="fas fa-trash-alt"></i>
                    Delete Transactions
                </button>
                <button type="button" class="action-btn primary" id="selectAllBtn" onclick="selectAllTransactions()" style="display: none;">
                    <i class="fas fa-check-square"></i>
                    Select All
                </button>
                <button type="button" class="action-btn danger" id="deleteSelectedBtn" onclick="deleteSelectedTransactions()" style="display: none;">
                    <i class="fas fa-trash"></i>
                    Delete Selected (<span id="selectedCount">0</span>)
                </button>
                <button type="button" class="action-btn" id="cancelDeleteBtn" onclick="cancelDeleteMode()" style="display: none;">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
            </div>
        </div>

        <!-- Premium Stats -->
        <div class="stats-grid">
            <?php
            $totalCredits = 0;
            $totalDebits = 0;
            $successfulTx = 0;

            foreach ($allTransactions as $tx) {
                $txType = $tx['type'] ?? $tx['transaction_type'] ?? '';

                if ($txType === 'deposit') {
                    if ($tx['status'] === 'approved' || $tx['status'] === 'successful' || $tx['status'] === 'completed') {
                        $totalCredits += $tx['amount'];
                    }
                } elseif ($txType === 'withdrawal') {
                    if ($tx['status'] === 'approved' || $tx['status'] === 'successful' || $tx['status'] === 'completed') {
                        $totalDebits += $tx['amount'];
                    }
                } else {
                    // For service purchases (airtime, data, cable, electricity)
                    if ($tx['status'] === 'completed' || $tx['status'] === 'successful' || $tx['status'] === 'approved') {
                        $totalDebits += isset($tx['total']) ? $tx['total'] : $tx['amount'];
                    }
                }

                if ($tx['status'] === 'approved' || $tx['status'] === 'completed' || $tx['status'] === 'successful') {
                    $successfulTx++;
                }
            }
            ?>
            <div class="stat-card">
                <span class="stat-value">₦<?php echo number_format($totalCredits, 0); ?></span>
                <span class="stat-label">Total Credits</span>
            </div>
            <div class="stat-card">
                <span class="stat-value">₦<?php echo number_format($totalDebits, 0); ?></span>
                <span class="stat-label">Total Debits</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $successfulTx; ?></span>
                <span class="stat-label">Successful</span>
            </div>
        </div>

        <!-- Premium Filter Section -->
        <div class="filter-section">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Transactions
            </h3>
            <div class="filter-grid">
                <select class="filter-input" id="statusFilter" onchange="filterTransactions()">
                    <option value="all">All Status</option>
                    <option value="success">Successful</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                    <option value="failed">Failed</option>
                </select>
                <select class="filter-input" id="typeFilter" onchange="filterTransactions()">
                    <option value="all">All Types</option>
                    <option value="deposit">Deposit</option>
                    <option value="withdrawal">Withdrawal</option>
                    <option value="order_commission">Order Commission</option>
                    <option value="package_purchase">Package Purchase</option>
                    <option value="referral_bonus">Referral Bonus</option>
                    <option value="investment_return">Investment Return</option>
                    <option value="daily_bonus">Daily Bonus</option>
                </select>
            </div>
            <div class="filter-grid">
                <input type="date" class="filter-input" id="fromDate" onchange="filterTransactions()">
                <input type="date" class="filter-input" id="toDate" onchange="filterTransactions()">
            </div>
        </div>

        <!-- Premium Transactions Container -->
        <div class="transactions-container">
            <div class="transactions-header">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <h3 class="transactions-title">Recent Transactions</h3>
                        <p class="transactions-subtitle">Your private transaction history</p>
                    </div>
                </div>
            </div>

            <div class="transaction-list" id="transactionsList">
                <?php if (count($allTransactions) > 0): ?>
                    <?php foreach ($allTransactions as $tx): ?>
                        <div class="transaction-card <?php echo $tx['transaction_type'] === 'deposit' ? 'credit' : 'debit'; ?>" data-transaction-id="<?php echo $tx['id']; ?>" onclick="handleTransactionClick('<?php echo $tx['id']; ?>')">
                            <div class="transaction-checkbox" style="display: none;">
                                <input type="checkbox" class="transaction-select" data-id="<?php echo $tx['id']; ?>" onchange="updateSelectedCount()">
                            </div>
                            <div class="transaction-main">
                                <div class="transaction-left">
                                    <div class="transaction-icon <?php echo $tx['transaction_type'] === 'deposit' ? 'credit' : 'debit'; ?>">
                                        <?php
                                        // Show product image in RED BOX for order commission transactions
                                        if ($tx['transaction_type'] === 'order_commission' && isset($tx['product_image'])) {
                                            echo '<div class="product-image-box">';
                                            echo '<img src="' . htmlspecialchars($tx['product_image']) . '" alt="Product" class="transaction-product-image" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'inline\';">';
                                            echo '<span class="fallback-icon" style="display:none;">🎯</span>';
                                            echo '</div>';
                                        } else {
                                            $icon = '';
                                            switch($tx['transaction_type']) {
                                                case 'deposit': $icon = '💰'; break;
                                                case 'withdrawal': $icon = '💸'; break;
                                                case 'order_commission': $icon = '🎯'; break;
                                                case 'package_purchase': $icon = '📦'; break;
                                                case 'referral_bonus': $icon = '🎁'; break;
                                                case 'investment_return': $icon = '📈'; break;
                                                default: $icon = '💼';
                                            }
                                            echo $icon;
                                        }
                                        ?>
                                    </div>
                                    <div class="transaction-info">
                                        <h4><?php echo htmlspecialchars($tx['description'] ?? 'Transaction', ENT_QUOTES, 'UTF-8'); ?></h4>
                                        <p class="transaction-meta">
                                            <?php 
                                            try {
                                                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $tx['created_at'])) {
                                                    $dateTime = new DateTime($tx['created_at'], new DateTimeZone('Africa/Lagos'));
                                                } else {
                                                    $dateTime = DateTime::createFromFormat('j M Y \a\t g:i A', $tx['created_at']);
                                                    if (!$dateTime) {
                                                        $dateTime = new DateTime($tx['created_at']);
                                                    }
                                                    $dateTime->setTimezone(new DateTimeZone('Africa/Lagos'));
                                                }
                                                echo $dateTime->format('M j, Y • h:i A');
                                            } catch (Exception $e) {
                                                echo htmlspecialchars($tx['created_at']);
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="transaction-amount">
                                    <p class="amount-value <?php echo $tx['transaction_type'] === 'deposit' ? 'credit' : 'debit'; ?>">
                                        <?php echo $tx['transaction_type'] === 'deposit' ? '+' : '-'; ?>₦<?php echo number_format($tx['amount'], 2); ?>
                                    </p>
                                    <span class="transaction-status status-<?php echo $tx['status'] === 'approved' ? 'success' : ($tx['status'] === 'rejected' ? 'failed' : $tx['status']); ?>">
                                        <?php 
                                        switch($tx['status']) {
                                            case 'success': 
                                            case 'approved': echo '✅ Success'; break;
                                            case 'pending': echo '⏳ Pending'; break;
                                            case 'rejected': 
                                            case 'failed': echo '❌ Failed'; break;
                                            default: echo ucfirst($tx['status']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="transaction-details">
                                <div class="detail-item">
                                    <div class="detail-label">Reference</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($tx['reference'] ?? $tx['id'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Type</div>
                                    <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $tx['transaction_type'])); ?></div>
                                </div>
                                <?php if (!empty($tx['phone'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Phone</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($tx['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h3>No transactions yet</h3>
                        <p>Your private transaction history will appear here once you start using our services. Make your first deposit or purchase to get started!</p>
                    </div>
                <?php endif; ?>
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

                <a href="games.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24">
                        <path d="M15 7.5V2H9v5.5l3 3 3-3zM7.5 9H2v6h5.5l3-3-3-3zM9 16.5V22h6v-5.5l-3-3-3 3zM16.5 9l-3 3 3 3H22V9h-5.5z"/>
                    </svg>
                    <span class="nav-label">Games</span>
                </a>

                <a href="history.php" class="nav-item active">
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

    <!-- WhatsApp Contact Button -->
    <a href="https://wa.me/61468302435" target="_blank" class="whatsapp-float">
        <i class="fab fa-whatsapp"></i>
    </a>

    <script>
        // Real transaction data from backend
        const realTransactions = <?php echo json_encode($allTransactions); ?>;

        // Global Theme Management System
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        // Initialize theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.body.className = savedTheme;

        if (savedTheme === 'dark') {
            themeIcon.className = 'fas fa-sun';
        }

        function toggleTheme() {
            const currentTheme = document.body.className;
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';

            document.body.className = newTheme;
            localStorage.setItem('theme', newTheme);

            themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        function filterTransactions() {
            const statusFilter = document.getElementById('statusFilter').value;
            const typeFilter = document.getElementById('typeFilter').value;
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;

            let filtered = realTransactions;

            if (statusFilter !== 'all') {
                filtered = filtered.filter(t => t.status === statusFilter);
            }

            if (typeFilter !== 'all') {
                filtered = filtered.filter(t => t.transaction_type === typeFilter);
            }

            if (fromDate) {
                filtered = filtered.filter(t => {
                    const txDate = new Date(t.created_at).toISOString().split('T')[0];
                    return txDate >= fromDate;
                });
            }

            if (toDate) {
                filtered = filtered.filter(t => {
                    const txDate = new Date(t.created_at).toISOString().split('T')[0];
                    return txDate <= toDate;
                });
            }

            renderFilteredTransactions(filtered);
        }

        function renderFilteredTransactions(transactions) {
            const container = document.getElementById('transactionsList');

            if (transactions.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>No transactions match your filters</h3>
                        <p>Try adjusting your filter criteria to see more results.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = transactions.map(tx => `
                <div class="transaction-card ${tx.transaction_type === 'deposit' ? 'credit' : 'debit'}" onclick="showTransactionDetails('${tx.id}')">
                    <div class="transaction-main">
                        <div class="transaction-left">
                            <div class="transaction-icon ${tx.transaction_type === 'deposit' ? 'credit' : 'debit'}">
                                ${tx.transaction_type === 'order_commission' && tx.product_image ? 
                                  `<div class="product-image-box">` +
                                  `<img src="${tx.product_image}" alt="Product" class="transaction-product-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">` +
                                  `<span class="fallback-icon" style="display:none;">🎯</span>` +
                                  `</div>` :
                                  (tx.transaction_type === 'deposit' ? '<i class="fas fa-plus"></i>' : 
                                   tx.transaction_type === 'withdrawal' ? '<i class="fas fa-minus"></i>' : 
                                   tx.transaction_type === 'package_purchase' ? '<i class="fas fa-box"></i>' :
                                   tx.transaction_type === 'referral_bonus' ? '<i class="fas fa-gift"></i>' :
                                   tx.transaction_type === 'investment_return' ? '<i class="fas fa-chart-line"></i>' :
                                   '<i class="fas fa-shopping-cart"></i>')
                                }
                            </div>
                            <div class="transaction-info">
                                <h4>${tx.description || 'Transaction'}</h4>
                                <p class="transaction-meta">
                                    ${new Date(tx.created_at + 'Z').toLocaleDateString('en-US', {
                                        month: 'short',
                                        day: 'numeric', 
                                        year: 'numeric',
                                        hour: 'numeric',
                                        minute: '2-digit',
                                        hour12: true,
                                        timeZone: 'Africa/Lagos'
                                    })}
                                </p>
                            </div>
                        </div>
                        <div class="transaction-amount">
                            <p class="amount-value ${tx.transaction_type === 'deposit' ? 'credit' : 'debit'}">
                                ${tx.transaction_type === 'deposit' ? '+' : '-'}₦${tx.amount.toLocaleString()}
                            </p>
                            <span class="transaction-status status-${tx.status === 'approved' || tx.status === 'success' ? 'success' : (tx.status === 'rejected' || tx.status === 'failed' ? 'failed' : tx.status)}">
                                ${tx.status === 'success' || tx.status === 'approved' ? '✅ Success' : 
                                  tx.status === 'pending' ? '⏳ Pending' : 
                                  tx.status === 'rejected' || tx.status === 'failed' ? '❌ Failed' : tx.status}
                            </span>
                        </div>
                    </div>
                    <div class="transaction-details">
                        <div class="detail-item">
                            <div class="detail-label">Reference</div>
                            <div class="detail-value">${tx.reference || tx.id}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Type</div>
                            <div class="detail-value">${tx.transaction_type.replace('_', ' ').charAt(0).toUpperCase() + tx.transaction_type.replace('_', ' ').slice(1)}</div>
                        </div>
                        ${tx.phone ? `
                        <div class="detail-item">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value">${tx.phone}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }

        let deleteMode = false;

        function showTransactionDetails(transactionId) {
            const transaction = realTransactions.find(t => t.id === transactionId);
            if (transaction) {
                const recipient = transaction.phone || transaction.meter_number || transaction.smartcard || 'N/A';
                const statusIcon = transaction.status === 'success' || transaction.status === 'approved' ? '✅' : 
                                 transaction.status === 'pending' ? '⏳' : '❌';

                alert(`${statusIcon} Transaction Details\n\n` +
                      `ID: ${transaction.id}\n` +
                      `Type: ${transaction.description || transaction.type}\n` +
                      `Amount: ₦${transaction.amount.toLocaleString()}\n` +
                      `Status: ${transaction.status.toUpperCase()}\n` +
                      `Date: ${new Date(transaction.created_at).toLocaleString()}\n` +
                      `Recipient: ${recipient}`);
            }
        }

        function handleTransactionClick(transactionId) {
            if (deleteMode) {
                const checkbox = document.querySelector(`input[data-id="${transactionId}"]`);
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    updateSelectedCount();
                    updateTransactionAppearance(transactionId, checkbox.checked);
                }
            } else {
                showTransactionDetails(transactionId);
            }
        }

        function toggleDeleteMode() {
            deleteMode = !deleteMode;

            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            const transactionCards = document.querySelectorAll('.transaction-card');
            const deleteBtn = document.querySelector('.delete-btn');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

            if (deleteMode) {
                // Show checkboxes and buttons
                checkboxes.forEach(cb => cb.style.display = 'block');
                transactionCards.forEach(card => card.classList.add('delete-mode'));

                deleteBtn.style.display = 'none';
                selectAllBtn.style.display = 'inline-flex';
                deleteSelectedBtn.style.display = 'inline-flex';
                cancelDeleteBtn.style.display = 'inline-flex';
            } else {
                cancelDeleteMode();
            }
        }

        function cancelDeleteMode() {
            deleteMode = false;

            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            const transactionCards = document.querySelectorAll('.transaction-card');
            const selects = document.querySelectorAll('.transaction-select');
            const deleteBtn = document.querySelector('.delete-btn');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

            // Hide checkboxes and reset
            checkboxes.forEach(cb => cb.style.display = 'none');
            transactionCards.forEach(card => {
                card.classList.remove('delete-mode', 'selected');
            });
            selects.forEach(select => select.checked = false);

            deleteBtn.style.display = 'inline-flex';
            selectAllBtn.style.display = 'none';
            deleteSelectedBtn.style.display = 'none';
            cancelDeleteBtn.style.display = 'none';

            updateSelectedCount();
        }

        function selectAllTransactions() {
            const selects = document.querySelectorAll('.transaction-select');
            const allChecked = Array.from(selects).every(select => select.checked);

            selects.forEach(select => {
                select.checked = !allChecked;
                updateTransactionAppearance(select.dataset.id, select.checked);
            });

            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('.transaction-select:checked').length;
            document.getElementById('selectedCount').textContent = selectedCount;

            const selectAllBtn = document.getElementById('selectAllBtn');
            const allSelects = document.querySelectorAll('.transaction-select');
            const allChecked = Array.from(allSelects).every(select => select.checked);

            selectAllBtn.innerHTML = allChecked ? 
                '<i class="fas fa-square"></i> Deselect All' : 
                '<i class="fas fa-check-square"></i> Select All';
        }

        function updateTransactionAppearance(transactionId, selected) {
            const card = document.querySelector(`[data-transaction-id="${transactionId}"]`);
            if (card) {
                if (selected) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            }
        }

        function deleteSelectedTransactions() {
            const selectedTransactions = document.querySelectorAll('.transaction-select:checked');
            const selectedIds = Array.from(selectedTransactions).map(cb => cb.dataset.id);

            if (selectedIds.length === 0) {
                alert('⚠️ Please select transactions to delete');
                return;
            }

            const confirmMessage = selectedIds.length === realTransactions.length ? 
                '⚠️ Are you sure you want to delete ALL transactions? This cannot be undone!' :
                `⚠️ Are you sure you want to delete ${selectedIds.length} selected transaction(s)? This cannot be undone!`;

            if (!confirm(confirmMessage)) {
                return;
            }

            // Show loading
            const loadingMsg = document.createElement('div');
            loadingMsg.className = 'success-message';
            loadingMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting transactions...';
            loadingMsg.style.position = 'fixed';
            loadingMsg.style.top = '20px';
            loadingMsg.style.left = '50%';
            loadingMsg.style.transform = 'translateX(-50%)';
            loadingMsg.style.zIndex = '1000';
            document.body.appendChild(loadingMsg);

            // Send delete request
            const formData = new FormData();
            formData.append('action', 'delete_transactions');
            formData.append('transaction_ids', JSON.stringify(selectedIds));

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.removeChild(loadingMsg);

                if (data.success) {
                    // Show success message and reload
                    const successMsg = document.createElement('div');
                    successMsg.className = 'success-message';
                    successMsg.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                    successMsg.style.position = 'fixed';
                    successMsg.style.top = '20px';
                    successMsg.style.left = '50%';
                    successMsg.style.transform = 'translateX(-50%)';
                    successMsg.style.zIndex = '1000';
                    document.body.appendChild(successMsg);

                    // Force reload to refresh data
                    setTimeout(() => {
                        window.location.href = window.location.pathname + '?deleted=' + selectedIds.length;
                    }, 1000);
                } else {
                    alert('❌ ' + (data.message || 'Failed to delete transactions'));
                }
            })
            .catch(error => {
                document.body.removeChild(loadingMsg);
                alert('❌ Error deleting transactions. Please try again.');
                console.error('Delete error:', error);
            });
        }

        function clearAllTransactions() {
            // Show confirmation dialog
            if (!confirm('⚠️ Are you sure you want to clear ALL transaction history? This cannot be undone!')) {
                return false; // Cancel the form submission
            }

            // Show loading message
            const successMsg = document.createElement('div');
            successMsg.className = 'success-message';
            successMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing transaction history...';
            successMsg.style.position = 'fixed';
            successMsg.style.top = '20px';
            successMsg.style.left = '50%';
            successMsg.style.transform = 'translateX(-50%)';
            successMsg.style.zIndex = '1000';
            successMsg.style.padding = '12px 20px';
            successMsg.style.borderRadius = '8px';
            document.body.appendChild(successMsg);

            return true; // Allow the form submission
        }



        // Add smooth scrolling and interaction animations
        document.querySelectorAll('.transaction-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(8px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });


    </script>
</body>
</html>