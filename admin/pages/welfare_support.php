<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');
// manager/welfare_support.php
// Grant Welfare Support (Payouts) to Members


require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_id = $_SESSION['admin_id'];

// 2. Handle Payout Grant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $member_id = intval($_POST['member_id']);
    $amount = floatval($_POST['amount']);
    $reason = trim($_POST['reason']);
    $case_id = !empty($_POST['case_id']) ? intval($_POST['case_id']) : null;
    
    if ($member_id > 0 && $amount > 0 && !empty($reason)) {
        $conn->begin_transaction();
        try {
            // A. Insert into Welfare Support Table
            // Schema assumed from member/welfare.php: member_id, amount, reason, date_granted, granted_by, status
            $stmt = $conn->prepare("INSERT INTO welfare_support (member_id, amount, reason, case_id, granted_by, status, date_granted) VALUES (?, ?, ?, ?, ?, 'approved', NOW())");
            $stmt->bind_param("idsis", $member_id, $amount, $reason, $case_id, $admin_id);
            $stmt->execute();
            $support_id = $conn->insert_id;
            $stmt->close();
            
            // C. Record via Financial Engine (Single Source of Truth)
            require_once __DIR__ . '/../../inc/FinancialEngine.php';
            $engine = new FinancialEngine($conn);
            $ref = "WS-" . str_pad($support_id, 6, '0', STR_PAD_LEFT);
            
            $engine->transact([
                'member_id'     => $member_id,
                'amount'        => $amount,
                'action_type'   => 'welfare_payout',
                'reference'     => $ref,
                'notes'         => "Welfare Grant: " . $reason,
                'related_id'    => $support_id,
                'related_table' => 'welfare_support'
            ]);
            
            // D. Notify Member
            // (Assuming notification logic)
            $msg = "You have been granted Welfare Support of KES " . number_format((float)$amount) . ". Reason: $reason. Funds added to your wallet.";
            $conn->query("INSERT INTO notifications (user_type, user_id, message, is_read, created_at) VALUES ('member', $member_id, '$msg', 0, NOW())");
            
            $conn->commit();
            flash_set("Welfare support granted successfully. Funds credited to member.", "success");
            header("Location: welfare_support.php");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            flash_set("Error: " . $e->getMessage(), "error");
        }
    } else {
        flash_set("Please fill all required fields.", "error");
    }
}

// 3. Fetch History
$history = $conn->query("SELECT w.*, m.full_name, m.national_id 
                         FROM welfare_support w 
                         JOIN members m ON w.member_id = m.member_id 
                         ORDER BY w.date_granted DESC LIMIT 20");

// 4. Fetch Active Members for Dropdown
$members = $conn->query("SELECT member_id, full_name, national_id FROM members WHERE status='active' ORDER BY full_name ASC");

// 5. Fetch Active Cases (Optional Linking)
$cases = $conn->query("SELECT case_id, title FROM welfare_cases WHERE status='active' ORDER BY created_at DESC");

$pageTitle = "Grant Welfare Support";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
</head>
<body class="manager-body">

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0 text-dark">Grant Welfare Support</h2>
            </div>

            <?php flash_render(); ?>

            <div class="row g-4">
                <!-- Form -->
                <div class="col-lg-4">
                    <div class="glass-card p-4">
                        <h5 class="fw-bold mb-3 text-secondary text-uppercase small">New Grant</h5>
                        <form method="POST">
                            <?= csrf_field() ?>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Beneficiary Member</label>
                                <select name="member_id" class="form-select" required>
                                    <option value="">-- Select Member --</option>
                                    <?php while($m = $members->fetch_assoc()): ?>
                                        <option value="<?= $m['member_id'] ?>">
                                            <?= htmlspecialchars($m['full_name']) ?> (<?= $m['national_id'] ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Related Case (Optional)</label>
                                <select name="case_id" class="form-select">
                                    <option value="">-- General / No Case --</option>
                                    <?php while($c = $cases->fetch_assoc()): ?>
                                        <option value="<?= $c['case_id'] ?>">
                                            <?= htmlspecialchars($c['title']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Amount (KES)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-success fw-bold">KES</span>
                                    <input type="number" name="amount" class="form-control fw-bold" step="0.01" min="1" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold">Reason / Details</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Hospital Bill Support..." required></textarea>
                            </div>

                            <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-2">
                                <i class="bi bi-check-circle me-2"></i> Approve Grant
                            </button>
                        </form>
                    </div>
                </div>

                <!-- History -->
                <div class="col-lg-8">
                    <div class="glass-card p-0 overflow-hidden">
                        <div class="p-3 border-bottom bg-light">
                            <h6 class="fw-bold mb-0">Recent Grants</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="small text-muted text-uppercase">
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Beneficiary</th>
                                        <th>Reason</th>
                                        <th class="text-end pe-4">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($history->num_rows === 0): ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">No grants recorded yet.</td></tr>
                                    <?php else: while ($row = $history->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4 text-nowrap"><?= date('d M Y', strtotime($row['date_granted'])) ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= $row['full_name'] ?></div>
                                                <small class="text-muted"><?= $row['national_id'] ?></small>
                                            </td>
                                            <td>
                                                <div class="small text-truncate" style="max-width: 250px;"><?= $row['reason'] ?></div>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-danger">- <?= number_format((float)$row['amount']) ?></td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





