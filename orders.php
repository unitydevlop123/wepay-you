<?php require_once 'firebase_setup.php'; ?>
<?php
// Include authentication system
require_once 'auth.php';

// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Enable proper authentication
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Include order auto-update processing for PHP refresh system
include_once 'order-auto-update.php';

// Get current user email from authenticated session
$current_user_email = $_SESSION['email'];

// Handle direct order completion from order-progress.php (hosting compatible)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_order_direct') {
    $commission = floatval($_POST['commission']);
    $user_email = $current_user_email; // Use authenticated user email
    $completion_package = $_POST['selected_package'] ?? null;

    // Write completion to tracker
    $completion_record = [
        'id' => uniqid(),
        'email' => $user_email,
        'commission' => $commission,
        'completed_at' => date('Y-m-d H:i:s'),
        'processed' => false
    ];

    // Save completion to Firebase
    $completions = readJsonFile('order_completion_tracker') ?: [];
    $completions[] = $completion_record;
    writeJsonFile('order_completion_tracker', $completions);

    // Process the completion immediately
    checkForCompletedOrders();

    // Redirect back to the same package that was being worked on
    $redirect_url = 'orders.php?completed=1&refresh=1&t=' . time();
    if ($completion_package) {
        $redirect_url .= '&selected_package=' . urlencode($completion_package);
    }
    header('Location: ' . $redirect_url);
    exit;
}

// Process any completed orders when page loads/refreshes
checkForCompletedOrders();
checkDailyReset();

// Get current authenticated user data from Firebase
$current_user = getCurrentUser();
if (!$current_user) {
    // This block should ideally not be reached if requireLogin() is active,
    // but kept for fallback in case of unexpected scenarios.
    // If user is not found after login, it indicates a critical issue.
    echo "<script>alert('Error: User session not found after login. Please log in again.'); window.location.href='logout.php';</script>";
    exit;
}

// Function to format amounts
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Function removed - using calculateCommissionForSelectedPackage() from order-auto-update.php instead


// Handle package selection from user input
$selected_package = $_GET['selected_package'] ?? null;

// Function to load package data from Firebase
function loadPackageData($packageName) {
    return readJsonFile(strtolower($packageName));
}

// Get real user data and active packages - FORCE FRESH DATA ALWAYS
$user_has_packages = false;
$available_packages = [];
$selected_package_data = null;
$user_package_data = null;
$user_balance_main = 0.00;
$orders_completed_today = 0;
$total_daily_orders = 0;
$todays_commission = 0.00;
$target_daily_profit = 0.00;
$user_key = null;
$show_order_completion = false;

// FORCE FRESH DATA LOADING - Clear all cached data to ensure accurate progress display
unset($_SESSION['cached_user_data']);
unset($_SESSION['daily_commissions']);

// Force refresh on any refresh parameter or completed order
if (isset($_GET['refresh']) || isset($_GET['completed'])) {
    // Clear file stat cache to ensure fresh file reads
    clearstatcache();
}

// Use authenticated user email instead of debug
$users = getUsers();
if (!empty($users)) {
    foreach ($users as $key => $user) {
        if ($user['email'] === $current_user_email) { // Use authenticated user email
            $user_key = $key;

                // Check for active packages system
                if (isset($user['active_packages']) && !empty($user['active_packages'])) {
                    $active_packages = $user['active_packages'];
                    $user_has_packages = true;

                    // Get valid (non-expired) packages that user can select from
                    foreach ($active_packages as $index => $package) {
                        // Skip packages missing required fields (defensive filtering)
                        if (!isset($package['daily_orders']) || !isset($package['daily_profit']) || !isset($package['expiry_date'])) {
                            continue;
                        }

                        $expiry_date = new DateTime($package['expiry_date']);
                        $today_dt = new DateTime();
                        if ($expiry_date > $today_dt) {
                            $available_packages[] = [
                                'index' => $index,
                                'package' => $package
                            ];
                        }
                    }

                    // If no package selected, auto-select first available package
                    if ($selected_package) {
                        // Find selected package in available packages
                        foreach ($available_packages as $pkg_info) {
                            if ($pkg_info['package']['name'] === $selected_package) {
                                $selected_package_data = $pkg_info;
                                break;
                            }
                        }

                        // If selected package not found in available packages, auto-select first
                        if (!$selected_package_data && !empty($available_packages)) {
                            $selected_package_data = $available_packages[0];
                            $selected_package = $selected_package_data['package']['name'];

                            // Store selected package in user data for dashboard to read
                            if ($selected_package && $user_key !== null) {
                                $users[$key]['current_selected_package'] = $selected_package;
                                saveUsers($users);
                            }
                        }
                    } else {
                        // AUTO-SELECT: If user has packages but none selected, auto-select the first one
                        if (!empty($available_packages)) {
                            $selected_package_data = $available_packages[0];
                            $selected_package = $selected_package_data['package']['name'];

                            // DEBUG: Log auto-selection
                            error_log("Auto-selected package: {$selected_package}");

                            // Store selected package in user data for dashboard to read
                            if ($selected_package && $user_key !== null) {
                                $users[$key]['current_selected_package'] = $selected_package;
                                saveUsers($users);
                            }
                        }
                    }

                    // Check if it's a new day and reset package cycle (Nigeria timezone - 12am reset)
                    date_default_timezone_set('Africa/Lagos'); // Nigeria timezone
                    $last_order_date = $user['last_order_date'] ?? '';
                    $today = date('Y-m-d');
                    if ($last_order_date !== $today) {
                        // Complete reset for new day to prevent earnings mixing
                        $users[$key]['current_package_cycle'] = 0;
                        $users[$key]['todays_orders_completed'] = 0;
                        $users[$key]['todays_commission'] = 0.00;
                        $users[$key]['last_order_date'] = date('Y-m-d');
                        $users[$key]['previous_day_incomplete'] = false; // Track if previous day was incomplete

                        // Check if previous day had incomplete orders
                        if (!empty($last_order_date)) {
                            $had_incomplete_orders = false;
                            foreach ($users[$key]['active_packages'] as $pkg_index => $pkg) {
                                $completed = $pkg['orders_completed_today'] ?? 0;
                                $required = $pkg['daily_orders'] ?? 20;
                                if ($completed < $required) {
                                    $had_incomplete_orders = true;
                                    break;
                                }
                            }
                            $users[$key]['previous_day_incomplete'] = $had_incomplete_orders;
                        }

                        // COMPLETE RESET - Fresh start to prevent mixed earnings
                        foreach ($users[$key]['active_packages'] as $pkg_index => $pkg) {
                            $users[$key]['active_packages'][$pkg_index]['orders_completed_today'] = 0;
                            $users[$key]['active_packages'][$pkg_index]['commission_today'] = 0;
                            // Archive previous day data if needed
                            $users[$key]['active_packages'][$pkg_index]['previous_day_completed'] = $pkg['orders_completed_today'] ?? 0;
                            $users[$key]['active_packages'][$pkg_index]['previous_day_commission'] = $pkg['commission_today'] ?? 0;
                        }

                        // Save updated user data to Firebase
                        saveUsers($users);
                        $user = $users[$key]; // Update current user data
                        $notifications = [];
                        if (file_exists($notificationFile)) {
                            $notifications = json_decode(file_get_contents($notificationFile), true) ?: [];
                        }

                        $notifications[] = [
                            'id' => uniqid(),
                            'type' => 'daily_reset',
                            'title' => 'New Day Started!',
                            'message' => 'Your orders have been reset for today (' . date('M j, Y') . '). Fresh start with clean earnings tracking.',
                            'created_at' => date('Y-m-d H:i:s'),
                            'read' => false
                        ];

                        file_put_contents($notificationFile, json_encode($notifications, JSON_PRETTY_PRINT));
                    }

                    // Use selected package data if available
                    if ($selected_package_data) {
                        $current_package = $selected_package_data['package'];

                        // ALWAYS FORCE RELOAD: Re-read user data from Firebase to get latest progress
                        clearstatcache(); // Clear file stat cache first
                        $fresh_users = getUsers();
                        $fresh_user_data = null;

                        foreach ($fresh_users as $fresh_user) {
                            if ($fresh_user['email'] === $current_user_email) { // Use authenticated user email
                                $fresh_user_data = $fresh_user;
                                // DEBUG: Log fresh user data loading
                                error_log("Fresh user data loaded for: {$current_user_email}");
                                break;
                            }
                        }

                        // DEBUG: Check if fresh data was found
                        if (!$fresh_user_data) {
                            error_log("ERROR: Fresh user data not found for: {$current_user_email}");
                        }

                        if ($fresh_user_data) {
                            // Get FRESH current progress for selected package
                            $fresh_package_data = $fresh_user_data['active_packages'][$selected_package_data['index']] ?? $current_package;
                            // USE PACKAGE-SPECIFIC DATA (not global counters)
                            $orders_completed_today = $fresh_package_data['orders_completed_today'] ?? 0;
                            $todays_commission = $fresh_package_data['commission_today'] ?? 0.00;

                            // ALWAYS use fresh data - never cached
                            $user = $fresh_user_data;
                            $users[$key] = $fresh_user_data;

                            // Update session user data too
                            $_SESSION['user'] = $fresh_user_data;
                        } else {
                            // Fallback to existing data but still try to get fresh package data
                            $fresh_package_data = $users[$key]['active_packages'][$selected_package_data['index']] ?? $current_package;
                            // USE PACKAGE-SPECIFIC DATA (not global counters)
                            $orders_completed_today = $fresh_package_data['orders_completed_today'] ?? 0;
                            $todays_commission = $fresh_package_data['commission_today'] ?? 0.00;
                        }

                        // Load LIVE package data from Firebase - Use consistent path
                        $live_package_data = readJsonFile(strtolower($current_package['name']));
                        if (!empty($live_package_data)) {
                            // Use LIVE package data for calculations
                            $total_daily_orders = $live_package_data['orders'] ?? 20;
                            $target_daily_profit = $live_package_data['daily_profit'] ?? 150.00;

                            // Create package data structure
                            $user_package_data = [
                                'name' => $current_package['name'],
                                'orders' => $total_daily_orders,
                                'daily_profit' => $target_daily_profit
                            ];

                            // Check if package daily target is reached (using package-specific data)
                            $package_complete = ($orders_completed_today >= $total_daily_orders) && ($todays_commission >= $target_daily_profit);
                            $show_order_completion = !$package_complete;

                            // DEBUG: Log package data loading
                            error_log("Package data loaded - Name: {$current_package['name']}, Orders: {$total_daily_orders}, Profit: {$target_daily_profit}");
                        } else {
                            error_log("Failed to load package data for: " . strtolower($current_package['name']));
                        }
                    } else {
                        // No package selected - user needs to choose
                        $user_package_data = null;
                        $total_daily_orders = 0;
                        $target_daily_profit = 0;
                        $show_order_completion = false;
                    }


                    $user_balance_main = $user['balance'] ?? 0.00;
                }
                // Fallback to old single package system
                elseif (isset($user['active_package']) && !empty($user['active_package'])) {
                    $user_has_packages = true;
                    $user_package_name = $user['active_package'] . " Package";

                    // Load package data
                    $packageFile = 'backend/storage/' . strtolower($user['active_package']) . '.json';
                    if (file_exists($packageFile)) {
                        $user_package_data = json_decode(file_get_contents($packageFile), true);
                        $total_daily_orders = $user_package_data['orders'];
                        $target_daily_profit = $user_package_data['daily_profit'];

                        // Get user's current order progress from package-specific data
                        $orders_completed_today = 0; // Reset to prevent cross-package contamination
                        $todays_commission = 0.00; // Reset to prevent cross-package contamination
                        $user_balance_main = $user['balance'] ?? 0.00;
                    }
                }
                break;
            }
        }
    }

// Load products from external API
function fetchProductsFromAPI() {
    $products_url = 'https://api.escuelajs.co/api/v1/products';

    // Use cURL for better API handling
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $products_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LET PAY YOU App');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $products_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($products_data === false || $http_code !== 200) {
        error_log("API request failed. HTTP Code: " . $http_code);
        // Fallback to local products if API fails
        $products_file = file_exists('backend/storage/products_large.json') ?
            'backend/storage/products_large.json' :
            'backend/storage/products.json';
        return json_decode(file_get_contents($products_file), true);
    }

    $api_products = json_decode($products_data, true);
    if (!$api_products || !is_array($api_products)) {
        error_log("Invalid API response format");
        // Fallback to local products if API response is invalid
        $products_file = file_exists('backend/storage/products_large.json') ?
            'backend/storage/products_large.json' :
            'backend/storage/products.json';
        return json_decode(file_get_contents($products_file), true);
    }

    // Transform API products to match our format
    $transformed_products = [];
    foreach ($api_products as $index => $product) {
        // Clean image URL - remove brackets and get first valid image
        $image_url = 'https://via.placeholder.com/400';
        if (isset($product['images']) && is_array($product['images']) && !empty($product['images'])) {
            $raw_image = $product['images'][0];
            // Clean the image URL (remove brackets if present)
            $clean_image = str_replace(['[', ']', '"'], '', $raw_image);
            if (filter_var($clean_image, FILTER_VALIDATE_URL)) {
                $image_url = $clean_image;
            }
        }

        $transformed_products[] = [
            'id' => $product['id'] ?? $index,
            'name' => $product['title'] ?? 'Product ' . ($index + 1),
            'price' => isset($product['price']) ? $product['price'] * 800 : rand(50000, 500000), // Convert to Naira
            'image' => $image_url,
            'category' => isset($product['category']['name']) ? $product['category']['name'] : 'General',
            'description' => $product['description'] ?? 'Quality product'
        ];
    }

    return $transformed_products;
}

$products = fetchProductsFromAPI();

// ENHANCED TRASH SYSTEM - Used products go to permanent trash
function moveProductToTrash($user_email, $product_id) {
    $trash_dir = 'backend/storage/trash_products/';
    if (!file_exists($trash_dir)) {
        mkdir($trash_dir, 0755, true);
    }

    $user_hash = md5($user_email);
    $trash_file = $trash_dir . 'used_products_' . $user_hash . '.json';

    $used_products = [];
    if (file_exists($trash_file)) {
        $used_products = json_decode(file_get_contents($trash_file), true) ?: [];
    }

    // Add product ID to permanent trash (never to be used again)
    if (!in_array($product_id, $used_products)) {
        $used_products[] = $product_id;
        file_put_contents($trash_file, json_encode($used_products, JSON_PRETTY_PRINT));
    }
}

function getUserTrashProducts($user_email) {
    $trash_dir = 'backend/storage/trash_products/';
    $user_hash = md5($user_email);
    $trash_file = $trash_dir . 'used_products_' . $user_hash . '.json';

    if (file_exists($trash_file)) {
        return json_decode(file_get_contents($trash_file), true) ?: [];
    }
    return [];
}

// USER-SPECIFIC + PACKAGE-SPECIFIC Product System with TRASH
$user_email = $current_user_email; // Use authenticated user email
$current_package_name = $user_package_data['name'] ?? 'bronze';
$user_package_key = $user_email . '_' . strtolower($current_package_name);

// Get user's permanently trashed products
$trashed_products = getUserTrashProducts($user_email);

// Create user-specific product pool EXCLUDING trashed products
$user_product_pools = [];
if (!isset($user_product_pools[$user_package_key])) {

    // Create pool excluding ALL previously used (trashed) products
    $all_product_ids = range(0, count($products) - 1);
    $available_products = array_diff($all_product_ids, $trashed_products);

    // If too few products left, reset trash and start fresh
    if (count($available_products) < 50) {
        $trashed_products = [];
        $trash_dir = 'backend/storage/trash_products/';
        $user_hash = md5($user_email);
        $trash_file = $trash_dir . 'used_products_' . $user_hash . '.json';
        file_put_contents($trash_file, json_encode([], JSON_PRETTY_PRINT));
        $available_products = $all_product_ids;
    }

    shuffle($available_products);

    $user_product_pools[$user_package_key] = [
        'pool' => array_values($available_products),
        'index' => 0
    ];
}

// Get next FRESH product from user's pool
$user_pool = $user_product_pools[$user_package_key] ?? ['pool' => [0], 'index' => 0];
$current_product_index = $user_pool['pool'][$user_pool['index']] ?? 0;

// Simple product selection without complex pool management
if ($current_product_index >= count($products)) {
    $current_product_index = 0;
}
    // Simple random product selection
$current_product_index = rand(0, count($products) - 1);

// Get the actual product from the array
$random_product = $products[$current_product_index];

if ($user_package_data) {
    // Use API product data directly - no more local image generation
    if (isset($random_product)) {
        // Use only API product data - no local generation
        $random_product['real_image'] = $random_product['image']; // Direct API image
        $random_product['real_name'] = $random_product['name'];   // Direct API name

        // Store product data in session for other pages
        $_SESSION['current_product_id'] = $current_product_index;
        $_SESSION['current_product_image'] = $random_product['real_image'];
        $_SESSION['current_product_name'] = $random_product['real_name'];
        $_SESSION['current_product_category'] = $random_product['category'];
        $_SESSION['selected_package'] = $selected_package;

        // Store selected package in user data for dashboard to read
        if ($selected_package && $user_key !== null) {
            $users[$user_key]['current_selected_package'] = $selected_package;
            saveUsers($users);
        }

        // Log for debugging
        error_log("Using API product: " . $random_product['real_name'] . " - Image: " . $random_product['real_image']);
    }

    // Use API product data directly - no more local image generation
    $random_product['real_image'] = $random_product['image']; // Direct API image
    $random_product['real_name'] = $random_product['name'];   // Direct API name

    // Store product data in session for other pages
    $_SESSION['current_product_id'] = $current_product_index;
    $_SESSION['current_product_image'] = $random_product['real_image'];
    $_SESSION['current_product_name'] = $random_product['real_name'];
    $_SESSION['current_product_category'] = $random_product['category'];
    $_SESSION['selected_package'] = $selected_package;


    // Use order-auto-update.php function for commission calculation
    $commission_data = calculateCommissionForSelectedPackage($current_user_email, $selected_package); // Use authenticated user email
    $order_commission = $commission_data['commission'];

    // STORE commission in session for consistency across all pages
    $_SESSION['order_commission'] = $order_commission;

    // Calculate order price based on commission (reverse calculation for realism)
    $calculated_order_price = $order_commission * 6.67; // Approximate multiplier
} else {
    // Fallback for users without packages
    $calculated_order_price = 50000;
    $order_commission = 7.50;
    $_SESSION['order_commission'] = $order_commission;
}

// Order data calculated dynamically

// FORCE FRESH DATA RELOAD - Always get latest user progress from Firebase
clearstatcache(); // Clear file stat cache
$fresh_users_data = getUsers();
$fresh_user_data = null;
foreach ($fresh_users_data as $fresh_user) {
    if ($fresh_user['email'] === $current_user_email) { // Use authenticated user email
        $fresh_user_data = $fresh_user;
        break;
    }
}

if (!$fresh_user_data) {
    // If user data is not found, it might be a new user or an error
    // Handle this case appropriately, maybe redirect to invest page or show an error
    echo "<script>alert('User data not found after login. Please log in again.'); window.location.href='logout.php';</script>";
    exit;
} else {
    // Initialize with package-specific data only (no global mixing)
    $orders_completed_today = 0; // Will be set from selected package data
    $todays_commission = 0.00; // Will be set from selected package data
    $user_balance_main = $fresh_user_data['balance'] ?? 0.00;

    // GET USER'S ACTIVE PACKAGES - Check all package purchases with better detection
    $available_packages = [];
    $user_has_packages = false;

    // Check for active_packages array (new multi-package system)
    if (isset($fresh_user_data['active_packages']) && !empty($fresh_user_data['active_packages'])) {
        foreach ($fresh_user_data['active_packages'] as $index => $package) {
            // Check if package is not expired
            $expiry_date = new DateTime($package['expiry_date']);
            $today_dt = new DateTime();

            if ($expiry_date > $today_dt) {
                // Load package template data
                $package_data_template = loadPackageData($package['name']);
                if ($package_data_template) {
                    // Merge template with user package data
                    $complete_package_info = array_merge($package_data_template, $package);

                    // Add to available packages list
                    $available_packages[] = [
                        'index' => $index,
                        'package' => $complete_package_info,
                        'user_data' => $package
                    ];
                    $user_has_packages = true;
                }
            }
        }
    }

    // Fallback: Check old single package system if no new packages found
    if (!$user_has_packages) {
        $package_names = ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Ruby', 'Sapphire', 'Emerald', 'Topaz', 'Crown', 'Legend'];
        foreach ($package_names as $package_name) {
            $package_key = strtolower($package_name) . '_package';

            // Check if user has this package purchased and active
            if (isset($fresh_user_data[$package_key])) {
                $user_package_data_entry = $fresh_user_data[$package_key];

                // Check if package is active and not expired
                if (isset($user_package_data_entry['active']) && $user_package_data_entry['active'] === true) {
                    // Load package template data
                    $package_data_template = loadPackageData($package_name);
                    if ($package_data_template) {
                        // Merge template with user-specific data
                        $complete_package_info = array_merge($package_data_template, $user_package_data_entry);

                        // Add to available packages list
                        $available_packages[] = [
                            'package' => $complete_package_info,
                            'user_data' => $user_package_data_entry
                        ];
                        $user_has_packages = true;
                    }
                }
            }
        }
    }

    // Find the currently selected package details from the newly populated available_packages
    if ($selected_package) {
        foreach ($available_packages as $pkg_info) {
            if ($pkg_info['package']['name'] === $selected_package) {
                $selected_package_data = $pkg_info;
                $user_package_data = $pkg_info['package']; // Set user_package_data for calculations
                break;
            }
        }
    }

    // Update the $users array and $user variable to reflect the fresh data
    // This is crucial for consistency if other parts of the script rely on these global variables
    // Use the correct key for the authenticated user
    $users[$user_key] = $fresh_user_data;
    $user = $fresh_user_data;

    // Recalculate based on potentially updated selected package data
    if ($selected_package_data && $user_package_data) {
        // Use Firebase directly - consistent with other calls
        $live_package_data = readJsonFile(strtolower($user_package_data['name']));
        if (!empty($live_package_data)) {
            $total_daily_orders = $live_package_data['orders'] ?? 20;
            $target_daily_profit = $live_package_data['daily_profit'] ?? 150.00;

            // GET PACKAGE-SPECIFIC PROGRESS (NOT GLOBAL!)
            $package_orders_completed = 0;
            $package_commission_earned = 0.00;

            // Find the specific package progress using package index
            if (isset($fresh_user_data['active_packages']) && isset($selected_package_data['index'])) {
                $package_index = $selected_package_data['index'];
                if (isset($fresh_user_data['active_packages'][$package_index])) {
                    $package_data = $fresh_user_data['active_packages'][$package_index];
                    $package_orders_completed = $package_data['orders_completed_today'] ?? 0;
                    $package_commission_earned = $package_data['commission_today'] ?? 0.00;

                    // DEBUG: Log progress data
                    error_log("Package progress loaded - Orders: {$package_orders_completed}, Commission: {$package_commission_earned}");
                }
            }

            // OVERRIDE GLOBAL COUNTS WITH PACKAGE-SPECIFIC COUNTS
            $orders_completed_today = $package_orders_completed;
            $todays_commission = $package_commission_earned;

            $package_complete = ($orders_completed_today >= $total_daily_orders) && ($todays_commission >= $target_daily_profit);
            $show_order_completion = !$package_complete;
        }
    } else {
        // Reset if no valid package is selected or found
        $user_package_data = null;
        $total_daily_orders = 0;
        $target_daily_profit = 0;
        $show_order_completion = false;
    }
}


// Package-specific order checking - initialize variables first
$current_package_earnings_complete = false;
$current_package_orders_complete = false;

// Check completion status for current package - BOTH order count AND earnings target must be met
if ($selected_package_data && $user_package_data) {
    $current_package_earnings_complete = $todays_commission >= $target_daily_profit;
    $current_package_orders_complete = $orders_completed_today >= $total_daily_orders;
}

// STRICT COMPLETION - Must meet BOTH criteria (orders AND earnings)
$current_package_complete = $current_package_earnings_complete && $current_package_orders_complete;
$show_order_completion = !$current_package_complete && ($orders_completed_today < $total_daily_orders);
$user_package_name = $selected_package_data ? $selected_package_data['package']['name'] . " Package" : "Current Package";
$earnings_complete_message = $current_package_complete ? "Package '" . $user_package_name . "' completed! Orders: {$orders_completed_today}/{$total_daily_orders} | Earnings: " . formatAmount($todays_commission) . "/" . formatAmount($target_daily_profit) : "";

// Calculate progress percentage for CURRENT package only
$progress_percentage = $total_daily_orders > 0 ? ($orders_completed_today / $total_daily_orders) * 100 : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Orders - LET PAY YOU</title>
    <?php
    // No automatic refresh - let users take their time
    // Only refresh after order completion (handled by POST redirect)
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
            max-width: 1200px;
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

        /* Hero Section */
        .hero-section {
            position: relative;
            height: 300px;
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('attached_assets/attached_assets/generated_images/Businessman_champagne_celebration_success_85461b3b.png');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .hero-content h1 {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
        }

        .hero-content p {
            font-size: 1.2rem;
            opacity: 0.9;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
        }

        .selected-package-display {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: inline-block;
            box-shadow: 0 3px 10px rgba(76, 175, 80, 0.3);
        }

        /* Package Selection */
        .package-selection {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .package-selection h3 {
            color: #333;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .package-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .package-btn {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #1976d2;
            border: 2px solid #e3f2fd;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .package-btn:hover {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 118, 210, 0.3);
        }

        .package-btn.active {
            background: linear-gradient(135deg, #2196f3, #1976d2);
            color: white;
            border-color: #2196f3;
        }

        .package-btn.completed {
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
            border-color: #4caf50;
        }

        .package-btn small {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .package-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #666;
        }

        /* Content */
        .content {
            padding: 40px 30px;
        }

        /* No Package State */
        .no-package {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 20px;
            margin-bottom: 30px;
        }

        .no-package h2 {
            color: #e74c3c;
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        .no-package p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .invest-btn {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .invest-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
        }

        /* Order Progress */
        .progress-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .progress-title {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .progress-bar {
            background: #e0e0e0;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 0.9rem;
            color: #666;
            text-align: center;
        }

        /* Product Section */
        .product-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .product-preview {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 25px;
        }

        .product-image {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .product-info h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 8px;
        }

        .product-price {
            font-size: 1.1rem;
            color: #4CAF50;
            font-weight: 600;
        }

        .product-image-placeholder {
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .product-category {
            font-size: 0.9rem;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }

        /* Order Info Table */
        .order-table {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .table-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-label {
            font-weight: 600;
            color: #333;
        }

        .table-value {
            color: #666;
            font-weight: 500;
        }

        .highlight {
            color: #4CAF50;
            font-weight: bold;
        }

        /* Action Button */
        .action-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .start-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 18px 60px;
            border: none;
            border-radius: 25px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .start-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(231, 76, 60, 0.4);
        }

        .start-btn.disabled {
            background: #bbb;
            cursor: not-allowed;
        }

        .start-btn.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Warning Section */
        .warning-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .warning-title {
            font-size: 1rem;
            font-weight: 600;
            color: #856404;
            margin-bottom: 10px;
        }

        .warning-text {
            color: #856404;
            line-height: 1.5;
            font-size: 0.9rem;
        }

        /* Encouragement Section */
        .encouragement {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            margin-bottom: 30px;
        }

        .encouragement h3 {
            color: #2e7d32;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .encouragement p {
            color: #388e3c;
            line-height: 1.6;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .content {
                padding: 20px 15px;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .product-preview {
                flex-direction: column;
                text-align: center;
            }

            .product-image {
                width: 100px;
                height: 100px;
            }

            .start-btn {
                padding: 15px 40px;
                font-size: 1rem;
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
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>

        <!-- Hero Section -->
        <div class="hero-section">
            <div class="hero-content">
                <?php if ($user_has_packages): ?>
                    <?php if ($selected_package_data): ?>
                        <div class="selected-package-display"><?= htmlspecialchars($selected_package_data['package']['name']) ?> Package</div>
                    <?php endif; ?>
                    <h1>WORK GRAB ORDER</h1>
                    <p>Select a package and complete your daily orders to earn commission</p>
                <?php else: ?>
                    <h1>Start Your Journey</h1>
                    <p>Invest in a package to begin earning</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (!$user_has_packages): ?>
                <!-- No Package State -->
                <div class="no-package">
                    <h2>No Work Available For You To Grab</h2>
                    <p><strong>Please Invest First</strong></p>
                    <p>You need to purchase an investment package first to start grabbing orders and earning commissions. Choose from our premium packages designed for consistent daily profits.</p>
                    <a href="invest.php" class="invest-btn">View Investment Packages</a>
                </div>
            <?php else: ?>
                <!-- Package Selection -->
                <?php if (!empty($available_packages)): ?>
                <div class="package-selection">
                    <h3>📦 Your Active Packages (<?= count($available_packages) ?> Available)</h3>
                    <div class="package-buttons">
                        <?php foreach ($available_packages as $pkg_info):
                            $pkg = $pkg_info['package'];
                            $pkg_orders_done = $pkg['orders_completed_today'] ?? 0;
                            $pkg_commission_done = $pkg['commission_today'] ?? 0;

                            // Load live package data from Firebase for display
                            $pkg_live_data = readJsonFile(strtolower($pkg['name'])) ?: [];
                            $pkg_daily_orders = $pkg_live_data['orders'] ?? 20;
                            $pkg_daily_profit = $pkg_live_data['daily_profit'] ?? 150;

                            // STRICT CHECK - Package must meet BOTH order count AND earnings target
                            $pkg_orders_complete = ($pkg_orders_done >= $pkg_daily_orders);
                            $pkg_earnings_complete = ($pkg_commission_done >= $pkg_daily_profit);
                            $pkg_complete = ($pkg_orders_complete && $pkg_earnings_complete);
                            $is_selected = ($selected_package_data && $selected_package_data['package']['name'] === $pkg['name']);
                        ?>
                            <a href="orders.php?selected_package=<?= urlencode($pkg['name']) ?>&t=<?= time() ?>"
                               class="package-btn <?= $pkg_complete ? 'completed' : '' ?> <?= $is_selected ? 'active' : '' ?>"
                               onclick="switchPackageInstantly('<?= htmlspecialchars($pkg['name']) ?>'); return false;">
                                <?= htmlspecialchars($pkg['name']) ?> Package
                                <?= $pkg_complete ? ' ✅' : '' ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$selected_package_data): ?>
                    <div class="package-info">
                        <strong>💡 Select a package:</strong> Choose any package above to start working. You can purchase additional packages anytime and they will appear here.
                    </div>
                    <?php else: ?>
                    <div class="package-info">
                        <strong>🎯 Working on:</strong> <?= htmlspecialchars($selected_package_data['package']['name']) ?> Package. Complete all orders to unlock other packages or purchase new ones!
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($selected_package_data): ?>
                <!-- Order Progress -->
                <div class="progress-section">
                    <h3 class="progress-title">Order Progress</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= htmlspecialchars($progress_percentage) ?>%"></div>
                    </div>
                    <p class="progress-text"><?= htmlspecialchars($orders_completed_today) ?> / <?= htmlspecialchars($total_daily_orders) ?> orders completed (<?= round($progress_percentage) ?>%)</p>
                </div>

                <!-- Product Section -->
                <div class="product-section">
                    <h3 class="progress-title">Today's Product</h3>
                    <div class="product-preview">
                        <img src="<?= htmlspecialchars($random_product['real_image'] ?? 'https://via.placeholder.com/400') ?>" alt="<?= htmlspecialchars($random_product['name']) ?>" style="width: 150px; height: 150px; border-radius: 15px; border: 3px solid #e0e0e0; object-fit: cover; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                        <div class="product-info">
                            <h3><?= htmlspecialchars($random_product['real_name'] ?? $random_product['name']) ?></h3>
                            <p class="product-price"><?= formatAmount($calculated_order_price) ?></p>
                            <p class="product-category"><?= htmlspecialchars($random_product['category']) ?></p>
                        </div>
                    </div>

                    <!-- Order Info Table -->
                    <div class="order-table">
                        <div class="table-row">
                            <span class="table-label">Order Quantity:</span>
                            <span class="table-value"><?= htmlspecialchars($orders_completed_today) ?> / <?= htmlspecialchars($total_daily_orders) ?></span>
                        </div>
                        <div class="table-row">
                            <span class="table-label">Today's Commission:</span>
                            <span class="table-value highlight"><?= formatAmount($todays_commission) ?></span>
                        </div>
                        <div class="table-row">
                            <span class="table-label">Order Commission:</span>
                            <span class="table-value highlight"><?= formatAmount($order_commission) ?></span>
                        </div>
                        <div class="table-row">
                            <span class="table-label">Order Price:</span>
                            <span class="table-value"><?= formatAmount($calculated_order_price) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="action-section">
                    <?php if ($show_order_completion): ?>
                        <a href="order-confirmation.php<?= $selected_package_data ? '?package=' . urlencode($selected_package_data['package']['name']) : '' ?>" class="start-btn">Start Order</a>
                    <?php else: ?>
                        <button class="start-btn disabled" disabled>Daily Orders Completed</button>
                        <div style="margin-top: 20px; padding: 20px; background: #d4edda; border-radius: 15px; text-align: center;">
                            <h4 style="color: #155724; margin-bottom: 10px;">🎉 All Orders Completed!</h4>
                            <p style="color: #155724; margin: 0;">Your daily commission of <?= formatAmount($target_daily_profit) ?> has been transferred to your main balance.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Encouragement Section -->
                <div class="encouragement">
                    <h3>Earn More Every Day</h3>
                    <p>Complete all your daily orders to maximize your earnings. Consistent work leads to consistent profits. Your success is our priority.</p>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Important Warning -->
            <div class="warning-section">
                <h4 class="warning-title">Important Notice</h4>
                <p class="warning-text">
                    You MUST complete ALL daily orders every day to maintain your investment. If you miss any day or fail to complete your required orders, you will lose your earnings for that specific day only. We are not responsible for lost daily earnings due to incomplete tasks. Consistency and daily participation is mandatory for maximum returns.
                </p>
            </div>
        </div>
    </div>

    <script>
        // No automatic refresh - Users can take their time to complete orders
        // Page only refreshes when order is completed via POST redirect
        console.log('Manual order system - Users control the pace');

        // INSTANT PACKAGE SWITCHING FUNCTION
        function switchPackageInstantly(packageName) {
            // Clear all cached data to force fresh load
            sessionStorage.clear();
            localStorage.clear();

            // Force immediate redirect with cache busting and maintain selected package
            window.location.href = 'orders.php?selected_package=' + encodeURIComponent(packageName) + '&instant_switch=1&t=' + Date.now();
            return false;
        }

        // Only refresh when switching packages or after order completion
        function handlePackageSwitch() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('instant_switch')) {
                // Only refresh for package switching
                console.log('Package switched - refreshing once');
            }
        }

        // Check for package switch only
        handlePackageSwitch();
    </script>
</body>
</html>