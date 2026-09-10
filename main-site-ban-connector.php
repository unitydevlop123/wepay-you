<?php
/**
 * Main Site Ban Connector
 * Include this file in your main website to connect to the backend ban system
 * Since both sites are on the same domain, this will share the ban data
 */

// Path to your backend (adjust if needed)
$backendPath = __DIR__; // Same directory since same domain

/**
 * Simple function to check if user is banned
 * Include this in your main site pages
 */
function checkIfUserBanned($userId = null, $userEmail = null) {
    global $backendPath;

    // If no user info provided, check session
    if (!$userId && !$userEmail) {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        $userEmail = $_SESSION['user_email'] ?? null;
    }

    if (!$userId && !$userEmail) {
        return false; // No user to check
    }

    // Check banned files directory
    $bannedDir = $backendPath . '/storage/banned';

    // Check by user ID
    if ($userId && file_exists($bannedDir . '/' . $userId . '.json')) {
        return json_decode(file_get_contents($bannedDir . '/' . $userId . '.json'), true);
    }

    // Check by email hash
    if ($userEmail && file_exists($bannedDir . '/' . md5($userEmail) . '.json')) {
        return json_decode(file_get_contents($bannedDir . '/' . md5($userEmail) . '.json'), true);
    }

    // Check users.json for banned status
    $usersFile = $backendPath . '/storage/users.json';
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true) ?: [];

        foreach ($users as $user) {
            $matchByEmail = $userEmail && isset($user['email']) && $user['email'] === $userEmail;
            $matchById = $userId && isset($user['id']) && $user['id'] === $userId;

            if ($matchByEmail || $matchById) {
                if (isset($user['status']) && ($user['status'] === 'banned' || $user['status'] === 'suspended')) {
                    return [
                        'user_id' => $user['id'] ?? $userId,
                        'email' => $user['email'] ?? $userEmail,
                        'banned_at' => $user['banned_at'] ?? date('Y-m-d H:i:s'),
                        'reason' => $user['banned_reason'] ?? 'Account suspended',
                        'status' => 'banned'
                    ];
                }
                break;
            }
        }
    }

    return false;
}

/**
 * Show ban page and block access
 */
function showMainSiteBanPage() {
    // Kill session
    session_start();
    session_destroy();

    // Clear cookies
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time()-1000);
            setcookie($name, '', time()-1000, '/');
        }
    }

    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Suspended - Unity Tech VTU</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { 
                background: linear-gradient(135deg, #dc3545, #6c757d); 
                min-height: 100vh; 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .ban-container { min-height: 100vh; display: flex; align-items: center; }
            .ban-card { 
                background: white; 
                border-radius: 20px; 
                box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            .ban-header {
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
                padding: 2rem;
                text-align: center;
            }
            .ban-icon { font-size: 4rem; margin-bottom: 1rem; }
            .ban-body { padding: 2rem; }
            .contact-card {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 1.5rem;
                margin-top: 1.5rem;
            }
        </style>
    </head>
    <body>
        <div class="container ban-container">
            <div class="row justify-content-center w-100">
                <div class="col-lg-6 col-md-8">
                    <div class="ban-card">
                        <div class="ban-header">
                            <div class="ban-icon">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <h2 class="mb-0">Account Suspended</h2>
                        </div>
                        <div class="ban-body">
                            <div class="alert alert-danger border-0">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Access Denied</h5>
                                <p class="mb-0">Your account has been suspended for violating our terms of service and community guidelines.</p>
                            </div>

                            <div class="alert alert-warning border-0">
                                <strong>Reason:</strong> Not following our rules and privacy policy
                            </div>

                            <p class="text-muted mb-0">
                                If you believe this suspension was made in error, please contact our support team for review.
                            </p>

                            <div class="contact-card">
                                <h6 class="text-primary mb-3"><i class="fas fa-headset me-2"></i>Contact Support</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        <small>unity.tech.support@gmail.com</small>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <i class="fas fa-phone text-primary me-2"></i>
                                        <small>+234-XXX-XXX-XXXX</small>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>
                                    Unity Tech VTU System - Secure Platform
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Main function - Add this to the top of your main site pages
 */
function protectMainSitePage($userId = null, $userEmail = null) {
    $banInfo = checkIfUserBanned($userId, $userEmail);
    if ($banInfo) {
        // Log the blocked attempt
        logBlockedAttempt($banInfo, $userId, $userEmail);
        showMainSiteBanPage();
    }
}

/**
 * Log blocked access attempts
 */
function logBlockedAttempt($banInfo, $userId, $userEmail) {
    global $backendPath;

    $dir = $backendPath . '/storage/banned';
    if (!is_dir($dir)) return;

    // Update ban file with blocked attempt
    if ($userId && file_exists($dir . '/' . $userId . '.json')) {
        $banData = json_decode(file_get_contents($dir . '/' . $userId . '.json'), true);
        $banData['main_site_blocked_attempts'] = ($banData['main_site_blocked_attempts'] ?? 0) + 1;
        $banData['last_main_site_attempt'] = date('Y-m-d H:i:s');
        $banData['main_site_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $banData['main_site_page'] = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        file_put_contents($dir . '/' . $userId . '.json', json_encode($banData, JSON_PRETTY_PRINT));
    }
}
?>