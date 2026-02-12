<?php
/**
 * PaymentGatewayInterface
 * Standardizes behavior for all payment aggregators (M-Pesa, Paystack, etc.)
 */
interface PaymentGatewayInterface {
    /**
     * Initiate an Outward Payment (B2C / Withdrawal)
     * 
     * @param string $phone Customer Phone Number
     * @param float $amount Amount to send
     * @param string $reference Internal reference for tracking
     * @param string $remarks Description of payment
     * @return array ['success' => bool, 'message' => string, 'data' => mixed, 'reference' => string]
     */
    public function initiateWithdrawal($phone, $amount, $reference, $remarks = 'Withdrawal');

    /**
     * Initiate an Inward Payment (C2B / STK Push)
     * 
     * @param string $phone Customer Phone Number
     * @param float $amount Amount to request
     * @param string $reference Internal reference for tracking
     * @param $account_ref Account reference (e.g. Member ID or Purpose)
     * @return array ['success' => bool, 'message' => string, 'data' => mixed, 'checkout_id' => string]
     */
    public function initiateDeposit($phone, $amount, $reference, $account_ref = 'USMS Payment');

    /**
     * Get the environment (sandbox or production)
     */
    public function getEnvironment();
}
