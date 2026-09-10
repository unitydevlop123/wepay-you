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
 * Send verification email using PHPMailer configuration
 */
function sendVerificationEmail($email, $name, $verificationCode) {
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
        $mail->Subject = 'VERIFY YOUR EMAIL';

        // Gmail-optimized HTML template - SIGNUP VERSION (Table-based structure to prevent truncation)
        $mail->Body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>Verify Your Email – LET PAY YOU</title></head><body style="margin:0;padding:0;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#333;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);"><tr><td align="center" valign="top"><table cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#fff;border-radius:15px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.2);margin:20px auto;"><tr><td style="background:#fff;padding:15px 20px;border-bottom:1px solid #ddd;"><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td align="left" valign="middle" style="padding:0;"><img src="logo.svg" alt="LET PAY YOU" style="height:35px;display:inline-block;vertical-align:middle;margin-right:15px;" /><span style="color:#2d3748;font-size:1.4rem;font-weight:700;vertical-align:middle;">LET PAY YOU</span></td></tr></table></td></tr><tr><td style="padding:40px 30px;text-align:center;"><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td align="center"><h2 style="font-size:2rem;font-weight:700;color:#333;margin:0 0 15px 0;">Hello ' . htmlspecialchars($name) . ',</h2><h1 style="font-size:2.5rem;color:#667eea;margin:20px 0;">Welcome to LET PAY YOU</h1><p style="font-size:1.1rem;color:#666;margin:0 0 30px 0;line-height:1.6;">We\'re excited to have you on board!<br>You\'re about to join Nigeria\'s most trusted financial platform where thousands earn daily.</p></td></tr><tr><td align="center" style="padding:30px 0;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:linear-gradient(135deg,#f8f9ff,#e6edff);border:2px solid #667eea;border-radius:15px;"><tr><td style="padding:30px;text-align:center;"><div style="font-size:1.2rem;font-weight:600;color:#333;margin:0 0 15px 0;">To secure your account and activate your membership, please enter the verification code below:</div><div style="font-size:2.5rem;font-weight:800;color:#667eea;letter-spacing:8px;background:#fff;padding:20px;border-radius:10px;border:2px dashed #667eea;margin:20px 0;">' . $verificationCode . '</div></td></tr></table></td></tr><tr><td align="center" style="padding:25px 0;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#fff3cd;border:1px solid #ffeaa7;border-radius:10px;"><tr><td style="padding:15px;text-align:center;color:#856404;font-weight:600;"><strong>For your security, this code will expire in 5 minutes.</strong></td></tr></table></td></tr><tr><td align="left" style="padding:30px 0;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8f9ff;border-radius:12px;"><tr><td style="padding:25px;"><h3 style="font-size:1.3rem;font-weight:700;color:#667eea;margin:0 0 15px 0;text-align:center;">Why We Verify Your Email</h3><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">1</td><td style="padding-left:10px;vertical-align:top;"><strong>Account Security:</strong> Protects your earnings and prevents unauthorized access to your funds</td></tr></table></td></tr><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">2</td><td style="padding-left:10px;vertical-align:top;"><strong>Payment Notifications:</strong> Receive instant alerts for all transactions, deposits, and withdrawals</td></tr></table></td></tr><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">3</td><td style="padding-left:10px;vertical-align:top;"><strong>Account Recovery:</strong> Essential for password resets and secure account recovery</td></tr></table></td></tr><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">4</td><td style="padding-left:10px;vertical-align:top;"><strong>Compliance:</strong> Required by Nigerian financial regulations for user protection</td></tr></table></td></tr><tr><td style="padding:6px 0;"><table cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#667eea;color:#fff;width:20px;height:20px;border-radius:50%;text-align:center;font-size:12px;font-weight:bold;vertical-align:top;padding:2px 0;">5</td><td style="padding-left:10px;vertical-align:top;"><strong>Exclusive Updates:</strong> Get first access to new investment packages and earning opportunities</td></tr></table></td></tr></table></td></tr></table></td></tr><tr><td align="left" style="padding:25px 0;"><table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8f9fa;border-left:4px solid #28a745;"><tr><td style="padding:20px;"><strong>Security Note:</strong><br>If you did not attempt to register for LET PAY YOU, please ignore this message — no changes will be made to any account. Your security is our top priority.</td></tr></table></td></tr></table></td></tr><tr><td style="background:#2d3748;color:#fff;padding:20px;text-align:center;"><p style="margin:0;color:#a0aec0;">LET PAY YOU - Nigeria\'s Premier Financial Technology Platform</p></td></tr></table></td></tr></table></body></html>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate 6-digit verification code
 */
function generateVerificationCode() {
    return sprintf('%06d', mt_rand(100000, 999999));
}

/**
 * Store verification code in Firebase
 */
function storeVerificationCode($email, $name, $code) {
    $users = getUsers();

    // Update existing user or create temporary record
    $userFound = false;
    for ($i = 0; $i < count($users); $i++) {
        if (isset($users[$i]['email']) && strtolower($users[$i]['email']) === strtolower($email)) {
            $users[$i]['verification_code'] = $code;
            $users[$i]['verification_expiry'] = date('Y-m-d\TH:i:s', time() + 300); // 5 minutes
            $userFound = true;
            break;
        }
    }

    if (!$userFound) {
        // Create temporary record
        $users[] = [
            'email' => $email,
            'name' => $name,
            'verification_code' => $code,
            'verification_expiry' => date('Y-m-d\TH:i:s', time() + 300),
            'status' => 'pending_verification',
            'created_at' => date('Y-m-d H:i:s')
        ];
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
        'fullname' => trim($_POST['fullname'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => preg_replace('/[^0-9]/', '', $_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];

    // Load existing users from Firebase users/ path
    $users = getUsers();

    $validation_passed = false;

    switch ($current_step) {
        case 1: // Validate Full Name
            $value = $form_data['fullname'];
            if (empty($value)) {
                $error = 'Full name is required';
            } else {
                $validation_passed = true;
            }
            break;

        case 2: // Validate Username
            $value = $form_data['username'];
            if (empty($value)) {
                $error = 'Username is required';
            } else {
                // Check if username exists
                $exists = false;
                foreach ($users as $user) {
                    if (isset($user['username']) && strtolower($user['username']) === strtolower($value)) {
                        $exists = true;
                        break;
                    }
                }

                if ($exists) {
                    $error = 'Username already exists, please choose another.';
                } else {
                    $validation_passed = true;
                }
            }
            break;

        case 3: // Validate Email - ONLY Gmail and Yahoo Accepted
            $value = $form_data['email'];
            if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address';
            } else {
                // SECURITY: Only accept Gmail and Yahoo emails
                $email_lower = strtolower($value);
                $domain_part = substr($email_lower, strpos($email_lower, '@') + 1);

                // Only allowed domains
                $allowed_domains = ['gmail.com', 'yahoo.com'];

                if (!in_array($domain_part, $allowed_domains)) {
                    $error = '⚠️ Security Warning: Only Gmail and Yahoo emails are accepted for verification purposes.';
                } else {
                    // Check if email exists
                    $exists = false;
                    foreach ($users as $user) {
                        if (isset($user['email']) && strtolower($user['email']) === strtolower($value)) {
                            $exists = true;
                            break;
                        }
                    }

                    if ($exists) {
                        $error = 'Email already exists, please use another.';
                    } else {
                        $validation_passed = true;
                    }
                }
            }
            break;

        case 4: // Validate Phone
            $phone = $form_data['phone'];

            if (empty($phone) || strlen($phone) !== 11) {
                $error = 'Please enter your real Nigerian phone number.';
            } else {
                // Check valid Nigerian prefixes
                $validPrefixes = ['080', '081', '090', '091', '070', '071', '082', '083', '084', '085', '086', '087', '088', '089'];
                $prefix = substr($phone, 0, 3);

                if (!in_array($prefix, $validPrefixes)) {
                    $error = 'Please enter your real Nigerian phone number.';
                } else {
                    // Check if phone exists
                    $exists = false;
                    foreach ($users as $user) {
                        if (isset($user['phone']) && $user['phone'] === $phone) {
                            $exists = true;
                            break;
                        }
                    }

                    if ($exists) {
                        $error = 'Phone number already exists';
                    } else {
                        $validation_passed = true;
                    }
                }
            }
            break;

        case 5: // Validate Password
            $value = $form_data['password'];
            $username = $form_data['username'];
            $email = $form_data['email'];
            $phone = $form_data['phone'];

            if (empty($value) || strlen($value) < 8) {
                $error = 'Password must be at least 8 characters long';
            } elseif (strtolower($value) === strtolower($username) || 
                     strtolower($value) === strtolower($email) || 
                     $value === $phone) {
                $error = 'Password cannot be the same as your username, phone number, or email.';
            } else {
                $validation_passed = true;
            }
            break;

        case 6: // Validate Confirm Password and Send Verification Email
            $value = $form_data['confirm_password'];
            $password = $form_data['password'];

            if ($value !== $password) {
                $error = 'Passwords do not match';
            } else {
                // Generate and send verification code
                $verificationCode = generateVerificationCode();
                storeVerificationCode($form_data['email'], $form_data['fullname'], $verificationCode);

                if (sendVerificationEmail($form_data['email'], $form_data['fullname'], $verificationCode)) {
                    $validation_passed = true;
                    $success = 'Verification code sent to your email!';
                } else {
                    $error = 'Failed to send verification email. Please try again.';
                }
            }
            break;

        case 7: // Email Verification
            $resend_code = $_POST['resend_code'] ?? '0';

            // Handle resend code request
            if ($resend_code === '1') {
                // Generate and send new verification code
                $newVerificationCode = generateVerificationCode();
                storeVerificationCode($form_data['email'], $form_data['fullname'], $newVerificationCode);

                if (sendVerificationEmail($form_data['email'], $form_data['fullname'], $newVerificationCode)) {
                    $success = '✅ New verification code sent to your email!';
                    $current_step = 7; // Stay on the same step
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
                } else {
                    // REAL EMAIL VERIFICATION - Check against stored code in Firebase
                    $email = $form_data['email'];
                    $users = getUsers();

                    $codeValid = false;
                    foreach ($users as $user) {
                        if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                            if (isset($user['verification_code']) && $user['verification_code'] === $verification_code) {
                                // Check if code hasn't expired (5 minutes)
                                if (isset($user['verification_expiry'])) {
                                    $expiry = strtotime($user['verification_expiry']);
                                    if (time() <= $expiry) {
                                        $codeValid = true;
                                    }
                                }
                            }
                            break;
                        }
                    }

                    if ($codeValid) {
                        $validation_passed = true;
                        $success = '🎉 Email verified successfully!';
                    } else {
                        $error = '❌ Invalid or expired verification code. Please check your email or request a new code.';
                    }
                }
            }
            break;

        case 8: // Complete Registration
            $fullname = $form_data['fullname'];
            $username = $form_data['username'];
            $email = $form_data['email'];
            $phone = $form_data['phone'];
            $password = $form_data['password'];

            // Create new user
            $user = [
                'id' => uniqid(),
                'name' => $fullname,
                'username' => $username,
                'phone' => $phone,
                'email' => $email,
                'password' => $password,
                'balance' => 0.00,
                'commission_balance' => 5000.00,
                'referral_balance' => 0.00,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'current_package_cycle' => 0,
                'todays_orders_completed' => 0,
                'todays_commission' => 0,
                'last_order_date' => date('Y-m-d')
            ];

            // Add to users array
            $users[] = $user;

            // Save to Firebase
            if (saveUsers($users)) {
                // Create user data in Firebase
                saveUserTransactions($email, []);

                // Generate personalized welcome notification for new user
                $welcome_notification = [
                    'id' => 'welcome_' . time() . '_' . rand(1000, 9999),
                    'type' => 'welcome',
                    'title' => 'Welcome to LET PAY YOU!',
                    'message' => "🎉 Welcome " . $fullname . "! Your account has been created successfully on LET PAY YOU - Nigeria's premier financial platform. We're excited to have you join thousands of users who earn daily through our innovative system. To get started: 💰 Make your first deposit, 📦 Choose an investment package, 🎮 Play exciting games to multiply your earnings, 🎯 Complete daily orders for guaranteed profits. Your journey to financial freedom starts now!",
                    'amount' => 0,
                    'status' => 'unread',
                    'created_at' => date('Y-m-d H:i:s'),
                    'read_at' => null
                ];

                // Add welcome notification using Firebase format
                addUserNotification($email, $welcome_notification);

                // Set session
                $_SESSION['user_email'] = $email;
                $_SESSION['logged_in'] = true;
                $_SESSION['user'] = $user;

                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Failed to save user data';
            }
            break;
    }

    // If validation passed, move to next step
    if ($validation_passed && $current_step < 7) {
        $current_step++;
        $error = '';
        $success = '';
    } elseif ($validation_passed && $current_step == 7) {
        // Show completion step
        $current_step = 8;
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
    <title>Join LET PAY YOU - Elite Registration</title>
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

        /* COMBINED 3D ANIMATION CONTAINER */
        .animated-logo-circle {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            perspective: 1000px;
            animation: master-animation-cycle 50s ease-in-out infinite;
        }

        /* 3D CUBE FACES */
        .logo-face {
            width: 100%;
            height: 100%;
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            backface-visibility: hidden;
            box-shadow: 
                0 20px 40px rgba(102, 126, 234, 0.4),
                inset 0 0 30px rgba(255, 255, 255, 0.1);
            transition: all 0.5s ease;
            animation: cube-phase 50s ease-in-out infinite;
        }

        /* CUBE EFFECT - Phase 1 (0-10s) */
        .logo-face.front {
            transform: rotateY(0deg) translateZ(100px);
            background: linear-gradient(135deg, #667eea, #764ba2);
            clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
        }
        
        @keyframes cube-phase {
            0% { opacity: 1; pointer-events: auto; }
            19% { opacity: 1; pointer-events: auto; }
            20% { opacity: 0; pointer-events: none; }
            100% { opacity: 0; pointer-events: none; }
        }

        /* HEXAGON GRID - Phase 2 (10-20s) */
        .hexagon-container {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            animation: hexagon-phase 50s ease-in-out infinite;
        }

        .hexagon-grid {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            position: relative;
            animation: hexagon-pulse 2s ease-in-out infinite;
        }

        .hex-pattern {
            position: absolute;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.2);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            animation: hex-float 3s ease-in-out infinite;
        }

        /* LIQUID MORPH - Phase 3 (20-30s) */
        .liquid-container {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            animation: liquid-phase 50s ease-in-out infinite;
        }

        .liquid-morph {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            animation: morph-animation 4s ease-in-out infinite;
            box-shadow: 
                0 20px 50px rgba(245, 158, 11, 0.5),
                inset 0 0 40px rgba(255, 255, 255, 0.2);
        }

        /* DIAMOND SHAPE - Phase 4 (30-40s) */
        .diamond-container {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            animation: diamond-phase 50s ease-in-out infinite;
        }

        .diamond-shape {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);
            position: relative;
            animation: diamond-rotate 3s ease-in-out infinite;
            box-shadow: 
                0 20px 50px rgba(139, 92, 246, 0.6),
                inset 0 0 40px rgba(255, 255, 255, 0.3);
        }

        /* STAR BURST - Phase 5 (40-50s) */
        .star-container {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            animation: star-phase 50s ease-in-out infinite;
        }

        .star-burst {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #10b981, #34d399);
            clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
            position: relative;
            animation: star-spin 5s linear infinite;
            box-shadow: 
                0 20px 50px rgba(16, 185, 129, 0.6),
                inset 0 0 40px rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            text-align: center;
            letter-spacing: 2px;
            z-index: 20;
            position: absolute;
            animation: text-glow 2s ease-in-out infinite alternate;
            text-shadow: 
                0 0 10px rgba(0, 0, 0, 0.5),
                0 0 20px rgba(255, 255, 255, 0.3);
        }
        
        /* Different text colors for each phase */
        .hexagon-container .logo-text {
            color: #fff;
            text-shadow: 
                0 0 15px rgba(239, 68, 68, 0.8),
                0 0 30px rgba(220, 38, 38, 0.6);
        }
        
        .liquid-container .logo-text {
            color: #fff;
            text-shadow: 
                0 0 15px rgba(245, 158, 11, 0.8),
                0 0 30px rgba(217, 119, 6, 0.6);
        }
        
        .diamond-container .logo-text {
            color: #fff;
            text-shadow: 
                0 0 15px rgba(139, 92, 246, 0.8),
                0 0 30px rgba(167, 139, 250, 0.6);
        }
        
        .star-container .logo-text {
            color: #fff;
            text-shadow: 
                0 0 15px rgba(16, 185, 129, 0.8),
                0 0 30px rgba(52, 211, 153, 0.6);
        }

        /* MASTER ANIMATION CYCLE - 50 seconds total (5 phases x 10 seconds each) */
        @keyframes master-animation-cycle {
            /* Phase 1: 3D CUBE (0-10s = 0-20%) WEPAY-YOU */
            0%, 20% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg);
            }
            5% {
                transform: rotateX(90deg) rotateY(90deg) rotateZ(0deg);
            }
            10% {
                transform: rotateX(180deg) rotateY(180deg) rotateZ(0deg);
            }
            15% {
                transform: rotateX(270deg) rotateY(270deg) rotateZ(0deg);
            }
            
            /* Phase 2: HEXAGON GRID (10-20s = 20-40%) JOIN-NOW */
            20.1%, 40% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg) scale(1);
            }
            25% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(60deg) scale(1.1);
            }
            30% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(120deg) scale(1.2);
            }
            35% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(180deg) scale(1.1);
            }
            
            /* Phase 3: LIQUID MORPH (20-30s = 40-60%) INVEST-NOW */
            40.1%, 60% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg) scale(1);
            }
            45% {
                transform: rotateX(0deg) rotateY(360deg) rotateZ(0deg) scale(1.15);
            }
            50% {
                transform: rotateX(360deg) rotateY(0deg) rotateZ(0deg) scale(1.15);
            }
            55% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(360deg) scale(1);
            }
            
            /* Phase 4: DIAMOND SHAPE (30-40s = 60-80%) FINANCIAL-FREEDOM */
            60.1%, 80% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg) scale(1);
            }
            65% {
                transform: rotateX(45deg) rotateY(45deg) rotateZ(0deg) scale(1.1);
            }
            70% {
                transform: rotateX(0deg) rotateY(90deg) rotateZ(45deg) scale(1.15);
            }
            75% {
                transform: rotateX(90deg) rotateY(0deg) rotateZ(0deg) scale(1.1);
            }
            
            /* Phase 5: STAR BURST (40-50s = 80-100%) WELCOME */
            80.1%, 100% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg) scale(1);
            }
            85% {
                transform: rotateX(0deg) rotateY(180deg) rotateZ(0deg) scale(1.2);
            }
            90% {
                transform: rotateX(180deg) rotateY(0deg) rotateZ(0deg) scale(1.2);
            }
            95% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(180deg) scale(1);
            }
        }

        /* HEXAGON ANIMATIONS */
        @keyframes hexagon-phase {
            0%, 19% { opacity: 0; pointer-events: none; }
            20% { opacity: 1; pointer-events: auto; }
            39% { opacity: 1; pointer-events: auto; }
            40%, 100% { opacity: 0; pointer-events: none; }
        }

        @keyframes hexagon-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes hex-float {
            0%, 100% { 
                transform: translateY(0) rotate(0deg);
                opacity: 0.3;
            }
            50% { 
                transform: translateY(-10px) rotate(60deg);
                opacity: 0.7;
            }
        }

        /* LIQUID MORPH ANIMATIONS */
        @keyframes liquid-phase {
            0%, 39% { opacity: 0; pointer-events: none; }
            40% { opacity: 1; pointer-events: auto; }
            59% { opacity: 1; pointer-events: auto; }
            60%, 100% { opacity: 0; pointer-events: none; }
        }
        
        /* DIAMOND ANIMATIONS */
        @keyframes diamond-phase {
            0%, 59% { opacity: 0; pointer-events: none; }
            60% { opacity: 1; pointer-events: auto; }
            79% { opacity: 1; pointer-events: auto; }
            80%, 100% { opacity: 0; pointer-events: none; }
        }

        @keyframes diamond-rotate {
            0%, 100% { 
                transform: rotate(0deg) scale(1);
            }
            25% { 
                transform: rotate(90deg) scale(1.1);
            }
            50% { 
                transform: rotate(180deg) scale(1.05);
            }
            75% { 
                transform: rotate(270deg) scale(1.1);
            }
        }
        
        /* STAR BURST ANIMATIONS */
        @keyframes star-phase {
            0%, 79% { opacity: 0; pointer-events: none; }
            80% { opacity: 1; pointer-events: auto; }
            99% { opacity: 1; pointer-events: auto; }
            100% { opacity: 0; pointer-events: none; }
        }

        @keyframes star-spin {
            0% { 
                transform: rotate(0deg);
            }
            100% { 
                transform: rotate(360deg);
            }
        }

        @keyframes morph-animation {
            0%, 100% {
                border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
                background: linear-gradient(135deg, #10b981, #3b82f6);
            }
            25% {
                border-radius: 58% 42% 75% 25% / 76% 46% 54% 24%;
                background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            }
            50% {
                border-radius: 50% 50% 33% 67% / 55% 27% 73% 45%;
                background: linear-gradient(135deg, #8b5cf6, #f59e0b);
            }
            75% {
                border-radius: 33% 67% 58% 42% / 63% 68% 32% 37%;
                background: linear-gradient(135deg, #f59e0b, #10b981);
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

        /* Completion Animation */
        .completion-animation {
            text-align: center;
        }

        .checkmark {
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

        .completion-title {
            font-size: 2rem;
            font-weight: 800;
            color: #10b981;
            margin-bottom: 10px;
        }

        .completion-message {
            color: rgba(51, 51, 51, 0.8);
            font-size: 1.1rem;
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
            Elite Registration
        </div>
    </div>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="professional-image">
            <div class="animated-logo-circle">
                <!-- Phase 1: 3D CUBE EFFECT - WEPAY-YOU -->
                <div class="logo-face front">
                    <div class="logo-text">WEPAY-YOU</div>
                </div>
                
                <!-- Phase 2: HEXAGON GRID EFFECT - JOIN-NOW -->
                <div class="hexagon-container">
                    <div class="hexagon-grid">
                        <div class="hex-pattern" style="top: 20%; left: 30%;"></div>
                        <div class="hex-pattern" style="top: 50%; left: 20%; animation-delay: 0.5s;"></div>
                        <div class="hex-pattern" style="top: 70%; left: 50%; animation-delay: 1s;"></div>
                        <div class="hex-pattern" style="top: 30%; left: 70%; animation-delay: 1.5s;"></div>
                    </div>
                    <div class="logo-text">JOIN-NOW</div>
                </div>
                
                <!-- Phase 3: LIQUID MORPH EFFECT - INVEST-NOW -->
                <div class="liquid-container">
                    <div class="liquid-morph"></div>
                    <div class="logo-text">INVEST-NOW</div>
                </div>
                
                <!-- Phase 4: DIAMOND SHAPE EFFECT - FINANCIAL-FREEDOM -->
                <div class="diamond-container">
                    <div class="diamond-shape"></div>
                    <div class="logo-text">FINANCIAL-FREEDOM</div>
                </div>
                
                <!-- Phase 5: STAR BURST EFFECT - WELCOME -->
                <div class="star-container">
                    <div class="star-burst"></div>
                    <div class="logo-text">WELCOME</div>
                </div>
            </div>
        </div>
        <h1 class="hero-title">Join The Elite</h1>
        <p class="hero-subtitle">Experience investment opportunities with Nigeria's most trusted financial platform. Your journey to financial freedom starts here.</p>
    </div>

    <!-- Progressive Form -->
    <div class="form-container">
        <div class="loading-overlay" id="loading-overlay">
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <div class="loading-text">Validating your information...</div>
            </div>
        </div>

        <div class="progress-indicator">
            <?php for ($i = 1; $i <= 7; $i++): ?>
                <div class="progress-dot <?= $current_step >= $i ? 'active' : '' ?>"></div>
            <?php endfor; ?>
        </div>

        <div class="step-container">
            <?php if ($current_step == 1): ?>
                <!-- Step 1: Full Name -->
                <h3 class="step-title">What's your full name?</h3>
                <p class="step-description">Let's start with your complete name as it appears on your official documents.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="1">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'fullname'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="text" class="form-input" name="fullname" value="<?= htmlspecialchars($form_data['fullname'] ?? '') ?>" placeholder="Enter your full name" autocomplete="name" required>
                        <?php if ($error): ?>
                            <div class="validation-message error"><?= $error ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-arrow-right"></i> Continue
                    </button>
                </form>

            <?php elseif ($current_step == 2): ?>
                <!-- Step 2: Username -->
                <h3 class="step-title">Choose your username</h3>
                <p class="step-description">Create a unique username that you'll use to access your account.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="2">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'username'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="text" class="form-input" name="username" value="<?= htmlspecialchars($form_data['username'] ?? '') ?>" placeholder="Enter your username" autocomplete="username" required>
                        <?php if ($error): ?>
                            <div class="validation-message error"><?= $error ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-arrow-right"></i> Continue
                    </button>
                </form>

            <?php elseif ($current_step == 3): ?>
                <!-- Step 3: Email -->
                <h3 class="step-title">What's your email?</h3>
                <p class="step-description">We'll use this email for important account notifications and communications.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="3">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'email'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" placeholder="Enter your email address" autocomplete="email" required>
                        <?php if ($error): ?>
                            <div class="validation-message error"><?= $error ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-arrow-right"></i> Continue
                    </button>
                </form>

            <?php elseif ($current_step == 4): ?>
                <!-- Step 4: Phone -->
                <h3 class="step-title">Your phone number</h3>
                <p class="step-description">Enter your Nigerian phone number for account security and verification.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="4">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'phone'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="tel" class="form-input" name="phone" value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>" placeholder="Enter your phone number" autocomplete="tel" required>
                        <?php if ($error): ?>
                            <div class="validation-message error"><?= $error ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-arrow-right"></i> Continue
                    </button>
                </form>

            <?php elseif ($current_step == 5): ?>
                <!-- Step 5: Password -->
                <h3 class="step-title">Create a secure password</h3>
                <p class="step-description">Choose a strong password that's at least 8 characters long.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="5">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'password'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="password" class="form-input" name="password" value="<?= htmlspecialchars($form_data['password'] ?? '') ?>" placeholder="Enter your password" autocomplete="new-password" required>
                        <?php if ($error): ?>
                            <div class="validation-message error"><?= $error ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-arrow-right"></i> Continue
                    </button>
                </form>

            <?php elseif ($current_step == 6): ?>
                <!-- Step 6: Confirm Password -->
                <h3 class="step-title">Confirm your password</h3>
                <p class="step-description">Enter your password again to make sure it matches.</p>
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="6">
                    <?php foreach ($form_data as $key => $value): ?>
                        <?php if ($key != 'confirm_password'): ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="form-group">
                        <input type="password" class="form-input" name="confirm_password" value="<?= htmlspecialchars($form_data['confirm_password'] ?? '') ?>" placeholder="Confirm your password" autocomplete="new-password" required>
                        <?php if ($error): ?>
                            <div class="validation-message error"><?= $error ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-crown"></i> Complete Registration
                    </button>
                </form>

            <?php elseif ($current_step == 7): ?>
                <!-- Step 7: Email Verification -->
                <h3 class="step-title">Email Verification</h3>
                <p class="step-description">A quick verification code has been sent to <strong><?= htmlspecialchars($form_data['email']) ?></strong>. Please confirm your email by entering the 6-digit code.</p>

                <!-- Countdown Timer -->
                <div class="verification-timer" id="verification-timer">
                    <div class="timer-display">
                        <i class="fas fa-clock"></i> 
                        <span id="countdown-text">Code expires in: <strong><span id="countdown">59</span>s</strong></span>
                    </div>
                </div>

                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="7">
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
                        <i class="fas fa-envelope-check"></i> Verify Email
                    </button>
                </form>

            <?php elseif ($current_step == 8): ?>
                <!-- Final Registration Step -->
                <form method="POST" id="step-form">
                    <input type="hidden" name="current_step" value="8">
                    <?php foreach ($form_data as $key => $value): ?>
                        <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                    <?php endforeach; ?>

                    <div class="completion-animation">
                        <div class="checkmark">
                            <i class="fas fa-check" style="color: white; font-size: 2rem;"></i>
                        </div>
                        <h3 class="completion-title">Almost Done!</h3>
                        <p class="completion-message">Click below to complete your registration.</p>
                        <br>
                        <button type="submit" class="submit-btn" id="submit-btn">
                            <i class="fas fa-crown"></i> Create My Account
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="form-links">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
                <div class="divider">•</div>
                <p>Forgot your password? <a href="reset-password.php"><i class="fas fa-key"></i> Reset Password</a></p>
                <div class="divider">•</div>
                <p>Need help? <a href="support.php"><i class="fas fa-headset"></i> Contact Support</a></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 5-PHASE ANIMATION TEXT - Static words for each phase
            const cubeText = document.querySelector('.logo-face.front .logo-text');
            const hexText = document.querySelector('.hexagon-container .logo-text');
            const liquidText = document.querySelector('.liquid-container .logo-text');
            const diamondText = document.querySelector('.diamond-container .logo-text');
            const starText = document.querySelector('.star-container .logo-text');
            
            // No text updates needed - each phase has its static word in HTML
            // Phase 1 (0-10s): WEPAY-YOU
            // Phase 2 (10-20s): JOIN-NOW
            // Phase 3 (20-30s): INVEST-NOW
            // Phase 4 (30-40s): FINANCIAL-FREEDOM
            // Phase 5 (40-50s): WELCOME
            
            function updateAnimationText() {
                // Text is now static in HTML for each phase, no updates needed
            }
            
            // Update text every 100ms for smooth transitions
            setInterval(updateAnimationText, 100);
            updateAnimationText(); // Initial call
            
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