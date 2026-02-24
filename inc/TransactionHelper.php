<?php
/**
 * inc/TransactionHelper.php (BRIDGE)
 * Safely bridges legacy static calls to the namespaced USMS\Services\TransactionService.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('TransactionHelper')) {
    class TransactionHelper {
        /**
         * Safely proxy 'record' static call to TransactionService instance
         */
        public static function record(array $params): int|false {
            $service = new \USMS\Services\TransactionService();
            return $service->record($params);
        }

        /**
         * Proxy to static quickRecord
         */
        public static function quickRecord(array $params): int|false {
            return \USMS\Services\TransactionService::quickRecord($params);
        }

        /**
         * Proxy to static recordDoubleEntry
         */
        public static function recordDoubleEntry(array $params): int|false {
            return \USMS\Services\TransactionService::recordDoubleEntry($params);
        }

        /**
         * Proxy to static recordSimple
         */
        public static function recordSimple(int|null $mid, float $amt, string $type, string $notes = '', string $ref = null): int|false {
            return \USMS\Services\TransactionService::recordSimple($mid, $amt, $type, $notes, $ref);
        }
    }
}

// Global legacy function shim
if (!function_exists('record_legacy_transaction')) {
    function record_legacy_transaction($params) {
        return TransactionHelper::recordDoubleEntry($params);
    }
}
