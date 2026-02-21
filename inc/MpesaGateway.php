<?php
declare(strict_types=1);
require_once __DIR__ . '/PaymentGatewayInterface.php';

class MpesaGateway implements PaymentGatewayInterface {
    private $config;
    private $env;

    public function __construct($config, $env = 'sandbox') {
        $this->config = $config;
        $this->env = $env;
    }

    public function getEnvironment() {
        return $this->env;
    }

    public function getProviderName() {
        return 'M-Pesa';
    }

    private function getAccessToken() {
        $url = $this->config['base_url'] . '/oauth/v1/generate?grant_type=client_credentials';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($this->config['consumer_key'] . ':' . $this->config['consumer_secret'])],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        
        $resp = curl_exec($ch);
        if ($resp === false) throw new Exception("Mpesa Auth Error: " . curl_error($ch));
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $j = json_decode($resp, true);
        if ($http_code != 200) throw new Exception("Mpesa Token Error ($http_code): " . ($j['errorMessage'] ?? $resp));
        
        return $j['access_token'];
    }

    public function initiateDeposit($phone, $amount, $reference, $account_ref = 'USMS Payment') {
        $token = $this->getAccessToken();
        $timestamp = date("YmdHis");
        $password = base64_encode($this->config['shortcode'] . $this->config['passkey'] . $timestamp);
        
        $formatted_phone = preg_replace('/^0/', '254', preg_replace('/^\+/', '', $phone));

        $payload = [
            "BusinessShortCode" => $this->config['shortcode'],
            "Password" => $password,
            "Timestamp" => $timestamp,
            "TransactionType" => "CustomerPayBillOnline",
            "Amount" => (int)$amount,
            "PartyA" => $formatted_phone,
            "PartyB" => $this->config['shortcode'],
            "PhoneNumber" => $formatted_phone,
            "CallBackURL" => $this->config['callback_url'],
            "AccountReference" => substr($reference, 0, 12),
            "TransactionDesc" => $account_ref
        ];

        $ch = curl_init($this->config['base_url'] . "/mpesa/stkpush/v1/processrequest");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) return ['success' => false, 'message' => $error];
        $json = json_decode($response, true);

        if (isset($json['ResponseCode']) && $json['ResponseCode'] === '0') {
            return [
                'success' => true,
                'message' => 'STK Push Initiated',
                'data' => $json,
                'checkout_id' => $json['CheckoutRequestID']
            ];
        }

        return ['success' => false, 'message' => $json['errorMessage'] ?? 'STK Push Failed', 'data' => $json];
    }

    public function initiateWithdrawal($phone, $amount, $reference, $remarks = 'Withdrawal') {
        if (empty($this->config['b2c_security_credential'])) {
            return ['success' => false, 'message' => 'Missing B2C Security Credential'];
        }

        try {
            $token = $this->getAccessToken();
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $formatted_phone = preg_replace('/^\+/', '', $phone);
        $formatted_phone = preg_replace('/^0/', '254', $formatted_phone);
        if (strlen($formatted_phone) === 9) $formatted_phone = '254' . $formatted_phone;

        $payload = [
            'InitiatorName' => $this->config['b2c_initiator_name'] ?? 'testapi',
            'SecurityCredential' => $this->config['b2c_security_credential'],
            'CommandID' => 'BusinessPayment',
            'Amount' => (int)$amount,
            'PartyA' => $this->config['b2c_shortcode'], 
            'PartyB' => $formatted_phone,
            'Remarks' => $remarks,
            'QueueTimeOutURL' => $this->config['b2c_timeout_url'] ?? '',
            'ResultURL' => $this->config['b2c_result_url'] ?? '',
            'Occasion' => $remarks,
            'OriginatorConversationID' => $reference
        ];

        $ch = curl_init($this->config['base_url'] . '/mpesa/b2c/v1/paymentrequest');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        if (isset($json['ResultCode']) && $json['ResultCode'] === '0') {
            return ['success' => true, 'message' => 'Withdrawal processing', 'data' => $json, 'reference' => $json['ConversationID'] ?? ''];
        } elseif (isset($json['ResponseCode']) && $json['ResponseCode'] === '0') {
             return ['success' => true, 'message' => 'Withdrawal initiated', 'data' => $json, 'reference' => $json['ConversationID'] ?? ''];
        }

        return ['success' => false, 'message' => $json['errorMessage'] ?? $json['ResponseDescription'] ?? 'M-Pesa B2C Failed', 'data' => $json];
    }
}
