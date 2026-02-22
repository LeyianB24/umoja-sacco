<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// 1. Auth & Layout
require_admin();
require_permission();
$layout = LayoutManager::create('admin');
?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }
    </style>
<?php
/**
 * admin/pages/trial_balance.php
 * The Mathematical Proof - Golden Ledger V10
 * Equation: Assets = Liabilities + Equity
 */

$pageTitle = "Trial Balance Proof";

// 2. Fetch All Ledger Accounts for Dynamic Grouping
$sql = "SELECT * FROM ledger_accounts ORDER BY account_type ASC, category ASC, account_name ASC";
$res = $conn->query($sql);
$all_accounts = [];
while ($row = $res->fetch_assoc()) {
    $all_accounts[] = $row;
}

// 3. Categorization Logic
$assets = [];
$liabilities = [];
$equity = [];

$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;

foreach ($all_accounts as $acc) {
    $bal = (float)$acc['current_balance'];
    $type = strtolower($acc['account_type']);
    
    if ($type === 'asset') {
        $assets[] = $acc;
        $total_assets += $bal;
    } elseif ($type === 'liability') {
        $liabilities[] = $acc;
        $total_liabilities += $bal;
    } elseif ($type === 'equity') {
        $equity[] = $acc;
        $total_equity += $bal;
    }
}

// Special Logic for Retained Earnings (Profit/Loss Surplus)
// We check if "SACCO Revenue" and "SACCO Expenses" are in the list.
$revenue_total = 0;
$expense_total = 0;
foreach ($all_accounts as $acc) {
    if (stripos((string)($acc['account_name'] ?? ''), 'revenue') !== false) $revenue_total += (float)$acc['current_balance'];
    if (stripos((string)($acc['account_name'] ?? ''), 'expense') !== false) $expense_total += (float)$acc['current_balance'];
}
$net_income = $revenue_total - $expense_total;

// THE ULTIMATE TEST
$balance_check = $total_assets - ($total_liabilities + $total_equity);
$is_balanced = abs($balance_check) < 0.01;

// 4. Handle Export Actions
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $export_data = [];
    $export_data[] = ['Category', 'Account', 'Amount (Dr)', 'Amount (Cr)'];
    
    foreach ($assets as $a) $export_data[] = ['ASSET', $a['account_name'], number_format((float)$a['current_balance'], 2), ''];
    $export_data[] = ['ASSET', 'TOTAL ASSETS', number_format((float)$total_assets, 2), ''];
    $export_data[] = ['', '', '', ''];
    
    foreach ($liabilities as $l) $export_data[] = ['LIABILITY', $l['account_name'], '', number_format((float)$l['current_balance'], 2)];
    foreach ($equity as $e) $export_data[] = ['EQUITY', $e['account_name'], '', number_format((float)$e['current_balance'], 2)];
    $export_data[] = ['EQUITY', 'Net Income (P&L)', '', number_format((float)$net_income, 2)];
    $export_data[] = ['', 'TOTAL LIAB + EQUITY', '', number_format((float)($total_liabilities + $total_equity), 2)];
    
    UniversalExportEngine::handle($format, $export_data, [
        'title' => 'Trial Balance Audit Proof',
        'module' => 'Internal Audit',
        'is_balanced' => $is_balanced,
        'difference' => $balance_check
    ]);
    exit;
}
?>
<?php $layout->header($pageTitle); ?>
<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'Accounting Ledger'); ?>
        <div class="container-fluid">

        <!-- Layout Header -->
        <div class="portal-header fade-in">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Audit Protocol V10.5</span>
                    <h1 class="display-5 fw-800 mb-2">Trial Balance Proof</h1>
                    <p class="opacity-75 fs-5 mb-0">Mathematically verifying that Assets = Liabilities + Equity.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <div class="dropdown">
                        <button class="btn btn-lime-pill shadow-lg dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export Analysis
                        </button>
                        <ul class="dropdown-menu shadow-lg border-0 mt-2">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export Integrity (PDF)</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Print Friendly</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Row: KPIs and Chart -->
        <div class="row g-4 mb-4">
            
            <!-- Left Side: 3 KPI Cards -->
            <div class="col-xl-8">
                <div class="row g-4 h-100">
                    <!-- Total Assets -->
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden position-relative" style="background: #ffffff;">
                            <div class="card-body p-4 d-flex flex-column justify-content-center">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="text-muted small fw-800 text-uppercase ls-1" style="font-size: 0.75rem;">Total Assets</div>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                        <i class="bi bi-safe2 fs-5"></i>
                                    </div>
                                </div>
                                <h3 class="fw-900 mb-1" style="color: #111827; letter-spacing: -1px;">KES <?= number_format($total_assets, 2) ?></h3>
                                <div class="small fw-600 mt-auto" style="color: #10b981;"><i class="bi bi-graph-up-arrow me-1"></i> Debit Balance</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Liabilities + Equity -->
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden position-relative <?= $is_balanced ? 'bg-success text-white' : 'bg-danger text-white' ?>" style="<?= $is_balanced ? 'background: linear-gradient(135deg, #0f392b 0%, #1a5140 100%);' : '' ?>">
                            <div class="card-body p-4 d-flex flex-column justify-content-center position-relative z-1">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="small fw-800 text-uppercase ls-1 opacity-75" style="font-size: 0.75rem;">Liab. & Equity</div>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(255, 255, 255, 0.15); color: #ffffff;">
                                        <i class="bi bi-shield-lock fs-5"></i>
                                    </div>
                                </div>
                                <h3 class="fw-900 mb-1 text-white" style="letter-spacing: -1px;">KES <?= number_format($total_liabilities + $total_equity, 2) ?></h3>
                                <div class="small fw-600 mt-auto opacity-75 text-white"><i class="bi <?= $is_balanced ? 'bi-check2-all' : 'bi-x-octagon' ?> me-1"></i> Credit Balance</div>
                            </div>
                            <!-- Background Icon Decor -->
                            <i class="bi bi-shield-lock position-absolute opacity-10" style="font-size: 8rem; right: -15px; bottom: -25px;"></i>
                        </div>
                    </div>

                    <!-- Net Income -->
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden position-relative" style="background: #d0f764;">
                            <div class="card-body p-4 d-flex flex-column justify-content-center position-relative z-1">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="small fw-800 text-uppercase ls-1" style="color: #0f392b; font-size: 0.75rem;">Net Income (P&L)</div>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(15, 57, 43, 0.1); color: #0f392b;">
                                        <i class="bi bi-graph-up-arrow fs-5"></i>
                                    </div>
                                </div>
                                <h3 class="fw-900 mb-1" style="color: #0f392b; letter-spacing: -1px;">KES <?= number_format($net_income, 2) ?></h3>
                                <div class="small fw-600 mt-auto" style="color: rgba(15, 57, 43, 0.7);"><i class="bi bi-stars me-1"></i> Recorded Surplus/Deficit</div>
                            </div>
                            <!-- Background Icon Decor -->
                            <i class="bi bi-graph-up-arrow position-absolute" style="font-size: 7rem; right: -10px; bottom: -20px; color: rgba(15, 57, 43, 0.05);"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Chart.js Doughnut -->
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm h-100 rounded-4" style="background: #ffffff;">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark">Equation Balance</h6>
                            <span class="badge <?= $is_balanced ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-pill px-3 py-2 fw-bold">
                                <?= $is_balanced ? 'Balanced' : 'Imbalanced' ?>
                            </span>
                        </div>
                        <div class="flex-grow-1 position-relative" style="min-height: 200px;">
                            <canvas id="trialBalanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

        <div class="row g-4">
            <!-- Left: Assets -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="background: #ffffff;">
                    <div class="card-header bg-transparent border-bottom p-4">
                        <h5 class="mb-0 fw-bold d-flex align-items-center"><i class="bi bi-safe2 me-2 fs-4 text-success"></i> ASSETS (Debit Balance)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                        <?php 
                        $asset_cats = [];
                        foreach($assets as $a) {
                            $cat = $a['category'] ?: 'Uncategorized';
                            if(!isset($asset_cats[$cat])) $asset_cats[$cat] = 0;
                            $asset_cats[$cat] += (float)$a['current_balance'];
                        }
                        foreach($asset_cats as $name => $val): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-4 border-bottom" style="cursor: pointer; border-style: dashed !important;" data-bs-toggle="modal" data-bs-target="#modal_<?= str_replace([' ','/','-'],'_',(string)$name) ?>" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'">
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= strtoupper($name) ?> GROUP</span>
                                <span class="fw-bold text-success font-monospace" style="font-size: 1.05rem;"><?= number_format($val, 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-light p-4 border-0 rounded-bottom-4 d-flex justify-content-between align-items-center">
                        <span class="fw-800 text-muted small text-uppercase ls-1" style="font-size: 0.75rem;">Total Debit Entries</span>
                        <span class="h4 fw-900 text-success mb-0" style="letter-spacing: -0.5px;">KES <?= number_format($total_assets, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Right: Liabilities & Equity -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="background: #ffffff;">
                    <div class="card-header bg-transparent border-bottom p-4">
                        <h5 class="mb-0 fw-bold d-flex align-items-center"><i class="bi bi-shield-lock me-2 fs-4 text-info"></i> LIABILITIES & EQUITY (Credit)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                        <!-- Group Liabilities -->
                        <?php 
                        $liab_cats = [];
                        foreach($liabilities as $l) {
                            $cat = $l['category'] ?: 'Uncategorized';
                            if(!isset($liab_cats[$cat])) $liab_cats[$cat] = 0;
                            $liab_cats[$cat] += (float)$l['current_balance'];
                        }
                        foreach($liab_cats as $name => $val): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-4 border-bottom" style="cursor: pointer; border-style: dashed !important;" data-bs-toggle="modal" data-bs-target="#modal_<?= str_replace([' ','/','-'],'_',(string)$name) ?>" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'">
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= strtoupper($name) ?> OBLIGATIONS</span>
                                <span class="fw-bold text-dark font-monospace" style="font-size: 1.05rem;"><?= number_format($val, 2) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <!-- Equity Section -->
                        <div class="px-4 py-3 bg-light small fw-800 text-muted border-bottom text-uppercase ls-1" style="font-size: 0.75rem;">Equity & Reserves</div>
                        <?php 
                        $equity_cats = [];
                        foreach($equity as $e) {
                            $cat = $e['category'] ?: 'Equity';
                            if(!isset($equity_cats[$cat])) $equity_cats[$cat] = 0;
                            $equity_cats[$cat] += (float)$e['current_balance'];
                        }
                        foreach($equity_cats as $name => $val): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-4 border-bottom" style="cursor: pointer; border-style: dashed !important;" data-bs-toggle="modal" data-bs-target="#modal_<?= str_replace([' ','/','-'],'_',(string)$name) ?>" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'">
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= strtoupper($name) ?> PORTFOLIO</span>
                                <span class="fw-bold text-dark font-monospace" style="font-size: 1.05rem;"><?= number_format($val, 2) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <!-- Net Income -->
                        <div class="list-group-item d-flex justify-content-between align-items-center p-4" style="background: rgba(15, 57, 43, 0.02);">
                            <span class="fw-bold text-dark" style="font-size: 0.9rem;">NET INCOME (SURPLUS/DEFICIT)</span>
                            <span class="fw-bold font-monospace <?= $net_income < 0 ? 'text-danger' : 'text-success' ?>" style="font-size: 1.05rem;"><?= number_format($net_income, 2) ?></span>
                        </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light p-4 border-0 rounded-bottom-4 d-flex justify-content-between align-items-center">
                        <span class="fw-800 text-muted small text-uppercase ls-1" style="font-size: 0.75rem;">Total Credit Entries</span>
                        <span class="h4 fw-900 text-dark mb-0" style="letter-spacing: -0.5px;">KES <?= number_format($total_liabilities + $total_equity, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Verdict -->
        <div class="status-banner slide-up text-center p-5 mt-5 rounded-4 border-0 shadow-sm" style="<?= $is_balanced ? 'background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));' : 'background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));' ?>">
            <div class="<?= $is_balanced ? 'text-success' : 'text-danger' ?>">
                <i class="bi <?= $is_balanced ? 'bi-patch-check-fill' : 'bi-exclamation-triangle-fill' ?> display-1 mb-3"></i>
                <h2 class="fw-900 mb-2 ls-1"><?= $is_balanced ? 'SYSTEM BALANCED' : 'IMBALANCE DETECTED' ?></h2>
                <div class="d-inline-flex align-items-center gap-2 mt-2 px-4 py-2 rounded-pill fw-bold fs-6 <?= $is_balanced ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                    <span>Equation Difference:</span>
                    <span class="font-monospace fs-5"><?= number_format($balance_check, 4) ?></span>
                </div>
            </div>
        </div>

        <?php if(!$is_balanced): ?>
            <div class="alert mt-4 shadow-sm border-0 d-flex gap-4 p-4 rounded-4 align-items-start slide-up" style="background-color: #fef2f2; border-left: 6px solid #ef4444 !important; animation-delay: 0.2s;">
                <i class="bi bi-info-circle-fill fs-2" style="color: #ef4444;"></i>
                <div>
                    <h5 class="fw-bold mb-2" style="color: #991b1b; letter-spacing: -0.5px;">Audit Recommendation</h5>
                    <p class="mb-0 text-dark opacity-75" style="line-height: 1.6;">
                        A difference of more than <strong>0.01</strong> suggests a database-level manual entry that bypassed the Golden Ledger Double-Entry system. Please review recent manual SQL updates to the <code class="bg-white px-2 py-1 rounded-2 text-danger">ledger_accounts</code> or <code class="bg-white px-2 py-1 rounded-2 text-danger">transactions</code> tables.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center mt-5 text-muted small pb-5">
            Internal Audit Protocol V10.4 &bull; Generated <?= date('d M Y, H:i:s') ?> &bull; <?= SITE_NAME ?> Finance
        </div>
        
        <?php $layout->footer(); ?>
    </div>
    
</div>

<!-- Dynamic Modals for Drill-Down -->
<?php 
$all_cats = array_unique(array_column($all_accounts, 'category'));
foreach($all_cats as $cat_name): 
    $safe_id = str_replace([' ','/','-'],'_', (string)$cat_name);
    $cat_total = 0;
?>
<div class="modal fade" id="modal_<?= $safe_id ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-2xl">
            <div class="modal-header">
                <h5 class="modal-title fw-800 text-uppercase">Audit Detail: <?= $cat_name ?: 'General' ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 border-0">Account Name</th>
                                <th class="text-end pe-4 border-0">Balance (KES)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($all_accounts as $acc): if($acc['category'] == $cat_name): $cat_total += (float)$acc['current_balance']; ?>
                                <tr>
                                    <td class="ps-4 py-3"><?= htmlspecialchars($acc['account_name']) ?></td>
                                    <td class="text-end pe-4 py-3 fw-bold"><?= number_format((float)$acc['current_balance'], 2) ?></td>
                                </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                        <tfoot class="border-top-2">
                            <tr class="table-dark">
                                <td class="ps-4 py-3 fw-bold">GROUP TOTAL</td>
                                <td class="text-end pe-4 py-3 fw-800">KES <?= number_format($cat_total, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
        </div>
        
    </div>
</div>

<!-- Chart.js Injection -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('trialBalanceChart');
    if (!ctx) return;

    // Data passed from PHP
    const totalAssets = <?= (float)$total_assets ?>;
    const totalLiabEq = <?= (float)($total_liabilities + $total_equity) ?>;
    const isBalanced = <?= $is_balanced ? 'true' : 'false' ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Total Assets (Debit)', 'Liabilities & Equity (Credit)'],
            datasets: [{
                data: [totalAssets, totalLiabEq],
                backgroundColor: [
                    '#10b981', // Forest/Green for Assets
                    isBalanced ? '#0f392b' : '#ef4444'  // Dark forest if balanced, Red if imbalanced
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            size: 13,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(context.parsed);
                            }
                            return label;
                        }
                    },
                    backgroundColor: 'rgba(15, 57, 43, 0.9)',
                    titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 13 },
                    bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 14, weight: 'bold' },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false
                }
            }
        }
    });
});
</script>
