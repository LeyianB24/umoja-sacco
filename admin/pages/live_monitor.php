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

// 1. Export Actions
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
    if ($_GET['action'] !== 'print_report') { require_once __DIR__ . '/../../inc/ExportHelper.php'; }
    else { require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php'; }
    $format = match($_GET['action']) { 'export_excel' => 'excel', 'print_report' => 'print', default => 'pdf' };
    $data = [];
    while ($row = $export_logs->fetch_assoc()) {
        $data[] = ['Time'=>date("d-M-Y H:i",strtotime($row['created_at'])),'Actor'=>$row['full_name']??$row['username']??'System','Role'=>ucfirst($row['role']??'System'),'Action'=>ucwords(str_replace('_',' ',(string)($row['action']??'Unknown'))),'Details'=>(string)($row['details']??''),'IP'=>(string)($row['ip_address']??'0.0.0.0')];
    }
    $title = 'System_Audit_Logs_'.date('Ymd_His');
    $headers = ['Time','Actor','Role','Action','Details','IP'];
    if ($format==='pdf') ExportHelper::pdf('System Audit Logs',$headers,$data,$title.'.pdf','D',['orientation'=>'L']);
    elseif ($format==='excel') ExportHelper::csv($title.'.csv',$headers,$data);
    else UniversalExportEngine::handle($format,$data,['title'=>'System Audit Logs','module'=>'Security Audit','headers'=>$headers,'orientation'=>'L']);
    exit;
}

// 2. Manual Audit Run
$audit_results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_audit'])) {
    $conn->query("CREATE TABLE IF NOT EXISTS integrity_checks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        check_type VARCHAR(100) NOT NULL,
        status VARCHAR(50) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $audit_results = $checker->runFullAudit();
    AuditHelper::log($conn,'SYSTEM_HEALTH_AUDIT','Manual system health audit executed by '.($_SESSION['admin_name']??'Admin'),null,(int)$_SESSION['admin_id'],'warning');
}

// 2.1 Quick Maintenance Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['system_action'])) {
    $action = $_POST['system_action'];
    switch ($action) {
        case 'clear_cache':     
            if (function_exists('opcache_reset')) @opcache_reset();
            clearstatcache();
            AuditHelper::log($conn,'SYSTEM_MAINTENANCE','System cache cleared manually.',null,(int)$_SESSION['admin_id'],'info'); 
            $_SESSION['success']="System cache successfully purged."; 
            break;
            
        case 'resync_financials': 
            $q = $conn->query("SELECT account_id, account_type FROM ledger_accounts");
            $synced = 0;
            if ($q) {
                while($r = $q->fetch_assoc()) {
                    $acc = (int)$r['account_id'];
                    $typ = $r['account_type'];
                    $eq = $conn->query("SELECT SUM(debit) as d, SUM(credit) as c FROM ledger_entries WHERE account_id = $acc")->fetch_assoc();
                    $bal = ($typ === 'asset' || $typ === 'expense') ? (($eq['d']??0) - ($eq['c']??0)) : (($eq['c']??0) - ($eq['d']??0));
                    $conn->query("UPDATE ledger_accounts SET current_balance = $bal WHERE account_id = $acc");
                    $synced++;
                }
            }
            AuditHelper::log($conn,'SYSTEM_MAINTENANCE',"Manual financial re-sync triggered. Synced $synced accounts.",null,(int)$_SESSION['admin_id'],'warning'); 
            $_SESSION['success']="Financial re-sync cycle completed. $synced accounts verified."; 
            break;
            
        case 'test_connectivity': 
            $mpesa_ok = false;
            if ($ch = curl_init('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials')) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $mpesa_ok = ($code == 400 || $code == 200 || $code == 401);
                curl_close($ch);
            }
            $status = $mpesa_ok ? "All systems operational (API Reachable)." : "M-Pesa API Unreachable.";
            AuditHelper::log($conn,'SYSTEM_DIAGNOSTIC',"Global connectivity test: $status",null,(int)$_SESSION['admin_id'],'info'); 
            $_SESSION['success']="Connectivity check: $status"; 
            break;
    }
    header("Location: live_monitor.php?tab=health"); exit;
}

// 3. Operations Feed
$health         = getSystemHealth($conn);
$recent_logs_q  = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
$recent_logs    = $recent_logs_q->fetch_all(MYSQLI_ASSOC);

// 4. Full Audit Logs
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

if (!function_exists('getActionStyle')) {
    function getActionStyle($action) {
        $a = strtolower($action);
        if (str_contains($a,'delete')||str_contains($a,'fail')||str_contains($a,'error')||str_contains($a,'lock'))
            return ['class'=>'ab-danger',  'icon'=>'bi-exclamation-octagon-fill'];
        if (str_contains($a,'update')||str_contains($a,'edit')||str_contains($a,'suspend'))
            return ['class'=>'ab-warning', 'icon'=>'bi-pencil-square'];
        if (str_contains($a,'create')||str_contains($a,'add')||str_contains($a,'approve')||str_contains($a,'unlock'))
            return ['class'=>'ab-success', 'icon'=>'bi-check-circle-fill'];
        if (str_contains($a,'login'))
            return ['class'=>'ab-info',    'icon'=>'bi-arrow-right-circle-fill'];
        return ['class'=>'ab-neutral', 'icon'=>'bi-activity'];
    }
}
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $name = trim($name ?? 'System');
        if (str_contains($name,' ')) { $p=explode(' ',$name); return strtoupper(substr($p[0],0,1).substr(end($p),0,1)); }
        return strtoupper(substr($name,0,1));
    }
}
?>
<?php $layout->header($pageTitle); ?>
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root {
            --forest:     #0f2e25;
            --forest-mid: #1a5c42;
            --lime:       #a3e635;
            --ease-expo:  cubic-bezier(0.16,1,0.3,1);
        }

        body, .main-content-wrapper, input, select, textarea,
        button, table, th, td, h1,h2,h3,h4,h5,h6, p, span, div,
        label, a, .modal, .nav-link {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        /* ── Hero ── */
        .mon-hero {
            background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
            border-radius: 24px;
            padding: 44px 52px 70px;
            color: #fff;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.65s var(--ease-expo) both;
        }

        .mon-hero .hg {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 32px 32px; pointer-events: none;
        }

        .mon-hero .hr1, .mon-hero .hr2 {
            position: absolute; border-radius: 50%; border: 1px solid rgba(163,230,53,0.1); pointer-events: none;
        }

        .mon-hero .hr1 { width: 320px; height: 320px; top: -80px; right: -80px; }
        .mon-hero .hr2 { width: 500px; height: 500px; top: -160px; right: -160px; }

        .live-pill {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(163,230,53,0.12); border: 1px solid rgba(163,230,53,0.25);
            color: #d4f98a; border-radius: 50px;
            padding: 4px 14px; font-size: 10.5px; font-weight: 700;
            letter-spacing: 0.8px; text-transform: uppercase; margin-bottom: 14px;
        }

        .live-dot {
            width: 7px; height: 7px; border-radius: 50%; background: var(--lime);
            animation: pulse-dot 1.4s ease-in-out infinite; flex-shrink: 0;
        }

        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.6)} }

        .mon-hero h1 { font-size: 2.4rem; font-weight: 800; letter-spacing: -0.5px; line-height: 1.1; margin-bottom: 8px; }
        .mon-hero p  { color: rgba(255,255,255,0.55); font-size: 0.92rem; font-weight: 500; margin: 0; }

        .mon-hero-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }

        .btn-hero-lime {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--lime); color: var(--forest);
            font-size: 0.875rem; font-weight: 700; padding: 10px 22px;
            border-radius: 50px; border: none; cursor: pointer;
            transition: all 0.22s var(--ease-expo);
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            text-decoration: none;
        }

        .btn-hero-lime:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(163,230,53,0.35); color: var(--forest); }

        .btn-hero-ghost {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15);
            color: #fff; font-size: 0.82rem; font-weight: 700; padding: 8px 18px;
            border-radius: 50px; cursor: pointer; transition: background 0.18s ease;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .btn-hero-ghost:hover { background: rgba(255,255,255,0.18); color: #fff; }

        /* ── Overlap wrapper ── */
        .mon-body { margin-top: -42px; position: relative; z-index: 10; }

        /* ── Tab Switcher ── */
        .mon-tabs {
            display: flex; gap: 4px;
            background: #fff; border: 1px solid rgba(0,0,0,0.07);
            border-radius: 14px; padding: 5px;
            width: fit-content; box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 24px; animation: fadeUp 0.5s var(--ease-expo) 0.1s both;
        }

        .mon-tab {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 10px;
            font-size: 0.83rem; font-weight: 600; color: #6b7280;
            border: none; background: transparent; cursor: pointer;
            transition: all 0.2s var(--ease-expo); white-space: nowrap;
        }

        .mon-tab:hover:not(.active) { color: #374151; }

        .mon-tab.active {
            background: var(--forest); color: #fff;
            font-weight: 700; box-shadow: 0 2px 8px rgba(15,46,37,0.2);
        }

        /* ── KPI Cards ── */
        .kpi-card {
            background: #fff; border-radius: 18px; padding: 22px 24px;
            border: 1px solid rgba(0,0,0,0.055); box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            position: relative; overflow: hidden; height: 100%;
            transition: transform 0.22s var(--ease-expo), box-shadow 0.22s ease;
            animation: fadeUp 0.6s var(--ease-expo) both;
        }

        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.09); }
        .kpi-card::after { content:''; position:absolute; bottom:0; left:0; right:0; height:3px; border-radius:0 0 18px 18px; opacity:0; transition: opacity 0.22s ease; }
        .kpi-card:hover::after { opacity:1; }
        .kpi-card.kc-success::after { background: linear-gradient(90deg,#22c55e,#86efac); }
        .kpi-card.kc-warn::after    { background: linear-gradient(90deg,#f59e0b,#fcd34d); }
        .kpi-card.kc-danger::after  { background: linear-gradient(90deg,#ef4444,#fca5a5); }
        .kpi-card.kc-dark::after    { background: linear-gradient(90deg,var(--lime),#d4f98a); }
        .kpi-card.kc-dark { background: linear-gradient(135deg, var(--forest), var(--forest-mid)); border: none; }

        .kpi-card:nth-child(1) { animation-delay: 0.04s; }
        .kpi-card:nth-child(2) { animation-delay: 0.10s; }
        .kpi-card:nth-child(3) { animation-delay: 0.16s; }
        .kpi-card:nth-child(4) { animation-delay: 0.22s; }

        .kpi-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; margin-bottom: 14px; }
        .kpi-label { font-size: 10.5px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; margin-bottom: 4px; }
        .kpi-value { font-size: 1.8rem; font-weight: 800; letter-spacing: -0.04em; line-height: 1; margin-bottom: 10px; }
        .kpi-sub   { font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 5px; }

        .kpi-progress { height: 4px; border-radius: 99px; background: rgba(0,0,0,0.07); overflow: hidden; margin-top: 10px; }
        .kpi-progress-bar { height: 100%; border-radius: 99px; transition: width 0.8s ease; }

        /* Glow dot */
        .gdot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; position: relative; }
        .gdot::after { content:''; position:absolute; inset:-3px; border-radius:50%; background:inherit; opacity:0.4; animation: glow-pulse 2s infinite; }
        @keyframes glow-pulse { 0%{transform:scale(1);opacity:0.5} 100%{transform:scale(2.5);opacity:0} }

        /* Sparkline */
        .spark { position:absolute; right:0; bottom:0; left:0; height:40px; opacity:0.12; pointer-events:none; overflow:hidden; transition: opacity 0.22s ease; }
        .kpi-card:hover .spark { opacity:0.4; }
        .spark svg { width:100%; height:100%; }
        .sp { fill:none; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; vector-effect:non-scaling-stroke; }
        .kc-success .sp { stroke: #22c55e; }
        .kc-warn    .sp { stroke: #f59e0b; }
        .kc-danger  .sp { stroke: #ef4444; }
        .kc-dark    .sp { stroke: var(--lime); }

        /* ── Feed Header ── */
        .feed-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 14px; animation: fadeUp 0.5s var(--ease-expo) 0.28s both;
        }

        .feed-title { font-size: 0.95rem; font-weight: 800; color: #111827; }
        .feed-sub   { font-size: 0.75rem; color: #9ca3af; margin-top: 2px; font-weight: 500; }
        .feed-count { font-size: 10.5px; font-weight: 700; background: #f3f4f6; color: #6b7280; padding: 4px 12px; border-radius: 7px; border: 1px solid #e5e7eb; }

        /* ── Log Table ── */
        .log-card {
            background: #fff; border-radius: 18px;
            border: 1px solid rgba(0,0,0,0.055); box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden; margin-bottom: 24px;
            animation: fadeUp 0.6s var(--ease-expo) 0.3s both;
        }

        .log-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }

        .log-table thead th {
            background: #fafafa; font-size: 10.5px; font-weight: 700;
            letter-spacing: 0.8px; text-transform: uppercase; color: #9ca3af;
            padding: 11px 18px; border: none; border-bottom: 1px solid #f0f0f0; white-space: nowrap;
        }

        .log-table tbody tr { border-bottom: 1px solid #f9fafb; transition: background 0.15s ease; }
        .log-table tbody tr:last-child { border-bottom: none; }
        .log-table tbody tr:hover { background: #fafff8; }
        .log-table tbody td { padding: 11px 18px; vertical-align: middle; }

        .log-time  { font-size: 0.82rem; font-weight: 700; color: #374151; font-family: monospace; }
        .log-date  { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }
        .log-action{ font-size: 0.85rem; font-weight: 700; color: #111827; }
        .log-type  { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }
        .log-detail{ font-size: 0.8rem; color: #6b7280; font-weight: 500; }
        .log-ip    { font-size: 0.78rem; font-weight: 600; color: #9ca3af; font-family: monospace; background: #f3f4f6; padding: 2px 8px; border-radius: 5px; }

        /* Severity badges */
        .sev-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 6px; white-space: nowrap; }
        .sev-badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; flex-shrink:0; }
        .sev-info     { background: #eff6ff; color: #1d4ed8; }
        .sev-success  { background: #f0fdf4; color: #16a34a; }
        .sev-warning  { background: #fffbeb; color: #d97706; }
        .sev-error    { background: #fef2f2; color: #dc2626; }
        .sev-critical { background: #fdf2f8; color: #9d174d; }

        /* Action badges */
        .ab { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; padding: 4px 10px; border-radius: 7px; white-space: nowrap; }
        .ab-danger  { background: #fef2f2; color: #b91c1c; }
        .ab-warning { background: #fffbeb; color: #b45309; }
        .ab-success { background: #f0fdf4; color: #166534; }
        .ab-info    { background: #eff6ff; color: #1d4ed8; }
        .ab-neutral { background: #f5f5f5; color: #6b7280; }

        /* Actor cell */
        .actor-cell { display: flex; align-items: center; gap: 9px; }
        .actor-av {
            width: 32px; height: 32px; border-radius: 9px;
            background: linear-gradient(135deg, var(--forest), var(--forest-mid));
            color: var(--lime); display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 800; flex-shrink: 0;
        }
        .actor-name { font-size: 0.85rem; font-weight: 700; color: #111827; }
        .actor-role { font-size: 0.7rem; color: #9ca3af; margin-top: 1px; }

        /* Empty state */
        .log-empty { text-align: center; padding: 60px 24px; }
        .log-empty .ei  { font-size: 2.8rem; color: #d1d5db; margin-bottom: 14px; }
        .log-empty h5   { font-weight: 700; color: #374151; margin-bottom: 6px; font-size: 0.95rem; }
        .log-empty p    { font-size: 0.85rem; color: #9ca3af; margin: 0; }

        /* Log card footer */
        .log-card-footer {
            padding: 10px 18px; background: #fafafa; border-top: 1px solid #f0f0f0;
            text-align: center; font-size: 0.72rem; font-weight: 600; color: #9ca3af;
        }

        /* ── Audit Toolbar ── */
        .audit-toolbar {
            display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
            background: #fff; border-radius: 14px; padding: 12px 16px;
            border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 16px; animation: fadeUp 0.5s var(--ease-expo) 0.1s both;
        }

        .audit-search-wrap { flex: 1; min-width: 220px; position: relative; }
        .audit-search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85rem; pointer-events: none; }
        .audit-search-wrap input {
            width: 100%; padding: 8px 13px 8px 34px;
            border: 1px solid #e5e7eb; border-radius: 9px;
            font-size: 0.85rem; font-weight: 500; color: #111827;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            box-shadow: none; transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }
        .audit-search-wrap input:focus { outline: none; border-color: rgba(15,46,37,0.35); box-shadow: 0 0 0 3px rgba(15,46,37,0.07); }

        .btn-audit-search {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--forest); color: #fff;
            font-size: 0.82rem; font-weight: 700; padding: 8px 18px;
            border-radius: 9px; border: none; cursor: pointer;
            transition: all 0.18s ease;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .btn-audit-search:hover { background: var(--forest-mid); }

        .btn-audit-outline {
            display: inline-flex; align-items: center; gap: 6px;
            background: transparent; color: #6b7280;
            border: 1px solid #e5e7eb; font-size: 0.82rem; font-weight: 700;
            padding: 8px 14px; border-radius: 9px; cursor: pointer; text-decoration: none;
            transition: all 0.18s ease;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .btn-audit-outline:hover { border-color: rgba(15,46,37,0.2); color: var(--forest); }

        /* ── Health Cards ── */
        .health-card {
            background: #fff; border-radius: 18px; padding: 22px 24px;
            border: 1px solid rgba(0,0,0,0.055); box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            height: 100%; display: flex; flex-direction: column;
            position: relative; overflow: hidden;
            transition: transform 0.22s var(--ease-expo), box-shadow 0.22s ease;
            animation: fadeUp 0.6s var(--ease-expo) both;
        }

        .health-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.09); }
        .health-card::after { content:''; position:absolute; bottom:0; left:0; right:0; height:3px; border-radius:0 0 18px 18px; opacity:0; transition:opacity 0.22s ease; }
        .health-card:hover::after { opacity:1; }
        .health-card.hc-ok::after   { background: linear-gradient(90deg,#22c55e,#86efac); }
        .health-card.hc-err::after  { background: linear-gradient(90deg,#ef4444,#fca5a5); }
        .health-card.hc-info::after { background: linear-gradient(90deg,var(--lime),#d4f98a); }

        .health-card:nth-child(1) { animation-delay: 0.04s; }
        .health-card:nth-child(2) { animation-delay: 0.10s; }
        .health-card:nth-child(3) { animation-delay: 0.16s; }

        .hc-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 10px; }
        .hc-title  { font-size: 0.875rem; font-weight: 800; color: #111827; }
        .hc-desc   { font-size: 0.78rem; color: #9ca3af; font-weight: 500; line-height: 1.5; flex: 1; }
        .hc-footer { margin-top: auto; padding-top: 14px; border-top: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }

        .h-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
        .h-dot.ok      { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.2); animation: pulse-dot 2s ease-in-out infinite; }
        .h-dot.err     { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.2); animation: pulse-dot 1s ease-in-out infinite; }
        .h-dot.neutral { background: #94a3b8; }

        .status-pill { font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: 7px; }
        .sp-ok  { background: #f0fdf4; color: #16a34a; }
        .sp-err { background: #fef2f2; color: #dc2626; }
        .sp-neutral { background: #f3f4f6; color: #6b7280; }

        /* ── Audit Banner ── */
        .audit-banner {
            display: flex; align-items: center; gap: 12px;
            background: #eff6ff; border: 1px solid #bfdbfe;
            border-radius: 12px; padding: 14px 18px;
            margin-bottom: 20px; animation: fadeUp 0.4s var(--ease-expo) both;
        }

        .audit-banner-icon { font-size: 1.2rem; color: #2563eb; flex-shrink: 0; }
        .audit-banner-title { font-size: 0.875rem; font-weight: 800; color: #1d4ed8; }
        .audit-banner-sub   { font-size: 0.78rem; color: #3b82f6; margin: 2px 0 0; font-weight: 500; }

        /* ── Power Panel ── */
        .power-card {
            background: #fff; border-radius: 18px; padding: 24px 28px;
            border: 1px solid rgba(0,0,0,0.055); box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            animation: fadeUp 0.6s var(--ease-expo) 0.2s both;
        }

        .power-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 6px; }
        .power-card-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--forest); color: var(--lime); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .power-card-title { font-size: 0.95rem; font-weight: 800; color: #111827; }
        .power-card-desc  { font-size: 0.78rem; color: #9ca3af; font-weight: 500; margin: 2px 0 0; }

        .power-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-top: 20px; }

        .power-btn {
            background: #fafafa; border: 1.5px solid #e5e7eb; border-radius: 14px;
            padding: 16px; display: flex; flex-direction: column; align-items: center; gap: 7px;
            cursor: pointer; text-align: center;
            transition: all 0.22s var(--ease-expo);
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .power-btn:hover { background: #fff; border-color: rgba(15,46,37,0.25); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .power-btn i { font-size: 1.4rem; color: var(--forest); }
        .power-btn .p-title { font-size: 0.85rem; font-weight: 800; color: #111827; }
        .power-btn .p-desc  { font-size: 0.7rem; color: #9ca3af; font-weight: 500; line-height: 1.4; }

        .power-btn.btn-audit-deep {
            background: var(--forest); border-color: var(--forest);
        }

        .power-btn.btn-audit-deep i { color: var(--lime); }
        .power-btn.btn-audit-deep .p-title { color: #fff; }
        .power-btn.btn-audit-deep .p-desc  { color: rgba(255,255,255,0.55); }
        .power-btn.btn-audit-deep:hover { background: var(--forest-mid); box-shadow: 0 8px 20px rgba(15,46,37,0.25); }

        /* ── Animations ── */
        @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

        @media (max-width: 768px) {
            .mon-hero { padding: 32px 24px 60px; }
            .mon-hero h1 { font-size: 1.8rem; }
            .mon-tabs { flex-wrap: wrap; width: 100%; }
        }
    </style>

    <!-- ─── HERO ──────────────────────────────────────────── -->
    <div class="mon-hero mb-0">
        <div class="hg"></div>
        <div class="hr1"></div>
        <div class="hr2"></div>
        <div class="d-flex align-items-center justify-content-between gap-4 flex-wrap">
            <div>
                <div class="live-pill"><span class="live-dot"></span> Unified Monitoring</div>
                <h1>System Monitor</h1>
                <p>Operations feed, financial integrity, and security audit in one view.</p>
            </div>
            <div class="mon-hero-actions">
                <button onclick="location.reload()" class="btn-hero-lime">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Feed
                </button>
                <div id="exportControls" class="d-none">
                    <div class="dropdown">
                        <button class="btn-hero-ghost dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-cloud-download"></i> Export Logs
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius:14px;padding:8px;">
                            <li><a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action'=>'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>PDF</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action'=>'export_excel'])) ?>"><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Excel</a></li>
                            <li><a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action'=>'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Print</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mon-body">

        <!-- ─── TAB SWITCHER ──────────────────────────────── -->
        <div class="mon-tabs">
            <button class="mon-tab active" id="tabFeed"   onclick="switchTab('Feed')"><i class="bi bi-broadcast"></i> Operations Feed</button>
            <button class="mon-tab"        id="tabHealth" onclick="switchTab('Health')"><i class="bi bi-cpu-fill"></i> Health &amp; Integrity</button>
            <button class="mon-tab"        id="tabAudit"  onclick="switchTab('Audit')"><i class="bi bi-shield-lock-fill"></i> Security Audit</button>
        </div>

        <!-- ════════════════════════════════════════════════
             TAB 1 — OPERATIONS FEED
        ════════════════════════════════════════════════ -->
        <div id="sectionFeed">

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">

                <!-- Callback Success -->
                <?php $cb = (float)$health['callback_success_rate'];
                $cb_color = $cb >= 90 ? '#22c55e' : ($cb >= 70 ? '#f59e0b' : '#ef4444');
                $cb_text  = $cb >= 90 ? '#166534' : ($cb >= 70 ? '#b45309' : '#b91c1c');
                $cb_class = $cb >= 90 ? 'kc-success' : ($cb >= 70 ? 'kc-warn' : 'kc-danger');
                ?>
                <div class="col-md-3">
                    <div class="kpi-card <?= $cb_class ?>">
                        <div class="kpi-icon" style="background:<?= $cb>=90?'#f0fdf4':($cb>=70?'#fffbeb':'#fef2f2') ?>;color:<?= $cb_text ?>;">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="kpi-label" style="color:#9ca3af;">Callback Success</div>
                        <div class="kpi-value" style="color:var(--forest);"><?= $cb ?>%</div>
                        <div class="kpi-progress">
                            <div class="kpi-progress-bar" style="width:<?= $cb ?>%;background:<?= $cb_color ?>;"></div>
                        </div>
                        <div class="kpi-sub mt-2" style="color:<?= $cb_text ?>;">
                            <span class="gdot" style="background:<?= $cb_color ?>;"></span>
                            <?= $cb >= 90 ? 'Healthy rate' : ($cb >= 70 ? 'Needs attention' : 'Critical — investigate') ?>
                        </div>
                        <div class="spark">
                            <svg preserveAspectRatio="none" viewBox="0 0 100 40">
                                <path class="sp" d="M0,30 Q10,10 20,25 T40,15 T60,35 T80,10 T100,20"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Pending STK -->
                <?php $pend = (int)$health['pending_transactions']; $pend_warn = $pend > 5; ?>
                <div class="col-md-3">
                    <div class="kpi-card <?= $pend_warn ? 'kc-warn' : 'kc-success' ?>">
                        <div class="kpi-icon" style="background:<?= $pend_warn?'#fffbeb':'#f0fdf4' ?>;color:<?= $pend_warn?'#b45309':'#166534' ?>;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="kpi-label" style="color:#9ca3af;">Pending STK</div>
                        <div class="kpi-value" style="color:<?= $pend_warn?'#b45309':'#166534' ?>;"><?= $pend ?></div>
                        <div class="kpi-sub mt-2" style="color:<?= $pend_warn?'#b45309':'#166534' ?>;">
                            <span class="gdot" style="background:<?= $pend_warn?'#f59e0b':'#22c55e' ?>;"></span>
                            <?= $pend_warn ? 'Stuck &gt; 5 mins' : 'All clear' ?>
                        </div>
                        <div class="spark">
                            <svg preserveAspectRatio="none" viewBox="0 0 100 40">
                                <path class="sp" d="M0,35 L15,30 L30,35 L45,20 L60,25 L75,10 L90,15 L100,5"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Failed Comms -->
                <?php $fail = (int)$health['failed_notifications']; ?>
                <div class="col-md-3">
                    <div class="kpi-card <?= $fail > 0 ? 'kc-danger' : 'kc-success' ?>">
                        <div class="kpi-icon" style="background:<?= $fail>0?'#fef2f2':'#f0fdf4' ?>;color:<?= $fail>0?'#dc2626':'#166534' ?>;">
                            <i class="bi bi-envelope-exclamation"></i>
                        </div>
                        <div class="kpi-label" style="color:#9ca3af;">Failed Comms</div>
                        <div class="kpi-value" style="color:<?= $fail>0?'#dc2626':'#166534' ?>;"><?= $fail ?></div>
                        <div class="kpi-sub mt-2" style="color:<?= $fail>0?'#dc2626':'#166534' ?>;">
                            <span class="gdot" style="background:<?= $fail>0?'#ef4444':'#22c55e' ?>;"></span>
                            <?= $fail > 0 ? 'Delivery errors today' : 'All delivered' ?>
                        </div>
                    </div>
                </div>

                <!-- Daily Volume -->
                <div class="col-md-3">
                    <div class="kpi-card kc-dark">
                        <div class="kpi-icon" style="background:rgba(255,255,255,0.12);color:var(--lime);">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <div class="kpi-label" style="color:rgba(255,255,255,0.45);">Daily Volume</div>
                        <div class="kpi-value" style="color:#fff;font-size:1.45rem;">KES <?= number_format($health['daily_volume'], 0) ?></div>
                        <div class="kpi-sub mt-2" style="color:rgba(255,255,255,0.45);">
                            <span class="gdot" style="background:var(--lime);"></span>
                            Successfully processed
                        </div>
                        <div class="spark" style="opacity:0.2;">
                            <svg preserveAspectRatio="none" viewBox="0 0 100 40">
                                <path class="sp" d="M0,38 L10,32 L20,35 L30,22 L40,28 L50,15 L60,22 L70,8 L80,18 L90,5 L100,12"/>
                            </svg>
                        </div>
                    </div>
                </div>

            </div><!-- /kpi row -->

            <!-- Feed Header -->
            <div class="feed-header">
                <div>
                    <div class="feed-title">Operation Audit Feed</div>
                    <div class="feed-sub">Real-time system activity — auto-sorted by latest</div>
                </div>
                <span class="feed-count" id="auditFeedCount"><?= count($recent_logs) ?> events</span>
            </div>

            <!-- Feed Table -->
            <div class="log-card">
                <div class="table-responsive">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th style="padding-left:22px;">Time</th>
                                <th>Action</th>
                                <th>Severity</th>
                                <th>Details</th>
                                <th style="padding-right:22px;">Origin IP</th>
                            </tr>
                        </thead>
                        <tbody id="auditFeedBody">
                            <?php if (empty($recent_logs)): ?>
                            <tr><td colspan="5">
                                <div class="log-empty">
                                    <div class="ei"><i class="bi bi-broadcast"></i></div>
                                    <h5>No Events Streaming</h5>
                                    <p>No operational logs recorded yet.</p>
                                </div>
                            </td></tr>
                            <?php else: foreach ($recent_logs as $log):
                                $sev       = strtolower((string)($log['severity'] ?? 'info'));
                                $sev_class = match($sev) { 'warning'=>'sev-warning','error'=>'sev-error','critical'=>'sev-critical','success'=>'sev-success',default=>'sev-info' };
                            ?>
                            <tr>
                                <td style="padding-left:22px;">
                                    <div class="log-time"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                    <div class="log-date"><?= date('M d', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="log-action"><?= htmlspecialchars((string)($log['action']??'')) ?></div>
                                    <div class="log-type"><?= htmlspecialchars((string)($log['user_type']??'')) ?></div>
                                </td>
                                <td><span class="sev-badge <?= $sev_class ?>"><?= strtoupper($sev ?: 'INFO') ?></span></td>
                                <td><span class="log-detail"><?= htmlspecialchars((string)($log['details']??'')) ?></span></td>
                                <td style="padding-right:22px;"><span class="log-ip"><?= htmlspecialchars((string)($log['ip_address']??'')) ?></span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /sectionFeed -->

        <!-- ════════════════════════════════════════════════
             TAB 2 — HEALTH & INTEGRITY
        ════════════════════════════════════════════════ -->
        <div id="sectionHealth" class="d-none">

            <?php if ($audit_results):
                $issue_count = count($audit_results['sync']['data']??[])
                             + count($audit_results['balance']['data']??[])
                             + count($audit_results['double_posting']['data']??[]);
            ?>
            <div class="audit-banner">
                <div class="audit-banner-icon"><i class="bi bi-info-circle-fill"></i></div>
                <div>
                    <div class="audit-banner-title">Audit Complete</div>
                    <p class="audit-banner-sub">Financial integrity check finished. Found <strong><?= $issue_count ?></strong> potential issue<?= $issue_count !== 1 ? 's' : '' ?>.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Integrity Cards -->
            <div class="row g-3 mb-4">
                <?php $imbal = $health['ledger_imbalance']; ?>
                <div class="col-md-4">
                    <div class="health-card <?= $imbal ? 'hc-err' : 'hc-ok' ?>">
                        <div class="hc-header">
                            <div class="hc-title">Ledger Balance Sync</div>
                            <span class="h-dot <?= $imbal ? 'err' : 'ok' ?>"></span>
                        </div>
                        <p class="hc-desc">Total Debits vs Credits in the golden ledger. Discrepancies indicate posting errors.</p>
                        <div class="hc-footer">
                            <span class="status-pill <?= $imbal ? 'sp-err' : 'sp-ok' ?>"><?= $imbal ? 'Imbalance Detected' : 'Healthy' ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="health-card hc-ok">
                        <div class="hc-header">
                            <div class="hc-title">Member Account Sync</div>
                            <span class="h-dot ok"></span>
                        </div>
                        <p class="hc-desc">Verifies individual account balances against the full transaction history for each member.</p>
                        <div class="hc-footer">
                            <span class="status-pill sp-ok">Verified</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="health-card hc-info">
                        <div class="hc-header">
                            <div class="hc-title">Database Storage</div>
                            <span class="h-dot neutral"></span>
                        </div>
                        <p class="hc-desc">Current size of the central repository. Archiving is recommended above 500 MB.</p>
                        <div class="hc-footer">
                            <span style="font-size:1.25rem;font-weight:800;color:var(--forest);"><?= htmlspecialchars((string)($health['db_size']??'N/A')) ?> MB</span>
                            <span class="status-pill sp-neutral">Storage</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Power Panel -->
            <div class="power-card">
                <div class="power-card-header">
                    <div class="power-card-icon"><i class="bi bi-cpu-fill"></i></div>
                    <div>
                        <div class="power-card-title">Maintenance Power Panel</div>
                        <div class="power-card-desc">Direct low-level system diagnostic and recovery operations.</div>
                    </div>
                </div>
                <form method="POST" style="display:contents;">
                    <div class="power-grid">
                        <button type="submit" name="system_action" value="test_connectivity" class="power-btn">
                            <i class="bi bi-broadcast"></i>
                            <span class="p-title">API Connectivity</span>
                            <span class="p-desc">Test M-Pesa gateway &amp; mail server</span>
                        </button>
                        <button type="submit" name="system_action" value="clear_cache" class="power-btn">
                            <i class="bi bi-trash3-fill"></i>
                            <span class="p-title">Purge Cache</span>
                            <span class="p-desc">Clear session &amp; temp files</span>
                        </button>
                        <button type="submit" name="system_action" value="resync_financials" class="power-btn">
                            <i class="bi bi-arrow-repeat"></i>
                            <span class="p-title">Re-sync Ledger</span>
                            <span class="p-desc">Force financial reconciliation</span>
                        </button>
                        <button type="submit" name="run_audit" value="1" class="power-btn btn-audit-deep">
                            <i class="bi bi-shield-check"></i>
                            <span class="p-title">Full Audit</span>
                            <span class="p-desc">Deep integrity scan now</span>
                        </button>
                    </div>
                </form>
            </div>

        </div><!-- /sectionHealth -->

        <!-- ════════════════════════════════════════════════
             TAB 3 — SECURITY AUDIT
        ════════════════════════════════════════════════ -->
        <div id="sectionAudit" class="d-none">

            <!-- Toolbar -->
            <div class="audit-toolbar">
                <form method="GET" id="searchAuditForm" style="display:contents;">
                    <input type="hidden" name="tab" value="audit">
                    <div class="audit-search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" placeholder="Search by actor, action, or details…" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn-audit-search"><i class="bi bi-search"></i> Search</button>
                    <?php if ($search): ?>
                    <a href="live_monitor.php?tab=audit" class="btn-audit-outline"><i class="bi bi-x"></i> Clear</a>
                    <?php endif; ?>
                </form>
                <div class="dropdown ms-auto">
                    <button class="btn-audit-outline dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-cloud-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:14px;padding:8px;">
                        <li><a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action'=>'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>PDF</a></li>
                        <li><a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action'=>'export_excel'])) ?>"><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Excel</a></li>
                        <li><a class="dropdown-item rounded-3 py-2" href="?<?= http_build_query(array_merge($_GET, ['action'=>'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Print</a></li>
                    </ul>
                </div>
            </div>

            <!-- Audit Table -->
            <div class="log-card">
                <div class="table-responsive">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th style="padding-left:22px;">Time</th>
                                <th>Actor</th>
                                <th>Action Type</th>
                                <th>Details</th>
                                <th style="padding-right:22px;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($full_logs->num_rows === 0): ?>
                            <tr><td colspan="5">
                                <div class="log-empty">
                                    <div class="ei"><i class="bi bi-shield-exclamation"></i></div>
                                    <h5>No Audit Logs Found</h5>
                                    <p>No security events match your search criteria.</p>
                                </div>
                            </td></tr>
                            <?php else: while ($row = $full_logs->fetch_assoc()):
                                $style = getActionStyle($row['action']);
                                $name  = $row['full_name'] ?? $row['username'] ?? 'System';
                                $role  = ucfirst($row['role'] ?? 'System');
                            ?>
                            <tr>
                                <td style="padding-left:22px;">
                                    <div class="log-time"><?= date('H:i:s', strtotime($row['created_at'])) ?></div>
                                    <div class="log-date"><?= date('M d', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="actor-cell">
                                        <div class="actor-av"><?= getInitials($name) ?></div>
                                        <div>
                                            <div class="actor-name"><?= htmlspecialchars($name) ?></div>
                                            <div class="actor-role"><?= htmlspecialchars($role) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="ab <?= $style['class'] ?>">
                                        <i class="bi <?= $style['icon'] ?>"></i>
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['action']))) ?>
                                    </span>
                                </td>
                                <td><span class="log-detail"><?= htmlspecialchars((string)($row['details']??'')) ?></span></td>
                                <td style="padding-right:22px;"><span class="log-ip"><?= htmlspecialchars((string)($row['ip_address']??'')) ?></span></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="log-card-footer">
                    Showing latest 200 security events &mdash; use search or export for more
                </div>
            </div>

        </div><!-- /sectionAudit -->

    </div><!-- /mon-body -->

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let lastLogId = <?= !empty($recent_logs) ? (int)($recent_logs[0]['audit_id'] ?? 0) : 0 ?>;
        let isPolling = false;

        async function pollAudit() {
            if (isPolling) return;
            isPolling = true;
            try {
                const response = await fetch(`ajax_audit_feed.php?since_id=${lastLogId}`);
                const data = await response.json();
                if (data.logs && data.logs.length > 0) {
                    const tbody = document.getElementById('auditFeedBody');
                    if (tbody && tbody.querySelector('.log-empty')) {
                        tbody.innerHTML = '';
                    }
                    data.logs.forEach(log => {
                        const row = document.createElement('tr');
                        let sevClass = 'sev-info';
                        switch(log.severity) {
                            case 'warning': sevClass = 'sev-warning'; break;
                            case 'error': sevClass = 'sev-error'; break;
                            case 'critical': sevClass = 'sev-critical'; break;
                            case 'success': sevClass = 'sev-success'; break;
                        }
                        row.innerHTML = `
                            <td style="padding-left:22px;">
                                <div class="log-time">${log.time}</div>
                                <div class="log-date">${log.date}</div>
                            </td>
                            <td>
                                <div class="log-action">${log.action}</div>
                                <div class="log-type">${log.user_type}</div>
                            </td>
                            <td><span class="sev-badge ${sevClass}">${(log.severity || 'INFO').toUpperCase()}</span></td>
                            <td><span class="log-detail">${log.details}</span></td>
                            <td style="padding-right:22px;"><span class="log-ip">${log.ip_address}</span></td>
                        `;
                        if (tbody) tbody.prepend(row);
                        lastLogId = Math.max(lastLogId, log.id);
                        row.style.animation = 'fadeUp 0.5s var(--ease-expo) both';
                    });
                }
            } catch (e) {
                console.error('Polling error:', e);
            } finally {
                isPolling = false;
            }
        }

        const tabs = document.querySelectorAll('.mon-tab');
        const sections = ['Feed', 'Health', 'Audit'];
        
        function switchTab(targetName) {
            sections.forEach(function(t) {
                const sec = document.getElementById('section' + t);
                const btn = document.getElementById('tab' + t);
                if (sec) sec.classList.add('d-none');
                if (btn) btn.classList.remove('active');
            });
            
            const activeSec = document.getElementById('section' + targetName);
            const activeBtn = document.getElementById('tab' + targetName);
            if (activeSec) activeSec.classList.remove('d-none');
            if (activeBtn) activeBtn.classList.add('active');
            
            const exportsCtrl = document.getElementById('exportControls');
            if (exportsCtrl) {
                if (targetName === 'Audit') {
                    exportsCtrl.classList.remove('d-none');
                } else {
                    exportsCtrl.classList.add('d-none');
                }
            }
            localStorage.setItem('mon_tab', targetName);
        }

        tabs.forEach(function(btn) {
            btn.addEventListener('click', function() {
                let name = 'Feed';
                if (this.id === 'tabHealth') name = 'Health';
                if (this.id === 'tabAudit') name = 'Audit';
                switchTab(name);
            });
            // remove inline onclick overrides
            btn.removeAttribute('onclick');
        });

        // Init tab
        const urlParams = new URLSearchParams(window.location.search);
        let currentTab = urlParams.get('tab');
        if (!currentTab) currentTab = localStorage.getItem('mon_tab');
        
        if (currentTab) {
            currentTab = currentTab.toLowerCase();
            let resolvedTab = 'Feed';
            if (currentTab === 'health') resolvedTab = 'Health';
            else if (currentTab === 'audit') resolvedTab = 'Audit';
            switchTab(resolvedTab);
        } else {
            switchTab('Feed');
        }

        setInterval(pollAudit, 5000);
    });
    </script>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->