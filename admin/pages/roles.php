<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

/**
 * admin/roles.php
 * V24 Perfect Mirror RBAC - Role & Permission Management Console
 * Enhanced UI with grouped permissions and real-time toggle
 */

require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_superadmin();

// 1. Handle AJAX Toggle (Permission Checkboxes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_permission'])) {
    header('Content-Type: application/json');
    $role_id = intval($_POST['role_id']);
    $perm_id = intval($_POST['perm_id']);
    $status  = $_POST['status'] === 'true';

    // Safety: Cannot modify superadmin (Role ID 1)
    if ($role_id === 1) {
        echo json_encode(['success' => false, 'message' => 'Superadmin permissions are locked.']);
        exit;
    }

    if ($status) {
        $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES ($role_id, $perm_id)");
    } else {
        $conn->query("DELETE FROM role_permissions WHERE role_id = $role_id AND permission_id = $perm_id");
    }
    echo json_encode(['success' => true]);
    exit;
}

// 2. Handle Role CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    
    if ($_POST['action'] === 'add_role') {
        $name = trim($_POST['role_name']);
        $desc = trim($_POST['role_desc']);
        $stmt = $conn->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $desc);
        if ($stmt->execute()) {
            flash_set("Role '$name' created successfully.", "success");
        } else {
            flash_set("Error: " . $conn->error, "danger");
        }
    }
    
    if ($_POST['action'] === 'edit_role') {
        $id = intval($_POST['role_id']);
        $name = trim($_POST['role_name']);
        $desc = trim($_POST['role_desc']);
        if ($id === 1) {
            flash_set("Cannot edit Superadmin role.", "danger");
        } else {
            $stmt = $conn->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $desc, $id);
            if ($stmt->execute()) flash_set("Role updated.", "success");
        }
    }

    if ($_POST['action'] === 'delete_role') {
        $id = intval($_POST['role_id']);
        if ($id === 1) {
            flash_set("Cannot delete Superadmin.", "danger");
        } else {
            $check = $conn->query("SELECT COUNT(*) FROM admins WHERE role_id = $id")->fetch_row()[0];
            if ($check > 0) {
                flash_set("Cannot delete: $check users are assigned to this role.", "warning");
            } else {
                $conn->query("DELETE FROM role_permissions WHERE role_id = $id");
                $conn->query("DELETE FROM roles WHERE id = $id");
                flash_set("Role deleted.", "success");
            }
        }
    }

    header("Location: roles.php");
    exit;
}

// 3. Fetch Data
$roles = $conn->query("SELECT * FROM roles ORDER BY id ASC");
$roles_data = [];
while($r = $roles->fetch_assoc()) {
    $roles_data[] = $r;
}

// Group permissions by category
$perms_res = $conn->query("SELECT * FROM permissions ORDER BY category, name ASC");
$perms_by_cat = [];
while($p = $perms_res->fetch_assoc()) {
    $perms_by_cat[$p['category']][] = $p;
}

// Build permission map
$map_res = $conn->query("SELECT * FROM role_permissions");
$active_map = [];
while($m = $map_res->fetch_assoc()) {
    $active_map[$m['role_id']][] = $m['permission_id'];
}

$pageTitle = "Roles & Permissions";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-green: #0F2E25;
            --forest-mid: #134e3b;
            --lime: #D0F35D;
            --lime-hover: #e1ff8d;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8f9fa;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s;
        }
        
        @media (max-width: 991px) {
            .main-content { margin-left: 0; }
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--forest-green) 0%, var(--forest-mid) 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .role-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .role-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .role-card.superadmin {
            background: linear-gradient(135deg, var(--forest-green) 0%, var(--forest-mid) 100%);
            color: white;
            border: none;
        }
        
        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .role-card.superadmin .role-header {
            border-bottom-color: rgba(255,255,255,0.2);
        }
        
        .role-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .role-desc {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 5px 0 0 0;
        }
        
        .perm-category {
            margin-bottom: 20px;
        }
        
        .category-label {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #6c757d;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .role-card.superadmin .category-label {
            color: var(--lime);
        }
        
        .category-label i {
            font-size: 1rem;
        }
        
        .perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
        }
        
        .perm-checkbox {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        
        .perm-checkbox:hover {
            background: #e9ecef;
            border-color: var(--forest-green);
        }
        
        .perm-checkbox.active {
            background: var(--lime);
            border-color: var(--forest-green);
        }
        
        .role-card.superadmin .perm-checkbox {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
            color: white;
        }
        
        .role-card.superadmin .perm-checkbox.active {
            background: var(--lime);
            color: var(--forest-green);
            border-color: var(--lime);
        }
        
        .perm-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
            accent-color: var(--forest-green);
        }
        
        .perm-label {
            font-size: 0.9rem;
            font-weight: 500;
            flex: 1;
        }
        
        .btn-lime {
            background: var(--lime);
            color: var(--forest-green);
            border: none;
            font-weight: 700;
            padding: 12px 24px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .btn-lime:hover {
            background: var(--lime-hover);
            color: var(--forest-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(208, 243, 93, 0.4);
        }
        
        .badge-count {
            background: var(--forest-green);
            color: var(--lime);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        
        .role-card.superadmin .badge-count {
            background: var(--lime);
            color: var(--forest-green);
        }
        
        .role-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        .locked-badge {
            background: var(--lime);
            color: var(--forest-green);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<main class="main-content">
    <?php $layout->topbar($pageTitle ?? 'Role Matrix'); ?>
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="mb-2"><i class="bi bi-grid-3x3-gap me-2"></i> Roles & Permissions</h1>
                <p class="mb-0 opacity-75">Define access levels for your staff.</p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <button class="btn btn-lime shadow-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                    <i class="bi bi-plus-circle me-2"></i> Create New Role
                </button>
            </div>
        </div>
    </div>

    <?php flash_render(); ?>

    <!-- Role Selector Control -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
        <div class="card-body p-4 d-flex align-items-center gap-3 bg-white rounded-4">
            <div class="flex-grow-1">
                <label class="small text-muted fw-bold text-uppercase mb-1">Select Role to Configure</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person-badge"></i></span>
                    <select id="roleSelector" class="form-select border-start-0 fw-bold" style="height: 50px;">
                        <?php foreach ($roles_data as $role): ?>
                            <option value="role_<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-none d-md-block text-end ps-4 border-start">
                <div class="small text-muted">Active Roles</div>
                <div class="h3 fw-bold mb-0 text-forest"><?= count($roles_data) ?></div>
            </div>
        </div>
    </div>

    <!-- Roles Container -->
    <div id="rolesContainer">
    <?php foreach ($roles_data as $index => $role): 
        $is_super = ($role['id'] == 1);
        $assigned_perms = $active_map[$role['id']] ?? [];
        $perm_count = count($assigned_perms);
        $display = ($index === 0) ? 'block' : 'none'; // Show first by default
    ?>
    <div class="role-config-card <?= $is_super ? 'superadmin' : '' ?>" id="role_<?= $role['id'] ?>" style="display: <?= $display ?>;">
        <div class="role-card <?= $is_super ? 'superadmin' : '' ?>">
            <div class="role-header">
                <div>
                    <h3 class="role-title">
                        <?= htmlspecialchars($role['name']) ?>
                        <?php if ($is_super): ?>
                            <span class="locked-badge ms-2">
                                <i class="bi bi-shield-lock-fill"></i> SYSTEM LOCKED
                            </span>
                        <?php endif; ?>
                    </h3>
                    <p class="role-desc"><?= htmlspecialchars($role['description'] ?? 'No description defined.') ?></p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge-count" id="badge_<?= $role['id'] ?>"><?= $perm_count ?> permissions active</span>
                    <?php if (!$is_super): ?>
                    <div class="role-actions">
                        <button class="btn btn-sm btn-outline-primary btn-icon" 
                                onclick="editRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['name']) ?>', '<?= htmlspecialchars($role['description'] ?? '') ?>')"
                                title="Edit Role Details">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-icon" 
                                onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['name']) ?>')"
                                title="Delete Role">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Permissions Grid -->
            <?php foreach ($perms_by_cat as $category => $permissions): ?>
            <div class="perm-category">
                <div class="category-label">
                    <i class="bi bi-<?= $category === 'operations' ? 'briefcase' : ($category === 'system' ? 'cpu' : 'gear') ?>-fill"></i>
                    <?= strtoupper($category) ?>
                </div>
                <div class="perm-grid">
                    <?php foreach ($permissions as $perm): 
                        $is_active = in_array($perm['id'], $assigned_perms);
                        $disabled = $is_super ? 'disabled' : '';
                    ?>
                    <label class="perm-checkbox <?= $is_active ? 'active' : '' ?>">
                        <input type="checkbox" 
                               <?= $disabled ?>
                               <?= $is_active ? 'checked' : '' ?>
                               onchange="togglePermission(<?= $role['id'] ?>, <?= $perm['id'] ?>, this)">
                        <span class="perm-label"><?= htmlspecialchars($perm['name']) ?></span>
                        <?php if($is_active): ?>
                            <i class="bi bi-check-circle-fill text-success ms-2 fade-in"></i>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(!$is_super): ?>
            <div class="mt-4 pt-3 border-top text-end">
                <button class="btn btn-forest px-4 rounded-pill" onclick="simulateSave()">
                    <i class="bi bi-check2-all me-2"></i> Save Configuration
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</main>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_role">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" name="role_name" class="form-control" required placeholder="e.g., Accountant">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="role_desc" class="form-control" rows="3" placeholder="What this role does..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="role_id" id="edit_role_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" name="role_name" id="edit_role_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="role_desc" id="edit_role_desc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_role">
    <input type="hidden" name="role_id" id="delete_role_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Role Dropdown Logic
    const selector = document.getElementById('roleSelector');
    if(selector) {
        selector.addEventListener('change', function() {
            const selectedId = this.value;
            // Hide all
            document.querySelectorAll('.role-config-card').forEach(card => card.style.display = 'none');
            // Show selected
            const target = document.getElementById(selectedId);
            if(target) {
                target.style.display = 'block';
                // optional fade
                target.style.opacity = '0';
                setTimeout(() => target.style.opacity = '1', 50);
            }
        });
    }

    // Save button visual simulation (Permissions are auto-saved via AJAX)
    function simulateSave() {
        const btn = event.currentTarget;
        const original = btn.innerHTML;
        
        btn.innerHTML = '<i class="bi bi-check2-circle me-2"></i> Saved Successfully';
        btn.classList.remove('btn-forest');
        btn.classList.add('btn-success');
        
        setTimeout(() => {
            btn.innerHTML = original;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-forest');
        }, 2000);
    }

    function togglePermission(roleId, permId, checkbox) {
        const parent = checkbox.closest('.perm-checkbox');
        
        fetch('roles.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `toggle_permission=1&role_id=${roleId}&perm_id=${permId}&status=${checkbox.checked}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                parent.classList.toggle('active', checkbox.checked);
                
                // Add green checkmark dynamically if checked
                if(checkbox.checked) {
                    if(!parent.querySelector('.bi-check-circle-fill')) {
                        const icon = document.createElement('i');
                        icon.className = 'bi bi-check-circle-fill text-success ms-2 fade-in';
                        parent.appendChild(icon);
                    }
                } else {
                    const icon = parent.querySelector('.bi-check-circle-fill');
                    if(icon) icon.remove();
                }
                
                // Update badge count
                const card = parent.closest('.role-card');
                const badge = card.querySelector('.badge-count');
                if(badge) {
                    const currentCount = parseInt(badge.textContent);
                    badge.textContent = (checkbox.checked ? currentCount + 1 : currentCount - 1) + ' permissions active';
                }
            } else {
                alert(data.message || 'Error updating permission');
                checkbox.checked = !checkbox.checked;
            }
        });
    }

function editRole(id, name, desc) {
    document.getElementById('edit_role_id').value = id;
    document.getElementById('edit_role_name').value = name;
    document.getElementById('edit_role_desc').value = desc;
    new bootstrap.Modal(document.getElementById('editRoleModal')).show();
}

function deleteRole(id, name) {
    if (confirm(`Are you sure you want to delete the role "${name}"?\n\nThis action cannot be undone.`)) {
        document.getElementById('delete_role_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

</body>
</html>







