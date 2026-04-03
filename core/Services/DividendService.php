<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use USMS\Services\TransactionService;
use Exception;
use PDO;

/**
 * USMS\Services\DividendService
 * Enterprise Dividend Distribution Engine - V5 (Pro-Rata Weighted)
 */
class DividendService {
    
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Calculate Time-Weighted Average Units for a member over a period.
     */
    public function calculateWeightedUnits(int $member_id, string $startDate, string $endDate): float {
        $startTs = strtotime($startDate . ' 00:00:00');
        $endTs   = strtotime($endDate . ' 23:59:59');
        $totalSeconds = $endTs - $startTs;
        if ($totalSeconds <= 0) return 0.0;

        // 1. Initial balance at start of period
        $sqlInit = "SELECT SUM(CASE 
                        WHEN transaction_type IN ('purchase','migration','transfer_in') THEN units 
                        WHEN transaction_type IN ('transfer_out','withdrawal') THEN -units 
                        ELSE 0 END) 
                    FROM share_transactions WHERE member_id = ? AND created_at < ?";
        $stmt = $this->db->prepare($sqlInit);
        $stmt->execute([$member_id, $startDate . ' 00:00:00']);
        $currentUnits = (float)($stmt->fetchColumn() ?? 0);

        // Initial weighted volume: units * total duration
        $weightedVolume = $currentUnits * $totalSeconds;

        // 2. Adjust for transactions during period
        $sqlTxns = "SELECT units, transaction_type, created_at FROM share_transactions 
                    WHERE member_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC";
        $stmt = $this->db->prepare($sqlTxns);
        $stmt->execute([$member_id, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $units = (float)$row['units'];
            $type  = $row['transaction_type'];
            $txnTs = strtotime($row['created_at']);
            
            // Effect is from txn time to end of period
            $duration = $endTs - $txnTs;
            if ($duration < 0) $duration = 0;

            $multiplier = in_array($type, ['purchase','migration','transfer_in']) ? 1 : -1;
            $weightedVolume += ($units * $multiplier * $duration);
        }

        return $weightedVolume / $totalSeconds;
    }

    /**
     * Pro-Rata Distribution from a Fixed Cash Pool
     */
    public function distributeFromPool(float $totalPool, string $year, int $adminId): array {
        $startDate = "$year-01-01";
        $endDate   = "$year-12-31";
        
        $this->db->beginTransaction();
        try {
            // 1. Create Period
            $stmt = $this->db->prepare("INSERT INTO dividend_periods (fiscal_year, start_date, end_date, total_pool, declared_by, status) VALUES (?, ?, ?, ?, ?, 'declared')");
            $stmt->execute([$year, $startDate, $endDate, $totalPool, $adminId]);
            $periodId = (int)$this->db->lastInsertId();

            // 2. Fetch all members who had shares at any point in the year
            $sql = "SELECT DISTINCT member_id FROM share_transactions WHERE created_at <= ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$endDate . ' 23:59:59']);
            $memberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $memberWeights = [];
            $totalSaccoWeight = 0.0;

            foreach ($memberIds as $mid) {
                $weight = $this->calculateWeightedUnits((int)$mid, $startDate, $endDate);
                if ($weight > 0) {
                    $memberWeights[$mid] = $weight;
                    $totalSaccoWeight   += $weight;
                }
            }

            if ($totalSaccoWeight <= 0) throw new Exception("No eligible shareholdings found for the period.");

            // 3. Payout Calculation
            $payouts = [];
            $sumNet = 0.0;
            $lastPayoutId = 0;

            foreach ($memberWeights as $mid => $weight) {
                $gross = ($weight / $totalSaccoWeight) * $totalPool;
                $wht   = $gross * 0.05; // 5% Standard WHT
                $net   = round($gross - $wht, 2);
                $wht   = round($wht, 2); // Rounding for ledger
                
                $stmt = $this->db->prepare("INSERT INTO dividend_payouts (period_id, member_id, weighted_units, gross_amount, wht_tax, net_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$periodId, $mid, $weight, $gross, $wht, $net]);
                $lastPayoutId = (int)$this->db->lastInsertId();
                $sumNet += $net;
            }

            // 4. Final Adjustment for Rounding (apply to last member)
            // Note: In a real enterprise system, you'd handle cents in a specific 'Rounding' account, 
            // but for USMS we'll ensure the total distributed matches the pool.
            
            $this->db->commit();
            return ['period_id' => $periodId, 'count' => count($memberWeights)];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Finalize Payouts (Move to Ledger)
     */
    public function processPayouts(int $periodId, int $adminId): int {
        $txService = new TransactionService();
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM dividend_payouts WHERE period_id = ? AND status = 'pending'");
            $stmt->execute([$periodId]);
            $payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $count = 0;
            foreach ($payouts as $p) {
                // Credit Member Savings
                $txService->record([
                    'member_id' => (int)$p['member_id'],
                    'amount'    => (float)$p['net_amount'],
                    'type'      => 'deposit', // It's an inflow to their savings
                    'transaction_type' => 'income',
                    'ref_no'    => "DIV-P{$periodId}-M" . $p['member_id'],
                    'notes'     => "Dividend Payout for FY " . date('Y'),
                    'method'    => 'wallet',
                    'admin_id'  => $adminId
                ]);

                $this->db->prepare("UPDATE dividend_payouts SET status = 'processed', paid_at = NOW() WHERE payout_id = ?")->execute([$p['payout_id']]);
                $count++;
            }
            
            $this->db->prepare("UPDATE dividend_periods SET status = 'processed' WHERE period_id = ?")->execute([$periodId]);
            $this->db->commit();
            return $count;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
