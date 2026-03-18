<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use USMS\Services\TransactionService;
use Exception;
use PDO;

/**
 * USMS\Services\DividendService
 * Enterprise Dividend Distribution Engine - V4
 * Handles dividend declaration, calculation, and distribution mapping to the ledger.
 */
class DividendService {
    
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Declares a new dividend period and calculates individual payouts.
     */
    public function declareDividends(int $year, float $rate_percent, int $admin_id): int|bool {
        $this->db->beginTransaction();

        try {
            // 1. Create Dividend Period record
            $sqlPeriod = "INSERT INTO dividend_periods (fiscal_year, rate_percentage, declared_by) VALUES (?, ?, ?)";
            $stmtP = $this->db->prepare($sqlPeriod);
            $stmtP->execute([$year, $rate_percent, $admin_id]);
            $period_id = (int)$this->db->lastInsertId();

            // 2. Fetch all members with Share Capital
            $sqlMembers = "SELECT member_id, SUM(total_value) as share_capital 
                           FROM shares 
                           GROUP BY member_id 
                           HAVING share_capital > 0";
            $res = $this->db->query($sqlMembers);

            // 3. Batch Calculate Payouts
            $sqlPayout = "INSERT INTO dividend_payouts (period_id, member_id, share_capital_snapshot, gross_amount, wht_tax, net_amount) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmtPayout = $this->db->prepare($sqlPayout);

            while ($m = $res->fetch(PDO::FETCH_ASSOC)) {
                $gross = ($rate_percent / 100) * (float)$m['share_capital'];
                $wht   = 0.05 * $gross; // 5% Standard WHT
                $net   = $gross - $wht;

                $stmtPayout->execute([
                    $period_id, 
                    (int)$m['member_id'], 
                    (float)$m['share_capital'], 
                    $gross, 
                    $wht, 
                    $net
                ]);
            }

            $this->db->commit();
            return $period_id;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Dividend Declaration Failure: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Processes payouts for a period, crediting member savings or issuing check.
     */
    public function processPayouts(int $period_id, int $admin_id, ?TransactionService $txService = null): int {
        $txService = $txService ?? new TransactionService();
        
        $sqlPending = "SELECT * FROM dividend_payouts WHERE period_id = ? AND status = 'pending'";
        $stmt = $this->db->prepare($sqlPending);
        $stmt->execute([$period_id]);
        
        $processed_count = 0;

        while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->db->beginTransaction();
            try {
                // 1. Update individual payout status
                $upSql = "UPDATE dividend_payouts SET status = 'processed', paid_at = NOW() WHERE payout_id = ?";
                $this->db->prepare($upSql)->execute([$p['payout_id']]);

                // 2. Record in Ledger (Double Entry)
                $txService->record([
                    'member_id'     => (int)$p['member_id'],
                    'amount'        => (float)$p['net_amount'],
                    'type'          => 'income', // Inflow to member
                    'ref_no'        => "DIV-{$period_id}-{$p['payout_id']}",
                    'notes'         => "Net Dividend Payout for Period #{$period_id}",
                    'related_id'    => (int)$p['payout_id'],
                    'related_table' => 'shares',
                    'admin_id'      => $admin_id,
                    'method'        => 'wallet' // Default to internal wallet for dividends
                ]);

                $this->db->commit();
                $processed_count++;
            } catch (Exception $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                error_log("Dividend Payout Processing failure for ID {$p['payout_id']}: " . $e->getMessage());
            }
        }

        return $processed_count;
    }
}
