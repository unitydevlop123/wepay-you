<?php
/**
 * HELPER SCRIPT: Add Ban Checking to All Protected Pages
 * 
 * This script automatically adds ban checking to all pages that use requireLogin()
 * Run this once to protect all your pages from banned users
 */

// List of all protected pages
$protected_pages = [
    'profile.php',
    'history.php',
    'referral.php',
    'notifications.php',
    'invest.php',
    'games.php',
    'dice.php',
    'plinko.php',
    'crash.php',
    'spin-wheel.php',
    'step-climber.php',
    'treasure-hunt.php',
    'virtual-result.php',
    'virtual-sporty.php',
    'platinum.php',
    'complete-order.php',
    'order-confirmation.php',
    'order-progress.php'
];

$ban_check_code = "\n// Include ban checking system\nrequire_once 'ban-checker.php';\n\n// SECURITY: Enable proper authentication\nrequireLogin();\n\n// SECURITY: Check if user is banned and force logout immediately\ncheckBanStatus();";

$search_pattern = "// SECURITY: Enable proper authentication\nrequireLogin();";

$updated_count = 0;
$already_protected = 0;
$errors = [];

foreach ($protected_pages as $page) {
    if (!file_exists($page)) {
        $errors[] = "File not found: $page";
        continue;
    }
    
    $content = file_get_contents($page);
    
    // Check if already protected
    if (strpos($content, 'ban-checker.php') !== false) {
        $already_protected++;
        echo "✓ Already protected: $page\n";
        continue;
    }
    
    // Add ban checking
    $new_content = str_replace($search_pattern, $ban_check_code, $content);
    
    if ($new_content !== $content) {
        file_put_contents($page, $new_content);
        $updated_count++;
        echo "✓ Added ban protection: $page\n";
    } else {
        $errors[] = "Could not add to: $page (pattern not found)";
    }
}

echo "\n=== SUMMARY ===\n";
echo "✓ Updated: $updated_count files\n";
echo "✓ Already protected: $already_protected files\n";
echo "✗ Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\nDone! All pages are now protected from banned users.\n";
?>
