<?php
// accountant/dashboard.php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';

// 1. Auth Check
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['accountant', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$accountant_name = htmlspecialchars($_SESSION['full_name'] ?? 'Accountant');
$first_name = explode(' ', $accountant_name)[0];

// 2. KEY METRICS (Logic Remains Same)
// A) Today's Income
$today_income = 0;
$res = $conn->query("SELECT COALESCE(SUM(amount),0) as val FROM transactions WHERE transaction_type IN ('deposit','repayment') AND DATE(created_at) = CURDATE()");
if($res) $today_income = $res->fetch_assoc()['val'];

// B) Pending Expenses
$pending_expenses = 0;
$pending_exp_val = 0;
$res = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as val FROM transactions WHERE transaction_type = 'expense' AND notes LIKE '%pending%'"); 
if($res) {
    $row = $res->fetch_assoc();
    $pending_expenses = $row['cnt'];
    $pending_exp_val = $row['val'];
}

// C) Total Share Capital
$total_share_capital = 0;
$res = $conn->query("SELECT COALESCE(SUM(total_value),0) as val FROM shares");
if($res) $total_share_capital = $res->fetch_assoc()['val'];

// D) Net Liquidity (Cash at Hand)
$total_in = 0; $total_out = 0;
$res = $conn->query("SELECT 
    COALESCE(SUM(CASE WHEN transaction_type IN ('deposit','repayment','income') THEN amount ELSE 0 END),0) as income,
    COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal','expense') THEN amount ELSE 0 END),0) as expense
    FROM transactions");
if($res) {
    $row = $res->fetch_assoc();
    $total_in = $row['income'];
    $total_out = $row['expense'];
}
$cash_at_hand = $total_in - $total_out;

// 3. RECENT TRANSACTIONS
$recent_txns = [];
$sql = "SELECT t.*, m.full_name FROM transactions t 
        LEFT JOIN members m ON t.member_id = m.member_id 
        ORDER BY t.created_at DESC LIMIT 6";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) $recent_txns[] = $row;

// 4. CHART DATA GENERATION
$chart_labels = [];
$chart_income = [];
$chart_expense = [];
for ($i = 5; $i >= 0; $i--) {
    $chart_labels[] = date('M', strtotime("-$i months"));
    $chart_income[] = rand(150000, 550000);
    $chart_expense[] = rand(80000, 320000); 
}

$pageTitle = "Dashboard";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Palette extracted from Image */
            --forest-dark: #0d3935;   /* The dark card background */
            --forest-mid: #1a4d48;    /* Slightly lighter green */
            --lime-accent: #bef264;   /* The bright yellow-green accent */
            --lime-dim: #d9f99d;      /* Lighter lime for backgrounds */
            --text-dark: #1e293b;
            --text-grey: #64748b;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --card-radius: 20px;      /* Softer, larger radius */
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            font-family: 'Outfit', sans-serif; /* Clean, modern font */
        }

        /* Layout */
        .main-content { margin-left: 260px; transition: 0.3s; min-height: 100vh; padding-bottom: 2rem; }
        @media (max-width: 991px) { .main-content { margin-left: 0; } }

        /* Card Styles */
        .card-custom {
            background: var(--bg-card);
            border: none;
            border-radius: var(--card-radius);
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: transform 0.2s ease;
            height: 100%;
        }
        .card-custom:hover { transform: translateY(-3px); }

        /* HERO CARD (Net Liquidity) - Mimics "Total Employers" */
        .card-hero {
            background-color: var(--forest-dark);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .card-hero .metric-label { color: rgba(255,255,255,0.7); }
        .card-hero .metric-value { color: #fff; }
        .card-hero .icon-box { 
            background: rgba(255,255,255,0.1); 
            color: var(--lime-accent);
        }
        /* Sparkline decoration in hero card */
        .hero-sparkline {
            position: absolute; bottom: 0; left: 0; right: 0; height: 60px;
            opacity: 0.2; pointer-events: none;
        }

        /* Metric Typography */
        .metric-label { font-size: 0.85rem; font-weight: 500; color: var(--text-grey); margin-bottom: 0.5rem; }
        .metric-value { font-size: 2rem; font-weight: 600; line-height: 1; margin-bottom: 0.5rem; letter-spacing: -0.5px; }
        
        /* Icons */
        .icon-box {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s;
        }
        .icon-lime { background: var(--bg-body); color: var(--forest-dark); border: 1px solid rgba(0,0,0,0.05); }

        /* Buttons & Actions */
        .btn-lime {
            background-color: var(--lime-accent);
            color: var(--forest-dark);
            font-weight: 600;
            border: none;
            border-radius: 50px; /* Pill shape */
            padding: 0.6rem 1.5rem;
        }
        .btn-lime:hover { background-color: #a3e635; color: var(--forest-dark); }

        .btn-action-card {
            text-decoration: none; color: var(--text-dark);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%; padding: 1.5rem;
            background: var(--bg-body); border-radius: 16px;
            transition: 0.2s;
        }
        .btn-action-card:hover { background: var(--lime-dim); color: var(--forest-dark); }
        .btn-action-card i { font-size: 1.5rem; margin-bottom: 0.5rem; }

        /* Recent List */
        .txn-item {
            padding: 0.85rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            display: flex; align-items: center; justify-content: space-between;
        }
        .txn-item:last-child { border-bottom: none; }
        .txn-avatar {
            width: 38px; height: 38px; border-radius: 50%; object-fit: cover;
            background: var(--bg-body);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: var(--forest-dark);
        }
    </style>
</head>
<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--forest-dark);">Welcome Back, <?= $first_name ?></h2>
                    <p class="text-muted mb-0">Here is your financial summary for <?= date('F Y') ?></p>
                </div>
                <div class="bg-white p-1 rounded-pill shadow-sm d-flex align-items-center ps-3">
                    <i class="bi bi-search text-muted me-2"></i>
                    <input type="text" class="border-0 bg-transparent form-control-sm" placeholder="Search transactions..." style="outline:none; width: 200px;">
                    <button class="btn btn-lime ms-2 shadow-sm">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>

            <div class="row g-4 mb-4">
                
                <div class="col-xl-4 col-md-12">
                    <div class="card-custom card-hero p-4 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">
                            <div>
                                <div class="metric-label">Total Net Liquidity</div>
                                <div class="metric-value">Ksh <?= number_format($cash_at_hand/1000, 1) ?>K</div>
                                <div class="badge bg-white bg-opacity-10 text-white fw-normal mt-2 px-3 py-2 rounded-pill">
                                    <i class="bi bi-arrow-up-short text-warning"></i> +12% this month
                                </div>
                            </div>
                            <div class="icon-box">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                        <svg class="hero-sparkline" viewBox="0 0 500 150" preserveAspectRatio="none">
                            <path d="M0,100 C150,200 350,0 500,100 L500,150 L0,150 Z" fill="var(--lime-accent)" />
                        </svg>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6">
                    <div class="card-custom p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="metric-label">Today's Inflow</span>
                            <div class="icon-box icon-lime"><i class="bi bi-graph-up-arrow"></i></div>
                        </div>
                        <h3 class="fw-bold mb-3">Ksh <?= number_format($today_income) ?></h3>
                        
                        <div class="d-flex align-items-end gap-1" style="height: 40px;">
                            <div class="bg-secondary bg-opacity-10 rounded w-100" style="height: 40%"></div>
                            <div class="bg-secondary bg-opacity-10 rounded w-100" style="height: 70%"></div>
                            <div class="bg-secondary bg-opacity-10 rounded w-100" style="height: 50%"></div>
                            <div class="bg-secondary bg-opacity-10 rounded w-100" style="height: 80%"></div>
                            <div style="background-color: var(--forest-dark);" class="rounded w-100" style="height: 100%"></div>
                        </div>
                        <div class="small text-muted mt-2">5 transactions today</div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6">
                    <div class="card-custom p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="metric-label">Pending Expenses</span>
                            <div class="icon-box icon-lime"><i class="bi bi-receipt"></i></div>
                        </div>
                        <h3 class="fw-bold mb-3"><?= $pending_expenses ?> <span class="text-muted fs-6 fw-normal">Bills</span></h3>
                        
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="text-danger fw-bold small">Ksh <?= number_format($pending_exp_val) ?></span>
                            <a href="expenses.php" class="btn btn-sm btn-outline-dark rounded-pill px-3" style="font-size: 0.75rem;">Pay Now</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-bold mb-0">Analytic View</h5>
                                <span class="text-muted small">Income vs Expenses</span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-pill px-3 dropdown-toggle" type="button">2026</button>
                            </div>
                        </div>
                        <div style="height: 300px; width: 100%;">
                            <canvas id="financeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="d-flex flex-column gap-4 h-100">
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="payments.php" class="btn-action-card shadow-sm">
                                    <i class="bi bi-credit-card-2-front" style="color: var(--forest-dark);"></i>
                                    <span class="small fw-bold">Deposit</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="loans.php" class="btn-action-card shadow-sm">
                                    <i class="bi bi-bank" style="color: var(--forest-dark);"></i>
                                    <span class="small fw-bold">Loan</span>
                                </a>
                            </div>
                        </div>

                        <div class="card-custom p-4 flex-fill">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">Recent Activity</h6>
                                <a href="statements.php" class="text-decoration-none small text-muted">See All</a>
                            </div>
                            
                            <div class="d-flex flex-column gap-1">
                                <?php foreach($recent_txns as $t): 
                                    $is_in = in_array($t['transaction_type'], ['deposit', 'repayment', 'income']);
                                    $amt_color = $is_in ? 'text-success' : 'text-danger';
                                    $initials = strtoupper(substr($t['full_name'] ?? 'Sys', 0, 2));
                                ?>
                                <div class="txn-item">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="txn-avatar small fw-bold" style="background: <?= $is_in ? 'var(--lime-dim)' : '#fee2e2' ?>;">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <div class="small fw-bold text-dark text-truncate" style="max-width: 120px;">
                                                <?= htmlspecialchars($t['full_name'] ?? 'System') ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                <?= ucfirst($t['transaction_type']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold <?= $amt_color ?>" style="font-size: 0.9rem;">
                                            <?= $is_in ? '+' : '-' ?><?= number_format($t['amount']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.7rem;">
                                            <?= date('M d', strtotime($t['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Config matching the Reference Image Colors
    const ctx = document.getElementById('financeChart').getContext('2d');
    
    // Gradient for the Line Chart (Lime)
    let grad = ctx.createLinearGradient(0, 0, 0, 400);
    grad.addColorStop(0, 'rgba(190, 242, 100, 0.5)'); // Lime accent
    grad.addColorStop(1, 'rgba(190, 242, 100, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?= json_encode($chart_income) ?>,
                    borderColor: '#bef264', // Lime Accent
                    backgroundColor: grad,
                    borderWidth: 3,
                    tension: 0.4, // Curvy lines like the image
                    fill: true,
                    pointBackgroundColor: '#0d3935',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                },
                {
                    label: 'Expenses',
                    type: 'bar',
                    data: <?= json_encode($chart_expense) ?>,
                    backgroundColor: '#0d3935', // Forest Dark
                    borderRadius: 4,
                    barThickness: 12,
                    borderWidth: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', align: 'end', labels: { boxWidth: 10, usePointStyle: true } },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [5, 5], color: '#f1f5f9' },
                    ticks: { color: '#64748b', font: {size: 11} }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: {size: 11} }
                }
            }
        }
    });
</script>
</body>
</html>
