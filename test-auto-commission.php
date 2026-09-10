<?php
session_start();

// Set test user for demo
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'testing@example.com';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Commission Test - VTU Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .btn {
            background: #4CAF50;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .btn.danger {
            background: #f44336;
        }
        
        .btn.danger:hover {
            background: #d32f2f;
        }
        
        .result {
            background: #e8f5e8;
            border: 1px solid #4CAF50;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .error {
            background: #ffe8e8;
            border: 1px solid #f44336;
            color: #d32f2f;
        }
        
        .user-info {
            background: #e3f2fd;
            border: 1px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .loading {
            display: none;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Automatic Commission Calculator Test</h1>
            <p>Test the new automatic commission calculation and daily limit system</p>
        </div>
        
        <div class="user-info">
            <strong>Current Test User:</strong> <?php echo $_SESSION['email']; ?>
            <br>
            <small>The system will automatically calculate commission based on your active packages and track daily limits.</small>
        </div>
        
        <div class="test-section">
            <h3>📊 Check User Status</h3>
            <button class="btn" onclick="checkUserStatus()">Check Current Status</button>
            <div id="status-result"></div>
        </div>
        
        <div class="test-section">
            <h3>💰 Test Automatic Commission Calculation</h3>
            <p><strong>How it works:</strong></p>
            <ul>
                <li><strong>Bronze Package:</strong> ₦150 daily profit ÷ 20 orders = ₦7.50 per order</li>
                <li><strong>Silver Package:</strong> ₦300 daily profit ÷ 20 orders = ₦15.00 per order</li>
                <li><strong>Smart Calculation:</strong> If 4 orders left and ₦22 needed, gives ₦22 (not ₦30)</li>
                <li><strong>Stops Exactly:</strong> At ₦150 for Bronze, ₦300 for Silver - NO MORE!</li>
            </ul>
            <button class="btn" onclick="calculateCommission()">Show Current Package Commission</button>
            <div id="commission-result"></div>
        </div>
        
        <div class="test-section">
            <h3>⚡ Trigger Auto Processing</h3>
            <p>This will create a new order completion record and automatically process it with calculated commission.</p>
            <button class="btn" onclick="triggerAutoProcessing()">Trigger Auto Processing</button>
            <div id="processing-result"></div>
        </div>
        
        <div class="test-section">
            <h3>🔄 Check for Completed Orders</h3>
            <button class="btn" onclick="checkCompletedOrders()">Check & Process Orders</button>
            <div id="completion-result"></div>
        </div>
        
        <div class="test-section">
            <h3>📈 Get Dashboard Progress</h3>
            <button class="btn" onclick="getDashboardProgress()">Get Current Progress</button>
            <div id="progress-result"></div>
        </div>
        
        <div class="loading" id="loading">Processing...</div>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }
        
        function showResult(elementId, data, isError = false) {
            const element = document.getElementById(elementId);
            element.innerHTML = `<div class="result ${isError ? 'error' : ''}">
                <pre>${JSON.stringify(data, null, 2)}</pre>
            </div>`;
        }
        
        async function checkUserStatus() {
            showLoading();
            try {
                const response = await fetch('order-auto-update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_dashboard_progress'
                });
                
                const data = await response.json();
                showResult('status-result', data);
            } catch (error) {
                showResult('status-result', { error: error.message }, true);
            } finally {
                hideLoading();
            }
        }
        
        async function calculateCommission() {
            showLoading();
            // This function will show what the auto-calculated commission would be
            // by triggering the calculation without actually processing
            try {
                const response = await fetch('test-auto-commission.php?action=calc_only', {
                    method: 'POST'
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const data = await response.text();
                showResult('commission-result', { 
                    message: "Commission calculation would be handled by the system automatically",
                    note: "Actual calculation happens during order processing",
                    response: data
                });
            } catch (error) {
                showResult('commission-result', { error: error.message }, true);
            } finally {
                hideLoading();
            }
        }
        
        async function triggerAutoProcessing() {
            showLoading();
            try {
                const response = await fetch('order-auto-update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=trigger_auto_processing'
                });
                
                const data = await response.json();
                showResult('processing-result', data);
            } catch (error) {
                showResult('processing-result', { error: error.message }, true);
            } finally {
                hideLoading();
            }
        }
        
        async function checkCompletedOrders() {
            showLoading();
            try {
                const response = await fetch('order-auto-update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=check_completions'
                });
                
                const data = await response.json();
                showResult('completion-result', data);
            } catch (error) {
                showResult('completion-result', { error: error.message }, true);
            } finally {
                hideLoading();
            }
        }
        
        async function getDashboardProgress() {
            showLoading();
            try {
                const response = await fetch('order-auto-update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_dashboard_progress'
                });
                
                const data = await response.json();
                showResult('progress-result', data);
            } catch (error) {
                showResult('progress-result', { error: error.message }, true);
            } finally {
                hideLoading();
            }
        }
    </script>
</body>
</html>