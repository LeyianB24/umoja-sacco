<?php
// member/loans.php
// UI: Forest Green Premium | Layout: Grid System

session_start();

// --- 1. CONFIG & AUTH ---
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Validate Login
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// --- 2. DATA FETCHING ---

// A. Get Savings & Calculate Limit
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM savings WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$savings_res = $stmt->get_result()->fetch_assoc();
$total_savings = $savings_res['total'] ?? 0;
$stmt->close();

// Get Wallet Balance (For withdrawing loan funds)
$stmt = $conn->prepare("SELECT account_balance FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$cur_bal = $stmt->get_result()->fetch_assoc()['account_balance'] ?? 0;
$stmt->close();

// Policy: Limit is 3x Savings
$max_loan_limit = $total_savings * 3;

// B. Check for Active or Pending Loans
$active_loan = null;
$pending_loan = null;

$stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ? AND status NOT IN ('completed', 'rejected') ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    if ($row['status'] === 'pending') {
        $pending_loan = $row;
    } else {
        $active_loan = $row;
    }
}
$stmt->close();

// C. Calculate Repayment Progress (If Active)
$progress_percent = 0;
$repaid_amount = 0;
$outstanding_balance = 0;
$total_payable = 0;

if ($active_loan) {
    $loan_id = $active_loan['loan_id'];
    
    // Get total paid so far
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total_paid FROM loan_repayments WHERE loan_id = ?");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $repaid_amount = $stmt->get_result()->fetch_assoc()['total_paid'];
    $stmt->close();
    
    // Simple Interest Logic (12% flat) - Adjust logic if your DB stores the final amount
    $total_payable = $active_loan['amount'] * 1.12; 
    $outstanding_balance = max(0, $total_payable - $repaid_amount);
    
    if ($total_payable > 0) {
        $progress_percent = ($repaid_amount / $total_payable) * 100;
    }
}

// D. Fetch History
$history = [];
$stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$res_history = $stmt->get_result();
while($row = $res_history->fetch_assoc()) $history[] = $row;
$stmt->close();

$pageTitle = "My Loans";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
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
        }

        @media (max-width: 991px) {
            .main-content { margin-left: 0; }
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
        .status-pending { background: #FEF3C7; color: #92400E; }
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
</head>
<body>

<div class="dashboard-wrapper">
    
    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="main-content">
        
        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <div class="p-4 p-lg-5">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
                <div>
                    <h2 class="fw-bold mb-1 text-dark">Loan Portfolio</h2>
                    <p class="text-secondary mb-0">Manage your finances and track repayment progress.</p>
                </div>
                
                <?php if ($active_loan || $pending_loan): ?>
                    <div class="d-flex gap-2">
                        <?php if($cur_bal > 0): ?>
                            <a href="mpesa_request.php?type=withdraw" class="btn btn-dark fw-bold px-4 py-3 rounded-4 shadow-sm">
                                <i class="bi bi-wallet2 me-2"></i> Withdraw Funds
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-light border text-secondary fw-bold px-4 py-3 rounded-4" style="opacity: 0.8; cursor: not-allowed;">
                            <i class="bi bi-lock-fill me-2"></i> Limit Reached
                        </button>
                    </div>
                <?php else: ?>
                    <div class="d-flex gap-2">
                        <?php if($cur_bal > 0): ?>
                            <a href="mpesa_request.php?type=withdraw" class="btn btn-dark fw-bold px-4 py-3 rounded-4 shadow-sm">
                                <i class="bi bi-wallet2 me-2"></i> Withdraw Funds
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-lime shadow-lg py-3 rounded-4" data-bs-toggle="modal" data-bs-target="#applyLoanModal">
                            <i class="bi bi-plus-lg me-2"></i> Apply for Loan
                        </button>
                    </div>
                <?php endif; ?>
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
                                <span class="small text-secondary">Your request for <strong>KES <?= number_format($pending_loan['amount']) ?></strong> is currently being processed by the committee.</span>
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
                                    <h1 class="display-4 fw-bold mb-0">KES <?= number_format($outstanding_balance) ?></h1>
                                    <span class="label-text">Outstanding Balance</span>
                                </div>
                                <div class="text-end d-none d-sm-block">
                                    <span class="label-text d-block mb-1">Due Date</span>
                                    <span class="fw-bold fs-5"><?= date('M d, Y', strtotime('+30 days')) // Example logic ?></span>
                                </div>
                            </div>

                            <div class="row align-items-end">
                                <div class="col-lg-7 mb-4 mb-lg-0">
                                    <div class="d-flex justify-content-between text-white small fw-bold mb-2">
                                        <span>Repayment Progress</span>
                                        <span><?= number_format($progress_percent, 0) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 8px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $progress_percent ?>%"></div>
                                    </div>
                                    <div class="mt-3 text-white opacity-75 small">
                                        Paid: KES <?= number_format($repaid_amount) ?> of KES <?= number_format($total_payable) ?>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <a href="mpesa_request.php?type=loan_repayment&loan_id=<?= $active_loan['loan_id'] ?>" 
                                       class="btn btn-lime w-100 py-3">
                                        Repay Now <i class="bi bi-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!$pending_loan): ?>
                        <div class="card-clean p-5 text-center mb-4 border-dashed h-100 d-flex flex-column justify-content-center align-items-center">
                            <div class="icon-box lime rounded-circle mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <h3 class="fw-bold text-dark">You are Eligible!</h3>
                            <p class="text-secondary mb-4 col-lg-8 mx-auto">You currently have no active debts. Based on your savings, you qualify for an instant loan up to the limit below.</p>
                            <h2 class="text-success fw-bold">KES <?= number_format($max_loan_limit) ?></h2>
                        </div>
                    <?php endif; ?>

                    <div class="card-clean">
                        <div class="p-4 border-bottom border-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark">Recent History</h6>
                            <button class="btn btn-sm btn-light border"><i class="bi bi-download me-1"></i> Export</button>
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
                                            'active' => 'status-active',
                                            'rejected' => 'status-rejected',
                                            default => 'status-pending'
                                        };
                                    ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?= date('M d, Y', strtotime($h['created_at'])) ?></td>
                                            <td class="text-capitalize text-secondary"><?= $h['loan_type'] ?></td>
                                            <td class="fw-bold">KES <?= number_format($h['amount']) ?></td>
                                            <td><span class="badge-pill <?= $statusClass ?>"><?= ucfirst($h['status']) ?></span></td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-light border rounded-circle" style="width:32px; height:32px;"><i class="bi bi-chevron-right"></i></button>
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
                        
                        <div class="card-clean p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box me-3">
                                    <i class="bi bi-safe"></i>
                                </div>
                                <div>
                                    <span class="text-secondary small fw-bold text-uppercase">Total Savings</span>
                                    <h5 class="fw-bold mb-0">KES <?= number_format($total_savings) ?></h5>
                                </div>
                            </div>
                            <hr class="border-light opacity-50 my-2">
                            <div class="d-flex align-items-center mt-2">
                                <div class="icon-box lime me-3">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <div>
                                    <span class="text-secondary small fw-bold text-uppercase">Max Loan Limit (3x)</span>
                                    <h5 class="fw-bold text-success mb-0">KES <?= number_format($max_loan_limit) ?></h5>
                                </div>
                            </div>
                        </div>

                        <div class="card-clean bg-dark text-white p-4" style="background: var(--forest-deep);">
                            <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Quick Terms</h5>
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-3 small opacity-75">
                                <li class="d-flex align-items-start">
                                    <i class="bi bi-check-circle-fill text-warning me-2 mt-1"></i>
                                    Interest rate is fixed at 12% p.a on reducing balance.
                                </li>
                                <li class="d-flex align-items-start">
                                    <i class="bi bi-check-circle-fill text-warning me-2 mt-1"></i>
                                    Loans require 2 active guarantors.
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
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="applyLoanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content overflow-hidden">
      
      <div class="modal-header border-0 px-4 pt-4 pb-0">
        <div>
             <h5 class="modal-title fw-bold text-dark">New Application</h5>
             <p class="text-secondary small mb-0">Customize your loan details</p>
        </div>
        <button type="button" class="btn-close bg-light rounded-circle p-2" data-bs-dismiss="modal"></button>
      </div>

      <form action="apply_loan.php" method="POST">
        <div class="modal-body p-4">
          
          <div class="mb-4">
              <div class="d-flex justify-content-between small fw-bold mb-1">
                  <span class="text-secondary">Limit Usage</span>
                  <span id="limitPercentText">0%</span>
              </div>
              <div class="progress" style="height: 6px;">
                  <div class="progress-bar bg-success" id="limitBar" style="width: 0%"></div>
              </div>
              <div class="text-end text-muted small mt-1">Max: KES <?= number_format($max_loan_limit) ?></div>
          </div>

          <div class="mb-3">
              <label class="form-label small fw-bold text-uppercase text-secondary">Loan Type</label>
              <select name="loan_type" class="form-select form-control-lg-custom" required>
                <option value="emergency">Emergency Loan</option>
                <option value="development">Development Loan</option>
                <option value="business">Business Expansion</option>
                <option value="education">Education / School Fees</option>
              </select>
          </div>

          <div class="row g-3 mb-3">
              <div class="col-7">
                  <label class="form-label small fw-bold text-uppercase text-secondary">Amount (KES)</label>
                  <input type="number" id="modalAmount" name="amount" class="form-control form-control-lg-custom" placeholder="0" required>
                  <div class="invalid-feedback fw-bold" id="amountError">Amount exceeds your limit!</div>
              </div>
              <div class="col-5">
                  <label class="form-label small fw-bold text-uppercase text-secondary">Months</label>
                  <input type="number" id="modalMonths" name="repayment_period" value="12" min="1" max="36" class="form-control form-control-lg-custom" required>
              </div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold text-uppercase text-secondary">Purpose of funds</label>
            <textarea name="notes" class="form-control bg-light border-0 rounded-3 p-3" rows="2" placeholder="Briefly describe why you need this loan..." required></textarea>
          </div>
          
          <div class="bg-light p-3 rounded-4 border border-dashed">
              <div class="d-flex justify-content-between small text-secondary mb-1">
                  <span>Interest (12%)</span>
                  <span id="estInterest" class="fw-bold">KES 0</span>
              </div>
              <hr class="my-2 opacity-25">
              <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-bold text-dark">Total Repayment</span>
                  <span class="fs-4 fw-bold text-success" id="estTotal">KES 0</span>
              </div>
          </div>

        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-0">
          <button type="submit" class="btn btn-lime w-100 py-3 text-uppercase letter-spacing-1" id="submitBtn">Submit Application</button>
        </div>
      </form>
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
</script>
</body>
</html>