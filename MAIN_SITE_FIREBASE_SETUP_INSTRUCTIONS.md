
# 🔥 MAIN SITE FIREBASE SETUP INSTRUCTIONS

## Boss's Complete Guide: Connect Your Main Site to Firebase

### 📋 WHAT YOU NEED TO DO

**Goal:** Make your main website use Firebase instead of local JSON files (storage/users.json, storage/bronze.json, etc.)

**Result:** Your main site and admin dashboard will share the same Firebase database in real-time.

---

## 🚀 STEP 1: UPLOAD THESE 2 FILES TO YOUR MAIN SITE

Upload these 2 files to your main website's root directory:

### File 1: `firebase_setup.php`
```php
<?php
// firebase_setup.php - Include this at the top of ALL your main site pages
require_once 'main_site_firebase.php';

// Initialize Firebase connection
date_default_timezone_set('Africa/Lagos');

// Helper function to check if file exists in Firebase (optional)
function firebaseFileExists($path) {
    global $firebase;
    $data = $firebase->get($path);
    return !empty($data);
}

// That's it! Now all your pages use Firebase automatically
?>
```

### File 2: `main_site_firebase.php`
```php
<?php
// main_site_firebase.php - Firebase connector for your main website
class MainSiteFirebase {
    private $baseUrl;
    private $apiKey;
    
    public function __construct() {
        $this->baseUrl = 'https://wepayyou-c100d-default-rtdb.firebaseio.com/';
        $this->apiKey = 'AIzaSyDaaPiXVGKKQrKq9no3t2QRGu3dk5W2G9k';
    }
    
    private function makeUrl($path) {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/') . '.json';
        if ($this->apiKey) $url .= '?auth=' . urlencode($this->apiKey);
        return $url;
    }
    
    // Get data from Firebase (replaces reading JSON files)
    public function get($path) {
        $ch = curl_init($this->makeUrl($path));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true) ?: [];
        }
        return [];
    }
    
    // Save data to Firebase (replaces writing JSON files)
    public function put($path, $data) {
        $ch = curl_init($this->makeUrl($path));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    // Get user data (replaces reading storage/users/email/file.json)
    public function getUserData($email, $type = 'notifications') {
        $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $email);
        return $this->get("users_data/$firebase_key/$type");
    }
    
    // Save user data (replaces writing storage/users/email/file.json)  
    public function saveUserData($email, $type, $data) {
        $firebase_key = str_replace(['@', '.'], ['_at_', '_dot_'], $email);
        return $this->put("users_data/$firebase_key/$type", $data);
    }
}

// Global Firebase instance
$firebase = new MainSiteFirebase();

// Replace your old functions with these Firebase-powered ones
function readJsonFile($filename) {
    global $firebase;
    $path = str_replace('.json', '', $filename);
    return $firebase->get($path);
}

function writeJsonFile($filename, $data) {
    global $firebase;
    $path = str_replace('.json', '', $filename);
    return $firebase->put($path, $data);
}

// User-specific data functions
function readUserFile($email, $filename) {
    global $firebase;
    $type = str_replace('.json', '', $filename);
    return $firebase->getUserData($email, $type);
}

function writeUserFile($email, $filename, $data) {
    global $firebase;
    $type = str_replace('.json', '', $filename);
    return $firebase->saveUserData($email, $type, $data);
}

// Main functions your site uses
function getUsers() {
    global $firebase;
    return $firebase->get('users') ?: [];
}

function saveUsers($users) {
    global $firebase;
    return $firebase->put('users', $users);
}

function getUserTransactions($email) {
    global $firebase;
    return $firebase->getUserData($email, 'transactions');
}

function saveUserTransactions($email, $transactions) {
    global $firebase;
    return $firebase->saveUserData($email, 'transactions', $transactions);
}

function getUserNotifications($email) {
    global $firebase;
    return $firebase->getUserData($email, 'notifications');
}

function saveUserNotifications($email, $notifications) {
    global $firebase;
    return $firebase->saveUserData($email, 'notifications', $notifications);
}
?>
```

---

## 🔧 STEP 2: UPDATE ALL YOUR MAIN SITE PHP FILES

Add this ONE LINE at the top of EVERY PHP file on your main site:

```php
<?php require_once 'firebase_setup.php'; ?>
```

**Example - Your login.php file:**
```php
<?php require_once 'firebase_setup.php'; ?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<?php
// This code automatically uses Firebase now!
$users = getUsers();
$notifications = getUserNotifications($email);
?>
</body>
</html>
```

---

## 🔄 STEP 3: REPLACE OLD JSON CODE WITH NEW FIREBASE CODE

### ❌ REMOVE THESE OLD LINES:
```php
// OLD - Delete these lines from your main site files
$users = json_decode(file_get_contents('storage/users.json'), true);
$notifications = json_decode(file_get_contents("storage/users/$email/notifications.json"), true);
$transactions = json_decode(file_get_contents("storage/users/$email/transactions.json"), true);
file_put_contents('storage/users.json', json_encode($users));
file_put_contents("storage/users/$email/notifications.json", json_encode($notifications));
```

### ✅ REPLACE WITH THESE NEW LINES:
```php
// NEW - Use these instead
$users = getUsers();
$notifications = getUserNotifications($email);
$transactions = getUserTransactions($email);
saveUsers($users);
saveUserNotifications($email, $notifications);
saveUserTransactions($email, $transactions);
```

---

## 📂 STEP 4: SPECIFIC FILE REPLACEMENTS

### For files that read storage/filename.json:
```php
// OLD
$data = json_decode(file_get_contents('storage/bronze.json'), true);

// NEW
$data = readJsonFile('bronze.json');
```

### For files that save storage/filename.json:
```php
// OLD
file_put_contents('storage/bronze.json', json_encode($data));

// NEW
writeJsonFile('bronze.json', $data);
```

### For user-specific files:
```php
// OLD
$user_data = json_decode(file_get_contents("storage/users/$email/notifications.json"), true);
file_put_contents("storage/users/$email/notifications.json", json_encode($data));

// NEW
$user_data = getUserNotifications($email);
saveUserNotifications($email, $data);
```

---

## 🎯 STEP 5: WHAT FILES TO UPDATE

Update these types of files on your main site:

1. **Login/Registration pages** - Add `<?php require_once 'firebase_setup.php'; ?>`
2. **Dashboard pages** - Add `<?php require_once 'firebase_setup.php'; ?>`
3. **Game pages** - Add `<?php require_once 'firebase_setup.php'; ?>`
4. **Any page that reads/writes JSON files** - Add `<?php require_once 'firebase_setup.php'; ?>`

---

## ✅ FINAL RESULT

After following these steps:

✅ **Your main site** → Connects to Firebase  
✅ **Your admin backend** → Connects to same Firebase  
✅ **Real-time sync** → Both see same data instantly  
✅ **No more local JSON files** → Everything in cloud  
✅ **Same database** → Admin changes reflect on main site immediately  

---

## 🔍 TESTING

1. Upload the 2 files to your main site
2. Add `<?php require_once 'firebase_setup.php'; ?>` to one test page
3. Replace one `json_decode(file_get_contents())` with `readJsonFile()`
4. Check if data loads correctly
5. If it works, update all other pages

---

## ❗ IMPORTANT NOTES

- **Keep your admin dashboard unchanged** - It already uses Firebase
- **Only modify your main site files** - Not the admin backend
- **Test one page first** - Before updating all pages
- **Backup your main site** - Before making changes

---

## 🆘 IF SOMETHING GOES WRONG

1. **Check file paths** - Make sure the 2 Firebase files are in the right location
2. **Check permissions** - Make sure PHP can read the files
3. **Check internet** - Firebase needs internet connection
4. **Check syntax** - Make sure you added the require line correctly

Boss, this is your complete guide! Follow these steps and your main site will be connected to Firebase just like your admin dashboard.
