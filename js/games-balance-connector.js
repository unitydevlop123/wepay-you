/**
 * GAMES BALANCE CONNECTOR - Universal Balance System
 * Connects all games to backend/games-balance-handler.php
 * 
 * Usage in games:
 * 1. Set global variables in HTML: window.userBalance and window.userEmail
 * 2. Include this file: <script src="js/games-balance-connector.js"></script>
 * 3. Call deductBalance() before starting game
 * 4. Call addWinnings() when player wins
 * 5. Call updateBalanceDisplay() to refresh UI
 */

// Global balance state - will be set by individual game pages
let currentUserBalance = window.userBalance || 0;
let userEmail = window.userEmail || 'testing@example.com';

/**
 * DEDUCT BALANCE - Called when player places bet
 */
async function deductBalance(amount, gameName) {
    try {
        const response = await fetch('/backend/games-balance-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=deduct_balance&user_email=${encodeURIComponent(userEmail)}&amount=${amount}&game_name=${encodeURIComponent(gameName)}`
        });

        const result = await response.json();

        if (result.success) {
            // Update local balance
            currentUserBalance = result.new_balance;
            updateBalanceDisplay();

            // Show success message
            console.log(`✅ ${result.message}`);
            return { success: true, newBalance: result.new_balance };
        } else {
            // Handle failure
            console.error(`❌ Balance deduction failed: ${result.message}`);
            alert(`Error: ${result.message}`);
            return { success: false, message: result.message };
        }
    } catch (error) {
        console.error('❌ Network error during balance deduction:', error);
        alert('Network error. Please check your connection and try again.');
        return { success: false, message: 'Network error' };
    }
}

/**
 * ADD WINNINGS - Called when player wins game
 */
async function addWinnings(amount, gameName) {
    try {
        const response = await fetch('/backend/games-balance-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_winnings&user_email=${encodeURIComponent(userEmail)}&amount=${amount}&game_name=${encodeURIComponent(gameName)}`
        });

        const result = await response.json();

        if (result.success) {
            // Update local balance
            currentUserBalance = result.new_balance;
            updateBalanceDisplay();

            // Show success message
            console.log(`🎉 ${result.message}`);
            return { success: true, newBalance: result.new_balance, winnings: result.winnings };
        } else {
            // Handle failure
            console.error(`❌ Adding winnings failed: ${result.message}`);
            return { success: false, message: result.message };
        }
    } catch (error) {
        console.error('❌ Network error during winnings addition:', error);
        return { success: false, message: 'Network error' };
    }
}

/**
 * GET CURRENT BALANCE - Refreshes balance from server
 */
async function getCurrentBalance() {
    try {
        const response = await fetch('/backend/games-balance-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_balance&user_email=${encodeURIComponent(userEmail)}`
        });

        const result = await response.json();

        if (result.success) {
            currentUserBalance = result.balance;
            updateBalanceDisplay();
            return result.balance;
        } else {
            console.error('❌ Failed to get balance:', result.message);
            return currentUserBalance; // Return cached balance
        }
    } catch (error) {
        console.error('❌ Network error getting balance:', error);
        return currentUserBalance; // Return cached balance
    }
}

/**
 * UPDATE BALANCE DISPLAY - Updates all balance elements on page
 */
function updateBalanceDisplay() {
    const balanceElements = document.querySelectorAll('[data-balance], .user-balance, #user-balance, .balance-amount');
    const formattedBalance = '₦' + Number(currentUserBalance).toLocaleString('en-NG', {minimumFractionDigits: 2});

    balanceElements.forEach(element => {
        if (element.tagName === 'INPUT') {
            element.value = formattedBalance;
        } else {
            element.textContent = formattedBalance;
        }
    });

    // Also update any balance displays in the header
    const headerBalance = document.querySelector('.user-balance-amount');
    if (headerBalance) {
        headerBalance.textContent = formattedBalance;
    }
}

/**
 * CHECK BALANCE - Returns true if user has enough balance for bet
 */
function checkBalance(betAmount) {
    return currentUserBalance >= betAmount;
}

/**
 * FORMAT AMOUNT - Formats amount with Nigerian Naira symbol
 */
function formatAmount(amount) {
    return '₦' + Number(amount).toLocaleString('en-NG', {minimumFractionDigits: 2});
}

/**
 * PLACE BET WITH BALANCE CHECK - Universal betting function
 */
async function placeBetWithBalance(betAmount, gameName, onSuccess, onFailure) {
    // Validate bet amount
    if (betAmount <= 0) {
        alert('Bet amount must be greater than zero');
        if (onFailure) onFailure('Invalid bet amount');
        return false;
    }

    // Check if user has enough balance
    if (!checkBalance(betAmount)) {
        alert('Insufficient balance. Please deposit funds to continue.');
        if (onFailure) onFailure('Insufficient balance');
        return false;
    }

    // Deduct balance
    const result = await deductBalance(betAmount, gameName);

    if (result.success) {
        console.log(`✅ Bet placed: ₦${betAmount} for ${gameName}`);
        if (onSuccess) onSuccess(result);
        return true;
    } else {
        console.log(`❌ Bet failed: ${result.message}`);
        if (onFailure) onFailure(result.message);
        return false;
    }
}

/**
 * CASH OUT WITH WINNINGS - Universal cash out function
 */
async function cashOutWithWinnings(winAmount, gameName, onSuccess, onFailure) {
    // Validate win amount
    if (winAmount <= 0) {
        console.log('No winnings to add');
        if (onFailure) onFailure('No winnings');
        return false;
    }

    // Add winnings
    const result = await addWinnings(winAmount, gameName);

    if (result.success) {
        console.log(`🎉 Winnings added: ₦${winAmount} from ${gameName}`);
        if (onSuccess) onSuccess(result);
        return true;
    } else {
        console.log(`❌ Adding winnings failed: ${result.message}`);
        if (onFailure) onFailure(result.message);
        return false;
    }
}

// Initialize balance display when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateBalanceDisplay();
    console.log('🎮 Games Balance Connector loaded successfully!');
    console.log(`💰 Current Balance: ${formatAmount(currentUserBalance)}`);
});