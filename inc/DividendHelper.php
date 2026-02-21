<?php
declare(strict_types=1);
// usms/inc/DividendHelper.php
// Enterprise Dividend Distribution Engine - V4

class DividendHelper {
    
    private $db;

    public function __construct($conn) {
        $this->db = $conn;
    }

    /**
     * Declares a new dividend period and calculates individual payouts.
     */
    public function declareDividends($year, $rate_percent, $admin_id) {
        $this->db->begin_transaction();

        try {
            // 1. Create Dividend Period record
            $sqlPeriod = "INSERT INTO dividend_periods (fiscal_year, rate_percentage, declared_by) VALUES (?, ?, ?)";
            $stmtP = $this->db->prepare($sqlPeriod);
            $stmtP->bind_param("idi", $year, $rate_percent, $admin_id);
            if (!$stmtP->execute()) throw new Exception("Failed to declare period.");
            $period_id = $this->db->insert_id;
            $stmtP->close();

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

            while ($m = $res->fetch_assoc()) {
                $gross = ($rate_percent / 100) * $m['share_capital'];
                $wht   = 0.05 * $gross; // 5% Standard WHT
                $net   = $gross - $wht;

                $stmtPayout->bind_param("iidddd", 
                    $period_id, 
                    $m['member_id'], 
                    $m['share_capital'], 
                    $gross, 
                    $wht, 
                    $net
                );
                $stmtPayout->execute();
            }
            $stmtPayout->close();

            $this->db->commit();
            return $period_id;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Dividend Declaration Failure: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Processes payouts for a period, crediting member savings or issuing check.
     * Updates the General Ledger via TransactionHelper.
     */
    public function processPayouts($period_id, $admin_id, $txHelper) {
        $sqlPending = "SELECT * FROM dividend_payouts WHERE period_id = ? AND status = 'pending'";
        $stmt = $this->db->prepare($sqlPending);
        $stmt->bind_param("i", $period_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $processed_count = 0;

        while ($p = $res->fetch_assoc()) {
            $this->db->begin_transaction();
            try {
                // 1. Update individual payout status
                $upSql = "UPDATE dividend_payouts SET status = 'processed', paid_at = NOW() WHERE payout_id = ?";
                $upStmt = $this->db->prepare($upSql);
                $upStmt->bind_param("i", $p['payout_id']);
                $upStmt->execute();

                // 2. Record in Ledger (Double Entry)
                $txHelper->recordDoubleEntry([
                    'member_id'     => $p['member_id'],
                    'amount'        => $p['net_amount'],
                    'type'          => 'income', // Inflow to member
                    'ref_no'        => "DIV-{$period_id}-{$p['payout_id']}",
                    'notes'         => "Net Dividend Payout for Period #{$period_id}",
                    'related_id'    => $p['payout_id'],
                    'related_table' => 'shares', // Dividends are share-related
                    'admin_id'      => $admin_id,
                    'update_member_balance' => true,
                    'is_outflow'    => false,
                    'use_external_txn' => true
                ]);

                $this->db->commit();
                $processed_count++;
            } catch (Exception $e) {
                $this->db->rollback();
            }
        }

        return $processed_count;
    }
}
