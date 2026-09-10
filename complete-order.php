<?php require_once 'firebase_setup.php'; ?>
<?php
// Include authentication system
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// TRASH SYSTEM - Move completed products to permanent trash using Firebase
function moveCompletedProductToTrash($user_email, $product_id) {
    $user_hash = md5($user_email);
    $trash_path = 'trash_products/used_products_' . $user_hash;

    $used_products = readJsonFile($trash_path) ?: [];

    // Add completed product to permanent trash
    if (!in_array($product_id, $used_products)) {
        $used_products[] = $product_id;
        writeJsonFile($trash_path, $used_products);
    }
}

// SECURITY: Require login before processing orders
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Get current authenticated user data from Firebase
$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: login.php');
    exit();
}

function updateUserOrderProgress($email, $commission_earned) {
    $users = getUsers();
    if (empty($users)) {
        return false;
    }

    foreach ($users as $key => $user) {
        if ($user['email'] === $email) {
            // Check for 24-hour reset before processing order (Nigeria timezone)
            date_default_timezone_set('Africa/Lagos');
            $last_order_date = $user['last_order_date'] ?? '';
            $today = date('Y-m-d');

            if ($last_order_date !== $today) {
                // PREVENT EARNINGS MIXING - Reset everything for new day
                $users[$key]['current_package_cycle'] = 0;
                $users[$key]['todays_orders_completed'] = 0;
                $users[$key]['todays_commission'] = 0.00;
                $users[$key]['last_order_date'] = $today;

                // Reset all package progress to prevent mixed earnings
                if (isset($users[$key]['active_packages'])) {
                    foreach ($users[$key]['active_packages'] as $pkg_index => $pkg) {
                        $users[$key]['active_packages'][$pkg_index]['orders_completed_today'] = 0;
                        $users[$key]['active_packages'][$pkg_index]['commission_today'] = 0;
                    }
                }

                $user = $users[$key]; // Update current user data
            }

            // Update balance immediately
            $users[$key]['balance'] = ($user['balance'] ?? 0) + $commission_earned;

            // Add transaction record
            $transaction = [
                'id' => 'TXN_' . strtoupper(bin2hex(random_bytes(4))),
                'transaction_type' => 'commission',
                'description' => 'Order Commission Earned',
                'amount' => $commission_earned,
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s')
            ];
            addUserTransaction($email, $transaction);

            // Add order completion notification
            $notification = [
                'id' => uniqid('notif_'),
                'type' => 'order_completed',
                'title' => 'Order Completed!',
                'message' => 'You have successfully completed an order and earned ₦' . number_format($commission_earned, 2) . ' commission',
                'amount' => $commission_earned,
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s')
            ];
            addUserNotification($email, $notification);

            // Handle multi-package system with proper cycling
            if (isset($user['active_packages']) && is_array($user['active_packages']) && !empty($user['active_packages'])) {
                $active_packages = $user['active_packages'];
                $current_package_cycle = $user['current_package_cycle'] ?? 0;

                // Filter valid (non-expired) packages
                $valid_packages = [];
                foreach ($active_packages as $index => $package) {
                    $expiry_date = new DateTime($package['expiry_date']);
                    $today = new DateTime();
                    if ($expiry_date > $today) {
                        $valid_packages[] = ['index' => $index, 'package' => $package];
                    }
                }

                if (!empty($valid_packages)) {
                    // Get current package
                    if ($current_package_cycle >= count($valid_packages)) {
                        $current_package_cycle = 0;
                        $users[$key]['current_package_cycle'] = 0;
                    }

                    $current_package_info = $valid_packages[$current_package_cycle];
                    $current_package_index = $current_package_info['index'];
                    $current_package = $current_package_info['package'];

                    // Update current package progress
                    $users[$key]['active_packages'][$current_package_index]['orders_completed_today'] = 
                        ($current_package['orders_completed_today'] ?? 0) + 1;
                    $users[$key]['active_packages'][$current_package_index]['commission_today'] = 
                        ($current_package['commission_today'] ?? 0) + $commission_earned;

                    // Update global counters IMMEDIATELY
                    $users[$key]['todays_orders_completed'] = ($user['todays_orders_completed'] ?? 0) + 1;
                    $users[$key]['todays_commission'] = ($user['todays_commission'] ?? 0) + $commission_earned;

                    // Check if current package orders are complete OR earnings target reached
                    $package_orders_completed = $users[$key]['active_packages'][$current_package_index]['orders_completed_today'];
                    $package_commission_today = $users[$key]['active_packages'][$current_package_index]['commission_today'];

                    // Load LIVE package data for real-time earnings target and order limits
                    $live_package_data = readJsonFile(strtolower($current_package['name'])) ?: [];
                    $target_daily_profit = $live_package_data['daily_profit'] ?? $current_package['daily_profit'];
                    $package_daily_orders = $live_package_data['orders'] ?? ($current_package['daily_orders'] ?? 20);

                    // STRICT COMPLETION CHECK - Package must meet BOTH order count AND earnings target
                    $orders_complete = ($package_orders_completed >= $package_daily_orders);
                    $earnings_complete = ($package_commission_today >= $target_daily_profit);
                    $current_package_completed = ($orders_complete && $earnings_complete);

                    // DEBUG: Log detailed completion status
                    error_log("Package '{$current_package['name']}' completion status:");
                    error_log("- Orders: {$package_orders_completed}/{$package_daily_orders} (" . ($orders_complete ? 'COMPLETE' : 'INCOMPLETE') . ")");
                    error_log("- Earnings: ₦{$package_commission_today}/₦{$target_daily_profit} (" . ($earnings_complete ? 'COMPLETE' : 'INCOMPLETE') . ")");
                    error_log("- Overall Complete: " . ($current_package_completed ? 'YES' : 'NO'));


                    // If current package is completed, find next incomplete package
                    if ($current_package_completed) {
                        $found_next_package = false;

                        // Look for next incomplete package
                        for ($i = 0; $i < count($valid_packages); $i++) {
                            $next_cycle = ($current_package_cycle + $i + 1) % count($valid_packages);
                            $next_package_info = $valid_packages[$next_cycle];
                            $next_package_index = $next_package_info['index'];
                            $next_package = $next_package_info['package'];

                            // Check if this package is incomplete
                            $next_orders_done = $users[$key]['active_packages'][$next_package_index]['orders_completed_today'] ?? 0;
                            $next_commission_done = $users[$key]['active_packages'][$next_package_index]['commission_today'] ?? 0;
                            $next_daily_orders = $next_package['daily_orders'] ?? 20;

                            // Load next package daily profit target using Firebase
                            $next_live_data = readJsonFile(strtolower($next_package['name'])) ?: [];
                            $next_target_profit = $next_live_data['daily_profit'] ?? $next_package['daily_profit'];

                            // STRICT CHECK - Next package must meet BOTH criteria to be complete
                            $next_orders_complete = ($next_orders_done >= $next_daily_orders);
                            $next_earnings_complete = ($next_commission_done >= $next_target_profit);
                            $next_package_complete = ($next_orders_complete && $next_earnings_complete);

                            // DEBUG: Log next package check
                            error_log("Next package check - Orders: {$next_orders_done}/{$next_daily_orders}, Earnings: ₦{$next_commission_done}/₦{$next_target_profit}");

                            if (!$next_package_complete) {
                                // Found incomplete package - switch to it
                                $users[$key]['current_package_cycle'] = $next_cycle;
                                $found_next_package = true;
                                break;
                            }
                        }

                        // If no incomplete packages found, all are completed for today
                        if (!$found_next_package) {
                            // All packages completed - stay on current package but orders will show as completed
                        }
                    }



                    // Check if ALL packages are completed and reset if needed
                    $all_packages_completed = true;
                    $cumulative_orders_done = 0;
                    $cumulative_total_orders = 0;

                    foreach ($valid_packages as $pkg_info) {
                        $pkg = $pkg_info['package'];
                        $pkg_index = $pkg_info['index'];
                        $pkg_orders_done = $users[$key]['active_packages'][$pkg_index]['orders_completed_today'] ?? 0;
                        $pkg_commission_done = $users[$key]['active_packages'][$pkg_index]['commission_today'] ?? 0;
                        $pkg_daily_orders = $pkg['daily_orders'] ?? 20;

                        // Load package data for earnings target using Firebase
                        $pkg_live_data = readJsonFile(strtolower($pkg['name'])) ?: [];
                        $pkg_target_profit = $pkg_live_data['daily_profit'] ?? $pkg['daily_profit'];

                        $cumulative_orders_done += $pkg_orders_done;
                        $cumulative_total_orders += $pkg_daily_orders;

                        // STRICT CHECK - Package must meet BOTH orders AND earnings target
                        $pkg_orders_complete = ($pkg_orders_done >= $pkg_daily_orders);
                        $pkg_earnings_complete = ($pkg_commission_done >= $pkg_target_profit);
                        $pkg_complete = ($pkg_orders_complete && $pkg_earnings_complete);

                        // DEBUG: Log package completion status
                        error_log("Package {$pkg['name']} - Orders: {$pkg_orders_done}/{$pkg_daily_orders}, Earnings: ₦{$pkg_commission_done}/₦{$pkg_target_profit}, Complete: " . ($pkg_complete ? 'YES' : 'NO'));

                        if (!$pkg_complete) {
                            $all_packages_completed = false;
                        }
                    }

                    // If ALL packages completed, reset everything for fresh start
                    if ($all_packages_completed && $cumulative_orders_done >= $cumulative_total_orders) {
                        // COMPLETE RESET - Fresh start from Bronze
                        $users[$key]['active_packages'] = []; // Clear all packages
                        $users[$key]['current_package_cycle'] = 0;
                        $users[$key]['todays_orders_completed'] = 0;
                        $users[$key]['todays_commission'] = 0.00;

                        // Create notification about reset using Firebase functions
                        $notification = [
                            'id' => uniqid(),
                            'type' => 'packages_reset',
                            'title' => 'All Packages Completed! 🎉',
                            'message' => 'Congratulations! You completed ALL your packages today (' . $cumulative_orders_done . '/' . $cumulative_total_orders . ' orders). Your packages have been reset and you can start fresh from Bronze package again!',
                            'created_at' => date('Y-m-d H:i:s'),
                            'read' => false
                        ];
                        addUserNotification($user['email'], $notification);
                    }

                    $total_daily_orders = $package_daily_orders;
                    $daily_profit = $current_package['daily_profit'] ?? 0;

                    // Create notification when package is completed using Firebase functions
                    if ($package_orders_completed >= $package_daily_orders) {
                        $notification = [
                            'id' => uniqid(),
                            'type' => 'package_completed',
                            'title' => $current_package['name'] . ' Package Completed!',
                            'message' => 'Congratulations! You completed all ' . $package_daily_orders . ' orders for your ' . $current_package['name'] . ' package today. Commission: ₦' . number_format($daily_profit, 2),
                            'amount' => $daily_profit,
                            'created_at' => date('Y-m-d H:i:s'),
                            'read' => false
                        ];
                        addUserNotification($user['email'], $notification);
                    }
                }
            // Fallback to old single package system
            elseif (isset($user['active_package'])) {
                $users[$key]['todays_orders_completed'] = ($user['todays_orders_completed'] ?? 0) + 1;
                $users[$key]['todays_commission'] = ($user['todays_commission'] ?? 0) + $commission_earned;

                $packageData = readJsonFile(strtolower($user['active_package']));
                if ($packageData) {
                    $total_daily_orders = $packageData['orders'];
                    $daily_profit = $packageData['daily_profit'];
                }
            }

            // If all orders completed, create notification using Firebase functions
            if ($total_daily_orders > 0 && $users[$key]['todays_orders_completed'] >= $total_daily_orders) {
                $notification = [
                    'id' => uniqid(),
                    'type' => 'order_completed',
                    'title' => 'Daily Orders Completed!',
                    'message' => 'Congratulations! You completed all ' . $total_daily_orders . ' orders today. Your commission of ₦' . number_format($daily_profit, 2) . ' has been transferred to your main balance.',
                    'amount' => $daily_profit,
                    'created_at' => date('Y-m-d H:i:s'),
                    'read' => false
                ];
                addUserNotification($user['email'], $notification);
            }

            // Save updated user data to Firebase
            saveUsers($users);
            return true;
        }
    }

    return false;
}

// Handle direct form submission for order completion (hosting compatible)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_order') {
    $commission = $_SESSION['order_commission'] ?? 7.50;
    $success = updateUserOrderProgress($_SESSION['email'], $commission);

    if ($success) {
        // Move completed product to TRASH - never to be used again
        if (isset($_SESSION['current_product_id'])) {
            moveCompletedProductToTrash($_SESSION['email'], $_SESSION['current_product_id']);
        }

        // COMPLETE SESSION REFRESH for immediate progress update
        unset($_SESSION['current_product_id']);
        unset($_SESSION['calculated_order_price']);
        unset($_SESSION['order_commission']);
        unset($_SESSION['current_product_image']);
        unset($_SESSION['user_product_pools']);
        unset($_SESSION['cached_user_data']);
        unset($_SESSION['daily_commissions']);

        // Redirect back to orders page with success
        header('Location: orders.php?completed=1&refresh=1&commission=' . $commission . '&t=' . time());
        exit;
    } else {
        // Redirect with error
        header('Location: orders.php?error=1&message=Failed+to+update+order+progress');
        exit;
    }
}

// Get current user progress for display
$user_data = null;
$orders_completed = 0;
$total_orders = 20;
$commission_today = 0.00;

if (isset($_SESSION['email'])) {
    $users = getUsers();
    if (!empty($users)) {
        foreach ($users as $user) {
            if ($user['email'] === $_SESSION['email']) {
                $user_data = $user;
                $orders_completed = $user['todays_orders_completed'] ?? 0;
                $commission_today = $user['todays_commission'] ?? 0.00;

                // Check multi-package system first
                if (isset($user['active_packages']) && is_array($user['active_packages']) && !empty($user['active_packages'])) {
                    // Find first active package for display
                    foreach ($user['active_packages'] as $package) {
                        $packageData = readJsonFile(strtolower($package['name']));
                        if ($packageData) {
                            $total_orders = $packageData['orders'];
                            break;
                        }
                    }
                }
                // Fallback to old single package system
                elseif (isset($user['active_package'])) {
                    $packageData = readJsonFile(strtolower($user['active_package']));
                    if ($packageData) {
                        $total_orders = $packageData['orders'];
                    }
                }
                break;
            }
        }
    }
}

// Redirect to orders page if accessing directly
if (!isset($_POST['action'])) {
    header('Location: orders.php');
    exit;
}
?>