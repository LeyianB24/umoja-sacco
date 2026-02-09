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

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    if ($format === 'excel') {
        $data = [
            ['Category', 'Account', 'Amount (Dr)', 'Amount (Cr)'],
            ['ASSETS', 'Cash / Bank Balances', number_format((float)$cash_on_hand, 2), ''],
            ['ASSETS', 'Outstanding Member Loans', number_format((float)$outstanding_loans, 2), ''],
            ['ASSETS', 'TOTAL ASSETS', number_format((float)$total_assets, 2), ''],
            ['', '', '', ''],
            ['LIABILITIES', 'Member Balances', '', number_format((float)$member_balances, 2)],
            ['LIABILITIES', 'Welfare Fund', '', number_format((float)$welfare_fund, 2)],
            ['EQUITY', 'Share Capital', '', number_format((float)$share_capital, 2)],
            ['EQUITY', 'Net Income', '', number_format((float)$net_income, 2)],
            ['', 'TOTAL LIAB + EQUITY', '', number_format((float)($total_liabilities + $total_equity), 2)],
            ['', '', '', ''],
            ['STATUS', $is_balanced ? 'BALANCED' : 'IMBALANCE', '', number_format((float)$balance_check, 2)]
        ];
        UniversalExportEngine::handle('excel', $data, ['title' => 'Trial Balance Proof', 'module' => 'Finance']);
    } else {
        // PDF Custom Layout
        $callback = function($pdf) use ($cash_on_hand, $outstanding_loans, $total_assets, $member_balances, $welfare_fund, $share_capital, $net_income, $total_liabilities, $total_equity, $is_balanced, $balance_check) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Trial Balance Integrity Proof', 0, 1, 'C');
            $pdf->Ln(5);
            
            // Assets
            $pdf->SetFillColor(15, 46, 37);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(190, 8, ' ASSETS (Debit)', 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->Cell(140, 8, 'Cash / Bank Balances', 1); $pdf->Cell(50, 8, number_format((float)$cash_on_hand, 2), 1, 1, 'R');
            $pdf->Cell(140, 8, 'Outstanding Member Loans', 1); $pdf->Cell(50, 8, number_format((float)$outstanding_loans, 2), 1, 1, 'R');
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(140, 8, 'TOTAL ASSETS', 1); $pdf->Cell(50, 8, number_format((float)$total_assets, 2), 1, 1, 'R');
            
            $pdf->Ln(10);
            
            // Liab & Equity
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(22, 70, 57);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(190, 8, ' LIABILITIES & EQUITY (Credit)', 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(140, 8, 'Member Balances (Savings/Wallet)', 1); $pdf->Cell(50, 8, number_format((float)$member_balances, 2), 1, 1, 'R');
            $pdf->Cell(140, 8, 'Welfare Donations Pool', 1); $pdf->Cell(50, 8, number_format((float)$welfare_fund, 2), 1, 1, 'R');
            $pdf->Cell(140, 8, 'Share Capital', 1); $pdf->Cell(50, 8, number_format((float)$share_capital, 2), 1, 1, 'R');
            $pdf->Cell(140, 8, 'Retained Earnings (P&L)', 1); $pdf->Cell(50, 8, number_format((float)$net_income, 2), 1, 1, 'R');
            
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(140, 8, 'TOTAL LIABILITIES + EQUITY', 1); $pdf->Cell(50, 8, number_format((float)($total_liabilities + $total_equity), 2), 1, 1, 'R');
            
            $pdf->Ln(15);
            if ($is_balanced) {
                $pdf->SetFillColor(208, 243, 93);
                $pdf->Cell(190, 12, ' STATUS: SYSTEM BALANCED (Difference: ' . number_format((float)$balance_check, 4) . ')', 0, 1, 'C', true);
            } else {
                $pdf->SetFillColor(255, 71, 87);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell(190, 12, ' STATUS: IMBALANCE DETECTED (Difference: ' . number_format((float)$balance_check, 2) . ')', 0, 1, 'C', true);
            }
        };

        UniversalExportEngine::handle($format, $callback, [
            'title' => 'Trial Balance Proof',
            'module' => 'Internal Audit'
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | Unified USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-dark: #0d3935;
            --lime-accent: #bef264;
            --bg-body: #f8fafc;
            --card-radius: 24px;
        }
        body { background: var(--bg-body); font-family: 'Outfit', sans-serif; display: flex; }
        .main-content-wrapper { margin-left: 280px; flex: 1; min-height: 100vh; transition: 0.3s; }
        
        .portal-banner {
            background: linear-gradient(135deg, var(--forest-dark) 0%, #1a4d3e 100%);
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.1);
        }
        .hope-card { background: #fff; border-radius: var(--card-radius); border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.02); overflow: hidden; height: 100%; }
        .card-header-forest { background: var(--forest-dark); color: #fff; padding: 20px 30px; }
        
        .ledger-row { padding: 15px 30px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .ledger-row span:first-child { color: #64748b; font-weight: 500; }
        .ledger-row span:last-child { font-weight: 700; color: var(--forest-dark); }
        .ledger-total { background: #f8fafc; border-top: 2px solid #e2e8f0; }
        
        .status-pill { padding: 12px 30px; border-radius: 50px; display: inline-flex; align-items: center; gap: 10px; font-weight: 800; letter-spacing: 1px; }
        .status-balanced { background: var(--lime-accent); color: var(--forest-dark); }
        .status-error { background: #fee2e2; color: #991b1b; }

        @media screen and (max-width: 992px) { .main-content-wrapper { margin-left: 0; } }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ''); ?>

    <div class="container-fluid p-4">
        
        <div class="portal-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Accounting Integrity Proof</span>
                    <h1 class="display-5 fw-bold mb-2">Golden Ledger Trial Balance</h1>
                    <p class="opacity-75 fs-5 mb-0">Mathematically verifying that Assets = Liabilities + Equity.</p>
                </div>
                <div class="col-md-4 text-md-end mt-4 mt-md-0">
                    <div class="dropdown">
                        <button class="btn btn-light btn-lg px-4 dropdown-toggle rounded-pill" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i> Export Proof
                        </button>
                        <ul class="dropdown-menu shadow border-0">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export PDF Statement</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel Spreadsheet</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print Integrity Proof</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

        <div class="row g-4">
            <!-- Assets Side -->
            <div class="col-lg-6">
                <div class="hope-card">
                    <div class="card-header-forest">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-down-left-circle me-2 text-lime"></i> ASSETS (Debit)</h5>
                    </div>
                    <div class="p-0">
                        <div class="ledger-row">
                            <span>Cash & Bank Balances</span>
                            <span><?= number_format((float)$cash_on_hand, 2) ?></span>
                        </div>
                        <div class="ledger-row">
                            <span>Outstanding Member Loans</span>
                            <span><?= number_format((float)$outstanding_loans, 2) ?></span>
                        </div>
                        <div class="ledger-row ledger-total">
                            <span class="text-uppercase small fw-bold">Total Physical & Financial Assets</span>
                            <span class="fs-5 text-primary">KES <?= number_format((float)$total_assets, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liab & Equity Side -->
            <div class="col-lg-6">
                <div class="hope-card">
                    <div class="card-header-forest" style="background: #164639;">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-up-right-circle me-2" style="color: #60a5fa;"></i> LIABILITIES & EQUITY (Credit)</h5>
                    </div>
                    <div class="p-0">
                        <div class="px-4 py-2 bg-light small fw-bold text-muted border-bottom">Member Liabilities</div>
                        <div class="ledger-row">
                            <span>Member Balances (Savings/Wallet)</span>
                            <span><?= number_format((float)$member_balances, 2) ?></span>
                        </div>
                        <div class="ledger-row border-bottom-0">
                            <span>Welfare Donations Pool</span>
                            <span><?= number_format((float)$welfare_fund, 2) ?></span>
                        </div>
                        
                        <div class="px-4 py-2 bg-light small fw-bold text-muted border-top border-bottom">Proprietary Equity</div>
                        <div class="ledger-row">
                            <span>Share Capital</span>
                            <span><?= number_format((float)$share_capital, 2) ?></span>
                        </div>
                        <div class="ledger-row">
                            <span>Retained Earnings (P&L Surplus)</span>
                            <span><?= number_format((float)$net_income, 2) ?></span>
                        </div>
                        
                        <div class="ledger-row ledger-total">
                            <span class="text-uppercase small fw-bold">Total Equities & Obligations</span>
                            <span class="fs-5 text-success">KES <?= number_format((float)($total_liabilities + $total_equity), 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 text-center">
            <?php if ($is_balanced): ?>
                <div class="status-pill status-balanced shadow-sm">
                    <i class="bi bi-shield-check fs-4"></i>
                    SYSTEM NORMALLY BALANCED (DIFF: <?= number_format((float)$balance_check, 4) ?>)
                </div>
            <?php else: ?>
                <div class="status-pill status-error shadow-sm mb-3">
                    <i class="bi bi-exclamation-octagon fs-4"></i>
                    CRITICAL IMBALANCE DETECTED (DIFF: <?= number_format((float)$balance_check, 2) ?>)
                </div>
                <div class="alert alert-danger rounded-4 shadow-sm border-0 col-md-8 mx-auto py-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-info-circle-fill fs-3"></i>
                        <div class="text-start">
                            <strong>Audit Alert:</strong> The financial ledger is technically inconsistent. This may occur if a transaction was recorded manually in the database without its offsetting double-entry.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-4 text-muted small">
                Golden Ledger Verification System &bull; <?= date('d M Y H:i:s') ?> &bull; Internal Audit Protocol V10.4
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





