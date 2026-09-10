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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
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