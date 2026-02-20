<?php
/**
 * accountant/reports.php
 * Super Enhanced Executive Dashboard
 * Features: Comparative Analytics, Modern UI, Mixed-Type Charts
 */

declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
ob_start(); // Buffer output to prevent header errors during PDF generation

// --- Dependencies ---
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/ReportGenerator.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Security & Layout ---
require_permission();
Auth::requireAdmin();
$layout = LayoutManager::create('admin');

// --- Helpers ---
if (!function_exists('ksh')) {
    function ksh($amount, $decimals = 2) {
        return 'KES ' . number_format((float)$amount, $decimals);
    }
}

if (!function_exists('calc_growth')) {
    function calc_growth($current, $previous) {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return (($current - $previous) / $previous) * 100;
    }
}

// --- 1. Filter Logic ---
$duration = $_GET['duration'] ?? 'monthly'; // Default to monthly for better initial view
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// Auto-calculate dates if not custom
if ($duration !== 'custom') {
    switch ($duration) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'weekly':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
            break;
        case 'monthly':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
            break;
        case '3months':
            $start_date = date('Y-m-d', strtotime('-3 months'));
            $end_date = date('Y-m-d');
            break;
        case 'yearly':
            $start_date = date('Y-01-01');
            $end_date = date('Y-12-31');
            break;
        case 'all':
            $start_date = '2020-01-01'; // Reasonable start
            $end_date = date('Y-m-d');
            break;
    }
}

// Calculate Previous Period for Comparison
$days_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
$prev_end_date = date('Y-m-d', strtotime($start_date . ' -1 day'));
$prev_start_date = date('Y-m-d', strtotime($prev_end_date . ' -' . $days_diff . ' days'));

// --- 2. Data Aggregation ---

$liquidity_names = "'Cash at Hand', 'M-Pesa Float', 'Bank Account', 'Paystack Clearing Account'";

function fetch_totals($conn, $start, $end, $liquidity_names) {
    $sql = "SELECT 
        SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.debit ELSE 0 END) as total_inflow,
        SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.credit ELSE 0 END) as total_outflow,
        SUM(CASE WHEN la.account_name = 'SACCO Expenses' THEN le.debit ELSE 0 END) as operational_expense
        FROM ledger_entries le
        JOIN ledger_accounts la ON le.account_id = la.account_id
        WHERE DATE(le.created_at) BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Current Period Totals
$totals = fetch_totals($conn, $start_date, $end_date, $liquidity_names);
$net_cash_flow = $totals['total_inflow'] - $totals['total_outflow'];

// Previous Period Totals (For Growth Stats)
$prev_totals = fetch_totals($conn, $prev_start_date, $prev_end_date, $liquidity_names);
$prev_net_cash = $prev_totals['total_inflow'] - $prev_totals['total_outflow'];

// Growth Percentages
$growth_inflow = calc_growth($totals['total_inflow'], $prev_totals['total_inflow']);
$growth_outflow = calc_growth($totals['total_outflow'], $prev_totals['total_outflow']);
$growth_expense = calc_growth($totals['operational_expense'], $prev_totals['operational_expense']);
$growth_net = calc_growth($net_cash_flow, $prev_net_cash);

// Monthly Trend Data
$trend_labels = []; $trend_in = []; $trend_out = []; $trend_net = [];
$monthly_data = []; 

$sql_trend = "SELECT 
    DATE_FORMAT(le.created_at, '%Y-%m') as month_str,
    DATE_FORMAT(le.created_at, '%b %Y') as display_date,
    SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.debit ELSE 0 END) as inflow,
    SUM(CASE WHEN la.account_name IN ($liquidity_names) THEN le.credit ELSE 0 END) as outflow
    FROM ledger_entries le 
    JOIN ledger_accounts la ON le.account_id = la.account_id
    WHERE DATE(le.created_at) BETWEEN ? AND ?
    GROUP BY month_str
    ORDER BY month_str ASC";

$stmt = $conn->prepare($sql_trend);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_trend = $stmt->get_result();

while($row = $res_trend->fetch_assoc()) {
    $trend_labels[] = $row['display_date'];
    $trend_in[]     = (float)$row['inflow'];
    $trend_out[]    = (float)$row['outflow'];
    $trend_net[]    = (float)$row['inflow'] - (float)$row['outflow'];
    $monthly_data[] = $row;
}

// Inflow Distribution
$inflow_dist = array_fill_keys(['Deposits', 'Repayments', 'Shares', 'Welfare', 'Revenue', 'Wallet', 'Investments', 'Other'], 0);
$sql_dist = "SELECT la.category, SUM(le.credit) as val 
             FROM ledger_entries le
             JOIN ledger_accounts la ON le.account_id = la.account_id
             WHERE DATE(le.created_at) BETWEEN ? AND ? 
             AND (la.account_type IN ('liability', 'equity', 'revenue') OR la.category IN ('loans', 'investments'))
             GROUP BY la.category";
$stmt = $conn->prepare($sql_dist);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_dist = $stmt->get_result();

while($row = $res_dist->fetch_assoc()){
    $cat = strtolower($row['category'] ?? '');
    $val = (float)$row['val'];
    
    if($cat == 'savings') $inflow_dist['Deposits'] += $val;
    elseif($cat == 'loans') $inflow_dist['Repayments'] += $val;
    elseif($cat == 'shares') $inflow_dist['Shares'] += $val;
    elseif($cat == 'welfare') $inflow_dist['Welfare'] += $val;
    elseif(in_array($cat, ['income', 'revenue'])) $inflow_dist['Revenue'] += $val;
    elseif($cat == 'wallet') $inflow_dist['Wallet'] += $val;
    elseif($cat == 'investments') $inflow_dist['Investments'] += $val;
    else $inflow_dist['Other'] += $val;
}

// --- 3. Action Handling ---
$reportGen = new ReportGenerator($conn);
$balanceData = $reportGen->getBalanceSheetData($start_date, $end_date);

if (isset($_GET['action'])) {
    // Clean any prior output (whitespace, notices, HTML) to ensure valid file generation
    if (ob_get_length()) ob_clean();
    
    if ($_GET['action'] === 'export_pdf') {
        $reportGen->generatePDF("Financial Report (" . date('d M', strtotime($start_date)) . " - " . date('d M', strtotime($end_date)) . ")", $balanceData);
        exit;
    } elseif ($_GET['action'] === 'export_excel') {
        $reportGen->generateExcel($balanceData);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_all'])) {
    // [Keep existing mail logic, ensuring error handling is robust]
    $members = $conn->query("SELECT email, full_name FROM members WHERE status='active' AND email LIKE '%@%'");
    $sentCount = 0; $errCount = 0;
    
    // Generate PDF once
    $pdfContent = $reportGen->generatePDF("Performance Report", $balanceData, true);

    while ($m = $members->fetch_assoc()) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'leyianbeza24@gmail.com'; 
            $mail->Password = 'duzb mbqt fnsz ipkg'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom('leyianbeza24@gmail.com', 'Umoja Drivers Sacco');
            $mail->addAddress($m['email'], $m['full_name']);
            $mail->Subject = 'Executive Performance Report - ' . date('F Y');
            $mail->Body = "Dear {$m['full_name']},\n\nAttached is the latest financial performance report.\n\nRegards,\nUmoja Sacco Admin";
            $mail->addStringAttachment($pdfContent, 'Financial_Report.pdf');
            $mail->send();
            $sentCount++;
        } catch (Exception $e) { $errCount++; }
    }
    flash_set("Report sent to $sentCount members. Failed: $errCount", $errCount > 0 ? "warning" : "success");
    header("Location: reports.php");
    exit;
}

$pageTitle = "Executive Reports";
?>
<?php $layout->header($pageTitle); ?>
<body>
<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="main-content">
        <?php $layout->topbar($pageTitle ?? ''); ?>
    
    <style>
        .main-content { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 1.5rem; } }
        
        /* Filter Bar Refinement */
        .filter-bar { border-radius: 20px; padding: 20px; border: 1px solid var(--glass-border); background: white; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        
        /* Trend Badges */
        .hp-trend { font-size: 0.75rem; font-weight: 800; padding: 6px 12px; border-radius: 50px; display: inline-flex; align-items: center; gap: 4px; }
        .hp-trend-up { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .hp-trend-down { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

        .stat-card-dark { background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%); color: white; }
        .stat-card-accent { background: var(--lime); color: var(--forest); }
        
        .chart-container { position: relative; height: 350px; width: 100%; }
        
        @media print {
            .sidebar, .no-print, .btn, .filter-bar, .hp-hero { display: none !important; }
            .main-content { margin: 0; padding: 0; }
            .glass-card { border: 1px solid #ddd; box-shadow: none; background: white !important; }
        }
    </style>

</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="container-fluid p-0">
        <div class="hp-hero">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Executive Analytics</span>
                    <h1 class="display-4 fw-800 mb-2">Financial Insights.</h1>
                    <p class="opacity-75 fs-5">Track liquidity, growth, and operational efficiency. Real-time data visualization meets <span class="text-lime fw-bold">exclusive intelligence</span>.</p>
                </div>
                <div class="col-md-5 text-end d-none d-lg-block">
                    <div class="d-flex gap-2 justify-content-end no-print mb-3">
                        <div class="dropdown">
                            <button class="btn btn-white bg-opacity-10 text-white border-white border-opacity-25 shadow-sm dropdown-toggle fw-bold rounded-pill px-4" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-file-earmark-arrow-down me-2 text-lime"></i> Export
                            </button>
                            <ul class="dropdown-menu shadow-lg border-0">
                                <li><a class="dropdown-item" href="?action=export_pdf&duration=<?= $duration ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"><i class="bi bi-file-pdf text-danger me-2"></i> PDF Document</a></li>
                                <li><a class="dropdown-item" href="?action=export_excel&duration=<?= $duration ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"><i class="bi bi-file-excel text-success me-2"></i> Excel Sheet</a></li>
                            </ul>
                        </div>
                        <form method="POST" onsubmit="return confirm('Email this report to all members?');">
                            <button type="submit" name="send_to_all" class="btn btn-lime shadow-sm fw-bold rounded-pill px-4">
                                <i class="bi bi-send-fill me-2"></i> Email All
                            </button>
                        </form>
                    </div>
                    <div class="d-inline-block text-start p-3 rounded-4 bg-white bg-opacity-10 backdrop-blur">
                        <div class="small opacity-75">Active Period</div>
                        <div class="fw-bold mb-0 text-lime"><?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?></div>
                    </div>
                </div>
            </div>
        </div>

            <div class="filter-bar d-flex flex-wrap align-items-center gap-3 no-print">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-funnel text-muted"></i>
                    <span class="fw-bold small text-uppercase text-muted">Filter:</span>
                </div>
                <form method="GET" class="d-flex flex-wrap flex-grow-1 gap-2 align-items-center" id="filterForm">
                    <div style="min-width: 150px;">
                        <select name="duration" class="form-select" onchange="toggleDateInputs(this.value)">
                            <option value="today" <?= $duration === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="weekly" <?= $duration === 'weekly' ? 'selected' : '' ?>>This Week</option>
                            <option value="monthly" <?= $duration === 'monthly' ? 'selected' : '' ?>>This Month</option>
                            <option value="3months" <?= $duration === '3months' ? 'selected' : '' ?>>Last 3 Months</option>
                            <option value="yearly" <?= $duration === 'yearly' ? 'selected' : '' ?>>This Year</option>
                            <option value="all" <?= $duration === 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="custom" <?= $duration === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div id="customDateRange" class="d-flex gap-2 <?= $duration !== 'custom' ? 'd-none' : '' ?>">
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                        <span class="align-self-center text-muted">-</span>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                    </div>

                    <button type="submit" class="btn btn-forest ms-auto">Apply Filter</button>
                    <a href="reports.php" class="btn btn-light text-muted border">Reset</a>
                </form>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="glass-stat stat-card-dark h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-white bg-opacity-10 text-lime p-3 rounded-4 fs-3">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <?php $trend = $growth_inflow >= 0 ? 'hp-trend-up' : 'hp-trend-down'; $icon = $growth_inflow >= 0 ? 'bi-arrow-up' : 'bi-arrow-down'; ?>
                            <span class="hp-trend <?= $trend ?>">
                                <i class="bi <?= $icon ?>"></i> <?= number_format(abs($growth_inflow), 1) ?>%
                            </span>
                        </div>
                        <div>
                            <h2 class="fw-800 mb-0">KES <?= number_format((float)($totals['total_inflow'] ?? 0)) ?></h2>
                            <p class="text-white opacity-50 small fw-800 text-uppercase mb-0">Total Inflow</p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="glass-stat h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-forest bg-opacity-10 text-forest p-3 rounded-4 fs-3">
                                <i class="bi bi-graph-down-arrow"></i>
                            </div>
                            <?php $trend = $growth_outflow <= 0 ? 'hp-trend-up' : 'hp-trend-down'; $icon = $growth_outflow >= 0 ? 'bi-arrow-up' : 'bi-arrow-down'; ?>
                            <span class="hp-trend <?= $trend ?>">
                                <i class="bi <?= $icon ?>"></i> <?= number_format(abs($growth_outflow), 1) ?>%
                            </span>
                        </div>
                        <div>
                            <h2 class="fw-800 mb-0 text-forest">KES <?= number_format((float)($totals['total_outflow'] ?? 0)) ?></h2>
                            <p class="text-muted small fw-800 text-uppercase mb-0">Total Outflow</p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="glass-stat stat-card-accent h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-forest bg-opacity-10 text-forest p-3 rounded-4 fs-3">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <span class="hp-badge" style="background: var(--forest); color: var(--lime);">Net Position</span>
                        </div>
                        <div>
                            <h2 class="fw-800 mb-0">KES <?= number_format((float)($net_cash_flow ?? 0)) ?></h2>
                            <p class="text-forest opacity-50 small fw-800 text-uppercase mb-0">Net Cash Flow</p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="glass-stat h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-forest bg-opacity-10 text-forest p-3 rounded-4 fs-3">
                                <i class="bi bi-receipt"></i>
                            </div>
                            <?php $trend = $growth_expense <= 0 ? 'hp-trend-up' : 'hp-trend-down'; ?>
                             <span class="hp-trend <?= $trend ?>">
                                <i class="bi <?= $growth_expense >= 0 ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i> <?= number_format(abs($growth_expense), 1) ?>%
                            </span>
                        </div>
                        <div>
                            <h2 class="fw-800 mb-0 text-forest">KES <?= number_format((float)($totals['operational_expense'] ?? 0)) ?></h2>
                            <p class="text-muted small fw-800 text-uppercase mb-0">Op. Expenses</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="glass-card h-100">
                        <h5 class="fw-800 mb-4 text-forest">Cash Flow Trends</h5>
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="glass-card h-100 text-center">
                        <h5 class="fw-800 mb-4 text-forest text-start">Inflow Sources</h5>
                        <div style="height: 220px; position: relative;">
                            <canvas id="sourceChart"></canvas>
                        </div>
                        <div class="mt-4 small text-start">
                            <?php 
                            $top_sources = $inflow_dist;
                            arsort($top_sources);
                            $top_sources = array_slice($top_sources, 0, 3);
                            foreach($top_sources as $k => $v): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted"><i class="bi bi-circle-fill me-2 text-lime" style="font-size: 10px;"></i><?= $k ?></span>
                                <span class="fw-800 text-forest">KES <?= number_format($v) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card p-0 overflow-hidden mb-5">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-light bg-opacity-10">
                    <h5 class="fw-800 mb-0 text-forest">Monthly Breakdown</h5>
                    <button class="btn btn-sm btn-white border rounded-pill px-3 shadow-sm" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print Report</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Month</th>
                                <th class="text-end">Total Inflow</th>
                                <th class="text-end">Total Outflow</th>
                                <th class="text-end">Net Change</th>
                                <th class="text-end pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($monthly_data)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No data available.</td></tr>
                            <?php else: foreach($monthly_data as $m): 
                                $net = $m['inflow'] - $m['outflow'];
                                $status_cls = $net >= 0 ? 'bg-success text-success' : 'bg-danger text-danger';
                                $status_lbl = $net >= 0 ? 'Positive' : 'Deficit';
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold "><?= $m['display_date'] ?></td>
                                <td class="text-end font-monospace text-success">+ <?= number_format((float)$m['inflow']) ?></td>
                                <td class="text-end font-monospace text-danger">- <?= number_format((float)$m['outflow']) ?></td>
                                <td class="text-end font-monospace fw-bold"><?= ksh($net) ?></td>
                                <td class="text-end pe-4">
                                    <span class="badge <?= $status_cls ?> bg-opacity-10 rounded-pill"><?= $status_lbl ?></span>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-center mt-5 mb-4 text-muted small no-print">
                &copy; <?= date('Y') ?> Umoja Sacco Management System. All rights reserved.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- Toggle Date Filter ---
    function toggleDateInputs(val) {
        const customDiv = document.getElementById('customDateRange');
        if (val === 'custom') {
            customDiv.classList.remove('d-none');
        } else {
            customDiv.classList.add('d-none');
            document.getElementById('filterForm').submit();
        }
    }

    // --- Chart Config ---
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    
    // Gradients
    let gradIn = ctxTrend.createLinearGradient(0, 0, 0, 400);
    gradIn.addColorStop(0, '#D0F35D');
    gradIn.addColorStop(1, 'rgba(208, 243, 93, 0.1)');

    let gradOut = ctxTrend.createLinearGradient(0, 0, 0, 400);
    gradOut.addColorStop(0, '#0F2E25');
    gradOut.addColorStop(1, 'rgba(15, 46, 37, 0.2)');

    new Chart(ctxTrend, {
        type: 'bar',
        data: {
            labels: <?= json_encode($trend_labels) ?>,
            datasets: [
                {
                    type: 'line',
                    label: 'Net Cash Flow',
                    data: <?= json_encode($trend_net) ?>,
                    borderColor: '#bef264',
                    borderWidth: 3,
                    pointBackgroundColor: '#000000',
                    pointBorderColor: '#bef264',
                    pointRadius: 5,
                    tension: 0.4,
                    order: 0
                },
                {
                    label: 'Inflow',
                    data: <?= json_encode($trend_in) ?>,
                    backgroundColor: gradIn,
                    borderRadius: 6,
                    barPercentage: 0.5,
                    order: 1
                },
                {
                    label: 'Outflow',
                    data: <?= json_encode($trend_out) ?>,
                    backgroundColor: gradOut,
                    borderRadius: 6,
                    barPercentage: 0.5,
                    order: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, color: '#94a3b8' } },
                tooltip: {
                    backgroundColor: '#000000',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    padding: 12,
                    titleFont: { family: 'Plus Jakarta Sans', size: 13 },
                    bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.dataset.label + ': KES ' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [4, 4], color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 11 } }, border: { display: false } },
                x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11 } }, border: { display: false } }
            }
        }
    });

    const ctxSource = document.getElementById('sourceChart').getContext('2d');
    new Chart(ctxSource, {
        type: 'doughnut',
        data: {
            labels: ['Deposits', 'Repayments', 'Shares', 'Welfare', 'Revenue', 'Wallet', 'Investments', 'Other'],
            datasets: [{
                data: [<?= $inflow_dist['Deposits'] ?>, <?= $inflow_dist['Repayments'] ?>, <?= $inflow_dist['Shares'] ?>, <?= $inflow_dist['Welfare'] ?>, <?= $inflow_dist['Revenue'] ?>, <?= $inflow_dist['Wallet'] ?>, <?= $inflow_dist['Investments'] ?>, <?= $inflow_dist['Other'] ?>],
                backgroundColor: ['#0f3d32', '#d1fa59', '#f59e0b', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899', '#cbd5e1'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false } }
        }
    });
</script>
    </div><!-- End main-content -->
</div><!-- End d-flex -->

<?php $layout->footer(); ?>
