<?php
// member/contributions.php

session_start();

// 1. Config & Auth
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Validate Login
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'] ?? 'Member';

// --- Handling Pagination & Filters ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';
$filter_type = $_GET['type'] ?? '';

// 2. Statistics Query (Summary)
$stats_sql = "SELECT 
    SUM(amount) as grand_total,
    SUM(CASE WHEN contribution_type = 'savings' THEN amount ELSE 0 END) as total_savings,
    SUM(CASE WHEN contribution_type = 'shares' THEN amount ELSE 0 END) as total_shares,
    SUM(CASE WHEN contribution_type IN ('welfare', 'welfare_case') THEN amount ELSE 0 END) as total_welfare
    FROM contributions 
    WHERE member_id = ? AND status = 'active'";

$stmt_stats = $conn->prepare($stats_sql);
$stmt_stats->bind_param("i", $member_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

$savings_val = (float)($stats['total_savings'] ?? 0);
$shares_val  = (float)($stats['total_shares'] ?? 0);
$welfare_val = (float)($stats['total_welfare'] ?? 0);

// 3. Main History Query
// Base SQL
$sql_base = "FROM contributions WHERE member_id = ?";
$params = [$member_id];
$types = "i";

// Apply Filters
if (!empty($filter_type)) {
    $sql_base .= " AND contribution_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if (!empty($filter_from) && !empty($filter_to)) {
    $sql_base .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $filter_from;
    $params[] = $filter_to;
    $types .= "ss";
}

// Count Total Records (For Pagination)
$count_sql = "SELECT COUNT(*) as total " . $sql_base;
$stmt_count = $conn->prepare($count_sql);
if(!empty($params)) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $records_per_page);

// Fetch Actual Data
$sql = "SELECT contribution_id, reference_no, contribution_type, amount, payment_method, created_at, status 
        " . $sql_base . " 
        ORDER BY created_at DESC 
        LIMIT ?, ?";

// Append Limit params
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = "My Contributions";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        :root {
            --theme-dark: #142e2a;
            --theme-lime: #c4ea70;
            --theme-lime-hover: #b3d960;
            --theme-white: #ffffff;
            --theme-bg: #f3f4f6;
            --theme-text-gray: #8d8d8d;
            --card-radius: 20px;
        }

        body {
            background-color: var(--theme-bg);
            font-family: 'Outfit', sans-serif;
            color: #1a1a1a;
            overflow-x: hidden;
        }

        /* --- Transitions --- */
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- Cards --- */
        .hope-card {
            border-radius: var(--card-radius);
            padding: 1.5rem;
            border: none;
            height: 100%;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        .hope-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }

        .card-dark { background-color: var(--theme-dark); color: white; }
        .card-dark .card-title-sm { color: rgba(255,255,255,0.6); font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-dark .card-amount { font-size: 1.8rem; font-weight: 700; margin: 0.2rem 0; letter-spacing: -0.5px; }
        
        .card-white { background-color: var(--theme-white); color: var(--theme-dark); }
        .card-white .card-title-sm { color: var(--theme-text-gray); font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-white .card-amount { font-size: 1.8rem; font-weight: 700; margin: 0.2rem 0; letter-spacing: -0.5px;}

        .card-lime { background-color: var(--theme-lime); color: var(--theme-dark); }
        .card-lime .card-title-sm { color: rgba(20,46,42,0.7); font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-lime .card-amount { font-size: 1.8rem; font-weight: 700; margin: 0.2rem 0; letter-spacing: -0.5px;}

        .icon-box {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        }

        /* --- Filters --- */
        .filter-container {
            background: var(--theme-white);
            border-radius: var(--card-radius);
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.03);
        }
        .form-control-custom {
            background: #f8f9fa; border: 1px solid #eee; border-radius: 10px; padding: 0.5rem 1rem; font-size: 0.9rem;
        }
        .form-control-custom:focus {
            background: #fff; border-color: var(--theme-lime); box-shadow: 0 0 0 3px rgba(196, 234, 112, 0.2);
        }
        .btn-filter {
            background: var(--theme-dark); color: white; border-radius: 10px; padding: 0.5rem 1.2rem; border: none; font-size: 0.9rem;
            transition: 0.2s;
        }
        .btn-filter:hover { background: #0f221f; color: var(--theme-lime); }

        /* --- Table --- */
        .transaction-list {
            background: var(--theme-white); border-radius: var(--card-radius); padding: 0; overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }
        .table-header { padding: 1.5rem; border-bottom: 1px solid #f0f0f0; }
        
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
        .custom-table th { 
            background: #fcfcfc; color: var(--theme-text-gray); font-weight: 600; font-size: 0.75rem; 
            text-transform: uppercase; padding: 1rem 1.5rem; border-bottom: 1px solid #eee;
        }
        .custom-table td { padding: 1rem 1.5rem; border-bottom: 1px solid #f5f5f5; vertical-align: middle; transition: background 0.2s; }
        .custom-table tr:hover td { background: #f9fbfb; }
        .custom-table tr:last-child td { border-bottom: none; }
        
        .avatar-box {
            width: 42px; height: 42px; border-radius: 12px;
            background: #f4f6f8; color: var(--theme-dark);
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-right: 15px;
        }
        
        /* --- Badges --- */
        .badge-status { padding: 6px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.3px; display: inline-flex; align-items: center; gap: 4px; }
        .badge-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; display: block; }
        
        .badge-success-soft { background: #ecfdf5; color: #059669; }
        .badge-success-soft::before { background: #059669; }
        
        .badge-warning-soft { background: #fffbeb; color: #d97706; }
        .badge-warning-soft::before { background: #d97706; }
        
        .badge-danger-soft { background: #fef2f2; color: #dc2626; }
        .badge-danger-soft::before { background: #dc2626; }

        /* --- Pagination --- */
        .page-link { border: none; color: var(--theme-text-gray); border-radius: 8px; margin: 0 2px; }
        .page-link.active { background-color: var(--theme-dark); color: var(--theme-lime); }
        .page-link:hover:not(.active) { background-color: #eee; color: var(--theme-dark); }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .hope-card { border: 1px solid #eee; box-shadow: none; }
        }
    </style>
</head>
<body>
<body>
    <div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
               
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-5 gap-3">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--theme-dark);">My Contributions</h2>
                    <p class="text-muted mb-0">Track your savings, shares, and welfare contributions.</p>
                </div>
                <div class="d-flex gap-2 no-print">
                    <button onclick="window.print()" class="btn btn-outline-secondary fw-medium rounded-pill px-4">
                        <i class="bi bi-printer me-2"></i>Print
                    </button>
                    <a href="<?= BASE_URL ?>/member/mpesa_request.php" class="btn fw-medium shadow-sm rounded-pill px-4" 
                       style="background: var(--theme-lime); color: var(--theme-dark); border: none;">
                       <i class="bi bi-plus-lg me-1"></i> New Deposit
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-4">
                
                <div class="col-md-4">
                    <div class="hope-card card-dark d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="card-title-sm">Total Savings</div>
                                <div class="card-amount">KES <?= number_format($savings_val) ?></div>
                            </div>
                            <div class="icon-box" style="background: rgba(255,255,255,0.1); color: var(--theme-lime);">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                        <div id="chart-savings" style="margin-bottom: -15px; margin-left: -10px;"></div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="hope-card card-white d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="card-title-sm">Shares Capital</div>
                                <div class="card-amount">KES <?= number_format($shares_val) ?></div>
                            </div>
                            <div class="icon-box" style="background: #f4f6f8; color: var(--theme-dark);">
                                <i class="bi bi-pie-chart"></i>
                            </div>
                        </div>
                         <div id="chart-shares" style="margin-bottom: -15px;"></div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="hope-card card-lime d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="card-title-sm">Welfare Fund</div>
                                <div class="card-amount">KES <?= number_format($welfare_val) ?></div>
                            </div>
                            <div class="icon-box" style="background: rgba(20, 46, 42, 0.1); color: var(--theme-dark);">
                                <i class="bi bi-heart-pulse-fill"></i>
                            </div>
                        </div>
                        <div class="mt-4 pt-2 border-top border-dark border-opacity-10 d-flex justify-content-between align-items-center">
                            <span class="small fw-semibold" style="color: var(--theme-dark);">Status</span>
                            <span class="badge bg-dark bg-opacity-25 text-dark rounded-pill px-3">Active Coverage</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="filter-container mb-4 no-print">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold mb-1 ms-1">Type</label>
                        <select name="type" class="form-select form-control-custom">
                            <option value="">All Transactions</option>
                            <option value="savings" <?= $filter_type == 'savings' ? 'selected' : '' ?>>Savings</option>
                            <option value="shares" <?= $filter_type == 'shares' ? 'selected' : '' ?>>Shares</option>
                            <option value="welfare" <?= $filter_type == 'welfare' ? 'selected' : '' ?>>Welfare</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold mb-1 ms-1">From</label>
                        <input type="date" name="from" value="<?= htmlspecialchars($filter_from) ?>" class="form-control form-control-custom">
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted fw-bold mb-1 ms-1">To</label>
                        <input type="date" name="to" value="<?= htmlspecialchars($filter_to) ?>" class="form-control form-control-custom">
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-filter w-100 shadow-sm">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <?php if(!empty($filter_type) || !empty($filter_from)): ?>
                                <a href="contributions.php" class="btn btn-light border w-auto" title="Clear Filters">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="transaction-list">
                <div class="table-header d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0" style="color: var(--theme-dark);">Transaction History</h5>
                    <div class="small text-muted">Page <?= $page ?> of <?= $total_pages ?></div>
                </div>
                
                <div class="table-responsive">
                    <table class="custom-table align-middle">
                        <thead>
                            <tr>
                                <th style="padding-left: 25px;">Contribution Details</th>
                                <th>Date Sent</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th class="text-end" style="padding-right: 25px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): 
                                    $type = $row['contribution_type'];
                                    $status = strtolower($row['status'] ?? 'pending');
                                    $dateTime = new DateTime($row['created_at']);
                                    
                                    $icon = match($type) {
                                        'savings' => 'bi-wallet2',
                                        'shares' => 'bi-pie-chart',
                                        'welfare' => 'bi-heart',
                                        default => 'bi-cash-stack'
                                    };
                                    
                                    $badgeClass = match($status) {
                                        'active', 'completed' => 'badge-success-soft',
                                        'pending' => 'badge-warning-soft',
                                        'failed', 'cancelled' => 'badge-danger-soft',
                                        default => 'bg-light text-dark'
                                    };
                                ?>
                                <tr>
                                    <td style="padding-left: 25px;">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-box">
                                                <i class="bi <?= $icon ?>"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark text-capitalize"><?= str_replace('_', ' ', $type) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($row['payment_method']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-dark fw-medium fs-6"><?= $dateTime->format('M d, Y') ?></div>
                                        <div class="small text-muted"><?= $dateTime->format('h:i A') ?></div>
                                    </td>
                                    <td class="font-monospace text-muted small">
                                        <?= htmlspecialchars($row['reference_no'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= $badgeClass ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td class="text-end" style="padding-right: 25px;">
                                        <div class="fw-bold text-success">
                                            + <?= number_format((float)$row['amount'], 2) ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="opacity-50 mb-2">
                                            <i class="bi bi-receipt display-4 text-muted"></i>
                                        </div>
                                        <p class="text-muted fw-medium">No transactions found.</p>
                                        <?php if(!empty($filter_type)): ?>
                                            <a href="contributions.php" class="btn btn-sm btn-outline-dark rounded-pill">Clear Filters</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center py-3 border-top border-light no-print">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&type=<?= $filter_type ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item">
                                    <a class="page-link <?= ($page == $i) ? 'active' : '' ?>" 
                                       href="?page=<?= $i ?>&type=<?= $filter_type ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&type=<?= $filter_type ?>&from=<?= $filter_from ?>&to=<?= $filter_to ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

        </div> 
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div> 
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- Sidebar Toggle Logic ---
        const sidebarToggle = document.querySelector('#sidebarToggle'); 
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', event => {
                event.preventDefault();
                document.body.classList.toggle('sb-sidenav-toggled');
            });
        }

        // --- Initialize Charts (ApexCharts) ---
        
        // 1. Savings Chart (Area Sparkline)
        var optionsSavings = {
            series: [{ name: 'Savings', data: [30, 40, 35, 50, 49, 60, 70, 91, 125] }], // Replace with dynamic data if available
            chart: { type: 'area', height: 80, sparkline: { enabled: true } },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.3, stops: [0, 90, 100] } },
            colors: ['#c4ea70'], // Lime
            tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
        };
        new ApexCharts(document.querySelector("#chart-savings"), optionsSavings).render();

        // 2. Shares Chart (Bar Sparkline)
        var optionsShares = {
            series: [{ name: 'Shares', data: [20, 30, 40, 50, 40, 60, 80] }],
            chart: { type: 'bar', height: 80, sparkline: { enabled: true } },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            colors: ['#142e2a'], // Dark Theme
            tooltip: { fixed: { enabled: false }, x: { show: false } }
        };
        new ApexCharts(document.querySelector("#chart-shares"), optionsShares).render();
    });
</script>

</body>
</html>
