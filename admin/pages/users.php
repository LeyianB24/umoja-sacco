<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/HRService.php';
require_once __DIR__ . '/../../inc/SystemUserService.php';

$layout = LayoutManager::create('admin');
$hrService = new HRService($conn);
$systemUserService = new SystemUserService();

$pageTitle = "System Users";
require_superadmin();

// ---------------------------------------------------------
// 1. POST ACTIONS — must run before any HTML output
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();

    if ($_POST['action'] === 'add_user') {
        $fullname = trim($_POST['full_name']);
        $email    = trim($_POST['email']);
        $username = trim($_POST['username']);
        $role_id  = (int)$_POST['role_id'];
        $password = trim($_POST['password']);

        $check = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? OR username = ?");
        $check->bind_param("ss", $email, $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            flash_set("Email or Username already exists.", "danger");
        } else {
            $userData = ['employee_no' => $username, 'company_email' => $email, 'full_name' => $fullname];
            $result = $systemUserService->createSystemUser($userData, $role_id);

            if ($result['success']) {
                if (!empty($password) && $password !== $username) {
                    $systemUserService->resetPassword($result['admin_id'], $password);
                }
                // Sync to employees table
                $empData = [
                    'full_name'  => $fullname,
                    'national_id'=> 'SYS-' . $result['admin_id'],
                    'phone'      => '',
                    'job_title'  => 'System Administrator',
                    'grade_id'   => 1,
                    'salary'     => 0.00,
                    'hire_date'  => date('Y-m-d')
                ];
                $empResult = $hrService->createEmployee($empData);
                if ($empResult['success']) {
                    $stmt = $conn->prepare("UPDATE employees SET admin_id = ? WHERE employee_id = ?");
                    $stmt->bind_param("ii", $result['admin_id'], $empResult['employee_id']);
                    $stmt->execute();
                }
                flash_set("Staff account created successfully.", "success");
            } else {
                flash_set("Error: " . $result['error'], "danger");
            }
        }
    }

    if ($_POST['action'] === 'edit_user') {
        $id       = (int)$_POST['admin_id'];
        $fullname = trim($_POST['full_name']);
        $email    = trim($_POST['email']);
        $role_id  = (int)$_POST['role_id'];
        $password = trim($_POST['password'] ?? '');

        if ($id > 0) {
            // Direct mysqli update — avoids PDO/mysqli mismatch on $systemUserService
            $stmt = $conn->prepare("UPDATE admins SET full_name = ?, email = ?, role_id = ? WHERE admin_id = ?");
            $stmt->bind_param("ssii", $fullname, $email, $role_id, $id);
            $stmt->execute();

            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $ps = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
                $ps->bind_param("si", $hashed, $id);
                $ps->execute();
            }

            // Sync full_name to linked employee record
            $sync = $conn->prepare("UPDATE employees SET full_name = ? WHERE admin_id = ?");
            $sync->bind_param("si", $fullname, $id);
            $sync->execute();

            // Audit log
            $admin_id   = $_SESSION['admin_id'] ?? 0;
            $log_detail = "Edited system user ID $id (name: $fullname, role: $role_id).";
            $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $log_action = 'User Updated';
            $stmt_log   = $conn->prepare("INSERT INTO audit_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->bind_param("isss", $admin_id, $log_action, $log_detail, $ip);
            $stmt_log->execute();

            flash_set("User profile updated successfully.", "success");
        } else {
            flash_set("Invalid user ID.", "danger");
        }
    }

    header("Location: users.php"); exit;
}

// ---------------------------------------------------------
// 2. DELETE
// ---------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    if ($del_id === ($_SESSION['admin_id'] ?? 0)) {
        flash_set("Self-deletion is not allowed.", "danger");
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            flash_set("Staff account deleted.", "warning");
        } else {
            flash_set("User not found.", "danger");
        }
    }
    header("Location: users.php"); exit;
}

// ---------------------------------------------------------
// 3. FETCH DATA for display
// ---------------------------------------------------------
$roles_res  = $conn->query("SELECT * FROM roles ORDER BY name ASC");
$roles_list = [];
while ($r = $roles_res->fetch_assoc()) $roles_list[] = $r;

$users_res = $conn->query("SELECT a.*, r.name as role_name FROM admins a LEFT JOIN roles r ON a.role_id = r.id ORDER BY a.created_at DESC");
$all_users = [];
while ($u = $users_res->fetch_assoc()) $all_users[] = $u;

$total_staff = count($all_users);
$role_counts = [];
foreach ($all_users as $u) {
    $rn = $u['role_name'] ?? 'No Role';
    $role_counts[$rn] = ($role_counts[$rn] ?? 0) + 1;
}
$total_roles = count($role_counts);
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
.detail-card,
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
    --shadow-xs:    0 1px 3px rgba(26,58,42,.06);
    --shadow-sm:    0 4px 12px rgba(26,58,42,.08);
    --shadow-md:    0 8px 28px rgba(26,58,42,.12);
    --shadow-lg:    0 16px 48px rgba(26,58,42,.16);
    --radius-sm:    10px;
    --radius-md:    16px;
    --radius-lg:    22px;
    --radius-xl:    30px;
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
    background: none;
    padding: 0;
    margin: 0 0 28px;
    font-size: .8rem;
    font-weight: 500;
    gap: 2px;
}
.breadcrumb-item a {
    color: var(--muted);
    text-decoration: none;
    transition: var(--transition);
}
.breadcrumb-item a:hover { color: var(--forest); }
.breadcrumb-item.active { color: var(--ink); font-weight: 600; }
.breadcrumb-item + .breadcrumb-item::before { color: var(--border); }

/* ── Page header ────────────────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, var(--forest-light) 100%);
    border-radius: var(--radius-lg);
    padding: 32px 40px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.page-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 60% 80% at 90% -10%, rgba(168,224,99,.2) 0%, transparent 60%),
        radial-gradient(ellipse 40% 50% at -5% 100%, rgba(168,224,99,.08) 0%, transparent 55%);
    pointer-events: none;
}
.page-header::after {
    content: '';
    position: absolute;
    right: -60px;
    top: -60px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    border: 1px solid rgba(168,224,99,.1);
    pointer-events: none;
}

.page-header-text h2 {
    font-size: clamp(1.35rem, 2.5vw, 1.75rem);
    font-weight: 800;
    color: #fff;
    margin: 0 0 6px;
    letter-spacing: -.4px;
}
.page-header-text p {
    font-size: .85rem;
    color: rgba(255,255,255,.65);
    margin: 0;
    font-weight: 500;
}

/* Stat chips in header */
.header-stats {
    display: flex;
    gap: 12px;
    margin-top: 16px;
    flex-wrap: wrap;
}
.header-stat {
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: var(--radius-sm);
    padding: 10px 16px;
    backdrop-filter: blur(4px);
    min-width: 90px;
}
.header-stat-label {
    font-size: .65rem;
    font-weight: 600;
    letter-spacing: .5px;
    text-transform: uppercase;
    color: rgba(255,255,255,.5);
    margin-bottom: 3px;
}
.header-stat-value {
    font-size: 1.15rem;
    font-weight: 800;
    color: #fff;
}
.header-stat-value.lime { color: var(--lime); }

/* ── Btn lime / forest ──────────────────────────────────────── */
.btn-lime {
    background: var(--lime);
    color: var(--ink);
    border: none;
    font-weight: 700;
    font-size: .85rem;
    transition: var(--transition);
    box-shadow: 0 4px 14px rgba(168,224,99,.4);
}
.btn-lime:hover {
    background: #baea78;
    color: var(--ink);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(168,224,99,.5);
}
.btn-forest {
    background: var(--forest);
    color: #fff;
    border: none;
    font-weight: 700;
    font-size: .85rem;
    transition: var(--transition);
}
.btn-forest:hover {
    background: var(--forest-light);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(26,58,42,.3);
}

/* ── Detail / table card ────────────────────────────────────── */
.detail-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    animation: fadeUp .4s ease both;
    animation-delay: .1s;
}
.detail-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }

.card-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 12px;
}
.card-toolbar-title {
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--forest);
    display: flex;
    align-items: center;
    gap: 8px;
}
.card-toolbar-title i {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--lime-glow);
    color: var(--forest);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
}

/* Search bar */
.search-wrap {
    position: relative;
    flex: 1;
    max-width: 280px;
}
.search-wrap i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    font-size: .85rem;
    pointer-events: none;
}
.search-wrap input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    font-size: .82rem;
    font-weight: 500;
    border: 1.5px solid var(--border);
    border-radius: 100px;
    background: var(--surface-2);
    color: var(--ink);
    transition: var(--transition);
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}
.search-wrap input:focus {
    outline: none;
    border-color: var(--forest);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(26,58,42,.07);
}

/* ── Table ──────────────────────────────────────────────────── */
.staff-table { width: 100%; border-collapse: collapse; }
.staff-table thead th {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .8px;
    text-transform: uppercase;
    color: var(--muted);
    background: var(--surface-2);
    padding: 13px 16px;
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
}
.staff-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: var(--transition);
}
.staff-table tbody tr:last-child { border-bottom: none; }
.staff-table tbody tr:hover { background: #f9fcf9; }
.staff-table td { padding: 16px; vertical-align: middle; }

/* Avatar */
.avatar-box {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--forest), var(--forest-light));
    color: var(--lime);
    font-weight: 800;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 2px solid var(--border);
    transition: var(--transition);
}
tr:hover .avatar-box {
    border-color: var(--lime);
    box-shadow: 0 0 0 3px var(--lime-glow);
}

.user-profile-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-name {
    font-size: .9rem;
    font-weight: 700;
    color: var(--ink);
    line-height: 1.3;
}
.user-handle {
    font-size: .75rem;
    color: var(--muted);
    font-weight: 500;
}

/* Role badge */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .3px;
    background: var(--lime-glow);
    color: var(--forest);
    border: 1px solid rgba(168,224,99,.35);
    border-radius: 100px;
    padding: 4px 12px;
    white-space: nowrap;
}
.role-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--forest);
    opacity: .5;
}

/* You badge */
.you-badge {
    display: inline-flex;
    align-items: center;
    font-size: .6rem;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
    background: var(--forest);
    color: var(--lime);
    border-radius: 4px;
    padding: 2px 6px;
    margin-left: 6px;
    vertical-align: middle;
}

/* Contact cell */
.contact-cell {
    font-size: .8rem;
    color: var(--muted);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}
.contact-cell i { opacity: .6; }

/* Date cell */
.date-cell {
    font-size: .78rem;
    color: var(--muted);
    font-weight: 500;
    white-space: nowrap;
}

/* Action buttons */
.action-cell { text-align: right; white-space: nowrap; }
.btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: 1.5px solid var(--border);
    background: var(--surface);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
    transition: var(--transition);
    cursor: pointer;
    text-decoration: none;
}
.btn-icon.edit {
    color: var(--forest);
}
.btn-icon.edit:hover {
    background: var(--lime-glow);
    border-color: var(--lime);
    color: var(--forest);
    transform: translateY(-1px);
}
.btn-icon.del {
    color: #c0392b;
    margin-left: 6px;
}
.btn-icon.del:hover {
    background: #fef0ef;
    border-color: #f5c6c6;
    color: #c0392b;
    transform: translateY(-1px);
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--muted);
}
.empty-state i { font-size: 2.5rem; opacity: .2; display: block; margin-bottom: 12px; }
.empty-state p { font-size: .85rem; margin: 0; }

/* ── Modals ─────────────────────────────────────────────────── */
.modal-content {
    border: 0 !important;
    border-radius: var(--radius-lg) !important;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.modal-header { border-bottom: 0 !important; padding: 28px 28px 0 !important; }
.modal-body   { padding: 20px 28px 28px !important; }

.modal-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--lime-glow);
    color: var(--forest);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    margin-bottom: 14px;
}

.form-label {
    font-size: .78rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 7px;
}
.form-control, .form-select {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .85rem;
    font-weight: 500;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 10px 14px;
    color: var(--ink);
    background: var(--surface-2);
    transition: var(--transition);
}
.form-control:focus, .form-select:focus {
    border-color: var(--forest);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(26,58,42,.08);
    outline: none;
}

.password-hint {
    font-size: .73rem;
    color: var(--muted);
    margin-top: 5px;
    font-weight: 500;
}

.modal-submit-btn {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: none;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-weight: 700;
    font-size: .88rem;
    cursor: pointer;
    transition: var(--transition);
    margin-top: 6px;
}
.modal-submit-btn.forest {
    background: var(--forest);
    color: #fff;
}
.modal-submit-btn.forest:hover {
    background: var(--forest-light);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(26,58,42,.3);
}
.modal-submit-btn.primary {
    background: #1a6fc4;
    color: #fff;
}
.modal-submit-btn.primary:hover {
    background: #1560ae;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(26,111,196,.3);
}

.field-divider {
    height: 1px;
    background: var(--border);
    margin: 18px 0;
}

/* ── Animate in ─────────────────────────────────────────────── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.page-header { animation: fadeUp .35s ease both; }
.detail-card { animation: fadeUp .4s ease both; animation-delay: .08s; }

/* ── Utilities ──────────────────────────────────────────────── */
.fw-800 { font-weight: 800 !important; }
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4 page-canvas">

        <?php flash_render(); ?>

        <?php $pageTitle = "System Users"; ?>

        <!-- Breadcrumb -->
        <nav class="mb-1" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item active">Staff Management</li>
            </ol>
        </nav>

        <!-- ═══ PAGE HEADER ════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div style="position:relative;z-index:1">
                <div class="page-header-text">
                    <h2>Administrative Staff</h2>
                    <p>Manage personnel and assign them to specific roles.</p>
                </div>
                <div class="header-stats">
                    <div class="header-stat">
                        <div class="header-stat-label">Total Staff</div>
                        <div class="header-stat-value lime"><?= $total_staff ?></div>
                    </div>
                    <div class="header-stat">
                        <div class="header-stat-label">Roles Active</div>
                        <div class="header-stat-value"><?= $total_roles ?></div>
                    </div>
                </div>
            </div>
            <div style="position:relative;z-index:1">
                <button class="btn btn-lime rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-2"></i>New Staff Member
                </button>
            </div>
        </div>

        <!-- ═══ STAFF TABLE CARD ═══════════════════════════════════════ -->
        <div class="detail-card">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-people-fill d-flex"></i>
                    Staff Directory
                </div>
                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="staffSearch" placeholder="Search name, username, email…" oninput="filterTable()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="staff-table" id="staffTable">
                    <thead>
                        <tr>
                            <th style="padding-left:24px">User Profile</th>
                            <th>Assigned Role</th>
                            <th>Contact Details</th>
                            <th>Joined</th>
                            <th style="text-align:right;padding-right:24px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <?php if (empty($all_users)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-people"></i>
                                        <p>No staff members found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($all_users as $u):
                            $isMe = ($u['admin_id'] == $_SESSION['admin_id']);
                        ?>
                        <tr data-search="<?= strtolower(htmlspecialchars($u['full_name'] . ' ' . $u['username'] . ' ' . $u['email'])) ?>">
                            <td style="padding-left:24px">
                                <div class="user-profile-cell">
                                    <div class="avatar-box"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
                                    <div>
                                        <div class="user-name">
                                            <?= htmlspecialchars($u['full_name']) ?>
                                            <?php if ($isMe): ?><span class="you-badge">You</span><?php endif; ?>
                                        </div>
                                        <div class="user-handle">@<?= htmlspecialchars($u['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="role-badge"><?= htmlspecialchars($u['role_name'] ?? 'No Role') ?></span>
                            </td>
                            <td>
                                <div class="contact-cell">
                                    <i class="bi bi-envelope"></i>
                                    <?= htmlspecialchars($u['email']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="date-cell"><?= date('d M Y', strtotime($u['created_at'])) ?></span>
                            </td>
                            <td class="action-cell" style="padding-right:24px">
                                <button class="btn-icon edit" title="Edit" onclick='openEditModal(<?= json_encode($u) ?>)'>
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php if (!$isMe): ?>
                                    <a href="?delete_id=<?= $u['admin_id'] ?>" class="btn-icon del" title="Delete" onclick="return confirm('Permanently delete this staff account?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ ADD USER MODAL ════════════════════════════════════════ -->
        <div class="modal fade" id="addUserModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
                <form class="modal-content" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-header">
                        <div>
                            <div class="modal-header-icon"><i class="bi bi-person-plus-fill"></i></div>
                            <h5 class="fw-800 mb-1" style="color:var(--ink)">Register New Staff</h5>
                            <p class="text-muted mb-0" style="font-size:.82rem">Create a new administrative account.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" placeholder="e.g. Jane Mwangi" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="jane@sacco.co.ke" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" placeholder="jane.admin" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign Role</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">Select a role…</option>
                                <?php foreach ($roles_list as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-divider"></div>
                        <div class="mb-3">
                            <label class="form-label">Temporary Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min. 6 characters">
                            <div class="password-hint"><i class="bi bi-info-circle me-1"></i>User should change this on first login.</div>
                        </div>
                        <button type="submit" class="modal-submit-btn forest">
                            <i class="bi bi-person-check-fill me-2"></i>Initialize Account
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══ EDIT USER MODAL ══════════════════════════════════════ -->
        <div class="modal fade" id="editUserModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
                <form class="modal-content" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="modal-header">
                        <div>
                            <div class="modal-header-icon" style="background:#e8f0fe;color:#1a6fc4"><i class="bi bi-pencil-square"></i></div>
                            <h5 class="fw-800 mb-1" style="color:var(--ink)">Modify Staff Member</h5>
                            <p class="text-muted mb-0" style="font-size:.82rem">Update profile details and role assignment.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role Assignment</label>
                            <select name="role_id" id="edit_role_id" class="form-select" required>
                                <?php foreach ($roles_list as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-divider"></div>
                        <div class="mb-3">
                            <label class="form-label">Force New Password <span class="text-muted" style="font-weight:400">(leave blank to keep current)</span></label>
                            <input type="password" name="password" class="form-control" minlength="6" placeholder="Enter new password…">
                            <div class="password-hint"><i class="bi bi-shield-check me-1"></i>Only fill this if you need to reset the password.</div>
                        </div>
                        <button type="submit" class="modal-submit-btn primary">
                            <i class="bi bi-check-lg me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /container-fluid -->

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
function openEditModal(data) {
    document.getElementById('edit_admin_id').value  = data.admin_id;
    document.getElementById('edit_full_name').value = data.full_name;
    document.getElementById('edit_email').value     = data.email;
    document.getElementById('edit_role_id').value   = data.role_id;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function filterTable() {
    const q = document.getElementById('staffSearch').value.toLowerCase().trim();
    document.querySelectorAll('#staffTableBody tr[data-search]').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>