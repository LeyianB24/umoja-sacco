<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// 1. Auth Check
require_admin();
require_permission();

$layout = LayoutManager::create('admin');

/**
 * admin/transactions.php
 * The Golden Ledger - Unified Transaction Vault
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
    if ($_GET['action'] === 'export_pdf' || $_GET['action'] === 'export_excel') {
        require_once __DIR__ . '/../../inc/ExportHelper.php';
    } else {
        require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    }

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
            'Date'      => date('d-M-Y H:i', strtotime($row['created_at'])),
            'Reference' => $row['reference_no'],
            'Entity'    => $row['full_name'] ?: 'System/Office',
            'Type'      => ucwords(str_replace('_', ' ', $row['transaction_type'])),
            'Amount'    => number_format((float)$row['amount'], 2),
            'Notes'     => $row['notes']
        ];
    }

    $title   = 'Golden_Ledger_Export_' . date('Ymd_His');
    $headers = ['Date', 'Reference', 'Entity', 'Type', 'Amount', 'Notes'];

    if ($format === 'pdf') {
        ExportHelper::pdf('Golden Ledger Export', $headers, $data, $title . '.pdf');
    } elseif ($format === 'excel') {
        ExportHelper::csv($title . '.csv', $headers, $data);
    } else {
        UniversalExportEngine::handle($format, $data, [
            'title'       => 'Golden Ledger Export',
            'module'      => 'Central Treasury',
            'headers'     => $headers,
            'total_value' => $total_val,
            'filters'     => [
                'Search' => $search_query ?: 'None',
                'Type'   => $filter_type ?: 'All',
                'Range'  => ($start_date && $end_date) ? "$start_date to $end_date" : 'Historical'
            ]
        ]);
    }
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

// Stats
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
<?php $layout->header($pageTitle); ?>
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root {
            --ease-expo:   cubic-bezier(0.16, 1, 0.3, 1);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        body, .main-content-wrapper, .container-fluid,
        table, input, select, button, .form-control, .form-select {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        /* ── Hero ── */
        .gl-hero {
            background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
            border-radius: 28px;
            padding: 52px 56px;
            color: #fff;
            margin-bottom: 32px;
            box-shadow: 0 24px 60px rgba(15,46,37,0.20);
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.7s var(--ease-expo) both;
        }

        .gl-hero .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        .gl-hero .hero-circle {
            position: absolute;
            top: -90px; right: -90px;
            width: 340px; height: 340px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
        }

        .gl-hero .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 6px 16px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .gl-hero .pulse-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #a3e635;
            animation: pulseDot 2s infinite;
        }

        .gl-hero h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 2.8rem;
            letter-spacing: -0.5px;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .gl-hero p {
            opacity: 0.72;
            font-size: 1rem;
            line-height: 1.6;
            max-width: 480px;
        }

        /* Hero stat bubbles */
        .stat-bubble {
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.13);
            backdrop-filter: blur(12px);
            border-radius: 18px;
            padding: 18px 26px;
            min-width: 200px;
        }

        .stat-bubble .bubble-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.55;
            margin-bottom: 6px;
        }

        .stat-bubble .bubble-value {
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-bubble .bubble-value.in  { color: #a3e635; }
        .stat-bubble .bubble-value.out { color: #fff; }

        /* ── Filter Panel ── */
        .filter-panel {
            background: #fff;
            border-radius: 20px;
            padding: 24px 28px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            animation: fadeUp 0.6s var(--ease-expo) 0.1s both;
        }

        .filter-panel .form-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 6px;
        }

        .filter-panel .form-control,
        .filter-panel .form-select {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #111827;
            box-shadow: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .filter-panel .form-control:focus,
        .filter-panel .form-select:focus {
            border-color: rgba(15,46,37,0.35);
            box-shadow: 0 0 0 3px rgba(15,46,37,0.07);
        }

        .filter-panel .search-field {
            position: relative;
        }

        .filter-panel .search-field i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
            pointer-events: none;
        }

        .filter-panel .search-field input {
            padding-left: 36px;
        }

        /* ── Ledger Table Card ── */
        .ledger-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden;
            animation: fadeUp 0.6s var(--ease-expo) 0.2s both;
        }

        .ledger-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 28px;
            border-bottom: 1px solid #f3f4f6;
        }

        .ledger-card-header h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .entry-count-badge {
            display: inline-flex;
            align-items: center;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* ── Table Styles ── */
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .ledger-table thead th {
            background: #fafafa;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #9ca3af;
            padding: 12px 20px;
            border: none;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap;
        }

        .ledger-table tbody tr {
            border-bottom: 1px solid #f9fafb;
            transition: background 0.15s ease;
        }

        .ledger-table tbody tr:last-child { border-bottom: none; }

        .ledger-table tbody tr:hover { background: #fafff8; }

        .ledger-table tbody td {
            padding: 14px 20px;
            vertical-align: middle;
            color: #374151;
        }

        /* Reference + date */
        .ref-code {
            font-weight: 700;
            font-size: 0.85rem;
            color: #111827;
            letter-spacing: 0.2px;
        }

        .ref-date {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 3px;
        }

        /* Member cell */
        .member-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
        }

        .member-cell:hover { color: var(--forest, #0f2e25); }

        .member-avatar {
            width: 34px; height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--forest, #0f2e25), #1a5c42);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a3e635;
            font-weight: 800;
            font-size: 12px;
            flex-shrink: 0;
        }

        .system-avatar {
            width: 34px; height: 34px;
            border-radius: 10px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .member-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: #111827;
        }

        /* Type pill */
        .type-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 7px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .type-pill.in  { background: #f0fdf4; color: #16a34a; }
        .type-pill.out { background: #fef2f2; color: #dc2626; }

        /* Amount */
        .amount-in  { color: #16a34a; font-weight: 800; font-size: 0.92rem; }
        .amount-out { color: #dc2626; font-weight: 800; font-size: 0.92rem; }

        /* Notes cell */
        .notes-cell { max-width: 260px; }

        .tag-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--forest, #0f2e25);
            color: #a3e635;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            margin-bottom: 4px;
        }

        .withdrawal-badge {
            display: inline-flex;
            align-items: center;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            margin-bottom: 4px;
        }

        .withdrawal-badge.completed { background: #f0fdf4; color: #16a34a; }
        .withdrawal-badge.pending   { background: #fffbeb; color: #d97706; }

        .notes-text {
            font-size: 0.8rem;
            color: #9ca3af;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .result-desc {
            font-size: 0.7rem;
            color: #9ca3af;
            opacity: 0.6;
            margin-top: 3px;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 72px 24px;
        }

        .empty-icon {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .empty-state h5 {
            font-weight: 700;
            color: #374151;
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 0.875rem;
            color: #9ca3af;
        }

        /* ── Export Button ── */
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #374151;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 8px 18px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .export-btn:hover {
            background: rgba(15,46,37,0.06);
            border-color: rgba(15,46,37,0.15);
            color: var(--forest, #0f2e25);
        }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulseDot {
            0%   { box-shadow: 0 0 0 0   rgba(163,230,53,0.5); }
            70%  { box-shadow: 0 0 0 7px rgba(163,230,53,0); }
            100% { box-shadow: 0 0 0 0   rgba(163,230,53,0); }
        }

        @media (max-width: 768px) {
            .gl-hero { padding: 32px 28px; }
            .gl-hero h1 { font-size: 2rem; }
            .stat-bubble { min-width: unset; }
        }
    </style>

    <!-- ─── HERO ─────────────────────────────────── -->
    <div class="gl-hero mb-4">
        <div class="hero-grid"></div>
        <div class="hero-circle"></div>
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="hero-badge">
                    <span class="pulse-dot"></span>
                    Audit-Ready Ledger
                </div>
                <h1>The Golden Ledger.</h1>
                <p>Verifying financial integrity across all member and system accounts with <strong style="color:#a3e635;opacity:1;">absolute transparency</strong>.</p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
                <div class="d-inline-flex flex-wrap gap-3 justify-content-lg-end">
                    <div class="stat-bubble">
                        <div class="bubble-label">Volume In</div>
                        <div class="bubble-value in">KES <?= number_format((float)($gl_stats['total_in'] ?? 0)) ?></div>
                    </div>
                    <div class="stat-bubble">
                        <div class="bubble-label">Volume Out</div>
                        <div class="bubble-value out">KES <?= number_format((float)($gl_stats['total_out'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- ─── FILTER PANEL ─────────────────────────── -->
    <div class="filter-panel">
        <form method="GET" class="row g-3 align-items-end">
            <?php if ($filter_related_id): ?>
                <input type="hidden" name="filter" value="<?= $filter_related_id ?>">
            <?php endif; ?>

            <div class="col-md-3">
                <label class="form-label">Global Search</label>
                <div class="search-field">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Ref, name, notes…" value="<?= htmlspecialchars($search_query) ?>">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="type" class="form-select">
                    <option value="">All Streams</option>
                    <option value="deposit"          <?= $filter_type == 'deposit'          ? 'selected' : '' ?>>Deposits</option>
                    <option value="withdrawal"       <?= $filter_type == 'withdrawal'       ? 'selected' : '' ?>>Withdrawals</option>
                    <option value="loan_disbursement"<?= $filter_type == 'loan_disbursement'? 'selected' : '' ?>>Loan Payouts</option>
                    <option value="loan_repayment"   <?= $filter_type == 'loan_repayment'   ? 'selected' : '' ?>>Loan Repayment</option>
                    <option value="expense"          <?= $filter_type == 'expense'          ? 'selected' : '' ?>>Expenses</option>
                    <option value="revenue_inflow"   <?= $filter_type == 'revenue_inflow'   ? 'selected' : '' ?>>Revenue</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-forest w-100 rounded-pill py-2 fw-bold" style="font-size:0.875rem;">
                    <i class="bi bi-funnel me-1"></i> Apply Filters
                </button>
                <a href="transactions.php" class="btn rounded-pill px-3 py-2" style="background:#f3f4f6;border:1px solid #e5e7eb;color:#6b7280;" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- ─── LEDGER TABLE ─────────────────────────── -->
    <div class="ledger-card">
        <div class="ledger-card-header">
            <h5>
                <i class="bi bi-list-task" style="color:var(--forest,#0f2e25);opacity:0.7;"></i>
                Ledger Entries
                <span class="entry-count-badge"><?= number_format($gl_stats['count']) ?></span>
            </h5>
            <div class="dropdown">
                <button class="export-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-cloud-download"></i> Export Securely
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius:14px;padding:8px;">
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">
                            <i class="bi bi-file-pdf text-danger me-2"></i> Audit PDF Report
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">
                            <i class="bi bi-file-excel text-success me-2"></i> Spreadsheet Asset
                        </a>
                    </li>
                    <li><hr class="dropdown-divider mx-2"></li>
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank">
                            <i class="bi bi-printer me-2"></i> Print Registry
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="table-responsive">
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>Date &amp; Reference</th>
                        <th>Member / Party</th>
                        <th>Classification</th>
                        <th class="text-end">Amount (KES)</th>
                        <th>Narration</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($transactions->num_rows > 0):
                    while ($row = $transactions->fetch_assoc()):
                        $is_in      = in_array($row['transaction_type'], ['deposit','income','revenue_inflow','loan_repayment','share_capital']);
                        $pill_class = $is_in ? 'in' : 'out';
                        $icon       = $is_in ? 'bi-arrow-down-left-circle-fill' : 'bi-arrow-up-right-circle-fill';
                        $label      = ucwords(str_replace(['_','inflow','outflow'], [' ','',''], $row['transaction_type']));
                        $w_status   = $row['withdrawal_status'] ?? null;
                ?>
                    <tr>
                        <!-- Date & Ref -->
                        <td>
                            <div class="ref-code"><?= esc($row['reference_no']) ?></div>
                            <div class="ref-date"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></div>
                        </td>

                        <!-- Member -->
                        <td>
                            <?php if ($row['member_id']): ?>
                                <a href="member_profile.php?id=<?= $row['member_id'] ?>" class="member-cell">
                                    <div class="member-avatar"><?= strtoupper(substr($row['full_name'], 0, 1)) ?></div>
                                    <span class="member-name"><?= esc($row['full_name']) ?></span>
                                </a>
                            <?php else: ?>
                                <div class="member-cell">
                                    <div class="system-avatar"><i class="bi bi-shield-shaded"></i></div>
                                    <span class="member-name" style="color:#9ca3af;">System / Vault</span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Type -->
                        <td>
                            <span class="type-pill <?= $pill_class ?>">
                                <i class="bi <?= $icon ?>"></i>
                                <?= $label ?>
                            </span>
                        </td>

                        <!-- Amount -->
                        <td class="text-end <?= $is_in ? 'amount-in' : 'amount-out' ?>">
                            <?= $is_in ? '+' : '−' ?> <?= number_format((float)$row['amount'], 2) ?>
                        </td>

                        <!-- Narration -->
                        <td class="notes-cell">
                            <?php if ($row['asset_title']): ?>
                                <div class="tag-badge"><i class="bi bi-tag-fill"></i><?= esc($row['asset_title']) ?></div>
                            <?php endif; ?>
                            <?php if ($w_status): ?>
                                <div class="withdrawal-badge <?= $w_status === 'completed' ? 'completed' : 'pending' ?>">
                                    <i class="bi bi-phone me-1"></i> M-Pesa: <?= strtoupper($w_status) ?>
                                </div>
                            <?php endif; ?>
                            <div class="notes-text"><?= esc($row['notes'] ?: 'No notes attached.') ?></div>
                            <?php if ($row['withdrawal_result']): ?>
                                <div class="result-desc"><?= esc($row['withdrawal_result']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-search"></i></div>
                                <h5>No matching records</h5>
                                <p>Try adjusting your filters or search terms.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>