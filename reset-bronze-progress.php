
<?php require_once 'firebase_setup.php'; ?>
<?php
// Reset Bronze package progress for debugging
$debug_user_email = 'unityodigie440@gmail.com';

$users = getUsers();
if (!empty($users)) {
    foreach ($users as $key => $user) {
        if ($user['email'] === $debug_user_email) {
            // Reset Bronze package specifically
            if (isset($users[$key]['active_packages'])) {
                foreach ($users[$key]['active_packages'] as $pkg_index => $package) {
                    if (strtolower($package['name']) === 'bronze') {
                        $users[$key]['active_packages'][$pkg_index]['orders_completed_today'] = 0;
                        $users[$key]['active_packages'][$pkg_index]['commission_today'] = 0;
                        echo "Bronze package progress reset successfully!<br>";
                        break;
                    }
                }
            }
            
            // Save changes
            saveUsers($users);
            echo "User data saved successfully!<br>";
            echo "<a href='orders.php?selected_package=Bronze&t=" . time() . "'>Go to Bronze Package</a>";
            break;
        }
    }
}
?>
