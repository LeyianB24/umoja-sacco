<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
// member/savings.php
// Enhanced UI: Forest Green & Lime Theme + Responsive Sidebar


// 1. Load Config & Auth
// Validate Login
require_member();

// Initialize Layout Manager
$layout = LayoutManager::create('member');

$member_id = $_SESSION['member_id'];

// 2. Filter Logic
$typeFilter = $_GET['type'] ?? '';
$startDate  = $_GET['start_date'] ?? '';
$endDate    = $_GET['end_date'] ?? '';

$where = "WHERE member_id = ?";
$params = [$member_id];
$types = "i";

if ($typeFilter && in_array($typeFilter, ['deposit', 'withdrawal', 'contribution'])) {
    $where .= " AND transaction_type = ?";
    $params[] = $typeFilter;
    $types .= "s";
}
if ($startDate && $endDate) {
    $where .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}

// 3. Fetch Balances via Financial Engine (Golden Ledger Source)
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine = new FinancialEngine($conn);
$balances = $engine->getBalances($member_id);

$netSavings       = (float)($balances['savings'] ?? 0.0);
$totalSavings     = (float)($conn->query("SELECT SUM(amount) as total FROM contributions WHERE member_id = $member_id AND contribution_type = 'savings'")->fetch_assoc()['total'] ?? 0.0);
$totalWithdrawals = (float)($conn->query("SELECT SUM(amount) as total FROM transactions WHERE member_id = $member_id AND (transaction_type = 'withdrawal' OR transaction_type = 'debit') AND (notes LIKE '%savings%' OR notes LIKE '%Savings%')")->fetch_assoc()['total'] ?? 0.0);

// 4. Fetch History from transactions table
$sqlHistory = "SELECT * FROM transactions $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sqlHistory);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$history = $stmt->get_result();

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    $history->data_seek(0);
    while($row = $history->fetch_assoc()) {
        $isDeposit = in_array(strtolower($row['transaction_type']), ['deposit', 'contribution', 'income']);
        $sign = $isDeposit ? '+' : '-';
        
        $data[] = [
            'Date' => date('d-M-Y H:i', strtotime($row['created_at'])),
            'Type' => ucwords(str_replace('_', ' ', $row['transaction_type'])),
            'Notes' => $row['notes'] ?: '-',
            'Amount' => $sign . ' ' . number_format((float)$row['amount'], 2)
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Savings Statement',
        'module' => 'Member Portal',
        'headers' => ['Date', 'Type', 'Notes', 'Amount']
    ]);
    exit;
}

$pageTitle = "My Savings";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* --- HOPE UI VARIABLES --- */
            --hop-dark: #0F2E25;      /* Deep Forest Green */
            --hop-lime: #D0F35D;      /* Vibrant Lime */
            --hop-bg: #F8F9FA;        /* Light Background */
            --hop-card-bg: #FFFFFF;
            --hop-text: #1F2937;
            --hop-border: #EDEFF2;
            --card-radius: 24px;
        }

        [data-bs-theme="dark"] {
            --hop-bg: #0b1210;
            --hop-card-bg: #1F2937;
            --hop-text: #F9FAFB;
            --hop-border: #374151;
            --hop-dark: #13241f;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--hop-bg);
            color: var(--hop-text);
        }

        /* --- LAYOUT WRAPPER FOR SIDEBAR --- */
        .main-content-wrapper {
            margin-left: 280px; 
            transition: margin-left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0 !important; } }

        /* --- CARDS --- */
        .hop-card {
            background: var(--hop-card-bg);
            border-radius: var(--card-radius);
            border: 1px solid var(--hop-border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            padding: 1.5rem;
            height: 100%;
            transition: transform 0.2s ease;
        }
        .hop-card:hover { transform: translateY(-3px); }

        /* Dark Card (Hero) */
        .hop-card-dark {
            background-color: var(--hop-dark);
            color: white;
            border: none;
            position: relative;
            overflow: hidden;
        }
        .hop-card-dark::after {
            content: '';
            position: absolute; top: 0; right: 0; bottom: 0; left: 0;
            background: radial-gradient(circle at top right, rgba(208, 243, 93, 0.1), transparent 60%);
            pointer-events: none;
        }

        /* --- BUTTONS --- */
        .btn-hop-primary {
            background-color: var(--hop-lime);
            color: var(--hop-dark);
            border: none;
            border-radius: 50px;
            padding: 10px 24px;
            font-weight: 700;
            transition: all 0.2s;
        }
        .btn-hop-primary:hover {
            background-color: #c2e035;
            color: var(--hop-dark);
            transform: scale(1.05);
        }

        .btn-hop-secondary {
            background-color: var(--hop-card-bg);
            border: 1px solid var(--hop-border);
            color: var(--hop-text);
            border-radius: 50px;
            padding: 10px 24px;
            font-weight: 600;
        }
        .btn-hop-secondary:hover {
            background-color: var(--hop-border);
        }

        /* --- TABLE --- */
        .table-hop { border-collapse: separate; border-spacing: 0; }
        .table-hop th {
            font-size: 0.75rem; text-transform: uppercase; color: #9CA3AF;
            font-weight: 700; border: none; padding: 15px 20px;
        }
        .table-hop td {
            border-bottom: 1px solid var(--hop-border);
            padding: 20px; vertical-align: middle; background: var(--hop-card-bg);
        }
        .table-hop tr:last-child td { border-bottom: none; }

        .icon-box {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
        }
        .grow { flex-grow: 1; }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper">
        
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <div class="container-fluid px-4 py-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Savings Overview</h2>
                    <p class="text-secondary mb-0 small">Track your financial growth and history.</p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2">
                    <a href="<?= BASE_URL ?>/member/pages/dashboard.php" class="btn btn-hop-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-xl-4 col-md-12">
                    <div class="hop-card hop-card-dark h-100 p-4 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-box bg-white bg-opacity-10 text-lime rounded-circle">
                                    <i class="bi bi-wallet2" style="color: var(--hop-lime);"></i>
                                </div>
                                <span class="small opacity-75 fw-bold text-uppercase ls-1">Net Balance</span>
                            </div>
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-bold">Active</span>
                        </div>
                        
                        <div class="mt-4">
                            <h1 class="display-5 mb-0 fw-bold text-white">KES <?= number_format((float)$netSavings, 2) ?></h1>
                            <div class="mt-3 pt-3 border-top border-white border-opacity-10 d-flex justify-content-between align-items-center">
                                <small class="opacity-75">Available for withdrawal</small>
                                <span class="text-lime fw-bold small"><i class="bi bi-graph-up-arrow"></i> Growing</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6">
                    <div class="hop-card h-100 p-4">
                        <div class="d-flex justify-content-between mb-4">
                            <div>
                                <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Savings</h6>
                                <h3 class="fw-bold text-dark mb-0">KES <?= number_format((float)$totalSavings, 2) ?></h3>
                            </div>
                            <div class="icon-box bg-success bg-opacity-10 text-success">
                                <i class="bi bi-arrow-down-left"></i>
                            </div>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: 75%"></div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">Lifetime accumulated savings.</p>
                    </div>
                </div>

                <div class="col-xl-4 col-md-6">
                    <div class="hop-card h-100 p-4">
                         <div class="d-flex justify-content-between mb-4">
                            <div>
                                <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Withdrawals</h6>
                                <h3 class="fw-bold text-dark mb-0">KES <?= number_format((float)$totalWithdrawals, 2) ?></h3>
                            </div>
                            <div class="icon-box bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-arrow-up-right"></i>
                            </div>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-danger" style="width: 25%"></div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">Funds moved to M-Pesa.</p>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4 align-items-center">
                <div class="col-lg-4 d-flex gap-2">
                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=savings" class="btn btn-hop-primary shadow-sm">
                        <i class="bi bi-plus-lg me-1"></i> Deposit
                    </a>
                    <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=savings&source=savings" class="btn btn-hop-secondary">
                        <i class="bi bi-dash-lg me-1"></i> Withdraw
                    </a>
                </div>

                <div class="col-lg-2"></div>

                <div class="col-lg-6">
                    <form method="GET" class="hop-card p-2 d-flex gap-2 align-items-center" style="border-radius: 50px;">
                        <div class="grow px-2">
                            <select name="type" class="form-select border-0 bg-transparent text-muted fw-bold" style="font-size: 0.9rem;">
                                <option value="">All Transactions</option>
                                <option value="deposit" <?= $typeFilter === 'deposit' ? 'selected' : '' ?>>Deposits Only</option>
                                <option value="withdrawal" <?= $typeFilter === 'withdrawal' ? 'selected' : '' ?>>Withdrawals Only</option>
                                <option value="contribution" <?= $typeFilter === 'contribution' ? 'selected' : '' ?>>Contributions</option>
                            </select>
                        </div>
                        <div class="border-start mx-1" style="height: 20px;"></div>
                        <div class="grow">
                             <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control border-0 bg-transparent text-muted" placeholder="Start Date">
                        </div>
                        <div class="grow">
                             <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control border-0 bg-transparent text-muted" placeholder="End Date">
                        </div>
                        <button type="submit" class="btn btn-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: var(--hop-dark);">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="hop-card p-0 overflow-hidden">
                <div class="d-flex justify-content-between align-items-center p-4 border-bottom border-light">
                    <h5 class="fw-bold mb-0">Transaction History</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light fw-bold text-muted border dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i> Export Statement
                        </button>
                        <ul class="dropdown-menu shadow">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export PDF</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print Statement</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="table-responsive" id="savingsTable">
                    <table class="table table-hop mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Details</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history->num_rows > 0): ?>
                                <?php while ($row = $history->fetch_assoc()): 
                                    $isDeposit = in_array(strtolower($row['transaction_type']), ['deposit', 'contribution', 'income']);
                                    $bgIcon = $isDeposit ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger';
                                    $icon = $isDeposit ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
                                    $sign = $isDeposit ? '+' : '-';
                                    $amountColor = $isDeposit ? 'text-success' : 'text-danger';
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="icon-box me-3 <?= $bgIcon ?>">
                                                <i class="bi <?= $icon ?>"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark text-capitalize"><?= $row['transaction_type'] ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($row['notes'] ?? 'M-Pesa Transaction') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark small"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                        <div class="text-muted small" style="font-size: 0.75rem;"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-light text-dark border fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            Completed
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="fw-bold <?= $amountColor ?> fs-6">
                                            <?= $sign ?> KES <?= number_format((float)$row['amount'], 2) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="bi bi-inbox text-muted fs-1 mb-3"></i>
                                            <p class="text-muted fw-medium mb-0">No records found for this selection.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        
        <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





