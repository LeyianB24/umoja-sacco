<?php
// manager/dashboard.php
// Operations Manager Dashboard — Updated "Forest & Lime" Theme

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. CONFIGURATION & AUTH
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Enforce Manager Role
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['manager', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$db = $conn;

// Helper: Currency Format
function ksh($num) { return number_format((float)$num, 2); }

// ==========================================================================
// 2. DATA LOGIC (KPIs, Trends, Charts)
// ==========================================================================

// Helper: Calculate Month-over-Month Trend
function getTrend($conn, $table, $date_col, $status_col = null, $status_val = null) {
    $currentMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('-1 month'));
    
    $statusSql = $status_col ? "AND $status_col = '$status_val'" : "";

    // Current Count
    $sqlCur = "SELECT COUNT(*) as c FROM $table WHERE DATE_FORMAT($date_col, '%Y-%m') = '$currentMonth' $statusSql";
    $resCur = $conn->query($sqlCur)->fetch_assoc()['c'];

    // Last Month Count
    $sqlLast = "SELECT COUNT(*) as c FROM $table WHERE DATE_FORMAT($date_col, '%Y-%m') = '$lastMonth' $statusSql";
    $resLast = $conn->query($sqlLast)->fetch_assoc()['c'];

    $diff = $resCur - $resLast;
    
    return [
        'current' => $resCur,
        'diff'    => abs($diff),
        'arrow'   => $diff >= 0 ? 'up-short' : 'down-short',
        'color'   => $diff >= 0 ? 'success' : 'danger', // We will map 'success' to our lime/green in CSS
        'sign'    => $diff >= 0 ? '+' : '-'
    ];
}

// A. KPIs
$kpi_loans   = getTrend($db, 'loans', 'created_at', 'status', 'pending');
$kpi_members = getTrend($db, 'members', 'created_at', 'status', 'active');

$totalPending = $db->query("SELECT COUNT(*) as c FROM loans WHERE status = 'pending'")->fetch_assoc()['c'];
$totalActiveMembers = $db->query("SELECT COUNT(*) as c FROM members WHERE status = 'active'")->fetch_assoc()['c'];

$resPort = $db->query("SELECT SUM(current_value) as s FROM investments");
$totalPortfolio = $resPort ? (float)$resPort->fetch_assoc()['s'] : 0;

// B. Chart Data
$chartData = ['pending'=>0, 'approved'=>0, 'rejected'=>0, 'paid'=>0];
$resChart = $db->query("SELECT status, COUNT(*) as count FROM loans GROUP BY status");
while($row = $resChart->fetch_assoc()) {
    $status = strtolower($row['status']);
    if(isset($chartData[$status])) $chartData[$status] = $row['count'];
}

// C. Recent Pending Loans (Table)
$loans_review = $db->query("
    SELECT l.*, m.full_name, m.phone, m.profile_pic
    FROM loans l 
    JOIN members m ON l.member_id = m.member_id 
    WHERE l.status = 'pending' 
    ORDER BY l.created_at ASC LIMIT 5
");

// D. Activity Feed (Mock Data for UI)
$recent_activity = [
    ['icon'=>'bi-person-plus', 'bg'=>'bg-brand-dark', 'msg'=>'New member registration', 'time'=>'10 mins ago'],
    ['icon'=>'bi-cash-coin', 'bg'=>'bg-brand-lime', 'msg'=>'Loan #404 repayment received', 'time'=>'1 hour ago'],
    ['icon'=>'bi-exclamation-circle', 'bg'=>'bg-secondary', 'msg'=>'System maintenance scheduled', 'time'=>'3 hours ago'],
];

$pageTitle = "Manager Dashboard";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= $pageTitle ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* =========================================
           1. THEME VARIABLES (Based on Uploaded Images)
           ========================================= */
        :root {
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
            
            /* The Core Palette */
            --brand-dark: #0E2923;  /* Deep Forest Green (Header/Main Cards) */
            --brand-lime: #C2F25D;  /* Neon Lime (Accents/Buttons) */
            --brand-gray: #F3F4F6;  /* Light Gray Background */
            --brand-white: #FFFFFF;
            
            --text-dark: #111827;
            --text-muted: #6B7280;
            
            --border-radius: 16px;
        }

        /* 2. GENERAL LAYOUT */
        body {
            font-family: var(--font-main);
            background-color: var(--brand-gray);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Layout Structure */
        .app-wrapper { display: flex; min-height: 100vh; width: 100%; }
        .sidebar-container {
            width: 260px; flex-shrink: 0; position: fixed; top: 0; bottom: 0; left: 0; z-index: 1000;
            background: var(--brand-white); border-right: 1px solid rgba(0,0,0,0.05);
        }
        .main-content {
            flex-grow: 1; margin-left: 260px; display: flex; flex-direction: column; width: calc(100% - 260px);
        }
        @media (max-width: 991.98px) {
            .sidebar-container { display: none; }
            .main-content { margin-left: 0; width: 100%; }
        }

        /* 3. CARD STYLES (Matching Image 1 & 2) */
        
        /* Standard White Card */
        .card-custom {
            background: var(--brand-white);
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03); /* Very soft shadow */
            transition: transform 0.2s;
        }
        .card-custom:hover { transform: translateY(-2px); }

        /* "Total Employers" Style - Dark Green Card */
        .card-dark {
            background-color: var(--brand-dark);
            color: white;
        }
        .card-dark .text-muted { color: rgba(255,255,255,0.6) !important; }
        .card-dark .icon-box { background: rgba(255,255,255,0.1); color: var(--brand-lime); }

        /* "Action Plan" Style - Lime Green Card */
        .card-lime {
            background-color: var(--brand-lime);
            color: var(--brand-dark);
        }
        .card-lime .text-white-50 { color: rgba(14, 41, 35, 0.6) !important; } /* Darken muted text on lime */
        .card-lime h5 { color: var(--brand-dark) !important; }
        .card-lime .btn-action { background: var(--brand-dark); color: white; }

        /* 4. UTILITY STYLES */
        .icon-box {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
        }
        
        /* Table Styling */
        .table-custom th {
            font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em;
            color: var(--text-muted); background: transparent; border-bottom: 1px solid #eee; padding-bottom: 1rem;
        }
        .table-custom td {
            vertical-align: middle; border-bottom: 1px solid #f9f9f9; padding-top: 1rem; padding-bottom: 1rem;
        }
        
        /* Custom Buttons */
        .btn-brand-dark {
            background-color: var(--brand-dark); color: white; border: none;
            padding: 10px 24px; border-radius: 50px;
        }
        .btn-brand-dark:hover { background-color: #081a16; color: white; }

        .btn-brand-lime {
            background-color: var(--brand-lime); color: var(--brand-dark); border: none; font-weight: 600;
            padding: 8px 20px; border-radius: 50px;
        }
        .btn-brand-lime:hover { background-color: #b1e04d; color: var(--brand-dark); }

        .avatar-initials {
            width: 38px; height: 38px; border-radius: 50%;
            background: #f0fdf4; color: var(--brand-dark);
            font-weight: 700; display: flex; align-items: center; justify-content: center;
        }

        /* Chart Legend overrides */
        .text-success-custom { color: #10b981 !important; }
    </style>
</head>
<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--brand-dark);">Welcome Back, Manager</h2>
                    <p class="text-muted mb-0">Overview for <span class="fw-bold text-dark"><?= date('F Y') ?></span></p>
                </div>
                <div class="d-none d-md-block">
                    <a href="loans.php" class="btn btn-brand-lime shadow-sm">
                        <i class="bi bi-plus-lg me-1"></i> New Application
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-4">
                
                <div class="col-xl-3 col-md-6">
                    <div class="card-custom card-dark p-4 h-100">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <p class="small fw-bold text-uppercase mb-1 opacity-75">Pending Loans</p>
                                <h3 class="fw-bold mb-0 text-white"><?= $totalPending ?></h3>
                            </div>
                            <div class="icon-box">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-center small">
                            <span class="text-white fw-semibold me-2">
                                <i class="bi bi-arrow-<?= $kpi_loans['arrow'] ?>"></i> <?= $kpi_loans['current'] ?>
                            </span>
                            <span class="text-white opacity-50">this month</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card-custom p-4 h-100">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <p class="text-muted small fw-bold text-uppercase mb-1">Active Members</p>
                                <h3 class="fw-bold mb-0" style="color: var(--brand-dark);"><?= $totalActiveMembers ?></h3>
                            </div>
                            <div class="icon-box" style="background: #f0fdf4; color: var(--brand-dark);">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-center small">
                            <span class="text-success-custom fw-semibold me-2">
                                <i class="bi bi-arrow-<?= $kpi_members['arrow'] ?>"></i> <?= $kpi_members['current'] ?>
                            </span>
                            <span class="text-muted">new joined</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card-custom p-4 h-100">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <p class="text-muted small fw-bold text-uppercase mb-1">Total Assets</p>
                                <h3 class="fw-bold mb-0" style="color: var(--brand-dark);">
                                    <span class="fs-6 text-muted fw-normal">KES</span> <?= ksh($totalPortfolio / 1000000) ?>M
                                </h3>
                            </div>
                            <div class="icon-box" style="background: #ecfccb; color: #4d7c0f;">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                        <div class="small text-muted">Total investment value</div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card-custom card-lime p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start h-100">
                            <div class="d-flex flex-column justify-content-between h-100">
                                <div>
                                    <p class="text-white-50 small fw-bold text-uppercase mb-1">Action</p>
                                    <h5 class="fw-bold">Broadcast</h5>
                                </div>
                                <a href="../public/messages.php?action=compose" class="btn btn-sm btn-action rounded-pill px-3 align-self-start fw-bold">Compose</a>
                            </div>
                            <i class="bi bi-megaphone opacity-25" style="font-size: 3rem; color: var(--brand-dark);"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="card-custom h-100 p-0 overflow-hidden d-flex flex-column">
                        <div class="p-4 border-bottom border-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold m-0" style="color: var(--brand-dark);">Latest Loan Requests</h6>
                            <a href="loans.php" class="text-decoration-none small fw-bold" style="color: var(--brand-dark);">View All</a>
                        </div>
                        <div class="table-responsive grow">
                            <table class="table table-custom w-100 mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Member</th>
                                        <th>Applied Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($loans_review->num_rows == 0): ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted">No pending loans found.</td></tr>
                                    <?php else: while($l = $loans_review->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-initials me-3 small">
                                                        <?= strtoupper(substr($l['full_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark text-truncate" style="max-width: 140px;"><?= htmlspecialchars($l['full_name']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-muted small"><?= date('M d, Y', strtotime($l['created_at'])) ?></td>
                                            <td class="fw-bold text-dark">KES <?= ksh($l['amount']) ?></td>
                                            <td>
                                                <span class="badge rounded-pill px-3 py-2 fw-normal" style="background: #fffbeb; color: #b45309; border: 1px solid #fcd34d;">Pending</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="loans.php?id=<?= $l['loan_id'] ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3" style="border-color: #e5e7eb;">Review</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    
                    <div class="card-custom p-4 mb-4">
                        <h6 class="fw-bold mb-4" style="color: var(--brand-dark);">Loan Status</h6>
                        <div style="height: 220px; position: relative;">
                            <canvas id="loanChart"></canvas>
                        </div>
                    </div>

                    <div class="card-custom p-4 mb-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-1" style="color: var(--brand-dark);">System Status</h6>
                                <div class="d-flex align-items-center">
                                    <span class="d-inline-block p-1 rounded-circle me-2" style="background: var(--brand-lime);"></span>
                                    <small class="text-muted">Operational</small>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" checked style="background-color: var(--brand-dark); border-color: var(--brand-dark);">
                            </div>
                        </div>
                    </div>

                    <div class="card-custom p-4">
                        <h6 class="fw-bold mb-3" style="color: var(--brand-dark);">Recent Activity</h6>
                        <div class="vstack gap-3">
                            <?php foreach($recent_activity as $act): 
                                // Map PHP generic bg classes to our custom palette
                                $iconBg = 'bg-light text-dark';
                                if($act['bg'] == 'bg-brand-dark') $iconBg = 'text-white';
                                $bgStyle = $act['bg'] == 'bg-brand-dark' ? 'background: var(--brand-dark);' : ($act['bg'] == 'bg-brand-lime' ? 'background: var(--brand-lime); color: var(--brand-dark);' : 'background: #f3f4f6; color: #6b7280;');
                            ?>
                            <div class="d-flex">
                                <div class="shrink-0 me-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center <?= $iconBg ?>" style="width: 36px; height: 36px; font-size: 0.9rem; <?= $bgStyle ?>">
                                        <i class="bi <?= $act['icon'] ?>"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small fw-semibold text-dark"><?= $act['msg'] ?></div>
                                    <div class="small text-muted" style="font-size: 0.75rem;"><?= $act['time'] ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div> 
        </div> 
        
        <div class="mt-auto">
            <?php require_once __DIR__ . '/../inc/footer.php'; ?>
        </div>
    
    </div> 
</div> 

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Chart
    const ctx = document.getElementById('loanChart').getContext('2d');
    
    // Data from PHP
    const dataValues = [
        <?= $chartData['pending'] ?? 0 ?>, 
        <?= $chartData['approved'] ?? 0 ?>, 
        <?= $chartData['rejected'] ?? 0 ?>, 
        <?= $chartData['paid'] ?? 0 ?>
    ];

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Active', 'Rejected', 'Paid'],
            datasets: [{
                data: dataValues,
                // MATCHING COLORS: Pending (Yellow/Orange), Active (Dark Green), Rejected (Gray), Paid (Lime)
                backgroundColor: ['#f59e0b', '#0E2923', '#ef4444', '#C2F25D'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { usePointStyle: true, font: { size: 11, family: 'Inter' } } }
            },
            cutout: '75%'
        }
    });
</script>
</body>
</html>