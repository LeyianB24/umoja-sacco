<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SettingsHelper.php';

// AUTH CHECK
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

// allow all admins to see their own settings
// require_superadmin(); 

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
            // Audit Log (simplified)
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

// Initials helper
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
?>
<?php $layout->header($pageTitle); ?>

    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }

        /* Page-specific overrides */
        
        /* Navigation Pills */
        .nav-pills .nav-link { 
            font-weight: 700; border-radius: 16px; padding: 15px 25px; 
            margin-bottom: 10px; transition: 0.3s; color: var(--forest);
            border: 1px solid transparent; background: white;
        }
        .nav-pills .nav-link:hover { background-color: rgba(208, 243, 93, 0.1); }
        .nav-pills .nav-link.active { background-color: var(--forest); color: var(--lime); border-color: var(--forest); }
        
        .avatar-hero { 
            width: 90px; height: 90px; border-radius: 20px; 
            background: var(--lime); color: var(--forest);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 800; border: 4px solid rgba(255,255,255,0.2);
        }

        .section-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin: 40px 0 20px 0; display: flex; align-items: center; gap: 15px; }
        .section-title::after { content: ""; flex: 1; height: 1px; background: rgba(0,0,0,0.05); }

        .hp-badge { background: rgba(208, 243, 93, 0.15); color: var(--forest-mid); font-weight: 700; padding: 6px 12px; border-radius: 10px; font-size: 0.7rem; }
    </style>


<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'System Settings'); ?>
        <div class="container-fluid">
        
            
        <div class="hp-hero">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">System Control Panel</span>
                    <h1 class="display-4 fw-800 mb-2">Global Configuration.</h1>
                    <p class="opacity-75 fs-5">Fine-tune your portal. Manage identity, security, and global parameters with <span class="text-lime fw-bold">exclusive precision</span>.</p>
                </div>
                <div class="col-md-5 text-end d-none d-lg-block">
                    <div class="d-inline-block p-4 rounded-4 bg-white bg-opacity-10 backdrop-blur text-start">
                        <div class="small opacity-75">Server Status</div>
                        <div class="h3 fw-bold mb-0 text-lime"><i class="bi bi-cpu me-2"></i>Operational</div>
                    </div>
                </div>
            </div>
        </div>

        <?php flash_render(); ?>

        <div class="row g-4">
            <div class="col-lg-4 col-xl-3">
                <div class="glass-stat p-4 text-center mb-4" style="background: linear-gradient(180deg, var(--forest) 0%, var(--forest-mid) 100%); color: white;">
                    <div class="position-relative d-inline-block mb-3">
                        <div class="avatar-hero mx-auto">
                             <?= getInitials($me['full_name']) ?>
                        </div>
                    </div>
                    <h5 class="mb-1 fw-800"><?= htmlspecialchars($me['full_name']) ?></h5>
                    <p class="text-white-50 small mb-4"><?= $me['email'] ?></p>
                    
                    <div class="d-flex justify-content-center gap-4 pt-3 border-top border-white border-opacity-10">
                        <div class="text-center">
                            <div class="small text-white-50 fw-bold text-uppercase" style="letter-spacing: 1px;">Admin ID</div>
                            <div class="h5 fw-800 mb-0 text-lime">#<?= $admin_id ?></div>
                        </div>
                        <div class="text-center">
                            <div class="small text-white-50 fw-bold text-uppercase" style="letter-spacing: 1px;">Role</div>
                            <div class="h5 fw-800 mb-0 text-lime"><?= htmlspecialchars($me['role_name'] ?? 'Staff') ?></div>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-3">
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                            <button class="nav-link active text-start" data-bs-toggle="pill" data-bs-target="#v-pills-profile">
                                <i class="bi bi-person me-2"></i> Personal Info
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#v-pills-security">
                                <i class="bi bi-shield-lock me-2"></i> Security
                            </button>
                            <?php if($_SESSION['role_id'] == 1): ?>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#v-pills-global">
                                <i class="bi bi-globe2 me-2"></i> Global Config
                            </button>
                            <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#v-pills-system">
                                <i class="bi bi-hdd-network me-2"></i> System Status
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-xl-9">
                    <div class="tab-content" id="v-pills-tabContent">
                        
                        <div class="tab-pane fade show active" id="v-pills-profile">
                            <div class="glass-card p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-800 mb-0">Edit Profile</h5>
                                    <span class="hp-badge">Personal Details</span>
                                </div>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Full Name</label>
                                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($me['full_name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text border-0 bg-light text-muted" style="border-radius: 12px 0 0 12px;">@</span>
                                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($me['username']) ?>" style="border-radius: 0 12px 12px 0;" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Email Address</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($me['email']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mt-5 d-flex justify-content-end">
                                        <button type="submit" name="update_profile" class="btn btn-lime shadow-sm">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="v-pills-security">
                            <div class="glass-card p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-800 mb-0">Security</h5>
                                    <span class="hp-badge">Password Manager</span>
                                </div>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <div class="mb-4">
                                        <label class="small fw-800 text-muted text-uppercase mb-2">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="row g-4 mb-4">
                                        <div class="col-md-6">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">New Password</label>
                                            <input type="password" class="form-control" name="new_password" placeholder="Min 6 characters" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Confirm Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning d-flex align-items-start small rounded-4">
                                        <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                                        <div>Changing your password will log you out of all other active sessions for security.</div>
                                    </div>
                                    <div class="mt-4 d-flex justify-content-end">
                                        <button type="submit" name="change_password" class="btn btn-forest rounded-pill px-4 text-white" style="background: var(--forest);">Update Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if($_SESSION['role_id'] == 1): ?>
                        <div class="tab-pane fade" id="v-pills-global">
                            <div class="glass-card p-4 p-md-5">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    
                                    <div class="section-title">Sacco Information</div>
                                    <div class="row g-4 mb-5">
                                        <div class="col-md-6">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Sacco Name</label>
                                            <input type="text" class="form-control" name="site_name" value="<?= htmlspecialchars($sys_settings['SITE_NAME'] ?? SITE_NAME) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Short Name</label>
                                            <input type="text" class="form-control" name="site_short_name" value="<?= htmlspecialchars($sys_settings['SITE_SHORT_NAME'] ?? SITE_SHORT_NAME) ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Tagline</label>
                                            <input type="text" class="form-control" name="site_tagline" value="<?= htmlspecialchars($sys_settings['SITE_TAGLINE'] ?? SITE_TAGLINE) ?>">
                                        </div>
                                    </div>

                                    <div class="section-title">Contact Details</div>
                                    <div class="row g-4 mb-5">
                                        <div class="col-md-6">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Public Email</label>
                                            <input type="email" class="form-control" name="company_email" value="<?= htmlspecialchars($sys_settings['COMPANY_EMAIL'] ?? COMPANY_EMAIL) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Contact Phone</label>
                                            <input type="text" class="form-control" name="company_phone" value="<?= htmlspecialchars($sys_settings['COMPANY_PHONE'] ?? COMPANY_PHONE) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="small fw-800 text-muted text-uppercase mb-2">Address / Office Location</label>
                                            <textarea class="form-control" name="company_address" rows="2"><?= htmlspecialchars($sys_settings['COMPANY_ADDRESS'] ?? COMPANY_ADDRESS) ?></textarea>
                                        </div>
                                    </div>

                                    <div class="mt-5 d-flex justify-content-end">
                                        <button type="submit" name="update_system_settings" class="btn btn-lime shadow-sm px-5">Save Global Configuration</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="v-pills-system">
                            <div class="glass-card p-4 p-md-5">
                                <h5 class="fw-800 mb-4">Environment Status</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 bg-white bg-opacity-50 rounded-4 border">
                                            <small class="text-muted d-block mb-1">PHP Version</small>
                                            <span class="fw-bold font-monospace fs-5"><?= $php_v ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-white bg-opacity-50 rounded-4 border">
                                            <small class="text-muted d-block mb-1">Server IP</small>
                                            <span class="fw-bold font-monospace fs-5"><?= $server_ip ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 text-center">
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2">
                                        <i class="bi bi-activity"></i> System Operational
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
        </div>
        <?php $layout->footer(); ?>
    </div>
</div>






