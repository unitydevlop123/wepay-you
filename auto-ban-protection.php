<?php
/**
 * Automatic Ban Protection System
 * This file automatically captures user IP, email, and ID when they join/login
 * and checks ban status without requiring any code on main site pages
 */

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include functions
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/lib/storage_helper.php';

/**
 * Auto-capture user information and check ban status
 * Call this function when user logs in or accesses any page
 */
function autoCaptureBanCheck($userEmail = null, $userId = null, $userName = null) {
    // Get user IP address
    $userIP = getUserRealIP();
    
    // Auto-detect user info from session if not provided
    if (!$userEmail && isset($_SESSION['user_email'])) {
        $userEmail = $_SESSION['user_email'];
    }
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    if (!$userName && isset($_SESSION['user_name'])) {
        $userName = $_SESSION['user_name'];
    }
    
    // Store user session info with IP tracking
    if ($userEmail || $userId) {
        trackUserSession($userEmail, $userId, $userName, $userIP);
    }
    
    // Check ban status
    return checkUserBanWithIP($userId, $userEmail, $userIP);
}

/**
 * Get real user IP address (handles proxies, load balancers)
 */
function getUserRealIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Track user session with IP for ban monitoring
 */
function trackUserSession($userEmail, $userId, $userName, $userIP) {
    $sessionData = [
        'user_id' => $userId,
        'email' => $userEmail,
        'name' => $userName,
        'ip_address' => $userIP,
        'last_active' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'session_id' => session_id()
    ];
    
    // Create session tracking directory
    $sessionDir = 'storage/sessions';
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }
    
    // Save session tracking
    if ($userId) {
        StorageHelper::put($sessionDir . '/' . $userId . '.json', $sessionData);
    }
    if ($userEmail) {
        StorageHelper::put($sessionDir . '/' . md5($userEmail) . '.json', $sessionData);
    }
}

/**
 * Enhanced ban check with IP tracking
 */
function checkUserBanWithIP($userId, $userEmail, $userIP) {
    // Check ban status
    $banInfo = isUserBanned($userId, $userEmail);
    
    if ($banInfo) {
        // Update ban info with current access attempt
        $banInfo['blocked_access_attempts'] = ($banInfo['blocked_access_attempts'] ?? 0) + 1;
        $banInfo['last_blocked_attempt'] = date('Y-m-d H:i:s');
        $banInfo['blocked_ip'] = $userIP;
        
        // Update ban file with access attempt info
        updateBanAttemptInfo($userId, $userEmail, $banInfo);
        
        // Destroy session and show ban page
        session_destroy();
        showAutoBanPage($banInfo);
        return true;
    }
    
    return false;
}

/**
 * Update ban file with access attempt information
 */
function updateBanAttemptInfo($userId, $userEmail, $banInfo) {
    $dir = 'storage/banned';
    
    if ($userId) {
        StorageHelper::put($dir . '/' . $userId . '.json', $banInfo);
    }
    if ($userEmail) {
        StorageHelper::put($dir . '/' . md5($userEmail) . '.json', $banInfo);
    }
}

/**
 * Show automatic ban page with IP tracking
 */
function showAutoBanPage($banInfo) {
    http_response_code(403);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Unity Tech</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { 
                background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%); 
                min-height: 100vh; 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .ban-container { min-height: 100vh; display: flex; align-items: center; }
            .ban-card { 
                background: rgba(255,255,255,0.98); 
                backdrop-filter: blur(20px); 
                border-radius: 25px; 
                box-shadow: 0 30px 60px rgba(0,0,0,0.2); 
                border: 2px solid rgba(255,255,255,0.3);
                max-width: 650px;
                margin: 0 auto;
                animation: slideIn 0.5s ease-out;
            }
            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-30px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .ban-icon { 
                color: #dc3545; 
                font-size: 5rem; 
                animation: bounce 2s infinite;
            }
            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                40% { transform: translateY(-10px); }
                60% { transform: translateY(-5px); }
            }
            .alert-danger { 
                border: none;
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
            }
            .badge-banned { 
                background: linear-gradient(45deg, #dc3545, #c82333);
                padding: 8px 16px;
                font-size: 0.9rem;
            }
            .ip-info { 
                background: rgba(108, 117, 125, 0.1);
                border-radius: 10px;
                padding: 15px;
                font-family: 'Courier New', monospace;
            }
        </style>
    </head>
    <body>
        <div class="ban-container">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <div class="ban-card p-5">
                            <!-- Header -->
                            <div class="text-center mb-4">
                                <i class="fas fa-ban ban-icon mb-3"></i>
                                <h1 class="text-danger mb-2">ACCESS DENIED</h1>
                                <p class="text-muted fs-5">Your account has been suspended</p>
                            </div>
                            
                            <!-- Ban Alert -->
                            <div class="alert alert-danger d-flex align-items-center mb-4">
                                <i class="fas fa-shield-alt me-3 fs-3"></i>
                                <div class="flex-grow-1">
                                    <h5 class="alert-heading mb-2">Account Suspension Notice</h5>
                                    <p class="mb-2"><strong>Reason:</strong> <?php echo htmlspecialchars($banInfo['reason']); ?></p>
                                    <p class="mb-0"><strong>Contact:</strong> <?php echo htmlspecialchars($banInfo['contact']); ?></p>
                                </div>
                            </div>
                            
                            <!-- Details Grid -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card bg-light border-0 h-100">
                                        <div class="card-body">
                                            <h6 class="card-title d-flex align-items-center">
                                                <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                                Suspension Details
                                            </h6>
                                            <p class="mb-2"><strong>Date:</strong> <?php echo date('M j, Y \a\t g:i A', strtotime($banInfo['banned_at'])); ?></p>
                                            <p class="mb-2"><strong>User ID:</strong> <code><?php echo htmlspecialchars(substr($banInfo['user_id'], -8)); ?></code></p>
                                            <p class="mb-0"><strong>Status:</strong> <span class="badge badge-banned">SUSPENDED</span></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light border-0 h-100">
                                        <div class="card-body">
                                            <h6 class="card-title d-flex align-items-center">
                                                <i class="fas fa-chart-bar me-2 text-info"></i>
                                                Access Information
                                            </h6>
                                            <p class="mb-2"><strong>Blocked Attempts:</strong> <?php echo ($banInfo['blocked_access_attempts'] ?? 1); ?></p>
                                            <p class="mb-2"><strong>Last Attempt:</strong> <?php echo date('M j, g:i A', strtotime($banInfo['last_blocked_attempt'] ?? $banInfo['banned_at'])); ?></p>
                                            <p class="mb-0"><strong>Current IP:</strong> <code><?php echo htmlspecialchars($banInfo['blocked_ip'] ?? getUserRealIP()); ?></code></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Help Section -->
                            <div class="alert alert-info border-0 mb-4">
                                <h6 class="d-flex align-items-center mb-3">
                                    <i class="fas fa-question-circle me-2"></i>
                                    What can you do?
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="mb-0">
                                            <li>Contact Unity Tech support</li>
                                            <li>Provide your User ID for reference</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="mb-0">
                                            <li>Wait for admin review</li>
                                            <li>Check your email for updates</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="text-center">
                                <a href="/" class="btn btn-primary btn-lg px-4 me-3">
                                    <i class="fas fa-home me-2"></i>Return Home
                                </a>
                                <button onclick="checkBanStatus()" class="btn btn-outline-secondary btn-lg px-4" id="checkBtn">
                                    <i class="fas fa-sync-alt me-2"></i>Check Status
                                </button>
                            </div>
                            
                            <!-- Footer -->
                            <div class="text-center mt-4 pt-4 border-top">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>
                                    This security measure protects our platform and users.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            function checkBanStatus() {
                const btn = document.getElementById('checkBtn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking...';
                btn.disabled = true;
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
            
            // Auto-refresh every 2 minutes
            setTimeout(() => {
                window.location.reload();
            }, 120000);
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Auto-run ban check if user session exists
if (isset($_SESSION['user_id']) || isset($_SESSION['user_email'])) {
    autoCaptureBanCheck();
}
?>