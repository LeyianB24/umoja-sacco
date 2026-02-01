<?php
// superadmin/manage_admins.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php'; 
require_once __DIR__ . '/../inc/functions.php';

// 1. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$db = $conn;
$current_admin_id = $_SESSION['admin_id'] ?? 0;

$defined_roles = [
    'superadmin' => ['label' => 'Superadmin', 'color' => 'bg-danger'],
    'manager'    => ['label' => 'Manager',    'color' => 'bg-success'],
    'accountant' => ['label' => 'Accountant', 'color' => 'bg-primary'],
    'admin'      => ['label' => 'IT Admin',   'color' => 'bg-secondary']
];

function setFlash($msg, $type = 'success') {
    flash_set($msg, $type);
}

function time_ago($datetime) {
    if (!$datetime || $datetime == '0000-00-00 00:00:00') return 'Never';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    $intervals = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($intervals as $secs => $str) {
        $d = $diff / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . $str . ($r > 1 ? 's' : '') . ' ago';
        }
    }
    return date('M d, Y', $time);
}

function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) if(!empty($w)) $initials .= strtoupper($w[0]);
    return substr($initials, 0, 2) ?: '??';
}

// 2. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    // A. ADD ADMIN
    if (isset($_POST['add_admin'])) {
        $fullname = trim($_POST['full_name']);
        $email    = trim($_POST['email']);
        $username = trim($_POST['username']);
        $role     = $_POST['role'];
        $password = $_POST['password'];

        $stmt = $db->prepare("SELECT admin_id FROM admins WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            setFlash("Email or Username already exists.", "danger");
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO admins (full_name, email, username, role, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $fullname, $email, $username, $role, $hashed);
            if ($stmt->execute()) setFlash("New admin registered successfully.");
            else setFlash("Error: " . $db->error, "danger");
        }
    }

    // B. EDIT ADMIN
    if (isset($_POST['edit_admin'])) {
        $id = intval($_POST['admin_id']);
        $fullname = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];

        if (!empty($_POST['password'])) {
            $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE admins SET full_name=?, email=?, role=?, password=? WHERE admin_id=?");
            $stmt->bind_param("ssssi", $fullname, $email, $role, $hashed, $id);
        } else {
            $stmt = $db->prepare("UPDATE admins SET full_name=?, email=?, role=? WHERE admin_id=?");
            $stmt->bind_param("sssi", $fullname, $email, $role, $id);
        }
        if($stmt->execute()) setFlash("Admin updated successfully.");
    }
    
    header("Location: manage_admins.php");
    exit;
}

// 3. Handle GET Actions
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    if ($del_id !== $current_admin_id) {
        $stmt = $db->prepare("DELETE FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        setFlash("Admin deleted permanently.", "warning");
    }
    header("Location: manage_admins.php"); exit;
}

$res = $db->query("SELECT * FROM admins ORDER BY created_at DESC");
if (!$res) die("Query failed: " . $db->error);
echo "<div class='alert alert-info py-1 mb-0' style='position:fixed; bottom:10px; right:10px; z-index:9999;'>PHP Admins Found: " . $res->num_rows . "</div>"; 
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>Manage Admins | Umoja Drivers Sacco</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="superadmin-body">

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
            <div class="row mb-4 align-items-center">
                <div class="col-md-5">
                    <h3 class="fw-bold mb-0 text-dark">Administrative Staff</h3>
                    <p class="text-muted mb-0 small mt-1">Manage system users, roles, and access levels.</p>
                </div>
                <div class="col-md-7 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
                    <div class="input-group" style="max-width: 250px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="adminSearch" class="form-control border-start-0 ps-0" placeholder="Search admins...">
                    </div>
                    <button class="btn btn-success shadow-sm fw-bold px-4 rounded-3" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                        <i class="bi bi-person-plus-fill me-2"></i>New Admin
                    </button>
                </div>
            </div>

            <?php flash_render(); ?>

            <div class="iq-card">
                <div class="iq-card-header d-flex justify-content-between p-4 bg-white border-bottom">
                    <h5 class="fw-bold mb-0">System Administrators</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-filter"></i> Filter Role
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" id="roleFilter">
                            <li><a class="dropdown-item active" href="#" data-filter="all">All Roles</a></li>
                            <?php foreach($defined_roles as $k => $v): ?>
                                <li><a class="dropdown-item" href="#" data-filter="<?= $v['label'] ?>"><?= $v['label'] ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Profile Info</th>
                                    <th>Role</th>
                                    <th>Contact</th>
                                    <th>Created</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($res->num_rows > 0): ?>
                                    <?php while($row = $res->fetch_assoc()): 
                                        $roleKey = $row['role'] ?? 'admin';
                                        $roleInfo = $defined_roles[$roleKey] ?? ['label' => $roleKey, 'color' => 'bg-secondary'];
                                        $isMe = ($row['admin_id'] == $current_admin_id);
                                    ?>
                                    <tr class="admin-row" data-role="<?= $roleInfo['label'] ?>">
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-circle <?= $roleInfo['color'] ?> shadow-sm" style="width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold;">
                                                    <?= getInitials($row['full_name']) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark admin-name"><?= htmlspecialchars($row['full_name']) ?> 
                                                        <?php if($isMe): ?><span class="badge bg-dark ms-1" style="font-size: 9px;">YOU</span><?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted admin-user">@<?= htmlspecialchars($row['username']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $roleInfo['color'] ?> bg-opacity-10 text-<?= str_replace('bg-','',$roleInfo['color']) ?> px-3 rounded-pill">
                                                <?= $roleInfo['label'] ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <i class="bi bi-envelope me-1"></i> <span class="admin-email"><?= htmlspecialchars($row['email']) ?></span>
                                        </td>
                                        <td class="small text-muted">
                                            <?= time_ago($row['created_at']) ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-light text-primary rounded-circle" 
                                                        title="Edit User" onclick='openEditModal(<?= json_encode($row) ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <?php if(!$isMe): ?>
                                                    <a href="?delete_id=<?= $row['admin_id'] ?>" 
                                                       class="btn btn-sm btn-light text-danger rounded-circle ms-1" 
                                                       onclick="return confirm('Delete this admin?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">No admins found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Register New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <?= csrf_field() ?>
                <input type="hidden" name="add_admin" value="1">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Role</label>
                    <select name="role" class="form-select">
                        <?php foreach($defined_roles as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Create Account</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Administrator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_admin" value="1">
                <input type="hidden" name="admin_id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" name="full_name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Role</label>
                    <select name="role" id="edit_role" class="form-select">
                        <?php foreach($defined_roles as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">New Password (Empty to keep current)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Search
    document.getElementById('adminSearch').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.admin-row').forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(val) ? '' : 'none';
        });
    });

    // Filter
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

    // Edit
    function openEditModal(data) {
        document.getElementById('edit_id').value = data.admin_id;
        document.getElementById('edit_name').value = data.full_name;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_role').value = data.role;
        new bootstrap.Modal(document.getElementById('editAdminModal')).show();
    }
</script>
</body>
</html>