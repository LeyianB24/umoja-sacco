<?php
/**
 * inc/TransactionHelper.php
 * V28 Golden Ledger Bridge - Maps high-level events to the Double-Entry Engine.
 */

class TransactionHelper {
    private $db;
    private static $static_conn = null;

    public function __construct($conn) {
        $this->db = $conn;
    }

    public static function setConnection($conn) {
        self::$static_conn = $conn;
    }

    public static function record($params) {
        global $conn;
        $db = (self::$static_conn) ? self::$static_conn : $conn;
        $instance = new self($db);
        return $instance->processRecord($params);
    }

    public function processRecord($params) {
        require_once __DIR__ . '/FinancialEngine.php';
        $engine = new FinancialEngine($this->db);
        
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
        } elseif ($type === 'expense') {
            $action = 'expense_outflow';
        } elseif ($type === 'registration_fee' || $type === 'registration') {
            $action = 'registration_fee';
        } elseif ($type === 'fine' || $type === 'penalty') {
            $action = 'loan_penalty';
        }

        try {
            return $engine->transact([
                'member_id'     => $params['member_id'] ?? null,
                'amount'        => $params['amount'],
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

    public static function recordDoubleEntry($params) { return self::record($params); }
    public static function recordSimple($mid, $amt, $type, $notes = '', $ref = null) {
        return self::record([
            'member_id' => $mid,
            'amount' => $amt,
            'type' => $type,
            'notes' => $notes,
            'ref_no' => $ref
        ]);
    }
}
