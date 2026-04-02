<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SupportTicketWidget.php';

$layout = LayoutManager::create('admin');

// ---------------------------------------------------------
// POST HANDLER — approve / suspend / reactivate
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['member_id'])) {

    // CSRF guard
    verify_csrf_token();

    // Permission guard
    if (!can('manage_members')) {
        flash_set('You do not have permission to manage members.', 'danger');
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    $member_id  = (int) $_POST['member_id'];
    $action     = $_POST['action'];
    $admin_id   = $_SESSION['admin_id'] ?? 0;

    $new_status = match($action) {
        'approve'    => 'active',
        'suspend'    => 'suspended',
        'reactivate' => 'active',
        default      => null
    };

    if ($new_status && $member_id > 0) {
        $stmt = $conn->prepare("UPDATE members SET status = ? WHERE member_id = ?");
        $stmt->bind_param("si", $new_status, $member_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Fetch member name for audit log
            $r    = $conn->query("SELECT full_name FROM members WHERE member_id = $member_id");
            $name = $r ? ($r->fetch_assoc()['full_name'] ?? "Member #$member_id") : "Member #$member_id";

            $log_action = match($action) {
                'approve'    => 'Member Approved',
                'suspend'    => 'Member Suspended',
                'reactivate' => 'Member Reactivated',
                default      => 'Member Status Changed'
            };
            $log_detail = "$log_action: $name (ID: $member_id) → status set to '$new_status'.";
            $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            $stmt_log = $conn->prepare(
                "INSERT INTO audit_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt_log->bind_param("isss", $admin_id, $log_action, $log_detail, $ip);
            $stmt_log->execute();

            flash_set("$name has been successfully " . strtolower($log_action) . ".", 'success');
        } else {
            flash_set('No changes were made. Member may already have that status.', 'warning');
        }
    } else {
        flash_set('Invalid action or member ID.', 'danger');
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(['status' => $_GET['status'] ?? 'all', 'q' => $_GET['q'] ?? '']));
    exit;
}

// 1. Stats Aggregation
$stats = $conn->query("SELECT 
    COUNT(CASE WHEN status='active'    THEN 1 END) as active, 
    COUNT(CASE WHEN status='suspended' THEN 1 END) as suspended, 
    COUNT(CASE WHEN status='inactive'  THEN 1 END) as pending, 
    COUNT(*) as total 
FROM members")->fetch_assoc();

// 2. Fetch Members Table
$filter = $_GET['status'] ?? 'all';
$search = $_GET['q'] ?? '';
$where  = "1=1";
$params = [];
$types  = "";

if ($filter !== 'all') {
    $where   .= " AND status = ?";
    $params[] = $filter;
    $types   .= "s";
}
if (!empty($search)) {
    $sq       = "%$search%";
    $where   .= " AND (full_name LIKE ? OR national_id LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = $sq; $params[] = $sq; $params[] = $sq; $params[] = $sq;
    $types   .= "ssss";
}

$query = "SELECT * FROM members WHERE $where ORDER BY join_date DESC LIMIT 100";
$stmt  = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Registry Control Center";

// HANDLE EXPORT ACTIONS
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    if ($_GET['action'] === 'export_pdf' || $_GET['action'] === 'export_excel') {
        require_once __DIR__ . '/../../inc/ExportHelper.php';
    } else {
        require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    }

    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    foreach ($members as $m) {
        $data[] = [
            'ID'     => $m['national_id'],
            'Name'   => $m['full_name'],
            'Email'  => $m['email'],
            'Phone'  => $m['phone'],
            'Status' => strtoupper($m['status']),
            'Joined' => date('d-M-Y', strtotime($m['join_date']))
        ];
    }

    $title   = 'Member_Directory_' . date('Ymd_His');
    $headers = ['ID', 'Name', 'Email', 'Phone', 'Status', 'Joined'];

    if ($format === 'pdf') {
        ExportHelper::pdf('Member Directory', $headers, $data, $title . '.pdf');
    } elseif ($format === 'excel') {
        ExportHelper::csv($title . '.csv', $headers, $data);
    } else {
        UniversalExportEngine::handle($format, $data, [
            'title'   => 'Member Directory',
            'module'  => 'Registry Control',
            'headers' => $headers
        ]);
    }
    exit;
}

\USMS\Middleware\AuthMiddleware::requireModulePermission('members');
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

        body, .main-content-wrapper, input, select, table, button {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        /* ── Hero ── */
        .reg-hero {
            background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
            border-radius: 28px;
            padding: 52px 56px;
            color: #fff;
            margin-bottom: 32px;
            box-shadow: 0 24px 60px rgba(15,46,37,0.20);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            animation: fadeUp 0.7s var(--ease-expo) both;
        }

        .reg-hero .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        .reg-hero .hero-circle {
            position: absolute;
            top: -80px; right: -80px;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
        }

        .reg-hero .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 6px 16px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .reg-hero h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 2.8rem;
            letter-spacing: -0.5px;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .reg-hero p {
            opacity: 0.72;
            font-size: 1rem;
            line-height: 1.6;
            margin: 0;
        }

        .hero-export-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #a3e635;
            color: var(--forest, #0f2e25);
            font-size: 0.875rem;
            font-weight: 700;
            padding: 12px 24px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            box-shadow: 0 4px 16px rgba(163,230,53,0.3);
            transition: transform 0.22s var(--ease-expo), box-shadow 0.22s ease;
        }

        .hero-export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(163,230,53,0.4);
            color: var(--forest, #0f2e25);
            text-decoration: none;
        }

        /* ── Stat Cards ── */
        .stat-card {
            background: #fff;
            border-radius: 20px;
            padding: 24px 26px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            animation: fadeUp 0.6s var(--ease-expo) both;
            transition: transform 0.22s var(--ease-expo), box-shadow 0.22s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.09);
        }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.20s; }

        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            margin-bottom: 14px;
        }

        .stat-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-trend {
            font-size: 0.78rem;
            font-weight: 600;
        }

        /* Filter card */
        .filter-stat-card {
            background: #fff;
            border-radius: 20px;
            padding: 22px 24px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            animation: fadeUp 0.6s var(--ease-expo) 0.20s both;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
        }

        .filter-stat-card .filter-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #9ca3af;
        }

        .filter-stat-card .form-select {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
            box-shadow: none;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .filter-stat-card .form-select:focus {
            border-color: rgba(15,46,37,0.35);
            box-shadow: 0 0 0 3px rgba(15,46,37,0.07);
        }

        /* ── Members Table Card ── */
        .members-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden;
            animation: fadeUp 0.6s var(--ease-expo) 0.25s both;
        }

        .members-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 24px;
            border-bottom: 1px solid #f3f4f6;
            flex-wrap: wrap;
        }

        /* Search */
        .search-wrap {
            position: relative;
            flex: 1;
            max-width: 440px;
        }

        .search-wrap i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
            pointer-events: none;
        }

        .search-wrap input {
            width: 100%;
            padding: 9px 14px 9px 36px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #111827;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            box-shadow: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .search-wrap input:focus {
            outline: none;
            border-color: rgba(15,46,37,0.35);
            box-shadow: 0 0 0 3px rgba(15,46,37,0.07);
        }

        .search-wrap input::placeholder { color: #c4c9d4; }

        .entry-meta {
            font-size: 0.8rem;
            font-weight: 600;
            color: #9ca3af;
            white-space: nowrap;
        }

        .entry-meta span { color: #111827; }

        /* Export dropdown */
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #374151;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 50px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .export-btn:hover {
            background: rgba(15,46,37,0.06);
            border-color: rgba(15,46,37,0.15);
            color: var(--forest, #0f2e25);
        }

        /* ── Table ── */
        .members-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .members-table thead th {
            background: #fafafa;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #9ca3af;
            padding: 12px 18px;
            border: none;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap;
        }

        .members-table tbody tr {
            border-bottom: 1px solid #f9fafb;
            transition: background 0.15s ease;
        }

        .members-table tbody tr:last-child { border-bottom: none; }
        .members-table tbody tr:hover { background: #fafff8; }

        .members-table tbody td {
            padding: 13px 18px;
            vertical-align: middle;
        }

        /* Member identity cell */
        .identity-cell {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .member-avatar {
            width: 38px; height: 38px;
            border-radius: 11px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .member-avatar-initials {
            width: 38px; height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--forest, #0f2e25), #1a5c42);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a3e635;
            font-weight: 800;
            font-size: 13px;
            flex-shrink: 0;
        }

        .member-name {
            font-weight: 700;
            color: #111827;
            font-size: 0.875rem;
            line-height: 1.2;
        }

        .member-id {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 2px;
        }

        /* Contact cell */
        .contact-email {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
        }

        .contact-phone {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 2px;
        }

        /* Status badge */
        .status-badge {
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

        .status-badge::before {
            content: '';
            width: 5px; height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-badge.active    { background: #f0fdf4; color: #16a34a; }
        .status-badge.suspended { background: #fef2f2; color: #dc2626; }
        .status-badge.pending   { background: #fffbeb; color: #d97706; }

        /* Date cell */
        .join-date {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
        }

        .join-sub {
            font-size: 0.72rem;
            color: #9ca3af;
            margin-top: 2px;
        }

        /* Action cell */
        .action-cell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .action-btn-primary {
            font-size: 0.78rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            transition: all 0.18s ease;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .action-btn-approve   { background: rgba(15,46,37,0.08); color: var(--forest, #0f2e25); }
        .action-btn-approve:hover   { background: rgba(15,46,37,0.15); }

        .action-btn-suspend   { background: #fef2f2; color: #dc2626; }
        .action-btn-suspend:hover   { background: #fee2e2; }

        .action-btn-reactivate { background: #f0fdf4; color: #16a34a; }
        .action-btn-reactivate:hover { background: #dcfce7; }

        .icon-btn {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.18s ease;
            cursor: pointer;
        }

        .icon-btn:hover {
            background: rgba(15,46,37,0.07);
            border-color: rgba(15,46,37,0.15);
            color: var(--forest, #0f2e25);
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 72px 24px;
        }

        .empty-state .empty-icon {
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

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .reg-hero { padding: 32px 28px; flex-direction: column; }
            .reg-hero h1 { font-size: 2rem; }
            .members-card-header { flex-direction: column; align-items: stretch; }
            .search-wrap { max-width: 100%; }
        }
    </style>

    <!-- ─── HERO ──────────────────────────────────────────── -->
    <div class="reg-hero mb-4">
        <div class="hero-grid"></div>
        <div class="hero-circle"></div>
        <div>
            <div class="hero-badge">
                <i class="bi bi-people-fill"></i> Membership Control Engine
            </div>
            <h1>Member Directory</h1>
            <p>Managing the core registry of <strong style="color:#a3e635;opacity:1;"><?= number_format((float)$stats['total']) ?></strong> USMS members.</p>
        </div>
        <div class="flex-shrink-0">
            <div class="dropdown">
                <button class="hero-export-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export Registry
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius:14px;padding:8px;">
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">
                            <i class="bi bi-file-pdf text-danger me-2"></i> Full List (PDF)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">
                            <i class="bi bi-file-excel text-success me-2"></i> Spreadsheet (XLS)
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
    </div>

    <?php flash_render(); ?>

    <?php render_support_ticket_widget($conn, ['profile'], 'Member Profile & Account'); ?>

    <!-- ─── STAT CARDS ────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(15,46,37,0.08);color:var(--forest,#0f2e25);">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-label">Active Members</div>
                <div class="stat-value"><?= number_format((float)$stats['active']) ?></div>
                <div class="stat-trend" style="color:#16a34a;">Authorized &amp; Online</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef2f2;color:#dc2626;">
                    <i class="bi bi-person-x-fill"></i>
                </div>
                <div class="stat-label">Suspended</div>
                <div class="stat-value"><?= number_format((float)$stats['suspended']) ?></div>
                <div class="stat-trend" style="color:#dc2626;">Restricted Access</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fffbeb;color:#d97706;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-label">Pending Review</div>
                <div class="stat-value"><?= number_format((float)$stats['pending']) ?></div>
                <div class="stat-trend" style="color:#9ca3af;">Awaiting Approval</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="filter-stat-card">
                <div class="filter-label">Registry Filter</div>
                <form method="GET" id="memberFilter">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all"      <?= $filter === 'all'       ? 'selected' : '' ?>>All Registrants</option>
                        <option value="active"   <?= $filter === 'active'    ? 'selected' : '' ?>>Active Members</option>
                        <option value="suspended"<?= $filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        <option value="inactive" <?= $filter === 'inactive'  ? 'selected' : '' ?>>Pending (Inactive)</option>
                    </select>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search ?? '') ?>">
                </form>
            </div>
        </div>

    </div>

    <!-- ─── MEMBERS TABLE ─────────────────────────────────── -->
    <div class="members-card">
        <div class="members-card-header">
            <form method="GET" class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Search by name, ID or email…" value="<?= htmlspecialchars($search ?? '') ?>">
                <input type="hidden" name="status" value="<?= $filter ?>">
            </form>
            <div class="d-flex align-items-center gap-3">
                <span class="entry-meta">Showing <span><?= count($members) ?></span> entries</span>
                <div class="dropdown">
                    <button class="export-btn dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-cloud-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius:14px;padding:8px;">
                        <li>
                            <a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">
                                <i class="bi bi-file-pdf text-danger me-2"></i> Full List (PDF)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">
                                <i class="bi bi-file-excel text-success me-2"></i> Spreadsheet (XLS)
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
        </div>

        <div class="table-responsive">
            <table class="members-table">
                <thead>
                    <tr>
                        <th style="padding-left:24px;">Identity &amp; Profile</th>
                        <th>Contact Channels</th>
                        <th>Registry Status</th>
                        <th>Onboarding Date</th>
                        <th style="text-align:right;padding-right:24px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-person-slash"></i></div>
                                <h5>No Members Found</h5>
                                <p>No records match your current criteria. Try adjusting your search or filter.</p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    foreach ($members as $m):
                        $status_key = match($m['status']) {
                            'active'    => 'active',
                            'suspended' => 'suspended',
                            default     => 'pending'
                        };
                        $status_label = match($m['status']) {
                            'active'    => 'Active',
                            'suspended' => 'Suspended',
                            default     => 'Pending'
                        };
                ?>
                    <tr>
                        <!-- Identity -->
                        <td style="padding-left:24px;">
                            <div class="identity-cell">
                                <?php if (!empty($m['profile_pic'])): ?>
                                    <img src="data:image/jpeg;base64,<?= base64_encode($m['profile_pic']) ?>"
                                         class="member-avatar" alt="<?= esc($m['full_name']) ?>">
                                <?php else: ?>
                                    <div class="member-avatar-initials">
                                        <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="member-name"><?= esc($m['full_name']) ?></div>
                                    <div class="member-id">ID: <?= esc($m['national_id']) ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- Contact -->
                        <td>
                            <div class="contact-email"><?= esc($m['email']) ?></div>
                            <div class="contact-phone"><?= esc($m['phone']) ?></div>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="status-badge <?= $status_key ?>">
                                <?= $status_label ?>
                            </span>
                        </td>

                        <!-- Date -->
                        <td>
                            <div class="join-date"><?= date('d M, Y', strtotime($m['join_date'])) ?></div>
                            <div class="join-sub">System Registered</div>
                        </td>

                        <!-- Actions -->
                        <td style="padding-right:24px;">
                            <div class="action-cell">
                                <?php if (can('manage_members')): ?>
                                    <?php if ($m['status'] === 'inactive'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="action-btn-primary action-btn-approve">Approve</button>
                                        </form>
                                    <?php elseif ($m['status'] === 'active'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <button class="action-btn-primary action-btn-suspend">Suspend</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                            <input type="hidden" name="action" value="reactivate">
                                            <button class="action-btn-primary action-btn-reactivate">Reactivate</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <a href="transactions.php?member_id=<?= $m['member_id'] ?>"
                                   class="icon-btn" title="View Ledger">
                                    <i class="bi bi-journal-text"></i>
                                </a>
                                <a href="member_profile.php?id=<?= $m['member_id'] ?>"
                                   class="icon-btn" title="View Profile">
                                    <i class="bi bi-person-lines-fill"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>