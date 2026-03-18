<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SettingsHelper.php';

// AUTH CHECK
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_id = $_SESSION['admin_id'];
$db = $conn;

// Get User Data with Role Name
$me_res = $db->query("SELECT a.*, r.name as role_name FROM admins a LEFT JOIN roles r ON a.role_id = r.id WHERE a.admin_id = $admin_id");
$me = $me_res->fetch_assoc();

// --- LOGIC: Profile Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    verify_csrf_token();
    $fullname = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $username = trim($_POST['username']);

    if (!$fullname || !$email || !$username) {
        flash_set("All fields are required.", 'error');
    } else {
        $stmt = $db->prepare("UPDATE admins SET full_name = ?, email = ?, username = ? WHERE admin_id = ?");
        $stmt->bind_param("sssi", $fullname, $email, $username, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $fullname;
            flash_set("Profile updated successfully.", 'success');
            $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'profile_update', 'Updated Profile', '{$_SERVER['REMOTE_ADDR']}')");
        } else {
            flash_set("Update failed: " . $db->error, 'error');
        }
    }
}

// --- LOGIC: Password Change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verify_csrf_token();
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $db->prepare("SELECT password FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $user['password'])) {
        flash_set("Incorrect current password.", 'error');
    } elseif ($new !== $confirm) {
        flash_set("New passwords do not match.", 'error');
    } elseif (strlen($new) < 6) {
        flash_set("Password must be at least 6 characters.", 'error');
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $db->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
        $upd->bind_param("si", $hash, $admin_id);
        if ($upd->execute()) {
            flash_set("Password updated successfully.", 'success');
        } else {
            flash_set("Password update failed: " . $db->error, 'error');
        }
    }
}

// --- LOGIC: System Configuration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_system_settings'])) {
    verify_csrf_token();
    if ($_SESSION['role_id'] != 1) {
        flash_set("Unauthorized action.", 'error');
    } else {
        $fields = [
            'site_name', 'site_short_name', 'site_tagline',
            'company_email', 'company_phone', 'company_address',
            'social_facebook', 'social_twitter', 'social_instagram', 'social_youtube'
        ];
        $success_count = 0;
        foreach ($fields as $field) {
            $key = strtoupper($field);
            if (isset($_POST[$field])) {
                if (SettingsHelper::set($key, trim($_POST[$field]), $admin_id)) {
                    $success_count++;
                }
            }
        }
        if ($success_count > 0) {
            flash_set("System settings updated successfully.", 'success');
            if (isset($db)) {
               $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'system_update', 'Updated Global Configuration', '{$_SERVER['REMOTE_ADDR']}')");
            }
        } else {
            flash_set("No changes were saved.", 'warning');
        }
    }
}

// Fetch System Settings
$sys_settings = SettingsHelper::all();

if (!function_exists('getInitials')) {
    function getInitials($name) {
        if (!$name) return '??';
        $words = explode(" ", $name);
        $acronym = "";
        foreach ($words as $w) if(!empty($w)) $acronym .= mb_substr($w, 0, 1);
        return strtoupper(substr($acronym, 0, 2)) ?: '??';
    }
}
$server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$php_v     = phpversion();
$pageTitle = 'System Settings';
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ─── Base ─── */
*, body, .main-content-wrapper {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ─── Hero ─── */
.gs-hero {
    background: linear-gradient(135deg, #0F392B 0%, #1a5c43 55%, #0d2e22 100%);
    border-radius: 20px;
    padding: 36px 40px;
    position: relative;
    overflow: hidden;
    margin-bottom: 28px;
}
.gs-hero::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 320px; height: 320px;
    background: radial-gradient(circle, rgba(57,181,74,0.15) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.gs-hero::after {
    content: '';
    position: absolute;
    bottom: -50px; left: 25%;
    width: 240px; height: 240px;
    background: radial-gradient(circle, rgba(163,230,53,0.08) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.gs-hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 100px;
    padding: 5px 14px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.75);
    text-transform: uppercase;
    margin-bottom: 14px;
}
.gs-hero-eyebrow i { color: #A3E635; }
.gs-hero h1 {
    font-size: 2.4rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 8px;
    letter-spacing: -0.5px;
    line-height: 1.1;
}
.gs-hero-sub {
    color: rgba(255,255,255,0.58);
    font-size: 0.9rem;
    font-weight: 500;
    margin: 0;
}
.gs-hero-sub strong { color: #A3E635; font-weight: 700; }
.gs-server-badge {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 16px;
    padding: 18px 24px;
    backdrop-filter: blur(10px);
}
.gs-server-badge .label {
    font-size: 0.65rem;
    font-weight: 700;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 4px;
}
.gs-server-badge .value {
    font-size: 1.2rem;
    font-weight: 800;
    color: #A3E635;
    display: flex;
    align-items: center;
    gap: 8px;
}
.gs-pulse { 
    width: 8px; height: 8px;
    background: #A3E635;
    border-radius: 50%;
    box-shadow: 0 0 0 3px rgba(163,230,53,0.3);
    animation: gsPulse 2s ease-in-out infinite;
}
@keyframes gsPulse {
    0%,100% { box-shadow: 0 0 0 3px rgba(163,230,53,0.3); }
    50% { box-shadow: 0 0 0 6px rgba(163,230,53,0.1); }
}

/* ─── Profile Card ─── */
.gs-profile-card {
    background: linear-gradient(160deg, #0F392B 0%, #1e6645 100%);
    border-radius: 20px;
    padding: 28px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    margin-bottom: 16px;
}
.gs-profile-card::before {
    content: '';
    position: absolute;
    bottom: -40px; right: -40px;
    width: 160px; height: 160px;
    background: radial-gradient(circle, rgba(163,230,53,0.12) 0%, transparent 70%);
    border-radius: 50%;
}
.gs-avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #A3E635, #6fba1b);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    font-weight: 800;
    color: #0F392B;
    margin: 0 auto 14px;
    box-shadow: 0 0 0 4px rgba(163,230,53,0.25), 0 8px 24px rgba(0,0,0,0.25);
}
.gs-profile-name {
    font-size: 1rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 3px;
}
.gs-profile-email {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.5);
    margin: 0 0 18px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.gs-profile-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 4px;
}
.gs-stat-chip {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 10px 8px;
    text-align: center;
}
.gs-stat-chip .chip-label {
    font-size: 0.6rem;
    font-weight: 700;
    color: rgba(255,255,255,0.45);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: block;
    margin-bottom: 3px;
}
.gs-stat-chip .chip-val {
    font-size: 0.85rem;
    font-weight: 800;
    color: #A3E635;
}

/* ─── Nav Pills ─── */
.gs-nav-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #E8F0ED;
    box-shadow: 0 2px 16px rgba(15,57,43,0.06);
    overflow: hidden;
    padding: 10px;
}
.gs-nav-card .nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    border-radius: 12px;
    font-size: 0.84rem;
    font-weight: 700;
    color: #5a7a6e;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    transition: all 0.18s;
    margin-bottom: 2px;
    position: relative;
}
.gs-nav-card .nav-link .nav-icon {
    width: 32px; height: 32px;
    border-radius: 9px;
    background: #F0F7F4;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    color: #5a7a6e;
    transition: all 0.18s;
    flex-shrink: 0;
}
.gs-nav-card .nav-link:hover {
    color: #0F392B;
    background: #F7FBF9;
}
.gs-nav-card .nav-link:hover .nav-icon {
    background: #E0EDE7;
    color: #0F392B;
}
.gs-nav-card .nav-link.active {
    color: #0F392B;
    background: #F0F7F4;
}
.gs-nav-card .nav-link.active .nav-icon {
    background: linear-gradient(135deg, #39B54A, #2d9a3c);
    color: #fff;
}
.gs-nav-card .nav-link.active::after {
    content: '';
    position: absolute;
    right: 12px;
    width: 6px; height: 6px;
    background: #39B54A;
    border-radius: 50%;
}
.gs-nav-divider {
    height: 1px;
    background: #F0F7F4;
    margin: 6px 4px;
}
.gs-nav-section-label {
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: #a0b8b0;
    padding: 8px 14px 4px;
}

/* ─── Content Cards ─── */
.gs-card {
    background: #fff;
    border-radius: 20px;
    border: 1px solid #E8F0ED;
    box-shadow: 0 2px 20px rgba(15,57,43,0.06);
    overflow: hidden;
    animation: tabFadeIn 0.3s ease both;
}
@keyframes tabFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.gs-card-header {
    padding: 24px 32px 20px;
    border-bottom: 1px solid #F0F7F4;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.gs-card-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0F392B;
    margin: 0 0 2px;
}
.gs-card-subtitle {
    font-size: 0.75rem;
    color: #7a9e8e;
    font-weight: 500;
    margin: 0;
}
.gs-card-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #F0F7F4;
    color: #0F392B;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}
.gs-card-body { padding: 28px 32px 32px; }

/* ─── Form Controls ─── */
.gs-label {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #7a9e8e;
    display: block;
    margin-bottom: 7px;
}
.gs-input {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 0.875rem;
    font-weight: 600;
    color: #0F392B;
    background: #F7FBF9;
    border: 1.5px solid #E0EDE7;
    border-radius: 12px;
    padding: 11px 15px;
    width: 100%;
    outline: none;
    transition: all 0.2s;
    -webkit-appearance: none;
    appearance: none;
}
.gs-input:focus {
    border-color: #39B54A;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(57,181,74,0.08);
}
.gs-input::placeholder { color: #a8c5bb; }
.gs-input-group {
    display: flex;
    align-items: stretch;
    border: 1.5px solid #E0EDE7;
    border-radius: 12px;
    overflow: hidden;
    background: #F7FBF9;
    transition: all 0.2s;
}
.gs-input-group:focus-within {
    border-color: #39B54A;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(57,181,74,0.08);
}
.gs-input-group-prefix {
    background: #EEF5F1;
    border-right: 1.5px solid #E0EDE7;
    padding: 11px 14px;
    font-size: 0.85rem;
    color: #7a9e8e;
    font-weight: 700;
    display: flex;
    align-items: center;
    flex-shrink: 0;
}
.gs-input-group .gs-input {
    border: none;
    background: transparent;
    border-radius: 0;
    box-shadow: none;
}
.gs-input-group .gs-input:focus { background: transparent; box-shadow: none; }
.gs-textarea {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: 0.875rem;
    font-weight: 600;
    color: #0F392B;
    background: #F7FBF9;
    border: 1.5px solid #E0EDE7;
    border-radius: 12px;
    padding: 11px 15px;
    width: 100%;
    outline: none;
    resize: vertical;
    min-height: 80px;
    transition: all 0.2s;
    line-height: 1.6;
}
.gs-textarea:focus {
    border-color: #39B54A;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(57,181,74,0.08);
}

/* ─── Section Divider ─── */
.gs-section {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: #7a9e8e;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 28px 0 18px;
}
.gs-section::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #E0EDE7;
}
.gs-section i { color: #39B54A; font-size: 0.8rem; }

/* ─── Buttons ─── */
.gs-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #39B54A, #2d9a3c);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 11px 28px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.855rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(57,181,74,0.32);
    letter-spacing: 0.2px;
}
.gs-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(57,181,74,0.42); }
.gs-btn-primary:active { transform: translateY(0); }
.gs-btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #0F392B;
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 11px 28px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.855rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(15,57,43,0.2);
}
.gs-btn-secondary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(15,57,43,0.3); }

/* ─── Warning Box ─── */
.gs-warning {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-radius: 12px;
    padding: 13px 16px;
    font-size: 0.8rem;
    color: #92400e;
    font-weight: 500;
    line-height: 1.6;
}
.gs-warning i { color: #F59E0B; font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

/* ─── System Status ─── */
.gs-sys-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 20px;
}
.gs-sys-item {
    background: #F7FBF9;
    border: 1px solid #E0EDE7;
    border-radius: 14px;
    padding: 16px 18px;
}
.gs-sys-item .sys-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #7a9e8e;
    display: block;
    margin-bottom: 6px;
}
.gs-sys-item .sys-val {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0F392B;
    font-family: 'Courier New', monospace;
}
.gs-operational {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #D1FAE5;
    border: 1px solid #A7F3D0;
    border-radius: 12px;
    padding: 14px 20px;
    font-size: 0.85rem;
    font-weight: 800;
    color: #065f46;
}
.gs-operational .op-dot {
    width: 8px; height: 8px;
    background: #10B981;
    border-radius: 50%;
    animation: gsPulse 2s ease-in-out infinite;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.25);
}

/* ─── Flash / Alerts ─── */
.gs-flash {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 18px;
    border-radius: 14px;
    font-size: 0.83rem;
    font-weight: 600;
    margin-bottom: 20px;
    animation: tabFadeIn 0.3s ease both;
}
.gs-flash-success { background: #D1FAE5; color: #065f46; border: 1px solid #A7F3D0; }
.gs-flash-error   { background: #FEE2E2; color: #991b1b; border: 1px solid #FECACA; }
.gs-flash-warning { background: #FFFBEB; color: #92400e; border: 1px solid #FDE68A; }

/* ─── Responsive ─── */
@media (max-width: 768px) {
    .gs-hero { padding: 24px 20px; }
    .gs-hero h1 { font-size: 1.8rem; }
    .gs-card-header, .gs-card-body { padding-left: 20px; padding-right: 20px; }
    .gs-sys-grid { grid-template-columns: 1fr; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <!-- ── Hero ── -->
        <div class="gs-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="gs-hero-eyebrow">
                        <i class="bi bi-sliders2"></i> System Control Panel
                    </div>
                    <h1>Global Configuration.</h1>
                    <p class="gs-hero-sub">
                        Fine-tune your portal. Manage identity, security, and
                        global parameters with <strong>exclusive precision</strong>.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0 d-none d-lg-block">
                    <div class="gs-server-badge d-inline-flex">
                        <span class="label">Server Status</span>
                        <span class="value">
                            <span class="gs-pulse"></span>
                            Operational
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php flash_render(); ?>

        <div class="row g-4">

            <!-- ── LEFT SIDEBAR ── -->
            <div class="col-lg-4 col-xl-3">

                <!-- Profile Card -->
                <div class="gs-profile-card mb-4">
                    <div class="gs-avatar"><?= getInitials($me['full_name']) ?></div>
                    <div class="gs-profile-name"><?= htmlspecialchars($me['full_name']) ?></div>
                    <div class="gs-profile-email"><?= htmlspecialchars($me['email']) ?></div>
                    <div class="gs-profile-stats">
                        <div class="gs-stat-chip">
                            <span class="chip-label">Admin ID</span>
                            <span class="chip-val">#<?= $admin_id ?></span>
                        </div>
                        <div class="gs-stat-chip">
                            <span class="chip-label">Role</span>
                            <span class="chip-val"><?= htmlspecialchars($me['role_name'] ?? 'Staff') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Nav Tabs -->
                <div class="gs-nav-card">
                    <div class="gs-nav-section-label">Account</div>
                    <div class="nav flex-column" id="v-pills-tab" role="tablist">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#v-pills-profile">
                            <span class="nav-icon"><i class="bi bi-person-fill"></i></span>
                            Personal Info
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#v-pills-security">
                            <span class="nav-icon"><i class="bi bi-shield-lock-fill"></i></span>
                            Security
                        </button>
                        <?php if($_SESSION['role_id'] == 1): ?>
                        <div class="gs-nav-divider"></div>
                        <div class="gs-nav-section-label">Administration</div>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#v-pills-global">
                            <span class="nav-icon"><i class="bi bi-globe2"></i></span>
                            Global Config
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#v-pills-system">
                            <span class="nav-icon"><i class="bi bi-hdd-network-fill"></i></span>
                            System Status
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- ── RIGHT CONTENT ── -->
            <div class="col-lg-8 col-xl-9">
                <div class="tab-content" id="v-pills-tabContent">

                    <!-- ── Profile Tab ── -->
                    <div class="tab-pane fade show active" id="v-pills-profile">
                        <div class="gs-card">
                            <div class="gs-card-header">
                                <div>
                                    <p class="gs-card-title">Edit Profile</p>
                                    <p class="gs-card-subtitle">Update your personal information and display name</p>
                                </div>
                                <span class="gs-card-badge"><i class="bi bi-person-fill"></i> Personal</span>
                            </div>
                            <div class="gs-card-body">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="gs-label">Full Name</label>
                                            <input type="text" class="gs-input" name="full_name"
                                                value="<?= htmlspecialchars($me['full_name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="gs-label">Username</label>
                                            <div class="gs-input-group">
                                                <span class="gs-input-group-prefix">@</span>
                                                <input type="text" class="gs-input" name="username"
                                                    value="<?= htmlspecialchars($me['username']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="gs-label">Email Address</label>
                                            <input type="email" class="gs-input" name="email"
                                                value="<?= htmlspecialchars($me['email']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mt-5 d-flex justify-content-end">
                                        <button type="submit" name="update_profile" class="gs-btn-primary">
                                            Save Changes <i class="bi bi-check2 ms-1"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ── Security Tab ── -->
                    <div class="tab-pane fade" id="v-pills-security">
                        <div class="gs-card">
                            <div class="gs-card-header">
                                <div>
                                    <p class="gs-card-title">Security Settings</p>
                                    <p class="gs-card-subtitle">Change your password to keep your account secure</p>
                                </div>
                                <span class="gs-card-badge"><i class="bi bi-shield-lock-fill"></i> Security</span>
                            </div>
                            <div class="gs-card-body">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <div class="mb-4">
                                        <label class="gs-label">Current Password</label>
                                        <input type="password" class="gs-input" name="current_password"
                                            placeholder="Enter your current password" required>
                                    </div>
                                    <div class="row g-4 mb-4">
                                        <div class="col-md-6">
                                            <label class="gs-label">New Password</label>
                                            <input type="password" class="gs-input" name="new_password"
                                                placeholder="Minimum 6 characters" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="gs-label">Confirm New Password</label>
                                            <input type="password" class="gs-input" name="confirm_password"
                                                placeholder="Repeat new password" required>
                                        </div>
                                    </div>
                                    <div class="gs-warning mb-4">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <div>Changing your password will log you out of all other active sessions for security purposes.</div>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" name="change_password" class="gs-btn-secondary">
                                            Update Password <i class="bi bi-lock-fill ms-1"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if($_SESSION['role_id'] == 1): ?>

                    <!-- ── Global Config Tab ── -->
                    <div class="tab-pane fade" id="v-pills-global">
                        <div class="gs-card">
                            <div class="gs-card-header">
                                <div>
                                    <p class="gs-card-title">Global Configuration</p>
                                    <p class="gs-card-subtitle">Manage portal-wide settings and public identity</p>
                                </div>
                                <span class="gs-card-badge"><i class="bi bi-globe2"></i> Super Admin</span>
                            </div>
                            <div class="gs-card-body">
                                <form method="post">
                                    <?= csrf_field() ?>

                                    <div class="gs-section"><i class="bi bi-building"></i> Sacco Information</div>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="gs-label">Sacco Name</label>
                                            <input type="text" class="gs-input" name="site_name"
                                                value="<?= htmlspecialchars($sys_settings['SITE_NAME'] ?? SITE_NAME) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="gs-label">Short Name</label>
                                            <input type="text" class="gs-input" name="site_short_name"
                                                value="<?= htmlspecialchars($sys_settings['SITE_SHORT_NAME'] ?? SITE_SHORT_NAME) ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="gs-label">Tagline</label>
                                            <input type="text" class="gs-input" name="site_tagline"
                                                value="<?= htmlspecialchars($sys_settings['SITE_TAGLINE'] ?? SITE_TAGLINE) ?>">
                                        </div>
                                    </div>

                                    <div class="gs-section"><i class="bi bi-telephone-fill"></i> Contact Details</div>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="gs-label">Public Email</label>
                                            <div class="gs-input-group">
                                                <span class="gs-input-group-prefix"><i class="bi bi-envelope-fill" style="font-size:0.8rem;"></i></span>
                                                <input type="email" class="gs-input" name="company_email"
                                                    value="<?= htmlspecialchars($sys_settings['COMPANY_EMAIL'] ?? COMPANY_EMAIL) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="gs-label">Contact Phone</label>
                                            <div class="gs-input-group">
                                                <span class="gs-input-group-prefix"><i class="bi bi-telephone-fill" style="font-size:0.8rem;"></i></span>
                                                <input type="text" class="gs-input" name="company_phone"
                                                    value="<?= htmlspecialchars($sys_settings['COMPANY_PHONE'] ?? COMPANY_PHONE) ?>">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="gs-label">Office Address</label>
                                            <textarea class="gs-textarea" name="company_address" rows="2"><?= htmlspecialchars($sys_settings['COMPANY_ADDRESS'] ?? COMPANY_ADDRESS) ?></textarea>
                                        </div>
                                    </div>

                                    <div class="mt-5 d-flex justify-content-end">
                                        <button type="submit" name="update_system_settings" class="gs-btn-primary">
                                            Save Configuration <i class="bi bi-floppy-fill ms-1"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ── System Status Tab ── -->
                    <div class="tab-pane fade" id="v-pills-system">
                        <div class="gs-card">
                            <div class="gs-card-header">
                                <div>
                                    <p class="gs-card-title">Environment Status</p>
                                    <p class="gs-card-subtitle">Live server diagnostics and runtime information</p>
                                </div>
                                <span class="gs-card-badge"><i class="bi bi-hdd-network-fill"></i> Diagnostics</span>
                            </div>
                            <div class="gs-card-body">
                                <div class="gs-sys-grid">
                                    <div class="gs-sys-item">
                                        <span class="sys-label"><i class="bi bi-code-slash me-1"></i> PHP Version</span>
                                        <span class="sys-val"><?= $php_v ?></span>
                                    </div>
                                    <div class="gs-sys-item">
                                        <span class="sys-label"><i class="bi bi-hdd-fill me-1"></i> Server IP</span>
                                        <span class="sys-val"><?= $server_ip ?></span>
                                    </div>
                                    <div class="gs-sys-item">
                                        <span class="sys-label"><i class="bi bi-clock-fill me-1"></i> Server Time</span>
                                        <span class="sys-val" style="font-size:0.88rem;"><?= date('d M Y, H:i') ?></span>
                                    </div>
                                    <div class="gs-sys-item">
                                        <span class="sys-label"><i class="bi bi-memory me-1"></i> Memory Limit</span>
                                        <span class="sys-val"><?= ini_get('memory_limit') ?></span>
                                    </div>
                                </div>
                                <div class="gs-operational">
                                    <span class="op-dot"></span>
                                    All Systems Operational
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endif; ?>

                </div><!-- /tab-content -->
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>