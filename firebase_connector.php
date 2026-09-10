<?php
// firebase_connector.php - Lightweight Firebase connector for main site
class FirebaseConnector {
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
    
    // Get data from Firebase
    public function get($path) {
        $ch = curl_init($this->makeUrl($path));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response !== false) {
            $decoded = json_decode($response, true);
            // Ensure we always return an array, never null or string
            if (is_array($decoded)) {
                return $decoded;
            }
            if (is_null($decoded) || $decoded === 'null') {
                return [];
            }
            // If it's a string or other type, try to decode it
            if (is_string($decoded)) {
                $double_decoded = json_decode($decoded, true);
                return is_array($double_decoded) ? $double_decoded : [];
            }
        }
        return [];
    }
    
    // Save data to Firebase
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
}

// Global Firebase instance
$firebase = new FirebaseConnector();

// Replace your old file functions with these
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

// User functions
function getUsers() {
    global $firebase;
    return $firebase->get('users') ?: [];
}

function saveUsers($users) {
    global $firebase;
    return $firebase->put('users', $users);
}
?>