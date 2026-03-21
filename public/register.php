<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../inc/functions.php';

\USMS\Middleware\CsrfMiddleware::boot();

$errors = [];
$full_name = $national_id = $phone = $email = $phone_raw = $dob = $occupation = $address = $nok_name = $nok_phone = '';
$gender = 'male';

function normalize_phone($raw) {
    $p = preg_replace('/[^\d\+]/', '', $raw);
    if ($p === '') return '';
    if (strpos($p, '+') === 0) return $p;
    if (preg_match('/^0(\d{8,9})$/', $p, $m)) return '+254' . $m[1];
    if (preg_match('/^7(\d{8})$/', $p, $m)) return '+254' . $p;
    return $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $full_name   = trim($_POST['full_name'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $phone_raw   = trim($_POST['phone'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $gender      = $_POST['gender'] ?? 'male';
    $dob         = $_POST['dob'] ?? '';
    $occupation  = trim($_POST['occupation'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $nok_name    = trim($_POST['nok_name'] ?? '');
    $nok_phone   = trim($_POST['nok_phone'] ?? '');
    $phone = normalize_phone($phone_raw);

    $validator = new \USMS\Services\Validator($_POST);
    $validator->required('full_name')->required('national_id')->required('phone')->required('email')->email('email')->required('password')->min('password', 6)->required('dob')->required('address')->required('nok_name')->required('nok_phone');
    if (!$validator->passes()) $errors = array_values($validator->getFirstErrors());
    if ($password !== $confirm) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $checkSql = "SELECT member_id FROM members WHERE email = ? OR phone = ? OR national_id = ? LIMIT 1";
        if ($stmt = $conn->prepare($checkSql)) {
            $stmt->bind_param("sss", $email, $phone, $national_id);
            $stmt->execute(); $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = "A member with that email, phone, or national ID already exists.";
            $stmt->close();
        } else { $errors[] = "Database error. Please try again."; }
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $reg_no = generate_member_no($conn);
        $status = 'inactive';
        $kyc_status = 'pending';
        
        $conn->begin_transaction();
        try {
            $insertSql = "INSERT INTO members (member_reg_no, full_name, national_id, phone, email, password, join_date, status, reg_fee_paid, gender, dob, occupation, address, next_of_kin_name, next_of_kin_phone, kyc_status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 0, ?, ?, ?, ?, ?, ?, ?)";
            if ($ins = $conn->prepare($insertSql)) {
                $ins->bind_param("ssssssssssssss", $reg_no, $full_name, $national_id, $phone, $email, $hashed, $status, $gender, $dob, $occupation, $address, $nok_name, $nok_phone, $kyc_status);
                if ($ins->execute()) {
                    $newMemberId = $ins->insert_id;
                    
                    // Handle KYC Uploads
                    $upload_dir = __DIR__ . '/../uploads/kyc/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    
                    $files_to_process = [
                        'passport_photo'    => 'passport_photo',
                        'national_id_front' => 'national_id_front',
                        'national_id_back'  => 'national_id_back'
                    ];

                    foreach ($files_to_process as $input_name => $doc_type) {
                        if (!empty($_FILES[$input_name]['name']) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                            $ext      = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                            $filename = "{$doc_type}_{$newMemberId}_" . time() . ".$ext";
                            $target   = $upload_dir . $filename;
                            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target)) {
                                $doc_stmt = $conn->prepare("INSERT INTO member_documents (member_id, document_type, file_path, status) VALUES (?, ?, ?, 'pending')");
                                $doc_stmt->bind_param("iss", $newMemberId, $doc_type, $filename);
                                $doc_stmt->execute();
                                $doc_stmt->close();
                            }
                        } else {
                            throw new Exception("Please upload all required KYC documents.");
                        }
                    }

                    $conn->commit();
                    
                    session_regenerate_id(true);
                    $_SESSION['member_id'] = $newMemberId;
                    $_SESSION['member_name'] = $full_name;
                    $_SESSION['reg_no'] = $reg_no;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = 'member';
                    $_SESSION['status'] = $status;
                    $_SESSION['gender'] = $gender;
                    
                    require_once __DIR__ . '/../inc/notification_helpers.php';
                    send_notification($conn, (int)$newMemberId, 'registration_success', ['member_no' => $reg_no]);
                    
                    $ins->close();
                    header("Location: ../member/pages/pay_registration.php"); exit;
                } else { 
                    $ins->close();
                    throw new Exception("Registration failed: " . $ins->error); 
                }
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: stretch;
        background: #0B1E17;
    }

    /* ─── Left Branding Panel ─── */
    .reg-left {
        width: 380px;
        min-width: 380px;
        background:
            linear-gradient(160deg, rgba(11,30,22,0.94) 0%, rgba(15,57,43,0.90) 60%, rgba(10,24,18,0.97) 100%),
            url('<?= BACKGROUND_IMAGE ?>') center/cover no-repeat;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 44px 40px;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow: hidden;
    }
    .reg-left::before {
        content: '';
        position: absolute;
        top: -100px; right: -100px;
        width: 360px; height: 360px;
        background: radial-gradient(circle, rgba(57,181,74,0.16) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }
    .reg-left::after {
        content: '';
        position: absolute;
        bottom: -80px; left: -60px;
        width: 280px; height: 280px;
        background: radial-gradient(circle, rgba(163,230,53,0.09) 0%, transparent 65%);
        border-radius: 50%;
        pointer-events: none;
    }

    .rl-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 2;
    }
    .rl-brand-logo {
        width: 44px; height: 44px;
        border-radius: 12px;
        background: #fff;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        flex-shrink: 0;
    }
    .rl-brand-logo img { width: 100%; height: 100%; object-fit: contain; padding: 5px; }
    .rl-brand-name { font-size: 0.88rem; font-weight: 800; color: #fff; letter-spacing: -0.2px; }
    .rl-brand-sub  { font-size: 0.6rem; font-weight: 600; color: rgba(255,255,255,0.38); text-transform: uppercase; letter-spacing: 0.8px; }

    .rl-hero { position: relative; z-index: 2; }
    .rl-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(163,230,53,0.1);
        border: 1px solid rgba(163,230,53,0.2);
        border-radius: 100px;
        padding: 5px 13px;
        font-size: 0.65rem;
        font-weight: 700;
        color: #A3E635;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-bottom: 16px;
    }
    .rl-eyebrow-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #A3E635;
        animation: rlPulse 2s ease-in-out infinite;
    }
    @keyframes rlPulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
    .rl-title {
        font-size: 2.1rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.8px;
        line-height: 1.1;
        margin-bottom: 14px;
    }
    .rl-title span { color: #A3E635; }
    .rl-desc {
        font-size: 0.84rem;
        color: rgba(255,255,255,0.48);
        font-weight: 500;
        line-height: 1.7;
        margin-bottom: 28px;
    }

    .rl-steps { display: flex; flex-direction: column; gap: 14px; }
    .rl-step {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .rl-step-num {
        width: 30px; height: 30px;
        border-radius: 9px;
        background: rgba(163,230,53,0.1);
        border: 1px solid rgba(163,230,53,0.18);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem;
        font-weight: 800;
        color: #A3E635;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .rl-step-title { font-size: 0.84rem; font-weight: 700; color: rgba(255,255,255,0.8); margin-bottom: 2px; }
    .rl-step-sub   { font-size: 0.72rem; color: rgba(255,255,255,0.35); font-weight: 500; }

    .rl-footer { position: relative; z-index: 2; }
    .rl-footer p { font-size: 0.67rem; color: rgba(255,255,255,0.2); font-weight: 600; }
    .rl-footer strong { color: rgba(255,255,255,0.4); }

    /* ─── Right Form Panel ─── */
    .reg-right {
        flex: 1;
        background: #F7FBF9;
        overflow-y: auto;
        padding: 48px 52px;
        position: relative;
    }
    .reg-right::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: linear-gradient(to bottom, #0F392B, #39B54A, #A3E635);
    }

    .rr-inner { max-width: 680px; margin: 0 auto; }

    /* Top Nav Row */
    .rr-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 32px;
    }
    .rr-top h2 {
        font-size: 1.6rem;
        font-weight: 800;
        color: #0F392B;
        letter-spacing: -0.4px;
        margin: 0 0 3px;
    }
    .rr-top p { font-size: 0.82rem; color: #7a9e8e; font-weight: 500; margin: 0; }
    .rr-login-link {
        font-size: 0.72rem;
        font-weight: 800;
        color: #0F392B;
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        border-bottom: 2px solid #A3E635;
        padding-bottom: 2px;
        white-space: nowrap;
        transition: opacity 0.2s;
    }
    .rr-login-link:hover { opacity: 0.6; }

    /* Error Box */
    .rr-errors {
        background: #FEE2E2;
        border: 1px solid #FECACA;
        border-radius: 14px;
        padding: 14px 18px;
        margin-bottom: 24px;
        animation: errIn 0.3s ease both;
    }
    @keyframes errIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .rr-errors ul { margin: 0; padding-left: 18px; }
    .rr-errors li { font-size: 0.8rem; font-weight: 600; color: #991b1b; line-height: 1.7; }

    /* Section Titles */
    .rr-section {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 28px 0 18px;
    }
    .rr-section-icon {
        width: 30px; height: 30px;
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .rr-section-icon-a { background: #E8F5E9; color: #1a6b35; }
    .rr-section-icon-b { background: #EFF6FF; color: #1d4ed8; }
    .rr-section-icon-c { background: #FFF7ED; color: #c2410c; }
    .rr-section-icon-d { background: #F5F3FF; color: #7c3aed; }
    .rr-section-name {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #0F392B;
    }
    .rr-section::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #E0EDE7;
    }

    /* Form labels */
    .rr-label {
        display: block;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #7a9e8e;
        margin-bottom: 6px;
    }

    /* Inputs */
    .rr-input {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 600;
        color: #0F392B;
        background: #fff;
        border: 1.5px solid #E0EDE7;
        border-radius: 12px;
        padding: 11px 14px;
        width: 100%;
        outline: none;
        transition: all 0.2s;
        -webkit-appearance: none;
    }
    .rr-input:focus {
        border-color: #39B54A;
        box-shadow: 0 0 0 4px rgba(57,181,74,0.08);
        background: #fff;
    }
    .rr-input::placeholder { color: #b8cec8; font-weight: 500; }

    .rr-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
        background: #fff;
        border: 1.5px solid #E0EDE7;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.2s;
    }
    .rr-input-wrap:focus-within {
        border-color: #39B54A;
        box-shadow: 0 0 0 4px rgba(57,181,74,0.08);
    }
    .rr-input-wrap .rr-input {
        border: none;
        border-radius: 0;
        box-shadow: none !important;
        background: transparent;
        flex: 1;
    }
    .rr-toggle-btn {
        padding: 11px 13px;
        background: transparent;
        border: none;
        outline: none;
        color: #a0b8b0;
        cursor: pointer;
        font-size: 0.9rem;
        flex-shrink: 0;
        transition: color 0.2s;
    }
    .rr-toggle-btn:hover { color: #0F392B; }

    /* Submit */
    .rr-submit {
        width: 100%;
        background: linear-gradient(135deg, #0F392B, #1a5c43);
        color: #fff;
        border: none;
        border-radius: 13px;
        padding: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.9rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 6px 20px rgba(15,57,43,0.28);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        letter-spacing: 0.2px;
        margin-top: 8px;
    }
    .rr-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(15,57,43,0.36); }
    .rr-submit:active { transform: translateY(0); }
    .rr-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
    .rr-submit .spinner-border { width: 1rem; height: 1rem; border-width: 0.15em; }

    .rr-terms {
        text-align: center;
        font-size: 0.76rem;
        font-weight: 500;
        color: #7a9e8e;
        margin-top: 16px;
        line-height: 1.6;
    }
    .rr-terms a { color: #0F392B; font-weight: 700; text-decoration: none; border-bottom: 1px solid #A3E635; }

    /* Progress dots */
    .rr-progress {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 28px;
    }
    .rr-progress-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #E0EDE7;
        transition: all 0.3s;
    }
    .rr-progress-dot.fill { background: #39B54A; width: 24px; border-radius: 4px; }

    @media (max-width: 900px) {
        .reg-left { display: none; }
        .reg-right { padding: 36px 24px; }
    }
    </style>
</head>
<body>

<!-- Left Panel -->
<div class="reg-left">
    <a href="<?= BASE_URL ?>/public/index.php" class="rl-brand text-decoration-none">
        <div class="rl-brand-logo">
            <img src="<?= SITE_LOGO ?>" alt="<?= SITE_NAME ?>">
        </div>
        <div>
            <div class="rl-brand-name"><?= htmlspecialchars(SITE_NAME) ?></div>
            <div class="rl-brand-sub">Member Portal</div>
        </div>
    </a>

    <div class="rl-hero">
        <div class="rl-eyebrow">
            <span class="rl-eyebrow-dot"></span> New Member
        </div>
        <h1 class="rl-title">
            Join the<br><span>Sacco</span><br>community.
        </h1>
        <p class="rl-desc">
            Start your journey toward financial freedom. Simple registration, secure access, full control.
        </p>
        <div class="rl-steps">
            <div class="rl-step">
                <div class="rl-step-num">1</div>
                <div>
                    <div class="rl-step-title">Personal Details</div>
                    <div class="rl-step-sub">ID, name, date of birth & contact</div>
                </div>
            </div>
            <div class="rl-step">
                <div class="rl-step-num">2</div>
                <div>
                    <div class="rl-step-title">Account Security</div>
                    <div class="rl-step-sub">Email, password & next of kin</div>
                </div>
            </div>
            <div class="rl-step">
                <div class="rl-step-num">3</div>
                <div>
                    <div class="rl-step-title">Portal Access</div>
                    <div class="rl-step-sub">Pay registration fee & go live</div>
                </div>
            </div>
        </div>
    </div>

    <div class="rl-footer">
        <p>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(SITE_NAME) ?></strong>. All rights reserved.</p>
    </div>
</div>

<!-- Right Panel -->
<div class="reg-right">
    <div class="rr-inner">

        <div class="rr-top">
            <div>
                <h2>Create Account</h2>
                <p>Fill in your details to get started</p>
            </div>
            <a href="login.php" class="rr-login-link">Sign In Instead</a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="rr-errors">
            <ul>
                <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="regForm" enctype="multipart/form-data">
            <?php csrf_field(); ?>

            <!-- Personal Info -->
            <div class="rr-section">
                <div class="rr-section-icon rr-section-icon-a"><i class="bi bi-person-fill"></i></div>
                <span class="rr-section-name">Personal Information</span>
            </div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="rr-label">Full Name <span style="color:#dc2626">*</span></label>
                    <input type="text" name="full_name" class="rr-input" required
                        value="<?= htmlspecialchars($full_name) ?>" placeholder="As per National ID">
                </div>
                <div class="col-md-6">
                    <label class="rr-label">National ID <span style="color:#dc2626">*</span></label>
                    <input type="text" name="national_id" class="rr-input" required
                        value="<?= htmlspecialchars($national_id) ?>" placeholder="8-digit ID number">
                </div>
                <div class="col-md-6">
                    <label class="rr-label">Phone Number <span style="color:#dc2626">*</span></label>
                    <input type="text" name="phone" class="rr-input" required
                        value="<?= htmlspecialchars($phone_raw) ?>" placeholder="07xxxxxxxx">
                </div>
                <div class="col-md-4">
                    <label class="rr-label">Gender</label>
                    <select name="gender" class="rr-input" style="cursor:pointer;">
                        <option value="male"   <?= $gender==='male'   ?'selected':'' ?>>Male</option>
                        <option value="female" <?= $gender==='female' ?'selected':'' ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="rr-label">Date of Birth <span style="color:#dc2626">*</span></label>
                    <input type="date" name="dob" class="rr-input" required value="<?= htmlspecialchars($dob) ?>">
                </div>
                <div class="col-md-4">
                    <label class="rr-label">Occupation</label>
                    <input type="text" name="occupation" class="rr-input"
                        value="<?= htmlspecialchars($occupation) ?>" placeholder="e.g. Driver">
                </div>
                <div class="col-12">
                    <label class="rr-label">Home Address <span style="color:#dc2626">*</span></label>
                    <input type="text" name="address" class="rr-input" required
                        value="<?= htmlspecialchars($address) ?>" placeholder="e.g. Ruiru, Kiambu County">
                </div>
            </div>

            <!-- Next of Kin -->
            <div class="rr-section">
                <div class="rr-section-icon rr-section-icon-c"><i class="bi bi-people-fill"></i></div>
                <span class="rr-section-name">Next of Kin</span>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="rr-label">Full Name <span style="color:#dc2626">*</span></label>
                    <input type="text" name="nok_name" class="rr-input" required
                        value="<?= htmlspecialchars($nok_name) ?>" placeholder="Next of kin full name">
                </div>
                <div class="col-md-6">
                    <label class="rr-label">Phone Number <span style="color:#dc2626">*</span></label>
                    <input type="text" name="nok_phone" class="rr-input" required
                        value="<?= htmlspecialchars($nok_phone) ?>" placeholder="07xxxxxxxx">
                </div>
            </div>

            <!-- KYC Documents -->
            <div class="rr-section">
                <div class="rr-section-icon rr-section-icon-b" style="background: #FFFBEB; color: #92400E;"><i class="bi bi-file-earmark-medical-fill"></i></div>
                <span class="rr-section-name">KYC Documents</span>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="rr-label">Passport Photo <span style="color:#dc2626">*</span></label>
                    <input type="file" name="passport_photo" class="rr-input" required accept="image/*">
                </div>
                <div class="col-md-4">
                    <label class="rr-label">National ID (Front) <span style="color:#dc2626">*</span></label>
                    <input type="file" name="national_id_front" class="rr-input" required accept="image/*">
                </div>
                <div class="col-md-4">
                    <label class="rr-label">National ID (Back) <span style="color:#dc2626">*</span></label>
                    <input type="file" name="national_id_back" class="rr-input" required accept="image/*">
                </div>
            </div>

            <!-- Account -->
            <div class="rr-section">
                <div class="rr-section-icon rr-section-icon-d"><i class="bi bi-shield-lock-fill"></i></div>
                <span class="rr-section-name">Account Credentials</span>
            </div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="rr-label">Email Address <span style="color:#dc2626">*</span></label>
                    <input type="email" name="email" class="rr-input" required
                        value="<?= htmlspecialchars($email) ?>" placeholder="your@email.com">
                </div>
                <div class="col-md-6">
                    <label class="rr-label">Password <span style="color:#dc2626">*</span></label>
                    <div class="rr-input-wrap">
                        <input type="password" name="password" id="pass1" class="rr-input" required placeholder="Min. 6 characters">
                        <button type="button" class="rr-toggle-btn" onclick="togglePass('pass1','icon1')">
                            <i class="bi bi-eye-slash" id="icon1"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="rr-label">Confirm Password <span style="color:#dc2626">*</span></label>
                    <div class="rr-input-wrap">
                        <input type="password" name="confirm_password" id="pass2" class="rr-input" required placeholder="Repeat password">
                        <button type="button" class="rr-toggle-btn" onclick="togglePass('pass2','icon2')">
                            <i class="bi bi-eye-slash" id="icon2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="rr-submit" id="submitBtn">
                    <span id="btnText"><i class="bi bi-person-plus-fill me-1"></i> Create My Account</span>
                    <div class="spinner-border d-none" role="status" id="btnSpinner"></div>
                </button>
            </div>

            <p class="rr-terms">
                By registering, you agree to our
                <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
            </p>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    }
}

document.getElementById('regForm').addEventListener('submit', function() {
    const btn     = document.getElementById('submitBtn');
    const text    = document.getElementById('btnText');
    const spinner = document.getElementById('btnSpinner');
    btn.disabled = true;
    spinner.classList.remove('d-none');
    text.innerHTML = 'Setting up your account…';
});
</script>
</body>
</html>