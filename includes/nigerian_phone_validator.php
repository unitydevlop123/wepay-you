<?php
class NigerianPhoneValidator {
    
    // Valid Nigerian network prefixes
    private static $validPrefixes = [
        // MTN Nigeria
        '0803', '0806', '0813', '0814', '0816', '0903', '0906', '0913', '0916',
        // Globacom
        '0805', '0807', '0811', '0815', '0905', '0915',
        // Airtel Nigeria  
        '0802', '0808', '0812', '0901', '0902', '0904', '0907', '0912',
        // 9mobile (Etisalat)
        '0809', '0817', '0818', '0908', '0909',
        // Ntel
        '0804',
        // Smile
        '0702',
        // Spectranet
        '0709'
    ];
    
    public static function isValidNigerianNumber($phone) {
        // Remove all non-numeric characters and spaces
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Handle international format (+234)
        if (strpos($phone, '+234') === 0) {
            $phone = '0' . substr($phone, 4);
        } elseif (strpos($phone, '234') === 0 && strlen($phone) == 13) {
            $phone = '0' . substr($phone, 3);
        }
        
        // Must be exactly 11 digits and start with 0
        if (strlen($phone) !== 11 || $phone[0] !== '0') {
            return false;
        }
        
        // Check if it's all the same digit (fake numbers like 01111111111)
        if (count(array_unique(str_split($phone))) === 1) {
            return false;
        }
        
        // Check against valid Nigerian network prefixes
        $prefix = substr($phone, 0, 4);
        if (!in_array($prefix, self::$validPrefixes)) {
            return false;
        }
        
        // Additional security checks
        // Check for sequential numbers (fake like 01234567890)
        $isSequential = true;
        for ($i = 1; $i < strlen($phone); $i++) {
            if ((int)$phone[$i] !== ((int)$phone[$i-1] + 1) % 10) {
                $isSequential = false;
                break;
            }
        }
        if ($isSequential) {
            return false;
        }
        
        // Check for patterns that look fake
        $suspicious_patterns = [
            '/^0(123456789|987654321)/', // Sequential
            '/^0(\d)\1{9}$/',            // Repeated digits like 02222222222
            '/^0000000000$/',            // All zeros
            '/^0111111111$/',            // Common fake pattern
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Format Nigerian phone number to standard format
     */
    public static function formatNigerianNumber($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Handle international format
        if (strpos($phone, '+234') === 0) {
            $phone = '0' . substr($phone, 4);
        } elseif (strpos($phone, '234') === 0 && strlen($phone) == 13) {
            $phone = '0' . substr($phone, 3);
        }
        
        if (self::isValidNigerianNumber($phone)) {
            return $phone;
        }
        
        return false;
    }
    
    /**
     * Get network provider from phone number
     */
    public static function getNetworkProvider($phone) {
        $phone = self::formatNigerianNumber($phone);
        if (!$phone) return false;
        
        $prefix = substr($phone, 0, 4);
        
        $networks = [
            'MTN' => ['0803', '0806', '0813', '0814', '0816', '0903', '0906', '0913', '0916'],
            'Globacom' => ['0805', '0807', '0811', '0815', '0905', '0915'],
            'Airtel' => ['0802', '0808', '0812', '0901', '0902', '0904', '0907', '0912'],
            '9mobile' => ['0809', '0817', '0818', '0908', '0909'],
            'Ntel' => ['0804'],
            'Smile' => ['0702'],
            'Spectranet' => ['0709']
        ];
        
        foreach ($networks as $network => $prefixes) {
            if (in_array($prefix, $prefixes)) {
                return $network;
            }
        }
        
        return 'Unknown';
    }
}
?>