<?php
// inc/paystack_lib.php
// Paystack Payment Library for Withdrawals - SIMPLIFIED TO MATCH WORKING CODE

function paystack_config() {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/paystack_config.php';
    }
    return $config;
}

function paystack_get_secret_key() {
    $config = paystack_config();
    return ($config['environment'] === 'live') 
        ? $config['live_secret_key'] 
        : $config['secret_key'];
}

/**
 * Simplified withdrawal function matching withdrawal.php exactly
 */
function initiate_paystack_withdrawal($phone, $amount, $name, $reason = 'Wallet Withdrawal') {
    $paystackSecretKey = paystack_get_secret_key();
    
    // EXACT phone formatting from withdrawal.php
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($clean_phone, 0, 1) == '0') {
        $final_phone = '254' . substr($clean_phone, 1);
    } elseif (substr($clean_phone, 0, 3) == '254') {
        $final_phone = $clean_phone;
    } elseif (strlen($clean_phone) == 9) {
        $final_phone = '254' . $clean_phone;
    } else {
        return ['success' => false, 'error' => 'Invalid phone format'];
    }
    
    // Validate
    if (strlen($final_phone) != 12) {
        return ['success' => false, 'error' => 'Phone must be 12 digits'];
    }
    
    $bank_code = 'MPS'; // Paystack Kenya M-Pesa code
    
    // DEBUG: Log what we're sending to Paystack
    error_log("PAYSTACK DEBUG - Phone: $phone");
    error_log("PAYSTACK DEBUG - Clean: $clean_phone");
    error_log("PAYSTACK DEBUG - Final: $final_phone");
    error_log("PAYSTACK DEBUG - Bank: $bank_code");
    error_log("PAYSTACK DEBUG - Name: $name");
    
    // STEP 1: CREATE RECIPIENT (exact match to withdrawal.php)
    $url = "https://api.paystack.co/transferrecipient";
    $fields = [
        'type' => 'mobile_money',
        'name' => $name,
        'account_number' => $final_phone,
        'bank_code' => $bank_code,
        'currency' => 'KES'
    ];
    
    // DEBUG: Log the request
    error_log("PAYSTACK DEBUG - Request: " . json_encode($fields));
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $paystackSecretKey,
        "Content-Type: application/json"
    ]);
    
    $result = curl_exec($ch);
    $recipient_data = json_decode($result, true);
    
    // DEBUG: Log the response
    error_log("PAYSTACK DEBUG - Response: " . $result);
    
    if (!$recipient_data['status']) {
        curl_close($ch);
        return [
            'success' => false,
            'error' => 'Recipient creation failed: ' . ($recipient_data['message'] ?? 'Unknown error')
        ];
    }
    
    $recipientCode = $recipient_data['data']['recipient_code'];
    
    // STEP 2: INITIATE TRANSFER (exact match to withdrawal.php)
    $urlTransfer = "https://api.paystack.co/transfer";
    $transferFields = [
        'source' => 'balance',
        'amount' => $amount * 100, // Convert to kobo
        'recipient' => $recipientCode,
        'reason' => $reason
    ];
    
    curl_setopt($ch, CURLOPT_URL, $urlTransfer);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transferFields));
    $resultTransfer = curl_exec($ch);
    curl_close($ch);
    
    $transResponse = json_decode($resultTransfer, true);
    
    if ($transResponse['status'] === true) {
        return [
            'success' => true,
            'data' => $transResponse['data'],
            'reference' => $transResponse['data']['reference'] ?? ''
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Transfer failed: ' . ($transResponse['message'] ?? 'Unknown error')
        ];
    }
}
?>
