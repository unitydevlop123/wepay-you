<?php
// Include authentication system
require_once 'auth.php';


// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Enable proper authentication
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Get user email from authenticated session
$userEmail = $_SESSION['email'];

// Ensure Firebase functions are available
if (!function_exists('getUserNotifications')) {
    die('Firebase functions not loaded. Please check firebase_setup.php');
}

// Handle JSON list action for popup (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
    // Get user notifications
    $notifications = getUserNotifications($userEmail);

    // Sort by created_at date (newest first)
    if (!empty($notifications)) {
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'notifications' => $notifications ?: []
    ]);
    exit;
}

// Handle JSON actions (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $notificationId = $_POST['notification_id'] ?? '';

    // Ensure user notifications are loaded for any POST action
    // This is a safety measure to prevent re-fetching if not needed for a specific action
    $notifications = getUserNotifications($userEmail);
    if (!is_array($notifications)) {
        $notifications = [];
    }

    if ($action === 'mark_read' && !empty($notificationId)) {
        // Mark specific notification as read
        foreach ($notifications as &$notification) {
            if (is_array($notification) && isset($notification['id']) && $notification['id'] === $notificationId) {
                $notification['status'] = 'read';
                $notification['read_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        unset($notification);

        // Save updated notifications
        saveUserNotifications($userEmail, $notifications);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'mark_all_read') {
        // Mark all as read
        foreach ($notifications as &$notification) {
            if (is_array($notification)) {
                $notification['status'] = 'read';
                $notification['read_at'] = date('Y-m-d H:i:s');
            }
        }
        unset($notification);

        // Save updated notifications
        saveUserNotifications($userEmail, $notifications);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_notification' && !empty($notificationId)) {
        // Remove specific notification
        $notifications = array_filter($notifications, function($notification) use ($notificationId) {
            return $notification['id'] !== $notificationId;
        });

        // Re-index array
        $notifications = array_values($notifications);

        // Save updated notifications
        saveUserNotifications($userEmail, $notifications);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_notifications' && !empty($_POST['notification_ids'])) {
        $notificationIds = json_decode($_POST['notification_ids'], true);

        if (is_array($notificationIds) && !empty($notificationIds)) {
            // Remove selected notifications (with safety check)
            $notifications = array_filter($notifications, function($notification) use ($notificationIds) {
                // Safety: ensure notification is an array
                if (!is_array($notification) || !isset($notification['id'])) {
                    return false; // Remove invalid notifications
                }
                return !in_array($notification['id'], $notificationIds);
            });

            // Re-index array
            $notifications = array_values($notifications);

            // Save updated notifications
            saveUserNotifications($userEmail, $notifications);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'deleted_count' => count($notificationIds)]);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid notification IDs']);
        exit;
    }
}

// Get user notifications for the main page display
$notifications = getUserNotifications($userEmail);

// Ensure we have an array
if (!is_array($notifications)) {
    $notifications = [];
}

// Sort notifications by date (newest first)
if (!empty($notifications)) {
    // Filter out invalid notifications (ensure all are arrays)
    $notifications = array_filter($notifications, function($notification) {
        return is_array($notification);
    });

    // Re-index array after filtering
    $notifications = array_values($notifications);

    // Sort only if we have valid notifications
    if (!empty($notifications)) {
        usort($notifications, function($a, $b) {
            // Safety check for created_at field
            $time_a = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
            $time_b = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $time_b - $time_a;
        });
    }
}

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    // Safety check: ensure notification is an array
    if (!is_array($notification)) {
        continue;
    }

    if (!isset($notification['status']) || $notification['status'] === 'unread') {
        $unread_count++;
    }
}

// Function to format time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';

    return date('M d, Y', strtotime($datetime));
}

// Function to get notification icon
function getNotificationIcon($type) {
    switch ($type) {
        case 'welcome':
        case 'success':
            return 'fas fa-check-circle';
        case 'welcome_back':
            return 'fas fa-hand-wave';
        case 'deposit':
            return 'fas fa-plus-circle';
        case 'withdrawal':
            return 'fas fa-minus-circle';
        case 'order_completed':
            return 'fas fa-shopping-cart';
        case 'commission':
            return 'fas fa-coins';
        case 'package_purchased':
            return 'fas fa-gift';
        case 'referral':
            return 'fas fa-users';
        case 'bonus':
            return 'fas fa-gift';
        case 'warning':
            return 'fas fa-exclamation-triangle';
        case 'error':
            return 'fas fa-times-circle';
        default:
            return 'fas fa-bell';
    }
}

// Function to get notification color
function getNotificationColor($type) {
    switch ($type) {
        case 'welcome':
        case 'success':
        case 'order_completed':
        case 'commission':
            return '#10b981';
        case 'welcome_back':
            return '#3b82f6';
        case 'deposit':
        case 'bonus':
            return '#3b82f6';
        case 'withdrawal':
            return '#f59e0b';
        case 'package_purchased':
            return '#8b5cf6';
        case 'referral':
            return '#06b6d4';
        case 'warning':
            return '#f59e0b';
        case 'error':
            return '#ef4444';
        default:
            return '#6b7280';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET PAY YOU - Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .back-btn {
            background: #007bff;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .back-btn:hover {
            background: #0056b3;
        }

        .logo img {
            height: 35px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-count {
            background: #ef4444;
            color: white;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
        }

        /* Banner */
        .banner {
            position: relative;
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), 
                       url('attached_assets/attached_assets/generated_images/Family_celebrating_game_wins_2a6b2e3f.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .banner h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .banner p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* Content */
        .content {
            padding: 0;
        }

        /* Notification Actions */
        .notification-actions {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .action-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .action-btn.primary {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }

        .action-btn.primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }

        /* Notifications List */
        .notifications-list {
            background: white;
        }

        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-checkbox {
            display: none;
            align-items: center;
            justify-content: center;
            margin-top: 2px;
        }

        .notification-checkbox.show {
            display: flex;
        }

        .notification-select {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #3b82f6;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
        }

        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .notification-message {
            color: #6b7280;
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #9ca3af;
        }

        .notification-time {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notification-amount {
            font-weight: 600;
            color: #10b981;
        }

        .notification-actions-menu {
            display: flex;
            gap: 5px;
        }

        .action-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .action-icon:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .action-icon.delete:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #374151;
        }

        .empty-state p {
            font-size: 1rem;
            line-height: 1.5;
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .loading i {
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .banner h1 {
                font-size: 1.5rem;
            }

            .notification-actions {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }

            .notification-item {
                padding: 15px;
            }

            .notification-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }

        /* Success Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <div class="logo">
                    <img src="logo.svg" alt="LET PAY YOU">
                </div>
            </div>
            <div class="header-actions">
                <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <span class="notification-count"><?= $unread_count ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Banner -->
        <div class="banner">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <p>Stay updated with your latest activities and earnings</p>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Notification Actions -->
            <?php if (isset($notifications) && !empty($notifications)): ?>
            <div class="notification-actions">
                <div>
                    <strong><?= count($notifications) ?> Total</strong> • 
                    <span style="color: #ef4444;"><?= isset($unread_count) ? $unread_count : 0 ?> Unread</span> • 
                    <span style="color: #3b82f6;" id="selected-count">0 Selected</span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="action-btn" onclick="toggleDeleteMode()" id="delete-mode-btn">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <button class="action-btn primary" onclick="markAllAsRead()">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                    <?php endif; ?>
                    <button class="action-btn" onclick="refreshNotifications()">
                        <i class="fas fa-refresh"></i> Refresh
                    </button>
                    <button class="action-btn" onclick="deleteSelected()" id="delete-btn" style="background: #ef4444; color: white; display: none;">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notifications List -->
            <div class="notifications-list">
                <?php if (isset($notifications) && !empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php 
                        $isUnread = !isset($notification['status']) || $notification['status'] === 'unread';
                        $type = isset($notification['type']) ? $notification['type'] : 'default';
                        $icon = getNotificationIcon($type);
                        $color = getNotificationColor($type);
                        ?>
                        <div class="notification-item <?= $isUnread ? 'unread' : '' ?>" 
                             data-id="<?= htmlspecialchars($notification['id']) ?>">
                            <div class="notification-checkbox">
                                <input type="checkbox" class="notification-select" 
                                       value="<?= htmlspecialchars($notification['id']) ?>" 
                                       onchange="updateSelectedCount()">
                            </div>
                            <div class="notification-icon" style="background-color: <?= $color ?>;">
                                <i class="<?= $icon ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?= htmlspecialchars($notification['title']) ?>
                                </div>
                                <div class="notification-message">
                                    <?= htmlspecialchars($notification['message']) ?>
                                </div>
                                <div class="notification-meta">
                                    <div class="notification-time">
                                        <i class="fas fa-clock"></i>
                                        <?= timeAgo($notification['created_at']) ?>
                                    </div>
                                    <?php if (isset($notification['amount']) && $notification['amount'] > 0): ?>
                                        <div class="notification-amount">
                                            +₦<?= number_format($notification['amount'], 2) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="notification-actions-menu">
                                <!-- Always show view eye icon -->
                                <div class="action-icon" onclick="viewNotification('<?= htmlspecialchars($notification['id']) ?>')" title="View notification details">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <!-- Show mark as read only for unread notifications -->
                                <?php if ($isUnread): ?>
                                <div class="action-icon" onclick="markAsRead('<?= htmlspecialchars($notification['id']) ?>')" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </div>
                                <?php else: ?>
                                <div class="action-icon" style="color: #10b981; opacity: 0.5;" title="Already read">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications Yet</h3>
                        <p>You'll see important updates, earnings, and activities here when they happen.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Declare all functions at the very beginning and make them globally available immediately
        let deleteMode = false;

        // Toggle delete mode - show/hide checkboxes
        function toggleDeleteMode() {
            const checkboxes = document.querySelectorAll('.notification-checkbox');
            const notificationItems = document.querySelectorAll('.notification-item');
            const deleteModeBtn = document.getElementById('delete-mode-btn');

            deleteMode = !deleteMode;

            if (deleteMode) {
                // Show checkboxes and change button to "Select All"
                checkboxes.forEach(checkbox => {
                    checkbox.classList.add('show');
                });

                // Make entire notification items clickable
                notificationItems.forEach(item => {
                    item.style.cursor = 'pointer';
                    item.addEventListener('click', toggleNotificationSelection);
                });

                deleteModeBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
                deleteModeBtn.onclick = selectAll;
            } else {
                // Hide checkboxes and change button back to "Delete"
                checkboxes.forEach(checkbox => {
                    checkbox.classList.remove('show');
                    const input = checkbox.querySelector('input');
                    if (input) input.checked = false;
                });

                // Remove click listeners and reset cursor
                notificationItems.forEach(item => {
                    item.style.cursor = 'default';
                    item.removeEventListener('click', toggleNotificationSelection);
                });

                deleteModeBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
                deleteModeBtn.onclick = toggleDeleteMode;
                updateSelectedCount();
            }
        }

        // Toggle notification selection when clicking on the item
        function toggleNotificationSelection(event) {
            // Don't trigger if clicking on action buttons
            if (event.target.closest('.notification-actions-menu') || 
                event.target.closest('.action-icon') ||
                event.target.closest('.notification-checkbox')) {
                return;
            }

            const item = event.currentTarget;
            const checkbox = item.querySelector('.notification-select');

            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                updateSelectedCount();
            }
        }

        // Update selected count and show/hide delete button
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.notification-select:checked');
            const count = selected.length;
            const selectedCountEl = document.getElementById('selected-count');
            const deleteBtn = document.getElementById('delete-btn');
            const deleteModeBtn = document.getElementById('delete-mode-btn');

            selectedCountEl.textContent = `${count} Selected`;

            if (deleteMode && count > 0) {
                deleteBtn.style.display = 'block';
                deleteModeBtn.innerHTML = '<i class="fas fa-times"></i> Deselect All';
                deleteModeBtn.onclick = deselectAll;
            } else if (deleteMode) {
                deleteBtn.style.display = 'none';
                deleteModeBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
                deleteModeBtn.onclick = selectAll;
            }
        }

        // Select all notifications
        function selectAll() {
            if (!deleteMode) {
                toggleDeleteMode();
                return;
            }
            const checkboxes = document.querySelectorAll('.notification-select');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        }

        // Deselect all notifications
        function deselectAll() {
            const checkboxes = document.querySelectorAll('.notification-select');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        // Delete selected notifications
        function deleteSelected() {
            const selected = document.querySelectorAll('.notification-select:checked');
            const count = selected.length;

            if (count === 0) {
                alert('Please select notifications to delete.');
                return;
            }

            const confirmMessage = count === 1 
                ? 'Are you sure you want to permanently delete this notification?' 
                : `Are you sure you want to permanently delete these ${count} notifications?`;

            if (!confirm(confirmMessage)) {
                return;
            }

            // Get selected notification IDs
            const notificationIds = Array.from(selected).map(checkbox => checkbox.value);

            // Show loading state
            selected.forEach(checkbox => {
                const item = checkbox.closest('.notification-item');
                if (item) {
                    item.style.opacity = '0.5';
                    item.style.pointerEvents = 'none';
                }
            });

            // Send bulk delete request
            const formData = new FormData();
            formData.append('action', 'delete_notifications');
            formData.append('notification_ids', JSON.stringify(notificationIds));

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, just reload the page as the delete likely worked
                    location.reload();
                    return {};
                }
            })
            .then(data => {
                if (data.success || Object.keys(data).length === 0) {
                    // Delete successful - reload page to refresh notifications
                    location.reload();
                } else {
                    // Restore items state if failed
                    selected.forEach(checkbox => {
                        const item = checkbox.closest('.notification-item');
                        if (item) {
                            item.style.opacity = '1';
                            item.style.pointerEvents = 'auto';
                        }
                    });
                    alert('Failed to delete notifications. Please try again.');
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                // Restore items state if failed
                selected.forEach(checkbox => {
                    const item = checkbox.closest('.notification-item');
                    if (item) {
                        item.style.opacity = '1';
                        item.style.pointerEvents = 'auto';
                    }
                });
                alert('Error deleting notifications. Please try again later.');
            });
        }

        // Mark single notification as read
        function markAsRead(notificationId) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&notification_id=${encodeURIComponent(notificationId)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return { success: false, error: 'Invalid response format' };
                }
            })
            .then(data => {
                if (data && data.success) {
                    // Remove unread styling
                    const item = document.querySelector(`[data-id="${notificationId}"]`);
                    if (item) {
                        item.classList.remove('unread');

                        // Update the check icon to show it's read
                        const checkIcon = item.querySelectorAll('.action-icon')[1]; // Second action icon (check)
                        if (checkIcon) {
                            checkIcon.style.color = '#10b981';
                            checkIcon.style.opacity = '0.5';
                            checkIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                            checkIcon.title = 'Already read';
                            checkIcon.onclick = null;
                        }
                    }

                    // Update notification count
                    updateNotificationCount();
                } else {
                    alert('Failed to mark as read. Please try again.');
                }
            })
            .catch(error => {
                console.error('Mark as read error:', error);
                alert('Error marking as read. Please try again.');
            });
        }

        // Mark all notifications as read
        function markAllAsRead() {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return { success: false, error: 'Invalid response format' };
                }
            })
            .then(data => {
                if (data && data.success) {
                    // Remove unread styling from all items
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');

                        // Update the check icon to show it's read
                        const actionIcons = item.querySelectorAll('.action-icon');
                        if (actionIcons.length >= 2) {
                            const checkIcon = actionIcons[1]; // Second action icon (check)
                            checkIcon.style.color = '#10b981';
                            checkIcon.style.opacity = '0.5';
                            checkIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                            checkIcon.title = 'Already read';
                            checkIcon.onclick = null;
                        }
                    });

                    // Update notification count
                    updateNotificationCount();

                    // Hide the mark all read button
                    const markAllBtn = document.querySelector('.action-btn.primary');
                    if (markAllBtn) {
                        markAllBtn.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error marking all as read. Please try again.');
            });
        }

        // Refresh notifications
        function refreshNotifications() {
            location.reload();
        }

        // Update notification count
        function updateNotificationCount() {
            const unreadItems = document.querySelectorAll('.notification-item.unread').length;
            const countElement = document.querySelector('.notification-count');

            if (unreadItems === 0) {
                if (countElement) {
                    countElement.remove();
                }
            } else {
                if (countElement) {
                    countElement.textContent = unreadItems;
                }
            }

            // Update the meta information
            const metaElement = document.querySelector('.notification-actions strong');
            if (metaElement) {
                const totalCount = document.querySelectorAll('.notification-item').length;
                metaElement.parentNode.innerHTML = `<strong>${totalCount} Total</strong> • <span style="color: #ef4444;">${unreadItems} Unread</span>`;
            }
        }

        // View notification (show details WITHOUT marking as read)
        function viewNotification(notificationId) {
            // Find the notification element
            const item = document.querySelector(`[data-id="${notificationId}"]`);
            if (!item) return;

            // Get notification details
            const title = item.querySelector('.notification-title').textContent;
            const message = item.querySelector('.notification-message').textContent;
            const amount = item.querySelector('.notification-amount');
            const time = item.querySelector('.notification-time').textContent;

            // Create a better modal instead of alert
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center;">
                    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0; color: #1f2937;">${title}</h3>
                            <button onclick="this.closest('div').remove(); location.reload();" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <p style="color: #6b7280; line-height: 1.6; margin: 0;">${message}</p>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                            <small style="color: #9ca3af;">${time}</small>
                            ${amount ? `<span style="color: #10b981; font-weight: 600;">${amount.textContent}</span>` : ''}
                        </div>
                        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                            <button onclick="markAsRead('${notificationId}'); this.closest('div').remove();" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Mark as Read</button>
                            <button onclick="this.closest('div').remove(); location.reload();" style="background: #6b7280; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }



        // Add slide out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100%);
                }
            }
        `;
        document.head.appendChild(style);

        // Make functions globally available immediately after declaration
        window.markAsRead = markAsRead;
        window.markAllAsRead = markAllAsRead;
        window.viewNotification = viewNotification;
        window.refreshNotifications = refreshNotifications;
        window.updateSelectedCount = updateSelectedCount;
        window.selectAll = selectAll;
        window.deselectAll = deselectAll;
        window.deleteSelected = deleteSelected;

        // Auto-refresh every 30 seconds for new notifications
        setInterval(() => {
            // Only refresh if user is active
            if (document.visibilityState === 'visible') {
                const currentCount = document.querySelectorAll('.notification-item').length;

                // Simple check - could be enhanced with AJAX
                fetch('notifications.php')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newCount = doc.querySelectorAll('.notification-item').length;

                    if (newCount > currentCount) {
                        // New notifications available
                        const banner = document.querySelector('.banner');
                        if (banner) {
                            banner.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                            banner.innerHTML = `
                                <h1><i class="fas fa-bell"></i> New Notifications Available!</h1>
                                <p>Click refresh to see your latest updates</p>
                            `;
                        }
                    }
                })
                .catch(error => console.log('Auto-refresh check failed'));
            }
        }, 30000);

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Notifications page loaded successfully!');
            console.log('Available functions:', {
                deleteNotification: typeof deleteNotification,
                markAsRead: typeof markAsRead,
                markAllAsRead: typeof markAllAsRead,
                viewNotification: typeof viewNotification
            });
        });
    </script>
</body>
</html>