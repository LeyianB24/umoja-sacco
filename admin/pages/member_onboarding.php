<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';

$layout = LayoutManager::create('admin');
require_permission();

$errors = [];
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
    $pay_method  = $_POST['payment_method'] ?? 'cash';
    $paid        = isset($_POST['is_paid']) ? 1 : 0;

    // Validation
    if (empty($full_name) || empty($national_id) || empty($phone) || empty($email)) {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // 1. Generate Reg No
            // 1. Generate Reg No
            // $reg_no = generate_member_no($conn); // Already doing this on line 39 in original code?
            // Wait, looking at file view, line 39 IS $reg_no = generate_member_no($conn);
            // And line 46 IS using $reg_no.
            // Result: The file ALREADY HAS Logic.
            // I will just verify it matches the user request.
            // User Request: "Update admin/pages/member_onboarding.php... The system automatically generates a unique regno".
            // It seems it already does.
            // Let me closely double check the file content.
            // Line 39: $reg_no = generate_member_no($conn);
            // Line 46: INSERT INTO members (member_reg_no...) VALUES (?, ...)
            // It seems I don't need to change it, or maybe I just need to visually confirm.
            // Actually, I will make sure it uses the SHARED helper function from functions.php, which it seems to be doing.
            // But wait, the previous `replace` failed because "Target Content not found".
            // Let me look at the file content again in step 368.
            // Line 39: $reg_no = generate_member_no($conn);
            // Line 46: $ins = $conn->prepare("INSERT INTO members (member_reg_no, ... VALUES (?, ...
            // The file IS ALREADY CORRECT.
            // I will skip editing this file and mark the task as done.
            $reg_no = generate_member_no($conn);
            $hashed = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
            
            $fee_status = ($paid) ? 'paid' : 'unpaid';
            $status = ($paid) ? 'active' : 'inactive';
            
            // 2. Insert Member
            $ins = $conn->prepare("INSERT INTO members (member_reg_no, full_name, national_id, phone, email, address, gender, password, join_date, status, registration_fee_status, reg_fee_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
            $ins->bind_param("ssssssssssi", $reg_no, $full_name, $national_id, $phone, $email, $address, $gender, $hashed, $status, $fee_status, $paid);
            
            if (!$ins->execute()) {
                throw new Exception("Failed to insert member: " . $ins->error);
            }
            
            $member_id = $conn->insert_id;
            $ins->close();
            // 2b. Handle File Uploads (KYC)
            $upload_dir = __DIR__ . '/../../uploads/kyc/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $files_to_process = [
                'passport_photo' => 'passport_photo', 
                'national_id_front' => 'national_id_front'
            ];

            foreach ($files_to_process as $input_name => $doc_type) {
                if (!empty($_FILES[$input_name]['name']) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                    $filename = "{$doc_type}_{$member_id}_" . time() . ".$ext";
                    $target = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target)) {
                        $sql = "INSERT INTO member_documents (member_id, document_type, file_path, status) VALUES (?, ?, ?, 'verified')";
                        $doc_stmt = $conn->prepare($sql);
                        $doc_stmt->bind_param("iss", $member_id, $doc_type, $filename);
                        $doc_stmt->execute();
                    }
                }
            }

            // 3. Record Payment if paid
            if ($paid) {
                $ref = ($pay_method === 'cash') ? 'CSH-' . strtoupper(bin2hex(random_bytes(3))) : 'MPS-OFFICE';
                
                TransactionHelper::record([
                    'member_id'      => $member_id,
                    'amount'         => 1000.00,
                    'type'           => 'income',
                    'category'       => 'registration',
                    'method'         => $pay_method,
                    'ref_no'         => $ref,
                    'notes'          => "Registration Fee for Member $reg_no",
                    'related_id'     => $member_id,
                    'related_table'  => 'members'
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | Clerk Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f7f4; }
        .glass-card { background: white; border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; }
        .btn-green { background: #0F392B; color: white; border-radius: 12px; font-weight: bold; }
        .btn-green:hover { background: #1a5a45; color: white; }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="mb-4">
                        <h2 class="fw-bold text-dark">Member Onboarding</h2>
                        <p class="text-muted">Register a new member and collect the mandatory <strong>KES 1,000</strong> registration fee.</p>
                    </div>

                    <?php if($success): ?>
                        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                            <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
                            <ul class="mb-0">
                                <?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="glass-card p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="national_id" class="form-label">National ID</label>
                                    <input type="text" class="form-control" id="national_id" name="national_id" required value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="male" <?= (($_POST['gender'] ?? '') == 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= (($_POST['gender'] ?? '') == 'female') ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label for="address" class="form-label">Physical Address</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                                </div>
                                <div class="col-md-12">
                                    <label for="password" class="form-label">Temporary Password (Default: 'password123')</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank for default">
                                </div>

                                <div class="col-12 mt-4">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">Registration Fee (KES 1,000)</h5>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid" onchange="togglePayment(this.checked)" checked>
                                        <label class="form-check-label" for="is_paid">Enrollment Fee Paid</label>
                                    </div>

                                    <div id="paymentDetails">
                                        <label class="form-label">Payment Method</label>
                                        <select class="form-select" name="payment_method">
                                            <option value="cash">Cash Payment</option>
                                            <option value="mpesa">M-Pesa (At Office)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 mt-4">
                                    <h5 class="fw-bold text-dark border-bottom pb-2">KYC Documents</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Passport Photo</label>
                                            <input type="file" name="passport_photo" class="form-control" accept="image/*">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">National ID Front</label>
                                            <input type="file" name="national_id_front" class="form-control" accept="image/*,application/pdf">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-green w-100 py-3 fs-5 shadow-sm">
                                        <i class="bi bi-person-plus me-2"></i> Register & Finalize Member
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php $layout->footer(); ?>
    </div>
</div>

<script>
    function togglePayment(checked) {
        document.getElementById('paymentDetails').style.display = checked ? 'block' : 'none';
    }
</script>
</body>
</html>
