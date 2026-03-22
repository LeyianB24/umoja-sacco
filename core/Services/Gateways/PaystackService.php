<?php
declare(strict_types=1);

namespace USMS\Services\Gateways;

/**
 * USMS\Services\Gateways\PaystackService
 * Paystack Gateway Implementation
 */
class PaystackService implements PaymentGatewayInterface {
    private array $config;
    private string $env;

    public function __construct(array $config, string $env = 'sandbox') {
        $this->config = $config;
        $this->env = $env;
    }

    public function getEnvironment(): string {
        return $this->env;
    }

    public function getProviderName(): string {
        return 'Paystack';
    }

    private function getSecretKey(): string {
        return (string)$this->config['secret_key'];
    }

    public function initiateDeposit(string $phone, float $amount, string $reference, string $account_ref = 'USMS Payment'): array {
        return ['success' => false, 'message' => 'Paystack Deposit not yet implemented in Gateway'];
    }

    public function initiateWithdrawal(string $phone, float $amount, string $reference, string $remarks = 'Withdrawal'): array {
        $apiKey = $this->getSecretKey();
        
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($clean_phone, 0, 1) == '0') $final_phone = $clean_phone;
        elseif (substr($clean_phone, 0, 3) == '254') $final_phone = '0' . substr($clean_phone, 3);
        elseif (strlen($clean_phone) == 9) $final_phone = '0' . $clean_phone;
        else $final_phone = $clean_phone;

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
        $recipient_data = json_decode((string)$result, true);
        
        if (!($recipient_data['status'] ?? false)) {
            curl_close($ch);
            return ['success' => false, 'message' => 'Recipient creation failed: ' . ($recipient_data['message'] ?? 'Unknown error')];
        }
        
        $recipientCode = $recipient_data['data']['recipient_code'];
        
        // Step 2: Initiate Transfer
        $urlTransfer = "https://api.paystack.co/transfer";
        $transferFields = [
            'source' => 'balance',
            'amount' => (int)($amount * 100),
            'recipient' => $recipientCode,
            'reason' => $remarks,
            'reference' => $reference
        ];
        
        curl_setopt($ch, CURLOPT_URL, $urlTransfer);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transferFields));
        $resultTransfer = curl_exec($ch);
        curl_close($ch);
        
        $json = json_decode((string)$resultTransfer, true);
        if (($json['status'] ?? false) === true) {
            return [
                'success' => true,
                'message' => 'Paystack Transfer Initiated',
                'data' => $json['data'],
                'reference' => $json['data']['reference'] ?? ''
            ];
        }

        $errorMsg = $json['message'] ?? 'Unknown error';
        
        // Sandbox bypass for testing withdrawal flow without real funds
        if ($this->env !== 'production' && stripos($errorMsg, 'balance is not enough') !== false) {
            return [
                'success' => true,
                'message' => 'Simulated Paystack Transfer (Sandbox Insufficient Balance)',
                'data' => [],
                'reference' => 'SIM-' . $reference
            ];
        }

        return ['success' => false, 'message' => 'Paystack Transfer Failed: ' . $errorMsg, 'data' => $json];
    }
}
