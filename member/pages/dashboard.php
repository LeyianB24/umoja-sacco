<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// Prevent caching of balance data
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
// usms/member/pages/dashboard.php

// 1. CONFIG & AUTH
// Validate Login
require_member();

// Initialize Layout Manager
$layout = LayoutManager::create('member');

$member_id = (int) $_SESSION['member_id'];

// Fetch Member Details (excluding legacy account_balance)
$stmt = $conn->prepare("SELECT full_name, member_reg_no FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member_data = $stmt->get_result()->fetch_assoc();
$member_name = htmlspecialchars($member_data['full_name'] ?? 'Member');
$reg_no = htmlspecialchars($member_data['member_reg_no'] ?? 'N/A');
$_SESSION['reg_no'] = $reg_no; // Ensure session is updated
$stmt->close();

// 2. FETCH BALANCES VIA FINANCIAL ENGINE
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine = new FinancialEngine($conn);
$balances = $engine->getBalances($member_id);

$cur_bal       = $balances['wallet'];
$total_savings = $balances['savings'];
$total_shares  = $balances['shares'];
$active_loans  = $balances['loans'];

// Chart Data (Last 6 Months)
$chart_labels = [];
$chart_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end   = date('Y-m-t', strtotime("-$i months"));
    $chart_labels[] = date('M', strtotime($month_start));
    
    // Monthly Savings Growth via Golden Ledger
    $sql = "SELECT COALESCE(SUM(le.credit - le.debit),0) 
            FROM ledger_entries le
            JOIN ledger_accounts la ON le.account_id = la.account_id
            WHERE la.member_id = ? AND la.category = 'savings'
            AND le.created_at BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $member_id, $month_start, $month_end);
    $stmt->execute();
    $chart_data[] = (float) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
}

// Recent Transactions
$recent_txn = [];
$stmt = $conn->prepare("SELECT transaction_type, amount, created_at, reference_no FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) $recent_txn[] = $row;
$stmt->close();

function ksh($v) { return number_format((float)($v ?? 0), 2); }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= $member_name ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --hope-bg: #f8f9fa;
            --hope-green: #0F392B;
            --hope-lime: #D0F764;
            --hope-text: #1F2937;
            --card-radius: 24px;
        }
        
        [data-bs-theme="dark"] {
            --hope-bg: #0b1210;
            --hope-text: #f9fafb;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--hope-bg); 
            color: var(--hope-text);
        }

        /* Card Styles */
        .hope-card {
            background: var(--bs-body-bg);
            border-radius: var(--card-radius);
            border: 1px solid var(--bs-border-color-translucent);
            padding: 1.5rem;
            height: 100%;
            transition: transform 0.2s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }
        .hope-card:hover { transform: translateY(-3px); }

        /* Hero Card (Green) */
        .card-hero {
            background: var(--hope-green);
            color: white;
            border: none;
            position: relative;
            overflow: hidden;
        }
        .card-hero .icon-box {
            background: rgba(255,255,255,0.1);
            color: var(--hope-lime);
        }

        /* Accent Card (Lime) */
        .card-accent {
            background: var(--hope-lime);
            color: var(--hope-green);
            border: none;
        }
        .card-accent .icon-box {
            background: rgba(15, 57, 43, 0.1);
            color: var(--hope-green);
        }

        .icon-box {
            width: 48px; height: 48px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Transaction List */
        .txn-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--bs-border-color-translucent);
        }
        .txn-item:last-child { border-bottom: none; }
        
        .txn-icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: var(--bs-tertiary-bg);
            display: flex; align-items: center; justify-content: center;
            margin-right: 1rem;
        }

        .main-content-wrapper { margin-left: 280px; transition: margin-left 0.3s ease; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; } }

        /* Receipt Modal Premium Styling */
        .receipt-modal .modal-content {
            border: none;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .receipt-header {
            background: var(--hope-green);
            color: white;
            border-radius: 24px 24px 0 0;
            padding: 40px 20px;
            text-align: center;
        }
        .receipt-body {
            padding: 30px;
            background: #fff;
        }
        .receipt-dashed-line {
            border-top: 2px dashed #e2e8f0;
            margin: 20px 0;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }
        .receipt-label { color: #64748b; }
        .receipt-value { font-weight: 600; color: #1e293b; }
        .receipt-stamp {
            border: 3px solid #39b54a;
            color: #39b54a;
            padding: 5px 15px;
            border-radius: 8px;
            display: inline-block;
            transform: rotate(-15deg);
            font-weight: 800;
            text-transform: uppercase;
            margin-top: 10px;
        }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="d-flex">
    
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper">
        
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="container-fluid px-4 py-4">
            
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <h1 class="fw-bold mb-1">Hi, <?= explode(' ', $member_name)[0] ?>! 👋</h1>
                    <p class="text-secondary mb-0">Member No: <span class="fw-bold "><?= $reg_no ?></span> | Financial Overview</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if($cur_bal > 0): ?>
                    <a href="withdraw.php" class="btn btn-dark rounded-pill px-4 py-2 d-none d-md-block" style="background: #1F2937; border:none;">
                        <i class="bi bi-arrow-up-right me-2"></i> Withdraw
                    </a>
                    <?php endif; ?>
                    <a href="mpesa_request.php" class="btn btn-dark rounded-pill px-4 py-2 d-none d-md-block" style="background: var(--hope-green); border:none;">
                        <i class="bi bi-plus-lg me-2"></i> New Deposit
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-5">
                
                <div class="col-xl-4 col-md-6">
                    <div class="hope-card card-hero">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <p class="small text-white-50 fw-bold text-uppercase mb-1">Total Savings</p>
                                <h2 class="fw-bold mb-0">KES <?= ksh($total_savings) ?></h2>
                            </div>
                            <div class="icon-box rounded-circle">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                        <div class="mt-auto">
                            <span class="badge bg-white bg-opacity-25 rounded-pill fw-normal px-3">
                                <i class="bi bi-arrow-up-short"></i> Active Account
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6">
                    <div class="hope-card card-accent">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <p class="small fw-bold text-uppercase mb-1" style="opacity: 0.7;">Share Capital</p>
                                <h2 class="fw-bold mb-0">KES <?= ksh($total_shares) ?></h2>
                            </div>
                            <div class="icon-box rounded-circle">
                                <i class="bi bi-pie-chart-fill"></i>
                            </div>
                        </div>
                        <div class="mt-auto fw-bold d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill"></i> Good Standing
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-12">
                    <div class="hope-card">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <p class="small text-secondary fw-bold text-uppercase mb-1">Active Loans</p>
                                <h2 class="fw-bold mb-0 text-danger">KES <?= ksh($active_loans) ?></h2>
                            </div>
                            <div class="icon-box bg-danger bg-opacity-10 text-danger rounded-circle">
                                <i class="bi bi-exclamation-circle"></i>
                            </div>
                        </div>
                        <?php 
                        $loan_limit = 500000; // Example limit
                        $loan_percent = $loan_limit > 0 ? min(100, ($active_loans / $loan_limit) * 100) : 0;
                        ?>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-danger" style="width: <?= $loan_percent ?>%"></div>
                        </div>
                        <div class="small text-secondary mt-2"><?= number_format((float)$loan_percent, 0) ?>% of limit used</div>
                    </div>
                </div>

            </div>

            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="hope-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Savings Growth</h5>
                            <select class="form-select form-select-sm w-auto rounded-pill border-0 bg-secondary bg-opacity-10">
                                <option>Last 6 Months</option>
                                <option>This Year</option>
                            </select>
                        </div>
                        <div class="chart-container">
                            <canvas id="savingsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="hope-card">
                        <h5 class="fw-bold mb-4">Recent Activity</h5>
                        
                        <?php if(empty($recent_txn)): ?>
                            <div class="text-center py-5 text-muted">No transactions yet.</div>
                        <?php else: foreach($recent_txn as $t): 
                            $is_in = in_array($t['transaction_type'], ['deposit', 'income', 'contribution', 'revenue_inflow']);
                            $color = $is_in ? 'text-success' : 'text-danger';
                            $icon  = $is_in ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
                        ?>
                            <div class="txn-item">
                                <div class="d-flex align-items-center">
                                    <div class="txn-icon <?= $is_in ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' ?>">
                                        <i class="bi <?= $icon ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-capitalize small"><?= $t['transaction_type'] ?></div>
                                        <div class="small text-secondary" style="font-size: 0.75rem;">
                                            <?= date('d M, h:i A', strtotime($t['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold <?= $color ?>"><?= $is_in ? '+' : '-' ?> <?= ksh($t['amount']) ?></div>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill" style="font-size: 0.65rem;">
                                        <?= strtoupper($t['reference_no']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>

                        <a href="transactions.php" class="btn btn-light w-100 rounded-pill mt-3 fw-bold text-secondary">
                            View All Transactions
                        </a>
                    </div>
                </div>

            </div>

        </div>

        <!-- Receipt Modal -->
        <div class="modal fade receipt-modal" id="receiptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="receipt-header">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill text-lime" style="font-size: 3rem;"></i>
                        </div>
                        <h4 class="fw-bold mb-1">Transaction Successful</h4>
                        <p class="opacity-75 mb-0">Receipt Generated Automatically</p>
                    </div>
                    <div class="receipt-body">
                        <div class="text-center mb-4">
                            <div class="h2 fw-bold mb-0 text-success" id="receiptAmount">KES 0.00</div>
                            <div class="receipt-stamp">Verified</div>
                        </div>
                        
                        <div class="receipt-dashed-line"></div>
                        
                        <div class="receipt-item">
                            <span class="receipt-label">Receipt Number:</span>
                            <span class="receipt-value" id="receiptNo">---</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Account:</span>
                            <span class="receipt-value" id="receiptAccount">---</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Reference ID:</span>
                            <span class="receipt-value" id="receiptRef">---</span>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Date & Time:</span>
                            <span class="receipt-value" id="receiptDate"><?= date('d M, Y H:i:s') ?></span>
                        </div>
                        
                        <div class="receipt-dashed-line"></div>
                        
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-dark rounded-pill px-5 fw-bold" data-bs-dismiss="modal">Close Receipt</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Chart Config
    const ctx = document.getElementById('savingsChart').getContext('2d');
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const colorText = isDark ? '#9CA3AF' : '#6B7280';
    const colorGrid = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Savings (KES)',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: '#0F392B',
                borderRadius: 6,
                barThickness: 24
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    grid: { color: colorGrid, drawBorder: false },
                    ticks: { color: colorText, font: {family: "'Plus Jakarta Sans', sans-serif"} }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: colorText, font: {family: "'Plus Jakarta Sans', sans-serif"} }
                }
            }
        }
    });

    // Check for general session notifications
    <?php if (isset($_SESSION['success'])): ?>
        showToast(<?= json_encode($_SESSION['success']) ?>, 'success');
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        showToast(<?= json_encode($_SESSION['error']) ?>, 'error');
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Check for success trigger in session (usually from a redirect after payment)
    <?php if (isset($_SESSION['payment_success_trigger'])): 
        $t_id = (int)$_SESSION['payment_success_trigger'];
        unset($_SESSION['payment_success_trigger']);
        $stmt_r = $conn->prepare("SELECT * FROM transactions WHERE transaction_id = ? AND member_id = ?");
        $stmt_r->bind_param("ii", $t_id, $member_id);
        $stmt_r->execute();
        $t_data = $stmt_r->get_result()->fetch_assoc();
        $stmt_r->close();
        if ($t_data):
    ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('receiptAmount').innerText = 'KES <?= number_format((float)$t_data['amount'], 2) ?>';
        document.getElementById('receiptNo').innerText = '<?= $t_data['reference_no'] ?>';
        document.getElementById('receiptAccount').innerText = '<?= ucfirst($t_data['transaction_type']) ?> Account';
        document.getElementById('receiptRef').innerText = 'TXN-<?= strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) ?>';
        
        const receiptModalElement = document.getElementById('receiptModal');
        if (receiptModalElement) {
            const receiptModal = new bootstrap.Modal(receiptModalElement);
            receiptModal.show();
        }
    });
    <?php endif; endif; ?>
</script>
</body>
</html>





