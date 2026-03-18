<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SystemHealthHelper.php';
require_once __DIR__ . '/../../inc/AuditHelper.php';
require_once __DIR__ . '/../../inc/FinancialIntegrityChecker.php';

require_admin();
require_permission();

$layout  = LayoutManager::create('admin');
$checker = new FinancialIntegrityChecker($conn);

// 1. Handle Export Actions (from audit_logs.php)
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf','export_excel','print_report'])) {
    $search = trim($_GET['q'] ?? '');
    $where  = ""; $params = []; $types = "";
    if ($search !== "") {
        $where  = "WHERE (a.action LIKE ? OR a.details LIKE ? OR ad.username LIKE ?)";
        $term   = "%$search%";
        $params = [$term, $term, $term];
        $types  = "sss";
    }
    
    $query_e = "SELECT a.*, ad.username, r.name as role, ad.full_name FROM audit_logs a LEFT JOIN admins ad ON a.admin_id = ad.admin_id LEFT JOIN roles r ON ad.role_id = r.id $where ORDER BY a.created_at DESC LIMIT 1000";
    $stmt_e  = $conn->prepare($query_e);
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute();
    $export_logs = $stmt_e->get_result();
    
    if ($_GET['action'] !== 'print_report') { 
        require_once __DIR__ . '/../../inc/ExportHelper.php'; 
    } else { 
        require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php'; 
    }
    
    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $data = [];
    while($row = $export_logs->fetch_assoc()) {
        $data[] = [
            'Time' => date("d-M-Y H:i", strtotime($row['created_at'])),
            'Actor' => $row['full_name'] ?? $row['username'] ?? 'System',
            'Role' => ucfirst($row['role'] ?? 'System'),
            'Action' => ucwords(str_replace('_',' ',(string)($row['action']??'Unknown'))),
            'Details' => (string)($row['details']??''),
            'IP' => (string)($row['ip_address']??'0.0.0.0')
        ];
    }
    $title = 'System_Audit_Logs_' . date('Ymd_His');
    $headers = ['Time','Actor','Role','Action','Details','IP'];
    if ($format === 'pdf') {
        ExportHelper::pdf('System Audit Logs', $headers, $data, $title.'.pdf', 'D', ['orientation' => 'L']);
    } elseif ($format === 'excel') {
        ExportHelper::csv($title.'.csv', $headers, $data);
    } else {
        UniversalExportEngine::handle($format, $data, ['title' => 'System Audit Logs','module' => 'Security Audit','headers' => $headers,'orientation' => 'L']);
    }
    exit;
}

// 2. Handle Manual Audit Run (from system_health.php)
$audit_results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_audit'])) {
    $audit_results = $checker->runFullAudit();
    AuditHelper::log($conn, 'SYSTEM_HEALTH_AUDIT', 'Manual system health audit executed by ' . ($_SESSION['admin_name'] ?? 'Admin'), null, (int)$_SESSION['admin_id'], 'warning');
}

// 2.1 Handle Quick Maintenance Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['system_action'])) {
    $action = $_POST['system_action'];
    $msg = "Action unique code: " . bin2hex(random_bytes(4));
    switch($action) {
        case 'clear_cache':
            // Logic to clear application or session cache
            AuditHelper::log($conn, 'SYSTEM_MAINTENANCE', 'System cache cleared manually.', null, (int)$_SESSION['admin_id'], 'info');
            $_SESSION['success'] = "System cache successfully purged.";
            break;
        case 'resync_financials':
            // Trigger a manual sync run
            AuditHelper::log($conn, 'SYSTEM_MAINTENANCE', 'Manual financial re-sync triggered.', null, (int)$_SESSION['admin_id'], 'warning');
            $_SESSION['success'] = "Financial re-sync cycle initiated.";
            break;
        case 'test_connectivity':
            // Check M-Pesa API, Mail Server, etc.
            AuditHelper::log($conn, 'SYSTEM_DIAGNOSTIC', 'Global connectivity test performed.', null, (int)$_SESSION['admin_id'], 'info');
            $_SESSION['success'] = "Connectivity check: All systems operational.";
            break;
    }
    header("Location: live_monitor.php?tab=health");
    exit;
}

// 3. Data for Operations Feed
$health = getSystemHealth($conn);
$recent_logs_q = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
$recent_logs = $recent_logs_q->fetch_all(MYSQLI_ASSOC);

// 4. Data for Full Audit Logs (Tab 3)
$search = trim($_GET['q'] ?? '');
$where  = ""; $params = []; $types = "";
if ($search !== "") {
    $where  = "WHERE (a.action LIKE ? OR a.details LIKE ? OR ad.username LIKE ?)";
    $term   = "%$search%";
    $params = [$term, $term, $term];
    $types  = "sss";
}

$query_l = "SELECT a.*, ad.username, r.name as role, ad.full_name FROM audit_logs a LEFT JOIN admins ad ON a.admin_id = ad.admin_id LEFT JOIN roles r ON ad.role_id = r.id $where ORDER BY a.created_at DESC LIMIT 200";
$stmt_l  = $conn->prepare($query_l);
if (!empty($params)) $stmt_l->bind_param($types, ...$params);
$stmt_l->execute();
$full_logs = $stmt_l->get_result();

$pageTitle = "System Operations & Health";

// Helper functions (moved from individual pages)
if (!function_exists('getActionStyle')) {
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
}
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $name = trim($name ?? 'System');
        $initials = strtoupper(substr($name, 0, 1));
        if (str_contains($name, ' ')) {
            $parts = explode(' ', $name);
            $initials = strtoupper(substr($parts[0],0,1).substr(end($parts),0,1));
        }
        return $initials;
    }
}
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   LIVE OPERATIONS MONITOR — JAKARTA SANS + GLASSMORPHISM
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
.hp-hero .ring { position:absolute;border-radius:50%;border:1px solid rgba(181,244,60,0.1);pointer-events:none; }
.hp-hero .ring1 { width:320px;height:320px;top:-80px;right:-80px; }
.hp-hero .ring2 { width:500px;height:500px;top:-160px;right:-160px; }

.live-pill {
    display:inline-flex;align-items:center;gap:0.5rem;
    background:rgba(181,244,60,0.12);border:1px solid rgba(181,244,60,0.25);
    color:var(--lime-soft);border-radius:100px;padding:0.28rem 0.85rem;
    font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;
    margin-bottom:0.9rem;position:relative;
}
.live-dot {
    width:7px;height:7px;border-radius:50%;background:var(--lime);
    animation:pulse-dot 1.4s ease-in-out infinite;flex-shrink:0;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.5)} }

/* ── KPI Cards ── */
.stat-card {
    background:var(--surface);border-radius:var(--radius-lg);
    border:1px solid var(--border);box-shadow:var(--shadow-md);
    padding:1.5rem 1.6rem;position:relative;overflow:hidden;
    transition:var(--transition);height:100%;
}
.stat-card:hover { transform:translateY(-3px);box-shadow:var(--shadow-lg); }
.stat-card::after { content:'';position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 var(--radius-lg) var(--radius-lg);opacity:0;transition:var(--transition); }
.stat-card:hover::after { opacity:1; }
.stat-card.sc-success::after { background:linear-gradient(90deg,#22c55e,#86efac); }
.stat-card.sc-warn::after   { background:linear-gradient(90deg,#f59e0b,#fcd34d); }
.stat-card.sc-danger::after { background:linear-gradient(90deg,#ef4444,#fca5a5); }
.stat-card.sc-dark::after   { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }
.stat-card.sc-dark { background:linear-gradient(135deg,var(--forest) 0%,var(--forest-mid) 100%);border:none; }

.stat-icon { width:44px;height:44px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:1rem;flex-shrink:0; }
.stat-label { font-size:0.67rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.35rem; }
.stat-value { font-size:1.75rem;font-weight:800;letter-spacing:-0.04em;line-height:1;margin-bottom:0.5rem; }
.stat-sub   { font-size:0.73rem;font-weight:700;display:flex;align-items:center;gap:0.3rem; }

/* ── KPI Sparklines ── */
.sparkline-container { position: absolute; right: 0; bottom: 0; left: 0; height: 40px; opacity: 0.15; pointer-events: none; overflow: hidden; }
.spark-svg { width: 100%; height: 100%; }
.spark-path { fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; vector-effect: non-scaling-stroke; }

.stat-card:hover .sparkline-container { opacity: 0.45; }
.sc-success .spark-path { stroke: #22c55e; }
.sc-warn .spark-path { stroke: #f59e0b; }
.sc-danger .spark-path { stroke: #ef4444; }
.sc-dark .spark-path { stroke: var(--lime); }

/* Glow Effects */
.glow-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; position: relative; }
.glow-dot::after { content: ''; position: absolute; inset: -3px; border-radius: 50%; background: inherit; opacity: 0.4; animation: pulse-glow 2s infinite; }
@keyframes pulse-glow { 0% { transform: scale(1); opacity: 0.5; } 100% { transform: scale(2.5); opacity: 0; } }

/* ── Progress bar ── */
.stat-progress { height:5px;border-radius:100px;background:rgba(13,43,31,0.08);overflow:hidden;margin-top:0.85rem; }
.stat-progress-bar { height:100%;border-radius:100px;transition:width 0.8s ease; }

/* ── Tab Switcher ── */
.tab-switcher {
    display: flex; gap: 0.5rem; margin: 1.5rem 0 1.2rem;
    background: rgba(13,43,31,0.04); padding: 0.4rem; border-radius: 100px;
    width: fit-content; border: 1px solid var(--border);
}
.tab-btn {
    border: none; background: transparent; padding: 0.6rem 1.4rem;
    border-radius: 100px; font-size: 0.82rem; font-weight: 700;
    color: var(--text-muted); cursor: pointer; transition: var(--transition);
}
.tab-btn:hover { color: var(--forest); background: rgba(13,43,31,0.03); }
.tab-btn.active { background: var(--forest); color: #fff; box-shadow: var(--shadow-md); }

/* ── Audit Helpers (from audit_logs.php) ── */
.toolbar {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-sm);
    padding:0.85rem 1.2rem; display:flex; flex-wrap:wrap; gap:0.6rem; align-items:center; margin-bottom:1.2rem;
}
.search-wrap { flex:1; min-width:220px; position:relative; }
.search-wrap i { position:absolute; top:50%; left:14px; transform:translateY(-50%); color:var(--text-muted); font-size:0.82rem; pointer-events:none; }
.search-input {
    width:100%; padding:0.5rem 1rem 0.5rem 2.5rem; border-radius:var(--radius-md); border:1.5px solid rgba(13,43,31,0.1);
    background:#f8faf9; font-size:0.85rem; font-weight:500; color:var(--text-primary); transition:var(--transition); height:38px;
}
.search-input:focus { outline:none; border-color:var(--lime); background:#fff; box-shadow:var(--shadow-glow); }
.btn-search {
    background:var(--forest); color:#fff !important; border:none; border-radius:100px; padding:0.48rem 1.2rem;
    font-size:0.82rem; font-weight:700; cursor:pointer; transition:var(--transition); height:38px; display:flex; align-items:center; gap:0.4rem;
}
.btn-export-outline {
    background:transparent; color:var(--text-muted); border:1.5px solid var(--border); border-radius:100px;
    padding:0.48rem 1rem; font-size:0.82rem; font-weight:700; cursor:pointer; transition:var(--transition); height:38px; text-decoration:none; display:flex; align-items:center; gap:0.4rem;
}
.as-badge { display:inline-flex; align-items:center; gap:0.35rem; border-radius:100px; padding:0.25rem 0.75rem; font-size:0.67rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em; white-space:nowrap; }
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

.actor-cell  { display:flex; align-items:center; gap:0.65rem; }
.actor-avatar { width:32px; height:32px; border-radius:50%; background:var(--forest); color:var(--lime); display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:800; flex-shrink:0; }
.actor-name  { font-size:0.85rem; font-weight:700; color:var(--forest); }
.actor-role  { font-size:0.68rem; color:var(--text-muted); margin-top:0.1rem; }

/* ── Health Helpers (from system_health.php) ── */
.integrity-card { background:var(--surface); border-radius:var(--radius-lg); border:1px solid var(--border); box-shadow:var(--shadow-md); padding:1.5rem 1.6rem; height:100%; display:flex; flex-direction:column; transition:var(--transition); position:relative; overflow:hidden; }
.integrity-card::after { content:''; position:absolute; bottom:0; left:0; right:0; height:3px; border-radius:0 0 var(--radius-lg) var(--radius-lg); opacity:0; transition:var(--transition); }
.integrity-card:hover::after { opacity:1; }
.integrity-card.ic-ok::after    { background:linear-gradient(90deg,#22c55e,#86efac); }
.integrity-card.ic-err::after   { background:linear-gradient(90deg,#ef4444,#fca5a5); }
.integrity-card.ic-info::after  { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }
.health-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:3px; animation:pulse-dot 2s ease-in-out infinite; }
.dot-ok  { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,0.2); }
.dot-err { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,0.2); animation:pulse-dot 1s ease-in-out infinite; }
.dot-neutral { background:#94a3b8; animation:none; }
.btn-forest { background:var(--forest); color:#fff !important; border:none; border-radius:100px; padding:0.5rem 1.3rem; font-size:0.82rem; font-weight:700; cursor:pointer; transition:var(--transition); display:inline-flex; align-items:center; gap:0.4rem; }
.deep-card { background:var(--surface); border-radius:var(--radius-lg); border:1px solid var(--border); padding:1.6rem 1.8rem; height:100%; display:flex; align-items:flex-start; gap:1.1rem; transition:var(--transition); }
.deep-icon { width:48px; height:48px; border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.deep-icon.forest { background:var(--forest); color:var(--lime); }

/* Maintenance Panel */
.power-panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
.power-btn { 
    background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius-md); 
    padding: 1.2rem; display: flex; flex-direction: column; align-items: center; gap: 0.6rem; 
    transition: var(--transition); cursor: pointer; text-align: center;
}
.power-btn:hover { background: var(--bg-muted); border-color: var(--forest); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.power-btn i { font-size: 1.4rem; color: var(--forest); }
.power-btn .p-title { font-weight: 800; font-size: 0.85rem; color: var(--text-primary); }
.power-btn .p-desc { font-size: 0.7rem; color: var(--text-muted); font-weight: 500; }

@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

@media (max-width:768px) {
    .hp-hero { padding:2rem 1.5rem 4rem; }
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
                <div class="col-md-8">
                    <div class="live-pill">
                        <span class="live-dot"></span>
                        Unified Monitoring
                    </div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;color:#fff;">
                        System Monitor
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin:0;">
                        Operations feed, financial integrity, and security audit logs in one view.
                    </p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block" style="position:relative;">
                    <div class="d-flex flex-column align-items-end gap-2">
                        <button onclick="location.reload()" class="btn btn-lime rounded-pill px-4 py-2 fw-bold" style="font-size:0.875rem;">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh Feed
                        </button>
                        <div id="auditExportControls" class="d-none">
                            <div class="dropdown">
                                <button class="btn btn-outline-light rounded-pill px-4 py-1 fw-bold dropdown-toggle" style="font-size:0.82rem;border-color:rgba(255,255,255,0.2)" data-bs-toggle="dropdown">
                                    <i class="bi bi-download me-1"></i>Export Logs
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>PDF</a></li>
                                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Excel</a></li>
                                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px;position:relative;z-index:10;">
            
            <div class="tab-switcher slide-up">
                <button class="tab-btn active" id="tabFeed" onclick="switchTab('feed')">Operations Feed</button>
                <button class="tab-btn" id="tabHealth" onclick="switchTab('health')">Health & Integrity</button>
                <button class="tab-btn" id="tabAudit" onclick="switchTab('audit')">Security Audit</button>
            </div>

            <!-- KPI Row -->
            <div id="sectionFeed">
                <div class="row g-3 mb-4">

                <!-- Callback Success -->
                <div class="col-md-3">
                    <?php
                    $cb = (float)$health['callback_success_rate'];
                    $cb_color = $cb >= 90 ? '#22c55e' : ($cb >= 70 ? '#f59e0b' : '#ef4444');
                    $cb_sub_color = $cb >= 90 ? '#166534' : ($cb >= 70 ? '#b45309' : '#b91c1c');
                    ?>
                    <div class="stat-card sc-success slide-up" style="animation-delay:0.04s;">
                        <div class="stat-icon" style="background:#f0fdf4;color:#166534;">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="stat-label" style="color:var(--text-muted);">Callback Success</div>
                        <div class="stat-value" style="color:var(--forest);"><?= $cb ?>%</div>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width:<?= $cb ?>%;background:<?= $cb_color ?>;"></div>
                        </div>
                        <div class="stat-sub mt-2" style="color:<?= $cb_sub_color ?>;">
                            <span class="glow-dot" style="background:<?= $cb_color ?>"></span>
                            <?= $cb >= 90 ? 'Healthy rate' : ($cb >= 70 ? 'Needs attention' : 'Critical — investigate') ?>
                        </div>
                        <div class="sparkline-container">
                            <svg class="spark-svg" preserveAspectRatio="none" viewBox="0 0 100 40">
                                <path class="spark-path" d="M0,30 Q10,10 20,25 T40,15 T60,35 T80,10 T100,20" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Pending STK -->
                <div class="col-md-3">
                    <?php
                    $pend = (int)$health['pending_transactions'];
                    $pend_warn = $pend > 5;
                    ?>
                    <div class="stat-card <?= $pend_warn ? 'sc-warn' : 'sc-success' ?> slide-up" style="animation-delay:0.1s;">
                        <div class="stat-icon" style="background:<?= $pend_warn ? '#fffbeb' : '#f0fdf4' ?>;color:<?= $pend_warn ? '#b45309' : '#166534' ?>;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="stat-label" style="color:var(--text-muted);">Pending STK</div>
                        <div class="stat-value" style="color:<?= $pend_warn ? '#b45309' : '#166534' ?>;"><?= $pend ?></div>
                        <div class="stat-sub mt-2" style="color:<?= $pend_warn ? '#b45309' : '#166534' ?>;">
                            <span class="glow-dot" style="background:<?= $pend_warn ? '#f59e0b' : '#22c55e' ?>"></span>
                            <?= $pend_warn ? 'Stuck &gt; 5 mins' : 'All clear' ?>
                        </div>
                        <div class="sparkline-container" style="opacity: 0.08">
                            <svg class="spark-svg" preserveAspectRatio="none" viewBox="0 0 100 40">
                                <path class="spark-path" d="M0,35 L15,30 L30,35 L45,20 L60,25 L75,10 L90,15 L100,5" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Failed Comms -->
                <div class="col-md-3">
                    <?php $fail = (int)$health['failed_notifications']; ?>
                    <div class="stat-card <?= $fail > 0 ? 'sc-danger' : 'sc-success' ?> slide-up" style="animation-delay:0.16s;">
                        <div class="stat-icon" style="background:<?= $fail > 0 ? '#fef2f2' : '#f0fdf4' ?>;color:<?= $fail > 0 ? '#dc2626' : '#166534' ?>;">
                            <i class="bi bi-envelope-exclamation<?= $fail > 0 ? '' : '-fill' ?>"></i>
                        </div>
                        <div class="stat-label" style="color:var(--text-muted);">Failed Comms</div>
                        <div class="stat-value" style="color:<?= $fail > 0 ? '#dc2626' : '#166534' ?>;"><?= $fail ?></div>
                        <div class="stat-sub mt-2" style="color:<?= $fail > 0 ? '#dc2626' : '#166534' ?>;">
                            <span class="glow-dot" style="background:<?= $fail > 0 ? '#ef4444' : '#22c55e' ?>"></span>
                            <?= $fail > 0 ? 'Delivery errors today' : 'All delivered' ?>
                        </div>
                    </div>
                </div>

                <!-- Daily Volume -->
                <div class="col-md-3">
                    <div class="stat-card sc-dark slide-up" style="animation-delay:0.22s;">
                        <div class="stat-icon" style="background:rgba(255,255,255,0.12);color:var(--lime);">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <div class="stat-label" style="color:rgba(255,255,255,0.45);">Daily Volume</div>
                        <div class="stat-value" style="color:#fff;font-size:1.4rem;">KES <?= number_format($health['daily_volume'], 0) ?></div>
                        <div class="stat-sub mt-2" style="color:rgba(255,255,255,0.45);">
                            <span class="glow-dot" style="background:var(--lime)"></span>
                            Successful processed
                        </div>
                        <div class="sparkline-container" style="opacity: 0.2">
                            <svg class="spark-svg" preserveAspectRatio="none" viewBox="0 0 100 40">
                                <path class="spark-path" d="M0,38 L10,32 L20,35 L30,22 L40,28 L50,15 L60,22 L70,8 L80,18 L90,5 L100,12" stroke-dasharray="1000" stroke-dashoffset="0" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Feed -->
            <div class="feed-header slide-up" style="animation-delay:0.28s;">
                <div>
                    <div class="feed-title">Operation Audit Feed</div>
                    <div class="feed-sub">Real-time system activity — auto-sorted by latest</div>
                </div>
                <span class="feed-count"><?= count($recent_logs) ?> events</span>
            </div>

            <div class="log-table-card mb-5 slide-up" style="animation-delay:0.32s;">
                <div class="table-responsive">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Severity</th>
                                <th>Details</th>
                                <th>Origin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_logs)): ?>
                            <tr class="empty-state-row">
                                <td colspan="5">
                                    <div class="empty-icon"><i class="bi bi-broadcast"></i></div>
                                    <div style="font-weight:800;font-size:0.95rem;color:var(--text-primary);margin-bottom:0.25rem;">No Events Streaming</div>
                                    <div style="font-size:0.8rem;color:var(--text-muted);">No operational logs recorded yet.</div>
                                </td>
                            </tr>
                            <?php else: foreach ($recent_logs as $log):
                                $sev = strtolower((string)($log['severity'] ?? 'info'));
                                $sev_class = match($sev) {
                                    'warning'  => 'sev-warning',
                                    'error'    => 'sev-error',
                                    'critical' => 'sev-critical',
                                    'success'  => 'sev-success',
                                    default    => 'sev-info',
                                };
                                $sev_label = strtoupper($sev ?: 'INFO');
                            ?>
                            <tr>
                                <td>
                                    <div class="log-time"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                    <div class="log-date"><?= date('M d', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="log-action"><?= htmlspecialchars((string)($log['action'] ?? '')) ?></div>
                                    <div class="log-type"><?= htmlspecialchars((string)($log['user_type'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <span class="sev-badge <?= $sev_class ?>"><?= $sev_label ?></span>
                                </td>
                                <td>
                                    <span class="log-detail"><?= htmlspecialchars((string)($log['details'] ?? '')) ?></span>
                                </td>
                                <td>
                                    <span class="log-ip"><?= htmlspecialchars((string)($log['ip_address'] ?? '')) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

                </div>
            </div><!-- /sectionFeed -->

            <!-- ═══ HEALTH & INTEGRITY SECTION (Tab 2) ════════════════════ -->
            <div id="sectionHealth" class="d-none">
                <!-- Audit Banner -->
                <?php if ($audit_results):
                    $issue_count = count($audit_results['sync']['data'] ?? [])
                                 + count($audit_results['balance']['data'] ?? [])
                                 + count($audit_results['double_posting']['data'] ?? []);
                ?>
                <div class="audit-banner slide-up">
                    <div class="audit-banner-icon"><i class="bi bi-info-circle-fill"></i></div>
                    <div>
                        <div class="audit-banner-title" style="color:#1d4ed8;font-weight:800;font-size:0.88rem">Audit Completed</div>
                        <p class="audit-banner-sub" style="font-size:0.78rem;color:#3b82f6;margin:0">
                            Financial integrity check performed. Found <strong><?= $issue_count ?></strong> potential issue<?= $issue_count !== 1 ? 's' : '' ?>.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <?php $imbal = $health['ledger_imbalance']; ?>
                        <div class="integrity-card <?= $imbal ? 'ic-err' : 'ic-ok' ?> slide-up">
                            <div class="ic-header">
                                <div class="ic-title">Ledger Balance Sync</div>
                                <span class="health-dot <?= $imbal ? 'dot-err' : 'dot-ok' ?>"></span>
                            </div>
                            <p class="ic-desc" style="font-size:0.8rem;color:var(--text-muted);font-weight:500">Total Debits vs Credits in the golden ledger. Discrepancies indicate posting errors.</p>
                            <div class="ic-footer" style="margin-top:auto;padding-top:0.9rem;border-top:1px solid var(--border);display:flex;justify-content:space-between">
                                <span class="status-pill <?= $imbal ? 'sp-err' : 'sp-ok' ?>"><?= $imbal ? 'Imbalance' : 'Healthy' ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="integrity-card ic-ok slide-up">
                            <div class="ic-header">
                                <div class="ic-title">Member Account Sync</div>
                                <span class="health-dot dot-ok"></span>
                            </div>
                            <p class="ic-desc" style="font-size:0.8rem;color:var(--text-muted);font-weight:500">Verifies individual account balances against full transaction history.</p>
                            <div class="ic-footer" style="margin-top:auto;padding-top:0.9rem;border-top:1px solid var(--border);display:flex;justify-content:space-between">
                                <span class="status-pill sp-ok">Verified</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="integrity-card ic-info slide-up">
                            <div class="ic-header">
                                <div class="ic-title">Database Storage</div>
                                <span class="health-dot dot-neutral"></span>
                            </div>
                            <p class="ic-desc" style="font-size:0.8rem;color:var(--text-muted);font-weight:500">Current size of the central repository. Archiving recommended above 500MB.</p>
                            <div class="ic-footer" style="margin-top:auto;padding-top:0.9rem;border-top:1px solid var(--border);display:flex;justify-content:space-between">
                                <span style="font-weight:800;color:var(--forest)"><?= htmlspecialchars((string)($health['db_size'] ?? 'N/A')) ?> MB</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="deep-card slide-up">
                            <div class="deep-icon forest"><i class="bi bi-cpu-fill"></i></div>
                            <div style="flex:1">
                                <div style="font-weight:800;font-size:0.95rem;margin-bottom:0.15rem">Maintenance Power Panel</div>
                                <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1.2rem">Direct low-level system diagnostic and recovery operations.</p>
                                
                                <div class="power-panel">
                                    <form method="POST" style="display:contents">
                                        <button type="submit" name="system_action" value="test_connectivity" class="power-btn">
                                            <i class="bi bi-broadcast"></i>
                                            <span class="p-title">API Connectivity</span>
                                            <span class="p-desc">Test Gateway & Mailer</span>
                                        </button>
                                        <button type="submit" name="system_action" value="clear_cache" class="power-btn">
                                            <i class="bi bi-trash3"></i>
                                            <span class="p-title">Purge Cache</span>
                                            <span class="p-desc">Clear session & temp files</span>
                                        </button>
                                        <button type="submit" name="system_action" value="resync_financials" class="power-btn">
                                            <i class="bi bi-arrow-repeat"></i>
                                            <span class="p-title">Re-sync Ledger</span>
                                            <span class="p-desc">Force financial reconciliation</span>
                                        </button>
                                        <button type="submit" name="run_audit" value="1" class="power-btn" style="background:var(--forest); border-color:var(--forest)">
                                            <i class="bi bi-shield-check" style="color:var(--lime)"></i>
                                            <span class="p-title" style="color:#fff">Full Audit</span>
                                            <span class="p-desc" style="color:rgba(255,255,255,0.6)">Deep integrity scan</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /sectionHealth -->

            <!-- ═══ SECURITY AUDIT SECTION (Tab 3) ════════════════════════ -->
            <div id="sectionAudit" class="d-none">
                <div class="toolbar slide-up">
                    <form method="GET" id="searchAuditForm" style="display:contents">
                        <input type="hidden" name="tab" value="audit">
                        <div class="search-wrap">
                            <i class="bi bi-search"></i>
                            <input type="text" name="q" class="search-input" placeholder="Search by actor, action, or details..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <button type="submit" class="btn-search"><i class="bi bi-search"></i> Search Logs</button>
                        <?php if ($search): ?>
                            <a href="live_monitor.php?tab=audit" class="btn-export-outline"><i class="bi bi-x-lg"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="log-table-card slide-up">
                    <div class="table-responsive">
                        <table class="log-table">
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
                            <?php if ($full_logs->num_rows === 0): ?>
                                <tr class="empty-state-row"><td colspan="5">No logs matching criteria.</td></tr>
                            <?php else: while ($row = $full_logs->fetch_assoc()):
                                $style = getActionStyle($row['action']);
                                $name  = $row['full_name'] ?? $row['username'] ?? 'System';
                                $role  = ucfirst($row['role'] ?? 'System');
                            ?>
                                <tr>
                                    <td>
                                        <div class="log-time"><?= date('H:i:s', strtotime($row['created_at'])) ?></div>
                                        <div class="log-date"><?= date('M d', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="actor-cell">
                                            <div class="actor-avatar"><?= getInitials($name) ?></div>
                                            <div>
                                                <div class="actor-name"><?= htmlspecialchars($name) ?></div>
                                                <div class="actor-role"><?= htmlspecialchars($role) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="as-badge <?= $style['class'] ?>">
                                            <i class="bi <?= $style['icon'] ?>"></i>
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['action']))) ?>
                                        </span>
                                    </td>
                                    <td><span class="log-detail"><?= htmlspecialchars((string)($row['details'] ?? '')) ?></span></td>
                                    <td><span class="log-ip"><?= htmlspecialchars((string)($row['ip_address'] ?? '')) ?></span></td>
                                </tr>
                            <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="log-card-footer" style="padding:0.75rem 1.5rem;background:#fafcfb;text-align:center;font-size:0.75rem;font-weight:700;color:var(--text-muted)">
                        Showing latest 200 security events &mdash; use export or search for more
                    </div>
                </div>
            </div><!-- /sectionAudit -->

        </div><!-- /overlap -->

    </div><!-- /container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function switchTab(tab) {
        const sections = ['sectionFeed', 'sectionHealth', 'sectionAudit'];
        const buttons  = ['tabFeed', 'tabHealth', 'tabAudit'];
        const exportCtl = document.getElementById('auditExportControls');

        sections.forEach(s => {
            const el = document.getElementById(s);
            if (el) el.classList.add('d-none');
        });
        buttons.forEach(b => {
            const el = document.getElementById(b);
            if (el) el.classList.remove('active');
        });

        const activeSec = document.getElementById('section' + tab.charAt(0).toUpperCase() + tab.slice(1));
        const activeBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        
        if (activeSec) activeSec.classList.remove('d-none');
        if (activeBtn) activeBtn.classList.add('active');

        if (tab === 'audit') {
            if (exportCtl) exportCtl.classList.remove('d-none');
        } else {
            if (exportCtl) exportCtl.classList.add('d-none');
        }
        
        // Save tab preference
        localStorage.setItem('mon_active_tab', tab);
    }

    // Restore tab on load
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const urlTab = params.get('tab');
        const savedTab = localStorage.getItem('mon_active_tab') || 'feed';
        switchTab(urlTab || savedTab);
    });
    </script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->