<?php
// member/shares.php
session_start();

// 1. Load Config & Auth
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Validate Login
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// ---------------------------------------------------------
// 2. Settings & Business Logic
// ---------------------------------------------------------
$current_share_price = 100.00;
$dividend_rate_projection = 12.5; // Example: 12.5% projected return

// ---------------------------------------------------------
// 3. Fetch Totals
// ---------------------------------------------------------
$sqlTotal = "SELECT 
        COALESCE(SUM(share_units), 0) AS total_units,
        COALESCE(SUM(total_value), 0) AS total_capital
    FROM shares WHERE member_id = ?";

$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->bind_param("i", $member_id);
$stmtTotal->execute();
$totals = $stmtTotal->get_result()->fetch_assoc();
$stmtTotal->close();

$totalUnits   = (float) $totals['total_units'];
$totalCapital = (float) $totals['total_capital'];

// Calculate Projected Dividend
$projectedDividend = $totalCapital * ($dividend_rate_projection / 100);

// ---------------------------------------------------------
// 4. Fetch Share History & Prepare Chart Data
// ---------------------------------------------------------
$sqlHistory = "SELECT * FROM shares WHERE member_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sqlHistory);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// Prepare Data for Chart (Cumulative Growth)
// We reverse the array to calculate growth from the first transaction to the last
$chartLabels = [];
$chartData = [];
$runningTotal = 0;

$chronological_transactions = array_reverse($transactions);

foreach ($chronological_transactions as $txn) {
    $runningTotal += (float)$txn['total_value'];
    // Format date for chart label (e.g., 'Jan 15')
    $chartLabels[] = date('M d', strtotime($txn['created_at'])); 
    $chartData[] = $runningTotal;
}

// Convert to JSON for JS
$jsLabels = json_encode($chartLabels);
$jsData   = json_encode($chartData);

$pageTitle = "My Share Portfolio";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <style>
        :root {
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --brand-dark: #0f172a; 
            --brand-dark-rgb: 15, 23, 42;
            --brand-lime: #bef264; 
            --brand-lime-hover: #a3e635;
            --card-bg: #ffffff;
            --body-bg: #f8fafc;
            --text-primary: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: var(--font-main);
            background-color: var(--body-bg);
            color: var(--text-primary);
        }

        .main-content-wrapper {
            margin-left: 260px; 
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

        /* Modern Card Styling */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 1.75rem;
            height: 100%;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Hero Glassmorphism Card */
        .hero-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            border: none;
            position: relative;
            overflow: hidden;
        }
        .hero-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(190, 242, 100, 0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        /* Buttons */
        .btn-lime {
            background-color: var(--brand-lime);
            color: var(--brand-dark);
            font-weight: 700;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(190, 242, 100, 0.4);
            transition: all 0.2s ease;
        }
        .btn-lime:hover {
            background-color: var(--brand-lime-hover);
            color: black;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(190, 242, 100, 0.6);
        }

        /* Progress & Highlights */
        .text-lime { color: #65a30d; }
        .bg-lime-subtle { background-color: rgba(190, 242, 100, 0.25); color: #3f6212; }
        .icon-box {
            width: 52px; height: 52px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .display-amount { font-size: 2.75rem; font-weight: 800; letter-spacing: -0.04em; }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }

        /* Table Tweaks */
        .table-custom thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            background-color: #f8fafc;
            border-bottom: 2px solid var(--border-color);
            padding: 1rem;
        }
        .table-custom tbody td {
            padding: 1rem;
            vertical-align: middle;
            font-size: 0.95rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--brand-dark) !important;
            color: white !important;
            border: none !important;
            border-radius: 50%;
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
                    <h2 class="fw-bold mb-1" style="color: var(--brand-dark);">Share Portfolio</h2>
                    <p class="text-secondary mb-0">Overview of your equity and projected returns.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-white border shadow-sm rounded-pill px-4 fw-medium" id="downloadReport">
                        <i class="bi bi-download me-2"></i> Report
                    </button>
                    <a href="<?= BASE_URL ?>/member/mpesa_request.php?type=shares" class="btn btn-lime d-flex align-items-center gap-2">
                        <i class="bi bi-plus-lg"></i>
                        <span>Buy Shares</span>
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-5">
                
                <div class="col-xl-4 col-lg-5 col-md-12">
                    <div class="stat-card hero-card d-flex flex-column justify-content-between h-100">
                        <div class="d-flex justify-content-between align-items-start z-1">
                            <span class="badge bg-white bg-opacity-10 border border-white border-opacity-10 rounded-pill px-3 py-2 fw-normal backdrop-blur">
                                <i class="bi bi-wallet2 me-2"></i> Capital Balance
                            </span>
                            <i class="bi bi-shield-check fs-4 opacity-50"></i>
                        </div>
                        
                        <div class="mt-4 z-1">
                            <h1 class="display-amount mb-0">KES <?= number_format($totalCapital, 2) ?></h1>
                            <p class="opacity-75 mb-0 mt-2">Total Accumulated Value</p>
                        </div>

                        <div class="mt-4 pt-3 border-top border-white border-opacity-10 d-flex justify-content-between align-items-end z-1">
                            <div>
                                <small class="text-lime fw-bold">Est. Dividend (<?= $dividend_rate_projection ?>%)</small>
                                <div class="fs-5 fw-bold text-white">KES <?= number_format($projectedDividend, 2) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-lime-subtle text-dark border-0">Annual Projection</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8 col-lg-7 col-md-12">
                    <div class="row g-4 h-100">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <p class="text-uppercase text-muted small fw-bold mb-1">Total Units</p>
                                        <h3 class="fw-bold mb-0"><?= number_format($totalUnits, 2) ?></h3>
                                    </div>
                                    <div class="icon-box bg-lime-subtle text-dark">
                                        <i class="bi bi-layers-fill"></i>
                                    </div>
                                </div>
                                <hr class="border-light my-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted small">Current Price</span>
                                    <span class="fw-bold text-dark">KES <?= number_format($current_share_price, 2) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="stat-card bg-light border-0">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <p class="text-uppercase text-muted small fw-bold mb-0">Portfolio Growth</p>
                                    <span class="badge bg-white border text-success shadow-sm">
                                        <i class="bi bi-graph-up-arrow me-1"></i> Active
                                    </span>
                                </div>
                                <div class="chart-container">
                                    <canvas id="growthChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-dark">Transaction History</h5>
                            <button class="btn btn-sm btn-light border text-muted" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive p-3">
                                <table id="historyTable" class="table table-custom table-hover align-middle mb-0 w-100">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-3">Date</th>
                                            <th>Ref No.</th>
                                            <th>Units</th>
                                            <th>Unit Price</th>
                                            <th>Total Paid</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $row): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold text-dark"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                                                    <span class="small text-muted"><?= date('H:i A', strtotime($row['created_at'])) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="font-monospace text-secondary small bg-light px-2 py-1 rounded border">
                                                    <?= htmlspecialchars($row['reference_no']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-1 me-2" style="font-size:0.6rem;">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </div>
                                                    <span class="fw-medium"><?= number_format($row['share_units'], 2) ?></span>
                                                </div>
                                            </td>
                                            <td class="text-muted">KES <?= number_format($row['unit_price'], 0) ?></td>
                                            <td>
                                                <span class="fw-bold text-dark">KES <?= number_format($row['total_value'], 2) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-medium">
                                                    Confirmed
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    // 1. Initialize DataTable
    $(document).ready(function() {
        $('#historyTable').DataTable({
            order: [[0, 'desc']], // Sort by date desc
            pageLength: 10,
            language: {
                search: "",
                searchPlaceholder: "Search transactions..."
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"f>t<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    });

    // 2. Initialize Chart
    
    const ctx = document.getElementById('growthChart').getContext('2d');
    
    // Create gradient
    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(190, 242, 100, 0.5)'); // Brand Lime
    gradient.addColorStop(1, 'rgba(190, 242, 100, 0.0)');

    const chartData = {
        labels: <?= $jsLabels ?>,
        datasets: [{
            label: 'Portfolio Value (KES)',
            data: <?= $jsData ?>,
            borderColor: '#65a30d', // Darker lime for line
            backgroundColor: gradient,
            borderWidth: 2,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#65a30d',
            pointRadius: 3,
            fill: true,
            tension: 0.4 // Smooth curves
        }]
    };

    new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#bef264',
                    bodyColor: '#fff',
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'KES ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: { display: false }, // Hide x axis labels for cleaner look in mini card
                y: { 
                    display: false, // Hide y axis
                    beginAtZero: true 
                }
            }
        }
    });

    // 3. PDF Export Logic
    document.getElementById("downloadReport")?.addEventListener("click", () => {
        const element = document.querySelector(".table-responsive");
        const opt = {
            margin:       0.5,
            filename:     'Shares_Statement.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' }
        };
        html2pdf().set(opt).from(element).save();
    });
</script>
</body>
</html>
