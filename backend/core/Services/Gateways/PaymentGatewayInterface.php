<?php
declare(strict_types=1);

namespace USMS\Services\Gateways;

/**
 * USMS\Services\Gateways\PaymentGatewayInterface
 * Standardizes behavior for all payment aggregators (M-Pesa, Paystack, etc.)
 */
interface PaymentGatewayInterface {
    /**
     * Initiate an Outward Payment (B2C / Withdrawal)
     */
    public function initiateWithdrawal(string $phone, float $amount, string $reference, string $remarks = 'Withdrawal'): array;

    /**
     * Initiate an Inward Payment (C2B / STK Push)
     */
    public function initiateDeposit(string $phone, float $amount, string $reference, string $account_ref = 'USMS Payment'): array;

    /**
     * Get the environment (sandbox or production)
     */
    public function getEnvironment(): string;

    /**
     * Get the descriptive name of the provider
     */
    public function getProviderName(): string;
}
