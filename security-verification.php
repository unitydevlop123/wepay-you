<?php require_once 'firebase_setup.php'; ?>
<?php
session_start();
$error = '';

// Check if user just logged in or registered
if (!isset($_SESSION['pending_verification'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $security_pin = trim($_POST['security_pin'] ?? '');
    $security_answer = trim($_POST['security_answer'] ?? '');
    
    // BOTH PIN and Security Answer are required
    if (empty($security_pin) || empty($security_answer)) {
        $error = "Please provide BOTH your security PIN and security question answer!";
    } else {
        // Get users from Firebase
        $users = getUsers();
        
        if (!empty($users)) {
            $verified = false;
            $userFound = false;
            
            // Find user and verify security
            foreach ($users as $user) {
                if ($user['email'] === $_SESSION['pending_verification']) {
                    $userFound = true;
                    
                    // Check if user has security credentials saved
                    if (!isset($user['security_pin']) || !isset($user['security_answer'])) {
                        $error = "Security credentials not found. Please contact support.";
                        break;
                    }
                    
                    // BOTH PIN and security answer must be correct
                    $pinCorrect = ($user['security_pin'] === $security_pin);
                    $answerCorrect = (strtolower($user['security_answer']) === strtolower($security_answer));
                    
                    if ($pinCorrect && $answerCorrect) {
                        $verified = true;
                        
                        // Complete the login/registration process
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user'] = [
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'id' => $user['id']
                        ];
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['last_activity'] = time();
                        unset($_SESSION['pending_verification']);
                        
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        // Provide specific error message
                        if (!$pinCorrect && !$answerCorrect) {
                            $error = "Both security PIN and security answer are incorrect. Please try again.";
                        } elseif (!$pinCorrect) {
                            $error = "Security PIN is incorrect. Please try again.";
                        } else {
                            $error = "Security answer is incorrect. Please try again.";
                        }
                    }
                    break;
                }
            }
            
            if (!$userFound) {
                $error = "User not found. Please try logging in again.";
            }
        } else {
            $error = "Verification failed. Please try again.";
        }
    }
}

// Get user's security question if available
$securityQuestion = '';
if (isset($_SESSION['pending_verification'])) {
    $users = getUsers();
    if (!empty($users)) {
        foreach ($users as $user) {
            if ($user['email'] === $_SESSION['pending_verification']) {
                $securityQuestion = $user['security_question'] ?? '';
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification - LET PAY YOU | Elite Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Space Grotesk', sans-serif;
            background: linear-gradient(135deg, #0c0c0c 0%, #1a1a1a 25%, #2d1b69 75%, #8b5cf6 100%);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 30% 40%, rgba(139, 92, 246, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 70% 80%, rgba(59, 130, 246, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 60% 20%, rgba(168, 85, 247, 0.3) 0%, transparent 50%);
            animation: pulse 4s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; }
        }

        .top-header {
            background: rgba(0, 0, 0, 0.9);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(139, 92, 246, 0.3);
            position: relative;
            z-index: 100;
        }

        .logo-section img {
            height: 45px;
            filter: brightness(0) invert(1) !important;
            -webkit-filter: brightness(0) invert(1) !important;
            drop-shadow: 0 0 20px rgba(255,255,255,1) !important;
            opacity: 1 !important;
        }

        .header-info {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Hero Section with Human Image */
        .hero-section {
            position: relative;
            height: 35vh;
            min-height: 280px;
            background: url('https://images.unsplash.com/photo-1560472355-536de3962603?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.85), rgba(118, 75, 162, 0.85));
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            padding: 0 2rem;
        }

        .hero-content h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-content p {
            font-size: 1.2rem;
            font-weight: 400;
            opacity: 0.95;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            line-height: 1.6;
        }

        /* Premium Verification Section */
        .content-section {
            background: #ffffff;
            padding: 2rem;
        }

        .content-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #8b5cf6, #3b82f6, #06b6d4, transparent);
        }

        .verification-card {
            background: #ffffff;
            border-radius: 0;
            box-shadow: none;
            border: none;
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }

        @keyframes cardEntrance {
            from {
                transform: translateY(80px) scale(0.9);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .verification-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(139, 92, 246, 0.15), transparent);
            animation: card-shimmer 4s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes card-shimmer {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(200%) rotate(45deg); }
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            color: #667eea;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .section-title p {
            color: #6c757d;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .security-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .security-steps {
            background: linear-gradient(135deg, #f8f9ff, #e3e8ff);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .step-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.8rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .step-item:last-child {
            margin-bottom: 0;
        }

        .step-number {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 1rem;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.8rem;
            display: block;
            font-size: 1rem;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            padding: 1.2rem 1.5rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            height: auto;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }

        .security-pin-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5rem;
            font-family: 'Courier New', monospace;
        }

        .btn-verify {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 15px;
            padding: 1.3rem 2rem;
            font-size: 1.2rem;
            font-weight: 700;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-verify:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
        }

        .alert {
            border: none;
            border-radius: 15px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 2rem;
        }

        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            background: white;
            padding: 0 1rem;
            color: #718096;
            font-weight: 600;
        }

        .logout-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }

        .logout-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .logout-link a:hover {
            text-decoration: underline;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.2rem;
            }
            
            .hero-content p {
                font-size: 1rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .content-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Top Header with Logo -->
    <div class="top-header">
        <div class="logo-section">
            <img src="logo.svg" alt="LET PAY YOU">
        </div>
        <div class="header-info">
            Ultra Security Portal
        </div>
    </div>

    <!-- Hero Section with Human Image -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="security-icon">
                <i class="fas fa-shield-check"></i>
            </div>
            <h1>Two-Factor Authentication</h1>
            <p>Enter your 4-digit Security PIN to complete login and access your secure VTU dashboard</p>
        </div>
    </section>

    <!-- Content Section -->
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-lock me-3"></i>Security Verification</h2>
            <p>Your account security is our top priority. Complete this final step to access your dashboard.</p>
        </div>
        
        <div class="form-container">
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="security-steps">
                    <div class="step-item">
                        <div class="step-number">✓</div>
                        <div>
                            <strong>Email & Password Verified</strong><br>
                            <small class="text-muted">Your login credentials have been validated</small>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div>
                            <strong>Security PIN & Question Required</strong><br>
                            <small class="text-muted">Enter BOTH your 4-digit PIN and security answer</small>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div>
                            <strong>Dashboard Access</strong><br>
                            <small class="text-muted">Complete access to your VTU account</small>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key me-2"></i>Security PIN (4 digits) <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control security-pin-input" name="security_pin" placeholder="••••" maxlength="4" pattern="[0-9]{4}" required>
                        <small class="text-muted">Enter the 4-digit PIN you set during registration</small>
                    </div>

                    <?php if($securityQuestion): ?>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-question-circle me-2"></i>Security Question <span class="text-danger">*</span>
                            </label>
                            <div class="alert alert-info mb-3">
                                <strong><?= htmlspecialchars($securityQuestion) ?></strong>
                            </div>
                            <input type="text" class="form-control" name="security_answer" placeholder="Enter your answer" required>
                            <small class="text-muted">Answer the security question you set during registration</small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Security Question Not Found!</strong><br>
                            Your account is missing security question. Please contact support.
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-verify text-white">
                        <i class="fas fa-shield-check me-2"></i>
                        Verify & Continue
                    </button>

                    <div class="logout-link">
                        <a href="login.php">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            Cancel & Logout
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format PIN input
        document.querySelector('input[name="security_pin"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>