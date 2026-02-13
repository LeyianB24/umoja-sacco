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
            'par_value' => (float)($settings['par_value'] ?? 100.00)
        ]);
    }

    /**
     * Issue shares to a member
     */
    public function issueShares(int $member_id, float $amount, string $reference, string $method = 'mpesa'): int {
        $valuation = $this->getValuation();
        $unit_price = (float)$valuation['price'];
        $units = $amount / $unit_price;

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
     * Perform Dividend Distribution
     */
    public function distributeDividends(float $total_pool, string $reference): bool {
        $valuation = $this->getValuation();
        $totalUnits = $valuation['total_units'];
        if ($totalUnits <= 0) return false;

        $dividend_per_unit = $total_pool / $totalUnits;

        $this->db->begin_transaction();
        try {
            $res = $this->db->query("SELECT member_id, units_owned FROM member_shareholdings WHERE units_owned > 0");
            while ($row = $res->fetch_assoc()) {
                $mid = (int)$row['member_id'];
                $units = (float)$row['units_owned'];
                $member_dividend = $units * $dividend_per_unit;

                // 1. Credit Member's Wallet via FinancialEngine
                // Debit: Equity (Retained Earnings), Credit: Liability (Member Wallet)
                $this->financialEngine->transact([
                    'member_id' => $mid,
                    'amount' => $member_dividend,
                    'action_type' => 'dividend_payment',
                    'reference' => $reference . '-M' . $mid,
                    'notes' => "Dividend payout: " . number_format($units, 2) . " units @ KES " . number_format($dividend_per_unit, 2)
                ]);

                // 2. Log in share_transactions
                $stmt = $this->db->prepare("INSERT INTO share_transactions (member_id, units, unit_price, total_value, transaction_type, reference_no) VALUES (?, 0, ?, ?, 'dividend', ?)");
                $price_placeholder = 0.0;
                $ref = $reference . '-DIV-' . $mid;
                $stmt->bind_param("idds", $mid, $price_placeholder, $member_dividend, $ref);
                $stmt->execute();
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
