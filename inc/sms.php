<?php
// usms/inc/sms.php
// PRODUCTION READY SMS CONFIGURATION

function send_sms($phone, $message) {
    // ============================================================
    // 1. CONFIGURATION
    // ============================================================
    
    // Your Live Credentials
    $username = 'USMS'; 
    $apiKey   = 'atsk_aac0d19755a64e3664f9bcb4653fa983e3e94fc90acdff7bca92c1b859e4f4c6aede328c'; 
    
    // Environment: 'sandbox' or 'live'
    $env = 'live'; 

    // Sender ID (Alphanumeric)
    // Leave NULL if using the default gateway shortcode (e.g., 20414)
    // Only set this to 'UMOJA' if you have received approval email from Africa's Talking.
    $senderId = 'USMS'; // e.g., 'UMOJA' or null

    // ============================================================
    // 2. LOGIC
    // ============================================================

    // Determine API URL
    $url = ($env === 'sandbox') 
        ? 'https://api.sandbox.africastalking.com/version1/messaging' 
        : 'https://api.africastalking.com/version1/messaging';

    // Robust Phone Formatting
    // Remove any non-numeric characters (spaces, +, -)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle standard Kenyan formats
    if (substr($phone, 0, 1) === '0') {
        // Converts 0722... or 0110... to 254722...
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) !== '254' && strlen($phone) == 9) {
        // Handle cases like 722123456 -> 254722...
        $phone = '254' . $phone;
    }

    // Prepare Payload
    $data = [
        'username' => $username,
        'to'       => $phone,
        'message'  => $message
    ];

    if (!empty($senderId)) {
        $data['from'] = $senderId;
    }

    // Initialize cURL
    $ch = curl_init($url);
    
    // Request Headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    
    // Request Options
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // ============================================================
    // 3. SSL SECURITY HANDLING (Auto-Detect)
    // ============================================================
    
    // Check if running on Localhost (XAMPP/WAMP) or Live Server
    $whitelist = array('127.0.0.1', '::1', 'localhost');
    $is_localhost = in_array($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $whitelist);

    if ($is_localhost) {
        // Disable SSL check for Localhost development
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    } else {
        // ENABLE SSL for Live Server (Security Best Practice)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }

    // Execute
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);

    // ============================================================
    // 4. ERROR HANDLING
    // ============================================================

    // Handle cURL connection errors
    if ($response === false) {
        $err_msg = json_encode(['status' => 'error', 'message' => 'Connection Failed: ' . $curl_error]);
        error_log("SMS Critical Error: " . $err_msg);
        return $err_msg;
    }

    // Handle API errors (HTTP 4xx or 5xx)
    if ($http_code != 201) {
        error_log("SMS API Error ($http_code): " . $response);
    }

    return $response;
}
?>