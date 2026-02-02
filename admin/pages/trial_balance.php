<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
/**
 * admin/reports/trial_balance.php
 * The Mathematical Proof - Golden Ledger V10
 * Equation: Assets = Liabilities + Equity
 */

Auth::requireAdmin();

$pageTitle = "Trial Balance Proof";

// 1. ASSETS
$assets_q = $conn->query("SELECT SUM(current_balance) as Total FROM ledger_accounts WHERE account_type = 'asset'");
$total_assets = (float)($assets_q->fetch_assoc()['Total'] ?? 0);

// Specific Asset breakdown
$cash_q = $conn->query("SELECT SUM(current_balance) FROM ledger_accounts WHERE account_name IN ('Cash at Hand', 'M-Pesa Float', 'Bank Account')");
$cash_on_hand = (float)($cash_q->fetch_row()[0] ?? 0);

$loans_q = $conn->query("SELECT SUM(current_balance) FROM ledger_accounts WHERE category = 'loans'");
$outstanding_loans = (float)($loans_q->fetch_row()[0] ?? 0);

// 2. LIABILITIES
$liabilities_q = $conn->query("SELECT SUM(current_balance) as Total FROM ledger_accounts WHERE account_type = 'liability'");
$total_liabilities = (float)($liabilities_q->fetch_assoc()['Total'] ?? 0);

// Specific Liability breakdown
$member_balances_q = $conn->query("SELECT SUM(current_balance) FROM ledger_accounts WHERE category = 'savings' OR category = 'wallet'");
$member_balances = (float)($member_balances_q->fetch_row()[0] ?? 0);

$welfare_fund_q = $conn->query("SELECT SUM(current_balance) FROM ledger_accounts WHERE category = 'welfare'");
$welfare_fund = (float)($welfare_fund_q->fetch_row()[0] ?? 0);

// 3. EQUITY
$equity_q = $conn->query("SELECT SUM(current_balance) as Total FROM ledger_accounts WHERE account_type = 'equity'");
$total_equity = (float)($equity_q->fetch_assoc()['Total'] ?? 0);

// Specific Equity breakdown
$share_capital_q = $conn->query("SELECT SUM(current_balance) FROM ledger_accounts WHERE category = 'shares'");
$share_capital = (float)($share_capital_q->fetch_row()[0] ?? 0);

// Retained Earnings (Revenue - Expenses)
$rev_q = $conn->query("SELECT SUM(current_balance) FROM ledger_accounts WHERE account_name = 'SACCO Revenue'");
$exp_q = $conn->query("SELECT SUM(current_balance) FROM ledger_accounts WHERE account_name = 'SACCO Expenses'");
$net_income = (float)($rev_q->fetch_row()[0] ?? 0) - (float)($exp_q->fetch_row()[0] ?? 0);

// THE ULTIMATE TEST
$balance_check = $total_assets - ($total_liabilities + $total_equity);
$is_balanced = abs($balance_check) < 0.01;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | Unified USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; display: flex; }
        .main-content { margin-left: 260px; flex: 1; padding: 50px; }
        .ledger-box { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .ledger-header { background: #0F2E25; color: #fff; padding: 30px; }
        .row-item { padding: 15px 30px; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; }
        .row-total { background: #f8f9fa; font-weight: bold; font-size: 1.1rem; }
        .status-banner { padding: 20px; text-align: center; font-weight: bold; letter-spacing: 2px; }
        .status-balanced { background: #D0F35D; color: #0F2E25; }
        .status-error { background: #ff4757; color: #fff; }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold mb-0">System Integrity Proof</h2>
        <div class="text-muted small">Generated: <?= date('Y-m-d H:i:s') ?></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="ledger-box h-100">
                <div class="ledger-header">
                    <h5 class="mb-0">Left Side: ASSETS (Dr)</h5>
                </div>
                <div class="row-item">
                    <span>Cash / Bank Balances</span>
                    <span class="fw-bold"><?= number_format((float)$cash_on_hand, 2) ?></span>
                </div>
                <div class="row-item">
                    <span>Outstanding Member Loans</span>
                    <span class="fw-bold"><?= number_format((float)$outstanding_loans, 2) ?></span>
                </div>
                <div class="row-item row-total border-0">
                    <span>TOTAL ASSETS</span>
                    <span class="text-primary"><?= number_format((float)$total_assets, 2) ?></span>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="ledger-box h-100">
                <div class="ledger-header" style="background: #164639;">
                    <h5 class="mb-0">Right Side: LIABILITIES & EQUITY (Cr)</h5>
                </div>
                <div class="px-4 py-2 bg-light small fw-bold text-muted">Liabilities</div>
                <div class="row-item">
                    <span>Member Balances (Savings/Wallet)</span>
                    <span class="fw-bold"><?= number_format((float)$member_balances, 2) ?></span>
                </div>
                <div class="row-item">
                    <span>Welfare Donations Pool</span>
                    <span class="fw-bold"><?= number_format((float)$welfare_fund, 2) ?></span>
                </div>
                <div class="px-4 py-2 bg-light small fw-bold text-muted">Equity</div>
                <div class="row-item">
                    <span>Share Capital</span>
                    <span class="fw-bold"><?= number_format((float)$share_capital, 2) ?></span>
                </div>
                <div class="row-item">
                    <span>Retained Earnings (P&L)</span>
                    <span class="fw-bold"><?= number_format((float)$net_income, 2) ?></span>
                </div>
                <div class="row-item row-total border-0">
                    <span>TOTAL LIAB + EQUITY</span>
                    <span class="text-success"><?= number_format((float)($total_liabilities + $total_equity), 2) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5">
        <?php if ($is_balanced): ?>
            <div class="ledger-box status-banner status-balanced shadow-lg">
                <i class="bi bi-shield-check me-2"></i> SYSTEM BALANCED: GOLDEN LEDGER CONSISTENT (DIFF: <?= number_format((float)$balance_check, 4) ?>)
            </div>
        <?php else: ?>
            <div class="ledger-box status-banner status-error shadow-lg">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> ERROR: IMBALANCE DETECTED (DIFF: <?= number_format((float)$balance_check, 2) ?>)
            </div>
            <div class="alert alert-danger mt-3 rounded-4">
                <strong>Warning:</strong> The ledger balance is off. This indicates a transaction was recorded without an offsetting entry or a manual database edit has occurred.
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-4 text-center">
        <button onclick="window.print()" class="btn btn-outline-secondary px-4"><i class="bi bi-printer me-2"></i> Print Proof</button>
    </div>
</main>

</body>
</html>





