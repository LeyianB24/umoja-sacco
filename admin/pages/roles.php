<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
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
        if ($stmt->execute()) flash_set("Role '$name' created successfully.", "success");
        else flash_set("Error: " . $conn->error, "danger");
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
while ($r = $roles->fetch_assoc()) $roles_data[] = $r;

$perms_res = $conn->query("SELECT * FROM permissions ORDER BY category, name ASC");

// Custom Category Sort Order (Sidebar Mirror)
$cat_order = [
    'General'               => 1,
    'Member Management'     => 2,
    'People & Access'       => 3,
    'Financial Management'  => 4,
    'Loans & Credit'        => 5,
    'Welfare Module'        => 6,
    'Investments & Assets'  => 7,
    'Reports & Exports'     => 8,
    'System Control Center' => 9,
    'Maintenance & Config'  => 10,
    'My Account'            => 11
];

$perms_by_cat = [];
while ($p = $perms_res->fetch_assoc()) {
    $perms_by_cat[$p['category']][] = $p;
}

// Re-sort the array by the custom order
uksort($perms_by_cat, function($a, $b) use ($cat_order) {
    $oa = $cat_order[$a] ?? 99;
    $ob = $cat_order[$b] ?? 99;
    return $oa <=> $ob;
});

$map_res = $conn->query("SELECT * FROM role_permissions");
$active_map = [];
while ($m = $map_res->fetch_assoc()) $active_map[$m['role_id']][] = $m['permission_id'];

$pageTitle = "Roles & Permissions";
?>
<?php $layout->header($pageTitle); ?>

<!-- ═══════════════════════════════════════════════════════════ PAGE STYLES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ── Base ───────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

body,
.main-content-wrapper,
.detail-card, .role-config-card,
.modal-content,
.table,
select, input, textarea, button {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Design tokens ──────────────────────────────────────────── */
:root {
    --forest:       #1a3a2a;
    --forest-mid:   #234d38;
    --forest-light: #2e6347;
    --lime:         #a8e063;
    --lime-soft:    #d4f0a0;
    --lime-glow:    rgba(168,224,99,.18);
    --ink:          #111c14;
    --muted:        #6b7f72;
    --surface:      #ffffff;
    --surface-2:    #f5f8f5;
    --border:       #e3ebe5;
    --shadow-sm:    0 4px 12px rgba(26,58,42,.08);
    --shadow-md:    0 8px 28px rgba(26,58,42,.12);
    --shadow-lg:    0 16px 48px rgba(26,58,42,.16);
    --radius-sm:    10px;
    --radius-md:    16px;
    --radius-lg:    22px;
    --transition:   all .22s cubic-bezier(.4,0,.2,1);
}

/* ── Page scaffold ──────────────────────────────────────────── */
.page-canvas {
    background: var(--surface-2);
    min-height: 100vh;
    padding: 0 0 60px;
}

/* ── Breadcrumb ─────────────────────────────────────────────── */
.breadcrumb {
    background: none; padding: 0; margin: 0 0 28px;
    font-size: .8rem; font-weight: 500;
}
.breadcrumb-item a { color: var(--muted); text-decoration: none; transition: var(--transition); }
.breadcrumb-item a:hover { color: var(--forest); }
.breadcrumb-item.active { color: var(--ink); font-weight: 600; }
.breadcrumb-item + .breadcrumb-item::before { color: var(--border); }

/* ── Page hero ──────────────────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, var(--forest-light) 100%);
    border-radius: var(--radius-lg);
    padding: 36px 40px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    animation: fadeUp .35s ease both;
}
.page-header::before {
    content: '';
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse 60% 80% at 90% -10%, rgba(168,224,99,.22) 0%, transparent 60%),
        radial-gradient(ellipse 40% 50% at -5% 100%, rgba(168,224,99,.08) 0%, transparent 55%);
    pointer-events: none;
}
.page-header::after {
    content: '';
    position: absolute; right: -60px; top: -60px;
    width: 260px; height: 260px;
    border-radius: 50%;
    border: 1px solid rgba(168,224,99,.1);
    pointer-events: none;
}
.hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 24px; }
.hero-rbac-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15);
    color: rgba(255,255,255,.8); font-size: .72rem; font-weight: 700;
    letter-spacing: .5px; text-transform: uppercase;
    border-radius: 100px; padding: 5px 14px; margin-bottom: 14px;
}
.hero-title {
    font-size: clamp(1.6rem, 3vw, 2.2rem);
    font-weight: 800; color: #fff;
    letter-spacing: -.5px; margin: 0 0 8px;
}
.hero-sub {
    font-size: .88rem; color: rgba(255,255,255,.65);
    font-weight: 500; margin: 0 0 22px;
}
.hero-sub .accent { color: var(--lime); font-weight: 700; }

.hero-stats { display: flex; gap: 12px; flex-wrap: wrap; }
.hero-stat {
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: var(--radius-sm); padding: 10px 18px;
    backdrop-filter: blur(4px);
}
.hero-stat-label { font-size: .65rem; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: 3px; }
.hero-stat-value { font-size: 1.2rem; font-weight: 800; color: #fff; }
.hero-stat-value.lime { color: var(--lime); }

/* ── Buttons ────────────────────────────────────────────────── */
.btn-lime {
    background: var(--lime); color: var(--ink); border: none;
    font-weight: 700; font-size: .85rem; transition: var(--transition);
    box-shadow: 0 4px 14px rgba(168,224,99,.4);
}
.btn-lime:hover { background: #baea78; color: var(--ink); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(168,224,99,.5); }
.btn-forest { background: var(--forest); color: #fff; border: none; font-weight: 700; font-size: .85rem; transition: var(--transition); }
.btn-forest:hover { background: var(--forest-light); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,58,42,.3); }

/* ── Role selector card ─────────────────────────────────────── */
.role-selector-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 22px 28px;
    margin-bottom: 24px;
    display: flex; align-items: center; gap: 28px; flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
    animation: fadeUp .4s ease both; animation-delay: .08s;
}
.role-selector-label {
    font-size: .68rem; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: var(--muted); margin-bottom: 8px;
}
.role-selector-wrap {
    flex: 1; min-width: 220px;
    display: flex; align-items: center; gap: 0;
}
.role-selector-icon {
    width: 48px; height: 48px;
    background: var(--lime-glow); border: 1.5px solid var(--border);
    border-right: none; border-radius: 10px 0 0 10px;
    display: flex; align-items: center; justify-content: center;
    color: var(--forest); font-size: 1.05rem;
}
.role-selector-wrap select {
    flex: 1; height: 48px;
    border: 1.5px solid var(--border); border-left: none;
    border-radius: 0 10px 10px 0;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .92rem; font-weight: 700;
    color: var(--ink); background: var(--surface-2);
    padding: 0 14px; transition: var(--transition);
    cursor: pointer;
}
.role-selector-wrap select:focus {
    outline: none; border-color: var(--forest); background: #fff;
    box-shadow: 0 0 0 3px rgba(26,58,42,.07);
}
.selector-divider {
    width: 1px; height: 44px;
    background: var(--border); flex-shrink: 0;
}
.selector-stat { text-align: center; padding: 0 8px; }
.selector-stat-label { font-size: .68rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: var(--muted); margin-bottom: 3px; }
.selector-stat-value { font-size: 1.6rem; font-weight: 800; color: var(--forest); line-height: 1; }

/* ── Role config card ───────────────────────────────────────── */
.role-config-card {
    animation: fadeUp .42s ease both; animation-delay: .14s;
}

.role-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}
.role-card:hover { box-shadow: var(--shadow-md); }
.role-card.superadmin { border-color: #f0d080; }

/* Role card header */
.role-card-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 14px;
    padding: 28px 32px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--surface-2);
}
.role-card.superadmin .role-card-header { background: linear-gradient(135deg, #fffbeb, #fef9e0); border-bottom-color: #f0d080; }

.role-title {
    font-size: 1.25rem; font-weight: 800;
    color: var(--ink); margin: 0 0 5px;
    display: flex; align-items: center; gap: 10px;
}
.role-desc { font-size: .82rem; color: var(--muted); font-weight: 500; margin: 0; max-width: 520px; }

.locked-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .64rem; font-weight: 800; letter-spacing: .4px;
    text-transform: uppercase;
    background: linear-gradient(135deg, #f5a623, #e8941a);
    color: #fff; border-radius: 6px; padding: 3px 10px;
}

.badge-count {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .75rem; font-weight: 700;
    background: var(--lime-glow); color: var(--forest);
    border: 1px solid rgba(168,224,99,.35);
    border-radius: 100px; padding: 5px 14px; white-space: nowrap;
}
.badge-count::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--forest); opacity: .5; }

.role-action-group { display: flex; gap: 8px; align-items: center; }
.btn-icon-sm {
    width: 34px; height: 34px; border-radius: 9px;
    border: 1.5px solid var(--border); background: var(--surface);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .85rem; transition: var(--transition); cursor: pointer;
}
.btn-icon-sm.edit { color: var(--forest); }
.btn-icon-sm.edit:hover { background: var(--lime-glow); border-color: var(--lime); transform: translateY(-1px); }
.btn-icon-sm.del  { color: #c0392b; }
.btn-icon-sm.del:hover  { background: #fef0ef; border-color: #f5c6c6; transform: translateY(-1px); }

/* Role card body */
.role-card-body { padding: 28px 32px; }

/* Support category strip */
.support-strip {
    background: #f0fff6; border: 1px solid #b8f0ce;
    border-left: 4px solid #2e6347;
    border-radius: var(--radius-sm); padding: 14px 18px;
    margin-bottom: 28px;
    display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
}
.support-strip-label {
    font-size: .67rem; font-weight: 800; letter-spacing: .8px;
    text-transform: uppercase; color: var(--forest-light);
    display: flex; align-items: center; gap: 6px;
    margin-right: 4px;
}
.support-chip {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .73rem; font-weight: 700;
    background: #dcfce7; color: #166534;
    border: 1px solid #86efac; border-radius: 100px; padding: 4px 12px;
}

/* ── Permission categories ──────────────────────────────────── */
.perm-category { margin-bottom: 28px; }
.perm-category:last-of-type { margin-bottom: 0; }

.category-header {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 14px; padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}
.category-icon {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--lime-glow); color: var(--forest);
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem;
}
.category-name {
    font-size: .68rem; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: var(--forest);
}
.category-count {
    font-size: .68rem; font-weight: 600; color: var(--muted);
    margin-left: auto;
}

/* ── Permission grid ────────────────────────────────────────── */
.perm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 10px;
}

.perm-checkbox {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    cursor: pointer; transition: var(--transition);
    background: var(--surface-2); user-select: none;
    position: relative;
}
.perm-checkbox:hover:not([disabled]) {
    border-color: #b8d4be; background: #f0f7f2;
    transform: translateY(-1px);
    box-shadow: var(--shadow-xs, 0 1px 4px rgba(26,58,42,.06));
}
.perm-checkbox.active {
    border-color: rgba(168,224,99,.6);
    background: var(--lime-glow);
}
.perm-checkbox input[type="checkbox"] {
    width: 16px; height: 16px; flex-shrink: 0;
    accent-color: var(--forest);
    cursor: pointer; border-radius: 4px;
}
.perm-checkbox input[type="checkbox"]:disabled { cursor: not-allowed; opacity: .6; }
.perm-label {
    font-size: .8rem; font-weight: 600;
    color: var(--ink); line-height: 1.3;
}
.perm-active-icon {
    margin-left: auto; color: #1a7a3f; font-size: .82rem; flex-shrink: 0;
}

/* Disabled state */
.perm-checkbox.locked {
    opacity: .7; cursor: not-allowed;
    border-color: #d4c88a; background: #fffdf0;
}
.perm-checkbox.locked .perm-label { color: var(--muted); }

/* ── Deploy button bar ──────────────────────────────────────── */
.deploy-bar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
    padding: 20px 32px;
    border-top: 1px solid var(--border);
    background: var(--surface-2);
}
.deploy-hint { font-size: .78rem; color: var(--muted); font-weight: 500; display: flex; align-items: center; gap: 6px; }
.deploy-hint i { color: #1a7a3f; }
.btn-deploy {
    background: var(--forest); color: #fff; border: none;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-weight: 700; font-size: .88rem;
    padding: 11px 28px; border-radius: 100px;
    cursor: pointer; transition: var(--transition);
    box-shadow: 0 4px 14px rgba(26,58,42,.25);
}
.btn-deploy:hover { background: var(--forest-light); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(26,58,42,.3); }
.btn-deploy.saved { background: #1a7a3f; }

/* ── Modals ─────────────────────────────────────────────────── */
.modal-content {
    border: 0 !important; border-radius: var(--radius-lg) !important;
    overflow: hidden; box-shadow: var(--shadow-lg);
}
.modal-header { border-bottom: 0 !important; padding: 28px 28px 0 !important; }
.modal-body   { padding: 20px 28px 28px !important; }
.modal-footer { border-top: 1px solid var(--border) !important; padding: 16px 28px !important; }

.modal-header-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: var(--lime-glow); color: var(--forest);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; margin-bottom: 14px;
}

.form-label { font-size: .78rem; font-weight: 700; color: var(--ink); margin-bottom: 7px; }
.form-control, .form-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem; font-weight: 500;
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 10px 14px; color: var(--ink); background: var(--surface-2);
    transition: var(--transition);
}
.form-control:focus, .form-select:focus {
    border-color: var(--forest); background: #fff;
    box-shadow: 0 0 0 3px rgba(26,58,42,.08); outline: none;
}
textarea.form-control { resize: vertical; min-height: 88px; }

/* ── Animate ────────────────────────────────────────────────── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; } to { opacity: 1; }
}
.fade-in { animation: fadeIn .25s ease both; }

/* ── Utilities ──────────────────────────────────────────────── */
.fw-800 { font-weight: 800 !important; }
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4 page-canvas">

        <!-- Breadcrumb -->
        <nav class="mb-1" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item active">Roles & Permissions</li>
            </ol>
        </nav>

        <?php flash_render(); ?>

        <!-- ═══ PAGE HERO ══════════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div class="hero-inner">
                <div>
                    <div class="hero-rbac-chip"><i class="bi bi-shield-lock-fill"></i>RBAC Console V24</div>
                    <h1 class="hero-title">Roles & Permissions</h1>
                    <p class="hero-sub">Configure access levels with <span class="accent">surgical precision</span>. Changes save instantly.</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Total Roles</div>
                            <div class="hero-stat-value lime"><?= count($roles_data) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Permission Groups</div>
                            <div class="hero-stat-value"><?= count($perms_by_cat) ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Total Permissions</div>
                            <div class="hero-stat-value"><?= array_sum(array_map('count', $perms_by_cat)) ?></div>
                        </div>
                    </div>
                </div>
                <div>
                    <button class="btn btn-lime rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>Create New Role
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ ROLE SELECTOR ═════════════════════════════════════════════ -->
        <div class="role-selector-card">
            <div style="flex:1">
                <div class="role-selector-label">Select Role to Configure</div>
                <div class="role-selector-wrap">
                    <div class="role-selector-icon"><i class="bi bi-person-badge-fill"></i></div>
                    <select id="roleSelector">
                        <?php foreach ($roles_data as $role): ?>
                            <option value="role_<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="selector-divider"></div>
            <div class="selector-stat">
                <div class="selector-stat-label">Active Roles</div>
                <div class="selector-stat-value"><?= count($roles_data) ?></div>
            </div>
        </div>

        <!-- ═══ ROLES CONTAINER ═══════════════════════════════════════════ -->
        <div id="rolesContainer">
        <?php foreach ($roles_data as $index => $role):
            $is_super = ($role['id'] == 1);
            $assigned_perms = $active_map[$role['id']] ?? [];
            $perm_count = count($assigned_perms);
            $display = ($index === 0) ? 'block' : 'none';

            $my_categories = [];
            if (defined('SUPPORT_ROUTING_MAP')) {
                foreach (SUPPORT_ROUTING_MAP as $cat => $rname) {
                    if ($rname === $role['name']) $my_categories[] = ucfirst($cat);
                }
            }
        ?>
        <div class="role-config-card" id="role_<?= $role['id'] ?>" style="display:<?= $display ?>; opacity:1; transition:opacity .2s ease;">
            <div class="role-card <?= $is_super ? 'superadmin' : '' ?>">

                <!-- Role card header -->
                <div class="role-card-header">
                    <div>
                        <div class="role-title">
                            <?= htmlspecialchars($role['name']) ?>
                            <?php if ($is_super): ?>
                                <span class="locked-badge"><i class="bi bi-shield-lock-fill"></i>System Locked</span>
                            <?php endif; ?>
                        </div>
                        <p class="role-desc"><?= htmlspecialchars($role['description'] ?? 'No description defined.') ?></p>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="badge-count" id="badge_<?= $role['id'] ?>"><?= $perm_count ?> permissions active</span>
                        <?php if (!$is_super): ?>
                        <div class="role-action-group">
                            <button class="btn-icon-sm edit"
                                    title="Edit Role"
                                    onclick="editRole(<?= $role['id'] ?>, '<?= htmlspecialchars(addslashes($role['name'])) ?>', '<?= htmlspecialchars(addslashes($role['description'] ?? '')) ?>')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon-sm del"
                                    title="Delete Role"
                                    onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars(addslashes($role['name'])) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="role-card-body">

                    <!-- Support categories strip -->
                    <?php if (!empty($my_categories) || $is_super): ?>
                    <div class="support-strip">
                        <div class="support-strip-label"><i class="bi bi-headset"></i>Support Access</div>
                        <?php if ($is_super): ?>
                            <span class="support-chip"><i class="bi bi-infinity"></i>All Categories (Full Access)</span>
                        <?php else: foreach ($my_categories as $cat_name): ?>
                            <span class="support-chip"><i class="bi bi-tag-fill"></i><?= $cat_name ?></span>
                        <?php endforeach; endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Permission groups -->
                    <?php
                    $cat_icons = [
                        'Member Management'     => 'people-fill',
                        'People & Access'       => 'person-badge-fill',
                        'Financial Management'  => 'cash-stack',
                        'Loans & Credit'        => 'bank2',
                        'Welfare Module'        => 'heart-pulse-fill',
                        'Investments & Assets'  => 'buildings-fill',
                        'Reports & Exports'     => 'bar-chart-fill',
                        'System Control Center' => 'display-fill',
                        'Maintenance & Config'  => 'database-fill-check',
                        'My Account'            => 'person-circle',
                        'General'               => 'grid-1x2-fill',
                    ];
                    foreach ($perms_by_cat as $category => $permissions):
                        $cat_perm_count = count($permissions);
                        $cat_active     = count(array_filter($permissions, fn($p) => in_array($p['id'], $assigned_perms)));
                        $icon = $cat_icons[$category] ?? 'gear-fill';
                    ?>
                    <div class="perm-category">
                        <div class="category-header">
                            <div class="category-icon"><i class="bi bi-<?= $icon ?>"></i></div>
                            <span class="category-name"><?= strtoupper($category) ?></span>
                            <span class="category-count"><?= $cat_active ?> / <?= $cat_perm_count ?> active</span>
                        </div>
                        <div class="perm-grid">
                            <?php foreach ($permissions as $perm):
                                $is_active  = in_array($perm['id'], $assigned_perms);
                                $is_locked  = $is_super;
                            ?>
                            <label class="perm-checkbox <?= $is_active ? 'active' : '' ?> <?= $is_locked ? 'locked' : '' ?>">
                                <input type="checkbox"
                                       <?= $is_locked ? 'disabled' : '' ?>
                                       <?= $is_active ? 'checked' : '' ?>
                                       onchange="togglePermission(<?= $role['id'] ?>, <?= $perm['id'] ?>, this)">
                                <span class="perm-label"><?= htmlspecialchars($perm['name']) ?></span>
                                <?php if ($is_active): ?>
                                    <i class="bi bi-check-circle-fill perm-active-icon fade-in"></i>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div><!-- /role-card-body -->

                <!-- Deploy bar -->
                <?php if (!$is_super): ?>
                <div class="deploy-bar">
                    <div class="deploy-hint">
                        <i class="bi bi-check2-circle"></i>
                        Permission toggles save automatically via AJAX.
                    </div>
                    <button class="btn-deploy" onclick="simulateSave(this)">
                        <i class="bi bi-shield-check me-2"></i>Deploy Security Configuration
                    </button>
                </div>
                <?php else: ?>
                <div class="deploy-bar" style="background:#fffdf0;border-top-color:#f0d080">
                    <div class="deploy-hint" style="color:#92660a">
                        <i class="bi bi-lock-fill" style="color:#b88a00"></i>
                        Superadmin configuration is system-locked and cannot be modified.
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
        </div><!-- /rolesContainer -->

        <!-- ═══ ADD ROLE MODAL ════════════════════════════════════════════ -->
        <div class="modal fade" id="addRoleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
                <form class="modal-content" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_role">
                    <div class="modal-header">
                        <div>
                            <div class="modal-header-icon"><i class="bi bi-plus-circle-fill"></i></div>
                            <h5 class="fw-800 mb-1" style="color:var(--ink)">Create New Role</h5>
                            <p class="text-muted mb-0" style="font-size:.82rem">Define a new access level for staff members.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" name="role_name" class="form-control" required placeholder="e.g. Loan Officer">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="role_desc" class="form-control" placeholder="What responsibilities does this role carry?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end gap-2">
                        <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-lime rounded-pill fw-bold px-5">Create Role</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══ EDIT ROLE MODAL ═══════════════════════════════════════════ -->
        <div class="modal fade" id="editRoleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
                <form class="modal-content" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="edit_role">
                    <input type="hidden" name="role_id" id="edit_role_id">
                    <div class="modal-header">
                        <div>
                            <div class="modal-header-icon" style="background:#e8f0fe;color:#1a6fc4"><i class="bi bi-pencil-square"></i></div>
                            <h5 class="fw-800 mb-1" style="color:var(--ink)">Edit Role</h5>
                            <p class="text-muted mb-0" style="font-size:.82rem">Update the role name and description.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" name="role_name" id="edit_role_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="role_desc" id="edit_role_desc" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end gap-2">
                        <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-lime rounded-pill fw-bold px-5">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Form (hidden) -->
        <form method="POST" id="deleteForm" style="display:none">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" id="delete_role_id">
        </form>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
// ── Role Selector ────────────────────────────────────────────
const selector = document.getElementById('roleSelector');
if (selector) {
    selector.addEventListener('change', function () {
        const selectedId = this.value;
        document.querySelectorAll('.role-config-card').forEach(card => {
            card.style.display = 'none';
            card.style.opacity = '0';
        });
        const target = document.getElementById(selectedId);
        if (target) {
            target.style.display = 'block';
            requestAnimationFrame(() => {
                target.style.transition = 'opacity .25s ease';
                target.style.opacity = '1';
            });
        }
    });
}

// ── Deploy button ────────────────────────────────────────────
function simulateSave(btn) {
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Saved Successfully';
    btn.classList.add('saved');
    btn.disabled = true;
    setTimeout(() => {
        btn.innerHTML = original;
        btn.classList.remove('saved');
        btn.disabled = false;
    }, 2200);
}

// ── Permission Toggle ────────────────────────────────────────
function togglePermission(roleId, permId, checkbox) {
    const parent = checkbox.closest('.perm-checkbox');

    fetch('roles.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `toggle_permission=1&role_id=${roleId}&perm_id=${permId}&status=${checkbox.checked}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            parent.classList.toggle('active', checkbox.checked);

            const existingIcon = parent.querySelector('.perm-active-icon');
            if (checkbox.checked && !existingIcon) {
                const icon = document.createElement('i');
                icon.className = 'bi bi-check-circle-fill perm-active-icon fade-in';
                parent.appendChild(icon);
            } else if (!checkbox.checked && existingIcon) {
                existingIcon.remove();
            }

            // Update badge count
            const card  = parent.closest('.role-config-card');
            const badge = card ? card.querySelector('.badge-count') : null;
            if (badge) {
                const current = parseInt(badge.textContent);
                badge.textContent = (checkbox.checked ? current + 1 : Math.max(0, current - 1)) + ' permissions active';
            }

            // Update category count
            const catSection  = parent.closest('.perm-category');
            const catCountEl  = catSection ? catSection.querySelector('.category-count') : null;
            if (catCountEl) {
                const match = catCountEl.textContent.match(/(\d+) \/ (\d+)/);
                if (match) {
                    const active = parseInt(match[1]) + (checkbox.checked ? 1 : -1);
                    catCountEl.textContent = `${Math.max(0, active)} / ${match[2]} active`;
                }
            }
        } else {
            alert(data.message || 'Error updating permission.');
            checkbox.checked = !checkbox.checked;
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        checkbox.checked = !checkbox.checked;
    });
}

// ── Edit / Delete helpers ────────────────────────────────────
function editRole(id, name, desc) {
    document.getElementById('edit_role_id').value   = id;
    document.getElementById('edit_role_name').value = name;
    document.getElementById('edit_role_desc').value = desc;
    new bootstrap.Modal(document.getElementById('editRoleModal')).show();
}

function deleteRole(id, name) {
    if (confirm(`Delete the role "${name}"?\n\nThis action cannot be undone.`)) {
        document.getElementById('delete_role_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
</body>
</html>