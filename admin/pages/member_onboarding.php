<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

$layout = LayoutManager::create('admin');
require_permission();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $full_name   = trim($_POST['full_name']);
    $national_id = trim($_POST['national_id']);
    $phone       = trim($_POST['phone']);
    $email       = trim($_POST['email']);
    $address     = trim($_POST['address'] ?? '');
    $gender      = $_POST['gender'] ?? 'male';
    $password    = $_POST['password'];
    $dob         = $_POST['dob'] ?? null;
    $occupation  = trim($_POST['occupation'] ?? '');
    $nok_name    = trim($_POST['nok_name'] ?? '');
    $nok_phone   = trim($_POST['nok_phone'] ?? '');
    $pay_method  = $_POST['payment_method'] ?? 'cash';
    $paid        = isset($_POST['is_paid']) ? 1 : 0;

    if (empty($full_name) || empty($national_id) || empty($phone) || empty($email)) {
        $errors[] = "All required fields must be filled in.";
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $reg_no     = generate_member_no($conn);
            $hashed     = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
            $fee_status = $paid ? 'paid' : 'unpaid';
            $status     = $paid ? 'active' : 'inactive';
            $kyc_status = 'not_submitted';

            $ins = $conn->prepare("INSERT INTO members (member_reg_no, full_name, national_id, phone, email, address, gender, password, join_date, status, registration_fee_status, reg_fee_paid, dob, occupation, next_of_kin_name, next_of_kin_phone, kyc_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssssssssssissssss", $reg_no, $full_name, $national_id, $phone, $email, $address, $gender, $hashed, $status, $fee_status, $paid, $dob, $occupation, $nok_name, $nok_phone, $kyc_status);

            if (!$ins->execute()) throw new Exception("Failed to insert member: " . $ins->error);
            $member_id = $conn->insert_id;
            $ins->close();

            $upload_dir = __DIR__ . '/../../uploads/kyc/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $files_to_process = [
                'passport_photo'    => 'passport_photo',
                'national_id_front' => 'national_id_front',
                'national_id_back'  => 'national_id_back'
            ];

            $uploaded_count = 0;
            foreach ($files_to_process as $input_name => $doc_type) {
                if (!empty($_FILES[$input_name]['name']) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                    $ext      = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                    $filename = "{$doc_type}_{$member_id}_" . time() . ".$ext";
                    $target   = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target)) {
                        $doc_stmt = $conn->prepare("INSERT INTO member_documents (member_id, document_type, file_path, status, verified_at) VALUES (?, ?, ?, 'verified', NOW())");
                        $doc_stmt->bind_param("iss", $member_id, $doc_type, $filename);
                        $doc_stmt->execute();
                        $uploaded_count++;
                    }
                }
            }

            if ($uploaded_count > 0) {
                $conn->query("UPDATE members SET kyc_status = 'approved' WHERE member_id = $member_id");
            }

            if ($paid) {
                $ref = ($pay_method === 'cash') ? 'CSH-' . strtoupper(bin2hex(random_bytes(3))) : 'MPS-OFFICE';
                TransactionHelper::record([
                    'member_id'     => $member_id,
                    'amount'        => 1000.00,
                    'type'          => 'income',
                    'category'      => 'registration',
                    'method'        => $pay_method,
                    'ref_no'        => $ref,
                    'notes'         => "Registration Fee for Member $reg_no",
                    'related_id'    => $member_id,
                    'related_table' => 'members'
                ]);
            }

            $conn->commit();
            $success = "Member successfully registered with ID: <strong>$reg_no</strong>";
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = "Register Member";
?>
<?php $layout->header($pageTitle); ?>
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root {
            --ease-expo:   cubic-bezier(0.16, 1, 0.3, 1);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        body, .main-content-wrapper, input, select, textarea, button, label {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        /* ── Page Header ── */
        .ob-page-header {
            margin-bottom: 28px;
            animation: fadeUp 0.55s var(--ease-expo) both;
        }

        .ob-page-header .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--forest, #0f2e25);
            opacity: 0.6;
            margin-bottom: 8px;
        }

        .ob-page-header h2 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.4px;
            margin-bottom: 6px;
        }

        .ob-page-header p {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 0;
        }

        .ob-page-header .fee-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(15,46,37,0.07);
            color: var(--forest, #0f2e25);
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            margin-top: 10px;
        }

        /* ── Alert Cards ── */
        .ob-alert {
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: fadeUp 0.4s var(--ease-expo) both;
            border: none;
        }

        .ob-alert.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .ob-alert.danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .ob-alert i { font-size: 1rem; margin-top: 1px; flex-shrink: 0; }
        .ob-alert ul { margin: 0; padding-left: 16px; }

        /* ── Form Card ── */
        .ob-form-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
            overflow: hidden;
            animation: fadeUp 0.6s var(--ease-expo) 0.1s both;
        }

        /* ── Section Header ── */
        .form-section {
            padding: 28px 32px;
            border-bottom: 1px solid #f3f4f6;
        }

        .form-section:last-child { border-bottom: none; }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
        }

        .section-title .section-icon {
            width: 34px; height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
            background: rgba(15,46,37,0.07);
            color: var(--forest, #0f2e25);
        }

        .section-title h5 {
            font-size: 0.92rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        .section-title p {
            font-size: 0.78rem;
            color: #9ca3af;
            margin: 2px 0 0;
        }

        /* ── Form Labels & Inputs ── */
        .form-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #111827;
            box-shadow: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: rgba(15,46,37,0.4);
            box-shadow: 0 0 0 3px rgba(15,46,37,0.07);
            outline: none;
        }

        .form-control::placeholder { color: #c4c9d4; font-weight: 400; }

        /* Input with leading icon */
        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
            pointer-events: none;
        }

        .input-icon-wrap .form-control,
        .input-icon-wrap .form-select {
            padding-left: 36px;
        }

        /* ── Toggle Switch ── */
        .fee-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }

        .fee-toggle-row .toggle-info h6 {
            font-size: 0.875rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 2px;
        }

        .fee-toggle-row .toggle-info p {
            font-size: 0.78rem;
            color: #9ca3af;
            margin: 0;
        }

        .form-check-input[type="checkbox"] {
            width: 2.2rem;
            height: 1.2rem;
            border-radius: 50px;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--forest, #0f2e25);
            border-color: var(--forest, #0f2e25);
        }

        /* ── Payment Method Selector ── */
        .payment-options {
            display: flex;
            gap: 12px;
        }

        .payment-option {
            flex: 1;
            position: relative;
        }

        .payment-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0; height: 0;
        }

        .payment-option label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            transition: all 0.18s ease;
            text-transform: none;
            letter-spacing: 0;
        }

        .payment-option input:checked + label {
            border-color: var(--forest, #0f2e25);
            background: rgba(15,46,37,0.04);
            color: var(--forest, #0f2e25);
        }

        .payment-option label i { font-size: 1.1rem; }

        /* ── KYC Upload Zones ── */
        .upload-zone {
            border: 1.5px dashed #d1d5db;
            border-radius: 12px;
            padding: 20px 16px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.18s ease, background 0.18s ease;
            position: relative;
            overflow: hidden;
        }

        .upload-zone:hover {
            border-color: var(--forest, #0f2e25);
            background: rgba(15,46,37,0.025);
        }

        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-zone .upload-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.1rem;
            color: #9ca3af;
        }

        .upload-zone .upload-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 3px;
        }

        .upload-zone .upload-hint {
            font-size: 0.72rem;
            color: #9ca3af;
        }

        /* ── Submit Button ── */
        .submit-section {
            padding: 24px 32px;
            background: #fafafa;
            border-top: 1px solid #f3f4f6;
        }

        .btn-submit {
            width: 100%;
            padding: 14px 24px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--forest, #0f2e25), #1a5c42);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.25s var(--ease-expo);
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            letter-spacing: 0.2px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(15,46,37,0.25);
        }

        .btn-submit:active { transform: translateY(0); }

        /* ── Step Indicators ── */
        .form-steps {
            display: flex;
            gap: 6px;
            margin-bottom: 28px;
            animation: fadeUp 0.5s var(--ease-expo) 0.05s both;
        }

        .form-step {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            background: #f3f4f6;
            font-size: 0.78rem;
            font-weight: 700;
            color: #9ca3af;
        }

        .form-step.active {
            background: rgba(15,46,37,0.07);
            color: var(--forest, #0f2e25);
        }

        .form-step .step-num {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .form-step.active .step-num {
            background: var(--forest, #0f2e25);
            color: #a3e635;
        }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .form-section { padding: 20px 20px; }
            .payment-options { flex-direction: column; }
            .form-steps { display: none; }
            .submit-section { padding: 20px; }
        }
    </style>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">

            <!-- ─── PAGE HEADER ───────────────────────── -->
            <div class="ob-page-header">
                <div class="eyebrow"><i class="bi bi-person-plus-fill"></i> Member Onboarding</div>
                <h2>Register New Member</h2>
                <p>Fill in the member's details and collect the mandatory registration fee to activate their account.</p>
                <div class="fee-chip"><i class="bi bi-shield-check-fill"></i> KES 1,000 Registration Fee Required</div>
            </div>

            <!-- ─── ALERTS ────────────────────────────── -->
            <?php if ($success): ?>
            <div class="ob-alert success">
                <i class="bi bi-check-circle-fill"></i>
                <div><?= $success ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="ob-alert danger">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul class="mt-1">
                        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- ─── STEP INDICATORS ───────────────────── -->
            <div class="form-steps">
                <div class="form-step active">
                    <span class="step-num">1</span> Personal Info
                </div>
                <div class="form-step active">
                    <span class="step-num">2</span> Contact & NOK
                </div>
                <div class="form-step active">
                    <span class="step-num">3</span> Registration Fee
                </div>
                <div class="form-step active">
                    <span class="step-num">4</span> KYC Documents
                </div>
            </div>

            <!-- ─── FORM CARD ─────────────────────────── -->
            <div class="ob-form-card">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <!-- Section 1: Personal Info -->
                    <div class="form-section">
                        <div class="section-title">
                            <div class="section-icon"><i class="bi bi-person-fill"></i></div>
                            <div>
                                <h5>Personal Information</h5>
                                <p>Basic identity and demographic details</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-person"></i>
                                    <input type="text" class="form-control" name="full_name" required placeholder="e.g. Jane Wanjiru Kamau" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">National ID <span class="text-danger">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-credit-card"></i>
                                    <input type="text" class="form-control" name="national_id" required placeholder="e.g. 12345678" value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-gender-ambiguous"></i>
                                    <select class="form-select" name="gender">
                                        <option value="male"   <?= (($_POST['gender'] ?? '') == 'male')   ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= (($_POST['gender'] ?? '') == 'female') ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Occupation</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-briefcase"></i>
                                    <input type="text" class="form-control" name="occupation" placeholder="e.g. Teacher" value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Physical Address</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-geo-alt"></i>
                                    <input type="text" class="form-control" name="address" placeholder="e.g. Nairobi, Westlands" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Contact & NOK -->
                    <div class="form-section">
                        <div class="section-title">
                            <div class="section-icon"><i class="bi bi-telephone-fill"></i></div>
                            <div>
                                <h5>Contact &amp; Next of Kin</h5>
                                <p>Communication details and emergency contact</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-phone"></i>
                                    <input type="tel" class="form-control" name="phone" required placeholder="e.g. 0712345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-envelope"></i>
                                    <input type="email" class="form-control" name="email" required placeholder="e.g. jane@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Next of Kin Name</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-people"></i>
                                    <input type="text" class="form-control" name="nok_name" placeholder="Full name" value="<?= htmlspecialchars($_POST['nok_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Next of Kin Phone</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-phone-vibrate"></i>
                                    <input type="text" class="form-control" name="nok_phone" placeholder="e.g. 0798765432" value="<?= htmlspecialchars($_POST['nok_phone'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Temporary Password <span class="text-muted fw-normal" style="text-transform:none;letter-spacing:0;">(leave blank for default: password123)</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-lock"></i>
                                    <input type="password" class="form-control" name="password" placeholder="Leave blank for default">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Registration Fee -->
                    <div class="form-section">
                        <div class="section-title">
                            <div class="section-icon"><i class="bi bi-cash-stack"></i></div>
                            <div>
                                <h5>Registration Fee</h5>
                                <p>Mandatory KES 1,000 enrollment payment</p>
                            </div>
                        </div>

                        <div class="fee-toggle-row">
                            <div class="toggle-info">
                                <h6>Enrollment Fee Paid</h6>
                                <p>Toggle off if member will pay later</p>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid"
                                       onchange="togglePayment(this.checked)" checked>
                            </div>
                        </div>

                        <div id="paymentDetails">
                            <label class="form-label">Payment Method</label>
                            <div class="payment-options">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" id="pm_cash" value="cash" checked>
                                    <label for="pm_cash">
                                        <i class="bi bi-cash-coin"></i> Cash Payment
                                    </label>
                                </div>
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" id="pm_mpesa" value="mpesa">
                                    <label for="pm_mpesa">
                                        <i class="bi bi-phone-fill"></i> M-Pesa (At Office)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: KYC Documents -->
                    <div class="form-section">
                        <div class="section-title">
                            <div class="section-icon"><i class="bi bi-file-earmark-check-fill"></i></div>
                            <div>
                                <h5>KYC Documents</h5>
                                <p>Upload identity verification documents (optional at this stage)</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Passport Photo</label>
                                <div class="upload-zone" id="zone-passport">
                                    <input type="file" name="passport_photo" accept="image/*" onchange="previewZone(this,'zone-passport')">
                                    <div class="upload-icon"><i class="bi bi-camera"></i></div>
                                    <div class="upload-label">Click to upload</div>
                                    <div class="upload-hint">JPG, PNG up to 5MB</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">National ID Front</label>
                                <div class="upload-zone" id="zone-id-front">
                                    <input type="file" name="national_id_front" accept="image/*,application/pdf" onchange="previewZone(this,'zone-id-front')">
                                    <div class="upload-icon"><i class="bi bi-credit-card-2-front"></i></div>
                                    <div class="upload-label">Click to upload</div>
                                    <div class="upload-hint">Image or PDF up to 5MB</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">National ID Back</label>
                                <div class="upload-zone" id="zone-id-back">
                                    <input type="file" name="national_id_back" accept="image/*,application/pdf" onchange="previewZone(this,'zone-id-back')">
                                    <div class="upload-icon"><i class="bi bi-credit-card-2-back"></i></div>
                                    <div class="upload-label">Click to upload</div>
                                    <div class="upload-hint">Image or PDF up to 5MB</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="submit-section">
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-person-check-fill"></i>
                            Register &amp; Finalize Member
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
    function togglePayment(checked) {
        const el = document.getElementById('paymentDetails');
        el.style.display = checked ? 'block' : 'none';
    }

    function previewZone(input, zoneId) {
        const zone  = document.getElementById(zoneId);
        const icon  = zone.querySelector('.upload-icon');
        const label = zone.querySelector('.upload-label');
        const hint  = zone.querySelector('.upload-hint');

        if (input.files && input.files[0]) {
            const name = input.files[0].name;
            const size = (input.files[0].size / 1024).toFixed(0) + ' KB';
            icon.innerHTML  = '<i class="bi bi-check-circle-fill" style="color:#16a34a;"></i>';
            label.textContent = name.length > 22 ? name.substring(0, 22) + '…' : name;
            hint.textContent  = size + ' · Ready to upload';
            zone.style.borderColor = '#16a34a';
            zone.style.background  = 'rgba(22,163,74,0.04)';
        }
    }
</script>
</body>
</html>