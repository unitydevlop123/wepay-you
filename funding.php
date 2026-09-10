<?php
// REMOVED: Check for banned users before allowing access to funding services
// require_once 'ban-check.php'; // REMOVED BAN CHECK

// require_once 'auth.php'; // REMOVED AUTH
require_once 'db.php';
require_once 'includes/functions.php';
require_once 'lib/storage_helper.php';

// Handle AJAX requests
if ($_POST && isset($_POST['action'])) {
    // Clean output buffer for AJAX
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json');

    $action = $_POST['action'];

    try {
        if ($action === 'approve_funding') {
            $fundingId = trim($_POST['funding_id']);

            // Use Firebase paths (no .json extension)
            $transactions = readJsonFile('transactions');
            $updated = false;
            $userEmail = null;

            foreach ($transactions as &$funding) {
                if ($funding['id'] === $fundingId && ($funding['type'] === 'deposit' || $funding['type'] === 'funding')) {
                    $funding['status'] = 'approved';
                    $funding['approved_at'] = date('Y-m-d H:i:s');
                    $funding['updated_at'] = date('Y-m-d H:i:s');

                    // Update user balance using Firebase
                    $users = readJsonFile('users');
                    foreach ($users as &$user) {
                        if ($user['email'] === $funding['email']) {
                            $user['balance'] = ($user['balance'] ?? 0) + $funding['amount'];
                            $user['updated_at'] = date('Y-m-d H:i:s');
                            $userEmail = $user['email'];
                            break;
                        }
                    }
                    writeJsonFile('users', $users);

                    // Update user's individual transaction using Firebase user-specific path
                    if ($userEmail) {
                        $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $userEmail);
                        $userTransactionsPath = "users_data/$firebase_key/transactions";
                        $userTransactions = StorageHelper::get($userTransactionsPath) ?? [];
                        foreach ($userTransactions as &$transaction) {
                            if ($transaction['id'] === $fundingId) {
                                $transaction['status'] = 'approved';
                                $transaction['approved_at'] = date('Y-m-d H:i:s');
                                $transaction['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        StorageHelper::put($userTransactionsPath, $userTransactions);

                        // CREATE USER-SPECIFIC APPROVAL NOTIFICATION
                        $approvalNotification = [
                            'id' => 'deposit_success_' . time() . '_' . rand(1000, 9999),
                            'type' => 'deposit',
                            'title' => 'Deposit Successful! 💰',
                            'message' => "✅ Your deposit of ₦" . number_format($funding['amount'], 2) . " has been approved and credited to your wallet. Happy investing!",
                            'amount' => $funding['amount'],
                            'status' => 'unread',
                            'reference' => $funding['reference'] ?? $fundingId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'read_at' => null
                        ];

                        // Save notification to user's Firebase notifications
                        $notificationsPath = "users_data/$firebase_key/notifications";
                        $userNotifications = StorageHelper::get($notificationsPath) ?? [];
                        array_unshift($userNotifications, $approvalNotification);
                        StorageHelper::put($notificationsPath, $userNotifications);
                    }

                    $updated = true;
                    break;
                }
            }

            if ($updated && writeJsonFile('transactions', $transactions)) {
                addLog('Admin', 'Funding Approved', "ID: $fundingId");
                echo json_encode(['success' => true, 'message' => 'Funding approved successfully and user notification created']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve funding or funding not found']);
            }
        } elseif ($action === 'reject_funding') {
            $fundingId = trim($_POST['funding_id']);
            $reason = trim($_POST['reason'] ?? 'No reason provided');

            // Use Firebase paths (no .json extension)
            $transactions = readJsonFile('transactions');
            $updated = false;
            $userEmail = null;

            foreach ($transactions as &$funding) {
                if ($funding['id'] === $fundingId && ($funding['type'] === 'deposit' || $funding['type'] === 'funding')) {
                    $funding['status'] = 'rejected';
                    $funding['rejection_reason'] = $reason;
                    $funding['rejected_at'] = date('Y-m-d H:i:s');
                    $funding['updated_at'] = date('Y-m-d H:i:s');

                    // Get user email for transaction update  
                    $userEmail = $funding['email'];

                    // Update user's individual transaction using Firebase user-specific path
                    if ($userEmail) {
                        $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $userEmail);
                        $userTransactionsPath = "users_data/$firebase_key/transactions";
                        $userTransactions = StorageHelper::get($userTransactionsPath) ?? [];
                        foreach ($userTransactions as &$transaction) {
                            if ($transaction['id'] === $fundingId) {
                                $transaction['status'] = 'rejected';
                                $transaction['rejection_reason'] = $reason;
                                $transaction['rejected_at'] = date('Y-m-d H:i:s');
                                $transaction['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        StorageHelper::put($userTransactionsPath, $userTransactions);

                        // CREATE USER-SPECIFIC REJECTION NOTIFICATION
                        $rejectionNotification = [
                            'id' => 'deposit_rejected_' . time() . '_' . rand(1000, 9999),
                            'type' => 'warning',
                            'title' => 'Deposit Rejected ❌',
                            'message' => "❌ Your deposit of ₦" . number_format($funding['amount'], 2) . " has been rejected. Reason: " . $reason . ". Please contact support for assistance or try again with correct details.",
                            'amount' => $funding['amount'],
                            'status' => 'unread',
                            'reference' => $funding['reference'] ?? $fundingId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'read_at' => null
                        ];

                        // Save notification to user's Firebase notifications
                        $notificationsPath = "users_data/$firebase_key/notifications";
                        $userNotifications = StorageHelper::get($notificationsPath) ?? [];
                        array_unshift($userNotifications, $rejectionNotification);
                        StorageHelper::put($notificationsPath, $userNotifications);
                    }

                    $updated = true;
                    break;
                }
            }

            if ($updated && writeJsonFile('transactions', $transactions)) {
                addLog('Admin', 'Funding Rejected', "ID: $fundingId, Reason: $reason");
                echo json_encode(['success' => true, 'message' => 'Funding rejected successfully and user notification created']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject funding or funding not found']);
            }
        } elseif ($action === 'clear_from_admin_view') {
            $transactionId = trim($_POST['transaction_id']);

            // Create admin directory if it doesn't exist
            if (!is_dir('storage/admin/')) {
                mkdir('storage/admin/', 0755, true);
            }

            // Read cleared transactions list
            $clearedFile = 'storage/admin/cleared_transactions.json';
            $clearedTransactions = StorageHelper::get($clearedFile) ?? [];

            // Add transaction ID to cleared list if not already there
            if (!in_array($transactionId, $clearedTransactions)) {
                $clearedTransactions[] = $transactionId;

                if (StorageHelper::put($clearedFile, $clearedTransactions)) {
                    addLog('Admin', 'Transaction Hidden from View', "ID: $transactionId - Hidden from admin view only");
                    echo json_encode(['success' => true, 'message' => 'Transaction hidden from admin view successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to hide transaction from view']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'Transaction already hidden from view']);
            }
        } elseif ($action === 'manual_funding') {
            $userId = trim($_POST['user_id']);
            $amount = floatval($_POST['amount']);
            $method = trim($_POST['method']);
            $reference = trim($_POST['reference'] ?? '');

            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid amount']);
                exit;
            }

            // Get user
            $users = getUsers();
            $userFound = false;
            foreach ($users as &$user) {
                if ($user['id'] === $userId) {
                    $user['balance'] = ($user['balance'] ?? 0) + $amount;
                    $user['updated_at'] = date('Y-m-d H:i:s');
                    $userFound = true;
                    break;
                }
            }

            if (!$userFound) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            // Create funding record using Firebase
            $fundings = readJsonFile('funding');
            $fundings[] = [
                'id' => uniqid(),
                'user_id' => $userId,
                'user' => $user['username'] ?? $user['name'] ?? 'Unknown',
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'status' => 'approved',
                'type' => 'manual',
                'created_at' => date('Y-m-d H:i:s'),
                'approved_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if (saveUsers($users) && writeJsonFile('funding', $fundings)) {
                addLog('Admin', 'Manual Funding', "Amount: ₦$amount, User: {$user['username']}");
                echo json_encode(['success' => true, 'message' => 'Manual funding added successfully and user balance updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to process manual funding']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// Get funding data using Firebase
$transactions = readJsonFile('transactions');
$fundings = array_filter($transactions, function($t) {
    return $t['type'] === 'funding' || $t['type'] === 'deposit';
});

// Filter out transactions that admin has cleared from view
$clearedFile = 'admin/cleared_transactions';
$clearedTransactions = StorageHelper::get($clearedFile) ?? [];

// Remove cleared transactions from admin view
$fundings = array_filter($fundings, function($funding) use ($clearedTransactions) {
    return !in_array($funding['id'], $clearedTransactions);
});

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-money-bill-wave me-2"></i>Funding Management</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo count($fundings); ?></h3>
                    <p>Total Deposits</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo count(array_filter($fundings, fn($f) => $f['status'] === 'pending')); ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo count(array_filter($fundings, fn($f) => $f['status'] === 'approved')); ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo formatCurrency(array_sum(array_column(array_filter($fundings, fn($f) => $f['status'] === 'approved'), 'amount'))); ?></h3>
                    <p>Total Amount</p>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fundings)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No funding requests found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_reverse($fundings) as $funding): ?>
                            <tr data-id="<?php echo $funding['id']; ?>">
                                <td><?php echo htmlspecialchars($funding['user'] ?? 'Unknown'); ?></td>
                                <td><?php echo formatCurrency($funding['amount']); ?></td>
                                <td><?php echo ucfirst($funding['method'] ?? 'Bank Transfer'); ?></td>
                                <td><?php echo htmlspecialchars($funding['reference'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($funding['created_at'] ?? $funding['timestamp'] ?? date('Y-m-d H:i:s'))); ?></td>
                                <td>
                                    <span class="badge status-badge badge-<?php echo $funding['status'] === 'approved' ? 'success' : ($funding['status'] === 'rejected' ? 'danger' : ($funding['status'] === 'cancelled' ? 'secondary' : 'warning')); ?>">
                                        <?php echo ucfirst($funding['status']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($funding['status'] === 'pending'): ?>
                                        <div class="btn-group">
                                            <form method="POST" action="funding.php" class="ajax-form d-inline">
                                                <input type="hidden" name="action" value="approve_funding">
                                                <input type="hidden" name="funding_id" value="<?php echo $funding['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="funding.php" class="ajax-form d-inline">
                                                <input type="hidden" name="action" value="reject_funding">
                                                <input type="hidden" name="funding_id" value="<?php echo $funding['id']; ?>">
                                                <input type="hidden" name="reason" value="Rejected by admin">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                            <form method="POST" action="funding.php" class="ajax-form d-inline">
                                                <input type="hidden" name="action" value="clear_from_admin_view">
                                                <input type="hidden" name="transaction_id" value="<?php echo $funding['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-eye-slash"></i> Clear
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" 
                                                    onclick="viewFundingDetails('<?php echo $funding['id']; ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if ($funding['status'] === 'approved'): ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="showSuccess('Reverse feature will be implemented soon')">
                                                    <i class="fas fa-undo"></i> Reverse
                                                </button>
                                            <?php endif; ?>
                                            <form method="POST" action="funding.php" class="ajax-form d-inline">
                                                <input type="hidden" name="action" value="clear_from_admin_view">
                                                <input type="hidden" name="transaction_id" value="<?php echo $funding['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-eye-slash"></i> Clear
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-plus me-2"></i>Manual Funding</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="funding.php" class="ajax-form reset-after-submit">
            <input type="hidden" name="action" value="manual_funding">

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Select User</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">Select user...</option>
                            <?php
                            $users = readJsonFile('users');
                            foreach ($users as $user):
                            ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username'] ?? $user['name'] ?? 'Unknown'); ?> - <?php echo $user['phone'] ?? $user['email'] ?? 'No contact'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" 
                               min="1" step="0.01" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-control" required>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card Payment</option>
                            <option value="ussd">USSD</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary form-control">
                            <i class="fas fa-plus me-2"></i>Add
                        </button>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Reference/Notes</label>
                <input type="text" name="reference" class="form-control" 
                       placeholder="Enter reference number or notes">
            </div>
        </form>
    </div>
</div>

<div class="action-buttons">
    <button class="btn btn-success" onclick="bulkApprove()">
        <i class="fas fa-check-double me-2"></i>Bulk Approve
    </button>
    <button class="btn btn-info" onclick="exportFunding()">
        <i class="fas fa-download me-2"></i>Export
    </button>
    <button class="btn btn-warning" onclick="showFundingStats()">
        <i class="fas fa-chart-bar me-2"></i>Statistics
    </button>
</div>

<script>
function viewFundingDetails(fundingId) {
    showSuccess('Funding details viewed successfully!');
}

function exportFunding() {
    showLoading();

    // Create CSV data
    const fundings = <?php echo json_encode($fundings); ?>;
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "ID,User,Amount,Method,Reference,Date,Status\n";

    fundings.forEach(funding => {
        csvContent += `${funding.id},"${funding.user}",${funding.amount},"${funding.method}","${funding.reference}","${funding.created_at}","${funding.status}"\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `funding_export_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    hideLoading();
    showSuccess('Funding data exported successfully!');
}

function bulkApprove() {
    showSuccess('Bulk approve feature will be implemented soon!');
}

function showFundingStats() {
    showSuccess('Funding statistics feature will be implemented soon!');
}
</script>

<?php include 'includes/footer.php'; ?>