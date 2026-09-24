<?php
// superadmin/role_matrix.php
// Dynamic RBAC Management Console - Premium Hope UI

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../inc/auth.php';

require_superadmin();

// 1. Handle AJAX Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $role_id = intval($_POST['role_id']);
    $perm_id = intval($_POST['perm_id']);
    $status  = $_POST['status'] === 'true';

    if ($status) {
        $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES ($role_id, $perm_id)");
    } else {
        $conn->query("DELETE FROM role_permissions WHERE role_id = $role_id AND permission_id = $perm_id");
    }
    
    // Clear session permissions for real-time effect if current user affected
    // (Optional: In production, you might want to force logout or use a versioning system)
    echo json_encode(['success' => true]);
    exit;
}

// 2. Fetch Roles (Excluding Superadmin for safety)
$roles = $conn->query("SELECT * FROM roles WHERE role_name != 'superadmin' ORDER BY role_id ASC");
$roles_data = [];
while($r = $roles->fetch_assoc()) $roles_data[] = $r;

// 3. Fetch Permissions Categorized
$perms_res = $conn->query("SELECT * FROM permissions ORDER BY category, display_name ASC");
$perms_by_cat = [];
while($p = $perms_res->fetch_assoc()) {
    $perms_by_cat[$p['category']][] = $p;
}

// 4. Fetch Existing Assignments
$active_map = [];
$map_res = $conn->query("SELECT * FROM role_permissions");
while($m = $map_res->fetch_assoc()) {
    $active_map[$m['role_id']][] = $m['permission_id'];
}

$pageTitle = "Role Matrix";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | System Command</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-deep: #022c22;
            --lime-vibrant: #bef264;
            --bg-body: #f8fafc;
            --card-radius: 20px;
        }
        body { background: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--forest-deep); }
        .matrix-card { background: #fff; border: none; border-radius: var(--card-radius); box-shadow: 0 4px 24px rgba(0,0,0,0.03); }
        .table-matrix thead th { background: #f1f5f9; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; color: #64748b; padding: 1.25rem; border: none; }
        .table-matrix tbody td { padding: 1rem 1.25rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .cat-row { background: #fdfdfd; font-weight: 800; color: #1e293b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .form-check-input:checked { background-color: var(--forest-deep); border-color: var(--forest-deep); }
        .role-badge { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 4px 8px; border-radius: 6px; }
        .badge-manager { background: #e0f2fe; color: #0369a1; }
        .badge-accountant { background: #fef3c7; color: #92400e; }
        .badge-admin { background: #f1f5f9; color: #475569; }

        .main-content-wrapper { margin-left: 280px; transition: 0.3s; padding: 2.5rem; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }
    </style>
</head>
<body>

<div class="d-flex">
    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="flex-fill main-content-wrapper">
        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Role Matrix</h2>
                <p class="text-muted mb-0">Define which departments can access specific system modules.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="seed_permissions.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-arrow-repeat me-2"></i> Sync Permissions
                </a>
            </div>
        </div>

        <div class="matrix-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-matrix mb-0">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Module / Permission</th>
                            <?php foreach($roles_data as $r): ?>
                                <th class="text-center">
                                    <span class="role-badge badge-<?= strtolower($r['role_name']) ?>">
                                        <?= $r['role_name'] ?>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($perms_by_cat as $cat => $perms): ?>
                            <tr>
                                <td colspan="<?= count($roles_data) + 1 ?>" class="cat-row">
                                    <i class="bi bi-folder2-open me-2"></i> <?= $cat ?>
                                </td>
                            </tr>
                            <?php foreach($perms as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= $p['display_name'] ?></div>
                                        <small class="text-muted"><?= $p['permission_key'] ?></small>
                                    </td>
                                    <?php foreach($roles_data as $r): 
                                        $checked = isset($active_map[$r['role_id']]) && in_array($p['id'], $active_map[$r['role_id']]);
                                    ?>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input perm-toggle" type="checkbox" 
                                                       data-role="<?= $r['role_id'] ?>" 
                                                       data-perm="<?= $p['id'] ?>"
                                                       <?= $checked ? 'checked' : '' ?>>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 p-4 rounded-4 bg-white border border-light">
            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle text-primary me-2"></i>RBAC Policy</h6>
            <p class="small text-muted mb-0">Changes to the matrix take effect immediately. Administrative staff will see their dashboard and sidebar menus update upon their next page load or sign-in. Superadmins always retain full system control.</p>
        </div>

        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.perm-toggle').forEach(el => {
    el.addEventListener('change', function() {
        const formData = new FormData();
        formData.append('toggle', '1');
        formData.append('role_id', this.dataset.role);
        formData.append('perm_id', this.dataset.perm);
        formData.append('status', this.checked);

        fetch('role_matrix.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if(!data.success) alert('Failed to update permission.');
        })
        .catch(err => console.error(err));
    });
});
</script>
</body>
</html>
