<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/ShareValuationEngine.php';

\USMS\Middleware\AuthMiddleware::requireModulePermission('shares');
$layout = LayoutManager::create('admin');

$pageTitle = "Equity & Share Management";
$svEngine = new ShareValuationEngine($conn);
$valuation = $svEngine->getValuation();

// Handle Process Exit Request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_exit') {
    $req_id = (int)$_POST['request_id'];
    $status = $_POST['status']; // 'approved' or 'rejected'
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    $req_q = $conn->prepare("SELECT * FROM withdrawal_requests WHERE withdrawal_id = ? AND source_ledger = 'shares' AND status = 'pending'");
    $req_q->bind_param("i", $req_id);
    $req_q->execute();
    $req = $req_q->get_result()->fetch_assoc();
    
    if ($req) {
        $mem_id = (int)$req['member_id'];
        $amt = (float)$req['amount'];
        $ref = $req['ref_no'];
        
        require_once __DIR__ . '/../../inc/FinancialEngine.php';
        $fEngine = new FinancialEngine($conn);
        
        try {
            if ($status === 'approved') {
                $payout_method = $_POST['payout_method'] ?? 'bank';
                $fEngine->transact([
                    'member_id' => $mem_id,
                    'amount' => $amt,
                    'action_type' => 'withdrawal_finalize',
                    'method' => $payout_method,
                    'reference' => $ref,
                    'notes' => "Exit Request Approved: " . $admin_notes
                ]);
                $conn->query("UPDATE withdrawal_requests SET status = 'completed', notes = CONCAT(IFNULL(notes, ''), '\nAdmin: ', '$admin_notes'), updated_at = NOW() WHERE withdrawal_id = $req_id");
                $conn->query("UPDATE members SET status = 'inactive' WHERE member_id = $mem_id");
                
                $msg = "<div class='alert alert-success'>Exit request approved. Member has been successfully deactivated and payout processed.</div>";
            } elseif ($status === 'rejected') {
                $fEngine->transact([
                    'member_id' => $mem_id,
                    'amount' => $amt,
                    'action_type' => 'withdrawal_revert',
                    'dest_cat' => FinancialEngine::CAT_SHARES,
                    'reference' => $ref."-REV",
                    'notes' => "Exit Request Rejected: " . $admin_notes
                ]);
                $conn->query("UPDATE withdrawal_requests SET status = 'rejected', notes = CONCAT(IFNULL(notes, ''), '\nAdmin: ', '$admin_notes'), updated_at = NOW() WHERE withdrawal_id = $req_id");
                $msg = "<div class='alert alert-warning'>Exit request rejected and shares reinstated to the member.</div>";
            }
        } catch (Exception $e) {
            $msg = "<div class='alert alert-danger'>Error processing request: " . $e->getMessage() . "</div>";
        }
    }
}

// Handle Dividend Distribution POST
$msg = $msg ?? "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'distribute_dividend') {
    $pool = (float)$_POST['dividend_pool'];
    $ref = "DIV-" . strtoupper(uniqid());
    
    if ($pool > 0) {
        try {
            if ($svEngine->distributeDividends($pool, $ref)) {
                $msg = "<div class='alert alert-success'>Successfully distributed KES " . number_format($pool, 2) . " across all shareholders.</div>";
                // Refresh valuation
                $valuation = $svEngine->getValuation();
            }
        } catch (Exception $e) {
            $msg = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch Top Shareholders
$sqlTop = "SELECT m.full_name, ms.units_owned, ms.total_amount_paid, (ms.units_owned / ?) * 100 as ownership_pct 
           FROM member_shareholdings ms 
           JOIN members m ON ms.member_id = m.member_id 
           WHERE ms.units_owned > 0 
           ORDER BY ms.units_owned DESC LIMIT 5";
$stmt = $conn->prepare($sqlTop);
$totalU = (float)$valuation['total_units'] ?: 1;
$stmt->bind_param("d", $totalU);
$stmt->execute();
$topHolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Pending Exit Requests
$sqlExits = "SELECT w.*, m.full_name 
             FROM withdrawal_requests w
             JOIN members m ON w.member_id = m.member_id
             WHERE w.source_ledger = 'shares' AND w.status = 'pending'
             ORDER BY w.created_at ASC";
$exitsResult = $conn->query($sqlExits);
$pendingExits = $exitsResult ? $exitsResult->fetch_all(MYSQLI_ASSOC) : [];

// Fetch Recent Share Transactions (Global)
$sqlHistory = "SELECT st.created_at, st.reference_no, st.units as share_units, st.unit_price, st.total_value, st.transaction_type, m.full_name 
               FROM share_transactions st
               LEFT JOIN members m ON st.member_id = m.member_id 
               ORDER BY st.created_at DESC";
$historyResult = $conn->query($sqlHistory);

$transactions = [];
$chartLabels = [];
$chartData = [];
$runningUnits = 0;

if ($historyResult) {
    while ($row = $historyResult->fetch_assoc()) {
        $row['share_units'] = (float)$row['total_value'] / (float)$valuation['price'];
        $row['unit_price'] = (float)$valuation['price'];
        $transactions[] = $row;
    }
    
    // Build Chart Data (Chronological)
    $chronological_transactions = array_reverse($transactions);
    foreach ($chronological_transactions as $txn) {
        if ($txn['transaction_type'] === 'purchase' || $txn['transaction_type'] === 'migration') {
            $runningUnits += (float)$txn['share_units'];
        }
        $chartLabels[] = date('M d', strtotime($txn['created_at'])); 
        $chartData[] = $runningUnits * (float)$valuation['price'];
    }
}

$jsLabels = json_encode($chartLabels);
$jsData   = json_encode($chartData);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<?php $layout->header($pageTitle); ?>
<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle ?? ""); ?>
        <div class="container-fluid px-4 py-4">
    <style>
        :root {
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --brand-dark: #0f172a; 
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
            margin-left: 280px; 
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

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

        .text-lime { color: #65a30d; }
        .bg-lime-subtle { background-color: rgba(190, 242, 100, 0.25); color: #3f6212; }
        .icon-box {
            width: 52px; height: 52px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .display-amount { font-size: 2.75rem; font-weight: 800; letter-spacing: -0.04em; }

        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }

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
    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>


            
            <?php if (!empty($msg)): ?>
                <div class="mb-4"><?= $msg ?></div>
            <?php endif; ?>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--brand-dark);">Corporate Equity</h2>
                    <p class="text-secondary mb-0">Global Sacco Share Portfolio & Valuation.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-lime d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#dividendModal">
                        <i class="bi bi-cash-stack"></i>
                        <span>Distribute Dividend</span>
                    </button>
                </div>
            </div>

            <?php if (!empty($pendingExits)): ?>
            <div class="row mb-5">
                <div class="col-12">
                     <div class="card border-0 shadow-sm rounded-4 border-warning border-start border-4">
                        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i> Pending SACCO Exit Requests</h5>
                            <span class="badge bg-warning text-dark rounded-pill fw-bold"><?= count($pendingExits) ?> Pending</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Date Requested</th>
                                            <th>Member</th>
                                            <th>Ref No</th>
                                            <th>Refund Amount</th>
                                            <th>Phone</th>
                                            <th class="text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingExits as $exit): ?>
                                            <tr>
                                                <td class="ps-4"><?= date('d M Y, h:i A', strtotime($exit['created_at'])) ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($exit['full_name']) ?></span></td>
                                                <td><span class="font-monospace text-muted small bg-light px-2 py-1 rounded border"><?= htmlspecialchars($exit['ref_no']) ?></span></td>
                                                <td class="fw-bold text-danger">KES <?= number_format((float)$exit['amount'], 2) ?></td>
                                                <td><?= htmlspecialchars($exit['phone_number']) ?></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-dark rounded-pill px-4" onclick='openExitModal(<?= json_encode($exit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                        Review Exit
                                                    </button>
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
            <?php endif; ?>

            <div class="row g-4 mb-5">
                
                <div class="col-xl-4 col-lg-5 col-md-12">
                    <div class="stat-card hero-card d-flex flex-column justify-content-between h-100">
                        <div class="d-flex justify-content-between align-items-start z-1">
                            <span class="badge bg-white bg-opacity-10 border border-white border-opacity-10 rounded-pill px-3 py-2 fw-normal backdrop-blur">
                                <i class="bi bi-bank me-2"></i> Corporate Net Worth (NAV)
                            </span>
                            <i class="bi bi-shield-check fs-4 opacity-50"></i>
                        </div>
                        
                        <div class="mt-4 z-1">
                            <h1 class="display-amount mb-0">KES <?= number_format((float)$valuation['equity'], 2) ?></h1>
                            <p class="opacity-75 mb-0 mt-2">Total Sacco Equity</p>
                        </div>

                        <div class="mt-4 pt-3 border-top border-white border-opacity-10 d-flex justify-content-between align-items-end z-1">
                            <div>
                                <small class="text-lime fw-bold">Current Unit Price</small>
                                <div class="fs-5 fw-bold text-white">KES <?= number_format((float)$valuation['price'], 2) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-lime-subtle border-0">Calculated Dynamically</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8 col-lg-7 col-md-12">
                    <div class="row g-4 h-100">
                        <div class="col-md-5">
                            <div class="stat-card">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <p class="text-uppercase text-muted small fw-bold mb-1">Total Issued Units</p>
                                        <h3 class="fw-bold mb-0"><?= number_format((float)$valuation['total_units'], 4) ?></h3>
                                    </div>
                                    <div class="icon-box bg-lime-subtle">
                                        <i class="bi bi-pie-chart-fill"></i>
                                    </div>
                                </div>
                                <hr class="border-light my-3">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                    <span class="text-muted small">Total Assets</span>
                                    <span class="fw-bold text-success">KES <?= number_format((float)$valuation['total_assets'], 2) ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted small">Total Liabilities</span>
                                    <span class="fw-bold text-danger">KES <?= number_format((float)$valuation['liabilities'], 2) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="stat-card bg-light border-0">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <p class="text-uppercase text-muted small fw-bold mb-0">Corporate Portfolio Growth</p>
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

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">Global Share Transactions</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive p-3">
                                <table id="historyTable" class="table table-custom table-hover align-middle mb-0 w-100">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-3">Date</th>
                                            <th>Member</th>
                                            <th>Ref No.</th>
                                            <th>Units</th>
                                            <th>Total Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($transactions)): foreach ($transactions as $row): ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                                                        <span class="small text-muted"><?= date('H:i A', strtotime($row['created_at'])) ?></span>
                                                    </div>
                                                </td>
                                                <td><span class="fw-medium text-primary"><?= htmlspecialchars($row['full_name'] ?? 'System') ?></span></td>
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
                                                        <span class="fw-medium"><?= number_format((float)$row['share_units'], 2) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="fw-bold">KES <?= number_format((float)$row['total_value'], 2) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white border-0 py-3 px-4">
                            <h5 class="fw-bold mb-0">Top Shareholders</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush mt-2">
                                <?php foreach ($topHolders as $idx => $holder): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-4 py-3 bg-transparent border-light">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="text-muted fw-bold small">#<?= $idx + 1 ?></div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($holder['full_name']) ?></div>
                                                <div class="small text-muted"><?= number_format((float)$holder['units_owned'], 2) ?> Units</div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-success">KES <?= number_format((float)$holder['units_owned'] * (float)$valuation['price'], 2) ?></div>
                                            <span class="badge bg-primary-subtle text-primary border-0"><?= number_format((float)$holder['ownership_pct'], 2) ?>%</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
             <div class="modal fade" id="exitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold">Review Exit Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="process_exit">
                    <input type="hidden" name="request_id" id="exit_req_id">
                    
                    <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                        <span class="text-muted">Member</span>
                        <span class="fw-bold" id="exit_member_name"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                        <span class="text-muted">Refund Amount</span>
                        <span class="fw-bold text-danger fs-5" id="exit_amount"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Wait... Approve or Reject?</label>
                        <select name="status" class="form-select bg-light" id="exitStatusSelect" required onchange="togglePayout()">
                            <option value="">-- Choose Action --</option>
                            <option value="approved">Approve & Pay (Complete Exit)</option>
                            <option value="rejected">Reject & Cancel</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="payoutMethodSection" style="display:none;">
                        <label class="form-label fw-bold small text-muted text-uppercase">Payout Method / Ledger Source</label>
                        <select name="payout_method" class="form-select bg-light">
                            <option value="bank">SACCO Bank Account</option>
                            <option value="cash">SACCO Cash at Hand</option>
                            <option value="mpesa">M-Pesa B2C/Paybill</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Admin Notes</label>
                        <textarea name="admin_notes" class="form-control bg-light border-0" rows="2" placeholder="Reason for approval/rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-white border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark rounded-pill px-4">Submit Action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dividend Modal -->
<div class="modal fade" id="dividendModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Distribute Dividend</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="distribute_dividend">
                    <div class="mb-3">
                        <label class="form-label text-uppercase small fw-bold text-muted">Total Dividend Pool (KES)</label>
                        <input type="number" step="0.01" name="dividend_pool" class="form-control form-control-lg bg-light" placeholder="Enter Amount" required>
                    </div>
                    <div class="alert alert-info border-0 rounded-3 small mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <span>This will be distributed proportionally to all unit holders based on their ownership percentage.</span>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-white border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill px-4">Confirm Distribution</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function openExitModal(exit) {
        document.getElementById('exit_req_id').value = exit.withdrawal_id;
        document.getElementById('exit_member_name').innerText = exit.full_name;
        document.getElementById('exit_amount').innerText = "KES " + parseFloat(exit.amount).toLocaleString('en-US', {minimumFractionDigits: 2});
        
        document.getElementById('exitStatusSelect').value = '';
        togglePayout();
        
        new bootstrap.Modal(document.getElementById('exitModal')).show();
    }

    function togglePayout() {
        var stat = document.getElementById('exitStatusSelect').value;
        document.getElementById('payoutMethodSection').style.display = (stat === 'approved') ? 'block' : 'none';
    }

    $(document).ready(function() {
        $('#historyTable').DataTable({
            order: [[0, 'desc']], 
            pageLength: 5,
            dom: 't<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    });

    const ctx = document.getElementById('growthChart').getContext('2d');
    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(190, 242, 100, 0.5)');
    gradient.addColorStop(1, 'rgba(190, 242, 100, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= $jsLabels ?>,
            datasets: [{
                data: <?= $jsData ?>,
                borderColor: '#65a30d',
                backgroundColor: gradient,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { display: false }, y: { display: false } }
        }
    });
</script>
</body>
</html>
