<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

/**
 * admin/users.php
 * Dynamic Admin/Staff Management with RBAC Integration
 * Umoja Sacco V17
 */

require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_superadmin();
?>
<?php flash_render(); ?>
    <style>
        /* Page-specific overrides */
        .avatar-box { width: 45px; height: 45px; border-radius: 14px; background: rgba(15, 46, 37, 0.05); color: var(--forest); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; }
        .role-badge { background: rgba(208, 243, 93, 0.15); color: var(--forest-mid); font-weight: 700; padding: 6px 12px; border-radius: 10px; font-size: 0.75rem; }
        .iq-card { background: white; border-radius: 20px; border: 1px solid var(--border-color); overflow: hidden; }
    </style>
<?php
// 1. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    
    // Add Admin
    if ($_POST['action'] === 'add_user') {
        $fullname = trim($_POST['full_name']);
        $email    = trim($_POST['email']);
        $username = trim($_POST['username']);
        $role_id  = intval($_POST['role_id']);
        $password = trim($_POST['password']);

        // Check uniqueness
        $check = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? OR username = ?");
        $check->bind_param("ss", $email, $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            flash_set("Email or Username already exists.", "danger");
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (full_name, email, username, role_id, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssis", $fullname, $email, $username, $role_id, $hashed);
            if ($stmt->execute()) flash_set("Admin user created successfully.");
            else flash_set("Error: " . $conn->error, "danger");
        }
    }

    // Edit Admin
    if ($_POST['action'] === 'edit_user') {
        $id = intval($_POST['admin_id']);
        $fullname = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role_id = intval($_POST['role_id']);

        if (!empty($_POST['password'])) {
            $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET full_name=?, email=?, role_id=?, password=? WHERE admin_id=?");
            $stmt->bind_param("ssisi", $fullname, $email, $role_id, $hashed, $id);
        } else {
            $stmt = $conn->prepare("UPDATE admins SET full_name=?, email=?, role_id=? WHERE admin_id=?");
            $stmt->bind_param("ssii", $fullname, $email, $role_id, $id);
        }
        if($stmt->execute()) flash_set("User updated successfully.");
    }

    header("Location: users.php");
    exit;
}

// 2. Delete Admin
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    if ($del_id === $_SESSION['admin_id']) {
        flash_set("Self-deletion is not allowed.", "danger");
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        flash_set("User deleted permanently.", "warning");
    }
    header("Location: users.php"); exit;
}

// 3. Fetch Data
$roles_res = $conn->query("SELECT * FROM roles ORDER BY name ASC");
$roles_list = [];
while($r = $roles_res->fetch_assoc()) $roles_list[] = $r;

$users_res = $conn->query("SELECT a.*, r.name as role_name FROM admins a LEFT JOIN roles r ON a.role_id = r.id ORDER BY a.created_at DESC");

$pageTitle = "Staff Management";
?>
<?php $layout->header($pageTitle); ?>
<body>

    <?php $layout->sidebar(); ?>
    <div class="main-wrapper">
        <?php $layout->topbar($pageTitle ?? 'Staff Management'); ?>
        <main class="main-content">
        
        <div class="container-fluid p-0">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Administrative Staff</h2>
                    <p class="text-muted mb-0">Manage personnel and assign them to specific roles.</p>
                </div>
                <button class="btn btn-forest rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-2"></i> New Staff Member
                </button>
            </div>

            <?php flash_render(); ?>

            <div class="iq-card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light text-uppercase small fw-bold text-muted">
                            <tr>
                                <th class="ps-4 py-3">User Profile</th>
                                <th>Assigned Role</th>
                                <th>Contact Details</th>
                                <th>Joined</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($u = $users_res->fetch_assoc()): 
                                $isMe = ($u['admin_id'] == $_SESSION['admin_id']);
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-box">
                                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold "><?= htmlspecialchars($u['full_name']) ?> <?php if($isMe): ?><span class="badge bg-dark rounded-pill ms-1" style="font-size: 8px;">YOU</span><?php endif; ?></div>
                                            <small class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge">
                                        <?= htmlspecialchars($u['role_name'] ?? 'No Role') ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($u['email']) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-light text-primary rounded-circle" onclick='openEditModal(<?= json_encode($u) ?>)'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php if(!$isMe): ?>
                                            <a href="?delete_id=<?= $u['admin_id'] ?>" class="btn btn-sm btn-light text-danger rounded-circle ms-1" onclick="return confirm('Archive this user account?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
        </main>
        <?php $layout->footer(); ?>
    </div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_user">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Register New Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="row g-3 mb-3">
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
                    <label class="form-label small fw-bold">Assign Role</label>
                    <select name="role_id" class="form-select" required>
                        <option value="">Select a role...</option>
                        <?php foreach($roles_list as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Temp Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-forest w-100 py-2 fw-bold shadow-sm">Initialize Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="admin_id" id="edit_admin_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Modify Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Role Assignment</label>
                    <select name="role_id" id="edit_role_id" class="form-select" required>
                        <?php foreach($roles_list as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Force New Password (Empty to skip)</label>
                    <input type="password" name="password" class="form-control" minlength="6">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(data) {
    document.getElementById('edit_admin_id').value = data.admin_id;
    document.getElementById('edit_full_name').value = data.full_name;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_role_id').value = data.role_id;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>
</script>





