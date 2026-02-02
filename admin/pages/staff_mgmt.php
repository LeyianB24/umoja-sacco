<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

// superadmin/staff_mgmt.php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Security Check
require_admin();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
// require_permission(); // Relaxed: Allow all internal staff to view the admin directory

$db = $conn;
$current_admin_id = $_SESSION['admin_id'] ?? 0;

// 1. Fetch Dynamic Roles
$roles_res = $db->query("SELECT * FROM roles ORDER BY name ASC");
$defined_roles = [];
while($r = $roles_res->fetch_assoc()) {
    $defined_roles[$r['id']] = [
        'label' => ucwords($r['name']),
        'name'  => strtolower($r['name']),
        'color' => match(strtolower($r['name'])) {
            'superadmin' => 'bg-danger',
            'manager'    => 'bg-success',
            'accountant' => 'bg-primary',
            'clerk'      => 'bg-info',
            default      => 'bg-secondary'
        }
    ];
}

function setFlash($msg, $type = 'success') {
    flash_set($msg, $type);
}

function getInitials($name) {
    if (!$name) return '??';
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) if(!empty($w)) $initials .= strtoupper($w[0]);
    return substr($initials, 0, 2) ?: '??';
}

// 2. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only Superadmins or those with system_settings permission can modify admin staff
    if ($_SESSION['role_id'] != 1 && !can('system_settings')) {
        flash_set("Access Denied: You do not have permission to modify administrative staff.", "danger");
        header("Location: staff_mgmt.php"); exit;
    }
    verify_csrf_token();
    
    // A. ADD ADMIN
    if (isset($_POST['add_admin'])) {
        $fullname = trim($_POST['full_name']);
        $email    = trim($_POST['email']);
        $username = trim($_POST['username']);
        $role_id  = intval($_POST['role_id']);
        $password = $_POST['password'];

        $stmt = $db->prepare("SELECT admin_id FROM admins WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            setFlash("Email or Username already exists.", "danger");
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO admins (full_name, email, username, role_id, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssis", $fullname, $email, $username, $role_id, $hashed);
            if ($stmt->execute()) setFlash("New admin registered successfully.");
            else setFlash("Error: " . $db->error, "danger");
        }
    }

    // C. ADD ROLE (Quick Create)
    if (isset($_POST['add_role'])) {
        $rolename = trim(strtolower($_POST['role_name']));
        if(!empty($rolename)) {
            $stmt = $db->prepare("INSERT IGNORE INTO roles (name) VALUES (?)");
            $stmt->bind_param("s", $rolename);
            $stmt->execute();
            setFlash("New Role '$rolename' created. Configure it in Role Matrix.");
        }
    }

    // B. EDIT ADMIN
    if (isset($_POST['edit_admin'])) {
        $id = intval($_POST['admin_id']);
        $fullname = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role_id = intval($_POST['role_id']);

        if (!empty($_POST['password'])) {
            $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE admins SET full_name=?, email=?, role_id=?, password=? WHERE admin_id=?");
            $stmt->bind_param("ssisi", $fullname, $email, $role_id, $hashed, $id);
        } else {
            $stmt = $db->prepare("UPDATE admins SET full_name=?, email=?, role_id=? WHERE admin_id=?");
            $stmt->bind_param("ssii", $fullname, $email, $role_id, $id);
        }
        if($stmt->execute()) setFlash("Admin updated successfully.");
    }
    
    header("Location: staff_mgmt.php");
    exit;
}

// 3. Handle GET Actions
if (isset($_GET['delete_id'])) {
    if ($_SESSION['role_id'] != 1 && !can('system_settings')) {
        flash_set("Access Denied: You do not have permission to delete administrative staff.", "danger");
        header("Location: staff_mgmt.php"); exit;
    }
    $del_id = intval($_GET['delete_id']);
    if ($del_id !== $current_admin_id) {
        $stmt = $db->prepare("DELETE FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        setFlash("Admin deleted permanently.", "warning");
    }
    header("Location: staff_mgmt.php"); exit;
}

// Stats for dashboard
$total_admins = $db->query("SELECT COUNT(*) as count FROM admins")->fetch_assoc()['count'];
$total_roles = count($defined_roles);
$new_admins_today = $db->query("SELECT COUNT(*) as count FROM admins WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];

$admin_list_res = $db->query("SELECT * FROM admins ORDER BY created_at DESC");
if (!$admin_list_res) die("Query failed: " . $db->error);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>Manage Admins | Umoja Drivers Sacco</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
    <script>
        (() => {
            const saved = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', saved);
        })();
    </script>
    <style>
        :root {
            --primary-forest: #0F2E25;
            --accent-lime: #D0F35D;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-content-wrapper { transition: all 0.3s ease; }
        .admin-row:hover { background-color: rgba(15, 46, 37, 0.02) !important; }
        .avatar-circle {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-forest { background: var(--primary-forest); color: white; border: none; transition: 0.3s; }
        .btn-forest:hover { background: #164e3f; color: white; transform: translateY(-1px); }
        .btn-lime { background: var(--accent-lime); color: var(--primary-forest); border: none; font-weight: 600; }
        .btn-lime:hover { background: #c0e04b; transform: translateY(-1px); }
        
        /* Glassmorphism overrides */
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body class="bg-body">

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper" style="margin-left: 280px;">
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <div class="container-fluid py-4">
            <!-- Header Section -->
            <div class="row mb-4 align-items-center">
                <div class="col-md-6">
                    <h2 class="fw-800 text-forest mb-1">Administrative Staff</h2>
                    <p class="text-secondary mb-0">Manage system administrators, roles, and access credentials.</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                    <?php if($_SESSION['role_id'] == 1 || can('system_settings')): ?>
                    <button class="btn btn-outline-primary shadow-sm fw-bold px-4 rounded-3" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="bi bi-shield-lock me-2"></i>New Role
                    </button>
                    <button class="btn btn-forest shadow-sm fw-bold px-4 rounded-3" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                        <i class="bi bi-person-plus-fill me-2"></i>Register Admin
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card fade-in">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Total Administrators</div>
                                <div class="stat-value"><?= $total_admins ?></div>
                            </div>
                            <div class="stat-icon bg-forest text-lime">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card fade-in" style="animation-delay: 0.1s;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Active Roles</div>
                                <div class="stat-value"><?= $total_roles ?></div>
                            </div>
                            <div class="stat-icon bg-lime text-forest">
                                <i class="bi bi-shield-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card fade-in" style="animation-delay: 0.2s;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Added Today</div>
                                <div class="stat-value"><?= $new_admins_today ?></div>
                            </div>
                            <div class="stat-icon bg-info text-white">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php flash_render(); ?>

            <!-- Main Table Card -->
            <div class="glass-card p-0 overflow-hidden shadow-sm-custom slide-in">
                <div class="d-flex justify-content-between align-items-center p-4 border-bottom">
                    <h5 class="fw-bold mb-0 text-forest">System Users</h5>
                    <div class="d-flex gap-2">
                        <div class="input-group input-group-sm" style="max-width: 250px;">
                            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="adminSearch" class="form-control border-start-0" placeholder="Search staff...">
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-funnel"></i> Role
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" id="roleFilter">
                                <li><a class="dropdown-item active" href="#" data-filter="all">All Roles</a></li>
                                <?php foreach($defined_roles as $id => $v): ?>
                                    <li><a class="dropdown-item" href="#" data-filter="<?= $v['label'] ?>"><?= $v['label'] ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Administrator</th>
                                <th>Role / Access</th>
                                <th>Contact Info</th>
                                <th>Joined</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($admin_list_res->num_rows > 0): ?>
                                <?php while($row = $admin_list_res->fetch_assoc()): 
                                    $rid = $row['role_id'];
                                    $roleInfo = $defined_roles[$rid] ?? ['label' => 'Unknown', 'color' => 'bg-secondary'];
                                    $isMe = ($row['admin_id'] == $current_admin_id);
                                ?>
                                <tr class="admin-row" data-role="<?= $roleInfo['label'] ?>">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-circle <?= $roleInfo['color'] ?>">
                                                <?= getInitials($row['full_name']) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['full_name']) ?> 
                                                    <?php if($isMe): ?><span class="badge bg-forest text-lime ms-1" style="font-size: 9px; vertical-align: middle;">ME</span><?php endif; ?>
                                                </div>
                                                <div class="small text-muted">@<?= htmlspecialchars($row['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill <?= $roleInfo['color'] ?> bg-opacity-10 text-<?= str_replace('bg-','',$roleInfo['color']) ?>">
                                            <i class="bi bi-shield-fill-check me-1"></i> <?= $roleInfo['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small fw-500 text-dark"><i class="bi bi-envelope-at me-1"></i> <?= htmlspecialchars($row['email']) ?></div>
                                    </td>
                                    <td class="small text-muted">
                                        <i class="bi bi-clock-history me-1"></i> <?= time_ago($row['created_at']) ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <?php if($_SESSION['role_id'] == 1 || can('system_settings')): ?>
                                            <button class="btn btn-sm btn-light text-forest rounded-3 me-2" 
                                                    title="Edit Account" onclick='openEditModal(<?= json_encode($row) ?>)'>
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <?php if(!$isMe): ?>
                                                <a href="?delete_id=<?= $row['admin_id'] ?>" 
                                                   class="btn btn-sm btn-light text-danger rounded-3" 
                                                   onclick="return confirm('Are you sure you want to permanently remove this administrator?')">
                                                    <i class="bi bi-trash3"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">View Only</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-person-x-fill display-4"></i>
                                            <p class="mt-2">No registered administrators found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php $layout->footer(); ?>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST">
            <?= csrf_field() ?>
            <div class="modal-header bg-forest text-white">
                <h5 class="modal-title fw-bold">Create New Admin Type</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="add_role" value="1">
                <div class="mb-4">
                    <label class="form-label">Role Title</label>
                    <input type="text" name="role_name" class="form-control form-control-lg" placeholder="e.g. Finance Manager" required>
                    <p class="small text-muted mt-2">Roles define general access categories. Specific permissions are managed in the <strong>Role Matrix</strong>.</p>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-forest py-2 fw-bold">Create Role</button>
                    <a href="roles.php" class="btn btn-light py-2 fw-bold">Open Role Matrix</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST">
            <?= csrf_field() ?>
            <div class="modal-header bg-forest text-white">
                <h5 class="modal-title fw-bold">Register New Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="add_admin" value="1">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="john@umoja.com" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="jdoe" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Assigned Role</label>
                    <select name="role_id" class="form-select">
                        <?php foreach($defined_roles as $id => $v): ?>
                            <option value="<?= $id ?>"><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label">Security Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-forest w-100 py-2 fw-bold">Create Administrator</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST">
            <?= csrf_field() ?>
            <div class="modal-header bg-forest text-white">
                <h5 class="modal-title fw-bold">Edit Administrator</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="edit_admin" value="1">
                <input type="hidden" name="admin_id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Assigned Role</label>
                    <select name="role_id" id="edit_role" class="form-select">
                        <?php foreach($defined_roles as $id => $v): ?>
                            <option value="<?= $id ?>"><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted">Reset Password (Leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <button type="submit" class="btn btn-forest w-100 py-2 fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Search Functionality
    document.getElementById('adminSearch').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.admin-row').forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(val) ? '' : 'none';
        });
    });

    // Filtering by Role
    document.querySelectorAll('#roleFilter .dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            let filter = this.getAttribute('data-filter');
            document.querySelectorAll('#roleFilter .dropdown-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.admin-row').forEach(row => {
                if(filter === 'all') row.style.display = '';
                else row.style.display = (row.getAttribute('data-role') === filter) ? '' : 'none';
            });
        });
    });

    // Populate Edit Modal
    function openEditModal(data) {
        document.getElementById('edit_id').value = data.admin_id;
        document.getElementById('edit_name').value = data.full_name;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_role').value = data.role_id;
        new bootstrap.Modal(document.getElementById('editAdminModal')).show();
    }
</script>
</body>
</html>






