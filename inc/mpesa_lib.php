<?php
// usms/inc/mpesa_lib.php
// FIXED FOR LOCALHOST

function mpesa_config() {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/mpesa_config.php';
    }
    return $config;
}

function mpesa_base_url() {
    $c = mpesa_config();
    return ($c['environment'] === 'live') ? $c['live_url'] : $c['sandbox_url'];
}

function mpesa_get_access_token() {
    $c = mpesa_config();
    $url = mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($c['consumer_key'] . ':' . $c['consumer_secret'])],
        // ----------------------------------------------------
        // CRITICAL FIX FOR LOCALHOST:
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
        // ----------------------------------------------------
    ]);
    
    $resp = curl_exec($ch);
    
    if ($resp === false) {
        throw new Exception("cURL Connection Error: " . curl_error($ch));
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $j = json_decode($resp, true);
    
    if ($http_code != 200) {
        throw new Exception("Token Error ($http_code): " . ($j['errorMessage'] ?? $resp));
    }

    if (!isset($j['access_token'])) {
        throw new Exception("No access token received.");
    }
    return $j['access_token'];
}

function mpesa_generate_security_credential($plain_password) {
    // Ensure this path is correct on your machine
    $cert_path = __DIR__ . '/../cert/cert_sandbox.cer'; 

    if (!file_exists($cert_path)) {
        return false; 
    }

    $cert_content = file_get_contents($cert_path);
    $pubKey = openssl_pkey_get_public($cert_content);
    
    if (!$pubKey) {
        return false;
    }

    $encrypted = '';
    if (openssl_public_encrypt($plain_password, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING)) {
        return base64_encode($encrypted);
    }
    return false;
}

// STK PUSH (Deposits)
function mpesa_initiate_stk_push($phone, $amount) {
    $c = mpesa_config();
    $token = mpesa_get_access_token(); // This might throw exception if keys are wrong
    
    $timestamp = date("YmdHis");
    $password = base64_encode($c['shortcode'] . $c['passkey'] . $timestamp);
    
    // Ensure phone is 254...
    $formatted_phone = preg_replace('/^0/', '254', $phone);
    $formatted_phone = preg_replace('/^\+/', '', $formatted_phone);

    $payload = [
        "BusinessShortCode" => $c['shortcode'],
        "Password" => $password,
        "Timestamp" => $timestamp,
        "TransactionType" => "CustomerPayBillOnline",
        "Amount" => (int)$amount,
        "PartyA" => $formatted_phone,
        "PartyB" => $c['shortcode'],
        "PhoneNumber" => $formatted_phone,
        "CallBackURL" => $c['callback_url'],
        "AccountReference" => "USMS Payment",
        "TransactionDesc" => "Membership Payment"
    ];

    $ch = curl_init(mpesa_base_url() . "/mpesa/stkpush/v1/processrequest");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        // ----------------------------------------------------
        // CRITICAL FIX FOR LOCALHOST:
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
        // ----------------------------------------------------
    ]);

    $response = curl_exec($ch);
    
    if($response === false){
        return ['ResponseCode' => '99', 'errorMessage' => curl_error($ch)];
    }

    curl_close($ch);
    return json_decode($response, true);
}

// B2C (Withdrawals)
function mpesa_b2c_request($phone, $amount, $reference, $remarks = 'Withdrawal') {
    $c = mpesa_config();
    
    if (empty($c['b2c_security_credential'])) {
        return ['success' => false, 'message' => 'Missing Security Credential in Config'];
    }

    $security_credential = $c['b2c_security_credential'];

    try {
        $token = mpesa_get_access_token();
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Token Error: ' . $e->getMessage()];
    }

    $url = mpesa_base_url() . '/mpesa/b2c/v3/paymentrequest';
    $formatted_phone = preg_replace('/^0/', '254', $phone);

    $payload = [
        'InitiatorName' => $c['b2c_initiator_name'],
        'SecurityCredential' => $security_credential,
        'CommandID' => 'BusinessPayment',
        'Amount' => (int)$amount,
        'PartyA' => $c['b2c_shortcode'], 
        'PartyB' => $formatted_phone,
        'Remarks' => $remarks,
        'QueueTimeOutURL' => $c['b2c_timeout_url'],
        'ResultURL' => $c['b2c_result_url'],
        'Occasion' => $remarks
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        // ----------------------------------------------------
        // CRITICAL FIX FOR LOCALHOST:
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
        // ----------------------------------------------------
    ]);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($info['http_code'] >= 400) {
        return ['success' => false, 'message' => 'HTTP Error ' . $info['http_code'] . ': ' . $response];
    }

    $json = json_decode($response, true);
    
    if (isset($json['ResponseCode']) && $json['ResponseCode'] === '0') {
        return ['success' => true, 'data' => $json];
    } else {
        return ['success' => false, 'message' => $json['errorMessage'] ?? 'M-Pesa B2C Failed'];
    }
}
?>