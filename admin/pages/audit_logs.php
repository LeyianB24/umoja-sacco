<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

require_admin();
$layout = LayoutManager::create('admin');
require_permission();

$db         = $conn;
$admin_name = $_SESSION['full_name'] ?? 'System Admin';

$search = trim($_GET['q'] ?? '');
$where  = ""; $params = []; $types = "";
if ($search !== "") {
    $where  = "WHERE (a.action LIKE ? OR a.details LIKE ? OR ad.username LIKE ?)";
    $term   = "%$search%";
    $params = [$term, $term, $term];
    $types  = "sss";
}

if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf','export_excel','print_report'])) {
    $where_e = $where; $params_e = $params; $types_e = $types;
    $query_e = "SELECT a.*, ad.username, r.name as role, ad.full_name FROM audit_logs a LEFT JOIN admins ad ON a.admin_id = ad.admin_id LEFT JOIN roles r ON ad.role_id = r.id $where_e ORDER BY a.created_at DESC LIMIT 1000";
    $stmt_e  = $db->prepare($query_e);
    if (!empty($params_e)) $stmt_e->bind_param($types_e, ...$params_e);
    $stmt_e->execute();
    $export_logs = $stmt_e->get_result();
    if ($_GET['action'] !== 'print_report') { require_once __DIR__ . '/../../inc/ExportHelper.php'; }
    else { require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php'; }
    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $data = [];
    while($row = $export_logs->fetch_assoc()) {
        $data[] = ['Time' => date("d-M-Y H:i", strtotime($row['created_at'])),'Actor' => $row['full_name'] ?? $row['username'] ?? 'System','Role' => ucfirst($row['role'] ?? 'System'),'Action' => ucwords(str_replace('_',' ',(string)($row['action']??'Unknown'))),'Details' => (string)($row['details']??''),'IP' => (string)($row['ip_address']??'0.0.0.0')];
    }
    $title = 'System_Audit_Logs_' . date('Ymd_His');
    $headers = ['Time','Actor','Role','Action','Details','IP'];
    if ($format === 'pdf') ExportHelper::pdf('System Audit Logs', $headers, $data, $title.'.pdf', 'D', ['orientation' => 'L']);
    elseif ($format === 'excel') ExportHelper::csv($title.'.csv', $headers, $data);
    else UniversalExportEngine::handle($format, $data, ['title' => 'System Audit Logs','module' => 'Security Audit','headers' => $headers,'orientation' => 'L']);
    exit;
}

$query = "SELECT a.*, ad.username, r.name as role, ad.full_name FROM audit_logs a LEFT JOIN admins ad ON a.admin_id = ad.admin_id LEFT JOIN roles r ON ad.role_id = r.id $where ORDER BY a.created_at DESC LIMIT 200";
$stmt  = $db->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();

if (!function_exists('getInitials')) {
    function getInitials($name) { return strtoupper(substr($name ?? 'S', 0, 1)); }
}

function getActionStyle($action) {
    $a = strtolower($action);
    if (str_contains($a,'delete')||str_contains($a,'fail')||str_contains($a,'error')||str_contains($a,'lock'))
        return ['class'=>'as-danger', 'icon'=>'bi-exclamation-octagon-fill'];
    if (str_contains($a,'update')||str_contains($a,'edit')||str_contains($a,'suspend'))
        return ['class'=>'as-warning', 'icon'=>'bi-pencil-square'];
    if (str_contains($a,'create')||str_contains($a,'add')||str_contains($a,'approve')||str_contains($a,'unlock'))
        return ['class'=>'as-success', 'icon'=>'bi-check-circle-fill'];
    if (str_contains($a,'login'))
        return ['class'=>'as-info', 'icon'=>'bi-arrow-right-circle-fill'];
    return ['class'=>'as-neutral', 'icon'=>'bi-activity'];
}

$pageTitle = "Audit Logs";
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   AUDIT LOGS — JAKARTA SANS + GLASSMORPHISM THEME
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; }

:root {
    --forest:       #0d2b1f;
    --forest-mid:   #1a3d2b;
    --forest-light: #234d36;
    --lime:         #b5f43c;
    --lime-soft:    #d6fb8a;
    --lime-glow:    rgba(181,244,60,0.18);
    --lime-glow-sm: rgba(181,244,60,0.08);
    --surface:      #ffffff;
    --bg-muted:     #f5f8f6;
    --text-primary: #0d1f15;
    --text-muted:   #6b7c74;
    --border:       rgba(13,43,31,0.07);
    --radius-sm:    8px;
    --radius-md:    14px;
    --radius-lg:    20px;
    --radius-xl:    28px;
    --shadow-sm:    0 2px 8px rgba(13,43,31,0.07);
    --shadow-md:    0 8px 28px rgba(13,43,31,0.11);
    --shadow-lg:    0 20px 60px rgba(13,43,31,0.16);
    --shadow-glow:  0 0 0 3px var(--lime-glow), 0 6px 24px rgba(181,244,60,0.15);
    --transition:   all 0.22s cubic-bezier(0.4,0,0.2,1);
}

body, *, input, select, textarea, button, .btn, table, th, td,
h1,h2,h3,h4,h5,h6,p,span,div,label,a,.modal,.offcanvas {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Hero ── */
.hp-hero {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, #0e3522 100%);
    border-radius: var(--radius-xl);
    padding: 2.6rem 3rem 5rem;
    position: relative; overflow: hidden; color: #fff; margin-bottom: 0;
}
.hp-hero::before {
    content:''; position:absolute; inset:0;
    background:
        radial-gradient(ellipse 55% 70% at 95% 5%,  rgba(181,244,60,0.13) 0%, transparent 60%),
        radial-gradient(ellipse 35% 45% at 5%  95%, rgba(181,244,60,0.06) 0%, transparent 60%);
    pointer-events:none;
}
.hp-hero .ring { position:absolute; border-radius:50%; border:1px solid rgba(181,244,60,0.1); pointer-events:none; }
.hp-hero .ring1 { width:320px; height:320px; top:-80px; right:-80px; }
.hp-hero .ring2 { width:500px; height:500px; top:-160px; right:-160px; }
.hero-badge {
    display:inline-flex; align-items:center; gap:0.45rem;
    background:rgba(181,244,60,0.12); border:1px solid rgba(181,244,60,0.25);
    color:var(--lime-soft); border-radius:100px; padding:0.28rem 0.85rem;
    font-size:0.68rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase;
    margin-bottom:0.9rem; position:relative;
}
.hero-badge::before { content:''; width:6px; height:6px; border-radius:50%; background:var(--lime); animation:pulse-dot 2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

/* ── Toolbar ── */
.toolbar {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-sm);
    padding:0.85rem 1.2rem;
    display:flex; flex-wrap:wrap; gap:0.6rem; align-items:center;
    margin-bottom:1.2rem;
}
.search-wrap { flex:1; min-width:220px; position:relative; }
.search-wrap i {
    position:absolute; top:50%; left:14px; transform:translateY(-50%);
    color:var(--text-muted); font-size:0.82rem; pointer-events:none;
}
.search-input {
    width:100%; padding:0.5rem 1rem 0.5rem 2.5rem;
    border-radius:var(--radius-md); border:1.5px solid rgba(13,43,31,0.1);
    background:#f8faf9; font-size:0.85rem; font-weight:500; color:var(--text-primary);
    font-family:'Plus Jakarta Sans',sans-serif !important; transition:var(--transition); height:38px;
}
.search-input:focus { outline:none; border-color:var(--lime); background:#fff; box-shadow:var(--shadow-glow); }
.btn-search {
    background:var(--forest); color:#fff; border:none; border-radius:100px;
    padding:0.48rem 1.2rem; font-size:0.82rem; font-weight:700; cursor:pointer;
    transition:var(--transition); height:38px; white-space:nowrap;
    display:flex; align-items:center; gap:0.4rem;
}
.btn-search:hover { background:var(--forest-light); }
.btn-export-outline {
    background:transparent; color:var(--text-muted); border:1.5px solid var(--border);
    border-radius:100px; padding:0.48rem 1rem; font-size:0.82rem; font-weight:700;
    cursor:pointer; transition:var(--transition); height:38px; text-decoration:none;
    display:flex; align-items:center; gap:0.4rem; white-space:nowrap;
}
.btn-export-outline:hover { background:var(--bg-muted); color:var(--text-primary); border-color:rgba(13,43,31,0.15); }
.btn-export-dark {
    background:var(--forest); color:#fff !important; border:none; border-radius:100px;
    padding:0.48rem 1.1rem; font-size:0.82rem; font-weight:700;
    cursor:pointer; transition:var(--transition); height:38px;
}
.btn-export-dark:hover { background:var(--forest-light); }

/* Dropdown */
.dropdown-menu { border-radius:var(--radius-md) !important; border:1px solid var(--border) !important; box-shadow:var(--shadow-lg) !important; padding:0.4rem !important; }
.dropdown-item { border-radius:8px; font-size:0.84rem; font-weight:600; padding:0.58rem 0.9rem !important; color:var(--text-primary) !important; transition:var(--transition); }
.dropdown-item:hover { background:#f0faf4 !important; }

/* ── Log Table Card ── */
.log-card {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-md); overflow:hidden;
}
.log-card-header {
    padding:1rem 1.5rem; border-bottom:1px solid var(--border);
    display:flex; justify-content:space-between; align-items:center; background:#fff;
}
.log-card-header-left { display:flex; align-items:center; gap:0.6rem; }
.live-dot { width:8px; height:8px; border-radius:50%; background:#22c55e; animation:pulse-dot 1.6s ease-in-out infinite; flex-shrink:0; }
.log-card-title { font-weight:800; font-size:0.95rem; color:var(--text-primary); margin:0; }
.record-count {
    background:var(--bg-muted); border:1px solid var(--border); border-radius:100px;
    padding:0.22rem 0.8rem; font-size:0.72rem; font-weight:800; color:var(--text-muted);
}
.log-card-footer {
    padding:0.75rem 1.5rem; border-top:1px solid var(--border);
    background:#fafcfb; text-align:center;
    font-size:0.75rem; font-weight:700; color:var(--text-muted);
}

/* ── Audit Table ── */
.audit-table { width:100%; border-collapse:separate; border-spacing:0; }
.audit-table thead th {
    background:#f5f8f6; color:var(--text-muted); font-size:0.67rem;
    font-weight:800; text-transform:uppercase; letter-spacing:0.1em;
    padding:0.8rem 1rem; border-bottom:1px solid var(--border); white-space:nowrap;
}
.audit-table thead th:first-child { padding-left:1.5rem; }
.audit-table thead th:last-child  { padding-right:1.5rem; text-align:right; }
.audit-table tbody tr { border-bottom:1px solid rgba(13,43,31,0.04); transition:var(--transition); }
.audit-table tbody tr:last-child  { border-bottom:none; }
.audit-table tbody tr:hover { background:#f0faf4; }
.audit-table tbody td { padding:0.85rem 1rem; vertical-align:middle; }
.audit-table tbody td:first-child { padding-left:1.5rem; }
.audit-table tbody td:last-child  { padding-right:1.5rem; text-align:right; }

/* Cell types */
.cell-time   { font-size:0.85rem; font-weight:700; color:var(--text-primary); }
.cell-date   { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; color:var(--text-muted); margin-top:0.15rem; }
.actor-cell  { display:flex; align-items:center; gap:0.65rem; }
.actor-avatar {
    width:34px; height:34px; border-radius:50%;
    background:var(--forest); color:var(--lime);
    display:flex; align-items:center; justify-content:center;
    font-size:0.7rem; font-weight:800; flex-shrink:0;
}
.actor-name  { font-size:0.875rem; font-weight:700; color:var(--forest); }
.actor-role  { font-size:0.7rem; color:var(--text-muted); margin-top:0.1rem; }
.detail-text { font-size:0.8rem; color:var(--text-muted); font-weight:500; max-width:340px; line-height:1.5; }
.ip-badge    { font-family:'Courier New',monospace !important; font-size:0.72rem; font-weight:700; background:#f5f8f6; border:1px solid var(--border); border-radius:6px; padding:0.18rem 0.55rem; color:var(--text-muted); }

/* Action style badges */
.as-badge {
    display:inline-flex; align-items:center; gap:0.35rem;
    border-radius:100px; padding:0.25rem 0.75rem;
    font-size:0.67rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em;
    white-space:nowrap;
}
.as-badge::before { content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.as-danger  { background:#fef2f2; color:#b91c1c; border:1px solid rgba(239,68,68,0.18); }
.as-danger::before  { background:#ef4444; }
.as-warning { background:#fffbeb; color:#b45309; border:1px solid rgba(245,158,11,0.18); }
.as-warning::before { background:#f59e0b; }
.as-success { background:#f0fdf4; color:#166534; border:1px solid rgba(22,163,74,0.18); }
.as-success::before { background:#22c55e; }
.as-info    { background:#eff6ff; color:#1d4ed8; border:1px solid rgba(59,130,246,0.18); }
.as-info::before    { background:#3b82f6; }
.as-neutral { background:#f5f8f6; color:var(--text-muted); border:1px solid var(--border); }
.as-neutral::before { background:#94a3b8; }

/* Empty state */
.empty-cell { text-align:center; padding:5rem 2rem !important; }
.empty-icon { width:64px; height:64px; border-radius:16px; background:#f5f8f6; display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:#c4d4cb; margin:0 auto 0.9rem; }

/* Buttons */
.btn-lime { background:var(--lime); color:var(--forest) !important; border:none; font-weight:700; transition:var(--transition); }
.btn-lime:hover { background:var(--lime-soft); box-shadow:var(--shadow-glow); transform:translateY(-1px); }

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

@media (max-width:768px) {
    .hp-hero { padding:2rem 1.5rem 4rem; }
    .toolbar { flex-direction:column; align-items:stretch; }
    .search-wrap { min-width:100%; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <!-- Hero -->
        <div class="hp-hero fade-in">
            <div class="ring ring1"></div>
            <div class="ring ring2"></div>
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="hero-badge">Security &amp; Compliance</div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;color:#fff;">
                        System Audit Trails
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin:0;">
                        Track admin activities, security events, and financial operations in real-time.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0 d-none d-lg-flex align-items-center justify-content-end gap-2" style="position:relative;">
                    <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"
                       class="btn-export-outline" style="color:rgba(255,255,255,0.7);border-color:rgba(255,255,255,0.2);background:rgba(255,255,255,0.08);">
                        <i class="bi bi-printer"></i>Print
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-lime rounded-pill px-4 py-2 fw-bold dropdown-toggle" style="font-size:0.82rem;" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">
                                <i class="bi bi-file-earmark-pdf text-danger me-2"></i>Export PDF
                            </a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">
                                <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Export Excel
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px; position:relative; z-index:10;">

            <!-- Search Toolbar -->
            <div class="toolbar slide-up" style="animation-delay:0.06s;">
                <form method="GET" style="display:contents;">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" class="search-input"
                               placeholder="Search by actor, action, or details..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn-search">
                        <i class="bi bi-search" style="font-size:0.75rem;"></i>Search Logs
                    </button>
                    <?php if ($search): ?>
                    <a href="audit_logs.php" class="btn-export-outline">
                        <i class="bi bi-x-lg" style="font-size:0.7rem;"></i>Clear
                    </a>
                    <?php endif; ?>
                </form>
                <div style="margin-left:auto;display:flex;gap:0.5rem;" class="d-lg-none">
                    <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank" class="btn-export-outline">
                        <i class="bi bi-printer"></i>Print
                    </a>
                    <div class="dropdown">
                        <button class="btn-export-dark rounded-pill dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>PDF</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Excel</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Log Table Card -->
            <div class="log-card slide-up" style="animation-delay:0.12s;">
                <div class="log-card-header">
                    <div class="log-card-header-left">
                        <span class="live-dot"></span>
                        <h5 class="log-card-title">Recent Activity</h5>
                    </div>
                    <span class="record-count"><?= $logs->num_rows ?> Records</span>
                </div>

                <div class="table-responsive">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Actor</th>
                                <th>Action Type</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="empty-cell">
                                    <div class="empty-icon"><i class="bi bi-shield-check"></i></div>
                                    <div style="font-weight:800;font-size:0.95rem;color:var(--text-primary);margin-bottom:0.25rem;">No Logs Found</div>
                                    <div style="font-size:0.8rem;color:var(--text-muted);">
                                        <?= $search ? 'No results for "'.htmlspecialchars($search).'".' : 'No audit logs have been recorded yet.' ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else: while ($row = $logs->fetch_assoc()):
                                $style = getActionStyle($row['action']);
                                $name  = $row['full_name'] ?? $row['username'] ?? 'System';
                                $role  = ucfirst($row['role'] ?? 'System');
                                $initials = strtoupper(substr(trim($name), 0, 1));
                                if (str_contains($name, ' ')) {
                                    $parts = explode(' ', trim($name));
                                    $initials = strtoupper(substr($parts[0],0,1).substr(end($parts),0,1));
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="cell-time"><?= date('H:i:s', strtotime($row['created_at'])) ?></div>
                                    <div class="cell-date"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="actor-cell">
                                        <div class="actor-avatar"><?= htmlspecialchars($initials) ?></div>
                                        <div>
                                            <div class="actor-name"><?= htmlspecialchars($name) ?></div>
                                            <div class="actor-role"><?= htmlspecialchars($role) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="as-badge <?= $style['class'] ?>">
                                        <i class="bi <?= $style['icon'] ?>" style="font-size:0.65rem;"></i>
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['action']))) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="detail-text"><?= htmlspecialchars((string)($row['details'] ?? '')) ?></span>
                                </td>
                                <td>
                                    <span class="ip-badge"><?= htmlspecialchars((string)($row['ip_address'] ?? '::1')) ?></span>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="log-card-footer">
                    Showing latest 200 entries &mdash; use export for full history
                </div>
            </div>

        </div><!-- /overlap -->

    </div><!-- /container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->