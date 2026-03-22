<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use Exception;
use PDO;

/**
 * USMS\Services\FinancialService
 * Unified Financial Core - Golden Ledger (V28 Architecture)
 * The Single Source of Truth for all Money Movement.
 */
class FinancialService {
    
    // Ledger Categories
    public const CAT_WALLET   = 'wallet';   // Operating account (liquid funds)
    public const CAT_SAVINGS  = 'savings';  // Fixed/Member savings
    public const CAT_LOANS    = 'loans';    // Outstanding debt
    public const CAT_SHARES   = 'shares';   // Equity
    public const CAT_WELFARE  = 'welfare';  // Social fund
    public const CAT_CASH     = 'cash';     // SACCO Cash at hand
    public const CAT_BANK     = 'bank';     // SACCO Bank account
    public const CAT_INCOME   = 'income';   // SACCO Revenue
    public const CAT_EXPENSE  = 'expense';  // SACCO Expenses
    public const CAT_MPESA_CLEARING = 'mpesa_clearing'; // Pending B2C funds

    private PDO $db;
    private ?int $recorded_by;

    public function __construct(?int $recorded_by = null) {
        $this->db = Database::getInstance()->getPdo();
        $this->recorded_by = $recorded_by ?? (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
    }

    /**
     * main entry point for all money movement
     * Strict Double-Entry Architecture
     */
    public function transact(array $params): int {
        $member_id = isset($params['member_id']) ? (int)$params['member_id'] : null;
        $amount         = (float)($params['amount'] ?? 0);
        $action_type    = $params['action_type']; 
        $reference      = $params['reference'] ?? ('REF-' . strtoupper(uniqid()));
        $notes          = $params['notes'] ?? "";
        $method         = $params['method'] ?? 'cash'; // 'cash', 'mpesa', 'bank', 'wallet'
        $related_id     = $params['related_id'] ?? null;
        $related_table  = $params['related_table'] ?? null;

        if ($amount <= 0) throw new Exception("Amount must be greater than zero.");

        // 0. Idempotency Check
        $stmt = $this->db->prepare("SELECT transaction_id FROM ledger_transactions WHERE reference_no = ?");
        $stmt->execute([$reference]);
        $existing = $stmt->fetch();
        if ($existing) {
            return (int)$existing['transaction_id'];
        }

        $transactionStarted = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $transactionStarted = true;
        }

        try {
            // 1. Create Transaction Shell
            $txn_id = $this->createTransactionShell($reference, $action_type, $notes);

            // 2. Map Action to Double-Entry Accounts
            switch ($action_type) {
                case 'dividend_payment':
                    $this->postEntry($txn_id, $this->getSystemAccount('income'), $amount, 0); 
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WALLET), 0, $amount);
                    break;

                case 'share_purchase':
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_SHARES), 0, $amount);
                    break;
                    
                case 'savings_deposit':
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_SAVINGS), 0, $amount);
                    break;

                case 'withdrawal':
                    $source_cat = $params['source_cat'] ?? self::CAT_WALLET;
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, $source_cat), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount($method), 0, $amount);
                    break;

                case 'withdrawal_initiate':
                    $source_cat = $params['source_cat'] ?? self::CAT_WALLET;
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, $source_cat), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('mpesa_clearing'), 0, $amount);
                    break;

                case 'withdrawal_finalize':
                    $this->postEntry($txn_id, $this->getSystemAccount('mpesa_clearing'), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount($method), 0, $amount);
                    
                    $new_balances = $this->getBalances($member_id);
                    $bal_text = number_format($new_balances['wallet'] ?? 0, 2);
                    $this->triggerNotification($member_id, "Withdrawal of KES " . number_format($amount) . " successful. New Wallet Balance: KES $bal_text", $txn_id);
                    break;

                case 'withdrawal_revert':
                    $dest_cat = $params['dest_cat'] ?? self::CAT_WALLET;
                    $this->postEntry($txn_id, $this->getSystemAccount('mpesa_clearing'), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, $dest_cat), 0, $amount);
                    
                    $new_balances = $this->getBalances($member_id);
                    $bal_text = number_format($new_balances[$dest_cat] ?? 0, 2);
                    $this->triggerNotification($member_id, "Withdrawal of KES " . number_format($amount) . " failed. Funds returned. New " . ucfirst($dest_cat) . " Balance: KES $bal_text", $txn_id);
                    break;

                case 'loan_disbursement':
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_LOANS), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WALLET), 0, $amount);
                    
                    if ($related_table === 'loans' && $related_id) {
                        $this->db->prepare("UPDATE loans SET status = 'disbursed', disbursed_date = NOW(), last_repayment_date = NULL, next_repayment_date = DATE_ADD(NOW(), INTERVAL 1 MONTH), disbursed_amount = ?, current_balance = total_payable WHERE loan_id = ?")
                                 ->execute([$amount, $related_id]);
                    }
                    break;

                case 'loan_repayment':
                    if ($method === 'wallet') {
                        $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WALLET), $amount, 0);
                    } else {
                        $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    }
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_LOANS), 0, $amount);

                    if ($related_table === 'loans' && $related_id) {
                        $stmt = $this->db->prepare("SELECT amount, interest_rate, duration_months, total_payable, current_balance, next_repayment_date FROM loans WHERE loan_id = ?");
                        $stmt->execute([$related_id]);
                        $loan = $stmt->fetch();
                        $new_bal = max(0, (float)$loan['current_balance'] - $amount);
                        $status = ($new_bal <= 0) ? 'completed' : 'disbursed';
                        
                        // Calculate expected monthly installment
                        $base_total_payable = (float)$loan['total_payable'] > 0 ? (float)$loan['total_payable'] : ((float)$loan['amount'] * (1 + ((float)$loan['interest_rate']/100)));
                        $duration = (int)($loan['duration_months'] > 0 ? $loan['duration_months'] : 12);
                        $monthly_installment = $base_total_payable / $duration;

                        // Sum payments made in the current billing cycle (last 30 days before next_repayment_date)
                        $stmtCycle = $this->db->prepare("
                            SELECT SUM(amount_paid) as total_paid 
                            FROM loan_repayments 
                            WHERE loan_id = ? 
                            AND payment_date > DATE_SUB(?, INTERVAL 1 MONTH)
                        ");
                        $stmtCycle->execute([$related_id, $loan['next_repayment_date']]);
                        $cycle_paid = (float)$stmtCycle->fetch()['total_paid'];
                        
                        $total_this_cycle = $cycle_paid + $amount;
                        
                        // If they met the installment for the month, push the repayment date forward by 1 month from the CURRENT next_repayment_date
                        // If current next_repayment_date is already far in the past, it will slowly catch up, but typically it shouldn't jump from NOW() if they were late.
                        if ($total_this_cycle >= ($monthly_installment * 0.98) || $new_bal <= 0) {
                            $next_date_expr = "DATE_ADD(next_repayment_date, INTERVAL 1 MONTH)";
                        } else {
                            $next_date_expr = "next_repayment_date"; // Do not advance, they are still due for this month
                        }
                        
                        $this->db->prepare("UPDATE loans SET current_balance = ?, status = ?, last_repayment_date = NOW(), next_repayment_date = {$next_date_expr} WHERE loan_id = ?")
                                 ->execute([$new_bal, $status, $related_id]);
                        
                        $this->db->prepare("INSERT INTO loan_repayments (loan_id, amount_paid, payment_date, payment_method, reference_no, remaining_balance, status) VALUES (?, ?, CURDATE(), ?, ?, ?, 'Completed')")
                                 ->execute([$related_id, $amount, $method, $reference, $new_bal]);

                        if ($status === 'completed') {
                            $this->db->prepare("UPDATE loan_guarantors SET status = 'released' WHERE loan_id = ?")
                                     ->execute([$related_id]);
                        }
                    }
                    break;

                case 'revenue_inflow':
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('income'), 0, $amount);
                    break;

                case 'expense_outflow':
                    $this->postEntry($txn_id, $this->getSystemAccount('expense'), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount($method), 0, $amount);
                    break;

                case 'expense_incurred':
                    $this->postEntry($txn_id, $this->getSystemAccount('expense'), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('accounts_payable'), 0, $amount);
                    break;

                case 'expense_settlement':
                    $this->postEntry($txn_id, $this->getSystemAccount('accounts_payable'), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount($method), 0, $amount);
                    break;


                case 'welfare_contribution':
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('welfare'), 0, $amount);
                    break;

                case 'welfare_pool_consolidation':
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WELFARE), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('welfare'), 0, $amount);
                    break;

                case 'welfare_payout':
                    $this->postEntry($txn_id, $this->getSystemAccount('welfare'), $amount, 0);
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_WELFARE), 0, $amount);
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
                    $stmt = $this->db->prepare("SELECT account_type FROM ledger_accounts WHERE account_id = ?");
                    $stmt->execute([$acc_id]);
                    $type = $stmt->fetch()['account_type'];
                    
                    if ($type === 'asset' || $type === 'expense') {
                        $this->postEntry($txn_id, $acc_id, $amount, 0);
                    } else {
                        $this->postEntry($txn_id, $acc_id, 0, $amount);
                    }
                    break;

                case 'registration_fee':
                    $this->postEntry($txn_id, $this->getSystemAccount($method), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('income'), 0, $amount);
                    break;

                case 'loan_penalty':
                    $this->postEntry($txn_id, $this->getMemberAccount($member_id, self::CAT_LOANS), $amount, 0);
                    $this->postEntry($txn_id, $this->getSystemAccount('income'), 0, $amount);
                    break;

                default:
                    throw new Exception("Core: Unsupported action [$action_type]");
            }

            $this->syncLegacyTransactions($txn_id, $member_id, $amount, $action_type, $reference, $notes, $related_id, $related_table);

            if ($transactionStarted && $this->db->inTransaction()) {
                $this->db->commit();
            }
            return $txn_id;
        } catch (Exception $e) {
            if ($transactionStarted && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function createTransactionShell(string $ref, string $action, string $notes): int {
        $stmt = $this->db->prepare("INSERT INTO ledger_transactions (reference_no, transaction_date, description, action_type, recorded_by) VALUES (?, CURDATE(), ?, ?, ?)");
        $stmt->execute([$ref, $notes, $action, $this->recorded_by]);
        return (int)$this->db->lastInsertId();
    }

    private function postEntry(int $txn_id, int $account_id, float $debit, float $credit): void {
        $stmt = $this->db->prepare("SELECT account_type, current_balance FROM ledger_accounts WHERE account_id = ? FOR UPDATE");
        $stmt->execute([$account_id]);
        $acc = $stmt->fetch();
        
        $new_balance = (float)$acc['current_balance'];
        
        if ($acc['account_type'] == 'asset' || $acc['account_type'] == 'expense') {
            $new_balance += ($debit - $credit);
        } else {
            $new_balance += ($credit - $debit);
        }

        $this->db->prepare("UPDATE ledger_accounts SET current_balance = ? WHERE account_id = ?")
                 ->execute([$new_balance, $account_id]);

        $this->db->prepare("INSERT INTO ledger_entries (transaction_id, account_id, debit, credit, balance_after) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$txn_id, $account_id, $debit, $credit, $new_balance]);
    }

    public function getSystemAccount(string $key): int {
        $master_account = 'Paystack Clearing Account';
        $name_map = [
            'cash'           => $master_account,
            'mpesa'          => $master_account,
            'bank'           => $master_account,
            'paystack'       => $master_account,
            'mpesa_clearing' => $master_account,
            'income'         => 'SACCO Revenue',
            'expense'        => 'SACCO Expenses',
            'welfare'        => 'Welfare Fund Pool',
            'accounts_payable' => 'Accounts Payable'
        ];
        $name = $name_map[$key] ?? $key;
        $stmt = $this->db->prepare("SELECT account_id FROM ledger_accounts WHERE account_name = ? LIMIT 1");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if (!$row) {
            if ($name === 'Accounts Payable') {
                $this->db->prepare("INSERT INTO ledger_accounts (account_name, account_type, member_id, category) VALUES (?, 'liability', NULL, 'system')")
                         ->execute([$name]);
                return (int)$this->db->lastInsertId();
            }
            throw new Exception("System Account Missing: $name");
        }
        return (int)$row['account_id'];
    }

    public function getMemberAccount(?int $member_id, string $category): int {
        $stmt = $this->db->prepare("SELECT account_id FROM ledger_accounts WHERE member_id = ? AND category = ?");
        $stmt->execute([$member_id, $category]);
        $row = $stmt->fetch();
        if ($row) return (int)$row['account_id'];

        $types = [
            self::CAT_SAVINGS => 'liability',
            self::CAT_LOANS   => 'asset',
            self::CAT_WALLET  => 'liability',
            self::CAT_SHARES  => 'equity',
            self::CAT_WELFARE => 'liability'
        ];
        $type = $types[$category] ?? 'liability';
        $full_name = "Member $member_id " . ucfirst($category);
        
        $this->db->prepare("INSERT INTO ledger_accounts (account_name, account_type, member_id, category) VALUES (?, ?, ?, ?)")
                 ->execute([$full_name, $type, $member_id, $category]);
        return (int)$this->db->lastInsertId();
    }

    private function syncLegacyTransactions(int $txn_id, ?int $mid, float $amt, string $action, string $ref, string $notes, ?int $rid, ?string $rtable): void {
        if ($action === 'opening_balance') return;
        $flow = in_array($action, ['savings_deposit', 'loan_disbursement', 'revenue_inflow', 'welfare_payout']) ? 'credit' : 'debit';
        $cat = explode('_', $action)[0];
        $date = date('Y-m-d');
        
        $sql = "INSERT INTO transactions 
                (ledger_transaction_id, member_id, transaction_type, amount, type, category, reference_no, related_id, related_table, recorded_by, transaction_date, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $this->db->prepare($sql)->execute([$txn_id, $mid, $action, $amt, $flow, $cat, $ref, $rid, $rtable, $this->recorded_by, $date, $notes]);
    }

    public function getWelfarePoolBalance(): float {
        try {
            $acc_id = $this->getSystemAccount('welfare');
            $stmt = $this->db->prepare("SELECT current_balance FROM ledger_accounts WHERE account_id = ?");
            $stmt->execute([$acc_id]);
            $row = $stmt->fetch();
            return (float)($row['current_balance'] ?? 0);
        } catch (Exception $e) {
            error_log("FinancialService Error: " . $e->getMessage());
            return 0;
        }
    }

    public function getBalances(?int $mid): array {
        $bal = ['wallet' => 0, 'savings' => 0, 'loans' => 0, 'shares' => 0, 'welfare' => 0];
        $stmt = $this->db->prepare("SELECT category, current_balance FROM ledger_accounts WHERE member_id = ?");
        $stmt->execute([$mid]);
        while ($row = $stmt->fetch()) {
            $bal[$row['category']] = (float)$row['current_balance'];
        }
        return $bal;
    }

    /**
     * Legacy support: Sum of all credits for a member in a category
     */
    public function getLifetimeCredits(int $member_id, string|array $category): float {
        if (is_array($category)) {
            $total = 0;
            foreach ($category as $cat) {
                $total += $this->getLifetimeCredits($member_id, $cat);
            }
            return (float)$total;
        }

        $acc_id = $this->getMemberAccount($member_id, $category);
        $stmt = $this->db->prepare("SELECT SUM(credit) as total FROM ledger_entries WHERE account_id = ?");
        $stmt->execute([$acc_id]);
        $row = $stmt->fetch();
        return (float)($row['total'] ?? 0);
    }

    /**
     * Legacy support: Sum of all debits for a member in a category (withdrawals)
     */
    public function getCategoryWithdrawals(int $member_id, string $category): float {
        $acc_id = $this->getMemberAccount($member_id, $category);
        $stmt = $this->db->prepare("SELECT SUM(debit) as total FROM ledger_entries WHERE account_id = ?");
        $stmt->execute([$acc_id]);
        $row = $stmt->fetch();
        return (float)($row['total'] ?? 0);
    }

    /**
     * Legacy support: Welfare specific lifetime contribution
     */
    public function getMemberWelfareLifetime(int $member_id): float {
        return $this->getLifetimeCredits($member_id, self::CAT_WELFARE);
    }

    private function triggerNotification(?int $mid, string $message, int $txn_id): void {
        if (function_exists('add_notification')) {
            add_notification($mid, "Transaction Update", $message, 'info', "member/pages/transactions.php?id=$txn_id");
        }
    }
}
