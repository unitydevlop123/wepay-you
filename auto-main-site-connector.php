<?php
/**
 * Auto Main Site Connector
 * Automatic VTU API integration for your main site dashboard
 * Copy this file to your main site and include it in all service pages
 */

class VTUAutoConnector {
    private $backend_url;
    private $timeout;
    
    public function __construct($backend_url = null, $timeout = 30) {
        // Auto-detect backend URL if not provided
        $this->backend_url = $backend_url ?: $this->detectBackendURL();
        $this->timeout = $timeout;
    }
    
    /**
     * Auto-detect VTU backend URL
     */
    private function detectBackendURL() {
        // Try common backend URLs
        $possible_urls = [
            'http://localhost:5000/vtu-api.php',
            'https://your-backend-domain.com/vtu-api.php',
            '/vtu-backend/vtu-api.php' // Same domain
        ];
        
        foreach ($possible_urls as $url) {
            if ($this->testConnection($url)) {
                return $url;
            }
        }
        
        // Fallback - assume same domain
        return '/vtu-api.php';
    }
    
    /**
     * Test connection to backend
     */
    private function testConnection($url) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode(['service' => 'test', 'action' => 'ping']),
                'timeout' => 5
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        return $response !== false;
    }
    
    /**
     * Make API call to VTU backend
     */
    private function callAPI($service, $action, $data = []) {
        $postData = array_merge($data, [
            'service' => $service,
            'action' => $action
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($postData),
                'timeout' => $this->timeout
            ]
        ]);
        
        $response = @file_get_contents($this->backend_url, false, $context);
        
        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Unable to connect to VTU service. Please try again later.',
                'error_code' => 'CONNECTION_FAILED'
            ];
        }
        
        $result = json_decode($response, true);
        return $result ?: [
            'success' => false,
            'message' => 'Invalid response from VTU service',
            'error_code' => 'INVALID_RESPONSE'
        ];
    }
    
    /**
     * Buy Airtime - Auto API Integration
     */
    public function buyAirtime($phone, $network, $amount, $userId = null, $portedNumber = true) {
        return $this->callAPI('airtime', 'purchase', [
            'phone' => $phone,
            'network' => strtolower($network),
            'amount' => floatval($amount),
            'user_id' => $userId,
            'ported_number' => (bool)$portedNumber
        ]);
    }
    
    /**
     * Buy Data - Auto API Integration
     */
    public function buyData($phone, $network, $plan, $amount = null, $userId = null, $portedNumber = true) {
        return $this->callAPI('data', 'purchase', [
            'phone' => $phone,
            'network' => strtolower($network),
            'plan' => $plan,
            'amount' => $amount ? floatval($amount) : null,
            'user_id' => $userId,
            'ported_number' => (bool)$portedNumber
        ]);
    }
    
    /**
     * Buy Cable TV - Auto API Integration
     */
    public function buyCableTV($smartcard, $provider, $package, $amount, $userId = null) {
        return $this->callAPI('cable', 'purchase', [
            'smartcard' => $smartcard,
            'provider' => strtoupper($provider),
            'package' => $package,
            'amount' => floatval($amount),
            'user_id' => $userId
        ]);
    }
    
    /**
     * Pay Electricity Bill - Auto API Integration
     */
    public function payElectricity($meterNumber, $disco, $amount, $userId = null, $customerName = null) {
        return $this->callAPI('electricity', 'purchase', [
            'meter_number' => $meterNumber,
            'disco' => $disco,
            'amount' => floatval($amount),
            'user_id' => $userId,
            'customer_name' => $customerName
        ]);
    }
    
    /**
     * Verify Cable TV Smartcard
     */
    public function verifyCable($smartcard, $provider) {
        return $this->callAPI('cable', 'verify', [
            'smartcard' => $smartcard,
            'provider' => strtoupper($provider)
        ]);
    }
    
    /**
     * Verify Electricity Meter
     */
    public function verifyMeter($meterNumber, $disco, $meterType = 'prepaid') {
        return $this->callAPI('electricity', 'verify', [
            'meter_number' => $meterNumber,
            'disco' => $disco,
            'meter_type' => $meterType
        ]);
    }
    
    /**
     * Get VTU Service Status
     */
    public function getServiceStatus() {
        return $this->callAPI('system', 'status');
    }
    
    /**
     * Get Available Data Plans for Network
     */
    public function getDataPlans($network) {
        return $this->callAPI('data', 'plans', [
            'network' => strtolower($network)
        ]);
    }
    
    /**
     * Check Transaction Status
     */
    public function checkTransaction($reference) {
        return $this->callAPI('transaction', 'status', [
            'reference' => $reference
        ]);
    }
}

// Global VTU connector instance
$VTU = new VTUAutoConnector();

/**
 * Helper Functions for Easy Integration
 */

// Quick Airtime Purchase
function buyAirtime($phone, $network, $amount, $userId = null) {
    global $VTU;
    return $VTU->buyAirtime($phone, $network, $amount, $userId);
}

// Quick Data Purchase  
function buyData($phone, $network, $plan, $amount = null, $userId = null) {
    global $VTU;
    return $VTU->buyData($phone, $network, $plan, $amount, $userId);
}

// Quick Cable TV Purchase
function buyCableTV($smartcard, $provider, $package, $amount, $userId = null) {
    global $VTU;
    return $VTU->buyCableTV($smartcard, $provider, $package, $amount, $userId);
}

// Quick Electricity Bill Payment
function payElectricity($meterNumber, $disco, $amount, $userId = null) {
    global $VTU;
    return $VTU->payElectricity($meterNumber, $disco, $amount, $userId);
}

// Quick Service Status Check
function checkVTUStatus() {
    global $VTU;
    return $VTU->getServiceStatus();
}

/**
 * Example Usage in Your Main Site Dashboard Pages:
 * 
 * // In your airtime.php page:
 * require_once 'auto-main-site-connector.php';
 * 
 * if ($_POST['buy_airtime']) {
 *     $result = buyAirtime($_POST['phone'], $_POST['network'], $_POST['amount'], $_SESSION['user_id']);
 *     
 *     if ($result['success']) {
 *         echo "Success! Reference: " . $result['reference'];
 *     } else {
 *         echo "Error: " . $result['message'];
 *     }
 * }
 * 
 * // In your data.php page:
 * require_once 'auto-main-site-connector.php';
 * 
 * if ($_POST['buy_data']) {
 *     $result = buyData($_POST['phone'], $_POST['network'], $_POST['plan'], null, $_SESSION['user_id']);
 *     
 *     if ($result['success']) {
 *         echo "Data purchased! Reference: " . $result['reference'];
 *     } else {
 *         echo "Failed: " . $result['message'];
 *     }
 * }
 */
?>