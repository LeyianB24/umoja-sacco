<?php
/**
 * inc/FinancialEngine.php
 * Unified Financial Core - Golden Ledger (V28 Architecture)
 * The Single Source of Truth for all Money Movement.
 */

require_once __DIR__ . '/functions.php';

class FinancialEngine {
    
    // Ledger Categories
    const CAT_WALLET   = 'wallet';   // Operating account (liquid funds)
    const CAT_SAVINGS  = 'savings';  // Fixed/Member savings
    const CAT_LOANS    = 'loans';    // Outstanding debt
    const CAT_SHARES   = 'shares';   // Equity
    const CAT_WELFARE  = 'welfare';  // Social fund
    const CAT_CASH     = 'cash';     // SACCO Cash at hand
    const CAT_BANK     = 'bank';     // SACCO Bank account
    const CAT_INCOME   = 'income';   // SACCO Revenue
    const CAT_EXPENSE  = 'expense';  // SACCO Expenses
    const CAT_MPESA_CLEARING = 'mpesa_clearing'; // Pending B2C funds

    private $db;
    private $recorded_by;

    public function __construct($db, $recorded_by = null) {
        $this->db = $db;
        $this->recorded_by = $recorded_by ?? $_SESSION['admin_id'] ?? null;
    }

    /**
     * main entry point for all money movement
     * Strict Double-Entry Architecture
     */
    public function transact($params) {
        $val = $params['member_id'] ?? null;
        $member_id = (!empty($val) && is_numeric($val) && $val > 0) ? (int)$val : null;
        $amount         = (float)($params['amount'] ?? 0);
        $action_type    = $params['action_type']; 
        $reference      = $params['reference'] ?? ('REF-' . strtoupper(uniqid()));
        $notes          = $params['notes'] ?? "";
        $method         = $params['method'] ?? 'cash'; // 'cash', 'mpesa', 'bank', 'wallet'
        $related_id     = $params['related_id'] ?? null;
        $related_table  = $params['related_table'] ?? null;

        if ($amount <= 0) throw new Exception("Amount must be greater than zero.");

        // 0. Idempotency Check
        $check = $this->db->prepare("SELECT transaction_id FROM ledger_transactions WHERE reference_no = ?");
        $check->bind_param("s", $reference);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        if ($existing) {
            return (int)$existing['transaction_id'];
        }

        $this->db->begin_transaction();
        try {
            
            // 1. Create Transaction Shell
            $txn_id = $this->createTransactionShell($reference, $action_type, $notes);

            // ... cases ...
            // (I will skip the cases in this chunk but they are between lines 55-160)

            // 2. Map Action to Double-Entry Accounts
            switch ($action_type) {
                case 'dividend_payment':
                // Debit Equity (Retained Earnings), Credit Liability (Member Wallet)
                $this->postEntry($txn_id, $this->getSystemAccount('SACCO Revenue'), $amount, 0); // Using Revenue as placeholder for Retained Earnings source
                $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WALLET), 0, $amount);
                break;

            case 'share_purchase':
                // Debit Asset (Method), Credit Equity (Shares)
                $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_SHARES), 0, $amount);
                break;
                
            case 'savings_deposit':
                    // Debit Asset (Cash/Mpesa), Credit Liability (Member Savings)
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_SAVINGS), 0, $amount);
                    break;

                case 'withdrawal':
                    // LEGACY/DIRECT - Prefer using withdrawal_initiate + withdrawal_finalize
                    $source_cat = $params['source_cat'] ?? self::CAT_WALLET;
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, $source_cat), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount($method), 0, $amount);
                    break;

                case 'withdrawal_initiate':
                    // Step 1: Debit Member, Credit M-Pesa Clearing (Pending Hold)
                    $source_cat = $params['source_cat'] ?? self::CAT_WALLET;
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, $source_cat), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('mpesa_clearing'), 0, $amount);
                    break;

                case 'withdrawal_finalize':
                    // Step 2: Success - Debit Clearing, Credit the gateway/method (Actual Outflow)
                    $this->postEntry($txn_id, $this->getSystemAccount('mpesa_clearing'), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount($method), 0, $amount);
                    
                    // Trigger Notifications with new balance
                    $new_balances = $this->getBalances($member_id);
                    $bal_text = number_format($new_balances['wallet'] ?? 0, 2);
                    $this->triggerNotification($member_id, "Withdrawal of KES " . number_format($amount) . " successful. New Wallet Balance: KES $bal_text", $txn_id);
                    break;

                case 'withdrawal_revert':
                    // Step 2: Failed - Debit Clearing, Credit back to Member
                    $dest_cat = $params['dest_cat'] ?? self::CAT_WALLET;
                    $this->postEntry($txn_id, $this->getSystemAccount('mpesa_clearing'), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, $dest_cat), 0, $amount);
                    
                    // Trigger Notifications with new balance
                    $new_balances = $this->getBalances($member_id);
                    $bal_text = number_format($new_balances[$dest_cat] ?? 0, 2);
                    $this->triggerNotification($member_id, "Withdrawal of KES " . number_format($amount) . " failed. Funds returned. New " . ucfirst($dest_cat) . " Balance: KES $bal_text", $txn_id);
                    break;

                case 'loan_disbursement':
                    // 1. Double-Entry: Member owes more (Asset), Sacco has less Cash (Asset)
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_LOANS), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount($method), 0, $amount);
                    
                    // 2. Audit Trail: Show money passing through Wallet (Debit/Credit cancel out)
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WALLET), 0, $amount);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WALLET), $amount, 0);

                    // 3. Sync to Legacy Loans Table
                    if ($related_table === 'loans' && $related_id) {
                        $this->db->query("UPDATE loans SET status = 'disbursed', disbursed_date = NOW(), disbursed_amount = $amount, current_balance = total_payable WHERE loan_id = $related_id");
                    }
                    break;

                case 'loan_repayment':
                    if ($method === 'wallet') {
                        $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WALLET), $amount, 0);
                    } else {
                        $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    }
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_LOANS), 0, $amount);

                    // Sync to Legacy Loans & Repayments
                    if ($related_table === 'loans' && $related_id) {
                        $l_q = $this->db->query("SELECT current_balance FROM loans WHERE loan_id = $related_id");
                        $loan = $l_q->fetch_assoc();
                        $new_bal = max(0, $loan['current_balance'] - $amount);
                        $status = ($new_bal <= 0) ? 'completed' : 'disbursed';
                        
                        $this->db->query("UPDATE loans SET current_balance = $new_bal, status = '$status' WHERE loan_id = $related_id");
                        
                        $st = $this->db->prepare("INSERT INTO loan_repayments (loan_id, amount_paid, payment_date, payment_method, reference_no, remaining_balance, status) VALUES (?, ?, CURDATE(), ?, ?, ?, 'Completed')");
                        $st->bind_param("isssd", $related_id, $amount, $method, $reference, $new_bal);
                        $st->execute();

                        if ($status === 'completed') {
                            $this->db->query("UPDATE loan_guarantors SET status = 'released' WHERE loan_id = $related_id");
                        }
                    }
                    break;

                case 'share_purchase':
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_SHARES), 0, $amount);
                    break;

                case 'revenue_inflow':
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('income'), 0, $amount);
                    break;

                case 'expense_outflow':
                    $this->postEntry($txn_id, $this->getSystemAccount('expense'), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount($method), 0, $amount);
                    break;

                case 'welfare_contribution':
                    // Debit Asset (Cash/Mpesa), Credit Liability (Social Pool)
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('welfare'), 0, $amount);
                    break;

                case 'welfare_pool_consolidation':
                    // Debit Liability (Member Welfare Account), Credit Liability (Social Pool)
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WELFARE), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('welfare'), 0, $amount);
                    break;

                case 'welfare_payout':
                    // Debit Liability (Social Pool), Credit Liability (Member Wallet)
                    $this->postEntry($txn_id, $this->getSystemAccount('welfare'), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WALLET), 0, $amount);
                    break;

                case 'transfer':
                    $from_cat = $params['source_ledger'];
                    $to_cat = $params['target_ledger'];
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, $from_cat), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, $to_cat), 0, $amount);
                    break;

                case 'opening_balance':
                    $ledger = $params['target_ledger'];
                    $acc_id = $this->getMemberAccount($member_id, $ledger);
                    $q = $this->db->query("SELECT account_type FROM ledger_accounts WHERE account_id = $acc_id");
                    $type = $q->fetch_assoc()['account_type'];
                    
                    if ($type === 'asset' || $type === 'expense') {
                        $this->postEntry($txn_id, $acc_id, $amount, 0);
                    } else {
                        $this->postEntry($txn_id, $acc_id, 0, $amount);
                    }
                    break;

                case 'registration_fee':
                    // Debit Asset (Cash/Mpesa), Credit Revenue (SACCO Income)
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('income'), 0, $amount);
                    break;

                case 'loan_penalty':
                    // Debit Asset (Cash/Mpesa), Credit Revenue (SACCO Income)
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('income'), 0, $amount);
                    break;

                default:
                    throw new Exception("Core: Unsupported action [$action_type]");
            }

            // 3. Sync to human-readable 'transactions' table (Legacy compatibility)
            $this->syncLegacyTransactions($txn_id, $member_id, $amount, $action_type, $reference, $notes, $related_id, $related_table);

            $this->db->commit();
            return $txn_id;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function createTransactionShell($ref, $action, $notes) {
        $st = $this->db->prepare("INSERT INTO ledger_transactions (reference_no, transaction_date, description, action_type, recorded_by) VALUES (?, CURDATE(), ?, ?, ?)");
        $st->bind_param("sssi", $ref, $notes, $action, $this->recorded_by);
        $st->execute();
        return $this->db->insert_id;
    }

    private function postEntry($txn_id, $account_id, $debit, $credit) {
        // Calculate new balance for the account
        $q = $this->db->query("SELECT account_type, current_balance FROM ledger_accounts WHERE account_id = $account_id FOR UPDATE");
        $acc = $q->fetch_assoc();
        
        $new_balance = $acc['current_balance'];
        
        // Asset/Expense: Debit+, Credit-
        // Liability/Equity/Revenue: Credit+, Debit-
        if ($acc['account_type'] == 'asset' || $acc['account_type'] == 'expense') {
            $new_balance += ($debit - $credit);
        } else {
            $new_balance += ($credit - $debit);
        }

        // 1. Update Account Balance
        $this->db->query("UPDATE ledger_accounts SET current_balance = $new_balance WHERE account_id = $account_id");

        // 2. Record Entry
        $st = $this->db->prepare("INSERT INTO ledger_entries (transaction_id, account_id, debit, credit, balance_after) VALUES (?, ?, ?, ?, ?)");
        $st->bind_param("iiddd", $txn_id, $account_id, $debit, $credit, $new_balance);
        $st->execute();
    }

    public function getSystemAccount($key) {
        // Consolidated Settlement Architecture (V28)
        // All external money movement maps to the Single Master Settlement Account
        $master_account = 'Paystack Clearing Account';
        
        $name_map = [
            'cash'           => $master_account,
            'mpesa'          => $master_account,
            'bank'           => $master_account,
            'paystack'       => $master_account,
            'mpesa_clearing' => $master_account, // Clearing happens directly on the master ledger
            
            // Internal Pools (Unchanged)
            'income'         => 'SACCO Revenue',
            'expense'        => 'SACCO Expenses',
            'welfare'        => 'Welfare Fund Pool'
        ];
        $name = $name_map[$key] ?? $key;
        $q = $this->db->query("SELECT account_id FROM ledger_accounts WHERE account_name = '$name' LIMIT 1");
        $row = $q->fetch_assoc();
        if (!$row) throw new Exception("System Account Missing: $name");
        return (int)$row['account_id'];
    }

    public function getMemberAccount($member_id, $category) {
        $q = $this->db->prepare("SELECT account_id FROM ledger_accounts WHERE member_id = ? AND category = ?");
        $q->bind_param("is", $member_id, $category);
        $q->execute();
        $res = $q->get_result();
        if ($row = $res->fetch_assoc()) return (int)$row['account_id'];

        // Else Create
        $types = [
            self::CAT_SAVINGS => 'liability',
            self::CAT_LOANS   => 'asset',
            self::CAT_WALLET  => 'liability',
            self::CAT_SHARES  => 'equity',
            self::CAT_WELFARE => 'liability'
        ];
        $type = $types[$category] ?? 'liability';
        $full_name = "Member $member_id " . ucfirst($category);
        
        $st = $this->db->prepare("INSERT INTO ledger_accounts (account_name, account_type, member_id, category) VALUES (?, ?, ?, ?)");
        $st->bind_param("ssis", $full_name, $type, $member_id, $category);
        $st->execute();
        return (int)$this->db->insert_id;
    }

    private function syncLegacyTransactions($txn_id, $mid, $amt, $action, $ref, $notes, $rid, $rtable) {
        if ($action === 'opening_balance') return;
        $flow = ($action === 'savings_deposit' || $action === 'loan_disbursement' || $action === 'revenue_inflow' || $action === 'welfare_payout') ? 'credit' : 'debit';
        $cat = explode('_', $action)[0];
        $date = date('Y-m-d');
        
        $sql = "INSERT INTO transactions 
                (ledger_transaction_id, member_id, transaction_type, amount, type, category, reference_no, related_id, related_table, recorded_by, transaction_date, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $st = $this->db->prepare($sql);
        $st->bind_param("iisdsssisiss", $txn_id, $mid, $action, $amt, $flow, $cat, $ref, $rid, $rtable, $this->recorded_by, $date, $notes);
        $st->execute();
    }

    public function getWelfarePoolBalance() {
        try {
            $acc_id = $this->getSystemAccount('welfare');
            $q = $this->db->query("SELECT current_balance FROM ledger_accounts WHERE account_id = $acc_id");
            if (!$q) return 0;
            return (float)($q->fetch_assoc()['current_balance'] ?? 0);
        } catch (Exception $e) {
            error_log("FinancialEngine Error: " . $e->getMessage());
            return 0;
        }
    }

    public function getMemberWelfareLifetime($mid) {
        try {
            $stmt = $this->db->prepare("SELECT SUM(amount) as total FROM transactions WHERE member_id = ? AND transaction_type = 'welfare_contribution'");
            if (!$stmt) return 0;
            $stmt->bind_param("i", $mid);
            $stmt->execute();
            $res = $stmt->get_result();
            return (float)($res->fetch_assoc()['total'] ?? 0);
        } catch (Exception $e) {
            error_log("FinancialEngine Error: " . $e->getMessage());
            return 0;
        }
    }

    public function getBalances($mid) {
        $bal = [
            'wallet' => 0,
            'savings' => 0,
            'loans' => 0,
            'shares' => 0,
            'welfare' => 0
        ];
        
        $q = $this->db->prepare("SELECT category, current_balance FROM ledger_accounts WHERE member_id = ?");
        $q->bind_param("i", $mid);
        $q->execute();
        $res = $q->get_result();
        while ($row = $res->fetch_assoc()) {
            $bal[$row['category']] = (float)$row['current_balance'];
        }
        return $bal;
    }

    public function getCategoryWithdrawals($mid, $category) {
        $acc_id = $this->getMemberAccount($mid, $category);
        // Withdrawals are DEBITS from Member Liability/Equity accounts
        $stmt = $this->db->prepare("SELECT SUM(debit) as total FROM ledger_entries WHERE account_id = ?");
        $stmt->bind_param("i", $acc_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return (float)($res['total'] ?? 0.0);
    }

    public function getLifetimeCredits($mid, $category = null) {
        if (is_array($category)) {
            $ids = [];
            foreach($category as $cat) $ids[] = $this->getMemberAccount($mid, $cat);
            $id_list = implode(',', $ids);
            $stmt = $this->db->prepare("SELECT SUM(credit) as total FROM ledger_entries WHERE account_id IN ($id_list)");
        } elseif ($category) {
            $acc_id = $this->getMemberAccount($mid, $category);
            $stmt = $this->db->prepare("SELECT SUM(credit) as total FROM ledger_entries WHERE account_id = ?");
            $stmt->bind_param("i", $acc_id);
        } else {
            // All credits for this member across all accounts
            $stmt = $this->db->prepare("SELECT SUM(le.credit) as total FROM ledger_entries le 
                                       JOIN ledger_accounts la ON le.account_id = la.account_id 
                                       WHERE la.member_id = ?");
            $stmt->bind_param("i", $mid);
        }
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return (float)($res['total'] ?? 0.0);
    }

    private function triggerNotification($mid, $message, $txn_id) {
        $helper_path = __DIR__ . '/notification_helpers.php';
        if (file_exists($helper_path)) {
            require_once $helper_path;
        }
        
        // Ensure function exists before calling to prevent fatal error
        if (function_exists('add_notification')) {
            add_notification($mid, "Transaction Update", $message, 'info', "member/pages/transactions.php?id=$txn_id");
        } else {
            error_log("FinancialEngine: add_notification function missing.");
        }
    }
}
