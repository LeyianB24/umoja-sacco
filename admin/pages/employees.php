<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();


require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/HRService.php';
require_once __DIR__ . '/../../inc/SystemUserService.php';
require_once __DIR__ . '/../../inc/PayrollService.php';
require_once __DIR__ . '/../../inc/PayrollEngine.php';
require_once __DIR__ . '/../../inc/PayrollCalculator.php';
require_once __DIR__ . '/../../inc/PayslipGenerator.php';
require_once __DIR__ . '/../../inc/Mailer.php';
require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';

// Session validation
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

// Enforce Permission
// require_permission(); // Basic access check

$layout = LayoutManager::create('admin');
$db = $conn;

// Initialize Services
$hrService = new HRService($db);
$systemUserService = new SystemUserService($db);
$payrollService = new PayrollService($db);
$payrollEngine = new PayrollEngine($db);

$current_view = $_GET['view'] ?? 'hr'; // 'hr', 'sys', 'payroll', 'leave'
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
            'sha_no' => trim($_POST['sha_no'] ?? ''),
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
            'status' => $_POST['status'],
            'kra_pin' => strtoupper(trim($_POST['kra_pin'] ?? '')),
            'nssf_no' => trim($_POST['nssf_no'] ?? ''),
            'sha_no' => trim($_POST['sha_no'] ?? '')
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

    // --- PAYROLL ACTIONS ---
    if (isset($_POST['action']) && $_POST['action'] === 'start_run') {
        try {
            $month = $_POST['month_selector'];
            $payrollEngine->startRun($month, $_SESSION['admin_id']);
            flash_set("Payroll period $month started.", "success");
        } catch (Exception $e) { flash_set($e->getMessage(), "danger"); }
        header("Location: employees.php?view=payroll"); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'calculate_batch') {
        try {
            $count = $payrollEngine->calculateRun(intval($_POST['run_id']));
            flash_set("Calculated payroll for $count employees.", "success");
        } catch (Exception $e) { flash_set("Calculation failed: " . $e->getMessage(), "danger"); }
        header("Location: employees.php?view=payroll&run_id=" . $_POST['run_id']); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'approve_run') {
        try {
            $payrollEngine->approveRun(intval($_POST['run_id']), $_SESSION['admin_id']);
            flash_set("Payroll Run Approved.", "success");
        } catch (Exception $e) { flash_set("Approval failed: " . $e->getMessage(), "danger"); }
        header("Location: employees.php?view=payroll&run_id=" . $_POST['run_id']); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'disburse_run') {
        try {
            $count = $payrollEngine->disburseRun(intval($_POST['run_id']));
            flash_set("Disbursed salaries to $count employees. Ledger updated.", "success");
        } catch (Exception $e) { flash_set("Disbursement failed: " . $e->getMessage(), "danger"); }
        header("Location: employees.php?view=payroll&run_id=" . $_POST['run_id']); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'download_payslip') {
        $pid = intval($_POST['payroll_id']);
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.company_email, e.personal_email, 
                          e.job_title, e.kra_pin, e.nssf_no, e.nhif_no, e.bank_name, e.bank_account, sg.grade_name 
                          FROM payroll p 
                          JOIN employees e ON p.employee_id = e.employee_id 
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.id = $pid");
        if ($pq->num_rows > 0) {
            $row = $pq->fetch_assoc();
            $data = ['employee' => $row, 'payroll' => $row];
            UniversalExportEngine::handle('pdf', function($pdf) use ($data) {
                PayslipGenerator::render($pdf, $data);
            }, ['title' => "Payslip - " . $row['month'], 'module' => 'Payroll', 'output_mode' => 'D']);
            exit;
        }
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
} elseif ($current_view === 'sys') {
    // SYSTEM VIEW
    $search = trim($_GET['q'] ?? '');
    $where_sql = $search ? "WHERE full_name LIKE '%$search%' OR username LIKE '%$search%'" : "";
    
    $sql = "SELECT * FROM admins $where_sql ORDER BY created_at DESC";
    $res = $db->query($sql);
    while($row = $res->fetch_assoc()) $data_rows[] = $row;
} elseif ($current_view === 'leave') {
    $search = trim($_GET['q'] ?? '');
    $where_sql = $search ? "WHERE e.full_name LIKE '%$search%' OR e.employee_no LIKE '%$search%'" : "";
    
    $sql = "SELECT e.*, sg.grade_name 
            FROM employees e 
            LEFT JOIN salary_grades sg ON e.grade_id = sg.id 
            $where_sql ORDER BY e.full_name ASC";
    $res = $db->query($sql);
    while($row = $res->fetch_assoc()) $data_rows[] = $row;
} elseif ($current_view === 'payroll') {
    $run_id = isset($_GET['run_id']) ? intval($_GET['run_id']) : null;
    $active_run = null;
    if ($run_id) {
        $active_run = $db->query("SELECT * FROM payroll_runs WHERE id = $run_id")->fetch_assoc();
    } else {
        $active_run = $db->query("SELECT * FROM payroll_runs ORDER BY status='draft' DESC, month DESC LIMIT 1")->fetch_assoc();
    }
    
    $payroll_items = [];
    if ($active_run) {
        $run_id = $active_run['id'];
        $pq = $db->query("SELECT p.*, e.full_name, e.employee_no, e.phone, e.job_title, e.salary as emp_salary, 
                          e.kra_pin, e.nssf_no, e.sha_no, e.status as emp_status, sg.grade_name 
                          FROM payroll p 
                          JOIN employees e ON p.employee_id = e.employee_id 
                          LEFT JOIN salary_grades sg ON e.grade_id = sg.id
                          WHERE p.payroll_run_id = $run_id 
                          ORDER BY e.employee_no ASC");
        while($row = $pq->fetch_assoc()) {
            // Map keys for the editEmp modal which expects original employee fields
            $row['salary'] = $row['emp_salary'];
            $row['status'] = $row['emp_status'];
            $payroll_items[] = $row;
        }
    }
    $history_runs = $db->query("SELECT * FROM payroll_runs ORDER BY month DESC LIMIT 12");
}

// KPI Stats
if ($current_view === 'hr') {
    $kpi1_lbl = "Total Staff"; $kpi1_val = $db->query("SELECT COUNT(*) FROM employees")->fetch_row()[0];
    $kpi2_lbl = "Monthly Payroll"; $kpi2_val = "KES " . number_format($db->query("SELECT SUM(salary) FROM employees WHERE status='active'")->fetch_row()[0] ?? 0);
    $kpi3_lbl = "Active Drivers"; $kpi3_val = $db->query("SELECT COUNT(*) FROM employees WHERE job_title LIKE '%Driver%' AND status='active'")->fetch_row()[0];
} elseif ($current_view === 'sys') {
    $kpi1_lbl = "Total Admins"; $kpi1_val = $db->query("SELECT COUNT(*) FROM admins")->fetch_row()[0];
    $kpi2_lbl = "Active Roles"; $kpi2_val = count($defined_roles);
    $kpi3_lbl = "Joined Today"; $kpi3_val = $db->query("SELECT COUNT(*) FROM admins WHERE DATE(created_at) = CURDATE()")->fetch_row()[0];
} elseif ($current_view === 'payroll') {
    $kpi1_lbl = "Period Gross"; $kpi1_val = "KES " . number_format((float)($active_run['total_gross'] ?? 0));
    $kpi2_lbl = "Net Disbursable"; $kpi2_val = "KES " . number_format((float)($active_run['total_net'] ?? 0));
    $kpi3_lbl = "Deductions"; $kpi3_val = "KES " . number_format((float)(($active_run['total_gross'] ?? 0) - ($active_run['total_net'] ?? 0)));
} else {
    $kpi1_lbl = "Pending Leave"; $kpi1_val = "0";
    $kpi2_lbl = "On Leave Today"; $kpi2_val = "0";
    $kpi3_lbl = "Leave Balance"; $kpi3_val = "System";
}

$pageTitle = "People & Access";
?>
<?php $layout->header($pageTitle); ?>
    
    <style>
        .main-content { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 1.5rem; } }
        
        /* Tab Refinement */
        .nav-tabs-custom .nav-link { border: 0; color: var(--text-muted); font-weight: 700; padding: 1.25rem 2rem; background: transparent; border-bottom: 3px solid transparent; transition: 0.3s; }
        .nav-tabs-custom .nav-link.active { color: var(--forest); border-bottom-color: var(--lime); background: transparent; }
        .nav-tabs-custom .nav-link:hover:not(.active) { color: var(--forest); background: rgba(208, 243, 93, 0.05); }
        
        .table-custom tr:hover td { background-color: rgba(208, 243, 93, 0.05); }
    </style>

    <div class="hp-hero">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Staff Command Center</span>
                <h1 class="display-4 fw-800 mb-2">HR & Identity.</h1>
                <p class="opacity-75 fs-5 mb-4">Manage your talent ecosystem. Hire, compensate, and secure your workforce with <span class="text-lime fw-bold">vibrant efficiency</span>.</p>
                
                <div class="d-flex gap-3">
                    <?php if($current_view === 'hr'): ?>
                        <button class="btn btn-lime rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                            <i class="bi bi-person-plus-fill me-2"></i>Hire Employee
                        </button>
                    <?php elseif($current_view === 'payroll'): ?>
                        <button class="btn btn-lime rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newRunModal">
                            <i class="bi bi-plus-lg me-2"></i>New Pay Period
                        </button>
                    <?php elseif(($current_view === 'sys' && ($admin_role == 1 || can('system_settings')))): ?>
                        <button class="btn btn-lime rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                            <i class="bi bi-shield-lock-fill me-2"></i>New Admin
                        </button>
                    <?php endif; ?>
                    
                    <div class="dropdown">
                        <button class="btn btn-white bg-opacity-10 text-white border-white border-opacity-25 rounded-pill px-4 py-2 dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                        <ul class="dropdown-menu shadow-lg">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-pdf text-danger me-2"></i>PDF List</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-excel text-success me-2"></i>Excel Sheet</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-5 text-end d-none d-lg-block">
                <div class="d-inline-block p-4 rounded-4 bg-white bg-opacity-10 backdrop-blur">
                    <div class="small opacity-75">Workforce Strength</div>
                    <div class="h2 fw-800 mb-0 text-lime"><?= $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetch_row()[0] ?> Active</div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-4 mb-4">
        <?php 
        $stats = [
            ['label' => $kpi1_lbl, 'val' => $kpi1_val, 'icon' => 'people-fill', 'color' => 'forest'],
            ['label' => $kpi2_lbl, 'val' => $kpi2_val, 'icon' => 'wallet2', 'color' => 'lime'],
            ['label' => $kpi3_lbl, 'val' => $kpi3_val, 'icon' => 'activity', 'color' => 'forest'],
        ];
        foreach($stats as $s): ?>
        <div class="col-md-4">
            <div class="glass-stat h-100 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase fw-800 fs-7 mb-2" style="letter-spacing: 1px;"><?= $s['label'] ?></h6>
                    <h2 class="fw-800 mb-0 text-forest"><?= $s['val'] ?></h2>
                </div>
                <div class="stat-icon bg-<?= $s['color'] == 'lime' ? 'lime' : 'forest' ?> bg-opacity-10 text-<?= $s['color'] == 'lime' ? 'forest' : 'forest' ?> fs-1 p-3 rounded-4">
                    <i class="bi bi-<?= $s['icon'] ?>"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

            <?php flash_render(); ?>

            <!-- TABBED VIEW -->
            <div class="glass-card p-0 overflow-hidden">
                <div class="d-flex border-bottom bg-white bg-opacity-50 px-3 justify-content-between align-items-center">
                    <ul class="nav nav-tabs-custom mb-0">
                        <li class="nav-item">
                            <a class="nav-link <?= $current_view === 'hr' ? 'active' : '' ?>" href="?view=hr">
                                <i class="bi bi-person-badge me-2"></i>Staff Directory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_view === 'payroll' ? 'active' : '' ?>" href="?view=payroll">
                                <i class="bi bi-bank me-2"></i>Payroll Center
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_view === 'leave' ? 'active' : '' ?>" href="?view=leave">
                                <i class="bi bi-calendar-check me-2"></i>Leave Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_view === 'sys' ? 'active' : '' ?>" href="?view=sys">
                                <i class="bi bi-shield-lock me-2"></i>Security & Users
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

                <?php if($current_view === 'hr' || $current_view === 'sys'): ?>
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
                                            <div class="fw-bold "><?= htmlspecialchars($row['full_name']) ?></div>
                                            <div class="small text-muted font-monospace"><?= htmlspecialchars($row['employee_no']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $is_admin = !empty($row['admin_role']);
                                    $bt = $is_admin ? 'info' : 'light';
                                    $tc = $is_admin ? 'info' : 'dark';
                                    ?>
                                    <div class="badge bg-<?= $bt ?> bg-opacity-10 text-<?= $tc ?> border fw-normal"><?= htmlspecialchars($row['job_title']) ?></div>
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
                                            <li><a class="dropdown-item" href="#" onclick='editEmp(<?= json_encode($row) ?>)'><i class="bi bi-pencil-square me-2"></i>Edit Details</a></li>
                                            <li><a class="dropdown-item" href="payroll.php?employee_id=<?= $row['employee_id'] ?>"><i class="bi bi-bank me-2"></i>Payroll History</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-person-x me-2"></i>Suspend Access</a></li>
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
                                            <div class="fw-bold "><?= htmlspecialchars($row['full_name']) ?></div>
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
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light bg-transparent border-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <?php if($admin_role == 1 || can('system_settings')): ?>
                                                <li><a class="dropdown-item" href="#" onclick='editAdmin(<?= json_encode($row) ?>)'><i class="bi bi-shield-check me-2"></i>Account Settings</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="bi bi-key me-2"></i>Reset Password</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-slash-circle me-2"></i>Deactivate Account</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif ($current_view === 'payroll'): ?>
                    <?php if ($active_run): ?>
                        <div class="p-4 border-bottom bg-light bg-opacity-10">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge rounded-pill border px-3 py-2 <?= $active_run['status'] === 'paid' ? 'bg-success text-white' : 'bg-warning ' ?>">
                                        <?= strtoupper($active_run['status']) ?>
                                    </span>
                                    <span class="ms-2 fw-800 "><?= date('F Y', strtotime($active_run['month'])) ?></span>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($active_run['status'] === 'draft'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="calculate_batch">
                                            <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3">Calculate</button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="approve_run">
                                            <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Approve</button>
                                        </form>
                                    <?php elseif ($active_run['status'] === 'approved'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="disburse_run">
                                            <input type="hidden" name="run_id" value="<?= $active_run['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">Disburse Funds</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle mb-0">
                                <thead class="bg-light bg-opacity-50 small text-uppercase text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4">Employee</th>
                                        <th class="text-end">Basic</th>
                                        <th class="text-end">Gross</th>
                                        <th class="text-end">Deductions</th>
                                        <th class="text-end">Net Pay</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payroll_items as $item): ?>
                                     <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold"><?= htmlspecialchars($item['full_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($item['employee_no']) ?></div>
                                        </td>
                                        <td class="text-end font-monospace"><?= ksh($item['basic_salary']) ?></td>
                                        <td class="text-end font-monospace fw-bold"><?= ksh($item['gross_pay']) ?></td>
                                        <td class="text-end font-monospace text-danger"><?= ksh($item['tax_paye'] + $item['tax_housing'] + $item['tax_nssf'] + $item['tax_sha']) ?></td>
                                        <td class="text-end font-monospace text-success fw-bold"><?= ksh($item['net_pay']) ?></td>
                                        <td class="text-end pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light bg-transparent border-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li><a class="dropdown-item" href="#" onclick='editEmp(<?= json_encode($item) ?>)'><i class="bi bi-pencil-square me-2"></i>Adjust Salary/Statutory</a></li>
                                                    <?php if($item['status'] === 'paid'): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="download_payslip">
                                                                <input type="hidden" name="payroll_id" value="<?= $item['id'] ?>">
                                                                <button type="submit" class="dropdown-item"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Download Slip</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item" href="#"><i class="bi bi-arrow-clockwise me-2"></i>Recalculate</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-bank fs-1 text-muted opacity-25 d-block mb-3"></i>
                            <h5 class="text-muted">No active payroll run found.</h5>
                            <button class="btn btn-primary rounded-pill mt-2" data-bs-toggle="modal" data-bs-target="#newRunModal">Start New Pay Period</button>
                        </div>
                    <?php endif; ?>
                <?php elseif ($current_view === 'leave'): ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead class="bg-light bg-opacity-50 small text-uppercase text-muted fw-bold">
                            <tr>
                                <th class="ps-4 py-3">Employee</th>
                                <th class="py-3">Current Status</th>
                                <th class="py-3">Department/Role</th>
                                <th class="py-3">Leave Balance</th>
                                <th class="text-end pe-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($data_rows)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No employees found.</td></tr>
                        <?php else: foreach($data_rows as $row): 
                            $st_cls = match($row['status']) { 'on_leave'=>'bg-info', 'active'=>'bg-success', default=>'bg-secondary' };
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle"><?= getInitials($row['full_name']) ?></div>
                                        <div>
                                            <div class="fw-bold "><?= htmlspecialchars($row['full_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($row['employee_no']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge rounded-pill <?= $st_cls ?> bg-opacity-10 text-<?= str_replace('bg-', '', $st_cls) ?>"><?= ucfirst(str_replace('_', ' ', $row['status'])) ?></span></td>
                                <td class="small text-muted"><?= htmlspecialchars($row['job_title']) ?></td>
                                <td class="fw-medium">21 Days <span class="small text-muted fw-normal">(Standard)</span></td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light bg-transparent border-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><a class="dropdown-item" href="#" onclick='editEmp(<?= json_encode($row) ?>)'><i class="bi bi-calendar-check me-2"></i>Update Leave Status</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-clock-history me-2"></i>Leave History</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-primary" href="#"><i class="bi bi-plus-circle me-2"></i>Apply for Leave</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
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
                                    <input type="number" name="salary" id="sal_inp" class="form-control fw-bold  border-success" step="0.01" required>
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
                                <label class="form-label small fw-bold">SHA</label>
                                <input type="text" name="sha_no" class="form-control" placeholder="Optional">
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

<!-- MODAL: PAYROLL NEW RUN -->
<div class="modal fade" id="newRunModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="action" value="start_run">
                <div class="modal-header border-bottom-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Start Pay Period</h5>
                        <div class="small text-muted">Select the month to process.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Billing Month</label>
                        <input type="month" name="month_selector" value="<?= date('Y-m') ?>" class="form-control bg-light border-0 py-3" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold">Create Draft Run</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: PAYROLL HISTORY -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">Payroll History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php if(isset($history_runs)): while($h = $history_runs->fetch_assoc()): ?>
                        <a href="employees.php?view=payroll&run_id=<?= $h['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-4 py-3 bg-transparent border-bottom">
                            <div>
                                <div class="fw-bold "><?= date('F Y', strtotime($h['month'])) ?></div>
                                <div class="small text-muted text-uppercase"><?= $h['status'] ?></div>
                            </div>
                            <span class="badge bg-light  border fw-normal font-monospace">KES <?= number_format((float)$h['total_net'], 2) ?></span>
                        </a>
                    <?php endwhile; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EDIT EMPLOYEE -->
<div class="modal fade" id="editEmpModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Edit Employee Details</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_employee">
                    <input type="hidden" name="employee_id" id="edit_emp_id">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="full_name" id="edit_emp_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Phone</label>
                        <input type="text" name="phone" id="edit_emp_phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Job Title</label>
                        <select name="job_title" id="edit_emp_title" class="form-select" required>
                            <?php $t_res = $db->query("SELECT title FROM job_titles"); while($t=$t_res->fetch_assoc()) echo "<option>{$t['title']}</option>"; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Salary (KES)</label>
                        <input type="number" name="salary" id="edit_emp_salary" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" id="edit_emp_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="terminated">Terminated</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">KRA PIN</label>
                            <input type="text" name="kra_pin" id="edit_emp_kra" class="form-control uppercase">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">NSSF</label>
                            <input type="text" name="nssf_no" id="edit_emp_nssf" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">SHA</label>
                            <input type="text" name="sha_no" id="edit_emp_sha" class="form-control">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 rounded-pill fw-bold">Update Employee</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: UPDATE ADMIN -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Update Admin Account</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_admin">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="full_name" id="edit_admin_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email</label>
                        <input type="email" name="email" id="edit_admin_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Role</label>
                        <select name="role_id" id="edit_admin_role" class="form-select" required>
                            <?php foreach($defined_roles as $id => $val) echo "<option value='$id'>{$val['label']}</option>"; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reset Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <button class="btn btn-primary w-100 rounded-pill fw-bold">Update Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

        </div><!-- End container-fluid -->
    </div><!-- End main-content -->
</div><!-- End d-flex -->

<?php $layout->footer(); ?>
