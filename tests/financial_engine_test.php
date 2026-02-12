<?php
/**
 * tests/financial_engine_test.php
 * Lightweight Test Runner for Financial Engine Integrity
 */

define('CLI_MODE', true);
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/FinancialEngine.php';
require_once __DIR__ . '/../inc/Auth.php';

class FinancialEngineTest {
    private $db;
    private $engine;
    private $test_member_id = 999999; // Temporary mock member

    public function __construct($db) {
        $this->db = $db;
        $this->engine = new FinancialEngine($db);
    }

    public function run() {
        echo "=== STARTING FINANCIAL ENGINE INTEGRITY TESTS ===\n\n";
        
        try {
            $this->setup();
            
            $this->testSavingsDeposit();
            $this->testLoanDisbursement();
            $this->testLoanRepayment();
            $this->testLegacyAccountBalanceDeprecation();
            
            echo "\nALL TESTS PASSED SUCCESSFULLY! 🚀\n";
        } catch (Throwable $e) {
            echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
            if ($this->db->error) {
                echo "DB Error: " . $this->db->error . "\n";
            }
        } finally {
            $this->teardown();
        }
    }

    private function setup() {
        echo "[1/5] Setting up test environment... ";
        $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
        $this->db->query("DELETE FROM transactions WHERE member_id = {$this->test_member_id}");
        $this->db->query("DELETE FROM members WHERE member_id = {$this->test_member_id}");
        $this->db->query("INSERT INTO members (member_id, full_name, member_reg_no, account_balance, status, email, phone, national_id) 
                          VALUES ({$this->test_member_id}, 'Test Member', 'TEST-001', 0, 'active', 'test@example.com', '254700000000', 'ID-9999-99')");
        
        // Clear ledger for test member
        $this->db->query("DELETE FROM ledger_entries WHERE account_id IN (SELECT account_id FROM ledger_accounts WHERE member_id = {$this->test_member_id})");
        $this->db->query("DELETE FROM ledger_accounts WHERE member_id = {$this->test_member_id}");
        $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo "Done.\n";
    }

    private function testSavingsDeposit() {
        echo "[2/5] Testing Savings Deposit... ";
        $amount = 1000.00;
        $this->engine->transact([
            'member_id' => $this->test_member_id,
            'amount' => $amount,
            'action_type' => 'savings_deposit',
            'method' => 'cash',
            'notes' => 'Test Savings'
        ]);

        $balances = $this->engine->getBalances($this->test_member_id);
        if ($balances['savings'] != $amount) {
            throw new Exception("Savings balance mismatch. Expected $amount, got " . $balances['savings']);
        }
        echo "Passed.\n";
    }

    private function testLoanDisbursement() {
        echo "[3/5] Testing Loan Disbursement... ";
        $amount = 5000.00;
        
        // Mock a loan record in legacy table if needed, or just test ledger
        $this->engine->transact([
            'member_id' => $this->test_member_id,
            'amount' => $amount,
            'action_type' => 'loan_disbursement',
            'method' => 'bank',
            'notes' => 'Test Loan'
        ]);

        $balances = $this->engine->getBalances($this->test_member_id);
        if ($balances['loans'] != $amount) {
            throw new Exception("Loan balance mismatch. Expected $amount, got " . $balances['loans']);
        }
        echo "Passed.\n";
    }

    private function testLoanRepayment() {
        echo "[4/5] Testing Loan Repayment... ";
        $repayment = 2000.00;
        
        $this->engine->transact([
            'member_id' => $this->test_member_id,
            'amount' => $repayment,
            'action_type' => 'loan_repayment',
            'method' => 'cash',
            'notes' => 'Test Repayment'
        ]);

        $balances = $this->engine->getBalances($this->test_member_id);
        if ($balances['loans'] != 3000.00) {
            throw new Exception("Loan balance after repayment mismatch. Expected 3000.00, got " . $balances['loans']);
        }
        echo "Passed.\n";
    }

    private function testLegacyAccountBalanceDeprecation() {
        echo "[5/5] Verifying Legacy Column Deprecation... ";
        
        $res = $this->db->query("SELECT account_balance FROM members WHERE member_id = {$this->test_member_id}");
        $row = $res->fetch_assoc();
        
        if ($row['account_balance'] != 0) {
            throw new Exception("CRITICAL: Legacy account_balance was modified! Deprecation failed.");
        }
        echo "Verified (Column remains unchanged).\n";
    }

    private function teardown() {
        $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
        $this->db->query("DELETE FROM transactions WHERE member_id = {$this->test_member_id}");
        $this->db->query("DELETE FROM ledger_entries WHERE account_id IN (SELECT account_id FROM ledger_accounts WHERE member_id = {$this->test_member_id})");
        $this->db->query("DELETE FROM ledger_accounts WHERE member_id = {$this->test_member_id}");
        $this->db->query("DELETE FROM members WHERE member_id = {$this->test_member_id}");
        $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo "\nCleanup completed.\n";
    }
}

// Execute Tests
$tester = new FinancialEngineTest($conn);
$tester->run();
