<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// Auth Check
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_id = $_SESSION['admin_id'];

// 2. Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $remove_pic = isset($_POST['remove_pic']);

    $stmt = $conn->prepare("SELECT profile_pic FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $pic_data = $current['profile_pic'];

    if ($remove_pic) {
        $pic_data = null;
    } elseif (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $max_size = 1 * 1024 * 1024;
        $file_size = $_FILES['profile_pic']['size'];
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_type = mime_content_type($file_tmp);
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Please upload a JPG, PNG or WEBP image.";
            header("Location: profile.php"); exit;
        }
        if ($file_size > $max_size) {
            $_SESSION['error'] = "The image is too large (Maximum 1MB). Please compress it or use a smaller photo.";
            header("Location: profile.php"); exit;
        }
        $pic_data = file_get_contents($file_tmp);
    }

    $sql = "UPDATE admins SET email=?, phone=?, profile_pic=? WHERE admin_id=?";
    $stmt = $conn->prepare($sql);
    $null = null; 
    $stmt->bind_param("ssbi", $email, $phone, $null, $admin_id);
    if ($pic_data !== null) { $stmt->send_long_data(2, $pic_data); }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php"); exit;
    } else {
        $_SESSION['error'] = "Update failed: " . $conn->error;
    }
    $stmt->close();
}

// 3. Fetch Data
$stmt = $conn->prepare("SELECT a.*, r.name as role_name FROM admins a LEFT JOIN roles r ON a.role_id = r.id WHERE a.admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$display_pic = BASE_URL . '/public/assets/uploads/male.jpg';
if (!empty($admin['profile_pic'])) {
    $display_pic = 'data:image/jpeg;base64,' . base64_encode($admin['profile_pic']);
}

$pageTitle = "My Profile";
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

/* ─── Hero Cover ─── */
.pf-cover {
    height: 200px;
    background: linear-gradient(135deg, #0F392B 0%, #1a5c43 55%, #0d2e22 100%);
    position: relative;
    overflow: hidden;
    border-radius: 20px 20px 0 0;
}
.pf-cover-dots {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,0.06) 1.5px, transparent 1.5px);
    background-size: 28px 28px;
}
.pf-cover-glow-r {
    position: absolute;
    top: -60px; right: -60px;
    width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(57,181,74,0.2) 0%, transparent 65%);
    border-radius: 50%;
}
.pf-cover-glow-l {
    position: absolute;
    bottom: -60px; left: 10%;
    width: 220px; height: 220px;
    background: radial-gradient(circle, rgba(163,230,53,0.1) 0%, transparent 65%);
    border-radius: 50%;
}
.pf-cover-label {
    position: absolute;
    top: 22px; right: 24px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 100px;
    padding: 6px 14px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.9px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.7);
}
.pf-cover-label i { color: #A3E635; }

/* ─── Outer Card ─── */
.pf-outer-card {
    background: #fff;
    border-radius: 20px;
    border: 1px solid #E8F0ED;
    box-shadow: 0 4px 32px rgba(15,57,43,0.08);
    overflow: hidden;
}

/* ─── Profile Header ─── */
.pf-header {
    display: flex;
    align-items: flex-end;
    gap: 24px;
    padding: 0 36px 28px;
    margin-top: -64px;
    position: relative;
    z-index: 10;
    border-bottom: 1px solid #F0F7F4;
}
.pf-avatar-wrap {
    position: relative;
    width: 130px;
    height: 130px;
    border-radius: 50%;
    background: #fff;
    padding: 5px;
    box-shadow: 0 0 0 4px rgba(57,181,74,0.15), 0 8px 28px rgba(0,0,0,0.18);
    flex-shrink: 0;
}
.pf-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    background: #F0F7F4;
    display: block;
    transition: opacity 0.3s;
}
.pf-avatar-btn {
    position: absolute;
    bottom: 4px; right: 4px;
    width: 38px; height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #A3E635, #6fba1b);
    border: 3px solid #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: #0F392B;
    font-size: 0.85rem;
    transition: all 0.25s;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
}
.pf-avatar-btn:hover { transform: scale(1.12) rotate(15deg); }

.pf-identity { padding-bottom: 4px; flex: 1; }
.pf-identity h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0F392B;
    letter-spacing: -0.4px;
    margin: 0 0 8px;
    line-height: 1.2;
}
.pf-identity-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.pf-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 100px;
    font-size: 0.73rem;
    font-weight: 700;
}
.pf-chip-active { background: #D1FAE5; color: #065f46; }
.pf-chip-dept   { background: #F0F7F4; color: #3a6b55; }
.pf-chip-role   { background: #E8F0ED; color: #0F392B; }

/* ─── Flash Messages ─── */
.pf-flash {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 18px;
    border-radius: 14px;
    font-size: 0.83rem;
    font-weight: 600;
    margin-bottom: 24px;
    animation: pfFadeIn 0.35s ease both;
}
.pf-flash-success { background: #D1FAE5; color: #065f46; border: 1px solid #A7F3D0; }
.pf-flash-error   { background: #FEE2E2; color: #991b1b; border: 1px solid #FECACA; }
.pf-flash i { font-size: 1rem; flex-shrink: 0; }
@keyframes pfFadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ─── Form Body ─── */
.pf-body { padding: 32px 36px 36px; }

.pf-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
}
.pf-section-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.pf-section-icon-edit { background: #E8F5E9; color: #1a6b35; }
.pf-section-icon-lock { background: #F0F7F4; color: #5a7a6e; }
.pf-section-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0F392B;
    margin: 0;
}
.pf-section-desc {
    font-size: 0.72rem;
    color: #7a9e8e;
    font-weight: 500;
    margin: 1px 0 0;
}

/* ─── Inputs ─── */
.pf-label {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #7a9e8e;
    display: block;
    margin-bottom: 7px;
}
.pf-input {
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
.pf-input:focus {
    border-color: #39B54A;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(57,181,74,0.08);
}
.pf-input::placeholder { color: #a8c5bb; }
.pf-input-readonly {
    background: #F0F7F4 !important;
    color: #5a7a6e !important;
    cursor: not-allowed;
    border-color: #E0EDE7 !important;
}
.pf-input-group {
    display: flex;
    border: 1.5px solid #E0EDE7;
    border-radius: 12px;
    overflow: hidden;
    background: #F0F7F4;
    transition: all 0.2s;
}
.pf-input-group-pfx {
    padding: 11px 13px;
    background: #E8F0ED;
    border-right: 1.5px solid #E0EDE7;
    display: flex; align-items: center;
    color: #5a7a6e;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.pf-input-group .pf-input {
    border: none;
    background: transparent;
    border-radius: 0;
    box-shadow: none !important;
}

/* ─── Remove Pic Checkbox ─── */
.pf-remove-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #FFF5F5;
    border: 1px solid #FECACA;
    border-radius: 12px;
    padding: 11px 16px;
    cursor: pointer;
    margin-top: 18px;
    transition: all 0.2s;
}
.pf-remove-wrap:hover { background: #FEE2E2; }
.pf-remove-wrap input[type="checkbox"] {
    width: 17px; height: 17px;
    accent-color: #dc2626;
    cursor: pointer;
    flex-shrink: 0;
}
.pf-remove-wrap label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #dc2626;
    cursor: pointer;
    margin: 0;
}

/* ─── Divider ─── */
.pf-col-divider {
    position: relative;
}
@media (min-width: 992px) {
    .pf-col-divider::before {
        content: '';
        position: absolute;
        left: 0; top: 40px; bottom: 0;
        width: 1px;
        background: #E0EDE7;
    }
}

/* ─── Form Note ─── */
.pf-note {
    font-size: 0.72rem;
    color: #a0b8b0;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
}

/* ─── Footer Actions ─── */
.pf-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 24px;
    margin-top: 24px;
    border-top: 1px solid #E0EDE7;
    gap: 12px;
}
.pf-btn-cancel {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #F7FBF9;
    border: 1.5px solid #E0EDE7;
    border-radius: 12px;
    padding: 10px 22px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.84rem;
    font-weight: 700;
    color: #5a7a6e;
    text-decoration: none;
    transition: all 0.2s;
}
.pf-btn-cancel:hover { background: #E0EDE7; color: #0F392B; }
.pf-btn-save {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #39B54A, #2d9a3c);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 11px 30px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.875rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(57,181,74,0.32);
    letter-spacing: 0.2px;
}
.pf-btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(57,181,74,0.42); }
.pf-btn-save:active { transform: translateY(0); }

/* ─── Responsive ─── */
@media (max-width: 767px) {
    .pf-header { flex-direction: column; align-items: center; text-align: center; padding: 0 20px 24px; }
    .pf-identity-chips { justify-content: center; }
    .pf-body { padding: 20px; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="pf-flash pf-flash-success">
                <i class="bi bi-check-circle-fill"></i>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php elseif (!empty($_SESSION['error'])): ?>
            <div class="pf-flash pf-flash-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-10 mx-auto">
                <div class="pf-outer-card">

                    <!-- Cover Banner -->
                    <div class="pf-cover">
                        <div class="pf-cover-dots"></div>
                        <div class="pf-cover-glow-r"></div>
                        <div class="pf-cover-glow-l"></div>
                        <div class="pf-cover-label">
                            <i class="bi bi-person-badge-fill"></i> Admin Profile
                        </div>
                    </div>

                    <!-- Profile Header -->
                    <div class="pf-header">
                        <div class="pf-avatar-wrap">
                            <img id="preview" src="<?= $display_pic ?>" class="pf-avatar-img" alt="Profile Photo">
                            <label for="profile_pic" class="pf-avatar-btn" title="Change Photo">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                        </div>
                        <div class="pf-identity">
                            <h2><?= htmlspecialchars($admin['full_name']) ?></h2>
                            <div class="pf-identity-chips">
                                <span class="pf-chip pf-chip-active">
                                    <i class="bi bi-circle-fill" style="font-size:0.45rem;"></i> Active
                                </span>
                                <span class="pf-chip pf-chip-dept">
                                    <i class="bi bi-building"></i> <?= htmlspecialchars($admin['department'] ?? 'General') ?>
                                </span>
                                <span class="pf-chip pf-chip-role">
                                    <i class="bi bi-person-badge"></i> <?= htmlspecialchars($admin['role_name'] ?? 'Admin') ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Body -->
                    <div class="pf-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="file" name="profile_pic" id="profile_pic" accept="image/*" class="d-none" onchange="previewImage(event)">

                            <div class="row g-4 g-lg-5">

                                <!-- LEFT: Editable Fields -->
                                <div class="col-lg-6">
                                    <div class="pf-section-title">
                                        <div class="pf-section-icon pf-section-icon-edit">
                                            <i class="bi bi-pencil-fill"></i>
                                        </div>
                                        <div>
                                            <p class="pf-section-name">Contact Details</p>
                                            <p class="pf-section-desc">Fields you can update yourself</p>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="pf-label">Email Address</label>
                                        <div class="pf-input-group">
                                            <span class="pf-input-group-pfx"><i class="bi bi-envelope-fill"></i></span>
                                            <input type="email" name="email" class="pf-input"
                                                value="<?= htmlspecialchars($admin['email']) ?>" required
                                                placeholder="your@email.com">
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="pf-label">Phone Number</label>
                                        <div class="pf-input-group">
                                            <span class="pf-input-group-pfx"><i class="bi bi-telephone-fill"></i></span>
                                            <input type="text" name="phone" class="pf-input"
                                                value="<?= htmlspecialchars($admin['phone'] ?? '') ?>"
                                                placeholder="+1 000 000 0000">
                                        </div>
                                    </div>

                                    <label class="pf-remove-wrap">
                                        <input type="checkbox" name="remove_pic" id="remove_pic">
                                        <i class="bi bi-trash3-fill" style="color:#dc2626; font-size:0.85rem;"></i>
                                        Remove current profile picture
                                    </label>
                                </div>

                                <!-- RIGHT: Readonly Fields -->
                                <div class="col-lg-6 pf-col-divider" style="padding-left: 2.5rem;">
                                    <div class="pf-section-title">
                                        <div class="pf-section-icon pf-section-icon-lock">
                                            <i class="bi bi-shield-lock-fill"></i>
                                        </div>
                                        <div>
                                            <p class="pf-section-name">Official Record <sup style="color:#dc2626; font-size:0.65rem;">*</sup></p>
                                            <p class="pf-section-desc">Read-only — contact Super Admin to edit</p>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="pf-label">Full Name</label>
                                        <div class="pf-input-group">
                                            <span class="pf-input-group-pfx"><i class="bi bi-person-fill"></i></span>
                                            <input type="text" class="pf-input pf-input-readonly"
                                                value="<?= htmlspecialchars($admin['full_name']) ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="pf-label">Username</label>
                                        <div class="pf-input-group">
                                            <span class="pf-input-group-pfx"><i class="bi bi-at"></i></span>
                                            <input type="text" class="pf-input pf-input-readonly"
                                                value="<?= htmlspecialchars($admin['username'] ?? '') ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="pf-label">System Role</label>
                                        <div class="pf-input-group">
                                            <span class="pf-input-group-pfx"><i class="bi bi-key-fill"></i></span>
                                            <input type="text" class="pf-input pf-input-readonly"
                                                value="<?= htmlspecialchars($admin['role_name'] ?? 'Admin') ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-0">
                                        <label class="pf-label">Account Created</label>
                                        <div class="pf-input-group">
                                            <span class="pf-input-group-pfx"><i class="bi bi-calendar3"></i></span>
                                            <input type="text" class="pf-input pf-input-readonly"
                                                value="<?= date('d M, Y', strtotime($admin['created_at'])) ?>" readonly>
                                        </div>
                                        <p class="pf-note"><i class="bi bi-lock-fill" style="font-size:0.65rem;"></i> Locked fields require Super Admin intervention to modify.</p>
                                    </div>
                                </div>

                            </div>

                            <!-- Footer -->
                            <div class="pf-actions">
                                <a href="dashboard.php" class="pf-btn-cancel">
                                    <i class="bi bi-x-lg"></i> Cancel
                                </a>
                                <button type="submit" class="pf-btn-save">
                                    <i class="bi bi-check2-circle"></i> Save Changes
                                </button>
                            </div>

                        </form>
                    </div><!-- /pf-body -->

                </div><!-- /pf-outer-card -->
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
function previewImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function() {
        const img = document.getElementById('preview');
        img.style.opacity = '0.4';
        setTimeout(() => {
            img.src = reader.result;
            img.style.opacity = '1';
        }, 200);
    };
    reader.readAsDataURL(file);
}
</script>
</body>
</html>