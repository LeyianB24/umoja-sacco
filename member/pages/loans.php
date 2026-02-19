<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/functions.php';

$layout = LayoutManager::create('member');
// Initialize Layout Manager
$layout = LayoutManager::create('member');
// member/loans.php
// UI: Forest Green Premium | Layout: Grid System
// V15 Financial Integration: Dynamic Balance & Ledger Logic


// --- 1. CONFIG & AUTH ---
require_once __DIR__ . '/../../inc/TransactionHelper.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';

// Validate Login
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'] ?? 'Member';
$engine = new FinancialEngine($conn);

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];

    if ($action === 'repay_wallet') {
        // Fetch current data via FinancialEngine (Single Source of Truth)
        $balances = $engine->getBalances($member_id);
        $curr_wallet = $balances['wallet'];

        $stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ? AND status IN ('disbursed', 'active') ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $l_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $amount = (float)($_POST['amount'] ?? 0);

        if (!$l_data) {
            flash_set("No active disbursed loan found.", "error");
        } elseif ($amount <= 0) {
            flash_set("Invalid amount.", "error");
        } elseif ($amount > $curr_wallet) {
            flash_set("Insufficient wallet balance.", "error");
        } elseif ($amount > (float)$l_data['current_balance']) {
            flash_set("Amount exceeds the outstanding balance.", "error");
        } else {
            try {
                $loan_id = (int)$l_data['loan_id'];
                $ref = 'WAL-' . strtoupper(substr(md5(uniqid()), 0, 8));

                // Unified Ledger Transaction (Handles both Wallet debit and Loan credit + Legacy Sync)
                $engine->transact([
                    'member_id'     => $member_id,
                    'amount'        => $amount,
                    'action_type'   => 'loan_repayment',
                    'method'        => 'wallet',
                    'reference'     => $ref,
                    'notes'         => "Loan Repayment via Wallet (Loan #$loan_id)",
                    'related_id'    => $loan_id,
                    'related_table' => 'loans'
                ]);

                flash_set("Repayment of KES " . number_format((float)$amount) . " successful.", "success");
                header("Location: loans.php");
                exit;
            } catch (Exception $e) {
                flash_set("Error: " . $e->getMessage(), "error");
            }
        }
    }
}

// --- 2. DATA FETCHING ---

// A. Get Savings & Calculate Limit (Unified V28 Logic)
$balances = $engine->getBalances($member_id);

$total_savings    = $balances['savings'];
$account_balance  = $balances['wallet'];

// Policy: Limit is 3x Savings
$max_loan_limit = $total_savings * 3;
$is_eligible = ($total_savings > 0);

// B. Check for Active or Pending Loans
$active_loan = null;
$pending_loan = null;

$stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ? AND status NOT IN ('completed', 'rejected', 'settled') ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    if ($row['status'] === 'pending' || $row['status'] === 'approved') {
        $pending_loan = $row;
    } else {
        $active_loan = $row;
    }
}
$stmt->close();

// C. Calculate Repayment Progress (V15 Logic: Using current_balance and total_payable)
$progress_percent = 0;
$repaid_amount = 0;
$outstanding_balance = 0;
$total_payable = 0;

if ($active_loan) {
    $loan_id = $active_loan['loan_id'];
    $total_payable = $active_loan['total_payable'] > 0 ? $active_loan['total_payable'] : ($active_loan['amount'] * (1 + ($active_loan['interest_rate']/100)));
    $outstanding_balance = $active_loan['current_balance'];
    $repaid_amount = max(0, $total_payable - $outstanding_balance);
    
    if ($total_payable > 0) {
        $progress_percent = ($repaid_amount / $total_payable) * 100;
        if($progress_percent > 100) $progress_percent = 100;
    }
}

// D. Fetch History
$stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res_history = $stmt->get_result();

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    $res_history->data_seek(0);
    while($row = $res_history->fetch_assoc()) {
        $data[] = [
            'Date' => date('d-M-Y', strtotime($row['created_at'])),
            'Type' => ucwords(str_replace('_', ' ', $row['loan_type'])),
            'Amount' => number_format((float)$row['amount'], 2),
            'Status' => ucfirst($row['status']),
            'Balance' => number_format((float)$row['current_balance'], 2)
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'My Loan Portfolio',
        'module' => 'Member Portal',
        'headers' => ['Date', 'Type', 'Amount', 'Status', 'Balance']
    ]);
    exit;
}

$history = [];
$res_history->data_seek(0);
while($row = $res_history->fetch_assoc()) $history[] = $row;
$stmt->close();

// E. Fetch Other Members for Guarantors
$other_members = [];
$stmt = $conn->prepare("SELECT member_id, full_name, national_id FROM members WHERE member_id != ? AND status = 'active' ORDER BY full_name ASC");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res_m = $stmt->get_result();
while($row = $res_m->fetch_assoc()) $other_members[] = $row;
$stmt->close();

$pageTitle = "My Loans";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest-deep: #0F2E25;    /* Deep Green */
            --forest-mid: #1A4133;     /* Card Background */
            --forest-light: #2D6A4F;   /* Lighter elements */
            --lime-accent: #D0F35D;    /* Electric Lime */
            --lime-hover: #bce04b;
            --surface-bg: #F3F4F6;
            --text-main: #111827;
            --text-muted: #6B7280;
            --sidebar-width: 280px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--surface-bg);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* --- Layout --- */
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 991px) {
            .main-content { margin-left: 0 !important; }
        }

        /* --- Cards & Components --- */
        .card-clean {
            background: white;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        /* The Forest Card (Active Loan) */
        .card-forest {
            background-color: var(--forest-deep);
            background-image: radial-gradient(circle at 100% 0%, #2D6A4F 0%, var(--forest-deep) 60%);
            color: white;
            border: none;
            position: relative;
            border-radius: 24px;
        }

        .card-forest .label-text {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Buttons */
        .btn-lime {
            background-color: var(--lime-accent);
            color: var(--forest-deep);
            font-weight: 700;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .btn-lime:hover {
            background-color: var(--lime-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(208, 243, 93, 0.4);
            color: var(--forest-deep);
        }

        .btn-forest-outline {
            background: transparent;
            border: 2px solid rgba(15, 46, 37, 0.1);
            color: var(--forest-deep);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 12px;
        }
        .btn-forest-outline:hover {
            background: rgba(15, 46, 37, 0.05);
            border-color: var(--forest-deep);
        }

        /* Icons */
        .icon-box {
            width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 14px;
            font-size: 1.25rem;
            background: rgba(15, 46, 37, 0.05);
            color: var(--forest-deep);
        }
        .icon-box.lime {
            background: rgba(208, 243, 93, 0.2);
            color: var(--forest-deep);
        }

        /* Table */
        .table-premium {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-premium th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 700;
            padding: 1.25rem 1.5rem;
            border-bottom: 2px solid #f0f0f0;
            background: #fff;
        }
        .table-premium td {
            padding: 1.25rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f9f9f9;
            color: var(--text-main);
            font-weight: 500;
        }
        .table-premium tr:last-child td { border-bottom: none; }
        .table-premium tr:hover td { background-color: #fcfcfc; }

        /* Status Badges */
        .badge-pill {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-completed { background: #DCFCE7; color: #166534; }
        .status-active { background: #DBEAFE; color: #1E40AF; }
        .status-disbursed { background: #DBEAFE; color: #1E40AF; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-approved { background: #E0E7FF; color: #4338CA; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }

        /* Progress Bars */
        .progress-thin {
            height: 6px;
            border-radius: 10px;
            background: rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .progress-thin .bar {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        /* Modal Polish */
        .modal-content {
            border: none;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        .form-control-lg-custom {
            border: 2px solid #eee;
            border-radius: 12px;
            padding: 14px 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .form-control-lg-custom:focus {
            border-color: var(--forest-deep);
            box-shadow: none;
        }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>

<div class="dashboard-wrapper">
    
    <?php $layout->sidebar(); ?>

    <div class="main-content">
        
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="p-4 p-lg-5">
            <?php flash_render(); ?>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
                <div>
                    <h2 class="fw-bold mb-1 ">Loan Portfolio</h2>
                    <p class="text-secondary mb-0">Manage your finances and track repayment progress.</p>
                </div>
                
                <div class="d-flex gap-2">
                    <?php if($active_loan || $pending_loan): ?>
                        <?php if($account_balance > 0): ?>
                            <a href="withdraw.php?type=loans&source=loans" class="btn btn-dark fw-bold px-4 py-3 rounded-4 shadow-sm">
                                <i class="bi bi-wallet2 me-2"></i> Withdraw Funds
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-light border text-secondary fw-bold px-4 py-3 rounded-4" style="opacity: 0.8; cursor: not-allowed;">
                            <i class="bi bi-lock-fill me-2"></i> Limit Reached
                        </button>
                    <?php else: ?>
                        <?php if($account_balance > 0): ?>
                            <a href="withdraw.php?type=loans&source=loans" class="btn btn-dark fw-bold px-4 py-3 rounded-4 shadow-sm">
                                <i class="bi bi-wallet2 me-2"></i> Withdraw Funds
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-lime shadow-lg py-3 rounded-4" data-bs-toggle="modal" data-bs-target="#applyLoanModal">
                            <i class="bi bi-plus-lg me-2"></i> Apply for Loan
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4">
                
                <div class="col-xl-8">
                    
                    <?php if ($pending_loan): ?>
                        <div class="alert bg-warning bg-opacity-10 border-warning border-opacity-25 rounded-4 d-flex align-items-center p-4 mb-4">
                            <div class="icon-box bg-warning bg-opacity-25 text-warning-emphasis me-3">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-warning-emphasis mb-1">Application In Review</h6>
                                <span class="small text-secondary">Your request for <strong>KES <?= number_format((float)$pending_loan['amount']) ?></strong> is currently status: <strong><?= ucfirst($pending_loan['status']) ?></strong>.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($active_loan): ?>
                        <div class="card-forest p-4 p-lg-5 mb-4 shadow-lg">
                            <div class="d-flex justify-content-between align-items-start mb-5">
                                <div>
                                    <div class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25 rounded-pill px-3 py-2 mb-3 backdrop-blur">
                                        Active Loan #<?= $active_loan['loan_id'] ?>
                                    </div>
                                    <h1 class="display-4 fw-bold mb-0">KES <?= number_format((float)$outstanding_balance) ?></h1>
                                    <span class="label-text">Outstanding Balance</span>
                                </div>
                                <div class="text-end d-none d-sm-block">
                                    <span class="label-text d-block mb-1">Interest Rate</span>
                                    <span class="fw-bold fs-5"><?= $active_loan['interest_rate'] ?>% p.a</span>
                                </div>
                            </div>

                            <div class="row align-items-end">
                                <div class="col-lg-7 mb-4 mb-lg-0">
                                    <div class="d-flex justify-content-between text-white small fw-bold mb-2">
                                        <span>Repayment Progress</span>
                                        <span><?= number_format((float)$progress_percent, 0) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 8px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $progress_percent ?>%"></div>
                                    </div>
                                    <div class="mt-3 text-white opacity-75 small uppercase ls-1">
                                        Guarantors: 
                                        <?php
                                            $lid = $active_loan['loan_id'];
                                            $g_sql = "SELECT m.full_name FROM loan_guarantors g JOIN members m ON g.member_id = m.member_id WHERE g.loan_id = $lid";
                                            $guarantors = $conn->query($g_sql);
                                            $g_names = [];
                                            while($g = $guarantors->fetch_assoc()) $g_names[] = $g['full_name'];
                                            echo !empty($g_names) ? implode(', ', $g_names) : 'None';
                                        ?>
                                    </div>
                                    <div class="mt-2 text-white opacity-75 small">
                                        Paid: KES <?= number_format((float)$repaid_amount) ?> of KES <?= number_format((float)$total_payable) ?>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="row g-2 mt-2">
                                        <div class="col-sm-6">
                                            <a href="mpesa_request.php?type=loan_repayment&loan_id=<?= $active_loan['loan_id'] ?>" 
                                               class="btn btn-lime w-100 py-3 shadow-sm">
                                                <i class="bi bi-phone me-2"></i> M-Pesa
                                            </a>
                                        </div>
                                        <div class="col-sm-6">
                                            <button class="btn btn-white border w-100 py-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#repayWalletModal">
                                                <i class="bi bi-wallet2 me-2 text-success"></i> Wallet
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!$pending_loan): ?>
                        <div class="card-clean p-5 text-center mb-4 border-dashed h-100 d-flex flex-column justify-content-center align-items-center">
                            <?php if ($total_savings > 0): ?>
                                <div class="icon-box lime rounded-circle mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <h3 class="fw-bold ">You are Eligible!</h3>
                                <p class="text-secondary mb-4 col-lg-8 mx-auto">You currently have no active debts. Based on your savings, you qualify for an instant loan up to the limit below.</p>
                                <h2 class="text-success fw-bold">KES <?= number_format((float)$max_loan_limit) ?></h2>
                            <?php else: ?>
                                <div class="icon-box bg-light text-muted rounded-circle mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <h3 class="fw-bold ">Build Your Savings</h3>
                                <p class="text-secondary mb-4 col-lg-8 mx-auto">To qualify for a loan, you need to have active savings. Start saving today to unlock borrowing power up to 3x your balance.</p>
                                <a href="mpesa_request.php?type=savings" class="btn btn-dark rounded-pill px-5 py-3">Start Saving Now</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-clean">
                        <div class="p-4 border-bottom border-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 ">Recent History</h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-download me-1"></i> Export
                                </button>
                                <ul class="dropdown-menu shadow">
                                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export PDF</a></li>
                                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel</a></li>
                                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print History</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table-premium mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Loan Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($history)): ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted">No loan history found.</td></tr>
                                    <?php else: foreach($history as $h): 
                                        $statusClass = match($h['status']) {
                                            'completed' => 'status-completed',
                                            'disbursed' => 'status-disbursed',
                                            'rejected' => 'status-rejected',
                                            'approved' => 'status-approved',
                                            default => 'status-pending'
                                        };
                                    ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?= date('M d, Y', strtotime($h['created_at'])) ?></td>
                                            <td class="text-capitalize text-secondary"><?= str_replace('_', ' ', $h['loan_type']) ?></td>
                                            <td class="fw-bold">KES <?= number_format((float)$h['amount']) ?></td>
                                            <td><span class="badge-pill <?= $statusClass ?>"><?= ucfirst($h['status']) ?></span></td>
                                             <td class="text-end pe-4">
                                                 <button onclick="viewRepayments(<?= $h['loan_id'] ?>)" class="btn btn-sm btn-light border rounded-circle" style="width:32px; height:32px;"><i class="bi bi-chevron-right"></i></button>
                                             </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="d-flex flex-column gap-4">
                        
                        <div class="card-clean p-4 border-lime" style="border: 2px solid var(--lime-accent);">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box lime me-3">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <div>
                                    <span class="text-secondary small fw-bold text-uppercase">Loan Wallet (Disbursed)</span>
                                    <h5 class="fw-bold mb-0">KES <?= number_format((float)$account_balance) ?></h5>
                                </div>
                            </div>
                            <div class="d-grid mt-3">
                                <a href="<?= BASE_URL ?>/member/pages/withdraw.php?type=loans&source=loans" class="btn btn-lime py-2">
                                    <i class="bi bi-cash-coin me-2"></i> Withdraw to M-Pesa
                                </a>
                            </div>
                        </div>

                        <div class="card-clean p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box me-3">
                                    <i class="bi bi-safe"></i>
                                </div>
                                <div>
                                    <span class="text-secondary small fw-bold text-uppercase">Total Savings</span>
                                    <h5 class="fw-bold mb-0">KES <?= number_format((float)$total_savings) ?></h5>
                                </div>
                            </div>
                            <hr class="border-light opacity-50 my-2">
                            <div class="d-flex align-items-center mt-2">
                                <div class="icon-box lime me-3">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <div>
                                    <span class="text-secondary small fw-bold text-uppercase">Max Loan Limit (3x)</span>
                                    <h5 class="fw-bold text-success mb-0">KES <?= number_format((float)$max_loan_limit) ?></h5>
                                </div>
                            </div>
                        </div>

                        <div class="card-clean bg-dark text-white p-4" style="background: var(--forest-deep);">
                            <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Quick Terms</h5>
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-3 small opacity-75">
                                <li class="d-flex align-items-start">
                                    <i class="bi bi-check-circle-fill text-warning me-2 mt-1"></i>
                                    Interest rate is fixed at <?= $active_loan['interest_rate'] ?? 12 ?>% p.a on reducing balance.
                                </li>
                                <li class="d-flex align-items-start">
                                    <i class="bi bi-check-circle-fill text-warning me-2 mt-1"></i>
                                    Loans require active guarantors.
                                </li>
                                <li class="d-flex align-items-start">
                                    <i class="bi bi-check-circle-fill text-warning me-2 mt-1"></i>
                                    Processing takes 24-48 hours.
                                </li>
                            </ul>
                        </div>

                    </div>
                </div>

            </div>
        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<div class="modal fade" id="applyLoanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content overflow-hidden">
      
      <div class="modal-header border-0 px-4 pt-4 pb-0">
        <div>
             <h5 class="modal-title fw-bold ">New Application</h5>
             <p class="text-secondary small mb-0">Customize your loan details</p>
        </div>
        <button type="button" class="btn-close bg-light rounded-circle p-2" data-bs-dismiss="modal"></button>
      </div>

      <form action="apply_loan.php" method="POST">
        <div class="modal-body p-4">
          
          <!-- Step 1: Limits & Type -->
          <div class="mb-4">
              <label class="form-label small fw-bold text-uppercase mb-3" >
                  <span class="badge bg-dark text-white rounded-circle me-1" style="width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-size: 10px;">1</span>
                  Loan Details
              </label>
              
              <div class="mb-3">
                  <div class="d-flex justify-content-between small fw-bold mb-1">
                      <span class="text-secondary">Limit Usage</span>
                      <span id="limitPercentText">0%</span>
                  </div>
                  <div class="progress" style="height: 6px;">
                      <div class="progress-bar bg-success" id="limitBar" style="width: 0%"></div>
                  </div>
                  <div class="text-end text-muted small mt-1">Max: KES <?= number_format((float)$max_loan_limit) ?></div>
              </div>

              <div class="mb-3">
                  <label class="form-label small fw-bold text-uppercase text-secondary">Loan Category</label>
                  <select name="loan_type" class="form-select form-control-lg-custom" required>
                    <option value="emergency">Emergency Loan</option>
                    <option value="development">Development Loan</option>
                    <option value="business">Business Expansion</option>
                    <option value="education">Education / School Fees</option>
                  </select>
              </div>

              <div class="row g-3">
                  <div class="col-7">
                      <label class="form-label small fw-bold text-uppercase text-secondary">Amount (KES)</label>
                      <input type="number" id="modalAmount" name="amount" class="form-control form-control-lg-custom" placeholder="0" required>
                      <div class="invalid-feedback fw-bold" id="amountError">Amount exceeds your limit!</div>
                  </div>
                  <div class="col-5">
                      <label class="form-label small fw-bold text-uppercase text-secondary">Duration</label>
                      <select name="duration_months" id="modalMonths" class="form-select form-control-lg-custom" required>
                          <option value="3">3 Months</option>
                          <option value="6">6 Months</option>
                          <option value="12" selected>12 Months</option>
                          <option value="18">18 Months</option>
                          <option value="24">24 Months</option>
                      </select>
                  </div>
              </div>
          </div>

          <!-- Step 2: Guarantors -->
          <div class="mb-4 pt-3 border-top border-light">
              <label class="form-label small fw-bold text-uppercase mb-3" >
                  <span class="badge bg-dark text-white rounded-circle me-1" style="width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-size: 10px;">2</span>
                  Guarantors
              </label>
              
              <div class="row g-3">
                  <div class="col-12">
                      <label class="form-label small fw-bold text-secondary">First Guarantor</label>
                      <select name="guarantor_1" class="form-select border-0 bg-light rounded-3" required>
                          <option value="">Select a member...</option>
                          <?php foreach($other_members as $om): ?>
                              <option value="<?= $om['member_id'] ?>"><?= htmlspecialchars($om['full_name']) ?> (<?= $om['national_id'] ?>)</option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-12">
                      <label class="form-label small fw-bold text-secondary">Second Guarantor</label>
                      <select name="guarantor_2" class="form-select border-0 bg-light rounded-3" required>
                          <option value="">Select a member...</option>
                          <?php foreach($other_members as $om): ?>
                              <option value="<?= $om['member_id'] ?>"><?= htmlspecialchars($om['full_name']) ?> (<?= $om['national_id'] ?>)</option>
                          <?php endforeach; ?>
                      </select>
                  </div>
              </div>
          </div>

          <!-- Step 3: Purpose -->
          <div class="mb-4 pt-3 border-top border-light">
            <label class="form-label small fw-bold text-uppercase mb-2" >
                <span class="badge bg-dark text-white rounded-circle me-1" style="width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; font-size: 10px;">3</span>
                Purpose
            </label>
            <textarea name="notes" class="form-control bg-light border-0 rounded-3 p-3" rows="2" placeholder="Briefly describe why you need this loan..." required></textarea>
          </div>
          
          <div class="bg-light p-3 rounded-4 border border-dashed">
              <div class="d-flex justify-content-between small text-secondary mb-1">
                  <span>Interest Rating</span>
                  <span id="estInterest" class="fw-bold">KES 0</span>
              </div>
              <hr class="my-2 opacity-25">
              <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-bold ">Est. Total Payable</span>
                  <span class="fs-4 fw-bold text-success" id="estTotal">KES 0</span>
              </div>
          </div>

        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-0">
          <button type="submit" class="btn btn-lime w-100 py-3 text-uppercase letter-spacing-1 shadow-lg" id="submitBtn">
            Confirm & Apply <i class="bi bi-send-fill ms-2"></i>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="repayWalletModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content overflow-hidden">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0">Repay from Wallet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="repay_wallet">
                <div class="modal-body p-4">
                    <div class="p-4 rounded-4 mb-4 text-center" >
                        <small class="text-uppercase text-secondary ls-1 small fw-bold">Available in Wallet</small>
                        <h2 class="fw-bold  mt-1">KES <?= number_format((float)$account_balance, 2) ?></h2>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-secondary">Repayment Amount (KES)</label>
                        <input type="number" name="amount" class="form-control form-control-lg-custom" 
                               value="<?= $active_loan['current_balance'] ?>" 
                               max="<?= min($account_balance, $active_loan['current_balance']) ?>" 
                               min="10" step="0.01" required>
                        <div class="mt-2 small text-muted">
                            Min: KES 10 | Outstanding: KES <?= number_format((float)($active_loan['current_balance'] ?? 0), 2) ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-success w-100 py-3 rounded-4 shadow-lg text-uppercase fw-bold ls-1">
                        Confirm Repayment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Repayment History Drawer -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="repaymentDrawer" style="width: 450px;">
    <div class="offcanvas-header bg-forest-deep text-white p-4">
        <div>
            <h5 class="offcanvas-title fw-bold mb-1">Repayment History</h5>
            <p class="small opacity-75 mb-0" id="drawerLoanInfo">Loan details & breakdown</p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div id="repaymentLoading" class="p-5 text-center" style="display: none;">
            <div class="spinner-border text-success" role="status"></div>
            <p class="text-muted small mt-2">Loading activity...</p>
        </div>
        
        <div id="repaymentContent">
            <!-- Summary Header -->
            <div class="p-4 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-6">
                        <small class="text-uppercase text-muted fw-bold ls-1 d-block mb-1" style="font-size: 0.65rem;">Total Amount</small>
                        <h5 class="fw-bold mb-0 " id="disp_amount">KES 0</h5>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-uppercase text-muted fw-bold ls-1 d-block mb-1" style="font-size: 0.65rem;">Remaining</small>
                        <h5 class="fw-bold mb-0 text-success" id="disp_balance">KES 0</h5>
                    </div>
                </div>
            </div>

            <!-- List -->
            <div class="p-4">
                <h6 class="fw-bold  mb-4">Transaction Log</h6>
                <div id="repaymentList" class="d-flex flex-column gap-3">
                    <!-- Iterated by JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Constants
    const maxLimit = <?= $max_loan_limit ?>;
    
    // Elements
    const amountInput = document.getElementById('modalAmount');
    const monthsInput = document.getElementById('modalMonths');
    const estInterest = document.getElementById('estInterest');
    const estTotal = document.getElementById('estTotal');
    const limitBar = document.getElementById('limitBar');
    const limitPercentText = document.getElementById('limitPercentText');
    const submitBtn = document.getElementById('submitBtn');
    const amountError = document.getElementById('amountError');

    // Formatter
    const fmt = (num) => 'KES ' + num.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});

    function updateCalc() {
        const amt = parseFloat(amountInput.value) || 0;
        const months = parseFloat(monthsInput.value) || 12;
        const rate = 0.12; 

        // Update Progress Bar
        let percent = (amt / maxLimit) * 100;
        if(percent > 100) percent = 100;
        limitBar.style.width = percent + '%';
        limitPercentText.innerText = Math.round(percent) + '%';

        // Validation & Styling
        if(amt > maxLimit) {
            amountInput.classList.add('is-invalid');
            amountError.style.display = 'block';
            limitBar.classList.remove('bg-success');
            limitBar.classList.add('bg-danger');
            submitBtn.disabled = true;
        } else {
            amountInput.classList.remove('is-invalid');
            amountError.style.display = 'none';
            limitBar.classList.add('bg-success');
            limitBar.classList.remove('bg-danger');
            submitBtn.disabled = (amt <= 0);
        }

        // Calculation logic
        const interest = amt * rate * (months/12);
        const total = amt + interest;

        estInterest.innerText = fmt(Math.ceil(interest));
        estTotal.innerText = fmt(Math.ceil(total));
    }

    if(amountInput) {
        amountInput.addEventListener('input', updateCalc);
        monthsInput.addEventListener('input', updateCalc);
    }

    function viewRepayments(loanId) {
        const drawer = new bootstrap.Offcanvas(document.getElementById('repaymentDrawer'));
        const list = document.getElementById('repaymentList');
        const loading = document.getElementById('repaymentLoading');
        const content = document.getElementById('repaymentContent');
        
        loading.style.display = 'block';
        content.style.opacity = '0.3';
        list.innerHTML = '';
        
        drawer.show();
        
        fetch('ajax_get_loan_repayments.php?loan_id=' + loanId)
            .then(res => res.json())
            .then(data => {
                loading.style.display = 'none';
                content.style.opacity = '1';
                
                if (data.success) {
                    document.getElementById('drawerLoanInfo').innerText = data.loan.loan_type.toUpperCase() + ' | #' + data.loan.loan_id;
                    document.getElementById('disp_amount').innerText = 'KES ' + parseFloat(data.loan.amount).toLocaleString();
                    document.getElementById('disp_balance').innerText = 'KES ' + parseFloat(data.loan.current_balance).toLocaleString();
                    
                    if (data.repayments.length === 0) {
                        list.innerHTML = '<div class="text-center py-5"><i class="bi bi-inbox fs-2 text-muted opacity-25"></i><p class="text-muted small">No repayments found.</p></div>';
                        return;
                    }
                    
                    data.repayments.forEach(p => {
                        list.innerHTML += `
                            <div class="p-3 rounded-4 border bg-white shadow-sm d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small fw-bold ">${p.date}</div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Ref: ${p.ref} | ${p.method}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success">+ KES ${p.amount.toLocaleString()}</div>
                                    <div class="small text-muted" style="font-size: 0.65rem;">Repayment</div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    list.innerHTML = `<div class="alert alert-danger mx-3 mt-3">${data.message}</div>`;
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                content.style.opacity = '1';
                list.innerHTML = '<div class="alert alert-danger mx-3 mt-3">Failed to load repayment data.</div>';
            });
    }
</script>
</body>
</html>





