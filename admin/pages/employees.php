<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

// manager/employees.php
// Operations Manager - Staff & HR Management

if (session_status() === PHP_SESSION_NONE) session_start();

// Enforce Manager Role
// Enforce Permission
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['full_name'] ?? 'Manager';
$db = $conn;

// ---------------------------------------------------------
// 1. HANDLE ACTIONS
// ---------------------------------------------------------
// ---------------------------------------------------------
// 1. HANDLE ACTIONS
// ---------------------------------------------------------
require_once __DIR__ . '/../../inc/EmployeeService.php';
$svc = new EmployeeService($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. ADD EMPLOYEE
    if (isset($_POST['action']) && $_POST['action'] === 'add_employee') {
        $name   = trim($_POST['full_name']);
        $nid    = trim($_POST['national_id']);
        $phone  = trim($_POST['phone']);
        $role   = trim($_POST['job_title']);
        $grade_id = intval($_POST['grade_id']);
        $p_email = trim($_POST['personal_email']);
        
        // Financials (Can be overridden from grade defaults)
        $salary = floatval($_POST['salary']);
        
        // Tax & Bank
        $kra    = strtoupper(trim($_POST['kra_pin']));
        $nssf   = trim($_POST['nssf_no']);
        $nhif   = trim($_POST['nhif_no']);
        $bank   = trim($_POST['bank_name']);
        $acc    = trim($_POST['bank_account']);
        $date   = $_POST['hire_date'];

        if (empty($name) || empty($nid)) {
            flash_set("Name and ID are required.", 'error');
        } else {
            // 1. Generate Identity
            $emp_no = $svc->generateEmployeeNo();
            $c_email = $svc->generateEmail($name);
            
            // 2. Insert Employee Record
            $sql = "INSERT INTO employees 
                    (employee_no, full_name, national_id, phone, company_email, personal_email, 
                     job_title, grade_id, salary, 
                     kra_pin, nssf_no, nhif_no, bank_name, bank_account, 
                     hire_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssssssidssssss", 
                $emp_no, $name, $nid, $phone, $c_email, $p_email, 
                $role, $grade_id, $salary, 
                $kra, $nssf, $nhif, $bank, $acc, $date
            );
            
            if ($stmt->execute()) {
                $emp_id = $stmt->insert_id;
                
                // 3. Create System User (Login)
                $role_id = $svc->getRoleIdForTitle($role);
                $user_data = [
                    'employee_no' => $emp_no,
                    'company_email' => $c_email,
                    'full_name' => $name
                ];
                $admin_id = $svc->createSystemUser($user_data, $role_id);
                
                if ($admin_id) {
                    $db->query("UPDATE employees SET admin_id = $admin_id WHERE employee_id = $emp_id");
                }

                // Audit Log
                $log_details = "Onboarded $name ($emp_no) as $role. System Access Granted.";
                $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'add_employee', '$log_details', '{$_SERVER['REMOTE_ADDR']}')");
                flash_set("Employee onboarded successfully. ID: $emp_no, Email: $c_email", 'success');
            } else {
                flash_set("Error: " . $stmt->error, 'error');
            }
        }
    }

    // B. UPDATE EMPLOYEE
    if (isset($_POST['action']) && $_POST['action'] === 'update_employee') {
        $emp_id = intval($_POST['employee_id']);
        $name   = trim($_POST['full_name']);
        $phone  = trim($_POST['phone']);
        $role   = trim($_POST['job_title']);
        $salary = floatval($_POST['salary']);
        $status = $_POST['status'];
        // Update basic fields for now 
        // (Full update logic would mirror add, but sticking to prompt Constraints)
        
        $stmt = $db->prepare("UPDATE employees SET full_name=?, phone=?, job_title=?, salary=?, status=? WHERE employee_id=?");
        $stmt->bind_param("sssdsi", $name, $phone, $role, $salary, $status, $emp_id);

        if ($stmt->execute()) {
            // Sync with admins table if this employee is linked
            $db->query("UPDATE admins SET full_name = '$name' WHERE admin_id = (SELECT admin_id FROM employees WHERE employee_id = $emp_id)");
            
            $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'update_employee', 'Updated Employee #$emp_id', '{$_SERVER['REMOTE_ADDR']}')");
            flash_set("Employee record updated.", 'success');
        }
    }
}

// ---------------------------------------------------------
// 2. FETCH DATA
// ---------------------------------------------------------
$filter_status = $_GET['status'] ?? 'active';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = "";

if ($filter_status !== 'all') {
    $where[] = "status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($search) {
    $where[] = "(full_name LIKE ? OR national_id LIKE ? OR job_title LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= "sss";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT e.*, r.name as admin_role, sg.grade_name 
        FROM employees e 
        LEFT JOIN admins a ON e.admin_id = a.admin_id 
        LEFT JOIN roles r ON a.role_id = r.id 
        LEFT JOIN salary_grades sg ON e.grade_id = sg.id 
        $where_sql 
        ORDER BY e.full_name ASC";

$stmt = $db->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$staff_res = $stmt->get_result();

// Fetch Grades for Dropdown
$grades = [];
$g_q = $db->query("SELECT * FROM salary_grades ORDER BY basic_salary DESC");
while ($g = $g_q->fetch_assoc()) {
    $grades[] = $g;
}

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    $staff_res->data_seek(0);
    while($row = $staff_res->fetch_assoc()) {
        $role_display = $row['job_title'];
        if ($row['admin_role']) {
            $role_display .= " (" . $row['admin_role'] . ")";
        }
        $data[] = [
            'Name' => $row['full_name'],
            'ID No' => $row['national_id'],
            'Role' => $role_display,
            'Phone' => $row['phone'],
            'Salary' => number_format((float)$row['salary'], 2),
            'Status' => ucfirst($row['status']),
            'Hired' => date('d-M-Y', strtotime($row['hire_date']))
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Employee Directory',
        'module' => 'HR Management',
        'headers' => ['Name', 'ID No', 'Role', 'Phone', 'Salary', 'Status', 'Hired']
    ]);
    exit;
}

// KPIs
$total_staff = $db->query("SELECT COUNT(*) as c FROM employees")->fetch_assoc()['c'];
$monthly_payroll = $db->query("SELECT SUM(salary) as s FROM employees WHERE status='active'")->fetch_assoc()['s'];
$drivers_count = $db->query("SELECT COUNT(*) as c FROM employees WHERE job_title LIKE '%Driver%' AND status='active'")->fetch_assoc()['c'];

function getInitials($name) { 
    $words = explode(" ", $name);
    $acronym = "";
    foreach ($words as $w) {
        $acronym .= mb_substr($w, 0, 1);
    }
    return strtoupper(substr($acronym, 0, 2)); 
}
function ksh($v, $d = 2) { return number_format((float)($v ?? 0), $d); }

$pageTitle = "Staff Management";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= $pageTitle ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Emerald Theme */
            --primary-hue: 158; 
            --primary-sat: 64%; 
            --primary-lig: 52%;
            --color-primary: hsl(var(--primary-hue), var(--primary-sat), var(--primary-lig));
            --color-primary-dark: hsl(var(--primary-hue), var(--primary-sat), 25%);
            --color-primary-light: hsl(var(--primary-hue), var(--primary-sat), 94%);
            
            --bg-app: #f0f2f5;
            --text-main: #1e293b;
            --text-muted: #64748b;
            
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: 1px solid rgba(255, 255, 255, 0.6);
            --glass-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            
            --card-radius: 16px;
        }

        [data-bs-theme="dark"] {
            --bg-app: #0f172a;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: 1px solid rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-app);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* Glass Components */
        .hd-glass {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: var(--glass-border);
            border-radius: var(--card-radius);
            box-shadow: var(--glass-shadow);
        }

        /* Layout */
        .main-content-wrapper { margin-left: 260px; transition: 0.3s; min-height: 100vh; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

        /* Custom Buttons */
        .btn-primary {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: #fff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        .btn-primary:hover {
            background-color: var(--color-primary-dark);
            border-color: var(--color-primary-dark);
        }

        /* Stats Cards */
        .stat-icon {
            width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px; font-size: 1.5rem;
        }

        /* Avatar */
        .avatar-circle {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            background: var(--color-primary-light);
            color: var(--color-primary-dark);
        }

        /* Table Styling */
        .table-custom tr { transition: all 0.2s ease; }
        .table-custom tr:hover td { background-color: rgba(var(--primary-hue), 185, 129, 0.05); }
        .badge-soft { padding: 0.5em 0.85em; border-radius: 6px; font-weight: 500; font-size: 0.75rem; letter-spacing: 0.3px; }
        
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-leave { background: #fef9c3; color: #854d0e; }
        .badge-terminated { background: #fee2e2; color: #991b1b; }

        /* Action Dropdown */
        .dropdown-menu-glass {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-gradient">Staff Management</h3>
                    <p class="text-muted small mb-0">Overview of HR, Payroll, and Driver Allocation.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-dark dropdown-toggle rounded-pill" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu shadow-sm">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export PDF</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print Report</a></li>
                        </ul>
                    </div>
                    <span class="badge bg-dark bg-opacity-10 text-dark border px-3 py-2 rounded-pill">
                        <i class="bi bi-calendar3 me-2"></i> <?= date('F d, Y') ?>
                    </span>
                </div>
            </div>

            <?php 
            require_once __DIR__ . '/../inc/hr_nav.php';
            flash_render(); 
            ?>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="hd-glass p-4 h-100 d-flex align-items-center justify-content-between position-relative overflow-hidden">
                        <div class="position-relative z-1">
                            <h6 class="text-muted text-uppercase fw-semibold fs-7 mb-2">Total Employees</h6>
                            <h2 class="fw-bold mb-0 text-dark"><?= $total_staff ?></h2>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary z-1">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hd-glass p-4 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold fs-7 mb-2">Monthly Payroll</h6>
                            <h2 class="fw-bold mb-0 text-success">
                                <small class="fs-6 text-muted fw-normal">KES</small> <?= number_format((float)($monthly_payroll ?? 0)) ?>
                            </h2>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hd-glass p-4 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold fs-7 mb-2">Active Drivers</h6>
                            <h2 class="fw-bold mb-0 text-warning"><?= $drivers_count ?></h2>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-truck-front-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hd-glass p-3 mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-6">
                        <div class="nav nav-pills gap-2">
                            <a href="?status=active" class="btn btn-sm rounded-pill <?= $filter_status=='active'?'btn-dark shadow-sm':'btn-light bg-transparent border' ?>">Active</a>
                            <a href="?status=on_leave" class="btn btn-sm rounded-pill <?= $filter_status=='on_leave'?'btn-dark shadow-sm':'btn-light bg-transparent border' ?>">On Leave</a>
                            <a href="?status=terminated" class="btn btn-sm rounded-pill <?= $filter_status=='terminated'?'btn-dark shadow-sm':'btn-light bg-transparent border' ?>">Terminated</a>
                            <a href="?status=all" class="btn btn-sm rounded-pill <?= $filter_status=='all'?'btn-dark shadow-sm':'btn-light bg-transparent border' ?>">View All</a>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="d-flex gap-2 justify-content-lg-end">
                            <form class="position-relative">
                                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                                <input type="text" name="q" class="form-control ps-5 rounded-pill bg-light border-0" placeholder="Search name, ID..." value="<?= htmlspecialchars($search) ?>">
                            </form>
                            <button class="btn btn-primary rounded-pill px-4 fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                <i class="bi bi-plus-lg"></i> Hire Staff
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hd-glass overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0" style="background:transparent;">
                        <thead class="bg-light bg-opacity-50 small text-uppercase text-muted fw-bold">
                            <tr>
                                <th class="ps-4 py-3">Employee</th>
                                <th class="py-3">Role</th>
                                <th class="py-3">Contact</th>
                                <th class="py-3">Salary</th>
                                <th class="py-3">Status</th>
                                <th class="text-end pe-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            <?php if($staff_res->num_rows === 0): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-person-x fs-1 d-block mb-2 opacity-25"></i>
                                    No employees found matching criteria.
                                </td></tr>
                            <?php else: while($emp = $staff_res->fetch_assoc()): 
                                $badge_cls = match($emp['status']) {
                                    'active' => 'badge-active',
                                    'on_leave' => 'badge-leave',
                                    'terminated' => 'badge-terminated',
                                    default => 'bg-secondary text-white'
                                };
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-circle">
                                                <?= getInitials($emp['full_name']) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($emp['full_name']) ?></div>
                                                <div class="small text-muted font-monospace text-uppercase" style="font-size: 0.75rem;">
                                                    <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($emp['employee_no'] ?? 'N/A') ?>
                                                </div>
                                                <div class="small text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($emp['company_email'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-white text-dark border fw-normal shadow-sm mb-1 align-self-start">
                                                <?= htmlspecialchars($emp['admin_role'] ? ucwords($emp['admin_role']) : $emp['job_title']) ?>
                                            </span>
                                            <?php if(!empty($emp['grade_id'])): ?>
                                                <span class="badge bg-light text-muted border px-2 mt-1" style="font-size: 0.65rem;">
                                                    Grade: <?= htmlspecialchars($emp['grade_name'] ?? $emp['grade_id']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <div><?= htmlspecialchars($emp['phone']) ?></div>
                                        <div class="text-muted opacity-75" style="font-size: 0.7rem;">ID: <?= htmlspecialchars($emp['national_id']) ?></div>
                                    </td>
                                    <td class="fw-medium font-monospace text-dark">
                                        <?= ksh($emp['salary']) ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <span class="badge badge-soft <?= $badge_cls ?>">
                                                <?= str_replace('_', ' ', ucfirst($emp['status'])) ?>
                                            </span>
                                            <?php if($emp['admin_role']): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border-0 rounded-pill px-2" style="font-size: 0.6rem;">
                                                    <i class="bi bi-shield-check"></i> System User
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light bg-transparent border-0 text-muted" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-glass border-0">
                                                <li><h6 class="dropdown-header small text-uppercase">Manage</h6></li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="openEditModal(<?= htmlspecialchars(json_encode($emp)) ?>)">
                                                        <i class="bi bi-pencil-square me-2 text-primary"></i> Edit Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="payroll.php?employee_id=<?= $emp['employee_id'] ?>">
                                                        <i class="bi bi-cash-stack me-2 text-success"></i> View Payroll
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-trash me-2"></i> Terminate</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
<?php $layout->footer(); ?>
        </div>
        
    </div>
</div>

<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold">Hire New Employee</h5>
                    <p class="small text-muted mb-0">Complete onboarding to generate System ID & Access.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_employee">
                    
                    <ul class="nav nav-pills nav-fill mb-4 gap-2" id="onboardTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active small fw-bold rounded-pill border" id="tab-basic" data-bs-toggle="pill" data-bs-target="#pane-basic" type="button">1. Personal Info</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link small fw-bold rounded-pill border" id="tab-role" data-bs-toggle="pill" data-bs-target="#pane-role" type="button">2. Role & Pay</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link small fw-bold rounded-pill border" id="tab-compliance" data-bs-toggle="pill" data-bs-target="#pane-compliance" type="button">3. Compliance</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- 1. PERSONAL -->
                        <div class="tab-pane fade show active" id="pane-basic">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Full Name</label>
                                    <input type="text" name="full_name" id="new_name" class="form-control bg-light border-0" required placeholder="e.g. John Kamau Doe">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold text-uppercase">National ID</label>
                                    <input type="text" name="national_id" class="form-control bg-light border-0" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Phone</label>
                                    <input type="text" name="phone" class="form-control bg-light border-0" required placeholder="07...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Personal Email</label>
                                    <input type="email" name="personal_email" class="form-control bg-light border-0" placeholder="@gmail.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Hire Date</label>
                                    <input type="date" name="hire_date" class="form-control bg-light border-0" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- 2. ROLE & PAY -->
                        <div class="tab-pane fade" id="pane-role">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Job Title</label>
                                    <select name="job_title" class="form-select bg-light border-0" required>
                                        <option value="Manager">Manager</option>
                                        <option value="Accountant">Accountant</option>
                                        <option value="Loans Officer">Loans Officer</option>
                                        <option value="Driver">Driver</option>
                                        <option value="Conductor">Conductor</option>
                                        <option value="Office Clerk">Office Clerk</option>
                                        <option value="Security">Security Guard</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Salary Grade</label>
                                    <select name="grade_id" id="grade_select" class="form-select bg-light border-0" required onchange="updateSalary()">
                                        <option value="" disabled selected>Select Grade...</option>
                                        <?php foreach($grades as $g): ?>
                                            <option value="<?= $g['id'] ?>" data-salary="<?= $g['basic_salary'] ?>">
                                                <?= htmlspecialchars($g['grade_name']) ?> (KES <?= number_format($g['basic_salary']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success-subtle">
                                        <label class="form-label small text-success fw-bold text-uppercase">Basic Salary (KES)</label>
                                        <input type="number" name="salary" id="basic_salary_input" class="form-control bg-white border-0 fw-bold text-success fs-5" step="0.01" required>
                                        <div class="form-text small">Auto-filled from Grade. Can be overridden.</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info d-flex align-items-center mb-0 py-2">
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        <div class="small">System User & Email will be auto-generated upon saving.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. COMPLIANCE -->
                        <div class="tab-pane fade" id="pane-compliance">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold text-uppercase">KRA PIN</label>
                                    <input type="text" name="kra_pin" class="form-control bg-light border-0" placeholder="A00..." required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold text-uppercase">NSSF No</label>
                                    <input type="text" name="nssf_no" class="form-control bg-light border-0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold text-uppercase">NHIF No</label>
                                    <input type="text" name="nhif_no" class="form-control bg-light border-0">
                                </div>
                                <div class="col-md-12"><hr class="my-2"></div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Bank Name</label>
                                    <input type="text" name="bank_name" class="form-control bg-light border-0" placeholder="e.g. Equity Bank">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold text-uppercase">Account Number</label>
                                    <input type="text" name="bank_account" class="form-control bg-light border-0">
                                </div>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow-sm">
                                    <i class="bi bi-person-check-fill me-2"></i> Complete Onboarding
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function updateSalary() {
        const select = document.getElementById('grade_select');
        const salaryInput = document.getElementById('basic_salary_input');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption && selectedOption.dataset.salary) {
            salaryInput.value = selectedOption.dataset.salary;
        }
    }
</script>

<div class="modal fade" id="editStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Update Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_employee">
                    <input type="hidden" name="employee_id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Full Name</label>
                        <input type="text" name="full_name" id="edit_name" class="form-control bg-light border-0" required>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold text-uppercase">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control bg-light border-0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold text-uppercase">Role</label>
                            <input type="text" name="job_title" id="edit_role" class="form-control bg-light border-0" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold text-uppercase">Salary</label>
                            <input type="number" name="salary" id="edit_salary" class="form-control bg-light border-0" step="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold text-uppercase">Status</label>
                            <select name="status" id="edit_status" class="form-select bg-light border-0">
                                <option value="active">Active</option>
                                <option value="on_leave">On Leave</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success rounded-pill py-2 fw-bold shadow-sm">
                            <i class="bi bi-arrow-repeat me-2"></i> Update Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openEditModal(emp) {
        document.getElementById('edit_id').value = emp.employee_id;
        document.getElementById('edit_name').value = emp.full_name;
        document.getElementById('edit_phone').value = emp.phone;
        document.getElementById('edit_role').value = emp.job_title;
        document.getElementById('edit_salary').value = emp.salary;
        document.getElementById('edit_status').value = emp.status;
        
        new bootstrap.Modal(document.getElementById('editStaffModal')).show();
    }
</script>
</body>
</html>




