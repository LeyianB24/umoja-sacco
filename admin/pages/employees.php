<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/HRService.php';
require_once __DIR__ . '/../../inc/SystemUserService.php';
require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$layout     = LayoutManager::create('admin');
$db         = $conn;
$hrService          = new HRService($db);
$systemUserService  = new SystemUserService($db);

$current_view = $_GET['view'] ?? 'hr';
$admin_id     = $_SESSION['admin_id'];
$admin_role   = $_SESSION['role_id'] ?? 0;

if (!function_exists('getInitials')) {
    function getInitials($name) {
        $words = explode(" ", $name); $a = "";
        foreach ($words as $w) if (!empty($w)) $a .= mb_substr($w, 0, 1);
        return strtoupper(substr($a, 0, 2));
    }
}
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        if (!$datetime) return 'Never';
        $diff = time() - strtotime($datetime);
        if ($diff < 60) return 'Just now';
        foreach ([31536000=>'yr',2592000=>'mth',604800=>'wk',86400=>'day',3600=>'hr',60=>'min'] as $s=>$t) {
            if (($d = $diff/$s) >= 1) { $r=round($d); return "$r $t".($r>1?'s':'').' ago'; }
        }
        return 'Just now';
    }
}

// ── POST HANDLERS (unchanged) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    if (isset($_POST['action']) && $_POST['action'] === 'add_employee') {
        $employeeData = ['full_name'=>trim($_POST['full_name']),'national_id'=>trim($_POST['national_id']),'phone'=>trim($_POST['phone']),'job_title'=>trim($_POST['job_title']),'grade_id'=>(int)$_POST['grade_id'],'personal_email'=>trim($_POST['personal_email']??''),'salary'=>(float)$_POST['salary'],'kra_pin'=>strtoupper(trim($_POST['kra_pin']??'')),'nssf_no'=>trim($_POST['nssf_no']??''),'sha_no'=>trim($_POST['sha_no']??''),'bank_name'=>trim($_POST['bank_name']??''),'bank_account'=>trim($_POST['bank_account']??''),'hire_date'=>$_POST['hire_date']];
        $result = $hrService->createEmployee($employeeData);
        if ($result['success']) {
            $roleId = $systemUserService->getRoleIdForTitle($employeeData['job_title']);
            $userData = ['employee_no'=>$result['employee_no'],'company_email'=>$result['company_email'],'full_name'=>$employeeData['full_name']];
            $userResult = $systemUserService->createSystemUser($userData, $roleId);
            if ($userResult['success']) { $stmt=$db->prepare("UPDATE employees SET admin_id=? WHERE employee_id=?"); $stmt->bind_param("ii",$userResult['admin_id'],$result['employee_id']); $stmt->execute(); }
            flash_set("Employee onboarded successfully. ID: {$result['employee_no']}", 'success');
        } else { flash_set("Error: ".$result['error'], 'error'); }
        header("Location: employees.php?view=hr"); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_employee') {
        $result = $hrService->updateEmployee((int)$_POST['employee_id'],['full_name'=>trim($_POST['full_name']),'phone'=>trim($_POST['phone']),'job_title'=>trim($_POST['job_title']),'salary'=>(float)$_POST['salary'],'status'=>$_POST['status'],'kra_pin'=>strtoupper(trim($_POST['kra_pin']??'')),'nssf_no'=>trim($_POST['nssf_no']??''),'sha_no'=>trim($_POST['sha_no']??'')]);
        flash_set($result['success'] ? "Employee updated successfully." : "Error: ".$result['error'], $result['success']?'success':'error');
        header("Location: employees.php?view=hr"); exit;
    }
}

// ── FETCH DATA ───────────────────────────────────────────
$roles_res = $db->query("SELECT * FROM roles ORDER BY name ASC");
$defined_roles = [];
while ($r = $roles_res->fetch_assoc()) $defined_roles[$r['id']] = ['label'=>ucwords($r['name']),'name'=>strtolower($r['name'])];

$grades = [];
$g_q = $db->query("SELECT * FROM salary_grades ORDER BY basic_salary DESC");
while ($g = $g_q->fetch_assoc()) $grades[] = $g;

$data_rows = [];
if ($current_view === 'hr') {
    $filter_status = $_GET['status'] ?? 'active'; $search = trim($_GET['q'] ?? '');
    $where = [];
    if ($filter_status !== 'all') $where[] = "e.status='$filter_status'";
    if ($search) $where[] = "(e.full_name LIKE '%$search%' OR e.national_id LIKE '%$search%')";
    $where_sql = $where ? "WHERE ".implode(" AND ",$where) : "";
    $res = $db->query("SELECT e.*,r.name as admin_role,sg.grade_name FROM employees e LEFT JOIN admins a ON e.admin_id=a.admin_id LEFT JOIN roles r ON a.role_id=r.id LEFT JOIN salary_grades sg ON e.grade_id=sg.id $where_sql ORDER BY e.full_name ASC");
    while ($row = $res->fetch_assoc()) $data_rows[] = $row;
} elseif ($current_view === 'leave') {
    $search = trim($_GET['q'] ?? ''); $where_sql = $search ? "WHERE e.full_name LIKE '%$search%' OR e.employee_no LIKE '%$search%'" : "";
    $res = $db->query("SELECT e.*,sg.grade_name FROM employees e LEFT JOIN salary_grades sg ON e.grade_id=sg.id $where_sql ORDER BY e.full_name ASC");
    while ($row = $res->fetch_assoc()) $data_rows[] = $row;
}

// KPIs
if ($current_view === 'hr') {
    $kpi1_lbl="Total Staff";     $kpi1_val=$db->query("SELECT COUNT(*) FROM employees")->fetch_row()[0];
    $kpi2_lbl="Monthly Payroll"; $kpi2_val="KES ".number_format($db->query("SELECT SUM(salary) FROM employees WHERE status='active'")->fetch_row()[0]??0);
    $kpi3_lbl="Active Drivers";  $kpi3_val=$db->query("SELECT COUNT(*) FROM employees WHERE job_title LIKE '%Driver%' AND status='active'")->fetch_row()[0];
} else {
    $kpi1_lbl="Pending Leave"; $kpi1_val="0";
    $kpi2_lbl="On Leave Today"; $kpi2_val="0";
    $kpi3_lbl="Leave Balance"; $kpi3_val="System";
}

$active_count = $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetch_row()[0];
$pageTitle = "People & Access";
?>
<?php $layout->header($pageTitle); ?>
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root { --ease-expo: cubic-bezier(0.16,1,0.3,1); --ease-spring: cubic-bezier(0.34,1.56,0.64,1); }

        body, .main-content-wrapper, input, select, textarea, table, button, .nav-link, .modal-content {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        /* ── Hero ── */
        .emp-hero {
            background: linear-gradient(135deg, var(--forest,#0f2e25) 0%, var(--forest-mid,#1a5c42) 100%);
            border-radius: 24px;
            padding: 44px 52px;
            color: #fff;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            animation: fadeUp 0.65s var(--ease-expo) both;
        }

        .emp-hero .hero-grid {
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px), linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);
            background-size: 32px 32px; pointer-events: none;
        }

        .emp-hero .hero-circle { position: absolute; top: -80px; right: -80px; width: 280px; height: 280px; border-radius: 50%; background: rgba(255,255,255,0.04); pointer-events: none; }

        .emp-hero-badge {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px); border-radius: 50px;
            padding: 5px 14px; font-size: 11px; font-weight: 700;
            letter-spacing: 0.7px; text-transform: uppercase; margin-bottom: 14px;
        }

        .emp-hero h1 { font-size: 2.6rem; font-weight: 800; letter-spacing: -0.5px; line-height: 1.1; margin-bottom: 10px; }
        .emp-hero p  { opacity: 0.72; font-size: 0.95rem; line-height: 1.6; margin: 0 0 20px; }

        .hero-strength-bubble {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 18px;
            padding: 20px 28px;
            text-align: center;
            flex-shrink: 0;
        }

        .hero-strength-bubble .label { font-size: 11px; font-weight: 600; opacity: 0.65; text-transform: uppercase; letter-spacing: 0.7px; }
        .hero-strength-bubble .value { font-size: 2rem; font-weight: 800; color: #a3e635; line-height: 1.1; }

        .btn-hero-lime {
            display: inline-flex; align-items: center; gap: 7px;
            background: #a3e635; color: var(--forest,#0f2e25);
            font-size: 0.875rem; font-weight: 700; padding: 10px 22px;
            border-radius: 50px; border: none; cursor: pointer;
            transition: all 0.22s var(--ease-expo);
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .btn-hero-lime:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(163,230,53,0.3); color: var(--forest,#0f2e25); text-decoration: none; }

        .btn-hero-ghost {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            color: #fff; font-size: 0.875rem; font-weight: 700; padding: 10px 22px;
            border-radius: 50px; cursor: pointer;
            transition: background 0.18s ease;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .btn-hero-ghost:hover { background: rgba(255,255,255,0.17); color: #fff; }

        /* ── Stat Cards ── */
        .emp-stat {
            background: #fff; border-radius: 18px; padding: 22px 24px;
            border: 1px solid rgba(0,0,0,0.055); box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            height: 100%; animation: fadeUp 0.6s var(--ease-expo) both;
            transition: transform 0.22s var(--ease-expo), box-shadow 0.22s ease;
        }

        .emp-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.09); }
        .emp-stat:nth-child(1) { animation-delay: 0.05s; }
        .emp-stat:nth-child(2) { animation-delay: 0.10s; }
        .emp-stat:nth-child(3) { animation-delay: 0.15s; }

        .emp-stat .s-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 14px; }
        .emp-stat .s-label { font-size: 10.5px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: #9ca3af; margin-bottom: 5px; }
        .emp-stat .s-value { font-size: 1.75rem; font-weight: 800; color: var(--forest,#0f2e25); line-height: 1; }

        /* ── Main Card ── */
        .emp-card {
            background: #fff; border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.055); box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden; animation: fadeUp 0.6s var(--ease-expo) 0.2s both;
        }

        /* ── View Tabs ── */
        .view-tabs-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px; border-bottom: 1px solid #f3f4f6; flex-wrap: wrap; gap: 12px;
        }

        .view-tabs {
            display: flex; gap: 4px;
            background: #f3f4f6; border-radius: 12px; padding: 4px;
        }

        .view-tab {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 9px;
            font-size: 0.82rem; font-weight: 600; color: #6b7280;
            text-decoration: none; transition: all 0.2s var(--ease-expo);
            white-space: nowrap; border: none; background: transparent;
        }

        .view-tab:hover:not(.active) { color: #374151; }

        .view-tab.active {
            background: #fff; color: var(--forest,#0f2e25);
            font-weight: 700; box-shadow: 0 1px 6px rgba(0,0,0,0.07);
        }

        /* Search bar */
        .tab-search-wrap { position: relative; }
        .tab-search-wrap i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85rem; pointer-events: none; }
        .tab-search-wrap input { padding: 8px 13px 8px 32px; border: 1px solid #e5e7eb; border-radius: 9px; font-size: 0.83rem; font-weight: 500; color: #111827; font-family: 'Plus Jakarta Sans',sans-serif!important; box-shadow: none; width: 220px; transition: border-color 0.18s ease, box-shadow 0.18s ease; }
        .tab-search-wrap input:focus { outline: none; border-color: rgba(15,46,37,0.35); box-shadow: 0 0 0 3px rgba(15,46,37,0.07); width: 260px; }

        /* ── Tables ── */
        .emp-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .emp-table thead th { background: #fafafa; font-size: 10.5px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: #9ca3af; padding: 11px 16px; border: none; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
        .emp-table tbody tr { border-bottom: 1px solid #f9fafb; transition: background 0.15s ease; }
        .emp-table tbody tr:last-child { border-bottom: none; }
        .emp-table tbody tr:hover { background: #fafff8; }
        .emp-table tbody td { padding: 12px 16px; vertical-align: middle; }

        /* Avatar */
        .emp-avatar {
            width: 38px; height: 38px; border-radius: 11px;
            background: linear-gradient(135deg, var(--forest,#0f2e25), #1a5c42);
            color: #a3e635; display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800; flex-shrink: 0;
        }

        .emp-avatar.sys { background: #1e293b; color: #94a3b8; }

        .emp-name { font-weight: 700; color: #111827; font-size: 0.875rem; line-height: 1.2; }
        .emp-no   { font-size: 0.75rem; color: #9ca3af; margin-top: 2px; font-family: monospace; }

        /* Role pill */
        .role-pill {
            display: inline-flex; align-items: center;
            font-size: 10.5px; font-weight: 700; padding: 3px 9px;
            border-radius: 7px; white-space: nowrap;
        }

        .role-pill.admin { background: #eff6ff; color: #2563eb; }
        .role-pill.staff { background: #f3f4f6; color: #374151; }
        .role-pill.grade { background: transparent; color: #9ca3af; font-size: 0.72rem; font-weight: 500; padding: 0; margin-top: 3px; display: block; }

        /* Status badge */
        .status-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 7px; }
        .status-badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
        .status-badge.active    { background:#f0fdf4; color:#16a34a; }
        .status-badge.suspended { background:#fffbeb; color:#d97706; }
        .status-badge.terminated{ background:#fef2f2; color:#dc2626; }
        .status-badge.on_leave  { background:#eff6ff; color:#2563eb; }

        /* Salary cell */
        .salary-val { font-size: 0.875rem; font-weight: 700; color: var(--forest,#0f2e25); font-family: monospace; }

        /* Action menu button */
        .action-menu-btn { width: 30px; height: 30px; border-radius: 8px; background: #f3f4f6; border: 1px solid #e5e7eb; color: #6b7280; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.85rem; transition: all 0.15s ease; }
        .action-menu-btn:hover { background: rgba(15,46,37,0.07); color: var(--forest,#0f2e25); border-color: rgba(15,46,37,0.15); }

        /* ── Payroll Panel ── */
        .payroll-run-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 24px; border-bottom: 1px solid #f3f4f6; flex-wrap: wrap; gap: 12px;
            background: #fafafa;
        }

        .run-period { display: flex; align-items: center; gap: 10px; }
        .run-period-name { font-size: 1rem; font-weight: 800; color: #111827; }

        .run-status-badge { font-size: 10.5px; font-weight: 700; padding: 4px 10px; border-radius: 7px; }
        .run-status-badge.draft    { background: #fffbeb; color: #d97706; }
        .run-status-badge.approved { background: #eff6ff; color: #2563eb; }
        .run-status-badge.paid     { background: #f0fdf4; color: #16a34a; }

        .run-actions { display: flex; gap: 8px; }

        .btn-run {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.8rem; font-weight: 700; padding: 7px 16px;
            border-radius: 7px; border: none; cursor: pointer;
            transition: all 0.18s ease; font-family: 'Plus Jakarta Sans',sans-serif!important;
        }

        .btn-run.calculate { background: #f3f4f6; color: #374151; }
        .btn-run.calculate:hover { background: #e5e7eb; }
        .btn-run.approve   { background: #eff6ff; color: #2563eb; }
        .btn-run.approve:hover { background: #dbeafe; }
        .btn-run.disburse  { background: #f0fdf4; color: #16a34a; }
        .btn-run.disburse:hover { background: #dcfce7; }

        /* Payroll amounts */
        .pay-gross    { font-weight: 700; color: #111827; font-family: monospace; font-size: 0.875rem; }
        .pay-deduct   { font-weight: 700; color: #dc2626; font-family: monospace; font-size: 0.875rem; }
        .pay-net      { font-weight: 800; color: #16a34a; font-family: monospace; font-size: 0.875rem; }
        .pay-basic    { color: #6b7280; font-family: monospace; font-size: 0.875rem; }

        /* ── Leave status ── */
        .leave-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 7px; }
        .leave-badge.active   { background:#f0fdf4; color:#16a34a; }
        .leave-badge.on_leave { background:#eff6ff; color:#2563eb; }
        .leave-badge.default  { background:#f3f4f6; color:#6b7280; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 64px 24px; }
        .empty-state .ei { font-size: 2.8rem; color: #d1d5db; margin-bottom: 14px; }
        .empty-state h5  { font-weight: 700; color: #374151; margin-bottom: 6px; font-size: 0.95rem; }
        .empty-state p   { font-size: 0.85rem; color: #9ca3af; }

        /* ── Payroll Empty ── */
        .payroll-empty { text-align: center; padding: 72px 24px; }
        .payroll-empty .ei { font-size: 3rem; color: #d1d5db; margin-bottom: 16px; }

        /* ── Modals ── */
        .modal-content { border-radius: 20px !important; border: none !important; }
        .modal-content .form-control,
        .modal-content .form-select {
            border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 9px 13px; font-size: 0.875rem; font-weight: 500; color: #111827;
            box-shadow: none; font-family: 'Plus Jakarta Sans',sans-serif!important;
        }
        .modal-content .form-control:focus,
        .modal-content .form-select:focus { border-color: rgba(15,46,37,0.35); box-shadow: 0 0 0 3px rgba(15,46,37,0.07); }

        .modal-content .form-label { font-size: 10.5px; font-weight: 700; letter-spacing: 0.7px; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }

        .modal-section-header {
            font-size: 10.5px; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; color: #9ca3af;
            margin-bottom: 14px; padding-bottom: 8px;
            border-bottom: 1px solid #f3f4f6;
            display: flex; align-items: center; gap: 7px;
        }

        .modal-section { background: #fafafa; border: 1px solid #f0f0f0; border-radius: 14px; padding: 18px; margin-bottom: 18px; }

        .btn-modal-submit {
            width: 100%; padding: 12px; border-radius: 50px;
            background: var(--forest,#0f2e25); color: #fff;
            font-size: 0.875rem; font-weight: 700; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.22s var(--ease-expo);
            font-family: 'Plus Jakarta Sans',sans-serif!important;
        }

        .btn-modal-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15,46,37,0.25); }

        /* Payroll history list */
        .history-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 13px 20px; border-bottom: 1px solid #f3f4f6;
            text-decoration: none; transition: background 0.15s ease;
        }

        .history-item:hover { background: #fafff8; }
        .history-item:last-child { border-bottom: none; }
        .history-item-name { font-size: 0.875rem; font-weight: 700; color: #111827; }
        .history-item-status { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #9ca3af; margin-top: 2px; }
        .history-item-amt { font-size: 0.82rem; font-weight: 700; color: var(--forest,#0f2e25); font-family: monospace; background: #f0fdf4; padding: 3px 10px; border-radius: 7px; }

        /* ── Animations ── */
        @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

        @media (max-width: 768px) {
            .emp-hero { padding: 28px 24px; flex-direction: column; }
            .emp-hero h1 { font-size: 2rem; }
            .view-tabs { flex-wrap: wrap; }
            .tab-search-wrap input { width: 100%; }
        }
    </style>

    <!-- ─── HERO ──────────────────────────────────────────── -->
    <div class="emp-hero mb-4">
        <div class="hero-grid"></div>
        <div class="hero-circle"></div>
        <div>
            <div class="emp-hero-badge"><i class="bi bi-people-fill"></i> Staff Command Center</div>
            <h1>HR &amp; Identity.</h1>
            <p>Manage your talent ecosystem. Hire, compensate, and secure your workforce with <strong style="color:#a3e635;opacity:1;">vibrant efficiency</strong>.</p>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($current_view === 'hr'): ?>
                    <button class="btn-hero-lime" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="bi bi-person-plus-fill"></i> Hire Employee
                    </button>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="btn-hero-ghost dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-cloud-download"></i> Export
                    </button>
                    <ul class="dropdown-menu shadow border-0 mt-2" style="border-radius:14px;padding:8px;">
                        <li><a class="dropdown-item rounded-3 py-2" href="#"><i class="bi bi-file-pdf text-danger me-2"></i>PDF List</a></li>
                        <li><a class="dropdown-item rounded-3 py-2" href="#"><i class="bi bi-file-excel text-success me-2"></i>Excel Sheet</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="hero-strength-bubble d-none d-lg-block">
            <div class="label">Workforce Strength</div>
            <div class="value"><?= $active_count ?></div>
            <div style="font-size:0.75rem;opacity:0.6;margin-top:4px;">Active Staff</div>
        </div>
    </div>

    <?php flash_render(); ?>

    <!-- ─── KPI CARDS ─────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <?php
        $icons  = ['people-fill','wallet2','activity'];
        $colors = [['bg'=>'rgba(15,46,37,0.08)','c'=>'var(--forest,#0f2e25)'],['bg'=>'#f0fdf4','c'=>'#16a34a'],['bg'=>'#eff6ff','c'=>'#2563eb']];
        foreach ([[$kpi1_lbl,$kpi1_val],[$kpi2_lbl,$kpi2_val],[$kpi3_lbl,$kpi3_val]] as $i => [$lbl,$val]):
        ?>
        <div class="col-md-4">
            <div class="emp-stat">
                <div class="s-icon" style="background:<?= $colors[$i]['bg'] ?>;color:<?= $colors[$i]['c'] ?>;">
                    <i class="bi bi-<?= $icons[$i] ?>"></i>
                </div>
                <div class="s-label"><?= $lbl ?></div>
                <div class="s-value"><?= $val ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ─── MAIN CARD ─────────────────────────────────────── -->
    <div class="emp-card">

        <!-- Tab Bar -->
        <div class="view-tabs-bar">
            <div class="view-tabs">
                <a href="?view=hr"      class="view-tab <?= $current_view==='hr'      ?'active':'' ?>"><i class="bi bi-person-badge-fill"></i> Staff Directory</a>
                <a href="?view=leave"   class="view-tab <?= $current_view==='leave'   ?'active':'' ?>"><i class="bi bi-calendar-check-fill"></i> Leave</a>
            </div>
            <form class="tab-search-wrap" method="GET">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Search…" value="<?= htmlspecialchars($_GET['q']??'') ?>">
                <input type="hidden" name="view" value="<?= $current_view ?>">
            </form>
        </div>

        <?php if ($current_view === 'hr'): ?>
        <div class="table-responsive">
            <table class="emp-table">
                <thead>
                    <tr>
                        <th style="padding-left:24px;">Employee</th>
                        <th>Role &amp; Grade</th>
                        <th>Contact</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th style="text-align:right;padding-right:24px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_rows)): ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <div class="ei"><i class="bi bi-inbox"></i></div>
                            <h5>No records found</h5>
                            <p>Try adjusting your search or filter.</p>
                        </div>
                    </td></tr>
                    <?php else: ?>
                        <?php foreach ($data_rows as $row):
                            $st_key = match($row['status'] ?? 'active') { 'active'=>'active','terminated'=>'terminated','suspended'=>'suspended',default=>'on_leave' };
                        ?>
                        <tr>
                            <td style="padding-left:24px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="emp-avatar"><?= getInitials($row['full_name']) ?></div>
                                    <div>
                                        <div class="emp-name"><?= htmlspecialchars($row['full_name']??'') ?></div>
                                        <div class="emp-no"><?= htmlspecialchars($row['employee_no']??'') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="role-pill <?= !empty($row['admin_role'])?'admin':'staff' ?>">
                                    <?= htmlspecialchars($row['job_title']??'') ?>
                                </span>
                                <span class="role-pill grade"><?= $row['grade_name'] ?? '—' ?></span>
                            </td>
                            <td>
                                <div style="font-size:0.85rem;font-weight:600;color:#374151;"><?= htmlspecialchars($row['phone']??'') ?></div>
                                <div style="font-size:0.75rem;color:#9ca3af;"><?= htmlspecialchars($row['company_email']??'') ?></div>
                            </td>
                            <td><span class="salary-val"><?= ksh($row['salary']) ?></span></td>
                            <td><span class="status-badge <?= $st_key ?>"><?= ucfirst($row['status']) ?></span></td>
                            <td style="text-align:right;padding-right:24px;">
                                <div class="dropdown">
                                    <button class="action-menu-btn" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:14px;padding:6px;min-width:190px;">
                                        <li><a class="dropdown-item rounded-3 py-2" href="#" onclick='editEmp(<?= json_encode($row) ?>)'><i class="bi bi-pencil-square me-2"></i>Edit Details</a></li>
                                        <li><a class="dropdown-item rounded-3 py-2" href="payroll.php?employee_id=<?= $row['employee_id'] ?>"><i class="bi bi-bank2 me-2"></i>Payroll History</a></li>
                                        <li><hr class="dropdown-divider mx-2"></li>
                                        <li><a class="dropdown-item rounded-3 py-2 text-danger" href="#"><i class="bi bi-person-x me-2"></i>Suspend Access</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($current_view === 'leave'): ?>
        <div class="table-responsive">
            <table class="emp-table">
                <thead>
                    <tr>
                        <th style="padding-left:24px;">Employee</th>
                        <th>Status</th>
                        <th>Department / Role</th>
                        <th>Leave Balance</th>
                        <th style="text-align:right;padding-right:24px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_rows)): ?>
                    <tr><td colspan="5">
                        <div class="empty-state"><div class="ei"><i class="bi bi-calendar-x"></i></div><h5>No employees found</h5></div>
                    </td></tr>
                    <?php else: ?>
                        <?php foreach ($data_rows as $row):
                            $lv_key = match($row['status'] ?? 'active') { 'on_leave'=>'on_leave','active'=>'active',default=>'default' };
                        ?>
                        <tr>
                            <td style="padding-left:24px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="emp-avatar"><?= getInitials($row['full_name']) ?></div>
                                    <div>
                                        <div class="emp-name"><?= htmlspecialchars($row['full_name']??'') ?></div>
                                        <div class="emp-no"><?= htmlspecialchars($row['employee_no']??'') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="leave-badge <?= $lv_key ?>"><?= ucfirst(str_replace('_',' ',$row['status']??'')) ?></span></td>
                            <td style="font-size:0.85rem;color:#6b7280;"><?= htmlspecialchars($row['job_title']??'') ?></td>
                            <td style="font-weight:700;color:#374151;font-size:0.875rem;">21 Days <span style="font-size:0.75rem;font-weight:400;color:#9ca3af;">(Standard)</span></td>
                            <td style="text-align:right;padding-right:24px;">
                                <div class="dropdown">
                                    <button class="action-menu-btn" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:14px;padding:6px;min-width:190px;">
                                        <li><a class="dropdown-item rounded-3 py-2" href="#" onclick='editEmp(<?= json_encode($row) ?>)'><i class="bi bi-calendar-check me-2"></i>Update Status</a></li>
                                        <li><a class="dropdown-item rounded-3 py-2" href="#"><i class="bi bi-clock-history me-2"></i>Leave History</a></li>
                                        <li><hr class="dropdown-divider mx-2"></li>
                                        <li><a class="dropdown-item rounded-3 py-2 text-primary" href="#"><i class="bi bi-plus-circle me-2"></i>Apply for Leave</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /emp-card -->

    <!-- ════════════════════════════════════════════════════
         MODALS
    ════════════════════════════════════════════════════ -->

    <!-- ADD EMPLOYEE -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <div>
                        <h5 style="font-size:1rem;font-weight:800;color:var(--forest,#0f2e25);margin-bottom:4px;">
                            <i class="bi bi-person-plus-fill me-2" style="color:#a3e635;"></i>Onboard Talent
                        </h5>
                        <p style="font-size:0.8rem;color:#9ca3af;margin:0;">Fill in details to generate this employee's system identity.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <form method="POST" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_employee">

                        <div class="modal-section">
                            <div class="modal-section-header"><i class="bi bi-person-badge"></i> Personal Identity</div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Full Legal Name</label><input type="text" name="full_name" class="form-control" placeholder="e.g. Jane Doe" required></div>
                                <div class="col-md-6"><label class="form-label">National ID / Passport</label><input type="text" name="national_id" class="form-control" placeholder="ID Number" required></div>
                                <div class="col-md-6"><label class="form-label">Mobile Phone</label><input type="text" name="phone" class="form-control" placeholder="07XX XXX XXX" required></div>
                                <div class="col-md-6"><label class="form-label">Personal Email</label><input type="email" name="personal_email" class="form-control" placeholder="jane.doe@gmail.com"></div>
                            </div>
                        </div>

                        <div class="modal-section">
                            <div class="modal-section-header"><i class="bi bi-briefcase-fill"></i> Role &amp; Compensation</div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Job Title / Position</label>
                                    <select name="job_title" class="form-select" required>
                                        <option value="" selected disabled>Select Position…</option>
                                        <?php $t_res=$db->query("SELECT title FROM job_titles"); while($t=$t_res->fetch_assoc()) echo "<option>{$t['title']}</option>"; ?>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label">Salary Grade</label>
                                    <select name="grade_id" class="form-select" id="grade_sel" onchange="updSal()" required>
                                        <option value="" selected disabled>Choose Grade…</option>
                                        <?php foreach ($grades as $g) echo "<option value='{$g['id']}' data-sal='{$g['basic_salary']}'>{$g['grade_name']} (Base: ".ksh($g['basic_salary']).")</option>"; ?>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label">Hire Date</label><input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                                <div class="col-md-6"><label class="form-label">Confirmed Basic Salary (KES)</label>
                                    <div class="input-group">
                                        <span class="input-group-text" style="background:rgba(15,46,37,0.08);color:var(--forest,#0f2e25);border-color:#e5e7eb;font-weight:700;font-size:0.8rem;">KES</span>
                                        <input type="number" name="salary" id="sal_inp" class="form-control fw-bold" step="0.01" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-section">
                            <div class="modal-section-header"><i class="bi bi-bank2"></i> Statutory &amp; Banking</div>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label">KRA PIN</label><input type="text" name="kra_pin" class="form-control" placeholder="A00…" style="text-transform:uppercase;" required></div>
                                <div class="col-md-4"><label class="form-label">NSSF Number</label><input type="text" name="nssf_no" class="form-control" placeholder="Optional"></div>
                                <div class="col-md-4"><label class="form-label">SHA Number</label><input type="text" name="sha_no" class="form-control" placeholder="Optional"></div>
                                <div class="col-md-6"><label class="form-label">Bank Name</label><input type="text" name="bank_name" class="form-control" placeholder="e.g. KCB, Equity"></div>
                                <div class="col-md-6"><label class="form-label">Account Number</label><input type="text" name="bank_account" class="form-control" placeholder="Account No."></div>
                            </div>
                        </div>

                        <button type="submit" class="btn-modal-submit">
                            <i class="bi bi-check-circle-fill"></i> Complete Onboarding
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT EMPLOYEE -->
    <div class="modal fade" id="editEmpModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <h5 style="font-size:1rem;font-weight:800;color:var(--forest,#0f2e25);"><i class="bi bi-pencil-square me-2"></i>Edit Employee</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_employee">
                        <input type="hidden" name="employee_id" id="edit_emp_id">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Full Name</label><input type="text" name="full_name" id="edit_emp_name" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="edit_emp_phone" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">Job Title</label>
                                <select name="job_title" id="edit_emp_title" class="form-select" required>
                                    <?php $t_res=$db->query("SELECT title FROM job_titles"); while($t=$t_res->fetch_assoc()) echo "<option>{$t['title']}</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Salary (KES)</label><input type="number" name="salary" id="edit_emp_salary" class="form-control" step="0.01" required></div>
                            <div class="col-md-6"><label class="form-label">Status</label>
                                <select name="status" id="edit_emp_status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="terminated">Terminated</option>
                                    <option value="on_leave">On Leave</option>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">KRA PIN</label><input type="text" name="kra_pin" id="edit_emp_kra" class="form-control" style="text-transform:uppercase;"></div>
                            <div class="col-md-4"><label class="form-label">NSSF</label><input type="text" name="nssf_no" id="edit_emp_nssf" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">SHA</label><input type="text" name="sha_no" id="edit_emp_sha" class="form-control"></div>
                            <div class="col-12"><button type="submit" class="btn-modal-submit"><i class="bi bi-check-circle-fill"></i> Update Employee</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updSal() {
            const sel = document.getElementById('grade_sel');
            const inp = document.getElementById('sal_inp');
            if (sel && inp) inp.value = sel.options[sel.selectedIndex]?.dataset?.sal ?? '';
        }

        function editEmp(row) {
            document.getElementById('edit_emp_id').value     = row.employee_id || '';
            document.getElementById('edit_emp_name').value   = row.full_name   || '';
            document.getElementById('edit_emp_phone').value  = row.phone       || '';
            document.getElementById('edit_emp_salary').value = row.salary      || '';
            document.getElementById('edit_emp_kra').value    = row.kra_pin     || '';
            document.getElementById('edit_emp_nssf').value   = row.nssf_no     || '';
            document.getElementById('edit_emp_sha').value    = row.sha_no      || '';
            const titleSel  = document.getElementById('edit_emp_title');
            const statusSel = document.getElementById('edit_emp_status');
            if (titleSel)  { Array.from(titleSel.options).forEach(o  => o.selected = o.value === row.job_title); }
            if (statusSel) { Array.from(statusSel.options).forEach(o => o.selected = o.value === row.status); }
            new bootstrap.Modal(document.getElementById('editEmpModal')).show();        }
    </script>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>