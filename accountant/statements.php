<?php
// accountant/statements.php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';

// 1. Auth Check
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['accountant', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

// 2. Initialize Filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default: 1st of current month
$end_date   = $_GET['end_date'] ?? date('Y-m-d');   // Default: Today
$member_id  = !empty($_GET['member_id']) ? intval($_GET['member_id']) : null;
$txn_type   = $_GET['txn_type'] ?? 'all';

// 3. Data Fetching
$transactions = [];
$member_details = null;
$opening_balance = 0;
$total_debits = 0;
$total_credits = 0;

// A. Get Member Details (If selected)
if ($member_id) {
    $stmt = $conn->prepare("SELECT full_name, national_id, email, phone, account_balance FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $member_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // B. Calculate Opening Balance (Sum of transactions BEFORE start_date)
    $sql_open = "SELECT 
        SUM(CASE WHEN transaction_type IN ('deposit', 'repayment', 'income', 'share_capital') THEN amount ELSE 0 END) -
        SUM(CASE WHEN transaction_type IN ('withdrawal', 'loan_disbursement', 'expense') THEN amount ELSE 0 END) as balance
        FROM transactions 
        WHERE member_id = ? AND created_at < ?";
    
    // Append ' 00:00:00' to start date for SQL comparison
    $date_calc = $start_date . ' 00:00:00';
    $stmt = $conn->prepare($sql_open);
    $stmt->bind_param("is", $member_id, $date_calc);
    $stmt->execute();
    $opening_balance = $stmt->get_result()->fetch_assoc()['balance'] ?? 0;
    $stmt->close();
}

// C. Fetch Statement Transactions
$where = "DATE(t.created_at) BETWEEN ? AND ?";
$params = [$start_date, $end_date];
$types = "ss";

if ($member_id) {
    $where .= " AND t.member_id = ?";
    $params[] = $member_id;
    $types .= "i";
}

if ($txn_type !== 'all') {
    $where .= " AND t.transaction_type = ?";
    $params[] = $txn_type;
    $types .= "s";
}

$sql = "SELECT t.*, m.full_name 
        FROM transactions t 
        LEFT JOIN members m ON t.member_id = m.member_id 
        WHERE $where 
        ORDER BY t.created_at ASC"; 

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// 4. Fetch Members for Dropdown
$members_list = [];
$res = $conn->query("SELECT member_id, full_name, national_id FROM members ORDER BY full_name ASC");
while ($row = $res->fetch_assoc()) $members_list[] = $row;

$pageTitle = "Statements & Reports";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Teal / Mint Theme Palette */
            --primary-color: #008080;       /* Teal */
            --primary-hover: #006666;
            --secondary-color: #20c997;     /* Mint */
            --accent-bg: #e6f7f4;           /* Very Light Mint */
            --text-dark: #2c3e50;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: 1px solid rgba(0, 128, 128, 0.15);
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        body {
            background-color: #f4f7f6;
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
        }

        /* Layout Layout */
        .main-content-wrapper { margin-left: 260px; transition: margin-left 0.3s; min-height: 100vh; padding-bottom: 50px; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

        /* Custom Components */
        .hd-glass {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: var(--glass-border);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .btn-teal {
            background-color: var(--primary-color);
            color: white;
            border: none;
            transition: all 0.2s;
        }
        .btn-teal:hover {
            background-color: var(--primary-hover);
            color: white;
            transform: translateY(-1px);
        }

        .text-teal { color: var(--primary-color) !important; }
        .bg-teal-soft { background-color: var(--accent-bg) !important; }

        /* Summary Cards */
        .summary-card {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #ddd;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            height: 100%;
        }
        .summary-card.opening { border-left-color: #6c757d; }
        .summary-card.credit { border-left-color: #20c997; }
        .summary-card.debit { border-left-color: #dc3545; }
        .summary-card.closing { border-left-color: var(--primary-color); background: var(--accent-bg); }

        .summary-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; color: #7f8c8d; }
        .summary-value { font-size: 1.25rem; font-weight: 700; margin-top: 5px; }

        /* Table Styling */
        .table-custom thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            font-size: 0.85rem;
            border: none;
            padding: 12px 15px;
        }
        .table-custom tbody td {
            vertical-align: middle;
            font-size: 0.9rem;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .table-custom tbody tr:hover { background-color: var(--accent-bg); }
        .ref-pill { background: #eee; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.8rem; color: #555; }

        /* Print Styles */
        @media print {
            body { background-color: #fff !important; }
            .no-print, .sidebar, .hd-glass-nav { display: none !important; }
            .main-content-wrapper { margin: 0 !important; width: 100% !important; padding: 0 !important; }
            .hd-glass { box-shadow: none; border: none; background: none; }
            .btn { display: none; }
            .table-custom thead th { background-color: #f0f0f0 !important; color: #000 !important; border-bottom: 2px solid #000; }
            .summary-card { border: 1px solid #ddd; border-left-width: 4px !important; }
            a { text-decoration: none; color: #000; }
        }
    </style>
</head>
<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <div>
                    <h3 class="fw-bold mb-1 text-teal">Account Statements</h3>
                    <p class="text-muted small mb-0">Generate ledger reports and member account history.</p>
                </div>
                <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-printer me-2"></i>Print Report
                </button>
            </div>

            <div class="hd-glass p-4 mb-4 no-print">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase text-teal">Select Member</label>
                        <select name="member_id" class="form-select border-0 bg-light">
                            <option value="">-- General Ledger (All) --</option>
                            <?php foreach ($members_list as $m): ?>
                                <option value="<?= $m['member_id'] ?>" <?= ($member_id == $m['member_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['full_name']) ?> (<?= $m['national_id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-uppercase text-teal">Type</label>
                        <select name="txn_type" class="form-select border-0 bg-light">
                            <option value="all">All Types</option>
                            <option value="deposit" <?= $txn_type == 'deposit' ? 'selected' : '' ?>>Deposits</option>
                            <option value="withdrawal" <?= $txn_type == 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                            <option value="expense" <?= $txn_type == 'expense' ? 'selected' : '' ?>>Expenses</option>
                            <option value="loan_repayment" <?= $txn_type == 'loan_repayment' ? 'selected' : '' ?>>Repayments</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-uppercase text-teal">From</label>
                        <input type="date" name="start_date" class="form-control border-0 bg-light" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-uppercase text-teal">To</label>
                        <input type="date" name="end_date" class="form-control border-0 bg-light" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-teal w-100 fw-bold"><i class="bi bi-funnel me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-1 text-end">
                        <a href="statements.php" class="btn btn-light w-100 text-muted" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>

            <div class="hd-glass p-5" id="printArea">
                
                <div class="border-bottom pb-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-6">
                            <h2 class="fw-bold text-uppercase mb-1 text-teal">Statement of Account</h2>
                            <p class="text-muted fw-semibold mb-0"><?= defined('SITE_NAME') ? SITE_NAME : 'SACCO System' ?></p>
                            <p class="text-muted small">Generated on: <?= date('d M Y, H:i A') ?></p>
                        </div>
                        <div class="col-6 text-end">
                            <?php if ($member_details): ?>
                                <h4 class="fw-bold mb-0"><?= htmlspecialchars($member_details['full_name']) ?></h4>
                                <p class="mb-0 text-muted small">ID: <?= htmlspecialchars($member_details['national_id']) ?> | Phone: <?= htmlspecialchars($member_details['phone']) ?></p>
                                <div class="mt-2 text-muted small">
                                    Current Live Balance: <span class="fw-bold text-dark">KES <?= number_format($member_details['account_balance'], 2) ?></span>
                                </div>
                            <?php else: ?>
                                <h4 class="fw-bold mb-0">General Ledger</h4>
                                <p class="mb-0 text-muted small">Consolidated Office Transactions</p>
                            <?php endif; ?>
                            
                            <div class="mt-3 d-inline-block px-3 py-1 rounded-pill bg-teal-soft text-teal small fw-bold border border-success border-opacity-25">
                                <?= date('d M Y', strtotime($start_date)) ?> &mdash; <?= date('d M Y', strtotime($end_date)) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($member_id): ?>
                <div class="row mb-5 g-3">
                    <div class="col-md-3 col-6">
                        <div class="summary-card opening">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="summary-label">Opening Bal</div>
                                    <div class="summary-value">KES <?= number_format($opening_balance, 2) ?></div>
                                </div>
                                <i class="bi bi-wallet2 text-secondary fs-4 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="summary-card credit">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="summary-label">Total Credits (In)</div>
                                    <div class="summary-value text-success">KES <span id="sum-credit">0.00</span></div>
                                </div>
                                <i class="bi bi-arrow-down-circle text-success fs-4 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="summary-card debit">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="summary-label">Total Debits (Out)</div>
                                    <div class="summary-value text-danger">KES <span id="sum-debit">0.00</span></div>
                                </div>
                                <i class="bi bi-arrow-up-circle text-danger fs-4 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="summary-card closing">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="summary-label text-teal">Closing Bal</div>
                                    <div class="summary-value text-teal">KES <span id="closing-bal">0.00</span></div>
                                </div>
                                <i class="bi bi-safe text-teal fs-4 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="table-responsive rounded-3 border">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Ref No.</th>
                                <th>Description / Particulars</th>
                                <?php if (!$member_id): ?><th>Member / Entity</th><?php endif; ?>
                                <th class="text-end">Debit (Out)</th>
                                <th class="text-end">Credit (In)</th>
                                <?php if ($member_id): ?><th class="text-end">Running Bal</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $running_balance = $opening_balance;
                            $period_debits = 0;
                            $period_credits = 0;

                            if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="<?= $member_id ? 6 : 6 ?>" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                        No transactions found for this period.
                                    </td>
                                </tr>
                            <?php else: 
                                foreach ($transactions as $t): 
                                    $type = $t['transaction_type'];
                                    $is_credit = in_array($type, ['deposit', 'repayment', 'income', 'share_capital']);
                                    $amount = floatval($t['amount']);
                                    
                                    $credit_amt = $is_credit ? $amount : 0;
                                    $debit_amt  = !$is_credit ? $amount : 0;
                                    
                                    $period_credits += $credit_amt;
                                    $period_debits += $debit_amt;

                                    $running_balance = $running_balance + $credit_amt - $debit_amt;
                            ?>
                                <tr>
                                    <td class="text-nowrap text-secondary"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                    <td><span class="ref-pill"><?= htmlspecialchars($t['reference_no']) ?></span></td>
                                    <td>
                                        <div class="fw-bold text-dark small text-uppercase mb-1">
                                            <?= str_replace('_', ' ', $type) ?>
                                        </div>
                                        <div class="text-muted small fst-italic"><?= htmlspecialchars($t['notes']) ?></div>
                                    </td>
                                    <?php if (!$member_id): ?>
                                        <td class="small fw-semibold text-secondary"><?= htmlspecialchars($t['full_name'] ?? 'System/Office') ?></td>
                                    <?php endif; ?>
                                    
                                    <td class="text-end">
                                        <?= $debit_amt > 0 ? '<span class="text-danger fw-bold">-'.number_format($debit_amt, 2).'</span>' : '<span class="text-muted opacity-25">-</span>' ?>
                                    </td>
                                    <td class="text-end">
                                        <?= $credit_amt > 0 ? '<span class="text-success fw-bold">+'.number_format($credit_amt, 2).'</span>' : '<span class="text-muted opacity-25">-</span>' ?>
                                    </td>
                                    
                                    <?php if ($member_id): ?>
                                        <td class="text-end fw-bold text-dark bg-light"><?= number_format($running_balance, 2) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <?php if (!$member_id): ?>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td colspan="<?= $member_id ? 3 : 4 ?>" class="text-end text-uppercase text-muted small pt-3">Totals for Period:</td>
                                <td class="text-end text-danger pt-3 border-top border-dark"><?= number_format($period_debits, 2) ?></td>
                                <td class="text-end text-success pt-3 border-top border-dark"><?= number_format($period_credits, 2) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="mt-5 pt-4 border-top text-center">
                    <p class="small text-muted mb-1">
                        This is a computer-generated document. No signature is required.
                    </p>
                    <p class="small text-muted fst-italic">Errors and Omissions Excepted (E&OE).</p>
                </div>

            </div>
        </div>
         <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($member_id): ?>
<script>
    // Inject calculated totals into Summary Cards
    document.getElementById('sum-credit').innerText = "<?= number_format($period_credits, 2) ?>";
    document.getElementById('sum-debit').innerText = "<?= number_format($period_debits, 2) ?>";
    document.getElementById('closing-bal').innerText = "<?= number_format($running_balance, 2) ?>";
</script>
<?php endif; ?>
</body>
</html>