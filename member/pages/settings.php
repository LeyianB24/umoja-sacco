<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');

if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$success_message = $error_message = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_profile'])) {
        verify_csrf_token();
        $email   = trim($_POST['email']);
        $phone   = trim($_POST['phone']);
        $gender  = trim($_POST['gender'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($email) || empty($phone)) {
            $error_message = "Email and Phone are required.";
        } else {
            $stmt = $conn->prepare("UPDATE members SET email=?, phone=?, gender=?, address=? WHERE member_id=?");
            $stmt->bind_param("ssssi", $email, $phone, $gender, $address, $member_id);
            $success_message = $stmt->execute() ? "Profile updated successfully!" : "Update failed: ".$conn->error;
            $stmt->close();
        }
    }

    if (isset($_POST['change_password'])) {
        verify_csrf_token();
        $current  = $_POST['current_password'];
        $new      = $_POST['new_password'];
        $confirm  = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT password FROM members WHERE member_id=?");
        $stmt->bind_param("i", $member_id); $stmt->execute();
        $user_pw = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if (!password_verify($current, $user_pw['password'])) {
            $error_message = "Incorrect current password.";
        } elseif ($new !== $confirm) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new) < 6) {
            $error_message = "Password must be at least 6 characters.";
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE members SET password=? WHERE member_id=?");
            $stmt->bind_param("si", $hashed, $member_id);
            $success_message = $stmt->execute() ? "Password changed successfully!" : "Error changing password.";
            $stmt->close();
        }
    }
}

// Fetch member data
$stmt = $conn->prepare("SELECT full_name, email, phone, gender, address, profile_pic, created_at, member_reg_no FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();

// Avatar initials (2-letter)
$name_parts = explode(' ', trim($user['full_name']));
$initials   = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

$pageTitle = "Settings";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?> | <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   SETTINGS · HD EDITION · Forest & Lime · Plus Jakarta Sans
═══════════════════════════════════════════════════════════ */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

:root {
    --f:      #0b2419;  --fm: #154330;  --fs: #1d6044;
    --lime:   #a3e635;  --lt: #6a9a1a;  --lg: rgba(163,230,53,.14);
    --bg:     #eff5f1;  --bg2: #e8f1ec;
    --surf:   #ffffff;  --surf2: #f7fbf8;
    --bdr:    rgba(11,36,25,.07);  --bdr2: rgba(11,36,25,.04);
    --t1: #0b2419;  --t2: #456859;  --t3: #8fada0;
    --grn:    #16a34a;  --red: #dc2626;  --amb: #d97706;
    --grn-bg: rgba(22,163,74,.08);   --red-bg: rgba(220,38,38,.08);
    --r: 20px;  --rsm: 12px;
    --ease:   cubic-bezier(.16,1,.3,1);
    --spring: cubic-bezier(.34,1.56,.64,1);
    --sh:     0 1px 3px rgba(11,36,25,.05), 0 6px 20px rgba(11,36,25,.08);
    --sh-lg:  0 4px 8px rgba(11,36,25,.07), 0 20px 56px rgba(11,36,25,.13);
}

[data-bs-theme="dark"] {
    --bg: #070e0b;  --bg2: #0a1510;
    --surf: #0d1d14;  --surf2: #0a1810;
    --bdr: rgba(255,255,255,.07);  --bdr2: rgba(255,255,255,.04);
    --t1: #d8eee2;  --t2: #4d7a60;  --t3: #2a4d38;
}

body,* { font-family:'Plus Jakarta Sans',sans-serif !important; -webkit-font-smoothing:antialiased; }
body   { background:var(--bg); color:var(--t1); }

.main-content-wrapper { margin-left:272px; min-height:100vh; transition:margin-left .3s var(--ease); }
body.sb-collapsed .main-content-wrapper { margin-left:72px; }
@media(max-width:991px){ .main-content-wrapper{margin-left:0} }

.dash { padding:28px 28px 72px; }
@media(max-width:768px){ .dash{padding:16px 14px 48px} }

/* ── PAGE HEADER ── */
.pg-head {
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:16px; flex-wrap:wrap; margin-bottom:28px;
    animation:fadeUp .7s var(--ease) both;
}
.pg-title { font-size:1.5rem; font-weight:800; color:var(--t1); letter-spacing:-.4px; margin-bottom:4px; }
.pg-sub   { font-size:.82rem; font-weight:500; color:var(--t3); }

@keyframes fadeUp  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes floatUp { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }

/* ── FLASH MESSAGES ── */
.flash {
    display:flex; align-items:flex-start; gap:12px;
    border-radius:var(--rsm); padding:14px 18px; margin-bottom:22px;
    font-size:.85rem; font-weight:600;
    animation:fadeUp .4s var(--ease) both;
}
.flash.ok  { background:var(--grn-bg); border:1px solid rgba(22,163,74,.2);  color:var(--grn); }
.flash.err { background:var(--red-bg); border:1px solid rgba(220,38,38,.2);  color:var(--red); }
.flash i   { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
.flash strong { font-weight:800; display:block; margin-bottom:1px; }
.flash-close { margin-left:auto; background:none; border:none; color:inherit; opacity:.6; cursor:pointer; font-size:1rem; line-height:1; padding:0; }

/* ── PROFILE PANEL (left column) ── */
.profile-panel {
    background: linear-gradient(145deg, var(--f) 0%, var(--fm) 100%);
    border-radius:var(--r); padding:36px 28px;
    position:relative; overflow:hidden;
    display:flex; flex-direction:column; align-items:center;
    text-align:center; height:100%;
    animation:floatUp .7s var(--ease) .4s both;
}

/* dot grid overlay */
.profile-panel::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);
    background-size:20px 20px;
}
/* radial glow */
.profile-panel::after {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; border-radius:50%;
    background:radial-gradient(circle,rgba(163,230,53,.13) 0%,transparent 65%);
    pointer-events:none;
}

.profile-inner { position:relative; z-index:2; width:100%; }

/* Avatar */
.avatar {
    width:96px; height:96px; border-radius:50%; margin:0 auto 18px;
    display:flex; align-items:center; justify-content:center;
    font-size:2rem; font-weight:800; color:var(--lime);
    background:rgba(163,230,53,.15);
    border:3px solid rgba(163,230,53,.3);
    box-shadow:0 6px 24px rgba(0,0,0,.25);
    letter-spacing:-1px;
}

.profile-name  { font-size:1.1rem; font-weight:800; color:#fff; margin-bottom:4px; }
.profile-email { font-size:.78rem; font-weight:500; color:rgba(255,255,255,.45); margin-bottom:18px; }

.profile-badges { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-bottom:22px; }
.pbadge {
    display:inline-flex; align-items:center; gap:5px;
    background:rgba(255,255,255,.09); border:1px solid rgba(255,255,255,.14);
    border-radius:50px; padding:4px 12px;
    font-size:10px; font-weight:800; letter-spacing:.5px;
    text-transform:uppercase; color:rgba(255,255,255,.65);
}
.pbadge i { font-size:.72rem; }

.profile-divider { border-top:1px solid rgba(255,255,255,.1); padding-top:18px; margin-top:auto; }

.profile-stat-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.profile-stat-row:last-child { margin-bottom:0; }
.pstat-lbl { font-size:.7rem; font-weight:600; color:rgba(255,255,255,.35); text-transform:uppercase; letter-spacing:.5px; }
.pstat-val { font-size:.82rem; font-weight:800; color:#fff; }

/* ── SETTINGS CARD (right column) ── */
.settings-card {
    background:var(--surf); border-radius:var(--r);
    border:1px solid var(--bdr); box-shadow:var(--sh);
    overflow:hidden;
    animation:floatUp .7s var(--ease) .46s both;
}

/* ── TAB SWITCHER ── */
.stab-head {
    display:flex; align-items:center; gap:4px;
    background:var(--bg); border:1px solid var(--bdr);
    border-radius:14px; padding:5px; margin-bottom:28px;
}

.stab {
    display:flex; align-items:center; gap:7px;
    padding:9px 18px; border-radius:10px;
    font-size:.82rem; font-weight:700; color:var(--t3);
    border:none; background:transparent; cursor:pointer;
    transition:all .18s var(--ease); white-space:nowrap;
}
.stab:hover { color:var(--t2); }
.stab.active {
    background:var(--f); color:#fff;
    box-shadow:0 3px 12px rgba(11,36,25,.2);
}
.stab i { font-size:.88rem; }

/* Tab panels */
.stab-pane { display:none; }
.stab-pane.show { display:block; }

/* ── SECTION LABEL ── */
.sec-lbl {
    display:flex; align-items:center; gap:12px;
    font-size:9.5px; font-weight:800; letter-spacing:1.2px;
    text-transform:uppercase; color:var(--t3); margin-bottom:20px;
}
.sec-lbl::after { content:''; flex:1; height:1px; background:var(--bdr); }

/* ── FORM ELEMENTS ── */
.field-lbl {
    display:block; font-size:10px; font-weight:800;
    letter-spacing:.7px; text-transform:uppercase; color:var(--t3); margin-bottom:7px;
}

.field-ctrl {
    width:100%; padding:10px 14px;
    background:var(--surf2); border:1px solid var(--bdr);
    border-radius:var(--rsm); color:var(--t1);
    font-size:.875rem; font-weight:500;
    outline:none; transition:border-color .18s ease, box-shadow .18s ease;
}
.field-ctrl:focus { border-color:rgba(11,36,25,.3); box-shadow:0 0 0 3px rgba(11,36,25,.07); }
.field-ctrl:read-only { opacity:.55; cursor:not-allowed; }
[data-bs-theme="dark"] .field-ctrl { color:var(--t1); background:var(--surf2); }

/* Password input group */
.pw-wrap { position:relative; }
.pw-wrap .field-ctrl { padding-right:44px; }
.pw-toggle {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:var(--t3); cursor:pointer;
    padding:0; font-size:.88rem; transition:color .15s ease;
}
.pw-toggle:hover { color:var(--t1); }

/* Strength bar */
.str-bar { height:3px; border-radius:99px; background:var(--bg); overflow:hidden; margin-top:6px; }
.str-fill { height:100%; border-radius:99px; width:0; transition:width .4s var(--ease),background .3s ease; }

/* ── BUTTONS ── */
.btn-save {
    display:inline-flex; align-items:center; gap:8px;
    background:var(--lime); color:var(--f);
    font-size:.875rem; font-weight:800;
    padding:12px 28px; border-radius:50px; border:none; cursor:pointer;
    box-shadow:0 2px 14px rgba(163,230,53,.28);
    transition:all .25s var(--spring);
}
.btn-save:hover { transform:translateY(-2px) scale(1.03); box-shadow:0 10px 28px rgba(163,230,53,.4); color:var(--f); }

.btn-back-link {
    display:inline-flex; align-items:center; gap:7px;
    background:var(--surf2); border:1px solid var(--bdr); color:var(--t2);
    font-size:.82rem; font-weight:700;
    padding:10px 20px; border-radius:50px;
    text-decoration:none; transition:all .18s ease;
}
.btn-back-link:hover { border-color:rgba(11,36,25,.18); color:var(--t1); }

/* ── INFO ROW (read-only fields) ── */
.info-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:12px 0; border-bottom:1px solid var(--bdr2);
}
.info-row:last-child { border-bottom:none; }
.info-lbl { font-size:10px; font-weight:800; letter-spacing:.7px; text-transform:uppercase; color:var(--t3); }
.info-val { font-size:.85rem; font-weight:700; color:var(--t1); }

/* ── INFO CARD (inside security tab) ── */
.info-card {
    background:var(--lg); border:1px solid rgba(163,230,53,.2);
    border-radius:14px; padding:16px 18px;
    display:flex; align-items:flex-start; gap:12px;
    margin-bottom:22px;
}
.info-card i { color:var(--lt); font-size:1rem; flex-shrink:0; margin-top:1px; }
.info-card p { font-size:.78rem; font-weight:600; color:var(--t2); margin:0; line-height:1.5; }
</style>
</head>
<body>
<?php $layout->sidebar(); ?>

<div class="main-content-wrapper">
<?php $layout->topbar($pageTitle ?? ''); ?>

<div class="dash">

    <!-- Page header -->
    <div class="pg-head">
        <div>
            <div class="pg-title">Account Settings</div>
            <div class="pg-sub">Manage your profile and security preferences.</div>
        </div>
        <a href="dashboard.php" class="btn-back-link">
            <i class="bi bi-arrow-left" style="font-size:.75rem"></i> Dashboard
        </a>
    </div>

    <!-- Flash messages -->
    <?php if ($success_message): ?>
    <div class="flash ok">
        <i class="bi bi-check-circle-fill"></i>
        <div><strong>Success</strong><?= htmlspecialchars($success_message) ?></div>
        <button class="flash-close" onclick="this.closest('.flash').remove()">&times;</button>
    </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="flash err">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><strong>Error</strong><?= htmlspecialchars($error_message) ?></div>
        <button class="flash-close" onclick="this.closest('.flash').remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Main grid -->
    <div class="row g-3">

        <!-- Left: Profile panel -->
        <div class="col-lg-4">
            <div class="profile-panel">
                <div class="profile-inner">
                    <div class="avatar"><?= $initials ?></div>
                    <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>

                    <div class="profile-badges">
                        <span class="pbadge"><i class="bi bi-person-badge-fill"></i> <?= htmlspecialchars($user['member_reg_no']) ?></span>
                        <span class="pbadge"><i class="bi bi-shield-check-fill"></i> Active Member</span>
                    </div>

                    <div class="profile-divider">
                        <div class="profile-stat-row">
                            <span class="pstat-lbl">Gender</span>
                            <span class="pstat-val"><?= ucfirst($user['gender'] ?? '—') ?></span>
                        </div>
                        <div class="profile-stat-row">
                            <span class="pstat-lbl">Phone</span>
                            <span class="pstat-val"><?= htmlspecialchars($user['phone'] ?? '—') ?></span>
                        </div>
                        <div class="profile-stat-row">
                            <span class="pstat-lbl">Member Since</span>
                            <span class="pstat-val"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        <div class="profile-stat-row">
                            <span class="pstat-lbl">Address</span>
                            <span class="pstat-val" style="text-align:right;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($user['address'] ?? '—') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Settings card -->
        <div class="col-lg-8">
            <div class="settings-card">
                <div class="p-4 p-xl-5">

                    <!-- Tab switcher -->
                    <div class="stab-head">
                        <button class="stab active" data-tab="profile" id="tab-profile">
                            <i class="bi bi-person-fill"></i> Personal Details
                        </button>
                        <button class="stab" data-tab="security" id="tab-security">
                            <i class="bi bi-shield-lock-fill"></i> Security
                        </button>
                    </div>

                    <!-- ── Tab: Personal Details ── -->
                    <div class="stab-pane show" id="pane-profile">
                        <div class="sec-lbl">Edit Profile</div>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="row g-3">

                                <div class="col-12">
                                    <label class="field-lbl">Full Name <span style="color:var(--t3);font-size:9px">(Locked)</span></label>
                                    <input type="text" class="field-ctrl" value="<?= htmlspecialchars($user['full_name']) ?>" readonly>
                                </div>

                                <div class="col-md-6">
                                    <label class="field-lbl">Email Address</label>
                                    <input type="email" name="email" class="field-ctrl" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="field-lbl">Phone Number</label>
                                    <input type="tel" name="phone" class="field-ctrl" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="field-lbl">Gender</label>
                                    <select name="gender" class="field-ctrl">
                                        <option value="male"   <?= ($user['gender']==='male')   ?'selected':'' ?>>Male</option>
                                        <option value="female" <?= ($user['gender']==='female') ?'selected':'' ?>>Female</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="field-lbl">Address</label>
                                    <input type="text" name="address" class="field-ctrl" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="e.g. Nairobi, Kenya">
                                </div>

                                <div class="col-12" style="margin-top:8px">
                                    <button type="submit" name="update_profile" class="btn-save">
                                        <i class="bi bi-check-circle-fill"></i> Save Changes
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>

                    <!-- ── Tab: Security ── -->
                    <div class="stab-pane" id="pane-security">
                        <div class="sec-lbl">Change Password</div>

                        <div class="info-card">
                            <i class="bi bi-info-circle-fill"></i>
                            <p>Use a strong password of at least 8 characters with a mix of letters, numbers, and symbols. Your session will remain active after changing.</p>
                        </div>

                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="row g-3">

                                <div class="col-12">
                                    <label class="field-lbl">Current Password</label>
                                    <div class="pw-wrap">
                                        <input type="password" name="current_password" id="cur-pass" class="field-ctrl" required>
                                        <button type="button" class="pw-toggle" onclick="togglePw('cur-pass',this)"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="field-lbl">New Password</label>
                                    <div class="pw-wrap">
                                        <input type="password" name="new_password" id="new-pass" class="field-ctrl" required oninput="checkStrength(this.value)">
                                        <button type="button" class="pw-toggle" onclick="togglePw('new-pass',this)"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <div class="str-bar"><div class="str-fill" id="str-fill"></div></div>
                                    <div style="font-size:.65rem;font-weight:700;color:var(--t3);margin-top:3px" id="str-label"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="field-lbl">Confirm New Password</label>
                                    <div class="pw-wrap">
                                        <input type="password" name="confirm_password" id="conf-pass" class="field-ctrl" required>
                                        <button type="button" class="pw-toggle" onclick="togglePw('conf-pass',this)"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>

                                <div class="col-12" style="margin-top:8px">
                                    <button type="submit" name="change_password" class="btn-save">
                                        <i class="bi bi-lock-fill"></i> Update Password
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

    </div><!-- /row -->

</div><!-- /dash -->

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Custom tab switcher (no Bootstrap pill dependency)
document.querySelectorAll('.stab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.stab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.stab-pane').forEach(p => p.classList.remove('show'));
        btn.classList.add('active');
        document.getElementById('pane-' + btn.dataset.tab).classList.add('show');
    });
});

// If server returned a security error/success, show the security tab
<?php if (isset($_POST['change_password'])): ?>
document.getElementById('tab-security').click();
<?php endif; ?>

// Password toggle
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Password strength meter
function checkStrength(val) {
    const fill  = document.getElementById('str-fill');
    const label = document.getElementById('str-label');
    let score   = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const map = [
        { pct:'0%',   bg:'transparent', txt:'' },
        { pct:'25%',  bg:'#dc2626', txt:'Weak' },
        { pct:'50%',  bg:'#d97706', txt:'Fair' },
        { pct:'75%',  bg:'#2563eb', txt:'Good' },
        { pct:'100%', bg:'#16a34a', txt:'Strong' },
    ];
    const s = map[Math.min(score, 4)];
    fill.style.width      = s.pct;
    fill.style.background = s.bg;
    label.textContent     = s.txt;
    label.style.color     = s.bg;
}
</script>
</body>
</html>