<?php
// Include authentication system
require_once 'auth.php';
require_once 'firebase_setup.php';


// Include ban checking system
require_once 'ban-checker.php';

// SECURITY: Enable proper authentication
requireLogin();

// SECURITY: Check if user is banned and force logout immediately
checkBanStatus();

// Function to format amounts with proper comma separation
function formatAmount($amount) {
    return '₦' . number_format($amount, 2);
}

// Get user data from authenticated session
$current_user_email = $_SESSION['email'];

$user_data = null;
$users = [];

// Get user data directly from Firebase
$users = getUsers();
if (!empty($users)) {
    foreach ($users as $user) {
        if ($user['email'] === $current_user_email) {
            $user_data = $user;
            break;
        }
    }
}

// Handle profile update
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate inputs
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $update_message = 'error:All fields including password are required';
    } else {
        // Verify password - handle both hashed and plain text passwords (same as login.php)
        $password_verified = false;
        if ($user_data && isset($user_data['password'])) {
            $storedPassword = $user_data['password'];
            
            // If stored password looks like a hash (starts with $2y$), use password_verify
            if (substr($storedPassword, 0, 4) === '$2y$') {
                $password_verified = password_verify($password, $storedPassword);
            }
            // Otherwise use plain text comparison
            else {
                $password_verified = (trim($password) === trim($storedPassword));
            }
        }

        if (!$password_verified) {
            $update_message = 'error:Incorrect password';
        } else {
            // Update user data
            for ($i = 0; $i < count($users); $i++) {
                if ($users[$i]['email'] === $current_user_email) {
                    $users[$i]['name'] = $name;
                    $users[$i]['email'] = $email;
                    $users[$i]['phone'] = $phone;
                    $users[$i]['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }

            // Save back to Firebase
            if (saveUsers($users)) {
                // Update session email if changed
                $_SESSION['email'] = $email;
                
                $user_data['name'] = $name;
                $user_data['email'] = $email;
                $user_data['phone'] = $phone;

                $update_message = 'success:Profile updated successfully!';
            } else {
                $update_message = 'error:Failed to update profile';
            }
        }
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Convert dates to Nigeria time
function convertToNigeriaTime($dateString) {
    if (empty($dateString)) return 'Not available';
    try {
        $date = new DateTime($dateString);
        $date->setTimezone(new DateTimeZone('Africa/Lagos'));
        return $date->format('M d, Y \a\t g:i A');
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

// Get last login time (using current time as placeholder)
$last_login = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET PAY YOU - Profile</title>
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
        }

        .logo img {
            height: 40px;
        }

        .back-btn {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #0056b3;
        }

        /* Banner */
        .banner {
            height: 200px;
            background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)),
                       url('images/slide1.svg');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .banner h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .banner p {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Content */
        .content {
            padding: 30px;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 30px;
        }

        .profile-info {
            margin-bottom: 30px;
        }

        .info-row {
            margin-bottom: 25px;
        }

        .info-label {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .info-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            font-size: 1rem;
            color: #495057;
            display: block;
            width: 100%;
        }

        .password-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            font-size: 1.2rem;
            color: #6c757d;
            letter-spacing: 3px;
            display: block;
            width: 100%;
        }

        .password-note {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 8px;
        }

        /* Buttons */
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Referral Section */
        .referral-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            text-align: center;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .form-input {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Alert */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        /* Responsive */
        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }

            .banner h1 {
                font-size: 1.5rem;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <img src="logo.svg" alt="LET PAY YOU">
            </div>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <!-- Banner -->
        <div class="banner" style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('attached_assets/attached_assets/generated_images/Casual_lifestyle_portrait_df1b43be.png'); background-size: cover; background-position: center;">
            <div>
                <h1>User Profile</h1>
                <p>Manage your account information and settings</p>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if ($update_message): ?>
                <?php 
                $parts = explode(':', $update_message, 2);
                $type = $parts[0];
                $message = $parts[1];
                ?>
                <div class="alert alert-<?= $type ?>">
                    <?= $type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Profile Card -->
            <div class="profile-card">
                <?php if ($user_data): ?>
                    <div class="profile-info">
                        <div class="info-row">
                            <label class="info-label">Full Name</label>
                            <div class="info-display"><?= htmlspecialchars($user_data['name'] ?? 'N/A') ?></div>
                        </div>

                        <div class="info-row">
                            <label class="info-label">Username / User ID</label>
                            <div class="info-display"><?= htmlspecialchars($user_data['username'] ?? $user_data['name'] ?? 'N/A') ?></div>
                        </div>

                        <div class="info-row">
                            <label class="info-label">Email Address</label>
                            <div class="info-display"><?= htmlspecialchars($user_data['email'] ?? 'N/A') ?></div>
                        </div>

                        <div class="info-row">
                            <label class="info-label">Phone Number</label>
                            <div class="info-display"><?= htmlspecialchars($user_data['phone'] ?? 'N/A') ?></div>
                        </div>

                        <div class="info-row">
                            <label class="info-label">Registration Date</label>
                            <div class="info-display"><?= convertToNigeriaTime($user_data['created_at'] ?? '') ?></div>
                        </div>

                        <div class="info-row">
                            <label class="info-label">Last Login</label>
                            <div class="info-display"><?= convertToNigeriaTime($last_login) ?></div>
                        </div>

                        <div class="info-row">
                            <label class="info-label">Password</label>
                            <div class="password-display">•••••••••••••</div>
                            <div class="password-note">Password cannot be changed here</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        ❌ Unable to load user profile data
                    </div>
                <?php endif; ?>

                <div class="btn-group">
                    <button class="btn btn-primary" onclick="openUpdateModal()">Update Profile</button>
                    <a href="reset-password.php" class="btn btn-secondary">Reset Password</a>
                    <a href="?action=logout" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to logout?')">Logout</a>
                </div>
            </div>

            <!-- Referral Section -->
            <div class="referral-section">
                <h2 class="section-title">Referral Program</h2>
                <p style="margin-bottom: 20px; color: #6c757d;">
                    Earn money by inviting friends to join our platform
                </p>
                <a href="referral.php" class="btn btn-primary">View Referral Details</a>
            </div>
        </div>
    </div>

    <!-- Update Profile Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Update Profile</h2>
                <span class="close" onclick="closeUpdateModal()">&times;</span>
            </div>

            <form method="POST" id="updateForm">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label for="modal_name" class="form-label">Full Name</label>
                    <input type="text" id="modal_name" name="name" class="form-input" 
                           value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="modal_email" class="form-label">Email Address</label>
                    <input type="email" id="modal_email" name="email" class="form-input" 
                           value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="modal_phone" class="form-label">Phone Number</label>
                    <input type="tel" id="modal_phone" name="phone" class="form-input" 
                           value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="modal_password" class="form-label">Confirm Password *</label>
                    <input type="password" id="modal_password" name="password" class="form-input" 
                           placeholder="Enter your current password to confirm changes" required>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUpdateModal() {
            document.getElementById('updateModal').style.display = 'block';
            document.getElementById('modal_password').value = '';
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target == modal) {
                closeUpdateModal();
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                alert.style.transition = 'all 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>