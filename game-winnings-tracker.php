<?php require_once 'firebase_setup.php'; ?>
<?php
// Game Winnings Tracker - Daily Limit System
// Tracks daily game winnings with ₦1,000 daily limit and 12am Nigeria reset

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

date_default_timezone_set('Africa/Lagos'); // Nigeria timezone

/**
 * Check if user has reached daily game winnings limit
 */
function hasReachedDailyGameLimit($email) {
    $daily_limit = 1000000.00; // ₦1,000,000 daily limit (1 Million)
    $today_winnings = getDailyGameWinnings($email);
    
    return $today_winnings >= $daily_limit;
}

/**
 * Get user's total game winnings for today
 */
function getDailyGameWinnings($email) {
    $winnings_data = readJsonFile('daily_game_winnings') ?: [];
    $today = date('Y-m-d');
    
    foreach ($winnings_data as $record) {
        if ($record['email'] === $email && $record['date'] === $today) {
            return (float)$record['total_winnings'];
        }
    }
    
    return 0.00;
}

/**
 * Add game winnings to user's daily total
 */
function addGameWinnings($email, $amount) {
    if ($amount <= 0) {
        return false;
    }
    
    $winnings_data = readJsonFile('daily_game_winnings') ?: [];
    $today = date('Y-m-d');
    $found = false;
    
    // Update existing record or create new one
    foreach ($winnings_data as $key => $record) {
        if ($record['email'] === $email && $record['date'] === $today) {
            $winnings_data[$key]['total_winnings'] += $amount;
            $winnings_data[$key]['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $winnings_data[] = [
            'email' => $email,
            'date' => $today,
            'total_winnings' => $amount,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    return writeJsonFile('daily_game_winnings', $winnings_data);
}

/**
 * Reset daily game winnings (called at 12am Nigeria time)
 */
function resetDailyGameWinnings() {
    $today = date('Y-m-d');
    $winnings_data = readJsonFile('daily_game_winnings') ?: [];
    
    // Remove all old records (not today)
    $winnings_data = array_filter($winnings_data, function($record) use ($today) {
        return $record['date'] === $today;
    });
    
    // Reset today's records to 0
    foreach ($winnings_data as $key => $record) {
        if ($record['date'] === $today) {
            $winnings_data[$key]['total_winnings'] = 0.00;
            $winnings_data[$key]['updated_at'] = date('Y-m-d H:i:s');
        }
    }
    
    return writeJsonFile('daily_game_winnings', array_values($winnings_data));
}

/**
 * Apply house edge to winning amount (15-20% house edge)
 */
function applyHouseEdge($winning_amount, $house_edge_percent = 15) {
    if ($winning_amount <= 0) {
        return 0;
    }
    
    // House edge reduces the payout
    $payout_percentage = 100 - $house_edge_percent;
    $final_payout = ($winning_amount * $payout_percentage) / 100;
    
    return round($final_payout, 2);
}

/**
 * Validate bet amount (₦50 - ₦100 range)
 */
function isValidBetAmount($amount) {
    return $amount >= 50.00 && $amount <= 100.00;
}

/**
 * Get remaining daily game winnings allowance
 */
function getRemainingDailyAllowance($email) {
    $daily_limit = 1000000.00; // ₦1,000,000 daily limit (1 Million)
    $today_winnings = getDailyGameWinnings($email);
    
    return max(0, $daily_limit - $today_winnings);
}

/**
 * Check if it's a new day and reset if needed
 */
function checkAndResetIfNewDay() {
    $today = date('Y-m-d');
    $last_reset = readJsonFile('last_game_reset') ?: '';
    
    if ($last_reset !== $today) {
        resetDailyGameWinnings();
        writeJsonFile('last_game_reset', $today);
    }
}

// Auto-check for daily reset when this file is included
checkAndResetIfNewDay();
?>