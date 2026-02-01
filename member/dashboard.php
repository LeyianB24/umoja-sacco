<?php
// usms/member/dashboard.php
session_start();

// 1. CONFIG & AUTH
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';


// Validate Login
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/member/login.php");
    exit;
}

$member_id = (int) $_SESSION['member_id'];
$member_name = htmlspecialchars($_SESSION['member_name'] ?? 'Member', ENT_QUOTES);

// 2. FETCH DATA (Optimized)
$stmt = $conn->prepare("SELECT full_name FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();
if($row = $res->fetch_assoc()) {
    $member_name = htmlspecialchars($row['full_name']);
}
$stmt->close();

// Financial Calculations
function get_sum($conn, $sql, $id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $val = $stmt->get_result()->fetch_row()[0] ?? 0;
    $stmt->close();
    return (float)$val;
}

$total_savings = get_sum($conn, "SELECT COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END), 0) FROM savings WHERE member_id = ?", $member_id);
$total_shares  = get_sum($conn, "SELECT COALESCE(SUM(total_value), 0) FROM shares WHERE member_id = ?", $member_id);
$active_loans  = get_sum($conn, "SELECT COALESCE(SUM(amount), 0) FROM loans WHERE member_id = ? AND status IN ('approved', 'disbursed')", $member_id);

// Chart Data (Last 6 Months)
$chart_labels = [];
$chart_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end   = date('Y-m-t', strtotime("-$i months"));
    $chart_labels[] = date('M', strtotime($month_start));
    
    // Monthly Savings Net
    $sql = "SELECT COALESCE(SUM(amount),0) FROM transactions 
            WHERE member_id = ? AND transaction_type = 'deposit' 
            AND transaction_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $member_id, $month_start, $month_end);
    $stmt->execute();
    $chart_data[] = (float) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
}

// Recent Transactions
$recent_txn = [];
$stmt = $conn->prepare("SELECT transaction_type, amount, transaction_date, reference_no FROM transactions WHERE member_id = ? ORDER BY transaction_date DESC LIMIT 5");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) $recent_txn[] = $row;
$stmt->close();

function ksh($v) { return number_format($v, 2); }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
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
    </style>
</head>
<body>

<div class="d-flex">
    
    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
        
        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <div class="container-fluid px-4 py-4">
            
// Fetch Live Balance
$stmt = $conn->prepare("SELECT account_balance FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$cur_bal = $stmt->get_result()->fetch_assoc()['account_balance'] ?? 0;
$stmt->close();
?>
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <h1 class="fw-bold mb-1">Hi, <?= explode(' ', $member_name)[0] ?>! 👋</h1>
                    <p class="text-secondary mb-0">Here's your financial overview today.</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if($cur_bal > 0): ?>
                    <a href="mpesa_request.php?type=withdraw" class="btn btn-dark rounded-pill px-4 py-2 d-none d-md-block" style="background: #1F2937; border:none;">
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
                                <i class="bi bi-arrow-up-short"></i> +12% vs last month
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
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-danger" style="width: 45%"></div>
                        </div>
                        <div class="small text-secondary mt-2">45% of limit used</div>
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
                            $is_in = ($t['transaction_type'] == 'deposit');
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
                                            <?= date('d M, h:i A', strtotime($t['transaction_date'])) ?>
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
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
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
</script>
</body>
</html>