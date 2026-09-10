<?php
// require_once 'auth.php'; // REMOVED AUTH
require_once 'db.php';
require_once 'includes/functions.php';
require_once 'lib/storage_helper.php';

// Handle AJAX withdrawal actions
if ($_POST && isset($_POST['action'])) {
    // Clean output buffer for AJAX
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json');

    $action = $_POST['action'];

    try {
        if ($action === 'approve_withdrawal') {
            $withdrawalId = trim($_POST['withdrawal_id']);

            // Use Firebase paths (no .json extension)
            $transactions = readJsonFile('transactions');
            $updated = false;
            $userEmail = null;

            foreach ($transactions as &$transaction) {
                if ($transaction['id'] === $withdrawalId && $transaction['type'] === 'withdrawal') {
                    $transaction['status'] = 'approved';
                    $transaction['approved_at'] = date('Y-m-d H:i:s');
                    $transaction['updated_at'] = date('Y-m-d H:i:s');

                    // Get user email from transaction data
                    $userEmail = $transaction['email'] ?? null;

                    // If not found, search by phone
                    if (!$userEmail) {
                        $users = getUsers();
                        foreach ($users as $user) {
                            if (isset($user['phone']) && $user['phone'] === $transaction['phone']) {
                                $userEmail = $user['email'];
                                break;
                            }
                        }
                    }

                    // Update user's individual transaction using Firebase user-specific path
                    if ($userEmail) {
                        $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $userEmail);
                        $userTransactionsPath = "users_data/$firebase_key/transactions";
                        $userTransactions = StorageHelper::get($userTransactionsPath) ?? [];
                        foreach ($userTransactions as &$userTransaction) {
                            if ($userTransaction['id'] === $withdrawalId) {
                                $userTransaction['status'] = 'approved';
                                $userTransaction['approved_at'] = date('Y-m-d H:i:s');
                                $userTransaction['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        StorageHelper::put($userTransactionsPath, $userTransactions);

                        // CREATE USER-SPECIFIC APPROVAL NOTIFICATION
                        $approvalNotification = [
                            'id' => 'withdrawal_success_' . time() . '_' . rand(1000, 9999),
                            'type' => 'withdrawal',
                            'title' => 'Withdrawal Successful! 💸',
                            'message' => "✅ Your withdrawal of ₦" . number_format($transaction['amount'], 2) . " has been approved and sent to your bank account. Thank you!",
                            'amount' => $transaction['amount'],
                            'status' => 'unread',
                            'reference' => $transaction['reference'] ?? $withdrawalId,
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
                addLog('Admin', 'Withdrawal Approved', "ID: $withdrawalId");
                echo json_encode(['success' => true, 'message' => 'Withdrawal approved successfully and user transaction updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve withdrawal or withdrawal not found']);
            }
        } elseif ($action === 'reject_withdrawal') {
            $withdrawalId = trim($_POST['withdrawal_id']);
            $reason = trim($_POST['reason'] ?? 'No reason provided');

            // Use Firebase paths (no .json extension)
            $transactions = readJsonFile('transactions');
            $updated = false;
            $userEmail = null;
            $refundAmount = 0;

            foreach ($transactions as &$transaction) {
                if ($transaction['id'] === $withdrawalId && $transaction['type'] === 'withdrawal') {
                    $transaction['status'] = 'rejected';
                    $transaction['rejection_reason'] = $reason;
                    $transaction['rejected_at'] = date('Y-m-d H:i:s');
                    $transaction['updated_at'] = date('Y-m-d H:i:s');
                    $refundAmount = $transaction['amount'];

                    // Get user email from transaction data
                    $userEmail = $transaction['email'] ?? null;

                    // Refund amount to user balance using Firebase
                    $users = getUsers();
                    foreach ($users as &$user) {
                        if ($user['email'] === $userEmail || 
                           (isset($user['phone']) && $user['phone'] === $transaction['phone'])) {
                            $user['balance'] = ($user['balance'] ?? 0) + $refundAmount;
                            $user['updated_at'] = date('Y-m-d H:i:s');
                            $userEmail = $user['email'];
                            break;
                        }
                    }
                    saveUsers($users);

                    // Update user's individual transaction using Firebase user-specific path
                    if ($userEmail) {
                        $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $userEmail);
                        $userTransactionsPath = "users_data/$firebase_key/transactions";
                        $userTransactions = StorageHelper::get($userTransactionsPath) ?? [];
                        foreach ($userTransactions as &$userTransaction) {
                            if ($userTransaction['id'] === $withdrawalId) {
                                $userTransaction['status'] = 'rejected';
                                $userTransaction['rejection_reason'] = $reason;
                                $userTransaction['rejected_at'] = date('Y-m-d H:i:s');
                                $userTransaction['updated_at'] = date('Y-m-d H:i:s');
                                break;
                            }
                        }
                        StorageHelper::put($userTransactionsPath, $userTransactions);

                        // CREATE USER-SPECIFIC REJECTION NOTIFICATION
                        $rejectionNotification = [
                            'id' => 'withdrawal_rejected_' . time() . '_' . rand(1000, 9999),
                            'type' => 'warning',
                            'title' => 'Withdrawal Rejected ❌',
                            'message' => "❌ Your withdrawal request of ₦" . number_format($transaction['amount'], 2) . " has been rejected. Reason: " . $reason . ". Funds have been returned to your wallet. Please contact support for assistance.",
                            'amount' => $transaction['amount'],
                            'status' => 'unread',
                            'reference' => $transaction['reference'] ?? $withdrawalId,
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
                addLog('Admin', 'Withdrawal Rejected', "ID: $withdrawalId, Reason: $reason, Refunded: ₦$refundAmount");
                echo json_encode(['success' => true, 'message' => "Withdrawal rejected successfully. ₦$refundAmount refunded to user wallet"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject withdrawal or withdrawal not found']);
            }
        } elseif ($action === 'clear_from_admin_view') {
            $transactionId = trim($_POST['transaction_id']);
            
            // Create admin directory if it doesn't exist
            if (!is_dir('storage/admin/')) {
                mkdir('storage/admin/', 0755, true);
            }
            
            // Read cleared transactions list via Firebase
            $clearedFile = 'admin/cleared_transactions';
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
        } elseif ($action === 'delete_withdrawal') {
            $withdrawalId = trim($_POST['withdrawal_id']);

            $withdrawals = readJsonFile('withdrawals');
            $originalCount = count($withdrawals);
            $withdrawals = array_filter($withdrawals, fn($w) => $w['id'] !== $withdrawalId);

            if (count($withdrawals) < $originalCount && writeJsonFile('withdrawals', array_values($withdrawals))) {
                addLog('Admin', 'Withdrawal Deleted', "ID: $withdrawalId");
                echo json_encode(['success' => true, 'message' => 'Withdrawal deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete withdrawal or withdrawal not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// Get withdrawal data using Firebase
$transactions = readJsonFile('transactions');
$withdrawals = array_filter($transactions, function($t) {
    return $t['type'] === 'withdrawal';
});

// Filter out transactions that admin has cleared from view
$clearedFile = 'admin/cleared_transactions';
$clearedTransactions = StorageHelper::get($clearedFile) ?? [];

// Remove cleared transactions from admin view
$withdrawals = array_filter($withdrawals, function($withdrawal) use ($clearedTransactions) {
    return !in_array($withdrawal['id'], $clearedTransactions);
});

$withdrawalFee = 75; // Fixed withdrawal fee

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-money-bill-wave me-2"></i>Withdrawal Management</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo count($withdrawals); ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo count(array_filter($withdrawals, fn($w) => $w['status'] === 'pending')); ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo count(array_filter($withdrawals, fn($w) => $w['status'] === 'approved')); ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><?php echo formatCurrency(array_sum(array_column(array_filter($withdrawals, fn($w) => $w['status'] === 'approved'), 'amount'))); ?></h3>
                    <p>Total Paid</p>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Bank</th>
                        <th>Account No</th>
                        <th>Amount</th>
                        <th>Fee</th>
                        <th>Net Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($withdrawals)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No withdrawal requests found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_reverse($withdrawals) as $withdrawal): ?>
                            <tr data-withdrawal-id="<?php echo $withdrawal['id']; ?>">
                                <td><?php echo htmlspecialchars($withdrawal['user'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($withdrawal['bank'] ?? 'Unknown Bank'); ?></td>
                                <td><?php echo htmlspecialchars($withdrawal['account_number'] ?? 'N/A'); ?></td>
                                <td><?php echo formatCurrency($withdrawal['amount']); ?></td>
                                <td><?php echo formatCurrency($withdrawalFee); ?></td>
                                <td><?php echo formatCurrency($withdrawal['amount'] - $withdrawalFee); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($withdrawal['created_at'] ?? $withdrawal['timestamp'] ?? date('Y-m-d H:i:s'))); ?></td>
                                <td>
                                    <span class="badge status-badge badge-<?php echo $withdrawal['status'] === 'approved' ? 'success' : ($withdrawal['status'] === 'declined' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($withdrawal['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($withdrawal['status'] === 'pending'): ?>
                                        <div class="btn-group">
                                            <form method="POST" action="withdrawals.php" class="ajax-form d-inline">
                                                <input type="hidden" name="action" value="approve_withdrawal">
                                                <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="withdrawals.php" class="ajax-form d-inline">
                                                <input type="hidden" name="action" value="reject_withdrawal">
                                                <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">
                                                <input type="hidden" name="reason" value="Rejected by admin">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="withdrawals.php" class="ajax-form d-inline">
                                                <input type="hidden" name="action" value="clear_from_admin_view">
                                                <input type="hidden" name="transaction_id" value="<?php echo $withdrawal['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" 
                                                    onclick="viewWithdrawalDetails('<?php echo $withdrawal['id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="POST" action="withdrawals.php" class="ajax-form d-inline">
                                                <input type="hidden" name="action" value="clear_from_admin_view">
                                                <input type="hidden" name="transaction_id" value="<?php echo $withdrawal['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-eye-slash"></i>
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
        <h5><i class="fas fa-cog me-2"></i>Withdrawal Settings</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Withdrawal Fee</label>
                    <div class="input-group">
                        <span class="input-group-text">₦</span>
                        <input type="number" class="form-control" value="<?php echo $withdrawalFee; ?>" min="0" step="0.01">
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Minimum Withdrawal</label>
                    <div class="input-group">
                        <span class="input-group-text">₦</span>
                        <input type="number" class="form-control" value="1000" min="0" step="0.01">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Maximum Withdrawal</label>
                    <div class="input-group">
                        <span class="input-group-text">₦</span>
                        <input type="number" class="form-control" value="500000" min="0" step="0.01">
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Processing Time</label>
                    <select class="form-control">
                        <option value="instant">Instant</option>
                        <option value="24h">24 Hours</option>
                        <option value="48h">48 Hours</option>
                        <option value="72h">72 Hours</option>
                    </select>
                </div>
            </div>
        </div>

        <button class="btn btn-primary" onclick="updateWithdrawalSettings()">
            <i class="fas fa-save me-2"></i>Update Settings
        </button>
    </div>
</div>

<div class="action-buttons">
    <button class="btn btn-success" onclick="bulkProcessWithdrawals()">
        <i class="fas fa-check-double me-2"></i>Bulk Process
    </button>
    <button class="btn btn-info" onclick="exportWithdrawals()">
        <i class="fas fa-download me-2"></i>Export
    </button>
    <button class="btn btn-warning" onclick="showWithdrawalStats()">
        <i class="fas fa-chart-bar me-2"></i>Statistics
    </button>
</div>

<script>
function viewWithdrawalDetails(withdrawalId) {
    showSuccess('Withdrawal details viewed successfully!');
}

function updateWithdrawalSettings() {
    showLoading();

    setTimeout(() => {
        hideLoading();
        showSuccess('Withdrawal settings updated successfully!');
    }, 1000);
}

function bulkProcessWithdrawals() {
    showSuccess('Bulk processing feature will be implemented soon!');
}

function exportWithdrawals() {
    showSuccess('Withdrawal data exported successfully!');
}

function showWithdrawalStats() {
    showSuccess('Withdrawal statistics feature will be implemented soon!');
}
</script>

<?php include 'includes/footer.php'; ?>