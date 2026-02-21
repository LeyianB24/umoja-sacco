<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');
// Initialize Layout Manager
$layout = LayoutManager::create('admin');
// accountant/payments.php

require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

// 1. Auth Check - Using the more secure standard
require_admin();
require_permission();

// 2. Handle Form Submission (Record New Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_txn') {
    verify_csrf_token();
    
    $member_id = !empty($_POST['member_id']) ? intval($_POST['member_id']) : null;
    $type      = $_POST['transaction_type'] ?? '';
    $amount    = floatval($_POST['amount'] ?? 0);
    $notes     = trim($_POST['notes'] ?? '');
    $ref_no    = trim($_POST['reference_no'] ?? '');
    $date      = $_POST['txn_date'] ?? date('Y-m-d');

    // Basic Validation
    if ($amount <= 0) {
        $_SESSION['error'] = "Amount must be greater than zero.";
    } elseif (in_array($type, ['deposit', 'withdrawal', 'loan_repayment', 'share_capital']) && empty($member_id)) {
        $_SESSION['error'] = "Member selection is required for this transaction type.";
    } else {
        $conn->begin_transaction();
        try {
            $method = $_POST['payment_method'] ?? 'cash';
            
            // Map types to categories
            $category = match($type) {
                'deposit'        => 'savings',
                'withdrawal'     => 'wallet',
                'share_capital'  => 'shares',
                'loan_repayment' => 'loans',
                'expense'        => 'expense',
                'income'         => 'income',
                default          => 'general'
            };

            $related_id = null;
            $related_table = null;

            if ($type === 'loan_repayment' && $member_id) {
                $l_res = $conn->query("SELECT loan_id FROM loans WHERE member_id = $member_id AND status = 'disbursed' LIMIT 1");
                if ($l_res && $l_res->num_rows > 0) {
                    $related_id = $l_res->fetch_assoc()['loan_id'];
                    $related_table = 'loans';
                }
            } elseif (in_array($type, ['expense', 'income'])) {
                $unified_id = $_POST['unified_asset_id'] ?? 'other_0';
                if ($unified_id !== 'other_0') {
                    list($source, $related_id) = explode('_', $unified_id);
                    $related_id = (int)$related_id;
                    $related_table = 'investments';
                }
            }

            $ok = TransactionHelper::record([
                'member_id'     => $member_id,
                'amount'        => $amount,
                'type'          => $type,
                'category'      => $category,
                'ref_no'        => $ref_no,
                'notes'         => $notes,
                'method'        => $method,
                'related_id'    => $related_id,
                'related_table' => $related_table
            ]);

            if (!$ok) throw new Exception("Failed to record transaction in ledger.");

            $conn->commit();

            // Trigger Deposit Notification if attributed to a member
            if ($member_id && in_array($type, ['deposit', 'share_capital'])) {
                require_once __DIR__ . '/../../inc/notification_helpers.php';
                send_notification($conn, (int)$member_id, 'deposit_success', ['amount' => $amount, 'ref' => $ref_no]);
            }

            $_SESSION['success'] = "Transaction recorded successfully!";
            header("Location: payments.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
}

// A. Members List
$members = [];
$res = $conn->query("SELECT member_id, full_name, national_id FROM members ORDER BY full_name ASC");
while ($row = $res->fetch_assoc()) $members[] = $row;

// B. Active Investments (For attribution)
$investments = $conn->query("SELECT investment_id, title FROM investments WHERE status = 'active' ORDER BY title ASC");
$investments_all = $investments->fetch_all(MYSQLI_ASSOC);

// C. Transactions List
$where = "1";
$params = [];
$types = "";

if (!empty($_GET['type'])) {
    $where .= " AND t.transaction_type = ?";
    $params[] = $_GET['type'];
    $types .= "s";
}
if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where .= " AND (t.reference_no LIKE ? OR m.full_name LIKE ?)";
    $params[] = $search; $params[] = $search;
    $types .= "ss";
}

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    $sql_export = "SELECT t.*, m.full_name, m.national_id 
                   FROM transactions t 
                   LEFT JOIN members m ON t.member_id = m.member_id 
                   WHERE $where 
                   ORDER BY t.created_at DESC";
    $stmt_e = $conn->prepare($sql_export);
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute();
    $export_data_raw = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    $total_val = 0;
    foreach ($export_data_raw as $row) {
        $total_val += (float)$row['amount'];
        $data[] = [
            'Date' => date('d-M-Y', strtotime($row['created_at'])),
            'Reference' => $row['reference_no'],
            'Member/Entity' => $row['full_name'] ?? 'Office',
            'Type' => ucwords(str_replace('_', ' ', $row['transaction_type'])),
            'Amount' => number_format((float)$row['amount'], 2),
            'Notes' => $row['notes']
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Payments Ledger',
        'module' => 'Finance Management',
        'headers' => ['Date', 'Reference', 'Member/Entity', 'Type', 'Amount', 'Notes'],
        'total_value' => $total_val
    ]);
    exit;
}

$sql = "SELECT t.*, m.full_name, m.national_id, i.title as asset_title
        FROM transactions t 
        LEFT JOIN members m ON t.member_id = m.member_id 
        LEFT JOIN investments i ON t.related_table = 'investments' AND t.related_id = i.investment_id
        WHERE $where 
        ORDER BY t.created_at DESC LIMIT 50";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result();

$pageTitle = "Payments Ledger";
?>
<?php $layout->header($pageTitle); ?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }
        
        /* Custom Cards */
        .card-custom { 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }
        
        .badge-type { padding: 6px 12px; font-weight: 700; border-radius: 10px; font-size: 0.7rem; letter-spacing: 0.5px; }
        .badge-in { background: rgba(208, 243, 93, 0.1); color: var(--forest-mid); }
        .badge-out { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
        
        .avatar-initials {
            width: 42px; height: 42px; border-radius: 14px; background: rgba(15, 46, 37, 0.05); color: var(--forest);
            display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;
        }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <div class="container-fluid">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--forest-dark);">Transactions Ledger</h2>
                    <p class="text-muted mb-0">Monitor financial inflows and outflows.</p>
                </div>
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-forest dropdown-toggle shadow-sm" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">Export PDF</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">Export Excel</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank">Print Report</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-lime shadow-sm" data-bs-toggle="modal" data-bs-target="#recordTxnModal">
                        <i class="bi bi-plus-lg me-2"></i>New Transaction
                    </button>
                </div>
            </div>

            <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3 mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="card-custom p-4 mb-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Reference, Member Name..." value="<?= $_GET['search'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted fw-bold">Transaction Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="deposit" <?= ($_GET['type'] ?? '') == 'deposit' ? 'selected' : '' ?>>Deposit (Savings)</option>
                            <option value="withdrawal" <?= ($_GET['type'] ?? '') == 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                            <option value="loan_repayment" <?= ($_GET['type'] ?? '') == 'loan_repayment' ? 'selected' : '' ?>>Loan Repayment</option>
                            <option value="share_capital" <?= ($_GET['type'] ?? '') == 'share_capital' ? 'selected' : '' ?>>Share Capital</option>
                            <option value="revenue_inflow" <?= ($_GET['type'] ?? '') == 'revenue_inflow' ? 'selected' : '' ?>>Revenue Inflow</option>
                            <option value="expense" <?= ($_GET['type'] ?? '') == 'expense' ? 'selected' : '' ?>>Expense</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-forest w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <?php if(!empty($_GET['search']) || !empty($_GET['type'])): ?>
                            <a href="payments.php" class="btn btn-link text-decoration-none text-muted">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card-custom p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Member / Entity</th>
                                <th>Ref & Date</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th>Notes</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transactions->num_rows > 0): ?>
                                <?php while($row = $transactions->fetch_assoc()): 
                                    $in_types = ['deposit', 'savings_deposit', 'loan_repayment', 'share_purchase', 'revenue_inflow'];
                                    $is_in = in_array($row['transaction_type'], $in_types);
                                    $badge_class = $is_in ? 'badge-in' : 'badge-out';
                                    $amount_color = $is_in ? 'text-success' : 'text-danger';
                                    $sign = $is_in ? '+' : '-';
                                    
                                    // Initials Logic
                                    $name = $row['full_name'] ?? 'Office';
                                    $initials = strtoupper(substr($name, 0, 2));
                                    $avatar_bg = $is_in ? 'var(--lime-dim)' : '#fce7f3';
                                    $avatar_col = $is_in ? 'var(--forest-dark)' : '#be185d';
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-initials shadow-sm" style="background: <?= $avatar_bg ?>; color: <?= $avatar_col ?>;">
                                                <?= $initials ?>
                                            </div>
                                            <div>
                                                <?php if($row['member_id']): ?>
                                                    <a href="member_profile.php?id=<?= $row['member_id'] ?>" class="text-decoration-none">
                                                        <div class="fw-bold "><?= esc($name) ?> <i class="bi bi-person-bounding-box ms-1 small opacity-50"></i></div>
                                                    </a>
                                                <?php else: ?>
                                                    <div class="fw-bold "><?= esc($name) ?></div>
                                                <?php endif; ?>
                                                <?php if($row['national_id']): ?>
                                                    <div class="small text-muted" style="font-size: 0.75rem;">ID: <?= $row['national_id'] ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-medium "><?= esc($row['reference_no']) ?></div>
                                        <div class="small text-muted"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-type <?= $badge_class ?>">
                                            <?= ucwords(str_replace('_', ' ', $row['transaction_type'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold <?= $amount_color ?> fs-6">
                                            <?= $sign ?><?= number_format((float)$row['amount'], 2) ?>
                                        </div>
                                    </td>
                                    <td class="text-muted small">
                                        <?php if($row['asset_title']): ?>
                                            <div class="badge bg-forest text-white rounded-pill mb-1" style="font-size: 0.65rem; background-color: var(--forest-dark) !important;">
                                                <i class="bi bi-tag-fill me-1"></i> <?= esc($row['asset_title']) ?>
                                            </div><br>
                                        <?php endif; ?>
                                        <?= esc($row['notes']) ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-light rounded-circle shadow-sm border text-muted" title="Download Receipt">
                                            <i class="bi bi-download"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                        No transactions found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $layout->footer(); ?>
            </div>

        </div>
        
    </div>
</div>

<div class="modal fade" id="recordTxnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Record Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record_txn">
                <div class="modal-body p-4">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Transaction Type</label>
                        <select name="transaction_type" id="txnType" class="form-select" required onchange="toggleMemberField()">
                            <option value="" selected disabled>Select Type...</option>
                            <option value="deposit">Deposit (Savings)</option>
                            <option value="withdrawal">Withdrawal</option>
                            <option value="loan_repayment">Loan Repayment</option>
                            <option value="share_capital">Share Capital</option>
                            <option value="expense">Office Expense</option>
                            <option value="income">Revenue Inflow</option>
                        </select>
                    </div>

                    <div class="mb-3" id="memberField">
                        <label class="form-label small fw-bold text-uppercase text-muted">Select Member</label>
                        <select name="member_id" class="form-select">
                            <option value="">Search Member...</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['member_id'] ?>">
                                    <?= esc($m['full_name']) ?> (ID: <?= $m['national_id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="assetField">
                        <label class="form-label small fw-bold text-uppercase text-muted">Attribute to Asset (Optional)</label>
                        <select name="unified_asset_id" class="form-select">
                            <option value="other_0">General / Unassigned</option>
                            <?php foreach($investments_all as $inv): ?>
                                <option value="inv_<?= $inv['investment_id'] ?>"><?= esc($inv['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Amount (KES)</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Date</label>
                            <input type="date" name="txn_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash at Hand</option>
                            <option value="mpesa">M-Pesa Float</option>
                            <option value="bank">Bank Account</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Reference No.</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. MPESA-QWE12345" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Description..."></textarea>
                    </div>

                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4 text-center justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill px-4 me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill px-4">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleMemberField() {
        const type = document.getElementById('txnType').value;
        const memberDiv = document.getElementById('memberField');
        const assetDiv = document.getElementById('assetField');
        
        if (type === 'expense' || type === 'income') {
            memberDiv.classList.add('d-none');
            assetDiv.classList.remove('d-none');
        } else {
            memberDiv.classList.remove('d-none');
            assetDiv.classList.add('d-none');
        }
    }
</script>
</body>
</html>
