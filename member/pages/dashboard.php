<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');

require_member();

$layout = LayoutManager::create('member');
$member_id = (int) $_SESSION['member_id'];

$stmt = $conn->prepare("SELECT full_name, member_reg_no FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member_data = $stmt->get_result()->fetch_assoc();
$member_name = htmlspecialchars($member_data['full_name'] ?? 'Member');
$reg_no      = htmlspecialchars($member_data['member_reg_no'] ?? 'N/A');
$_SESSION['reg_no'] = $reg_no;
$stmt->close();

require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine        = new FinancialEngine($conn);
$balances      = $engine->getBalances($member_id);
$cur_bal       = $balances['wallet'];
$total_savings = $balances['savings'];
$total_shares  = $balances['shares'];
$active_loans  = $balances['loans'];

$chart_labels = [];
$chart_data   = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start    = date('Y-m-01', strtotime("-$i months"));
    $month_end      = date('Y-m-t',  strtotime("-$i months"));
    $chart_labels[] = date('M', strtotime($month_start));
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

$recent_txn = [];
$stmt = $conn->prepare("SELECT transaction_type, amount, created_at, reference_no FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT 6");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $recent_txn[] = $row;
$stmt->close();

$first_name = htmlspecialchars(explode(' ', $member_name)[0]);
$pageTitle  = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= $member_name ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
    *, *::before, *::after { box-sizing: border-box; }

    :root {
        --forest:       #0F392B;
        --forest-mid:   #1a5c43;
        --lime:         #A3E635;
        --lime-soft:    rgba(163,230,53,0.1);
        --body-bg:      #F4F8F6;
        --card-bg:      #ffffff;
        --card-border:  #E8F0ED;
        --text-dark:    #0F392B;
        --text-body:    #5a7a6e;
        --text-muted:   #a0b8b0;
        --radius:       20px;
        --radius-sm:    13px;
    }
    [data-bs-theme="dark"] {
        --body-bg:    #0B1E17;
        --card-bg:    #0d2018;
        --card-border: rgba(255,255,255,0.07);
        --text-dark:  #e0f0ea;
        --text-body:  #7a9e8e;
        --text-muted: #4a6a5e;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: var(--body-bg);
        color: var(--text-dark);
    }

    /* ─── Layout ─── */
    .main-content-wrapper {
        margin-left: 272px;
        transition: margin-left 0.28s cubic-bezier(0.4,0,0.2,1);
        min-height: 100vh;
    }
    body.sb-collapsed .main-content-wrapper { margin-left: 76px; }
    @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; } }

    .dash-inner { padding: 28px 28px 40px; }
    @media (max-width: 768px) { .dash-inner { padding: 18px 14px 32px; } }

    /* ─── Page Header ─── */
    .dash-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 28px;
        gap: 16px;
        flex-wrap: wrap;
    }
    .dash-greeting h1 {
        font-size: 1.55rem;
        font-weight: 800;
        color: var(--text-dark);
        letter-spacing: -0.4px;
        margin: 0 0 4px;
    }
    .dash-greeting p {
        font-size: 0.8rem;
        color: var(--text-body);
        font-weight: 600;
        margin: 0;
    }
    .dash-greeting p span { color: var(--forest); font-weight: 800; }
    [data-bs-theme="dark"] .dash-greeting p span { color: var(--lime); }

    .dash-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-dash-action {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        border-radius: var(--radius-sm);
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 800;
        text-decoration: none;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }
    .btn-dash-primary {
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff;
        box-shadow: 0 4px 14px rgba(15,57,43,0.25);
    }
    .btn-dash-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15,57,43,0.35); color: #fff; }
    .btn-dash-outline {
        background: var(--card-bg);
        color: var(--text-dark);
        border: 1.5px solid var(--card-border);
    }
    .btn-dash-outline:hover { border-color: #A3E635; background: var(--lime-soft); color: var(--forest); }

    /* ─── Stat Cards ─── */
    .stat-card {
        border-radius: var(--radius);
        padding: 24px;
        height: 100%;
        transition: transform 0.22s, box-shadow 0.22s;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover { transform: translateY(-3px); }

    /* Green Hero */
    .stat-card-forest {
        background: linear-gradient(135deg, #0F392B 0%, #1a5c43 100%);
        color: #fff;
        box-shadow: 0 8px 28px rgba(15,57,43,0.25);
    }
    .stat-card-forest::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 160px; height: 160px;
        background: radial-gradient(circle, rgba(163,230,53,0.15) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }

    /* Lime */
    .stat-card-lime {
        background: linear-gradient(135deg, #A3E635 0%, #bde32a 100%);
        color: #0F392B;
        box-shadow: 0 8px 28px rgba(163,230,53,0.3);
    }

    /* White/Neutral */
    .stat-card-neutral {
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        box-shadow: 0 4px 16px rgba(15,57,43,0.05);
    }

    /* Stat icon */
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 13px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        margin-bottom: 16px;
    }
    .stat-card-forest .stat-icon { background: rgba(163,230,53,0.15); color: #A3E635; }
    .stat-card-lime   .stat-icon { background: rgba(15,57,43,0.1); color: #0F392B; }
    .stat-card-neutral .stat-icon { background: #F0F7F4; color: var(--forest); }
    [data-bs-theme="dark"] .stat-card-neutral .stat-icon { background: rgba(163,230,53,0.07); color: #A3E635; }

    .stat-label {
        font-size: 0.63rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        opacity: 0.6;
        margin-bottom: 6px;
    }
    .stat-value {
        font-size: 1.65rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        line-height: 1;
        margin-bottom: 14px;
    }
    .stat-card-neutral .stat-value { color: var(--text-dark); }
    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 100px;
    }
    .stat-card-forest .stat-badge { background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.85); }
    .stat-card-lime   .stat-badge { background: rgba(15,57,43,0.1); color: #0F392B; }
    .stat-card-neutral .stat-badge-danger { background: #FEE2E2; color: #dc2626; }

    /* Loan progress */
    .loan-progress-wrap { margin-top: 14px; }
    .loan-progress-bar {
        height: 5px;
        background: #E8F0ED;
        border-radius: 100px;
        overflow: hidden;
        margin-bottom: 6px;
    }
    [data-bs-theme="dark"] .loan-progress-bar { background: rgba(255,255,255,0.08); }
    .loan-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #dc2626, #f87171);
        border-radius: 100px;
        transition: width 1s ease;
    }
    .loan-progress-meta { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); }

    /* ─── Panel Card (Chart + Transactions) ─── */
    .panel-card {
        background: var(--card-bg);
        border: 1.5px solid var(--card-border);
        border-radius: var(--radius);
        padding: 24px;
        height: 100%;
        box-shadow: 0 4px 16px rgba(15,57,43,0.04);
    }
    .panel-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        gap: 12px;
    }
    .panel-card-title {
        font-size: 0.92rem;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0;
    }
    .panel-select {
        background: var(--body-bg);
        border: 1.5px solid var(--card-border);
        border-radius: 10px;
        padding: 5px 10px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-body);
        outline: none;
        cursor: pointer;
    }

    .chart-container { position: relative; height: 280px; width: 100%; }

    /* ─── Transactions ─── */
    .txn-list { margin: 0; padding: 0; list-style: none; }
    .txn-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 11px 0;
        border-bottom: 1px solid var(--card-border);
        gap: 10px;
    }
    .txn-item:last-child { border-bottom: none; }
    .txn-icon {
        width: 36px; height: 36px;
        border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .txn-icon-in  { background: #D1FAE5; color: #059669; }
    .txn-icon-out { background: #FEE2E2; color: #dc2626; }
    .txn-name { font-size: 0.83rem; font-weight: 700; color: var(--text-dark); text-transform: capitalize; margin-bottom: 2px; }
    .txn-date { font-size: 0.68rem; font-weight: 600; color: var(--text-muted); }
    .txn-amount-in  { font-size: 0.88rem; font-weight: 800; color: #059669; }
    .txn-amount-out { font-size: 0.88rem; font-weight: 800; color: #dc2626; }
    .txn-ref {
        font-size: 0.62rem;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--body-bg);
        padding: 2px 7px;
        border-radius: 6px;
        display: block;
        margin-top: 2px;
        text-align: right;
    }

    .btn-view-all {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
        padding: 10px;
        border-radius: var(--radius-sm);
        background: var(--body-bg);
        border: 1.5px solid var(--card-border);
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--text-body);
        text-decoration: none;
        margin-top: 14px;
        transition: all 0.2s;
    }
    .btn-view-all:hover { border-color: #A3E635; color: var(--forest); background: var(--lime-soft); }

    /* ─── Receipt Modal ─── */
    .receipt-modal .modal-content {
        border: none;
        border-radius: var(--radius);
        box-shadow: 0 28px 56px rgba(0,0,0,0.18);
        overflow: hidden;
    }
    .receipt-header {
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff;
        padding: 40px 28px 32px;
        text-align: center;
        position: relative;
    }
    .receipt-header::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 160px; height: 160px;
        background: radial-gradient(circle, rgba(163,230,53,0.12) 0%, transparent 65%);
        border-radius: 50%;
    }
    .receipt-header-icon {
        width: 64px; height: 64px;
        border-radius: 18px;
        background: rgba(163,230,53,0.15);
        border: 1px solid rgba(163,230,53,0.25);
        display: flex; align-items: center; justify-content: center;
        color: #A3E635;
        font-size: 1.6rem;
        margin: 0 auto 14px;
        position: relative; z-index: 1;
    }
    .receipt-header h4 { font-weight: 800; margin: 0 0 5px; font-size: 1.1rem; position: relative; z-index: 1; }
    .receipt-header p  { opacity: 0.55; font-size: 0.78rem; margin: 0; position: relative; z-index: 1; }

    .receipt-body { padding: 28px; background: var(--card-bg); }
    .receipt-amount { font-size: 2rem; font-weight: 800; color: #059669; letter-spacing: -0.5px; margin-bottom: 6px; }
    .receipt-stamp {
        display: inline-block;
        border: 2.5px solid #059669;
        color: #059669;
        padding: 3px 14px;
        border-radius: 8px;
        font-weight: 800;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        transform: rotate(-12deg);
        margin-top: 6px;
    }
    .receipt-divider { border: none; border-top: 2px dashed var(--card-border); margin: 20px 0; }
    .receipt-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        font-size: 0.83rem;
    }
    .receipt-row-label { color: var(--text-body); font-weight: 600; }
    .receipt-row-value { font-weight: 800; color: var(--text-dark); }
    .btn-receipt-close {
        width: 100%;
        padding: 12px;
        border-radius: var(--radius-sm);
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff;
        border: none;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.88rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 20px;
    }
    .btn-receipt-close:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(15,57,43,0.3); }
    </style>
</head>
<body>

<div class="d-flex">

    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper">

        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="dash-inner">

            <!-- Page Header -->
            <div class="dash-header">
                <div class="dash-greeting">
                    <h1>Hi, <?= $first_name ?>! 👋</h1>
                    <p>Member No: <span><?= $reg_no ?></span> &nbsp;·&nbsp; Here's your financial overview</p>
                </div>
                <div class="dash-actions">
                    <?php if ($cur_bal > 0): ?>
                    <a href="withdraw.php" class="btn-dash-action btn-dash-outline">
                        <i class="bi bi-arrow-up-right"></i> Withdraw
                    </a>
                    <?php endif; ?>
                    <a href="mpesa_request.php" class="btn-dash-action btn-dash-primary">
                        <i class="bi bi-plus-lg"></i> New Deposit
                    </a>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">

                <div class="col-xl-4 col-md-6">
                    <div class="stat-card stat-card-forest">
                        <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-label">Total Savings</div>
                        <div class="stat-value">KES <?= ksh($total_savings) ?></div>
                        <span class="stat-badge"><i class="bi bi-check-circle-fill"></i> Active Account</span>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6">
                    <div class="stat-card stat-card-lime">
                        <div class="stat-icon"><i class="bi bi-pie-chart-fill"></i></div>
                        <div class="stat-label">Share Capital</div>
                        <div class="stat-value">KES <?= ksh($total_shares) ?></div>
                        <span class="stat-badge"><i class="bi bi-award-fill"></i> Good Standing</span>
                    </div>
                </div>

                <div class="col-xl-4 col-md-12">
                    <div class="stat-card stat-card-neutral">
                        <div class="stat-icon"><i class="bi bi-bank"></i></div>
                        <div class="stat-label">Active Loans</div>
                        <div class="stat-value" style="color: <?= $active_loans > 0 ? '#dc2626' : '#059669' ?>;">
                            KES <?= ksh($active_loans) ?>
                        </div>
                        <?php
                        $loan_limit   = 500000;
                        $loan_percent = $loan_limit > 0 ? min(100, ($active_loans / $loan_limit) * 100) : 0;
                        ?>
                        <div class="loan-progress-wrap">
                            <div class="loan-progress-bar">
                                <div class="loan-progress-fill" style="width:<?= $loan_percent ?>%"></div>
                            </div>
                            <div class="loan-progress-meta"><?= number_format($loan_percent, 0) ?>% of loan limit used</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Chart + Transactions -->
            <div class="row g-3">

                <div class="col-lg-8">
                    <div class="panel-card">
                        <div class="panel-card-head">
                            <h6 class="panel-card-title">Savings Growth</h6>
                            <select class="panel-select">
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
                    <div class="panel-card">
                        <div class="panel-card-head">
                            <h6 class="panel-card-title">Recent Activity</h6>
                            <span style="font-size:0.68rem;font-weight:700;color:var(--text-muted);">Last 6 txns</span>
                        </div>

                        <?php if (empty($recent_txn)): ?>
                            <div class="text-center py-5" style="color:var(--text-muted);">
                                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.3;"></i>
                                <p style="font-size:0.78rem;font-weight:600;margin:0;">No transactions yet</p>
                            </div>
                        <?php else: ?>
                            <ul class="txn-list">
                            <?php foreach ($recent_txn as $t):
                                $is_in = in_array($t['transaction_type'], ['deposit','income','contribution','revenue_inflow']);
                            ?>
                            <li class="txn-item">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="txn-icon <?= $is_in ? 'txn-icon-in' : 'txn-icon-out' ?>">
                                        <i class="bi <?= $is_in ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="txn-name"><?= $t['transaction_type'] ?></div>
                                        <div class="txn-date"><?= date('d M, h:i A', strtotime($t['created_at'])) ?></div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="<?= $is_in ? 'txn-amount-in' : 'txn-amount-out' ?>">
                                        <?= $is_in ? '+' : '-' ?> <?= ksh($t['amount']) ?>
                                    </div>
                                    <span class="txn-ref"><?= strtoupper($t['reference_no']) ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <a href="transactions.php" class="btn-view-all">
                            <i class="bi bi-list-ul"></i> View All Transactions
                        </a>
                    </div>
                </div>

            </div>

        </div>

        <!-- Receipt Modal -->
        <div class="modal fade receipt-modal" id="receiptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
                <div class="modal-content">
                    <div class="receipt-header">
                        <div class="receipt-header-icon"><i class="bi bi-check-lg"></i></div>
                        <h4>Transaction Successful</h4>
                        <p>Receipt generated automatically</p>
                    </div>
                    <div class="receipt-body">
                        <div class="text-center mb-2">
                            <div class="receipt-amount" id="receiptAmount">KES 0.00</div>
                            <div class="receipt-stamp">Verified</div>
                        </div>
                        <hr class="receipt-divider">
                        <div class="receipt-row">
                            <span class="receipt-row-label">Receipt Number</span>
                            <span class="receipt-row-value" id="receiptNo">—</span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-row-label">Account</span>
                            <span class="receipt-row-value" id="receiptAccount">—</span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-row-label">Reference ID</span>
                            <span class="receipt-row-value" id="receiptRef">—</span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-row-label">Date & Time</span>
                            <span class="receipt-row-value" id="receiptDate"><?= date('d M, Y H:i:s') ?></span>
                        </div>
                        <hr class="receipt-divider">
                        <button type="button" class="btn-receipt-close" data-bs-dismiss="modal">
                            Close Receipt
                        </button>
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
// ─── Chart ───
const ctx     = document.getElementById('savingsChart').getContext('2d');
const isDark  = document.documentElement.getAttribute('data-bs-theme') === 'dark';
const gridClr = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(15,57,43,0.05)';
const tickClr = isDark ? '#4a7264' : '#7a9e8e';

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Savings (KES)',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: (ctx) => {
                const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 280);
                g.addColorStop(0, '#0F392B');
                g.addColorStop(1, '#1a5c43');
                return g;
            },
            borderRadius: 8,
            barThickness: 28,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0F392B',
                titleColor: '#A3E635',
                bodyColor: '#fff',
                padding: 12,
                cornerRadius: 10,
                titleFont: { family: "'Plus Jakarta Sans', sans-serif", weight: '700' },
                bodyFont:  { family: "'Plus Jakarta Sans', sans-serif" },
                callbacks: {
                    label: (ctx) => ' KES ' + ctx.parsed.y.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                grid: { color: gridClr, drawBorder: false },
                ticks: { color: tickClr, font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 } }
            },
            x: {
                grid: { display: false },
                ticks: { color: tickClr, font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 } }
            }
        }
    }
});

// ─── Session Flash ───
<?php if (isset($_SESSION['success'])): ?>
    showToast && showToast(<?= json_encode($_SESSION['success']) ?>, 'success');
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    showToast && showToast(<?= json_encode($_SESSION['error']) ?>, 'error');
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

// ─── Receipt Modal Trigger ───
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
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('receiptAmount').innerText  = 'KES <?= number_format((float)$t_data['amount'], 2) ?>';
    document.getElementById('receiptNo').innerText      = '<?= $t_data['reference_no'] ?>';
    document.getElementById('receiptAccount').innerText = '<?= ucfirst($t_data['transaction_type']) ?> Account';
    document.getElementById('receiptRef').innerText     = 'TXN-<?= strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) ?>';
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
});
<?php endif; endif; ?>
</script>
</body>
</html>