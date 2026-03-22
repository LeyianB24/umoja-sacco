<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use USMS\Services\FinancialService;
use Exception;
use PDO;

/**
 * USMS\Services\TransactionService
 * V28 Golden Ledger Bridge - Maps high-level events to the Double-Entry Engine.
 */
class TransactionService {
    private PDO $db;
    private FinancialService $financialService;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
        $this->financialService = new FinancialService();
    }

    /**
     * Legacy Bridge - Maps parameters to FinancialService action types
     */
    public function record(array $params): int|bool {
        $type = $params['type'] ?? 'credit';
        $cat  = strtolower($params['category'] ?? '');
        $action = 'savings_deposit'; // Default

        // 1. Mapping for Action Types
        if ($type === 'loan_disbursement') {
            $action = 'loan_disbursement';
        } elseif ($type === 'loan_repayment') {
            $action = 'loan_repayment';
        } elseif ($type === 'withdrawal' || $type === 'debit') {
            $action = 'withdrawal';
        } elseif ($cat === 'shares') {
            $action = 'share_purchase';
        } elseif ($cat === 'welfare') {
            $action = 'welfare_contribution';
        } elseif ($type === 'income' || $cat === 'revenue') {
            $action = 'revenue_inflow';
        } elseif ($type === 'expense_incurred') {
            $action = 'expense_incurred';
        } elseif ($type === 'expense_settlement') {
            $action = 'expense_settlement';
        } elseif ($type === 'expense') {
            $action = 'expense_outflow';
        } elseif ($type === 'registration_fee' || $type === 'registration') {
            $action = 'registration_fee';
        } elseif ($type === 'fine' || $type === 'penalty') {
            $action = 'loan_penalty';
        }

        try {
            return $this->financialService->transact([
                'member_id'     => $params['member_id'] ?? null,
                'amount'        => (float)$params['amount'],
                'action_type'   => $action,
                'reference'     => $params['ref_no'] ?? $params['msg_id'] ?? null,
                'notes'         => $params['notes'] ?? $params['description'] ?? '',
                'related_id'    => $params['related_id'] ?? null,
                'related_table' => $params['related_table'] ?? null,
                'method'        => $params['method'] ?? 'cash',
                'source_cat'    => $cat
            ]);
        } catch (Exception $e) {
            if (defined('APP_ENV') && APP_ENV === 'development') throw $e;
            return false;
        }
    }

    /**
     * Static gateway for convenience (migration helper)
     */
    public static function quickRecord(array $params): int|bool {
        return (new self())->record($params);
    }

    public static function recordDoubleEntry(array $params): int|bool {
        return self::quickRecord($params);
    }

    public static function recordSimple(int|null $mid, float $amt, string $type, string $notes = '', string $ref = null): int|bool {
        return self::quickRecord([
            'member_id' => $mid,
            'amount' => $amt,
            'type' => $type,
            'notes' => $notes,
            'ref_no' => $ref
        ]);
    }

    // Backward compatibility shim for static calls that didn't exist but might be expected
    public static function recordStatic(array $params) {
        return self::recordDoubleEntry($params);
    }
}
