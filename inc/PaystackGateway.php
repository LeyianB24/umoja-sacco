<?php
require_once __DIR__ . '/PaymentGatewayInterface.php';

class PaystackGateway implements PaymentGatewayInterface {
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
        return 'Paystack';
    }

    private function getSecretKey() {
        return $this->config['secret_key'];
    }

    public function initiateDeposit($phone, $amount, $reference, $account_ref = 'USMS Payment') {
        // Paystack Deposit (C2B) usually involves a redirect or popup.
        // For now, we return a failure or a stub if not yet implemented.
        return ['success' => false, 'message' => 'Paystack Deposit not yet implemented in Gateway'];
    }

    public function initiateWithdrawal($phone, $amount, $reference, $remarks = 'Withdrawal') {
        $apiKey = $this->getSecretKey();
        
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($clean_phone, 0, 1) == '0') $final_phone = $clean_phone;
        elseif (substr($clean_phone, 0, 3) == '254') $final_phone = '0' . substr($clean_phone, 3);
        elseif (strlen($clean_phone) == 9) $final_phone = '0' . $clean_phone;
        else $final_phone = $clean_phone; // Fallback

        // Step 1: Create Recipient
        $url = "https://api.paystack.co/transferrecipient";
        $fields = [
            'type' => 'mobile_money',
            'name' => 'Member Withdrawal',
            'account_number' => $final_phone,
            'bank_code' => 'MPESA',
            'currency' => 'KES'
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => ["Authorization: Bearer " . $apiKey, "Content-Type: application/json"]
        ]);
        
        $result = curl_exec($ch);
        $recipient_data = json_decode($result, true);
        
        if (!$recipient_data['status']) {
            curl_close($ch);
            return ['success' => false, 'message' => 'Recipient creation failed: ' . ($recipient_data['message'] ?? 'Unknown error')];
        }
        
        $recipientCode = $recipient_data['data']['recipient_code'];
        
        // Step 2: Initiate Transfer
        $urlTransfer = "https://api.paystack.co/transfer";
        $transferFields = [
            'source' => 'balance',
            'amount' => $amount * 100,
            'recipient' => $recipientCode,
            'reason' => $remarks,
            'reference' => $reference
        ];
        
        curl_setopt($ch, CURLOPT_URL, $urlTransfer);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transferFields));
        $resultTransfer = curl_exec($ch);
        curl_close($ch);
        
        $json = json_decode($resultTransfer, true);
        if ($json['status'] === true) {
            return [
                'success' => true,
                'message' => 'Paystack Transfer Initiated',
                'data' => $json['data'],
                'reference' => $json['data']['reference'] ?? ''
            ];
        }

        return ['success' => false, 'message' => 'Paystack Transfer Failed: ' . ($json['message'] ?? 'Unknown error'), 'data' => $json];
    }
}
