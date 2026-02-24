<?php
/**
 * inc/FinancialEngine.php (LEGACY BRIDGE)
 * Redirects to the namespaced USMS\Services\FinancialService class.
 * Handles legacy mysqli constructor arguments.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('FinancialEngine')) {
    class FinancialEngine {
        // Ledger Categories (Mirrored from FinancialService for legacy compatibility)
        public const CAT_WALLET   = 'wallet';
        public const CAT_SAVINGS  = 'savings';
        public const CAT_LOANS    = 'loans';
        public const CAT_SHARES   = 'shares';
        public const CAT_WELFARE  = 'welfare';

        private \USMS\Services\FinancialService $service;

        public function __construct($db = null) {
            // FinancialService uses Database::getInstance() (PDO)
            // The $db (mysqli) is ignored for modern logic but accepted for legacy calls.
            $this->service = new \USMS\Services\FinancialService();
        }

        public function __call($name, $arguments) {
            return call_user_func_array([$this->service, $name], $arguments);
        }

        public static function __callStatic($name, $arguments) {
            $instance = new \USMS\Services\FinancialService();
            return call_user_func_array([$instance, $name], $arguments);
        }
    }
}
