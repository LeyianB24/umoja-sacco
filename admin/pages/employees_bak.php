<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!-- DEBUG: Top of file -->"; flush();

if (session_status() === PHP_SESSION_NONE) session_start();


require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/HRService.php';
require_once __DIR__ . '/../../inc/SystemUserService.php';
require_once __DIR__ . '/../../inc/PayrollService.php';

// Session validation
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

// Ops Manager
echo "<!-- DEBUG: Starting execution -->";

// Enforce Permission
echo "<!-- DEBUG: Checking permission -->";
// require_permission(); // Basic access check
echo "<!-- DEBUG: Permission SKIPPED -->";

$layout = LayoutManager::create('admin');
echo "<!-- DEBUG: Layout created -->";
$db = $conn;

// Initialize Services
$hrService = new HRService($db);
$systemUserService = new SystemUserService($db);
$payrollService = new PayrollService($db);

$current_view = $_GET['view'] ?? 'hr'; // 'hr' or 'sys'
$admin_id     = $_SESSION['admin_id'];
$admin_role   = $_SESSION['role_id'] ?? 0;

// Helper: Initials
if (!function_exists('getInitials')) {
    function getInitials($name) { 
        $words = explode(" ", $name);
        $acronym = "";
        foreach ($words as $w) if(!empty($w)) $acronym .= mb_substr($w, 0, 1);
        return strtoupper(substr($acronym, 0, 2)); 
    }
}
if (!function_exists('ksh')) {
    function ksh($v, $d = 2) { return number_format((float)($v ?? 0), $d); }
}
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        if(!$datetime) return 'Never';
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        $chk = [31536000=>'yr', 2592000=>'mth', 604800=>'wk', 86400=>'day', 3600=>'hr', 60=>'min'];
        foreach ($chk as $s => $t) {
            $d = $diff / $s;
            if ($d >= 1) { $r = round($d); return "$r $t" . ($r > 1 ? 's' : '') . ' ago'; }
        }
        return 'Just now';
    }
}

// ---------------------------------------------------------
// 1. HANDLE POST ACTIONS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    // --- HR ACTIONS ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_employee') {
        // Prepare employee data
        $employeeData = [
            'full_name' => trim($_POST['full_name']),
            'national_id' => trim($_POST['national_id']),
            'phone' => trim($_POST['phone']),
            'job_title' => trim($_POST['job_title']),
            'grade_id' => (int)$_POST['grade_id'],
            'personal_email' => trim($_POST['personal_email'] ?? ''),
            'salary' => (float)$_POST['salary'],
            'kra_pin' => strtoupper(trim($_POST['kra_pin'] ?? '')),
            'nssf_no' => trim($_POST['nssf_no'] ?? ''),
            'nhif_no' => trim($_POST['nhif_no'] ?? ''),
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'bank_account' => trim($_POST['bank_account'] ?? ''),
            'hire_date' => $_POST['hire_date']
        ];
        
        // Create employee using HRService
        $result = $hrService->createEmployee($employeeData);
        
        if ($result['success']) {
            // Create system user account
            $roleId = $systemUserService->getRoleIdForTitle($employeeData['job_title']);
            $userData = [
                'employee_no' => $result['employee_no'],
                'company_email' => $result['company_email'],
                'full_name' => $employeeData['full_name']
            ];
            
            $userResult = $systemUserService->createSystemUser($userData, $roleId);
            
            if ($userResult['success']) {
                // Link admin account to employee
                $stmt = $db->prepare("UPDATE employees SET admin_id = ? WHERE employee_id = ?");
                $stmt->bind_param("ii", $userResult['admin_id'], $result['employee_id']);
                $stmt->execute();
            }
            
            flash_set("Employee onboarded successfully. ID: {$result['employee_no']}", 'success');
        } else {
            flash_set("Error: " . $result['error'], 'error');
        }
        
        header("Location: employees.php?view=hr"); 
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_employee') {
        $employeeId = (int)$_POST['employee_id'];
        $updateData = [
            'full_name' => trim($_POST['full_name']),
            'phone' => trim($_POST['phone']),
            'job_title' => trim($_POST['job_title']),
            'salary' => (float)$_POST['salary'],
            'status' => $_POST['status']
        ];
        
        $result = $hrService->updateEmployee($employeeId, $updateData);
        
        if ($result['success']) {
            flash_set("Employee updated successfully.", 'success');
        } else {
            flash_set("Error: " . $result['error'], 'error');
        }
        
        header("Location: employees.php?view=hr"); 
        exit;
    }

    // --- SYSTEM ADMIN ACTIONS ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_admin') {
        if (!can('system_settings') && $admin_role != 1) {
            flash_set("Access Denied.", 'error'); 
            header("Location: employees.php?view=sys"); 
            exit;
        }

        $userData = [
            'employee_no' => trim($_POST['username']),
            'company_email' => trim($_POST['email']),
            'full_name' => trim($_POST['full_name'])
        ];
        $roleId = (int)$_POST['role_id'];
        
        // Create system user
        $result = $systemUserService->createSystemUser($userData, $roleId);
        
        if ($result['success']) {
            // Update password if different from default
            if (!empty($_POST['password']) && $_POST['password'] !== $userData['employee_no']) {
                $systemUserService->resetPassword($result['admin_id'], $_POST['password']);
            }
            
            // Create stub employee record for integrity
            $empData = [
                'full_name' => $userData['full_name'],
                'national_id' => 'SYS-' . $result['admin_id'],
                'phone' => '',
                'job_title' => 'System Administrator',
                'grade_id' => 1,
                'salary' => 0.00,
                'hire_date' => date('Y-m-d')
            ];
            $empResult = $hrService->createEmployee($empData);
            
            if ($empResult['success']) {
                // Link employee to admin
                $stmt = $db->prepare("UPDATE employees SET admin_id = ? WHERE employee_id = ?");
                $stmt->bind_param("ii", $result['admin_id'], $empResult['employee_id']);
                $stmt->execute();
            }
            
            flash_set("System Administrator registered successfully.", 'success');
        } else {
            flash_set("Error: " . $result['error'], 'error');
        }
        
        header("Location: employees.php?view=sys"); 
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_admin') {
        if (!can('system_settings') && $admin_role != 1) {
            flash_set("Access Denied.", 'error'); 
            header("Location: employees.php?view=sys"); 
            exit;
        }
        
        $targetId = (int)$_POST['admin_id'];
        $fullname = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $roleId = (int)$_POST['role_id'];

        // Update role
        $result = $systemUserService->assignRole($targetId, $roleId);
        
        // Update basic info (name, email)
        $stmt = $db->prepare("UPDATE admins SET full_name=?, email=? WHERE admin_id=?");
        $stmt->bind_param("ssi", $fullname, $email, $targetId);
        $stmt->execute();
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            $systemUserService->resetPassword($targetId, $_POST['password']);
        }
        
        // Sync name to employee record
        $stmt = $db->prepare("UPDATE employees SET full_name=? WHERE admin_id=?");
        $stmt->bind_param("si", $fullname, $targetId);
        $stmt->execute();
        
        flash_set("Administrator account updated successfully.", 'success');
        header("Location: employees.php?view=sys"); 
        exit;
    }
}

// ---------------------------------------------------------
// 2. FETCH DATA DEPENDING ON VIEW
// ---------------------------------------------------------

// Roles for dropdowns
$roles_res = $db->query("SELECT * FROM roles ORDER BY name ASC");
$defined_roles = [];
while($r = $roles_res->fetch_assoc()) {
    $defined_roles[$r['id']] = ['label' => ucwords($r['name']), 'name' => strtolower($r['name'])];
}

// Grades for HR dropdown
$grades = [];
$g_q = $db->query("SELECT * FROM salary_grades ORDER BY basic_salary DESC");
while ($g = $g_q->fetch_assoc()) $grades[] = $g;

$data_rows = [];
if ($current_view === 'hr') {
    $filter_status = $_GET['status'] ?? 'active';
    $search = trim($_GET['q'] ?? '');
    
    $where = [];
    if ($filter_status !== 'all') $where[] = "e.status = '$filter_status'";
    if ($search) $where[] = "(e.full_name LIKE '%$search%' OR e.national_id LIKE '%$search%')";
    
    $where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT e.*, r.name as admin_role, sg.grade_name 
            FROM employees e 
            LEFT JOIN admins a ON e.admin_id = a.admin_id 
            LEFT JOIN roles r ON a.role_id = r.id 
            LEFT JOIN salary_grades sg ON e.grade_id = sg.id 
            $where_sql ORDER BY e.full_name ASC";
    $res = $db->query($sql);
    while($row = $res->fetch_assoc()) $data_rows[] = $row;
} else {
    // SYSTEM VIEW
    $search = trim($_GET['q'] ?? '');
    $where_sql = $search ? "WHERE full_name LIKE '%$search%' OR username LIKE '%$search%'" : "";
    
    $sql = "SELECT * FROM admins $where_sql ORDER BY created_at DESC";
    $res = $db->query($sql);
    while($row = $res->fetch_assoc()) $data_rows[] = $row;
}

// KPI Stats
if ($current_view === 'hr') {
    $kpi1_lbl = "Total Staff"; $kpi1_val = $db->query("SELECT COUNT(*) FROM employees")->fetch_row()[0];
    $kpi2_lbl = "Monthly Payroll"; $kpi2_val = "KES " . number_format($db->query("SELECT SUM(salary) FROM employees WHERE status='active'")->fetch_row()[0] ?? 0);
    $kpi3_lbl = "Active Drivers"; $kpi3_val = $db->query("SELECT COUNT(*) FROM employees WHERE job_title LIKE '%Driver%' AND status='active'")->fetch_row()[0];
} else {
    $kpi1_lbl = "Total Admins"; $kpi1_val = $db->query("SELECT COUNT(*) FROM admins")->fetch_row()[0];
    $kpi2_lbl = "Active Roles"; $kpi2_val = count($defined_roles);
    $kpi3_lbl = "Joined Today"; $kpi3_val = $db->query("SELECT COUNT(*) FROM admins WHERE DATE(created_at) = CURDATE()")->fetch_row()[0];
}

$pageTitle = "People & Access";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= $pageTitle ?> | Umoja Sacco</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
    
    <style>
        :root {
            --primary-hue: 158; --primary-sat: 64%; --primary-lig: 52%;
            --color-primary: hsl(var(--primary-hue), var(--primary-sat), var(--primary-lig));
            --color-primary-dark: hsl(var(--primary-hue), var(--primary-sat), 25%);
            --color-primary-light: hsl(var(--primary-hue), var(--primary-sat), 94%);
            --bg-app: #f0f2f5; --text-main: #1e293b; --glass-bg: rgba(255, 255, 255, 0.85); --glass-border: 1px solid rgba(255, 255, 255, 0.6);
            --card-radius: 16px;
        }
        [data-bs-theme="dark"] {
            --bg-app: #0f172a; --text-main: #f1f5f9; --glass-bg: rgba(30, 41, 59, 0.7); --glass-border: 1px solid rgba(255, 255, 255, 0.08);
        }
        body { background-color: var(--bg-app); color: var(--text-main); font-family: 'Plus Jakarta Sans', sans-serif; }
        .hd-glass { background: var(--glass-bg); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: var(--glass-border); border-radius: var(--card-radius); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; }
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; background: var(--color-primary-light); color: var(--color-primary-dark); }
        .table-custom tr:hover td { background-color: rgba(var(--primary-hue), 185, 129, 0.05); }
        .nav-tabs-custom .nav-link { border: 0; color: var(--text-muted); font-weight: 500; padding: 1rem 1.5rem; background: transparent; border-bottom: 2px solid transparent; }
        .nav-tabs-custom .nav-link.active { color: var(--color-primary-dark); border-bottom-color: var(--color-primary); background: transparent; }
        .nav-tabs-custom .nav-link:hover:not(.active) { color: var(--color-primary); }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle); ?>
        
        <div class="container-fluid py-4">
            
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-gradient">People & Access</h3>
                    <p class="text-muted small mb-0">Unified management for Employees, Payroll, and System Administrators.</p>
                </div>
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-dark dropdown-toggle rounded-pill px-3" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu shadow-sm">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-pdf text-danger me-2"></i>PDF List</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-excel text-success me-2"></i>Excel Sheet</a></li>
                        </ul>
                    </div>
                    <?php if($current_view === 'hr'): ?>
                        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class="bi bi-person-plus-fill me-2"></i>Hire Employee
                        </button>
                    <?php elseif(($admin_role == 1 || can('system_settings'))): ?>
                        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                            <i class="bi bi-shield-lock-fill me-2"></i>New Admin
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row g-4 mb-4">
                <?php 
                $stats = [
                    ['label' => $kpi1_lbl, 'val' => $kpi1_val, 'icon' => 'people-fill', 'color' => 'primary'],
                    ['label' => $kpi2_lbl, 'val' => $kpi2_val, 'icon' => 'wallet2', 'color' => 'success'],
                    ['label' => $kpi3_lbl, 'val' => $kpi3_val, 'icon' => 'activity', 'color' => 'info'],
                ];
                foreach($stats as $s): ?>
                <div class="col-md-4">
                    <div class="hd-glass p-4 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold fs-7 mb-2"><?= $s['label'] ?></h6>
                            <h2 class="fw-bold mb-0 text-<?= $s['color'] ?>"><?= $s['val'] ?></h2>
                        </div>
                        <div class="stat-icon bg-<?= $s['color'] ?> bg-opacity-10 text-<?= $s['color'] ?> fs-1 p-3 rounded-4">
                            <i class="bi bi-<?= $s['icon'] ?>"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php flash_render(); ?>

            <!-- TABBED VIEW -->
            <div class="hd-glass overflow-hidden">
                <div class="d-flex border-bottom bg-light bg-opacity-25 px-3 justify-content-between align-items-center">
                    <ul class="nav nav-tabs-custom mb-0">
                        <li class="nav-item">
                            <a class="nav-link <?= $current_view === 'hr' ? 'active' : '' ?>" href="?view=hr">
                                <i class="bi bi-person-badge me-2"></i>HR Directory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_view === 'sys' ? 'active' : '' ?>" href="?view=sys">
                                <i class="bi bi-shield-lock me-2"></i>System Users
                            </a>
                        </li>
                    </ul>
                    <div class="pe-3 py-2">
                        <form class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" name="q" class="form-control form-control-sm ps-5 rounded-pill bg-white border" placeholder="Search..." value="<?= htmlspecialchars($_GET['q']??'') ?>">
                            <input type="hidden" name="view" value="<?= $current_view ?>">
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead class="bg-light bg-opacity-50 small text-uppercase text-muted fw-bold">
                        <?php if($current_view === 'hr'): ?>
                            <tr>
                                <th class="ps-4 py-3">Employee</th>
                                <th class="py-3">Role & Grade</th>
                                <th class="py-3">Contact</th>
                                <th class="py-3">Salary</th>
                                <th class="py-3">Status</th>
                                <th class="text-end pe-4 py-3">Actions</th>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th class="ps-4 py-3">Administrator</th>
                                <th class="py-3">Role</th>
                                <th class="py-3">Contact Email</th>
                                <th class="py-3">Joined</th>
                                <th class="text-end pe-4 py-3">Actions</th>
                            </tr>
                        <?php endif; ?>
                        </thead>
                        <tbody>
                        <?php if(empty($data_rows)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No records found.</td></tr>
                        <?php else: foreach($data_rows as $row): ?>
                            <?php if($current_view === 'hr'): 
                                $badge_cls = match($row['status']) { 'active'=>'bg-success bg-opacity-10 text-success', 'terminated'=>'bg-danger bg-opacity-10 text-danger', default=>'bg-warning bg-opacity-10 text-warning' }; 
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle"><?= getInitials($row['full_name']) ?></div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['full_name']) ?></div>
                                            <div class="small text-muted font-monospace"><?= htmlspecialchars($row['employee_no']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="badge bg-light text-dark border fw-normal"><?= htmlspecialchars($row['job_title']) ?></div>
                                    <div class="small text-muted mt-1"><?= $row['grade_name'] ?? '-' ?></div>
                                </td>
                                <td class="small">
                                    <div><?= htmlspecialchars($row['phone']) ?></div>
                                    <span class="text-muted"><?= htmlspecialchars($row['company_email']) ?></span>
                                </td>
                                <td class="font-monospace fw-medium"><?= ksh($row['salary']) ?></td>
                                <td><span class="badge rounded-pill <?= $badge_cls ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light bg-transparent border-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><a class="dropdown-item" href="#" onclick="editEmp(<?= htmlspecialchars(json_encode($row)) ?>)">Edit Details</a></li>
                                            <li><a class="dropdown-item" href="payroll.php?employee_id=<?= $row['employee_id'] ?>">View Payroll</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php else: // SYS VIEW ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle bg-dark text-white"><?= getInitials($row['full_name']) ?></div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['full_name']) ?></div>
                                            <div class="small text-muted">@<?= htmlspecialchars($row['username']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php $rid = $row['role_id']; $rname = $defined_roles[$rid]['label'] ?? 'Unknown'; ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border-0"><?= $rname ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td class="small text-muted"><?= time_ago($row['created_at']) ?></td>
                                <td class="text-end pe-4">
                                    <?php if($admin_role == 1 || can('system_settings')): ?>
                                        <button class="btn btn-sm btn-light text-primary" onclick="editAdmin(<?= htmlspecialchars(json_encode($row)) ?>)"><i class="bi bi-pencil-square"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL: HIRE EMPLOYEE (Modern Onboarding) -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-2xl">
            <div class="modal-header border-bottom-0 pt-4 px-4">
                <div>
                    <h5 class="fw-800 text-forest mb-1"><i class="bi bi-person-plus-fill me-2 text-lime"></i>Onboard Talent</h5>
                    <p class="text-muted small mb-0">Enter employee details to generate system identity.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_employee">
                    
                    <!-- Section 1: Identity -->
                    <div class="mb-4">
                        <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-person-badge me-2"></i>Personal Identity</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Full Legal Name</label>
                                <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">National ID / Passport</label>
                                <input type="text" name="national_id" class="form-control" placeholder="ID Number" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Mobile Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="07XX XXX XXX" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Personal Email</label>
                                <input type="email" name="personal_email" class="form-control" placeholder="john.doe@gmail.com">
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Role & Compensation -->
                    <div class="mb-4 bg-light bg-opacity-50 p-3 rounded-3 border border-light">
                        <h6 class="text-uppercase small fw-bold text-forest mb-3"><i class="bi bi-briefcase me-2"></i>Role & Compensation</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Job Title / Position</label>
                                <select name="job_title" class="form-select" required>
                                    <option value="" selected disabled>Select Position...</option>
                                    <?php $t_res = $db->query("SELECT title FROM job_titles"); while($t=$t_res->fetch_assoc()) echo "<option>{$t['title']}</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Salary Grade</label>
                                <select name="grade_id" class="form-select" id="grade_sel" onchange="updSal()" required>
                                    <option value="" selected disabled>Choose Grade...</option>
                                    <?php foreach($grades as $g) echo "<option value='{$g['id']}' data-sal='{$g['basic_salary']}'>{$g['grade_name']} (Base: " . ksh($g['basic_salary']) . ")</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Hire Date</label>
                                <input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-success">Confirmed Basic Salary (KES)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-success text-white border-0">KES</span>
                                    <input type="number" name="salary" id="sal_inp" class="form-control fw-bold text-dark border-success" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Statutory & Banking -->
                    <div class="mb-3">
                        <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-bank me-2"></i>Statutory & Banking</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">KRA PIN</label>
                                <input type="text" name="kra_pin" class="form-control uppercase" placeholder="A00..." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">NSSF Number</label>
                                <input type="text" name="nssf_no" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">NHIF Number</label>
                                <input type="text" name="nhif_no" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="e.g. KCB, Equity">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Account Number</label>
                                <input type="text" name="bank_account" class="form-control" placeholder="Account No.">
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-top mt-4">
                        <button class="btn btn-forest w-100 py-3 rounded-pill fw-800 shadow-sm hover-scale">
                            <i class="bi bi-check-circle-fill me-2"></i>Complete Onboarding
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ADD ADMIN -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <div class="modal-header border-0 pb-0"><h5 class="fw-bold">Register Admin</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_admin">
                    <div class="mb-3"><input type="text" name="full_name" class="form-control" placeholder="Full Name" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                        <div class="col"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
                    </div>
                    <div class="mb-3">
                        <select name="role_id" class="form-select" required>
                            <?php foreach($defined_roles as $id => $val) echo "<option value='$id'>{$val['label']}</option>"; ?>
                        </select>
                    </div>
                    <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                    <button class="btn btn-primary w-100 rounded-pill fw-bold">Create Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updSal() {
    const s = document.getElementById('grade_sel');
    const opt = s.options[s.selectedIndex];
    if(opt.dataset.sal) document.getElementById('sal_inp').value = opt.dataset.sal;
}
</script>
</body>
</html>
