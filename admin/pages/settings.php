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
               $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES (\$admin_id, 'system_update', 'Updated Global Configuration', '{\$_SERVER['REMOTE_ADDR']}')");
            }
        } else {
            flash_set("No changes were saved.", 'warning');
        }
    }
}

// Fetch System Settings
$sys_settings = SettingsHelper::all();

// Initials helper
function getInitials($name) {
    if (!$name) return '??';
    $words = explode(" ", $name);
    $acronym = "";
    foreach ($words as $w) if(!empty($w)) $acronym .= mb_substr($w, 0, 1);
    return strtoupper(substr($acronym, 0, 2)) ?: '??';
}
$server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$php_v     = phpversion();
?>
<?php $layout->header($pageTitle); ?>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-primary); color: var(--text-main); }
        
        /* Layout */
        .main-content { margin-left: 280px; min-height: 100vh; display: flex; flex-direction: column; transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); padding: 2.5rem; }
        @media(max-width: 991px){ .main-content { margin-left:0; padding: 1.5rem; } }

        /* Component Styles */
        .card-custom { border-radius: 24px; border: 1px solid var(--border-color); }

        .nav-pills .nav-link { font-weight: 500; border-radius: 12px; padding: 12px 20px; margin-bottom: 8px; transition: all 0.3s; }
        .nav-pills .nav-link:hover { background-color: rgba(255,255,255,0.05); }
        .nav-pills .nav-link.active { background-color: var(--lime); color: #000000; }
        
        .form-control { border-radius: 12px; padding: 12px 16px; }
        .form-control:focus { background-color: var(--bs-body-bg); border-color: var(--lime); box-shadow: 0 0 0 4px rgba(190, 242, 100, 0.2); }
        
        .btn-lime { background-color: var(--lime); color: #000000; font-weight: 600; border-radius: 50px; padding: 10px 24px; border: none; }
        .btn-lime:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .avatar-circle { width: 80px; height: 80px; background-color: rgba(255,255,255,0.05); color: var(--lime); font-size: 1.8rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto; }

        /* Hope UI Styles */
        .glass-card { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .tab-content-wrapper { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .section-title { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--lime); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .section-title::after { content: ""; flex: 1; height: 1px; background: rgba(190, 242, 100, 0.15); }
    </style>

<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($me['full_name']); ?>
            
            <div class="container-fluid">
        
            
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--accent-dark);">Settings</h2>
                    <p class="text-muted mb-0">Manage your profile and system preferences</p>
                </div>
            </div>

            <?php flash_render(); ?>

            <div class="row g-4">
                
                <div class="col-lg-4 col-xl-3">
                    
                    <div class="card-custom p-4 text-center mb-4" style="background: linear-gradient(180deg, var(--accent-dark) 0%, #0f1c03 100%); color: white;">
                        <div class="position-relative d-inline-block mb-3">
                            <div class="avatar-circle border border-2 border-white  bg-white">
                                 <?= getInitials($me['full_name']) ?>
                            </div>
                            <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-dark rounded-circle p-2"></span>
                        </div>
                        <h5 class="mb-1 fw-bold"><?= htmlspecialchars($me['full_name']) ?></h5>
                        <p class="text-white-50 small mb-3"><?= $me['email'] ?></p>
                        
                        <div class="d-flex justify-content-center gap-4 mt-3 pt-3 border-top border-secondary border-opacity-25">
                            <div class="text-center">
                                <h6 class="mb-0 fw-bold text-white">ID</h6>
                                <small class="text-white-50">#<?= $admin_id ?></small>
                            </div>
                            <div class="text-center">
                                <h6 class="mb-0 fw-bold text-white">Role</h6>
                                <small class="text-white-50"><?= htmlspecialchars($me['role_name'] ?? 'Staff') ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="card-custom p-3">
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
                            <div class="card-custom p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-bold mb-0">Edit Profile</h5>
                                    <span class="badge bg-light  border rounded-pill px-3 py-2">Personal Details</span>
                                </div>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label>Full Name</label>
                                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($me['full_name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text border-0 bg-light text-muted" style="border-radius: 12px 0 0 12px;">@</span>
                                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($me['username']) ?>" style="border-radius: 0 12px 12px 0;" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label>Email Address</label>
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
                            <div class="card-custom p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-bold mb-0">Security</h5>
                                    <span class="badge bg-light  border rounded-pill px-3 py-2">Password Manager</span>
                                </div>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <div class="mb-4">
                                        <label>Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="row g-4 mb-4">
                                        <div class="col-md-6">
                                            <label>New Password</label>
                                            <input type="password" class="form-control" name="new_password" placeholder="Min 6 characters" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Confirm Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning d-flex align-items-start small">
                                        <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                                        <div>Changing your password will log you out of all other active sessions.</div>
                                    </div>
                                    <div class="mt-4 d-flex justify-content-end">
                                        <button type="submit" name="change_password" class="btn btn-dark rounded-pill px-4">Update Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if($_SESSION['role_id'] == 1): ?>
                        <div class="tab-pane fade" id="v-pills-global">
                            <div class="card-custom p-4 p-md-5">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    
                                    <div class="section-title">Sacco Information</div>
                                    <div class="row g-4 mb-5">
                                        <div class="col-md-6">
                                            <label class="form-label">Sacco Name</label>
                                            <input type="text" class="form-control" name="site_name" value="<?= htmlspecialchars($sys_settings['SITE_NAME'] ?? SITE_NAME) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Short Name</label>
                                            <input type="text" class="form-control" name="site_short_name" value="<?= htmlspecialchars($sys_settings['SITE_SHORT_NAME'] ?? SITE_SHORT_NAME) ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Tagline</label>
                                            <input type="text" class="form-control" name="site_tagline" value="<?= htmlspecialchars($sys_settings['SITE_TAGLINE'] ?? SITE_TAGLINE) ?>">
                                        </div>
                                    </div>

                                    <div class="section-title">Contact Details</div>
                                    <div class="row g-4 mb-5">
                                        <div class="col-md-6">
                                            <label class="form-label">Public Email</label>
                                            <input type="email" class="form-control" name="company_email" value="<?= htmlspecialchars($sys_settings['COMPANY_EMAIL'] ?? COMPANY_EMAIL) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Contact Phone</label>
                                            <input type="text" class="form-control" name="company_phone" value="<?= htmlspecialchars($sys_settings['COMPANY_PHONE'] ?? COMPANY_PHONE) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Address / Office Location</label>
                                            <textarea class="form-control" name="company_address" rows="2"><?= htmlspecialchars($sys_settings['COMPANY_ADDRESS'] ?? COMPANY_ADDRESS) ?></textarea>
                                        </div>
                                    </div>

                                    <div class="section-title">Social Presence</div>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Facebook URL</label>
                                            <input type="url" class="form-control" name="social_facebook" value="<?= htmlspecialchars($sys_settings['SOCIAL_FACEBOOK'] ?? SOCIAL_FACEBOOK) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Twitter URL</label>
                                            <input type="url" class="form-control" name="social_twitter" value="<?= htmlspecialchars($sys_settings['SOCIAL_TWITTER'] ?? SOCIAL_TWITTER) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Instagram URL</label>
                                            <input type="url" class="form-control" name="social_instagram" value="<?= htmlspecialchars($sys_settings['SOCIAL_INSTAGRAM'] ?? SOCIAL_INSTAGRAM) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">YouTube URL</label>
                                            <input type="url" class="form-control" name="social_youtube" value="<?= htmlspecialchars($sys_settings['SOCIAL_YOUTUBE'] ?? SOCIAL_YOUTUBE) ?>">
                                        </div>
                                    </div>

                                    <div class="mt-5 d-flex justify-content-end">
                                        <button type="submit" name="update_system_settings" class="btn btn-lime shadow-sm px-5">Save Global Configuration</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="v-pills-system">
                            <div class="card-custom p-4 p-md-5">
                                <h5 class="fw-bold mb-4">Environment Status</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-4 border border-light">
                                            <small class="text-muted d-block mb-1">PHP Version</small>
                                            <span class="fw-bold font-monospace fs-5"><?= $php_v ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-4 border border-light">
                                            <small class="text-muted d-block mb-1">Server IP</small>
                                            <span class="fw-bold font-monospace fs-5"><?= $server_ip ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded-4 border border-light">
                                            <small class="text-muted d-block mb-1">Software</small>
                                            <span class="fw-bold font-monospace"><?= $_SERVER['SERVER_SOFTWARE'] ?></span>
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
                <?php $layout->footer(); ?>
        </div>
    </div>
</div>






