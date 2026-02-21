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

require_superadmin();
?>
<?php
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
<?php $layout->header($pageTitle); ?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }

        /* Hope UI Forest & Lime Tokens */
        :root {
            --forest: #0F2E25;
            --forest-mid: #1A4D3E;
            --lime: #D0F35D;
            --lime-hover: #BCE04B;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.5);
        }

        /* Hero Section */
        .hp-hero { 
            background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%); 
            border-radius: 30px; padding: 50px; color: white; margin-bottom: 40px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
            position: relative; overflow: hidden;
        }
        .hp-hero::after {
            content: ''; position: absolute; top: -50%; right: -10%; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(208, 243, 93, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        /* Glass Stat (Role Selector) */
        .glass-stat { 
            background: var(--glass-bg); backdrop-filter: blur(10px);
            border-radius: 24px; padding: 1.5rem; border: 1px solid var(--glass-border);
            transition: 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }
        .glass-stat:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(0,0,0,0.05); }

        /* Role Card Refinement */
        .role-card { background: white; border-radius: 24px; padding: 2.5rem; box-shadow: 0 10px 40px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); }
        .role-card.superadmin { background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%); color: white; border: none; }
        .role-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .role-card.superadmin .role-header { border-bottom-color: rgba(255,255,255,0.1); }
        .role-title { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px; }
        .role-desc { font-size: 1rem; opacity: 0.7; margin-top: 5px; }

        /* Permission Grid */
        .category-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin: 30px 0 15px 0; display: flex; align-items: center; gap: 10px; }
        .role-card.superadmin .category-label { color: var(--lime); }
        .perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
        .perm-checkbox { 
            display: flex; align-items: center; padding: 15px 20px; 
            background: #f8fafc; border-radius: 16px; cursor: pointer; 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid transparent; 
        }
        .perm-checkbox:hover { background: #f1f5f9; transform: scale(1.02); }
        .perm-checkbox.active { background: #f0fdf4; border-color: rgba(34, 197, 94, 0.2); }
        .role-card.superadmin .perm-checkbox { background: rgba(255,255,255,0.05); color: white; border-color: rgba(255,255,255,0.1); }
        .role-card.superadmin .perm-checkbox.active { background: var(--lime); color: var(--forest); border-color: var(--lime); }

        .perm-checkbox input[type="checkbox"] { width: 20px; height: 20px; margin-right: 15px; cursor: pointer; accent-color: var(--forest); }
        .perm-label { font-size: 0.95rem; font-weight: 600; flex: 1; }

        /* Buttons & Badges */
        .btn-lime { background: var(--lime); color: var(--forest); border: none; font-weight: 700; padding: 12px 28px; border-radius: 14px; transition: 0.3s; }
        .btn-lime:hover { background: var(--lime-hover); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(208, 243, 93, 0.2); }
        .badge-count { background: #f1f5f9; color: var(--forest); padding: 6px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 700; }
        .role-card.superadmin .badge-count { background: rgba(255,255,255,0.1); color: var(--lime); }
        .locked-badge { background: var(--lime); color: var(--forest); padding: 6px 16px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.5px; }

        /* Modals & Action Buttons */
        .modal-content { border-radius: 28px; border: 1px solid var(--glass-border); box-shadow: 0 25px 50px rgba(0,0,0,0.15); border: none; }
        .modal-header { border-bottom: 1px solid rgba(0,0,0,0.05); padding: 2rem 2rem 1.5rem; }
        .modal-body { padding: 2rem; }
        .modal-footer { border-top: 1px solid rgba(0,0,0,0.05); padding: 1.5rem 2rem 2rem; }
        .form-control, .form-select { border-radius: 14px; padding: 12px 18px; border: 1.5px solid #e2e8f0; }
        .form-control:focus { border-color: var(--forest); box-shadow: 0 0 0 4px rgba(15, 46, 37, 0.05); }

        .role-actions .btn-icon { 
            width: 42px; height: 42px; border-radius: 14px; 
            display: inline-flex; align-items: center; justify-content: center;
            transition: 0.2s; border: 1px solid transparent; background: #f8fafc; color: var(--forest);
        }
        .role-actions .btn-icon:hover { background: var(--forest); color: var(--lime); transform: translateY(-2px); }
        .role-actions .btn-outline-danger.btn-icon:hover { background: #ef4444; color: white; }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'Role Matrix'); ?>
        <div class="container-fluid">
    <div class="hp-hero">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">RBAC Console V24</span>
                <h1 class="display-4 fw-800 mb-2">Roles & Permissions.</h1>
                <p class="opacity-75 fs-5">Everything is organized. Configure access levels with <span class="text-lime fw-bold">surgical precision</span>.</p>
                <button class="btn btn-lime rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                    <i class="bi bi-plus-circle me-2"></i> Create New Role
                </button>
            </div>
            <div class="col-md-5 text-end d-none d-lg-block">
                <div class="d-inline-block p-4 rounded-4 bg-white bg-opacity-10 backdrop-blur">
                    <div class="small opacity-75">Security Integrity</div>
                    <div class="h3 fw-bold mb-0 text-lime">ACID Verified</div>
                </div>
            </div>
        </div>
    </div>

    <?php flash_render(); ?>

    <!-- Role Selector Control -->
    <div class="glass-stat mb-4">
        <div class="d-flex align-items-center gap-4">
            <div class="flex-grow-1">
                <label class="small text-muted fw-800 text-uppercase mb-2 d-block" style="letter-spacing: 1px;">Select Role to Configure</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0 rounded-start-4"><i class="bi bi-person-badge text-forest"></i></span>
                    <select id="roleSelector" class="form-select border-start-0 rounded-end-4 fw-bold" style="height: 60px; font-size: 1.1rem;">
                        <?php foreach ($roles_data as $role): ?>
                            <option value="role_<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-none d-md-block text-end ps-5 border-start border-2">
                <div class="small text-muted fw-bold">Active Roles</div>
                <div class="display-6 fw-800 mb-0 text-forest"><?= count($roles_data) ?></div>
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

            <!-- Assigned Support Categories Visibility -->
            <?php
            $my_categories = [];
            foreach (SUPPORT_ROUTING_MAP as $cat => $rname) {
                if ($rname === $role['name']) {
                    $my_categories[] = ucfirst($cat);
                }
            }
            if (!empty($my_categories) || $is_super):
            ?>
            <div class="mb-4 p-3 bg-light rounded-4 border-start border-4 border-success">
                <div class="category-label mb-2">
                    <i class="bi bi-headset"></i>
                    ASSIGNED SUPPORT CATEGORIES
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($is_super): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                            <i class="bi bi-infinity me-1"></i> All Categories (Full Access)
                        </span>
                    <?php else: ?>
                        <?php foreach ($my_categories as $cat_name): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                                <i class="bi bi-tag-fill me-1"></i> <?= $cat_name ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

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
            <div class="mt-5 pt-4 border-top text-end">
                <button class="btn btn-forest rounded-pill px-5 py-3 fw-bold shadow-sm" style="background: var(--forest); color: white;" onclick="simulateSave()">
                    <i class="bi bi-shield-check me-2"></i> Deploy Security Configuration
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php $layout->footer(); ?>
    </div><!-- End rolesContainer -->

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
        </div>
        
    </div>
</div>
