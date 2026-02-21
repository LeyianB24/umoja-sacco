<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// 1. Auth Check
require_admin();
require_permission();

$layout = LayoutManager::create('admin');

/**
 * admin/transactions.php
 * The Golden Ledger - Unified Transaction Vault
 * Premium Hope UI (Forest & Lime)
 */

// 2. Fetch Params
$filter_related_id = isset($_GET['filter']) ? intval($_GET['filter']) : null;
$filter_table      = $_GET['related_table'] ?? '';
$filter_member_id  = isset($_GET['member_id']) ? intval($_GET['member_id']) : null;
$filter_type       = $_GET['type'] ?? '';
$search_query      = $_GET['search'] ?? '';
$start_date        = $_GET['start_date'] ?? '';
$end_date          = $_GET['end_date'] ?? '';

// 3. Build SQL
$where = "1=1";
$params = [];
$types = "";

if ($filter_related_id) {
    if ($filter_table) {
        $where .= " AND t.related_id = ? AND t.related_table = ?";
        $params[] = $filter_related_id;
        $params[] = $filter_table;
        $types .= "is";
    } else {
        $where .= " AND t.related_id = ?";
        $params[] = $filter_related_id;
        $types .= "i";
    }
}

if ($filter_member_id) {
    $where .= " AND t.member_id = ?";
    $params[] = $filter_member_id;
    $types .= "i";
}

if ($filter_type) {
    $where .= " AND t.transaction_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($search_query) {
    $sq = "%$search_query%";
    $where .= " AND (t.reference_no LIKE ? OR t.notes LIKE ? OR m.full_name LIKE ?)";
    $params[] = $sq; $params[] = $sq; $params[] = $sq;
    $types .= "sss";
}

if ($start_date) {
    $where .= " AND t.created_at >= ?";
    $params[] = "$start_date 00:00:00";
    $types .= "s";
}

if ($end_date) {
    $where .= " AND t.created_at <= ?";
    $params[] = "$end_date 23:59:59";
    $types .= "s";
}

// 4. Handle Export
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $sql_export = "SELECT t.*, m.full_name 
                   FROM transactions t 
                   LEFT JOIN members m ON t.member_id = m.member_id 
                   WHERE $where 
                   ORDER BY t.created_at DESC";
    $stmt_e = $conn->prepare($sql_export);
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute();
    $export_data_raw = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

    $data = [];
    $total_val = 0;
    foreach ($export_data_raw as $row) {
        $total_val += (float)$row['amount'];
        $data[] = [
            'Date' => date('d-M-Y H:i', strtotime($row['created_at'])),
            'Reference' => $row['reference_no'],
            'Entity' => $row['full_name'] ?: 'System/Office',
            'Type' => ucwords(str_replace('_', ' ', $row['transaction_type'])),
            'Amount' => number_format((float)$row['amount'], 2),
            'Notes' => $row['notes']
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Golden Ledger Export',
        'module' => 'Central Treasury',
        'headers' => ['Date', 'Reference', 'Entity', 'Type', 'Amount', 'Notes'],
        'total_value' => $total_val,
        'filters' => [
            'Search' => $search_query ?: 'None',
            'Type' => $filter_type ?: 'All',
            'Range' => ($start_date && $end_date) ? "$start_date to $end_date" : 'Historical'
        ]
    ]);
    exit;
}

// 5. Fetch Final Data
$sql = "SELECT t.*, m.full_name, m.national_id, i.title as asset_title, wr.status as withdrawal_status, wr.result_desc as withdrawal_result
        FROM transactions t 
        LEFT JOIN members m ON t.member_id = m.member_id 
        LEFT JOIN investments i ON t.related_table = 'investments' AND t.related_id = i.investment_id
        LEFT JOIN withdrawal_requests wr ON t.mpesa_request_id = wr.ref_no
        WHERE $where 
        ORDER BY t.created_at DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result();

// Stats calculation for the context
$stats_sql = "SELECT 
    SUM(CASE WHEN transaction_type IN ('deposit','income','revenue_inflow','loan_repayment') THEN amount ELSE 0 END) as total_in,
    SUM(CASE WHEN transaction_type IN ('withdrawal','expense','expense_outflow','loan_disbursement') THEN amount ELSE 0 END) as total_out,
    COUNT(*) as count
    FROM transactions t 
    LEFT JOIN members m ON t.member_id = m.member_id 
    WHERE $where";
$stmt_s = $conn->prepare($stats_sql);
if (!empty($params)) $stmt_s->bind_param($types, ...$params);
$stmt_s->execute();
$gl_stats = $stmt_s->get_result()->fetch_assoc();

$pageTitle = "Golden Ledger Vault";
?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }

        /* Page-specific overrides */
        .ledger-glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }

        .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-premium thead th {
            color: var(--text-muted); font-weight: 800;
            text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.1em;
            padding: 20px 25px; border-bottom: 2px solid rgba(0,0,0,0.05);
            background: rgba(240, 244, 243, 0.5);
        }
        .table-premium tbody td {
            padding: 20px 25px; border-bottom: 1px solid rgba(0,0,0,0.05);
            vertical-align: middle; transition: 0.2s;
        }
        .table-premium tr:hover td { background: rgba(15, 46, 37, 0.02); }

        .type-pill {
            padding: 8px 16px; border-radius: 12px; font-size: 0.75rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 6px;
        }
        .type-in { background: rgba(208, 243, 93, 0.1); color: var(--forest-mid); }
        .type-out { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'Financial Ledger'); ?>
        <div class="container-fluid">
        <div class="hp-hero mb-4">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Audit-Ready Ledger</span>
                    <h1 class="display-4 fw-800 mb-2">The Golden Ledger.</h1>
                    <p class="opacity-75 fs-5">Verifying financial integrity across all member and system accounts with <span class="text-lime fw-bold">absolute transparency</span>.</p>
                </div>
                <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
                    <div class="d-inline-flex flex-wrap gap-3 justify-content-lg-end">
                        <div class="stat-bubble text-start">
                            <div class="small fw-800 opacity-75 text-uppercase ls-1">Volume In</div>
                            <div class="h3 fw-800 mb-0 text-lime">KES <?= number_format((float)($gl_stats['total_in'] ?? 0)) ?></div>
                        </div>
                        <div class="stat-bubble text-start">
                            <div class="small fw-800 opacity-75 text-uppercase ls-1">Volume Out</div>
                            <div class="h3 fw-800 mb-0 text-white">KES <?= number_format((float)($gl_stats['total_out'] ?? 0)) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

    <!-- Filtering -->
    <div class="ledger-glass p-4 mb-4 slide-up">
        <form method="GET" class="row g-3 align-items-end">
            <?php if($filter_related_id): ?>
                <input type="hidden" name="filter" value="<?= $filter_related_id ?>">
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Global Search</label>
                <input type="text" name="search" class="search-pill" placeholder="Ref, Name, Notes..." value="<?= htmlspecialchars($search_query) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Category</label>
                <select name="type" class="form-select border-0 shadow-sm rounded-4" style="padding: 12px 20px;">
                    <option value="">All Streams</option>
                    <option value="deposit" <?= $filter_type == 'deposit' ? 'selected' : '' ?>>Deposits</option>
                    <option value="withdrawal" <?= $filter_type == 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                    <option value="loan_disbursement" <?= $filter_type == 'loan_disbursement' ? 'selected' : '' ?>>Loan Payouts</option>
                    <option value="loan_repayment" <?= $filter_type == 'loan_repayment' ? 'selected' : '' ?>>Loan Repayment</option>
                    <option value="expense" <?= $filter_type == 'expense' ? 'selected' : '' ?>>Expenses</option>
                    <option value="revenue_inflow" <?= $filter_type == 'revenue_inflow' ? 'selected' : '' ?>>Revenue</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Start Date</label>
                <input type="date" name="start_date" class="form-control border-0 shadow-sm rounded-4" style="padding: 11px 15px;" value="<?= $start_date ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">End Date</label>
                <input type="date" name="end_date" class="form-control border-0 shadow-sm rounded-4" style="padding: 11px 15px;" value="<?= $end_date ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-forest w-100 rounded-pill py-2 fw-bold">Apply Filters</button>
                <a href="transactions.php" class="btn btn-light rounded-pill px-3 border py-2"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="ledger-glass slide-up" style="animation-delay: 0.2s">
        <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
            <h5 class="fw-800 mb-0"><i class="bi bi-list-task me-2"></i>Ledger Entries <span class="badge bg-light text-forest border ms-2"><?= $gl_stats['count'] ?></span></h5>
            <div class="dropdown">
                <button class="btn btn-outline-forest btn-sm rounded-pill px-4 dropdown-toggle fw-bold" data-bs-toggle="dropdown">
                    <i class="bi bi-cloud-download me-2"></i>Export Securely
                </button>
                <ul class="dropdown-menu shadow-lg border-0 mt-3">
                    <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Audit PDF Report</a></li>
                    <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Spreadsheet Asset</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Print Registry</a></li>
                </ul>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>Date & Reference</th>
                        <th>Member / Party</th>
                        <th>Classification</th>
                        <th class="text-end">Amount (KES)</th>
                        <th>Narration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($transactions->num_rows > 0): 
                    while($row = $transactions->fetch_assoc()): 
                        $is_in = in_array($row['transaction_type'], ['deposit','income','revenue_inflow','loan_repayment','share_capital']);
                        $pill_class = $is_in ? 'type-in' : 'type-out';
                        $icon = $is_in ? 'bi-arrow-down-left-circle' : 'bi-arrow-up-right-circle';
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold "><?= esc($row['reference_no']) ?></div>
                            <div class="small text-muted mt-1 opacity-75"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td>
                            <?php if($row['member_id']): ?>
                                <a href="member_profile.php?id=<?= $row['member_id'] ?>" class="text-decoration-none d-flex align-items-center gap-2">
                                    <div class="avatar-sm bg-light rounded-circle text-center d-flex align-items-center justify-content-center" style="width:32px; height:32px; font-size: 0.7rem; font-weight: 800; color: var(--forest);">
                                        <?= strtoupper(substr($row['full_name'], 0, 1)) ?>
                                    </div>
                                    <div class="fw-600 "><?= esc($row['full_name']) ?></div>
                                </a>
                            <?php else: ?>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-forest-soft rounded-circle text-center d-flex align-items-center justify-content-center" style="width:32px; height:32px; font-size: 0.7rem; color: var(--forest);">
                                        <i class="bi bi-shield-shaded"></i>
                                    </div>
                                    <div class="fw-600 text-muted">System/Vault</div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="type-pill <?= $pill_class ?>">
                                <i class="bi <?= $icon ?>"></i>
                                <?= ucwords(str_replace(['_','inflow','outflow'], [' ','',''], $row['transaction_type'])) ?>
                            </span>
                        </td>
                        <td class="text-end fw-800 fs-6 <?= $is_in ? 'text-success' : 'text-danger' ?>">
                            <?= $is_in ? '+' : '-' ?> <?= number_format((float)$row['amount'], 2) ?>
                        </td>
                        <td class="small text-muted" style="max-width: 250px;">
                            <?php if($row['asset_title']): ?>
                                <div class="badge bg-forest text-white rounded-pill mb-1" style="font-size: 0.65rem;">
                                    <i class="bi bi-tag-fill me-1"></i> <?= esc($row['asset_title']) ?>
                                </div><br>
                            <?php endif; ?>
                            <?php if ($row['withdrawal_status']): ?>
                                <div class="badge bg-<?= $row['withdrawal_status'] === 'completed' ? 'success' : 'warning' ?> bg-opacity-10 text-<?= $row['withdrawal_status'] === 'completed' ? 'success' : 'warning' ?> rounded-pill mb-1" style="font-size: 0.65rem;">
                                    WITHDRAWAL: <?= strtoupper($row['withdrawal_status']) ?>
                                </div><br>
                            <?php endif; ?>
                            <?= esc($row['notes'] ?: 'No notes attached.') ?>
                            <?php if ($row['withdrawal_result']): ?>
                                <div class="mt-1 xsmall opacity-50 text-wrap" style="font-size: 0.6rem;"><?= esc($row['withdrawal_result']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <i class="bi bi-search display-3 opacity-10"></i>
                            <h5 class="fw-bold text-muted mt-3">No matching records</h5>
                            <p class="text-muted small">Try adjusting your filters or search terms.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php $layout->footer(); ?>
    </div>
</div>
