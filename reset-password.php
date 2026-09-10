<?php require_once 'firebase_setup.php'; ?>
<?php
date_default_timezone_set('Africa/Lagos');

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require_once 'includes/nigerian_phone_validator.php';

/**
 * Send password reset verification email using PHPMailer configuration
 */
function sendPasswordResetVerificationEmail($email, $name, $verificationCode) {
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
        $mail->Subject = 'VERIFY PASSWORD RESET';

        // Gmail-optimized HTML template - PASSWORD RECOVERY VERSION (Anti-truncation)
        $mail->Body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Password Reset Verification</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f4;">
<tr>
<td align="center" style="padding:40px 0;">
<table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
<tr>
<td style="padding:30px;text-align:center;">
<h1 style="color:#667eea;font-size:28px;margin:0 0 20px 0;font-weight:bold;">LET PAY YOU</h1>
<h2 style="color:#333333;font-size:24px;margin:0 0 10px 0;">Hello ' . htmlspecialchars($name) . '</h2>
<h3 style="color:#667eea;font-size:20px;margin:0 0 20px 0;">Password Reset Request</h3>
<p style="color:#666666;font-size:16px;line-height:1.5;margin:0 0 30px 0;">
We received a password reset request for your LET PAY YOU account. For security, please use the verification code below:
</p>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0;">
<tr>
<td align="center" style="background-color:#f8f9ff;border:2px solid #667eea;border-radius:8px;padding:20px;">
<p style="color:#333333;font-size:18px;margin:0 0 15px 0;font-weight:bold;">Your Verification Code:</p>
<p style="color:#667eea;font-size:36px;font-weight:bold;letter-spacing:4px;margin:0;font-family:monospace;">' . $verificationCode . '</p>
</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0;">
<tr>
<td style="background-color:#fff3cd;border:1px solid #ffeaa7;border-radius:5px;padding:15px;text-align:center;">
<p style="color:#856404;font-size:14px;margin:0;font-weight:bold;">
This code expires in 5 minutes for your security
</p>
</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:30px 0;">
<tr>
<td style="background-color:#f8f9ff;border-radius:8px;padding:20px;">
<h4 style="color:#667eea;font-size:16px;margin:0 0 15px 0;text-align:center;">Security Verification Benefits</h4>
<p style="color:#333333;font-size:14px;margin:0 0 10px 0;"><strong>1. Account Protection:</strong> Prevents unauthorized password changes</p>
<p style="color:#333333;font-size:14px;margin:0 0 10px 0;"><strong>2. Identity Confirmation:</strong> Verifies you are the account owner</p>
<p style="color:#333333;font-size:14px;margin:0 0 10px 0;"><strong>3. Fraud Prevention:</strong> Blocks hackers from accessing your funds</p>
<p style="color:#333333;font-size:14px;margin:0;"><strong>4. Security Audit:</strong> Creates a secure record for compliance</p>
</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0;">
<tr>
<td style="background-color:#f8f9fa;border-left:4px solid #28a745;padding:15px;">
<p style="color:#333333;font-size:14px;margin:0;">
<strong>Security Note:</strong> If you did not request this password reset, please ignore this email. Your account remains secure.
</p>
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="background-color:#2d3748;padding:20px;text-align:center;border-radius:0 0 10px 10px;">
<p style="color:#a0aec0;font-size:14px;margin:0;">
LET PAY YOU - Nigeria\'s Premier Financial Technology Platform
</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Password reset email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate 6-digit verification code for password reset
 */
function generatePasswordResetVerificationCode() {
    return sprintf('%06d', mt_rand(100000, 999999));
}

/**
 * Store password reset verification code in Firebase
 */
function storePasswordResetVerificationCode($email, $code) {
    $users = getUsers();

    // Update existing user with password reset verification code
    for ($i = 0; $i < count($users); $i++) {
        if (isset($users[$i]['email']) && strtolower($users[$i]['email']) === strtolower($email)) {
            $users[$i]['password_reset_verification_code'] = $code;
            $users[$i]['password_reset_verification_expiry'] = date('Y-m-d\TH:i:s', time() + 300); // 5 minutes
            break;
        }
    }

    saveUsers($users);
}

$error = '';
$success = '';
$current_step = 1;
$form_data = [];
$user_info = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_step = intval($_POST['current_step'] ?? 1);

    // Store form data
    $form_data = [
        'login_identifier' => trim($_POST['login_identifier'] ?? ''),
        'verification_code' => trim($_POST['verification_code'] ?? ''),
        'new_password' => $_POST['new_password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
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
                    $error = 'Can\'t find this user. Please check your contact details, thank you.';
                } else {
                    $userFound = false;

                    // Search for user by email or username
                    foreach ($users as $userData) {
                        $emailMatch = isset($userData['email']) && strtolower($userData['email']) === strtolower($value);
                        $usernameMatch = isset($userData['username']) && strtolower($userData['username']) === strtolower($value);

                        if ($emailMatch || $usernameMatch) {
                            $userFound = true;
                            $user_info = $userData;
                            break;
                        }
                    }

                    if (!$userFound) {
                        // Check if it's email format or username
                        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $error = 'Can\'t find this email. Please check your contact details, thank you.';
                        } else {
                            $error = 'Can\'t find this username. Please check your contact details, thank you.';
                        }
                    } else {
                        // Generate and send password reset verification code
                        $verificationCode = generatePasswordResetVerificationCode();
                        storePasswordResetVerificationCode($user_info['email'], $verificationCode);

                        if (sendPasswordResetVerificationEmail($user_info['email'], $user_info['name'], $verificationCode)) {
                            $_SESSION['reset_user_info'] = $user_info;
                            $validation_passed = true;
                            $success = 'Verification code sent to your email!';
                        } else {
                            $error = 'Failed to send verification email. Please try again.';
                        }
                    }
                }
            }
            break;

        case 2: // Email Verification Code
            $resend_code = $_POST['resend_code'] ?? '0';
            $user_info = $_SESSION['reset_user_info'] ?? null;

            // Handle resend code request
            if ($resend_code === '1' && $user_info) {
                // Generate and send new password reset verification code
                $newVerificationCode = generatePasswordResetVerificationCode();
                storePasswordResetVerificationCode($user_info['email'], $newVerificationCode);

                if (sendPasswordResetVerificationEmail($user_info['email'], $user_info['name'], $newVerificationCode)) {
                    $success = '✅ New verification code sent to your email!';
                    $current_step = 2; // Stay on the same step
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
                } elseif (!$user_info || !isset($user_info['email'])) {
                    $error = '❌ Session expired. Please start the password reset process again.';
                } else {
                    // REAL PASSWORD RESET VERIFICATION - Check against stored code with EXPIRY enforcement
                    $users = getUsers();

                    $codeValid = false;
                    $userEmail = strtolower($user_info['email']);
                    $userIndex = -1;

                    for ($i = 0; $i < count($users); $i++) {
                        if (isset($users[$i]['email']) && strtolower($users[$i]['email']) === $userEmail) {
                            $userIndex = $i;
                            if (isset($users[$i]['password_reset_verification_code']) && $users[$i]['password_reset_verification_code'] === $verification_code) {
                                // Check if code has expired (5 minutes)
                                if (isset($users[$i]['password_reset_verification_expiry'])) {
                                    $expiryTime = strtotime($users[$i]['password_reset_verification_expiry']);
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

                    if ($codeValid) {
                        // SECURITY: Delete code after successful validation (one-time use)
                        if ($userIndex >= 0) {
                            unset($users[$userIndex]['password_reset_verification_code']);
                            unset($users[$userIndex]['password_reset_verification_expiry']);
                            saveUsers($users);
                        }

                        $validation_passed = true;
                        $success = '🎉 Verification successful!';
                    } else if (!isset($error)) {
                        $error = '❌ Invalid verification code. Please check your email or request a new code.';
                    }
                }
            }
            break;

        case 3: // New Password
            $password = $form_data['new_password'];
            $user_info = $_SESSION['reset_user_info'] ?? null;

            if (empty($password) || strlen($password) < 8) {
                $error = 'Password must be at least 8 characters long';
            } elseif ($user_info && isset($user_info['password']) && $password === $user_info['password']) {
                $error = 'You cannot use your current password. Please choose a different password.';
            } elseif ($user_info && (
                strtolower($password) === strtolower($user_info['username'] ?? '') || 
                strtolower($password) === strtolower($user_info['email'] ?? '') || 
                $password === ($user_info['phone'] ?? '')
            )) {
                $error = 'Password cannot be the same as your username, phone number, or email.';
            } else {
                $validation_passed = true;
            }
            break;

        case 4: // Confirm Password and Update
            $new_password = $form_data['new_password'];
            $confirm_password = $form_data['confirm_password'];
            $user_info = $_SESSION['reset_user_info'] ?? null;

            if ($confirm_password !== $new_password) {
                $error = 'Passwords do not match';
            } else {
                // Update user password in Firebase
                $users = getUsers();
                $user_updated = false;
                $debug_info = [];

                // DEBUG: Store original info
                $debug_info['user_email'] = $user_info['email'] ?? 'NOT_SET';
                $debug_info['total_users'] = count($users);
                
                for ($i = 0; $i < count($users); $i++) {
                    $debug_info['checking_user_' . $i] = $users[$i]['email'] ?? 'NO_EMAIL';
                    
                    if (isset($users[$i]['email']) && strtolower($users[$i]['email']) === strtolower($user_info['email'])) {
                        // Found the user - update password
                        $old_password = $users[$i]['password'] ?? 'NO_PASSWORD';
                        
                        // SIMPLE PASSWORD UPDATE: Store new password directly as plain text
                        $users[$i]['password'] = $new_password;
                        $users[$i]['updated_at'] = date('Y-m-d H:i:s');
                        
                        // Clear any existing password reset codes
                        unset($users[$i]['password_reset_verification_code']);
                        unset($users[$i]['password_reset_verification_expiry']);
                        
                        $user_updated = true;
                        
                        $debug_info['user_found'] = true;
                        $debug_info['old_password'] = $old_password;
                        $debug_info['new_password'] = $new_password;
                        $debug_info['user_index'] = $i;
                        break;
                    }
                }

                $debug_info['user_updated'] = $user_updated;

                if ($user_updated) {
                    // CRITICAL FIX: Ensure the users array is properly saved to Firebase
                    $save_result = saveUsers($users);
                    $debug_info['save_result'] = $save_result;
                    
                    if ($save_result) {
                        // Verify the password was actually saved by re-reading from Firebase
                        $verify_users = getUsers();
                        $password_verified = false;
                        
                        foreach ($verify_users as $verify_user) {
                            if (isset($verify_user['email']) && strtolower($verify_user['email']) === strtolower($user_info['email'])) {
                                if ($verify_user['password'] === $new_password) {
                                    $password_verified = true;
                                }
                                break;
                            }
                        }
                        
                        if ($password_verified) {
                            // Clear session
                            unset($_SESSION['reset_user_info']);
                            $success = 'Password reset successful! You can now login with your new password.';
                            $current_step = 5; // Success step
                            
                            // Force session write before showing success
                            session_write_close();
                        } else {
                            $error = 'Password update verification failed. Please try again.';
                        }
                    } else {
                        $error = 'Failed to save password update to Firebase database. Please try again.';
                    }
                } else {
                    $error = 'Failed to find user account for password update. User email: ' . ($user_info['email'] ?? 'NOT_SET');
                }
            }
            break;
    }

    // If validation passed, move to next step
    if ($validation_passed && $current_step < 4) {
        $current_step++;
        $error = '';
        $success = '';
    }
}

// Get user info from session if available
if (!$user_info && isset($_SESSION['reset_user_info'])) {
    $user_info = $_SESSION['reset_user_info'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LET PAY YOU | Premium Elite Platform</title>
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

        /* 2-PHASE COMBINED ANIMATION CONTAINER */
        .animated-logo-circle {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        /* PHASE 1: WAVE RIPPLE EFFECT - RESET-PASSWORD */
        .wave-container {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
            pointer-events: auto;
            animation: wave-phase 16s ease-in-out infinite;
        }

        .wave-circle {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #ef4444, #dc2626, #f87171);
            border-radius: 50%;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 20px 60px rgba(239, 68, 68, 0.7),
                inset 0 0 50px rgba(255, 255, 255, 0.2);
        }

        /* Ripple waves */
        .ripple-wave {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.4);
            animation: ripple-expand 3s ease-out infinite;
        }

        .ripple-wave:nth-child(1) {
            animation-delay: 0s;
        }

        .ripple-wave:nth-child(2) {
            animation-delay: 1s;
        }

        .ripple-wave:nth-child(3) {
            animation-delay: 2s;
        }

        @keyframes ripple-expand {
            0% {
                transform: scale(0.5);
                opacity: 1;
            }
            100% {
                transform: scale(2.5);
                opacity: 0;
            }
        }

        /* PHASE 2: GLITCH CYBERPUNK EFFECT - SECURE-ACCOUNT */
        .glitch-container {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            animation: glitch-phase 16s ease-in-out infinite;
        }

        .glitch-circle {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #8b5cf6, #a78bfa, #c4b5fd);
            border-radius: 50%;
            position: relative;
            animation: glitch-distort 0.3s infinite;
            box-shadow: 
                0 20px 60px rgba(139, 92, 246, 0.7),
                inset 0 0 50px rgba(255, 255, 255, 0.2);
        }

        /* Glitch layers */
        .glitch-layer {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: inherit;
            mix-blend-mode: screen;
        }

        .glitch-layer:nth-child(1) {
            animation: glitch-rgb-1 0.5s infinite;
            opacity: 0.8;
        }

        .glitch-layer:nth-child(2) {
            animation: glitch-rgb-2 0.7s infinite;
            opacity: 0.6;
        }

        .glitch-layer:nth-child(3) {
            animation: glitch-rgb-3 0.9s infinite;
            opacity: 0.4;
        }

        @keyframes glitch-distort {
            0%, 100% {
                transform: translate(0, 0) skew(0deg);
            }
            20% {
                transform: translate(-2px, 2px) skew(1deg);
            }
            40% {
                transform: translate(2px, -2px) skew(-1deg);
            }
            60% {
                transform: translate(-1px, -1px) skew(0.5deg);
            }
            80% {
                transform: translate(1px, 1px) skew(-0.5deg);
            }
        }

        @keyframes glitch-rgb-1 {
            0%, 100% {
                clip-path: polygon(0 0, 100% 0, 100% 45%, 0 45%);
                transform: translateX(0);
                filter: hue-rotate(0deg);
            }
            50% {
                clip-path: polygon(0 60%, 100% 60%, 100% 100%, 0 100%);
                transform: translateX(-5px);
                filter: hue-rotate(90deg);
            }
        }

        @keyframes glitch-rgb-2 {
            0%, 100% {
                clip-path: polygon(0 15%, 100% 15%, 100% 65%, 0 65%);
                transform: translateX(0);
                filter: hue-rotate(0deg);
            }
            50% {
                clip-path: polygon(0 30%, 100% 30%, 100% 85%, 0 85%);
                transform: translateX(5px);
                filter: hue-rotate(180deg);
            }
        }

        @keyframes glitch-rgb-3 {
            0%, 100% {
                clip-path: polygon(0 75%, 100% 75%, 100% 100%, 0 100%);
                transform: translateY(0);
                filter: hue-rotate(0deg);
            }
            50% {
                clip-path: polygon(0 0%, 100% 0%, 100% 25%, 0 25%);
                transform: translateY(-3px);
                filter: hue-rotate(270deg);
            }
        }

        .logo-text {
            font-size: 1.3rem;
            font-weight: 800;
            color: white;
            text-align: center;
            letter-spacing: 2px;
            z-index: 20;
            position: absolute;
            pointer-events: none;
        }

        /* Wave text effect */
        .wave-container .logo-text {
            animation: wave-text 2s ease-in-out infinite;
            text-shadow: 
                0 0 15px rgba(239, 68, 68, 0.8),
                0 0 30px rgba(220, 38, 38, 0.6),
                2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        @keyframes wave-text {
            0%, 100% {
                transform: translateY(0) scale(1);
                letter-spacing: 2px;
            }
            25% {
                transform: translateY(-5px) scale(1.05);
                letter-spacing: 3px;
            }
            50% {
                transform: translateY(0) scale(1);
                letter-spacing: 2px;
            }
            75% {
                transform: translateY(5px) scale(0.95);
                letter-spacing: 1px;
            }
        }

        /* Glitch text effect */
        .glitch-container .logo-text {
            animation: glitch-text 0.4s infinite;
            text-shadow: 
                -2px 0 #00ffff,
                2px 0 #ff00ff,
                0 0 20px rgba(139, 92, 246, 0.8),
                0 0 40px rgba(167, 139, 250, 0.6);
        }

        @keyframes glitch-text {
            0% {
                transform: translate(0);
                text-shadow: 
                    -2px 0 #00ffff,
                    2px 0 #ff00ff;
            }
            20% {
                transform: translate(-2px, 2px);
                text-shadow: 
                    2px 0 #00ffff,
                    -2px 0 #ff00ff;
            }
            40% {
                transform: translate(2px, -2px);
                text-shadow: 
                    -3px 0 #00ffff,
                    3px 0 #ff00ff;
            }
            60% {
                transform: translate(-1px, -1px);
                text-shadow: 
                    1px 0 #00ffff,
                    -1px 0 #ff00ff;
            }
            80% {
                transform: translate(1px, 1px);
                text-shadow: 
                    -1px 0 #00ffff,
                    1px 0 #ff00ff;
            }
            100% {
                transform: translate(0);
                text-shadow: 
                    -2px 0 #00ffff,
                    2px 0 #ff00ff;
            }
        }

        /* PHASE TRANSITIONS */
        @keyframes wave-phase {
            0% { opacity: 1; pointer-events: auto; }
            48% { opacity: 1; pointer-events: auto; }
            50% { opacity: 0; pointer-events: none; }
            98% { opacity: 0; pointer-events: none; }
            100% { opacity: 1; pointer-events: auto; }
        }

        @keyframes glitch-phase {
            0%, 48% { opacity: 0; pointer-events: none; }
            50% { opacity: 1; pointer-events: auto; }
            98% { opacity: 1; pointer-events: auto; }
            100% { opacity: 0; pointer-events: none; }
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
            margin: 60px auto;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
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

        /* Success Animation */
        .success-animation {
            text-align: center;
        }

        .success-checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #10b981;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: bounce 0.6s ease;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        /* Verification Timer Styles */
        .verification-timer {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }

        .timer-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #f59e0b;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .timer-display i {
            color: #f59e0b;
        }

        /* Resend Section */
        .resend-section {
            text-align: center;
            margin-bottom: 25px;
        }

        .resend-text {
            color: rgba(51, 51, 51, 0.7);
            font-size: 0.95rem;
            margin-bottom: 15px;
        }

        .resend-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            border-radius: 15px;
            color: white;
            padding: 12px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
                font-size: 2rem;
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
            Password Reset
        </div>
    </div>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="professional-image">
            <div class="animated-logo-circle">
                <!-- Phase 1: WAVE RIPPLE EFFECT - RESET-PASSWORD -->
                <div class="wave-container">
                    <div class="wave-circle">
                        <div class="ripple-wave"></div>
                        <div class="ripple-wave"></div>
                        <div class="ripple-wave"></div>
                    </div>
                    <div class="logo-text">RESET-PASSWORD</div>
                </div>
                
                <!-- Phase 2: GLITCH CYBERPUNK - SECURE-ACCOUNT -->
                <div class="glitch-container">
                    <div class="glitch-circle">
                        <div class="glitch-layer"></div>
                        <div class="glitch-layer"></div>
                        <div class="glitch-layer"></div>
                    </div>
                    <div class="logo-text">SECURE-ACCOUNT</div>
                </div>
            </div>
        </div>
        <h1 class="hero-title">Reset Password</h1>
        <p class="hero-subtitle">Secure password recovery for your premium investment account. Follow the simple steps to regain access.</p>
    </div>

    <!-- Progressive Reset Form -->
    <div class="form-container">
        <div class="loading-overlay" id="loading-overlay">
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <div class="loading-text">Processing your request...</div>
            </div>
        </div>

        <div class="progress-indicator">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="progress-dot <?= $current_step >= $i ? 'active' : '' ?>"></div>
            <?php endfor; ?>
        </div>

        <div class="step-container">
            <?php if ($current_step == 1): ?>
                <!-- Step 1: Username/Email -->
                <h3 class="step-title">Account Recovery</h3>
                <p class="step-description">Enter your username or email address to begin password recovery.</p>
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
                <!-- Step 2: Email Verification -->
                <h3 class="step-title">Email Verification</h3>
                <?php if ($user_info): ?>
                    <div class="user-greeting">
                        <h4>Dear <?= htmlspecialchars($user_info['name']) ?></h4>
                        <p>Thank you for making a request to change your password. For security purposes, we need to make a quick security check to make sure it is you. Please enter the email verification code being sent to your email <strong><?= htmlspecialchars($user_info['email']) ?></strong></p>
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
                    <input type="hidden" name="current_step" value="2">
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
                        <i class="fas fa-shield-check"></i> Verify Code
                    </button>
                </form>

            <?php elseif ($current_step == 3): ?>
                <!-- Step 3: New Password -->
                <h3 class="step-title">Create New Password</h3>
                <p class="step-description">Choose a strong new password that's at least 8 characters long.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="3">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'new_password'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="password" class="form-input" name="new_password" value="<?= htmlspecialchars($form_data['new_password'] ?? '') ?>" placeholder="Enter your new password" autocomplete="new-password" required>
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

            <?php elseif ($current_step == 4): ?>
                <!-- Step 4: Confirm Password -->
                <h3 class="step-title">Confirm New Password</h3>
                <p class="step-description">Enter your new password again to confirm.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="4">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'confirm_password'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="password" class="form-input" name="confirm_password" value="<?= htmlspecialchars($form_data['confirm_password'] ?? '') ?>" placeholder="Confirm your new password" autocomplete="new-password" required>
                        <?php if ($error): ?>
                            <div class="validation-message error">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-shield-check"></i> Reset Password
                    </button>
                </form>

            <?php elseif ($current_step == 5): ?>
                <!-- Step 5: Success -->
                <div class="success-animation">
                    <div class="success-checkmark">
                        <i class="fas fa-check" style="color: white; font-size: 2rem;"></i>
                    </div>
                    <h3 class="step-title" style="color: #10b981;">Password Reset Successful!</h3>
                    <p class="step-description">Your password has been successfully updated. You can now login with your new password.</p>
                    <a href="login.php" class="submit-btn" style="text-decoration: none; display: block; margin-top: 20px;">
                        <i class="fas fa-sign-in-alt"></i> Login Now
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($current_step < 5): ?>
                <div class="form-links">
                    <p>Remember your password? <a href="login.php">Back to Login</a></p>
                    <div class="divider">•</div>
                    <p>Need help? <a href="support.php"><i class="fas fa-headset"></i> Contact Support</a></p>
                </div>
            <?php endif; ?>
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

            // Auto-format verification code input (space every 3 digits)
            if (verificationInput) {
                verificationInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, ''); // Remove non-digits

                    if (value.length > 6) {
                        value = value.substring(0, 6); // Limit to 6 digits
                    }

                    e.target.value = value;

                    // Auto-submit when 6 digits are entered
                    if (value.length === 6) {
                        setTimeout(function() {
                            if (form && !form.submitted) {
                                form.submit();
                            }
                        }, 500);
                    }
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
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                    }

                    // Add delay for better UX
                    setTimeout(function() {
                        // Allow form to submit normally after delay
                        if (!form.submitted) {
                            form.submitted = true;
                            form.submit();
                        }
                    }, 2000);

                    // Prevent immediate submission
                    if (!form.submitted) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>