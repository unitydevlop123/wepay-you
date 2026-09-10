<?php require_once 'firebase_setup.php'; ?>
<?php require_once 'ban-checker.php'; ?>
<?php
date_default_timezone_set('Africa/Lagos');

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable sessions specifically for login (override auth.php debugging)
session_start();
require_once 'includes/nigerian_phone_validator.php';

/**
 * Send login verification email using PHPMailer configuration
 */
function sendLoginVerificationEmail($email, $name, $verificationCode) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration from withdraw.php
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
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'VERIFY YOUR LOGIN';

        // Gmail-optimized HTML template - Table-based structure to prevent truncation
        $mail->Body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>Secure Login Verification – LET PAY YOU</title></head><body style="margin:0;padding:0;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#333;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);"><tr><td align="center" valign="top"><table cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#fff;border-radius:15px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.2);margin:20px auto;"><tr><td style="background:#fff;padding:15px 20px;border-bottom:1px solid #ddd;"><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td align="left" valign="middle" style="padding:0;"><img src="logo.svg" alt="LET PAY YOU" style="height:35px;display:inline-block;vertical-align:middle;margin-right:15px;" /><span style="color:#2d3748;font-size:1.4rem;font-weight:700;vertical-align:middle;">LET PAY YOU</span></td></tr></table></td></tr><tr><td style="padding:40px 30px;text-align:center;"><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td align="center"><h2 style="font-size:2rem;font-weight:700;color:#333;margin:0 0 15px 0;">Hello ' . htmlspecialchars($name) . ',</h2><h1 style="font-size:2.5rem;color:#667eea;margin:20px 0;">Welcome Back!</h1><p style="font-size:1.1rem;color:#666;margin:0 0 30px 0;line-height:1.6;">Great to see you again!<br>We detected a login attempt to your LET PAY YOU account and need to verify it\'s really you.</p></td></tr><tr><td align="center" style="padding:30px 0;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:linear-gradient(135deg,#f8f9ff,#e6edff);border:2px solid #667eea;border-radius:15px;"><tr><td style="padding:30px;text-align:center;"><div style="font-size:1.2rem;font-weight:600;color:#333;margin:0 0 15px 0;">To complete your secure login, please enter the verification code below:</div><div style="font-size:2.5rem;font-weight:800;color:#667eea;letter-spacing:8px;background:#fff;padding:20px;border-radius:10px;border:2px dashed #667eea;margin:20px 0;">' . $verificationCode . '</div></td></tr></table></td></tr><tr><td align="center" style="padding:25px 0;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#fff3cd;border:1px solid #ffeaa7;border-radius:10px;"><tr><td style="padding:15px;text-align:center;color:#856404;font-weight:600;"><strong>For your security, this code will expire in 5 minutes.</strong></td></tr></table></td></tr><tr><td align="left" style="padding:30px 0;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8f9ff;border-radius:12px;"><tr><td style="padding:25px;"><h3 style="font-size:1.3rem;font-weight:700;color:#667eea;margin:0 0 15px 0;text-align:center;">Why We Verify Your Login</h3><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">1</td><td style="padding-left:10px;vertical-align:top;"><strong>Account Protection:</strong> Prevents unauthorized access to your funds and personal information</td></tr></table></td></tr><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">2</td><td style="padding-left:10px;vertical-align:top;"><strong>Fraud Prevention:</strong> Blocks hackers from accessing your account even with your password</td></tr></table></td></tr><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">3</td><td style="padding-left:10px;vertical-align:top;"><strong>Login Monitoring:</strong> Alerts you of any suspicious login attempts on your account</td></tr></table></td></tr><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">4</td><td style="padding-left:10px;vertical-align:top;"><strong>Regulatory Compliance:</strong> Meets Nigerian financial security standards for user protection</td></tr></table></td></tr></table></td></tr></table></td></tr><tr><td align="left" style="padding:25px 0;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8f9fa;border-left:4px solid #28a745;"><tr><td style="padding:20px;"><strong>Security Note:</strong><br>If you did not attempt to login to LET PAY YOU, please ignore this message and consider changing your password. Your security is our top priority.</td></tr></table></td></tr></table></td></tr><tr><td style="background:#2d3748;color:#fff;padding:20px;text-align:center;"><p style="margin:0;color:#a0aec0;">LET PAY YOU - Nigeria\'s Premier Financial Technology Platform</p></td></tr></table></td></tr></table></body></html>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Login email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate 6-digit verification code for login
 */
function generateLoginVerificationCode() {
    return sprintf('%06d', mt_rand(100000, 999999));
}

/**
 * Store login verification code in Firebase
 */
function storeLoginVerificationCode($email, $code) {
    $users = getUsers();

    // Update existing user with login verification code
    for ($i = 0; $i < count($users); $i++) {
        if (isset($users[$i]['email']) && strtolower($users[$i]['email']) === strtolower($email)) {
            $users[$i]['login_verification_code'] = $code;
            $users[$i]['login_verification_expiry'] = date('Y-m-d\TH:i:s', time() + 300); // 5 minutes
            break;
        }
    }

    saveUsers($users);
}

$error = '';
$success = '';
$current_step = 1;
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_step = intval($_POST['current_step'] ?? 1);

    // Store form data
    $form_data = [
        'login_identifier' => trim($_POST['login_identifier'] ?? ''),
        'password' => $_POST['password'] ?? ''
    ];

    $validation_passed = false;

    switch ($current_step) {
        case 1: // Validate Username/Email
            $value = $form_data['login_identifier'];
            if (empty($value)) {
                $error = 'Please enter your username or email address';
            } else {
                // Load users from Firebase users/ path
                $users = getUsers();
                if (empty($users)) {
                    $error = 'Username or email not found. Please check your credentials.';
                } else {
                    $userFound = false;

                    // Search for user by email or username
                    foreach ($users as $userData) {
                        $emailMatch = isset($userData['email']) && strtolower($userData['email']) === strtolower($value);
                        $usernameMatch = isset($userData['username']) && strtolower($userData['username']) === strtolower($value);

                        if ($emailMatch || $usernameMatch) {
                            $userFound = true;
                            break;
                        }
                    }

                    if (!$userFound) {
                        $error = 'Username or email not found. Please check your credentials.';
                    } else {
                        $validation_passed = true;
                    }
                }
            }
            break;

        case 2: // Validate Password
            $login_identifier = $form_data['login_identifier'];
            $password = $form_data['password'];

            if (empty($password)) {
                $error = 'Please enter your password';
            } else {
                // Load users from Firebase
                $users = getUsers();
                $userFound = false;
                $passwordMatch = false;
                $user = null;

                // Search for user by email or username
                foreach ($users as $userData) {
                    $emailMatch = isset($userData['email']) && strtolower($userData['email']) === strtolower($login_identifier);
                    $usernameMatch = isset($userData['username']) && strtolower($userData['username']) === strtolower($login_identifier);

                    if ($emailMatch || $usernameMatch) {
                        $userFound = true;
                        $user = $userData;

                        // Check password - handle both hashed and plain text passwords
                        if (isset($userData['password'])) {
                            $storedPassword = $userData['password'];
                            // If stored password looks like a hash (starts with $2y$), use password_verify
                            if (substr($storedPassword, 0, 4) === '$2y$') {
                                $passwordMatch = password_verify($password, $storedPassword);
                                error_log("LOGIN DEBUG - Hash verification result: " . ($passwordMatch ? 'SUCCESS' : 'FAILED'));
                            }
                            // Otherwise use plain text comparison
                            else {
                                $passwordMatch = (trim($password) === trim($storedPassword));
                                error_log("LOGIN DEBUG - Plain text verification result: " . ($passwordMatch ? 'SUCCESS' : 'FAILED'));
                            }
                        }
                        break;
                    }
                }

                if (!$userFound) {
                    $error = 'Username or email not found. Please check your credentials.';
                } elseif (!$passwordMatch) {
                    // Add debug info for password mismatch
                    $stored_pw = $user['password'] ?? 'NO_PASSWORD';
                    $entered_pw = $password;
                    error_log("LOGIN DEBUG - Password mismatch for user: " . $login_identifier);
                    error_log("LOGIN DEBUG - Entered: '$entered_pw' (" . strlen($entered_pw) . " chars)");
                    error_log("LOGIN DEBUG - Stored: '$stored_pw' (" . strlen($stored_pw) . " chars)");
                    error_log("LOGIN DEBUG - Account status: " . ($user['status'] ?? 'NO_STATUS'));
                    $error = 'Incorrect password. Please try again.';
                } else {
                    // CHECK IF USER IS BANNED BEFORE ALLOWING LOGIN
                    if (isUserBanned($user['email'])) {
                        // User is banned - redirect to login with ban message
                        header('Location: login.php?banned=1');
                        exit();
                    }
                    
                    // Generate and send login verification code
                    $verificationCode = generateLoginVerificationCode();
                    storeLoginVerificationCode($user['email'], $verificationCode);

                    if (sendLoginVerificationEmail($user['email'], $user['name'], $verificationCode)) {
                        $_SESSION['login_user_info'] = $user;
                        $validation_passed = true;
                        $success = 'Verification code sent to your email!';
                    } else {
                        $error = 'Failed to send verification email. Please try again.';
                    }
                }
            }
            break;

        case 3: // Email Verification and Complete Login
            $resend_code = $_POST['resend_code'] ?? '0';
            $user = $_SESSION['login_user_info'] ?? null;

            // Handle resend code request
            if ($resend_code === '1' && $user) {
                // Generate and send new login verification code
                $newVerificationCode = generateLoginVerificationCode();
                storeLoginVerificationCode($user['email'], $newVerificationCode);

                if (sendLoginVerificationEmail($user['email'], $user['name'], $newVerificationCode)) {
                    $success = '✅ New verification code sent to your email!';
                    $current_step = 3; // Stay on the same step
                } else {
                    $error = '❌ Failed to send new verification code. Please try again.';
                }
            } else {
                // Handle verification code validation
                $verification_code = trim($_POST['verification_code'] ?? '');

                if (empty($verification_code)) {
                    $error = '⚠️ Please enter the verification code sent to your email';
                } elseif (strlen($verification_code) !== 6) {
                    $error = '⚠️ Verification code must be exactly 6 digits';
                } elseif (!preg_match('/^\d{6}$/', $verification_code)) {
                    $error = '⚠️ Verification code must contain only numbers';
                } elseif (!$user || !isset($user['email'])) {
                    $error = '❌ Session expired. Please start the login process again.';
                } else {
                    // REAL LOGIN VERIFICATION - Check against stored code with EXPIRY enforcement
                    $users = getUsers();

                    $codeValid = false;
                    $userEmail = strtolower($user['email']);
                    $userIndex = -1;

                    for ($i = 0; $i < count($users); $i++) {
                        if (isset($users[$i]['email']) && strtolower($users[$i]['email']) === $userEmail) {
                            $userIndex = $i;
                            if (isset($users[$i]['login_verification_code']) && $users[$i]['login_verification_code'] === $verification_code) {
                                // Check if code has expired (5 minutes)
                                if (isset($users[$i]['login_verification_expiry'])) {
                                    $expiryTime = strtotime($users[$i]['login_verification_expiry']);
                                    if (time() > $expiryTime) {
                                        $error = '❌ Verification code has expired. Please request a new code.';
                                        break;
                                    }
                                }
                                $codeValid = true;
                            }
                            break;
                        }
                    }

                    // DEBUG: Log verification attempt
                    error_log("LOGIN DEBUG - Verification attempt:");
                    error_log("LOGIN DEBUG - Code entered: '$verification_code'");
                    error_log("LOGIN DEBUG - Code valid: " . ($codeValid ? 'YES' : 'NO'));
                    error_log("LOGIN DEBUG - User found: " . ($userIndex >= 0 ? 'YES' : 'NO'));

                    if ($codeValid && $userIndex >= 0) {
                        // FINAL BAN CHECK BEFORE LOGIN
                        if (isUserBanned($user['email'])) {
                            // User is banned - redirect to login with ban message
                            header('Location: login.php?banned=1');
                            exit();
                        }
                        
                        // SUCCESSFUL LOGIN - Create proper session
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['user'] = [
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'id' => $user['id'] ?? uniqid()
                        ];
                        $_SESSION['user_id'] = $user['id'] ?? uniqid();
                        $_SESSION['last_activity'] = time();

                        // Clear temporary login data
                        unset($_SESSION['login_user_info']);

                        // Clear verification codes from database
                        $users = getUsers();
                        for ($i = 0; $i < count($users); $i++) {
                            if (isset($users[$i]['email']) && strtolower($users[$i]['email']) === strtolower($user['email'])) {
                                unset($users[$i]['login_verification_code']);
                                unset($users[$i]['login_verification_expiry']);
                                break;
                            }
                        }
                        saveUsers($users);

                        // Create welcome back notification for returning user
                        $welcome_back_notification = [
                            'id' => 'login_' . time() . '_' . rand(1000, 9999),
                            'type' => 'welcome_back',
                            'title' => 'Welcome Back to LET PAY YOU!',
                            'message' => "👋 Welcome back " . $user['name'] . "! Great to see you again on LET PAY YOU. Your account is secure and ready for action. Don't forget to: 📈 Check your investment packages, 💰 Complete today's orders for profits, 🎮 Try your luck with our games, 💸 Withdraw your earnings anytime. Keep building your wealth with us!",
                            'amount' => 0,
                            'status' => 'unread',
                            'created_at' => date('Y-m-d H:i:s'),
                            'read_at' => null
                        ];

                        // Add welcome back notification using Firebase format
                        addUserNotification($user['email'], $welcome_back_notification);

                        // Force session write before redirect
                        session_write_close();

                        // Redirect to dashboard
                        header('Location: dashboard.php');
                        exit();
                    } else if (!isset($error)) {
                        $error = '❌ Invalid verification code. Please check your email or request a new code.';
                    }
                }
            }
            break;
    }

    // If validation passed, move to next step
    if ($validation_passed && $current_step < 3) {
        $current_step++;
        $error = '';
        $success = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LET PAY YOU | Premium Elite Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: #ffffff;
            min-height: 100vh;
            color: #333333;
            overflow-x: hidden;
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

        .header-info {
            color: #2d3748;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Hero Section */
        .hero-section {
            margin-top: 80px;
            padding: 60px 20px 40px;
            text-align: center;
            position: relative;
        }

        .professional-image {
            width: 200px;
            height: 200px;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .animated-logo-circle {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            animation: flip-3d 6s ease-in-out infinite;
        }

        .logo-face {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            backface-visibility: hidden;
            box-shadow: 
                0 20px 40px rgba(102, 126, 234, 0.4),
                inset 0 0 30px rgba(255, 255, 255, 0.1);
        }

        .logo-face.front {
            transform: rotateY(0deg);
        }

        .logo-face.back {
            transform: rotateY(180deg);
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 
                0 20px 40px rgba(245, 158, 11, 0.4),
                inset 0 0 30px rgba(255, 255, 255, 0.1);
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            text-align: center;
            letter-spacing: 2px;
            z-index: 2;
            animation: text-glow 2s ease-in-out infinite alternate;
        }

        .glow-ring {
            position: absolute;
            width: 110%;
            height: 110%;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.5);
            animation: ring-pulse 2s ease-in-out infinite;
            opacity: 0;
        }

        @keyframes flip-3d {
            0%, 100% { 
                transform: rotateY(0deg) scale(1);
            }
            25% {
                transform: rotateY(0deg) scale(1.05);
            }
            50% {
                transform: rotateY(180deg) scale(1.05);
            }
            75% {
                transform: rotateY(180deg) scale(1);
            }
        }

        @keyframes text-glow {
            0% { 
                text-shadow: 
                    0 0 10px rgba(255, 255, 255, 0.5),
                    0 0 20px rgba(255, 255, 255, 0.3);
            }
            100% { 
                text-shadow: 
                    0 0 20px rgba(255, 255, 255, 0.8),
                    0 0 30px rgba(255, 255, 255, 0.5),
                    0 0 40px rgba(255, 255, 255, 0.4);
            }
        }

        @keyframes ring-pulse {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }
            50% {
                opacity: 0.8;
            }
            100% {
                transform: scale(1.3);
                opacity: 0;
            }
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #333333, #8b5cf6, #3b82f6);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: rgba(51, 51, 51, 0.8);
            margin-bottom: 20px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.5;
        }

        /* Progressive Form Container */
        .form-container {
            max-width: 500px;
            width: 90%;
            margin: 60px auto;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #8b5cf6, #3b82f6, #06b6d4);
        }

        .progress-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            gap: 10px;
        }

        .progress-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
        }

        .progress-dot.active {
            background: #8b5cf6;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.5);
            transform: scale(1.2);
        }

        .step-container {
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .step-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
            background: linear-gradient(45deg, #333333, #8b5cf6);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .step-description {
            color: rgba(51, 51, 51, 0.7);
            text-align: center;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .user-greeting {
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        .user-greeting h4 {
            color: #8b5cf6;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .user-greeting p {
            color: rgba(51, 51, 51, 0.8);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .form-input {
            width: 100%;
            padding: 20px 25px;
            border: 2px solid rgba(139, 92, 246, 0.3);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            color: #333333;
            font-size: 1.1rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
            outline: none;
            box-sizing: border-box;
            min-width: 0;
            overflow: visible;
            white-space: normal;
            text-overflow: clip;
        }

        .form-input::placeholder {
            color: rgba(51, 51, 51, 0.5);
        }

        .form-input:focus {
            border-color: #8b5cf6;
            box-shadow: 
                0 0 0 3px rgba(139, 92, 246, 0.2),
                0 0 25px rgba(139, 92, 246, 0.3);
            background: rgba(255, 255, 255, 1);
        }

        .validation-message {
            margin-top: 15px;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
        }

        .validation-message.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #dc2626;
        }

        .validation-message.success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #059669;
        }

        /* Verification Timer Styles */
        .verification-timer {
            background: linear-gradient(135deg, #f0f7ff, #e0f2fe);
            border: 2px solid #0ea5e9;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .timer-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1rem;
            color: #0c4a6e;
            font-weight: 600;
        }

        .timer-display i {
            color: #0ea5e9;
            font-size: 1.1rem;
        }

        .timer-display strong {
            color: #dc2626;
            font-size: 1.1rem;
        }

        /* Resend Section Styles */
        .resend-section {
            background: #fafafa;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid #e5e5e5;
        }

        .resend-text {
            color: #666;
            margin: 0 0 10px 0;
            font-size: 0.9rem;
        }

        .resend-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .resend-btn:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .resend-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #8b5cf6, #3b82f6);
            border: none;
            border-radius: 20px;
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.6);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .submit-btn.processing {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            cursor: not-allowed;
        }

        .form-links {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(139, 92, 246, 0.2);
        }

        .form-links a {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .form-links a:hover {
            color: #3b82f6;
            text-decoration: underline;
        }

        .divider {
            margin: 15px 0;
            color: rgba(51, 51, 51, 0.5);
        }

        /* Loading Animation */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 25px;
            z-index: 100;
        }

        .loading-content {
            text-align: center;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(139, 92, 246, 0.3);
            border-top: 4px solid #8b5cf6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            color: #8b5cf6;
            font-weight: 600;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .form-container {
                margin: 40px 20px;
                padding: 30px 25px;
            }

            .professional-image {
                width: 150px;
                height: 150px;
            }

            .logo-text {
                font-size: 1.2rem;
                letter-spacing: 1px;
            }
        }

        /* Animation */
        .form-container {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <div class="logo-section">
            <img src="logo.svg" alt="LET PAY YOU">
            <h1>LET PAY YOU</h1>
        </div>
        <div class="header-info">
            Elite Login
        </div>
    </div>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="professional-image">
            <div class="animated-logo-circle">
                <div class="logo-face front">
                    <div class="logo-text">WEPAY-YOU</div>
                    <div class="glow-ring"></div>
                </div>
                <div class="logo-face back">
                    <div class="logo-text">GET-PAID</div>
                    <div class="glow-ring"></div>
                </div>
            </div>
        </div>
        <h1 class="hero-title">Welcome Back</h1>
        <p class="hero-subtitle">Access your premium investment dashboard and continue building your wealth with Nigeria's most trusted financial platform.</p>
    </div>

    <!-- Progressive Login Form -->
    <div class="form-container">
        <div class="loading-overlay" id="loading-overlay">
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <div class="loading-text">Validating your information...</div>
            </div>
        </div>

        <div class="progress-indicator">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="progress-dot <?= $current_step >= $i ? 'active' : '' ?>"></div>
            <?php endfor; ?>
        </div>

        <div class="step-container">
            <?php if ($current_step == 1): ?>
                <!-- Step 1: Username/Email -->
                <h3 class="step-title">Enter your credentials</h3>
                <p class="step-description">Enter your username or email address to begin the login process.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="1">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'login_identifier'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="text" class="form-input" name="login_identifier" value="<?= htmlspecialchars($form_data['login_identifier'] ?? '') ?>" placeholder="Enter your username or email" autocomplete="username" required>
                        <?php if ($error): ?>
                            <div class="validation-message error">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-arrow-right"></i> Continue
                    </button>
                </form>

            <?php elseif ($current_step == 2): ?>
                <!-- Step 2: Password -->
                <h3 class="step-title">Enter your password</h3>
                <p class="step-description">Please enter your secure password to continue the login process.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="2">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'password'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="password" class="form-input" name="password" value="<?= htmlspecialchars($form_data['password'] ?? '') ?>" placeholder="Enter your password" autocomplete="current-password" required>
                        <?php if ($error): ?>
                            <div class="validation-message error">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-arrow-right"></i> Continue
                    </button>
                </form>

            <?php elseif ($current_step == 3): ?>
                <!-- Step 3: Email Verification -->
                <h3 class="step-title">Email Verification</h3>
                <?php 
                $user = $_SESSION['login_user_info'] ?? null;
                if ($user): ?>
                    <div class="user-greeting">
                        <h4>Welcome back, <?= htmlspecialchars($user['name']) ?>!</h4>
                        <p>For security purposes, we've sent a verification code to your email <strong><?= htmlspecialchars($user['email']) ?></strong>. Please enter the code to complete your login.</p>
                    </div>
                <?php endif; ?>
                <p class="step-description">Enter the 6-digit verification code sent to your email.</p>

                <!-- Countdown Timer -->
                <div class="verification-timer" id="verification-timer">
                    <div class="timer-display">
                        <i class="fas fa-clock"></i> 
                        <span id="countdown-text">Code expires in: <strong><span id="countdown">59</span>s</strong></span>
                    </div>
                </div>

                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="3">
                    <input type="hidden" name="resend_code" value="0" id="resend-flag">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'verification_code'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="text" class="form-input" name="verification_code" placeholder="Enter 6-digit verification code" maxlength="6" autocomplete="one-time-code" required id="verification-input">
                        <?php if ($error): ?>
                            <div class="validation-message error">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <?= $error ?>
                            </div>
                        <?php elseif (isset($success)): ?>
                            <div class="validation-message success">
                                <i class="fas fa-check-circle"></i> 
                                <?= $success ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Resend Code Button (Initially Hidden) -->
                    <div class="resend-section" id="resend-section" style="display: none;">
                        <p class="resend-text">Didn't receive the code?</p>
                        <button type="button" class="resend-btn" id="resend-btn">
                            <i class="fas fa-paper-plane"></i> Resend Code
                        </button>
                    </div>

                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                    </button>
                </form>
            <?php endif; ?>

            <div class="form-links">
                <p>Don't have an account? <a href="signup.php">Join The Elite</a></p>
                <div class="divider">•</div>
                <p>Forgot your password? <a href="reset-password.php"><i class="fas fa-key"></i> Reset Password</a></p>
                <div class="divider">•</div>
                <p>Need help? <a href="support.php"><i class="fas fa-headset"></i> Contact Support</a></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('step-form');
            const submitBtn = document.getElementById('submit-btn');
            const loadingOverlay = document.getElementById('loading-overlay');

            // Verification Code Timer Functionality
            const countdownElement = document.getElementById('countdown');
            const countdownText = document.getElementById('countdown-text');
            const resendSection = document.getElementById('resend-section');
            const resendBtn = document.getElementById('resend-btn');
            const resendFlag = document.getElementById('resend-flag');
            const verificationInput = document.getElementById('verification-input');

            let timeLeft = 59;
            let countdownInterval;

            // Start countdown timer if on verification step
            if (countdownElement && resendSection) {
                countdownInterval = setInterval(function() {
                    timeLeft--;
                    countdownElement.textContent = timeLeft;

                    if (timeLeft <= 0) {
                        clearInterval(countdownInterval);

                        // Hide timer, show resend section
                        document.getElementById('verification-timer').style.display = 'none';
                        resendSection.style.display = 'block';
                    }
                }, 1000);
            }

            // Resend code functionality
            if (resendBtn) {
                resendBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Disable button and show loading
                    resendBtn.disabled = true;
                    resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

                    // Set resend flag and submit form
                    resendFlag.value = '1';
                    form.submit();
                });
            }

            // Auto-format verification code input
            if (verificationInput) {
                verificationInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, ''); // Remove non-digits

                    if (value.length > 6) {
                        value = value.substring(0, 6); // Limit to 6 digits
                    }

                    e.target.value = value;

                    // Auto-submit when 6 digits are entered - DISABLED FOR DEBUGGING
                    // if (value.length === 6) {
                    //     setTimeout(function() {
                    //         if (form && !form.submitted) {
                    //             form.submit();
                    //         }
                    //     }, 500);
                    // }
                });
            }

            // Form submission handling
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    // Show loading animation
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'flex';
                    }
                    submitBtn.classList.add('processing');
                    submitBtn.disabled = true;

                    // Different loading messages based on action
                    if (resendFlag && resendFlag.value === '1') {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending New Code...';
                    } else {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying Login...';
                    }
                });
            }
            
            // BAN MESSAGE POPUP - Show if user is banned
            if (window.location.search.includes('banned=1')) {
                // Create ban popup overlay
                const banOverlay = document.createElement('div');
                banOverlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    animation: fadeIn 0.3s ease;
                `;
                
                // Create ban popup
                const banPopup = document.createElement('div');
                banPopup.style.cssText = `
                    background: white;
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 500px;
                    width: 90%;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    animation: slideUp 0.4s ease;
                `;
                
                banPopup.innerHTML = `
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #ef4444, #dc2626); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-ban" style="font-size: 40px; color: white;"></i>
                    </div>
                    <h2 style="color: #1e293b; font-size: 1.8rem; font-weight: 700; margin-bottom: 15px;">Account Banned</h2>
                    <p style="color: #64748b; font-size: 1.1rem; line-height: 1.6; margin-bottom: 25px;">
                        Your account has been banned for not following our rules and policies. Please contact our support team for assistance.
                    </p>
                    <button onclick="window.location.href='login.php'" style="
                        background: linear-gradient(135deg, #667eea, #764ba2);
                        color: white;
                        border: none;
                        padding: 15px 40px;
                        border-radius: 12px;
                        font-size: 1rem;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 25px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </button>
                `;
                
                banOverlay.appendChild(banPopup);
                document.body.appendChild(banOverlay);
                
                // Add animations
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideUp {
                        from { transform: translateY(30px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                `;
                document.head.appendChild(style);
            }
        });
    </script>
</body>
</html>