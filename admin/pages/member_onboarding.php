<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

// usms/clerk/register_member.php
if (session_status() === PHP_SESSION_NONE) session_start();
// Enforce Admin Access
require_admin();

// Initialize Layout Manager
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
    $password    = $_POST['password'];
    $pay_method  = $_POST['payment_method']; // cash or mpesa
    $paid        = isset($_POST['is_paid']) ? 1 : 0;

    // Validation
    if (empty($full_name) || empty($national_id) || empty($phone) || empty($email)) {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // 1. Generate Reg No
            $reg_no = generate_member_no($conn);
            $hashed = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
            
            $fee_status = ($paid) ? 'paid' : 'unpaid';
            $status = ($paid) ? 'active' : 'inactive';
            
            // 2. Insert Member
            $ins = $conn->prepare("INSERT INTO members (member_reg_no, full_name, national_id, phone, email, password, join_date, status, registration_fee_status, reg_fee_paid) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
            $ins->bind_param("sssssssssi", $reg_no, $full_name, $national_id, $phone, $email, $hashed, $status, $fee_status, $paid);
            if (!$ins->execute()) throw new Exception("Member registration failed: " . $conn->error);
            $member_id = $ins->insert_id;

            // 3. Record Payment if paid
            if ($paid) {
                require_once __DIR__ . '/../../inc/TransactionHelper.php';
                $ref = ($pay_method === 'cash') ? 'CSH-' . strtoupper(bin2hex(random_bytes(3))) : 'MPS-OFFICE';
                
                // Unified Financial Engine Record
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
    <div class="flex-fill" style="margin-left: 280px; padding: 20px;">
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="mb-4">
                        <h2 class="fw-bold text-dark">Member Onboarding</h2>
                        <p class="text-muted">Register a new member and collect the mandatory <strong>KES 1,000</strong> registration fee.</p>
                    </div>

                    <?php if($success): ?>
                        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                            <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
                        </div>
                    <?php endif; ?>

                    <?php if($errors): ?>
                        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
                            <ul class="mb-0">
                                <?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="glass-card p-4 p-md-5">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control form-control-lg" required placeholder="John Doe">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">National ID / Passport</label>
                                    <input type="text" name="national_id" class="form-control" required placeholder="12345678">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" required placeholder="0712345678">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" required placeholder="john@example.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Temporary Password (Defaults to 'password123')</label>
                                    <input type="password" name="password" class="form-control" placeholder="Optional">
                                </div>

                                <div class="col-12">
                                    <hr class="my-3">
                                    <div class="p-3 rounded-4 border bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="fw-bold mb-0">Registration Fee (KES 1,000)</h6>
                                                <p class="small text-muted mb-0">Mark if member paid the enrollment fee.</p>
                                            </div>
                                            <div class="form-check form-switch fs-4">
                                                <input class="form-check-input" type="checkbox" name="is_paid" checked id="paidSwitch" onchange="togglePayment(this.checked)">
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3" id="paymentDetails">
                                            <label class="form-label">Payment Method</label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" value="cash" checked id="payCash">
                                                    <label class="form-check-label" for="payCash">Cash Payment</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" value="mpesa" id="payMpesa">
                                                    <label class="form-check-label" for="payMpesa">M-Pesa (At Office)</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-green w-100 py-3 fs-5 shadow-sm">
                                        Confirm & Finalize Registration
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePayment(checked) {
        document.getElementById('paymentDetails').style.display = checked ? 'block' : 'none';
    }
</script>
</body>
</html>





