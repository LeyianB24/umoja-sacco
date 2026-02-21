<?php
declare(strict_types=1);
// usms/inc/Trial_Balance_Logic.php
// V12 Golden Ledger Trial Balance Logic
// Fixes "Unknown column" errors by utilizing the central transactions table

class TrialBalanceLogic {
    private $db;
    
    public function __construct($conn) {
        $this->db = $conn;
    }
    
    public function calculate() {
        return [
            'debits' => $this->calculateDebits(),   // Assets + Expenses
            'credits' => $this->calculateCredits()  // Liabilities + Equity + Income
        ];
    }
    
    private function calculateDebits() {
        $debits = [];
        
        // 1. Cash at Hand (Golden Ledger State)
        // Sum of all SACCO system accounts (Cash, Bank, Mpesa)
        $query = "SELECT SUM(current_balance) as val FROM ledger_accounts WHERE category IN ('cash', 'bank', 'mpesa')";
        $res = $this->db->query($query);
        $cash = $res->fetch_assoc()['val'] ?? 0;
        
        $debits[] = ['category' => 'Cash & Bank Balances', 'amount' => (float)$cash, 'type' => 'Asset'];
        
        // 2. Loans Issued (Asset) - Ledger State
        $query = "SELECT SUM(current_balance) as val FROM ledger_accounts WHERE category = 'loans'";
        $res = $this->db->query($query);
        $loans = $res->fetch_assoc()['val'] ?? 0;
        
        $debits[] = ['category' => 'Loans Receivable', 'amount' => (float)$loans, 'type' => 'Asset'];
        
        // 3. Operational Expenses (related_table is null or 'expense')
        $query = "SELECT SUM(amount) as val FROM transactions WHERE transaction_type = 'expense' AND (related_table IS NULL OR related_table = 'expense')";
        $res = $this->db->query($query);
        $ops = $res->fetch_assoc()['val'] ?? 0;
        
        $debits[] = ['category' => 'Operational Expenses', 'amount' => (float)$ops, 'type' => 'Expense'];
        
        // 4. Vehicle Expenses
        $query = "SELECT SUM(amount) as val FROM transactions WHERE transaction_type = 'expense' AND related_table = 'vehicle'";
        $res = $this->db->query($query);
        $veh = $res->fetch_assoc()['val'] ?? 0;
        
        $debits[] = ['category' => 'Vehicle Expenses', 'amount' => (float)$veh, 'type' => 'Expense'];

        // 5. Investment Expenses
        $query = "SELECT SUM(amount) as val FROM transactions WHERE transaction_type = 'expense' AND related_table = 'investment'";
        $res = $this->db->query($query);
        $inv = $res->fetch_assoc()['val'] ?? 0;
        
        $debits[] = ['category' => 'Investment Expenses', 'amount' => (float)$inv, 'type' => 'Expense'];
        
        return $debits;
    }
    
    private function calculateCredits() {
        $credits = [];
        
        // 1. Member Savings (Liability)
        // Source of Truth: Ledger Sum for Savings Category
        $query = "SELECT SUM(current_balance) as val FROM ledger_accounts WHERE category = 'savings'";
        $res = $this->db->query($query);
        $savings = $res->fetch_assoc()['val'] ?? 0;
        
        $credits[] = ['category' => 'Member Savings', 'amount' => (float)$savings, 'type' => 'Liability'];
        
        // 2. Share Capital (Equity)
        $query = "SELECT SUM(amount) as val FROM transactions WHERE transaction_type = 'share_capital'";
        $res = $this->db->query($query);
        $shares = $res->fetch_assoc()['val'] ?? 0;
        
        $credits[] = ['category' => 'Share Capital', 'amount' => (float)$shares, 'type' => 'Equity'];
        
        // 3. Welfare (Liability/Fund)
        $query = "SELECT SUM(amount) as val FROM transactions WHERE related_table = 'welfare'";
        $res = $this->db->query($query);
        $welfare = $res->fetch_assoc()['val'] ?? 0;
        
        $credits[] = ['category' => 'Welfare Fund', 'amount' => (float)$welfare, 'type' => 'Liability'];
        
        // 4. Investment Income
        $query = "SELECT SUM(amount) as val FROM transactions WHERE transaction_type = 'income' AND related_table = 'investment'";
        $res = $this->db->query($query);
        $inv = $res->fetch_assoc()['val'] ?? 0;
        
        $credits[] = ['category' => 'Investment Income', 'amount' => (float)$inv, 'type' => 'Income'];
        
        // 5. Vehicle Income
        $query = "SELECT SUM(amount) as val FROM transactions WHERE transaction_type = 'income' AND related_table = 'vehicle'";
        $res = $this->db->query($query);
        $veh = $res->fetch_assoc()['val'] ?? 0;
        
        $credits[] = ['category' => 'Vehicle Income', 'amount' => (float)$veh, 'type' => 'Income'];
        
        // 6. Registration Fees
        $query = "SELECT SUM(amount) as val FROM transactions WHERE related_table = 'registration_fee'";
        $res = $this->db->query($query);
        $reg = $res->fetch_assoc()['val'] ?? 0;
        
        $credits[] = ['category' => 'Registration Fees', 'amount' => (float)$reg, 'type' => 'Income'];
        
        // 7. Fines
        $query = "SELECT SUM(amount) as val FROM transactions WHERE related_table IN ('fine', 'fines')";
        $res = $this->db->query($query);
        $fine = $res->fetch_assoc()['val'] ?? 0;
        
        $credits[] = ['category' => 'Fines', 'amount' => (float)$fine, 'type' => 'Income'];
        
        // 8. Other Interest
         $query = "SELECT SUM(amount) as val FROM transactions WHERE transaction_type = 'interest'";
        $res = $this->db->query($query);
        $int = $res->fetch_assoc()['val'] ?? 0;
        
        if ($int > 0) {
             $credits[] = ['category' => 'Interest Income', 'amount' => (float)$int, 'type' => 'Income'];
        }

        return $credits;
    }
    
    public function getCategoryDetails($category, $type = 'debit') {
        $details = [];
        
        switch($category) {
            case 'Cash at Hand':
                $query = "SELECT * FROM transactions ORDER BY created_at DESC LIMIT 100";
                break;
            case 'Loans Receivable':
                $query = "SELECT * FROM loans WHERE status IN ('active', 'disbursed') ORDER BY created_at DESC";
                break;
            case 'Member Savings':
                $query = "SELECT member_id, account_name, current_balance as account_balance FROM ledger_accounts WHERE category = 'savings' ORDER BY current_balance DESC LIMIT 100";
                break;
            case 'Vehicle Expenses':
                 $query = "SELECT * FROM transactions WHERE transaction_type = 'expense' AND related_table = 'vehicle' ORDER BY created_at DESC LIMIT 100";
                 break;
            default:
                // Default to transaction search if possible, or empty
                return $details;
        }
        
        $result = $this->db->query($query);
        if ($result) {
            while($row = $result->fetch_assoc()) {
                $details[] = $row;
            }
        }
        
        return $details;
    }
}
