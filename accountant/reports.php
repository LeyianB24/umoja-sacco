<?php
// accountant/reports.php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';

// 1. Auth Check
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['accountant', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

// 2. Filter Inputs (Default to Current Year)
$start_date = $_GET['start_date'] ?? date('Y-01-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// 3. AGGREGATION LOGIC

// A. Totals for Cards (Cash Flow Basis)
$sql_totals = "SELECT 
    SUM(CASE WHEN transaction_type IN ('deposit', 'repayment', 'income', 'share_capital') THEN amount ELSE 0 END) as total_inflow,
    SUM(CASE WHEN transaction_type IN ('withdrawal', 'expense') THEN amount ELSE 0 END) as total_outflow,
    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as operational_expense
    FROM transactions 
    WHERE DATE(created_at) BETWEEN ? AND ?";

$stmt = $conn->prepare($sql_totals);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$stmt->close();

$net_cash_flow = $totals['total_inflow'] - $totals['total_outflow'];

// B. Monthly Trend Data (For Bar Chart)
$trend_labels = [];
$trend_in = [];
$trend_out = [];

$sql_trend = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month_str,
    DATE_FORMAT(created_at, '%b %Y') as display_date,
    SUM(CASE WHEN transaction_type IN ('deposit', 'repayment', 'income', 'share_capital') THEN amount ELSE 0 END) as inflow,
    SUM(CASE WHEN transaction_type IN ('withdrawal', 'expense') THEN amount ELSE 0 END) as outflow
    FROM transactions 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY month_str
    ORDER BY month_str ASC";

$stmt = $conn->prepare($sql_trend);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_trend = $stmt->get_result();

$monthly_data = []; // Store for the table view
while($row = $res_trend->fetch_assoc()) {
    $trend_labels[] = $row['display_date'];
    $trend_in[]     = $row['inflow'];
    $trend_out[]    = $row['outflow'];
    $monthly_data[] = $row;
}
$stmt->close();

// C. Inflow Distribution (For Doughnut Chart)
$inflow_dist = [
    'Deposits' => 0,
    'Repayments' => 0,
    'Shares' => 0,
    'Other' => 0
];
$sql_dist = "SELECT transaction_type, SUM(amount) as val FROM transactions 
             WHERE DATE(created_at) BETWEEN ? AND ? 
             AND transaction_type IN ('deposit', 'repayment', 'share_capital', 'income')
             GROUP BY transaction_type";
$stmt = $conn->prepare($sql_dist);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res_dist = $stmt->get_result();
while($row = $res_dist->fetch_assoc()){
    if($row['transaction_type'] == 'deposit') $inflow_dist['Deposits'] = $row['val'];
    elseif($row['transaction_type'] == 'loan_repayment' || $row['transaction_type'] == 'repayment') $inflow_dist['Repayments'] = $row['val'];
    elseif($row['transaction_type'] == 'share_capital') $inflow_dist['Shares'] = $row['val'];
    else $inflow_dist['Other'] = $row['val'];
}

$pageTitle = "Executive Reports";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* --- THEME COLORS based on uploaded image --- */
        :root {
            --bg-app: #f6f8f7; /* Very light grey background */
            --forest-green: #143d30; /* Dark green from "Total employers" */
            --lime-green: #ccf257;   /* Lime green from "Action Plan" */
            --text-main: #111827;
            --text-muted: #6b7280;
            --card-bg: #ffffff;
            --card-radius: 24px;     /* Matching the rounded aesthetic */
        }

        body { 
            background-color: var(--bg-app); 
            color: var(--text-main); 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
        }
        
        /* Custom Card Style mimicking the screenshots */
        .hope-card {
            background: var(--card-bg);
            border: none;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            height: 100%;
            transition: transform 0.2s ease;
        }

        /* The Dark Green Card (Total Inflow) */
        .card-forest {
            background-color: var(--forest-green);
            color: #ffffff;
        }
        .card-forest .text-muted { color: rgba(255,255,255,0.7) !important; }
        .card-forest .icon-box { background: rgba(255,255,255,0.1); color: var(--lime-green); }

        /* The Lime Green Card (Net Cash Flow) */
        .card-lime {
            background-color: var(--lime-green);
            color: var(--forest-green);
        }
        .card-lime .text-muted { color: var(--forest-green) !important; opacity: 0.7; }
        
        /* Buttons */
        .btn-forest {
            background-color: var(--forest-green);
            color: white;
            border-radius: 50px;
            padding: 10px 24px;
            border: none;
        }
        .btn-forest:hover { background-color: #0d2b21; color: white; }
        
        .btn-outline-forest {
            border: 2px solid var(--forest-green);
            color: var(--forest-green);
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 600;
        }
        .btn-outline-forest:hover {
            background-color: var(--forest-green);
            color: white;
        }

        /* Form Inputs */
        .form-control {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 10px 15px;
            background-color: #ffffff;
        }
        .form-control:focus {
            border-color: var(--forest-green);
            box-shadow: 0 0 0 3px rgba(20, 61, 48, 0.1);
        }

        /* Icons */
        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .main-content-wrapper { margin-left: 260px; transition: margin-left 0.3s; min-height: 100vh; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

        /* Print Specifics */
        @media print {
            .no-print, .sidebar { display: none !important; }
            .main-content-wrapper { margin: 0; padding: 0; }
            body { background: white; }
            .hope-card { box-shadow: none; border: 1px solid #ccc; break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5 gap-3 no-print">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--forest-green);">Financial Reports</h2>
                    <p class="text-muted small mb-0">Overview from <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?></p>
                </div>
                <div class="d-flex gap-2">
                     <button onclick="window.print()" class="btn btn-outline-forest shadow-sm">
                        <i class="bi bi-printer me-2"></i> Print Report
                    </button>
                </div>
            </div>

            <div class="hope-card mb-4 no-print py-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold mb-1 ms-1">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold mb-1 ms-1">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-forest w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="reports.php" class="btn btn-light w-100 rounded-pill">Reset</a>
                    </div>
                </form>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="hope-card card-forest d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold">Total Inflow</small>
                                <h3 class="fw-bold mt-2 mb-0">KES <?= number_format($totals['total_inflow']) ?></h3>
                            </div>
                            <div class="icon-box p-2 rounded-circle">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="badge bg-white bg-opacity-10 fw-normal">Deposits & Revenue</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="hope-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="text-muted text-uppercase fw-bold">Total Outflow</small>
                                <h3 class="fw-bold mt-2 mb-0 text-danger">KES <?= number_format($totals['total_outflow']) ?></h3>
                            </div>
                            <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-arrow-up-right"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                             <span class="text-muted small">Includes Loans & Expenses</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="hope-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="text-muted text-uppercase fw-bold">Op. Expenses</small>
                                <h3 class="fw-bold mt-2 mb-0 text-warning">KES <?= number_format($totals['operational_expense']) ?></h3>
                            </div>
                            <div class="icon-circle bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-building"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="text-muted small">Running Costs</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="hope-card card-lime d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="text-muted text-uppercase fw-bold">Net Cash Flow</small>
                                <h3 class="fw-bold mt-2 mb-0">KES <?= number_format($net_cash_flow) ?></h3>
                            </div>
                            <div class="p-2 bg-dark bg-opacity-10 rounded-circle text-dark">
                                <i class="bi bi-activity"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-dark bg-opacity-10 text-dark fw-normal">Net Movement</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="hope-card">
                        <h5 class="fw-bold mb-4" style="color: var(--forest-green);">Inflow vs Outflow Trends</h5>
                        <div style="position: relative; height: 320px;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="hope-card">
                        <h5 class="fw-bold mb-4" style="color: var(--forest-green);">Sources of Funds</h5>
                        <div style="position: relative; height: 220px;">
                            <canvas id="sourceChart"></canvas>
                        </div>
                        <div class="mt-4">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item border-0 px-0 d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-circle-fill me-2" style="color: var(--forest-green); font-size:10px;"></i> Deposits</span>
                                    <span class="fw-bold"><?= number_format($inflow_dist['Deposits']) ?></span>
                                </li>
                                <li class="list-group-item border-0 px-0 d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-circle-fill me-2" style="color: var(--lime-green); font-size:10px;"></i> Repayments</span>
                                    <span class="fw-bold"><?= number_format($inflow_dist['Repayments']) ?></span>
                                </li>
                                <li class="list-group-item border-0 px-0 d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-circle-fill text-secondary me-2" style="font-size:10px;"></i> Share Capital</span>
                                    <span class="fw-bold"><?= number_format($inflow_dist['Shares']) ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hope-card p-0 overflow-hidden">
                <div class="p-4 border-bottom">
                    <h5 class="fw-bold mb-0" style="color: var(--forest-green);">Monthly Breakdown</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 text-muted small text-uppercase">Month</th>
                                <th class="text-end text-muted small text-uppercase">Total Inflow</th>
                                <th class="text-end text-muted small text-uppercase">Total Outflow</th>
                                <th class="text-end text-muted small text-uppercase">Net Change</th>
                                <th class="text-end pe-4 text-muted small text-uppercase">Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($monthly_data)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-5">No data available for this range.</td></tr>
                            <?php else: foreach($monthly_data as $m): 
                                $net = $m['inflow'] - $m['outflow'];
                                $perf_color = $net >= 0 ? 'text-success' : 'text-danger';
                                $perf_icon = $net >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right';
                            ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= $m['display_date'] ?></td>
                                    <td class="text-end" style="color: var(--forest-green);"><?= number_format($m['inflow']) ?></td>
                                    <td class="text-end text-danger"><?= number_format($m['outflow']) ?></td>
                                    <td class="text-end fw-bold <?= $perf_color ?>"><?= number_format($net) ?></td>
                                    <td class="text-end pe-4"><i class="bi <?= $perf_icon ?> <?= $perf_color ?>"></i></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- Chart Data ---
    const trendLabels = <?= json_encode($trend_labels) ?>;
    const trendIn = <?= json_encode($trend_in) ?>;
    const trendOut = <?= json_encode($trend_out) ?>;
    
    const sourceData = [
        <?= $inflow_dist['Deposits'] ?>, 
        <?= $inflow_dist['Repayments'] ?>, 
        <?= $inflow_dist['Shares'] ?>, 
        <?= $inflow_dist['Other'] ?>
    ];

    // Colors extracted from Image
    const clrForest = '#143d30';
    const clrLime   = '#ccf257';
    const clrGrey   = '#e5e7eb';
    const clrDanger = '#dc3545';

    // --- 1. Trend Chart (Bar) ---
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'bar',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Inflow',
                    data: trendIn,
                    backgroundColor: clrForest, 
                    borderRadius: 8,
                    barPercentage: 0.6
                },
                {
                    label: 'Outflow',
                    data: trendOut,
                    backgroundColor: clrGrey, // Using grey to keep it subtle compared to Forest Green
                    hoverBackgroundColor: clrDanger, // Red on hover to alert user
                    borderRadius: 8,
                    barPercentage: 0.6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6 } }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { borderDash: [5, 5], color: '#f0f0f0' },
                    ticks: { font: { size: 11 } },
                    border: { display: false }
                },
                x: { 
                    grid: { display: false },
                    ticks: { font: { size: 11 } },
                    border: { display: false }
                }
            }
        }
    });

    // --- 2. Source Chart (Doughnut) ---
    const ctxSource = document.getElementById('sourceChart').getContext('2d');
    new Chart(ctxSource, {
        type: 'doughnut',
        data: {
            labels: ['Deposits', 'Repayments', 'Shares', 'Other'],
            datasets: [{
                data: sourceData,
                backgroundColor: [clrForest, clrLime, '#9ca3af', '#d1d5db'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false } // Custom legend in HTML
            },
            cutout: '75%'
        }
    });
</script>
</body>
</html>