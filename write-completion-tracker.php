<?php require_once 'firebase_setup.php'; ?>
<?php
session_start();

// Set testing user session for demo (remove when login system is complete)
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'testing@example.com';
    $_SESSION['user'] = [
        'name' => 'Testing User',
        'email' => 'testing@example.com',
        'id' => '68a70b922f5fe'
    ];
    $_SESSION['user_id'] = '68a70b922f5fe';
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
}

// Ensure JSON response with hosting compatibility
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests for hosting
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_POST['action'] === 'write_completion') {
        $user_email = $_SESSION['email'];
        $commission = floatval($_POST['commission']);
        $redirect_after = isset($_POST['redirect_after']) ? $_POST['redirect_after'] : false;

        // Create completion record
        $completion_record = [
            'id' => uniqid(),
            'email' => $user_email,
            'commission' => $commission,
            'completed_at' => date('Y-m-d H:i:s'),
            'processed' => false
        ];

        // Load existing completions from Firebase
        $completions = readJsonFile('order_completion_tracker');

        // Add new completion
        $completions[] = $completion_record;

        // Save to Firebase
        if (!writeJsonFile('order_completion_tracker', $completions)) {
            throw new Exception('Failed to write completion tracker to Firebase');
        }

        // UNLOCK IMAGE SYSTEM - Clear locked image after successful completion
        // This allows the next person to get a fresh new product image
        unset($_SESSION['current_product_image']);
        unset($_SESSION['current_product_id']);

        // For form submissions (hosting compatibility), return JSON or redirect
        if ($redirect_after) {
            // Form submission - return simple success page
            echo '<!DOCTYPE html><html><head><title>Success</title></head><body>';
            echo '<script>window.parent.orderProcessed = true;</script>';
            echo '<p>Order completed successfully!</p>';
            echo '</body></html>';
        } else {
            // AJAX request - return JSON
            echo json_encode([
                'success' => true,
                'message' => 'Completion recorded successfully',
                'commission_earned' => $commission,
                'completion_id' => $completion_record['id']
            ]);
        }
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if (isset($_POST['redirect_after']) && $_POST['redirect_after']) {
        // Form submission error - return simple error page
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
        echo '<script>window.parent.orderError = true;</script>';
        echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</body></html>';
    } else {
        // AJAX error - return JSON
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}
?>