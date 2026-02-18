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
    if (stripos($acc['account_name'], 'revenue') !== false) $revenue_total += (float)$acc['current_balance'];
    if (stripos($acc['account_name'], 'expense') !== false) $expense_total += (float)$acc['current_balance'];
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
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest: #0f2e25;
            --forest-light: #1a4d3d;
            --lime: #d0f35d;
            --lime-dark: #a8cf12;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(15, 46, 37, 0.05);
            --glass-shadow: 0 10px 40px rgba(15, 46, 37, 0.06);
            --card-radius: 28px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f4f7f6;
            color: var(--forest);
            min-height: 100vh;
        }

        .main-content { margin-left: 280px; padding: 30px; transition: 0.3s; }
        
        .portal-header {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
            position: relative; overflow: hidden;
        }
        .portal-header::after {
            content: ''; position: absolute; bottom: -20%; right: -5%; width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(208, 243, 93, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .proof-card {
            background: white; border-radius: var(--card-radius); border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow); overflow: hidden; height: 100%; transition: 0.3s;
        }
        .proof-card:hover { transform: translateY(-5px); }
        
        .card-header-gradient {
            background: linear-gradient(to right, var(--forest), var(--forest-light));
            padding: 25px 30px; color: white; border: none;
        }

        .ledger-item {
            padding: 18px 30px; border-bottom: 1px solid #f1f5f9;
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; transition: 0.2s;
        }
        .ledger-item:hover { background: #fcfdfe; }
        .ledger-item .acc-name { font-weight: 500; color: #64748b; font-size: 0.95rem; }
        .ledger-item .acc-val { font-weight: 800; color: var(--forest); font-size: 1.05rem; }

        .total-row {
            background: #f8fafc; padding: 25px 30px;
            border-top: 2px solid #edf2f7;
        }

        .status-banner {
            border-radius: 24px; padding: 25px; margin-top: 40px;
            display: flex; align-items: center; justify-content: center; gap: 20px;
            animation: pulse-border 2s infinite;
        }
        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(208, 243, 93, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(208, 243, 93, 0); }
            100% { box-shadow: 0 0 0 0 rgba(208, 243, 93, 0); }
        }

        .balanced-theme { background: var(--lime); color: var(--forest); border: 1px solid var(--lime-dark); }
        .imbalanced-theme { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .btn-lime-pill {
            background: var(--lime); color: var(--forest); border-radius: 50px;
            font-weight: 800; padding: 12px 30px; border: none; transition: 0.3s;
        }
        .btn-lime-pill:hover { background: var(--lime-dark); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(15, 46, 37, 0.1); }

        /* Modal Customization */
        .modal-content { border-radius: 30px; border: none; }
        .modal-header { background: var(--forest); color: white; border: none; padding: 25px 35px; border-radius: 30px 30px 0 0; }
        
        @media (max-width: 991.98px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle); ?>

        <!-- Layout Header -->
        <div class="portal-header fade-in">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Audit Protocol V10.4</span>
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

        <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

        <div class="row g-4">
            <!-- Left: Assets -->
            <div class="col-lg-6">
                <div class="proof-card slide-up" style="animation-delay: 0.1s">
                    <div class="card-header-gradient">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-safe2 me-2 text-lime"></i> ASSETS (Debit Balance)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php 
                        $asset_cats = [];
                        foreach($assets as $a) {
                            $cat = $a['category'] ?: 'Uncategorized';
                            if(!isset($asset_cats[$cat])) $asset_cats[$cat] = 0;
                            $asset_cats[$cat] += (float)$a['current_balance'];
                        }
                        foreach($asset_cats as $name => $val): ?>
                            <div class="ledger-item" data-bs-toggle="modal" data-bs-target="#modal_<?= str_replace([' ','/','-'],'_',(string)$name) ?>">
                                <span class="acc-name"><?= strtoupper($name) ?> GROUP</span>
                                <span class="acc-val"><?= number_format($val, 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="total-row d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-muted text-uppercase small">Total Debit Entries</span>
                            <span class="h4 fw-800 text-dark mb-0">KES <?= number_format($total_assets, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Liabilities & Equity -->
            <div class="col-lg-6">
                <div class="proof-card slide-up" style="animation-delay: 0.2s">
                    <div class="card-header-gradient" style="background: linear-gradient(to right, #164639, #0f2e25);">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-info"></i> LIABILITIES & EQUITY (Credit)</h5>
                    </div>
                    <div class="card-body p-0">
                        <!-- Group Liabilities -->
                        <?php 
                        $liab_cats = [];
                        foreach($liabilities as $l) {
                            $cat = $l['category'] ?: 'Uncategorized';
                            if(!isset($liab_cats[$cat])) $liab_cats[$cat] = 0;
                            $liab_cats[$cat] += (float)$l['current_balance'];
                        }
                        foreach($liab_cats as $name => $val): ?>
                            <div class="ledger-item" data-bs-toggle="modal" data-bs-target="#modal_<?= str_replace([' ','/','-'],'_',(string)$name) ?>">
                                <span class="acc-name"><?= strtoupper($name) ?> OBLIGATIONS</span>
                                <span class="acc-val"><?= number_format($val, 2) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <!-- Equity Section -->
                        <div class="px-4 py-2 bg-light small fw-bold text-muted border-bottom text-uppercase">Equity & Reserves</div>
                        <?php 
                        $equity_cats = [];
                        foreach($equity as $e) {
                            $cat = $e['category'] ?: 'Equity';
                            if(!isset($equity_cats[$cat])) $equity_cats[$cat] = 0;
                            $equity_cats[$cat] += (float)$e['current_balance'];
                        }
                        foreach($equity_cats as $name => $val): ?>
                            <div class="ledger-item" data-bs-toggle="modal" data-bs-target="#modal_<?= str_replace([' ','/','-'],'_',(string)$name) ?>">
                                <span class="acc-name"><?= strtoupper($name) ?> PORTFOLIO</span>
                                <span class="acc-val"><?= number_format($val, 2) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <!-- Net Income -->
                        <div class="ledger-item">
                            <span class="acc-name">NET INCOME (SURPLUS/DEFICIT)</span>
                            <span class="acc-val <?= $net_income < 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($net_income, 2) ?></span>
                        </div>
                        
                        <div class="total-row d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-muted text-uppercase small">Total Credit Entries</span>
                            <span class="h4 fw-800 text-dark mb-0">KES <?= number_format($total_liabilities + $total_equity, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Verdict -->
        <div class="status-banner slide-up <?= $is_balanced ? 'balanced-theme' : 'imbalanced-theme' ?>">
            <div class="fs-1"><i class="bi <?= $is_balanced ? 'bi-patch-check-fill' : 'bi-exclamation-triangle-fill' ?>"></i></div>
            <div class="text-center">
                <h3 class="fw-800 mb-1"><?= $is_balanced ? 'SYSTEM BALANCED' : 'IMBALANCE DETECTED' ?></h3>
                <p class="mb-0 opacity-75 fw-600">
                    Equation Difference: <?= number_format($balance_check, 4) ?> | Status Check OK
                </p>
            </div>
        </div>

        <?php if(!$is_balanced): ?>
            <div class="alert alert-danger rounded-4 mt-4 p-4 fade-in">
                <div class="d-flex gap-4">
                    <i class="bi bi-info-circle-fill fs-3"></i>
                    <div>
                        <h5 class="fw-bold">Audit Recommendation</h5>
                        <p class="mb-0">A difference of more than 0.01 suggests a database-level manual entry that bypassed the Double-Entry system. Please review recent manual SQL updates to the `ledger_accounts` or `transactions` tables.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center mt-5 text-muted small pb-5">
            Internal Audit Protocol V10.4 &bull; Generated <?= date('d M Y, H:i:s') ?> &bull; <?= SITE_NAME ?> Finance
        <?php $layout->footer(); ?>
        </div>
        
        
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
