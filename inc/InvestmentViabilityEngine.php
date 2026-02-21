<?php
declare(strict_types=1);
/**
 * InvestmentViabilityEngine.php
 * Target-Driven Investment Performance & Economic Viability Calculator
 * 
 * Automatically evaluates investment performance against targets
 * using real revenue and expense data from the Golden Ledger.
 */

class InvestmentViabilityEngine {
    private $db;
    
    public function __construct($conn) {
        $this->db = $conn;
    }
    
    /**
     * Calculate performance metrics for a specific asset (Investment or Vehicle)
     * 
     * @param int $asset_id
     * @param string $table 'investments' or 'vehicles'
     * @return array Performance data
     */
    public function calculatePerformance(int $asset_id, string $table = 'investments') {
        // Get asset details
        $sql = "SELECT investment_id as id, title, category, target_amount, target_period, target_start_date FROM investments WHERE investment_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $asset_id);
        $stmt->execute();
        $asset = $stmt->get_result()->fetch_assoc();
        
        if (!$asset) {
            return null;
        }
        
        // Handle missing columns gracefully
        $target_start_date = $asset['target_start_date'] ?? null;
        $target_period = $asset['target_period'] ?? 'monthly';
        $target_amount = (float)($asset['target_amount'] ?? 0);
        
        // Determine evaluation period
        $period_start = $this->getPeriodStart($target_period, $target_start_date);
        $period_end = date('Y-m-d');
        
        // Get revenue for this asset
        $rev_sql = "SELECT SUM(amount) as total FROM transactions 
                    WHERE related_table = ? 
                    AND related_id = ? 
                    AND transaction_type IN ('income', 'revenue_inflow')
                    AND transaction_date BETWEEN ? AND ?";
        $stmt_rev = $this->db->prepare($rev_sql);
        $stmt_rev->bind_param("siss", $table, $asset_id, $period_start, $period_end);
        $stmt_rev->execute();
        $revenue = (float)($stmt_rev->get_result()->fetch_assoc()['total'] ?? 0);
        
        // Get expenses for this asset
        $exp_sql = "SELECT SUM(amount) as total FROM transactions 
                    WHERE related_table = ? 
                    AND related_id = ? 
                    AND transaction_type IN ('expense', 'expense_outflow')
                    AND transaction_date BETWEEN ? AND ?";
        $stmt_exp = $this->db->prepare($exp_sql);
        $stmt_exp->bind_param("siss", $table, $asset_id, $period_start, $period_end);
        $stmt_exp->execute();
        $expenses = (float)($stmt_exp->get_result()->fetch_assoc()['total'] ?? 0);
        
        // Calculate metrics
        $net_profit = $revenue - $expenses;
        $target_achievement = $target_amount > 0 ? ($revenue / $target_amount) * 100 : 0;
        
        // Determine viability status
        $viability = $this->determineViability($revenue, $expenses, $target_amount, $target_achievement);
        
        return [
            'asset_id' => $asset_id,
            'asset_table' => $table,
            'title' => $asset['title'],
            'period_start' => $period_start,
            'period_end' => $period_end,
            'target_amount' => $target_amount,
            'actual_revenue' => $revenue,
            'total_expenses' => $expenses,
            'net_profit' => $net_profit,
            'target_achievement_pct' => round($target_achievement, 2),
            'viability_status' => $viability,
            'is_profitable' => $net_profit > 0
        ];
    }
    
    /**
     * Determine viability status based on performance
     */
    private function determineViability($revenue, $expenses, $target, $achievement_pct) {
        $net_profit = $revenue - $expenses;
        
        // Loss-making: Negative profit
        if ($net_profit < 0) {
            return 'loss_making';
        }
        
        // Underperforming: Profitable but below 70% of target
        if ($achievement_pct < 70) {
            return 'underperforming';
        }
        
        // Viable: Meeting or exceeding 70% of target with positive profit
        if ($net_profit > 0 && $achievement_pct >= 70) {
            return 'viable';
        }
        
        return 'pending';
    }
    
    /**
     * Get period start date based on target period
     */
    private function getPeriodStart($period, $start_date) {
        if (!$start_date) {
            $start_date = date('Y-m-01'); // Default to current month start
        }
        
        switch ($period) {
            case 'daily':
                return date('Y-m-d'); // Today
            case 'monthly':
                return date('Y-m-01'); // Start of current month
            case 'annually':
                return date('Y-01-01'); // Start of current year
            default:
                return $start_date;
        }
    }
    
    /**
     * Update viability status in database
     */
    public function updateViabilityStatus(int $asset_id, string $table = 'investments') {
        $performance = $this->calculatePerformance($asset_id, $table);
        
        if (!$performance || $table !== 'investments') {
            return false;
        }
        
        $sql = "UPDATE investments 
                SET viability_status = ?, 
                    last_viability_check = NOW() 
                WHERE investment_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $performance['viability_status'], $asset_id);
        return $stmt->execute();
    }
    
    /**
     * Batch update all active investments
     */
    public function updateAllViabilities() {
        $sql = "SELECT investment_id FROM investments WHERE status = 'active'";
        $result = $this->db->query($sql);
        
        $updated = 0;
        while ($row = $result->fetch_assoc()) {
            if ($this->updateViabilityStatus($row['investment_id'])) {
                $updated++;
            }
        }
        
        return $updated;
    }
    
    /**
     * Get viability summary for dashboard
     */
    public function getViabilitySummary() {
        $sql = "SELECT 
                    viability_status,
                    COUNT(*) as count,
                    SUM(target_amount) as total_targets,
                    SUM(current_value) as total_value
                FROM investments 
                WHERE status = 'active'
                GROUP BY viability_status";
        
        $result = $this->db->query($sql);
        $summary = [];
        
        while ($row = $result->fetch_assoc()) {
            $summary[$row['viability_status']] = $row;
        }
        
        return $summary;
    }
}
?>
