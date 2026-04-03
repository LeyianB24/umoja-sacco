<?php
// usms/inc/ShareValuationEngine.php
declare(strict_types=1);

require_once __DIR__ . '/FinancialEngine.php';
require_once __DIR__ . '/ReportGenerator.php';

class ShareValuationEngine {
    private $db;
    private $financialEngine;
    private $reportGenerator;

    public function __construct($conn) {
        $this->db = $conn;
        $this->financialEngine = new FinancialEngine($conn);
        $this->reportGenerator = new ReportGenerator($conn);
    }

    /**
     * Calculate Current NAV and Unit Price
     */
    public function getValuation(): array {
        // Reuse the logic from ReportGenerator but with more precision for the engine
        $valuation = $this->reportGenerator->getShareValuation();
        
        // Fetch global settings
        $settings = $this->db->query("SELECT * FROM share_settings LIMIT 1")->fetch_assoc();
        
        return array_merge($valuation, [
            'initial_price' => (float)($settings['initial_unit_price'] ?? 100.00),
            'par_value' => (float)($settings['par_value'] ?? 100.00),
            'total_authorized_units' => (float)($settings['total_authorized_units'] ?? 0)
        ]);
    }

    /**
     * Issue shares to a member
     */
    public function issueShares(int $member_id, float $amount, string $reference, string $method = 'mpesa'): int {
        $valuation = $this->getValuation();
        $unit_price = (float)$valuation['price'];
        $units = $amount / $unit_price;

        $authorizedUnits = (float)$valuation['total_authorized_units'];
        $currentTotalUnits = (float)$valuation['total_units'];

        // 1. Check Global Limit
        if ($currentTotalUnits + $units > $authorizedUnits) {
            throw new Exception("Share Issuance Error: The requested purchase of " . number_format($units, 2) . " units exceeds the SACCO's authorized limit. Available capacity: " . number_format(max(0, $authorizedUnits - $currentTotalUnits), 2) . " units.");
        }

        // 2. Check Individual Ownership (Cannot exceed 100% of Authorized Sacco Value)
        $stmt_m = $this->db->prepare("SELECT units_owned FROM member_shareholdings WHERE member_id = ?");
        $stmt_m->bind_param("i", $member_id);
        $stmt_m->execute();
        $memberCurrentUnits = (float)($stmt_m->get_result()->fetch_assoc()['units_owned'] ?? 0);

        if ($memberCurrentUnits + $units > $authorizedUnits) {
            throw new Exception("Ownership Violation: A single member cannot own more than 100% of the SACCO's authorized value.");
        }

        $this->db->begin_transaction();
        try {
            // 1. Double-Entry via FinancialEngine
            // Debit: Asset (Cash/M-Pesa), Credit: Equity (Shares)
            $this->financialEngine->transact([
                'member_id' => $member_id,
                'amount' => $amount,
                'action_type' => 'share_purchase',
                'reference' => $reference,
                'method' => $method,
                'notes' => "Equity Issue: " . number_format($units, 4) . " units @ KES " . number_format($unit_price, 2)
            ]);

            // 2. Log Share Transaction
            $stmt = $this->db->prepare("INSERT INTO share_transactions (member_id, units, unit_price, total_value, transaction_type, reference_no) VALUES (?, ?, ?, ?, 'purchase', ?)");
            $stmt->bind_param("iddds", $member_id, $units, $unit_price, $amount, $reference);
            $stmt->execute();

            // 3. Update Member Shareholdings (Upsert)
            $stmt = $this->db->prepare("
                INSERT INTO member_shareholdings (member_id, units_owned, total_amount_paid, average_purchase_price) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    units_owned = units_owned + VALUES(units_owned),
                    total_amount_paid = total_amount_paid + VALUES(total_amount_paid),
                    average_purchase_price = (total_amount_paid) / (units_owned)
            ");
            $stmt->bind_param("iddd", $member_id, $units, $amount, $unit_price);
            $stmt->execute();

            $this->db->commit();
            return (int)$this->db->insert_id;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Calculate Ownership Percentage
     */
    public function getOwnershipPercentage(int $member_id): float {
        $valuation = $this->getValuation();
        $totalUnits = $valuation['total_units'];
        if ($totalUnits <= 0) return 0.0;

        $stmt = $this->db->prepare("SELECT units_owned FROM member_shareholdings WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $memberUnits = (float)($stmt->get_result()->fetch_assoc()['units_owned'] ?? 0);

        return ($memberUnits / $totalUnits) * 100;
    }

    /**
     * Perform Dividend Distribution using the Enterprise DividendService (Pro-Rata)
     */
    public function distributeDividends(float $total_pool, string $reference): bool {
        try {
            $dividendService = new \USMS\Services\DividendService();
            $year = date('Y');
            $adminId = (int)($_SESSION['admin_id'] ?? 1);
            
            // 1. Declare and calculate payouts
            $res = $dividendService->distributeFromPool($total_pool, $year, $adminId);
            
            // 2. Process payouts (Finalize to Ledger)
            $count = $dividendService->processPayouts($res['period_id'], $adminId);
            
            return $count > 0;
        } catch (Exception $e) {
            error_log("Dividend Redistribution Failure: " . $e->getMessage());
            throw $e;
        }
    }
}
