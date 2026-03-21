<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');

if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $email      = trim($_POST['email']       ?? '');
    $phone      = trim($_POST['phone']       ?? '');
    $address    = trim($_POST['address']     ?? '');
    $gender     = trim($_POST['gender']      ?? '');
    $dob        = trim($_POST['dob']         ?? '');
    $occupation = trim($_POST['occupation']  ?? '');
    $nok_name   = trim($_POST['nok_name']    ?? '');
    $nok_phone  = trim($_POST['nok_phone']   ?? '');
    $remove_pic = isset($_POST['remove_pic']);

    $stmt = $conn->prepare("SELECT profile_pic FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id); $stmt->execute();
    $current  = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $pic_data = $current['profile_pic'];

    if ($remove_pic) {
        $pic_data = null;
    } elseif (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $f_tmp  = $_FILES['profile_pic']['tmp_name'];
        $f_type = mime_content_type($f_tmp);
        $f_size = $_FILES['profile_pic']['size'];
        if (!in_array($f_type, ['image/jpeg','image/png','image/jpg','image/webp'])) {
            $_SESSION['error'] = "Invalid file type. Upload JPG, PNG or WEBP.";
            header("Location: profile.php"); exit;
        }
        if ($f_size > 1 * 1024 * 1024) {
            $_SESSION['error'] = "Image too large (max 1 MB). Please compress it.";
            header("Location: profile.php"); exit;
        }
        $pic_data = file_get_contents($f_tmp);
    }

    // KYC upload
    if (!empty($_FILES['kyc_doc']['name']) && $_FILES['kyc_doc']['error'] === UPLOAD_ERR_OK) {
        $doc_type = $_POST['doc_type'] ?? '';
        $kf_type  = mime_content_type($_FILES['kyc_doc']['tmp_name']);
        $kf_size  = $_FILES['kyc_doc']['size'];
        if (!in_array($kf_type, ['image/jpeg','image/png','application/pdf'])) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG and PDF allowed.";
        } elseif ($kf_size > 5 * 1024 * 1024) {
            $_SESSION['error'] = "File too large. Max 5 MB.";
        } else {
            $upload_dir = __DIR__ . '/../../uploads/kyc/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext      = pathinfo($_FILES['kyc_doc']['name'], PATHINFO_EXTENSION);
            $new_name = "{$doc_type}_{$member_id}_".time().".$ext";
            if (move_uploaded_file($_FILES['kyc_doc']['tmp_name'], $upload_dir.$new_name)) {
                $stmt = $conn->prepare("INSERT INTO member_documents (member_id, document_type, file_path, status) VALUES (?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), status='pending', uploaded_at=NOW()");
                $stmt->bind_param("iss", $member_id, $doc_type, $new_name); $stmt->execute(); $stmt->close();
                $conn->query("UPDATE members SET kyc_status='pending' WHERE member_id=$member_id AND kyc_status='not_submitted'");
            }
        }
    }

    $sql  = "UPDATE members SET email=?, phone=?, address=?, gender=?, dob=?, occupation=?, next_of_kin_name=?, next_of_kin_phone=?, profile_pic=? WHERE member_id=?";
    $stmt = $conn->prepare($sql);
    $null = null;
    $stmt->bind_param("ssssssssbi", $email, $phone, $address, $gender, $dob, $occupation, $nok_name, $nok_phone, $null, $member_id);
    if ($pic_data !== null) $stmt->send_long_data(8, $pic_data);
    if ($stmt->execute()) {
        require_once __DIR__ . '/../../inc/notification_helpers.php';
        send_notification($conn, (int)$member_id, 'profile_updated');
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php"); exit;
    } else {
        $_SESSION['error'] = "Update failed: " . $conn->error;
    }
    $stmt->close();
}

// Fetch member data
$stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id); $stmt->execute();
$member = $stmt->get_result()->fetch_assoc(); $stmt->close();

// Registration fee tx
$fee_stmt = $conn->prepare("SELECT * FROM transactions WHERE member_id = ? AND transaction_type = 'registration_fee' OR (related_table = 'members' AND description LIKE '%Registration%') ORDER BY created_at DESC LIMIT 1");
$fee_stmt->bind_param("i", $member_id); $fee_stmt->execute();
$fee_txn = $fee_stmt->get_result()->fetch_assoc(); $fee_stmt->close();

$reg_fee_paid = ($member['registration_fee_status'] === 'paid' || $member['reg_fee_paid'] == 1);

$account_status = 'Incomplete'; $status_cls = 'red';
if (!$reg_fee_paid)                                                     { $account_status = 'Fee Unpaid';      $status_cls = 'red'; }
elseif ($member['kyc_status'] === 'not_submitted')                      { $account_status = 'No KYC Uploaded'; $status_cls = 'amb'; }
elseif ($member['kyc_status'] === 'pending')                            { $account_status = 'Under Review';    $status_cls = 'blu'; }
elseif ($member['kyc_status'] === 'approved' && $reg_fee_paid)          { $account_status = 'Active';          $status_cls = 'grn'; }
elseif ($member['kyc_status'] === 'rejected')                           { $account_status = 'KYC Rejected';    $status_cls = 'red'; }

// KYC docs
$doc_stmt = $conn->prepare("SELECT * FROM member_documents WHERE member_id = ?");
$doc_stmt->bind_param("i", $member_id); $doc_stmt->execute();
$kyc_docs = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC); $doc_stmt->close();

// Profile picture
$gender_check = strtolower(trim($member['gender'] ?? ''));
$display_pic  = BASE_URL . '/public/assets/uploads/' . ($gender_check === 'female' ? 'female.jpg' : 'male.jpg');
if (!empty($member['profile_pic'])) $display_pic = 'data:image/jpeg;base64,' . base64_encode($member['profile_pic']);

// Avatar initials
$name_parts = explode(' ', trim($member['full_name']));
$initials   = strtoupper(substr($name_parts[0],0,1) . (isset($name_parts[1]) ? substr($name_parts[1],0,1) : ''));

$pageTitle  = "My Profile";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<script>(function(){var s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?> · <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   MEMBER PROFILE · HD EDITION · Forest & Lime
═══════════════════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --f:#0b2419;--fm:#154330;--fs:#1d6044;
    --lime:#a3e635;--lt:#6a9a1a;--lg:rgba(163,230,53,.14);
    --bg:#eff5f1;--bg2:#e8f1ec;--surf:#fff;--surf2:#f7fbf8;
    --bdr:rgba(11,36,25,.07);--bdr2:rgba(11,36,25,.04);
    --t1:#0b2419;--t2:#456859;--t3:#8fada0;
    --grn:#16a34a;--red:#dc2626;--amb:#d97706;--blu:#2563eb;
    --grn-bg:rgba(22,163,74,.08);--red-bg:rgba(220,38,38,.08);
    --amb-bg:rgba(217,119,6,.08);--blu-bg:rgba(37,99,235,.08);
    --r:20px;--rsm:12px;
    --ease:cubic-bezier(.16,1,.3,1);--spring:cubic-bezier(.34,1.56,.64,1);
    --sh:0 1px 3px rgba(11,36,25,.05),0 6px 20px rgba(11,36,25,.08);
    --sh-lg:0 4px 8px rgba(11,36,25,.07),0 20px 56px rgba(11,36,25,.13);
}
[data-bs-theme="dark"]{
    --bg:#070e0b;--bg2:#0a1510;--surf:#0d1d14;--surf2:#0a1810;
    --bdr:rgba(255,255,255,.07);--bdr2:rgba(255,255,255,.04);
    --t1:#d8eee2;--t2:#4d7a60;--t3:#2a4d38;
}
body,*{font-family:'Plus Jakarta Sans',sans-serif!important;-webkit-font-smoothing:antialiased}
body{background:var(--bg);color:var(--t1)}
.main-content-wrapper{margin-left:272px;min-height:100vh;transition:margin-left .3s var(--ease)}
body.sb-collapsed .main-content-wrapper{margin-left:72px}
@media(max-width:991px){.main-content-wrapper{margin-left:0}}
.dash{padding:28px 28px 72px}
@media(max-width:768px){.dash{padding:16px 14px 48px}}

/* ── HERO ── */
.hero{background:linear-gradient(135deg,var(--f) 0%,var(--fm) 55%,var(--fs) 100%);border-radius:var(--r);padding:36px 48px 90px;position:relative;overflow:hidden;color:#fff;animation:fadeUp .7s var(--ease) both}
.hero-mesh{position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse 60% 80% at 105% -5%,rgba(163,230,53,.11) 0%,transparent 55%),radial-gradient(ellipse 35% 45% at -8% 105%,rgba(163,230,53,.07) 0%,transparent 55%)}
.hero-dots{position:absolute;inset:0;pointer-events:none;background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);background-size:20px 20px}
.hero-ring{position:absolute;border-radius:50%;pointer-events:none;border:1px solid rgba(163,230,53,.07)}
.hero-ring.r1{width:380px;height:380px;top:-130px;right:-90px}
.hero-ring.r2{width:560px;height:560px;top:-200px;right:-180px}
.hero-inner{position:relative;z-index:2}

.hero-eyebrow{display:inline-flex;align-items:center;gap:7px;background:rgba(163,230,53,.12);border:1px solid rgba(163,230,53,.2);border-radius:50px;padding:4px 14px;margin-bottom:12px;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#bff060}
.hero h1{font-size:clamp(1.6rem,3.5vw,2.4rem);font-weight:800;color:#fff;letter-spacing:-.5px;line-height:1.1;margin-bottom:6px}
.hero-sub{font-size:.8rem;color:rgba(255,255,255,.45);font-weight:500}
.hero-sub strong{color:rgba(255,255,255,.7)}

/* ── FLOATING AVATAR CARD ── */
.avatar-float{margin-top:-68px;position:relative;z-index:10;padding:0 28px;animation:floatUp .8s var(--ease) .38s both}
@media(max-width:767px){.avatar-float{padding:0 14px}}

.av-card{background:var(--surf);border-radius:var(--r);border:1px solid var(--bdr);box-shadow:var(--sh-lg);padding:28px 28px 24px;display:flex;align-items:flex-end;gap:24px;flex-wrap:wrap;position:relative;overflow:hidden}
.av-card::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,rgba(163,230,53,.035) 0%,transparent 60%);pointer-events:none}

.av-wrap{position:relative;flex-shrink:0}
.av-img{width:100px;height:100px;border-radius:50%;object-fit:cover;border:4px solid var(--surf);box-shadow:0 4px 18px rgba(11,36,25,.18);display:block;background:var(--bg)}
.av-initials{width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,var(--f),var(--fm));color:var(--lime);font-size:2.2rem;font-weight:800;display:flex;align-items:center;justify-content:center;border:4px solid var(--surf);box-shadow:0 4px 18px rgba(11,36,25,.18);letter-spacing:-2px}
.av-upload-btn{position:absolute;bottom:2px;right:2px;width:32px;height:32px;border-radius:50%;background:var(--f);color:var(--lime);border:3px solid var(--surf);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.8rem;transition:all .22s var(--spring)}
.av-upload-btn:hover{transform:scale(1.15) rotate(15deg);background:var(--fm)}

.av-info{flex:1;min-width:0}
.av-name{font-size:1.25rem;font-weight:800;color:var(--t1);letter-spacing:-.3px;margin-bottom:4px}
.av-sub{font-size:.78rem;font-weight:500;color:var(--t3);margin-bottom:12px}
.av-badges{display:flex;gap:8px;flex-wrap:wrap}
.av-badge{display:inline-flex;align-items:center;gap:5px;background:var(--bg);border:1px solid var(--bdr);border-radius:50px;padding:4px 12px;font-size:10px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:var(--t2)}

.av-status-grn{background:var(--grn-bg);border-color:rgba(22,163,74,.2);color:var(--grn)}
.av-status-red{background:var(--red-bg);border-color:rgba(220,38,38,.2);color:var(--red)}
.av-status-amb{background:var(--amb-bg);border-color:rgba(217,119,6,.2);color:var(--amb)}
.av-status-blu{background:var(--blu-bg);border-color:rgba(37,99,235,.2);color:var(--blu)}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes floatUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}

/* ── PAGE BODY ── */
.pg-body{padding:28px 28px 0}
@media(max-width:767px){.pg-body{padding:20px 14px 0}}

/* ── ALERT BANNERS ── */
.alert-banner{display:flex;align-items:flex-start;gap:13px;border-radius:var(--rsm);padding:14px 18px;margin-bottom:14px;font-size:.82rem;font-weight:600;animation:fadeUp .4s var(--ease) both}
.ab-ico{font-size:1.2rem;flex-shrink:0;margin-top:1px}
.ab-title{font-size:.83rem;font-weight:800;margin-bottom:2px}
.ab-sub{font-size:.76rem;font-weight:500;line-height:1.5}
.ab-warn{background:var(--amb-bg);border:1px solid rgba(217,119,6,.22);color:var(--amb)}
.ab-err {background:var(--red-bg);border:1px solid rgba(220,38,38,.22);color:var(--red)}
.ab-info{background:var(--blu-bg);border:1px solid rgba(37,99,235,.22);color:var(--blu)}
.ab-ok  {background:var(--grn-bg);border:1px solid rgba(22,163,74,.22); color:var(--grn)}
.ab-cta{display:inline-flex;align-items:center;gap:6px;background:var(--blu);color:#fff;font-size:.72rem;font-weight:800;padding:6px 14px;border-radius:50px;text-decoration:none;margin-top:8px;transition:all .18s ease}
.ab-cta:hover{background:#1d4fd8;color:#fff;transform:translateY(-1px)}
.ab-close{margin-left:auto;background:none;border:none;color:inherit;opacity:.5;cursor:pointer;font-size:1rem;padding:0;line-height:1;flex-shrink:0}

/* ── SECTION CARD ── */
.sec-card{background:var(--surf);border-radius:var(--r);border:1px solid var(--bdr);box-shadow:var(--sh);overflow:hidden;animation:floatUp .7s var(--ease) both;margin-bottom:20px}
.sec-card-head{display:flex;align-items:center;gap:12px;padding:20px 26px;border-bottom:1px solid var(--bdr2);background:var(--surf2)}
.sch-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0}
.sch-title{font-size:.9rem;font-weight:800;color:var(--t1);letter-spacing:-.2px}
.sch-sub  {font-size:.7rem;font-weight:500;color:var(--t3);margin-top:1px}
.sec-card-body{padding:24px 26px}

/* ── SECTION LABEL ── */
.sec-lbl{display:flex;align-items:center;gap:12px;font-size:9.5px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:var(--t3);margin-bottom:18px}
.sec-lbl::after{content:'';flex:1;height:1px;background:var(--bdr)}

/* ── FORM FIELDS ── */
.field-lbl{display:block;font-size:10px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:var(--t3);margin-bottom:7px}
.field-ctrl{width:100%;padding:10px 14px;background:var(--surf2);border:1px solid var(--bdr);border-radius:var(--rsm);color:var(--t1);font-size:.875rem;font-weight:500;outline:none;transition:border-color .18s ease,box-shadow .18s ease}
.field-ctrl:focus{border-color:rgba(11,36,25,.3);box-shadow:0 0 0 3px rgba(11,36,25,.07)}
.field-ctrl:read-only,.field-ctrl[readonly]{opacity:.5;cursor:not-allowed}
.field-ctrl select{appearance:none}
.input-icon-wrap{position:relative}
.input-icon-wrap .field-ctrl{padding-left:38px}
.input-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--t3);font-size:.82rem;pointer-events:none}

/* ── SAVE BUTTON ── */
.btn-save{display:inline-flex;align-items:center;gap:8px;background:var(--lime);color:var(--f);font-size:.875rem;font-weight:800;padding:12px 28px;border-radius:50px;border:none;cursor:pointer;box-shadow:0 2px 14px rgba(163,230,53,.28);transition:all .25s var(--spring)}
.btn-save:hover{transform:translateY(-2px) scale(1.03);box-shadow:0 10px 28px rgba(163,230,53,.4);color:var(--f)}
.btn-back-link{display:inline-flex;align-items:center;gap:7px;background:var(--surf2);border:1px solid var(--bdr);color:var(--t2);font-size:.82rem;font-weight:700;padding:10px 20px;border-radius:50px;text-decoration:none;transition:all .18s ease}
.btn-back-link:hover{border-color:rgba(11,36,25,.18);color:var(--t1)}

/* ── REG FEE BLOCK ── */
.fee-block{background:var(--surf2);border:1px solid var(--bdr);border-radius:14px;padding:16px 18px}
.fee-status{display:flex;align-items:center;justify-content:space-between;gap:12px}
.fee-lbl{font-size:10px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:var(--t3);margin-bottom:5px}
.fee-val{font-size:.88rem;font-weight:800}
.fee-ref{font-size:.68rem;color:var(--t3);margin-top:3px;font-family:monospace}

/* ── KYC DOC LIST ── */
.kyc-list{list-style:none;margin:0;padding:0}
.kyc-item{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--bdr2);gap:12px}
.kyc-item:last-child{border-bottom:none}
.ki-type{font-size:.8rem;font-weight:700;color:var(--t1);text-transform:uppercase;margin-bottom:2px;letter-spacing:.3px}
.ki-date{font-size:.65rem;font-weight:500;color:var(--t3)}

/* ── KYC UPLOAD CARDS ── */
.kyc-upload-card{background:var(--surf2);border:1px solid var(--bdr);border-radius:var(--rsm);padding:18px;height:100%;transition:border-color .18s ease}
.kyc-upload-card:hover{border-color:rgba(11,36,25,.15)}
.kyc-uc-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;gap:8px}
.kyc-uc-title{font-size:.82rem;font-weight:800;color:var(--t1)}
.kyc-verified{display:flex;flex-direction:column;align-items:center;padding:18px 0;gap:8px}
.kyc-verified-ico{width:52px;height:52px;border-radius:14px;background:var(--grn-bg);display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--grn)}
.kyc-verified-lbl{font-size:.72rem;font-weight:700;color:var(--grn)}
.kyc-verified-date{font-size:.65rem;color:var(--t3)}

/* doc status chips */
.doc-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:7px;font-size:9px;font-weight:800;letter-spacing:.3px;text-transform:uppercase}
.chip-verified{background:var(--grn-bg);color:var(--grn)}
.chip-pending {background:var(--amb-bg);color:var(--amb)}
.chip-rejected{background:var(--red-bg);color:var(--red)}
.chip-missing {background:rgba(11,36,25,.06);color:var(--t3)}

/* file input */
.kyc-file-wrap{margin-bottom:10px}
.kyc-file{width:100%;font-size:.78rem;color:var(--t2);cursor:pointer}
.kyc-file::file-selector-button{background:var(--f);color:var(--lime);border:none;border-radius:8px;padding:6px 12px;font-size:.72rem;font-weight:800;cursor:pointer;transition:all .18s ease;font-family:'Plus Jakarta Sans',sans-serif!important;margin-right:10px}
.kyc-file::file-selector-button:hover{background:var(--fm)}

.btn-upload-doc{width:100%;padding:9px;border-radius:var(--rsm);background:var(--f);color:#fff;border:none;font-size:.78rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:all .22s var(--ease)}
.btn-upload-doc:hover{background:var(--fm);transform:translateY(-1px);box-shadow:0 5px 14px rgba(11,36,25,.18)}
.btn-reupload{background:var(--surf);border:1px solid var(--bdr);color:var(--t2)}
.btn-reupload:hover{background:var(--bg);border-color:rgba(11,36,25,.18);color:var(--t1)}

/* remove pic checkbox */
.rm-pic-row{display:flex;align-items:center;gap:9px;padding:10px 0;cursor:pointer}
.rm-pic-cb{width:16px;height:16px;accent-color:var(--red);flex-shrink:0;cursor:pointer}
.rm-pic-lbl{font-size:.78rem;font-weight:700;color:var(--red);cursor:pointer}

::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--bdr);border-radius:99px}
</style>
</head>
<body>
<?php $layout->sidebar(); ?>

<div class="main-content-wrapper">
<?php $layout->topbar($pageTitle ?? ''); ?>
<div class="dash">

<!-- ═══════════════ HERO ═══════════════ -->
<div class="hero">
    <div class="hero-mesh"></div><div class="hero-dots"></div>
    <div class="hero-ring r1"></div><div class="hero-ring r2"></div>
    <div class="hero-inner">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <div class="hero-eyebrow"><i class="bi bi-person-badge-fill" style="font-size:.75rem"></i> Member Account</div>
                <h1><?= htmlspecialchars($member['full_name']) ?></h1>
                <p class="hero-sub">
                    Reg No <strong><?= htmlspecialchars($member['member_reg_no']) ?></strong>
                    &nbsp;&middot;&nbsp;
                    Joined <strong><?= date('M Y', strtotime($member['join_date'] ?? $member['created_at'])) ?></strong>
                </p>
            </div>
            <a href="dashboard.php" class="btn-back-link mt-1">
                <i class="bi bi-arrow-left" style="font-size:.72rem"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<!-- ═══════════════ AVATAR + NAME FLOAT ═══════════════ -->
<div class="avatar-float">
    <div class="av-card">
        <!-- Avatar -->
        <div class="av-wrap">
            <?php if (!empty($member['profile_pic'])): ?>
                <img id="preview" src="<?= $display_pic ?>" alt="Profile" class="av-img">
            <?php else: ?>
                <div id="preview-initials" class="av-initials"><?= $initials ?></div>
                <img id="preview" src="<?= $display_pic ?>" alt="Profile" class="av-img" style="display:none">
            <?php endif; ?>
            <label for="profile_pic_input" class="av-upload-btn" title="Change Photo">
                <i class="bi bi-camera-fill"></i>
            </label>
        </div>
        <!-- Name + badges -->
        <div class="av-info">
            <div class="av-name"><?= htmlspecialchars($member['full_name']) ?></div>
            <div class="av-sub"><?= htmlspecialchars($member['email']) ?></div>
            <div class="av-badges">
                <span class="av-badge av-status-<?= $status_cls ?>">
                    <i class="bi bi-<?= $status_cls==='grn'?'shield-check-fill':($status_cls==='amb'?'clock-fill':'exclamation-circle-fill') ?>"></i>
                    <?= $account_status ?>
                </span>
                <span class="av-badge"><i class="bi bi-gender-<?= $gender_check==='female'?'female':'male' ?>"></i> <?= ucfirst($gender_check ?: 'Unknown') ?></span>
                <span class="av-badge"><i class="bi bi-calendar-check-fill"></i> Since <?= date('M Y', strtotime($member['created_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ BODY ═══════════════ -->
<div class="pg-body">

    <!-- Alert banners -->
    <?php if ($member['kyc_status'] === 'not_submitted'): ?>
    <div class="alert-banner ab-warn">
        <i class="ab-ico bi bi-shield-exclamation"></i>
        <div><div class="ab-title">KYC Documents Pending</div><div class="ab-sub">Upload your National ID and Passport photo below to complete verification.</div></div>
        <button class="ab-close" onclick="this.closest('.alert-banner').remove()">&times;</button>
    </div>
    <?php elseif ($member['kyc_status'] === 'rejected'): ?>
    <div class="alert-banner ab-err">
        <i class="ab-ico bi bi-x-octagon-fill"></i>
        <div><div class="ab-title">KYC Rejected</div><div class="ab-sub">Reason: <?= htmlspecialchars($member['kyc_notes'] ?? 'Please re-upload clear documents.') ?></div></div>
        <button class="ab-close" onclick="this.closest('.alert-banner').remove()">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!$reg_fee_paid): ?>
    <div class="alert-banner ab-info">
        <i class="ab-ico bi bi-cash-coin"></i>
        <div>
            <div class="ab-title">Registration Fee Pending</div>
            <div class="ab-sub">KES 1,000 is required to unlock full member benefits.</div>
            <a href="pay_registration.php" class="ab-cta"><i class="bi bi-arrow-right-circle-fill"></i> Pay Now</a>
        </div>
        <button class="ab-close" onclick="this.closest('.alert-banner').remove()">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert-banner ab-ok">
        <i class="ab-ico bi bi-check-circle-fill"></i>
        <div><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <button class="ab-close" onclick="this.closest('.alert-banner').remove()">&times;</button>
    </div>
    <?php elseif (!empty($_SESSION['error'])): ?>
    <div class="alert-banner ab-err">
        <i class="ab-ico bi bi-exclamation-triangle-fill"></i>
        <div><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <button class="ab-close" onclick="this.closest('.alert-banner').remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Main form -->
    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="file" name="profile_pic" id="profile_pic_input" accept="image/*" class="d-none" onchange="previewImage(event)">

        <div class="row g-3">

            <!-- Left: Editable fields -->
            <div class="col-lg-6">

                <!-- Contact details -->
                <div class="sec-card" style="animation-delay:.6s">
                    <div class="sec-card-head">
                        <div class="sch-ico" style="background:var(--grn-bg);color:var(--grn)"><i class="bi bi-pencil-square"></i></div>
                        <div><div class="sch-title">Contact Details</div><div class="sch-sub">Update your contact information</div></div>
                    </div>
                    <div class="sec-card-body">
                        <div class="sec-lbl">Editable Fields</div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="field-lbl">Email Address</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-envelope input-icon"></i>
                                    <input type="email" name="email" class="field-ctrl" value="<?= htmlspecialchars($member['email']) ?>" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="field-lbl">Phone Number</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-phone input-icon"></i>
                                    <input type="tel" name="phone" class="field-ctrl" value="<?= htmlspecialchars($member['phone'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="field-lbl">Date of Birth</label>
                                <input type="date" name="dob" class="field-ctrl" value="<?= htmlspecialchars($member['dob'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="field-lbl">Occupation</label>
                                <input type="text" name="occupation" class="field-ctrl" value="<?= htmlspecialchars($member['occupation'] ?? '') ?>" placeholder="e.g. Teacher">
                            </div>
                            <div class="col-12">
                                <label class="field-lbl">Home Address</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-geo-alt input-icon"></i>
                                    <input type="text" name="address" class="field-ctrl" value="<?= htmlspecialchars($member['address'] ?? '') ?>" placeholder="e.g. Nairobi, Kenya">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="rm-pic-row">
                                    <input type="checkbox" name="remove_pic" class="rm-pic-cb">
                                    <span class="rm-pic-lbl"><i class="bi bi-trash3-fill"></i> Remove profile picture</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next of kin -->
                <div class="sec-card" style="animation-delay:.65s">
                    <div class="sec-card-head">
                        <div class="sch-ico" style="background:var(--amb-bg);color:var(--amb)"><i class="bi bi-people-fill"></i></div>
                        <div><div class="sch-title">Next of Kin</div><div class="sch-sub">Emergency contact details</div></div>
                    </div>
                    <div class="sec-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="field-lbl">Full Name</label>
                                <input type="text" name="nok_name" class="field-ctrl" value="<?= htmlspecialchars($member['next_of_kin_name'] ?? '') ?>" placeholder="Next of kin name">
                            </div>
                            <div class="col-md-6">
                                <label class="field-lbl">Phone Number</label>
                                <input type="text" name="nok_phone" class="field-ctrl" value="<?= htmlspecialchars($member['next_of_kin_phone'] ?? '') ?>" placeholder="Phone number">
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /col-lg-6 -->

            <!-- Right: Read-only + KYC list + fee block -->
            <div class="col-lg-6">

                <!-- Account information (locked) -->
                <div class="sec-card" style="animation-delay:.7s">
                    <div class="sec-card-head">
                        <div class="sch-ico" style="background:rgba(11,36,25,.07);color:var(--t2)"><i class="bi bi-shield-lock-fill"></i></div>
                        <div><div class="sch-title">Account Information</div><div class="sch-sub">Locked — contact admin to change</div></div>
                    </div>
                    <div class="sec-card-body">
                        <div class="sec-lbl">Read-only Fields</div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="field-lbl">Full Name</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-person input-icon"></i>
                                    <input type="text" class="field-ctrl" value="<?= htmlspecialchars($member['full_name']) ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="field-lbl">Gender</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-gender-ambiguous input-icon"></i>
                                    <input type="text" class="field-ctrl" value="<?= ucwords($member['gender'] ?? 'Unknown') ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="field-lbl">Date Joined</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-calendar-check input-icon"></i>
                                    <input type="text" class="field-ctrl" value="<?= date('d M Y', strtotime($member['join_date'] ?? $member['created_at'])) ?>" readonly>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="field-lbl">National ID / Passport No.</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-card-heading input-icon"></i>
                                    <input type="text" class="field-ctrl" value="<?= htmlspecialchars($member['national_id'] ?? '—') ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Registration fee block -->
                        <div class="fee-block mt-4">
                            <div class="fee-lbl">Registration Fee</div>
                            <div class="fee-status">
                                <div>
                                    <div class="fee-val" style="color:<?= $reg_fee_paid?'var(--grn)':'var(--red)' ?>">
                                        <?= $reg_fee_paid ? 'PAID' : 'PENDING (KES 1,000)' ?>
                                    </div>
                                    <?php if ($fee_txn): ?>
                                    <div class="fee-ref">Ref: <?= htmlspecialchars($fee_txn['reference_no']) ?> · <?= date('d M Y',strtotime($fee_txn['created_at'])) ?></div>
                                    <?php endif; ?>
                                </div>
                                <i class="bi bi-<?= $reg_fee_paid?'patch-check-fill':'exclamation-circle-fill' ?>" style="font-size:1.6rem;color:<?= $reg_fee_paid?'var(--grn)':'var(--red)' ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KYC document list -->
                <?php if (!empty($kyc_docs)): ?>
                <div class="sec-card" style="animation-delay:.75s">
                    <div class="sec-card-head">
                        <div class="sch-ico" style="background:var(--blu-bg);color:var(--blu)"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <div><div class="sch-title">Uploaded Documents</div><div class="sch-sub">KYC submission history</div></div>
                    </div>
                    <div class="sec-card-body" style="padding-top:14px;padding-bottom:14px">
                        <ul class="kyc-list">
                            <?php foreach ($kyc_docs as $doc): ?>
                            <li class="kyc-item">
                                <div>
                                    <div class="ki-type"><?= str_replace('_',' ',$doc['document_type']) ?></div>
                                    <div class="ki-date">Uploaded: <?= date('d M Y',strtotime($doc['uploaded_at'])) ?></div>
                                </div>
                                <?php
                                $dc = ['verified'=>'chip-verified','pending'=>'chip-pending','rejected'=>'chip-rejected'];
                                $cc = $dc[$doc['status']] ?? 'chip-missing';
                                ?>
                                <span class="doc-chip <?= $cc ?>"><?= strtoupper($doc['status']) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /col-lg-6 -->

        </div><!-- /row -->

        <!-- Save row -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 0 6px;border-top:1px solid var(--bdr);margin-top:8px;flex-wrap:wrap;gap:12px">
            <div style="font-size:.75rem;font-weight:600;color:var(--t3)"><i class="bi bi-lock-fill me-1"></i> Locked fields require admin changes.</div>
            <button type="submit" class="btn-save"><i class="bi bi-check-circle-fill"></i> Save Changes</button>
        </div>

    </form>

    <!-- ═══ KYC UPLOAD SECTION ═══ -->
    <div class="sec-card mt-2" style="animation-delay:.8s">
        <div class="sec-card-head">
            <div class="sch-ico" style="background:var(--lg);color:var(--lt)"><i class="bi bi-cloud-arrow-up-fill"></i></div>
            <div><div class="sch-title">Complete Your KYC</div><div class="sch-sub">Upload required identity documents for verification</div></div>
        </div>
        <div class="sec-card-body">
            <div class="row g-3">
                <?php
                $required_docs = ['national_id_front'=>'National ID (Front)','national_id_back'=>'National ID (Back)','passport_photo'=>'Passport Photo'];
                foreach ($required_docs as $type => $label):
                    $doc = array_filter($kyc_docs, fn($d) => $d['document_type'] === $type);
                    $doc = !empty($doc) ? array_shift($doc) : null;
                    $chip_cls = $doc ? ($doc['status']==='verified'?'chip-verified':($doc['status']==='rejected'?'chip-rejected':'chip-pending')) : 'chip-missing';
                    $chip_txt = $doc ? strtoupper($doc['status']) : 'MISSING';
                ?>
                <div class="col-md-4">
                    <div class="kyc-upload-card">
                        <div class="kyc-uc-head">
                            <div class="kyc-uc-title"><?= $label ?></div>
                            <span class="doc-chip <?= $chip_cls ?>"><?= $chip_txt ?></span>
                        </div>

                        <?php if ($doc && $doc['status'] === 'verified'): ?>
                        <div class="kyc-verified">
                            <div class="kyc-verified-ico"><i class="bi bi-shield-check-fill"></i></div>
                            <div class="kyc-verified-lbl">Verified</div>
                            <div class="kyc-verified-date"><?= date('d M Y',strtotime($doc['verified_at'] ?? 'now')) ?></div>
                        </div>
                        <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="doc_type" value="<?= $type ?>">
                            <div class="kyc-file-wrap">
                                <input type="file" name="kyc_doc" class="kyc-file" required accept="image/*,application/pdf">
                            </div>
                            <button type="submit" class="btn-upload-doc <?= $doc?'btn-reupload':'' ?>">
                                <i class="bi bi-<?= $doc?'arrow-repeat':'cloud-upload-fill' ?>"></i>
                                <?= $doc ? 'Re-upload' : 'Upload Document' ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div><!-- /pg-body -->
</div><!-- /dash -->

<?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img      = document.getElementById('preview');
        const initials = document.getElementById('preview-initials');
        img.src        = e.target.result;
        img.style.display = 'block';
        if (initials) initials.style.display = 'none';
        img.style.animation = 'none';
        requestAnimationFrame(() => {
            img.style.animation = 'fadeUp .4s ease both';
        });
    };
    reader.readAsDataURL(file);
}
</script>
</body>
</html>