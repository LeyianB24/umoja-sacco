<?php
// accountant/revenue.php
// Track Investment Income (Matatus, Rentals, etc.)

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../inc/functions.php';

// 1. Auth
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['accountant', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

// 2. Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $investment_id = intval($_POST['investment_id']);
    $amount = floatval($_POST['amount']);
    $date = $_POST['income_date'];
    $desc = trim($_POST['description']);
    $ref = trim($_POST['ref_no']); // e.g. M-Pesa Ref
    
    if ($investment_id > 0 && $amount > 0) {
        $conn->begin_transaction();
        try {
            // A. Fetch Investment Details
            $stmt = $conn->prepare("SELECT title, category FROM investments WHERE investment_id = ?");
            $stmt->bind_param("i", $investment_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) throw new Exception("Invalid Investment Asset.");
            $asset = $res->fetch_assoc();
            $stmt->close();
            
            // B. Record Transaction (Income)
            // Note: related_id points to investment_id for income tracking
            // We use 'income' as type.
            $notes = "Revenue from {$asset['title']}: " . $desc;
            
            $stmt = $conn->prepare("INSERT INTO transactions (transaction_type, amount, related_id, reference_no, notes, created_at, payment_channel) VALUES ('income', ?, ?, ?, ?, ?, 'cash')");
            // Timestamp combines date + current time
            $timestamp = $date . ' ' . date('H:i:s');
            $stmt->bind_param("disss", $amount, $investment_id, $ref, $notes, $timestamp);
            $stmt->execute();
            
            $conn->commit();
            flash_set("Revenue recorded successfully!", "success");
            header("Location: revenue.php");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            flash_set("Error: " . $e->getMessage(), "error");
        }
    } else {
        flash_set("Please fill in valid amount and select an asset.", "error");
    }
}

// 3. Fetch Investments
$assets = $conn->query("SELECT investment_id, title, category FROM investments WHERE status = 'active' ORDER BY title ASC");

// 4. Fetch Recent Income Limit 10
$recent = $conn->query("SELECT t.*, i.title, i.category 
                        FROM transactions t 
                        LEFT JOIN investments i ON t.related_id = i.investment_id
                        WHERE t.transaction_type = 'income' 
                        ORDER BY t.created_at DESC LIMIT 10");

$pageTitle = "Revenue Tracking";
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
<body class="accountant-body">

<div class="d-flex">
    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="flex-fill main-content-wrapper">
        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Record Revenue</h2>
                
            </div>

            <?php flash_render(); ?>

            <div class="row g-4">
                <!-- Form Column -->
                <div class="col-lg-4">
                    <div class="hope-card p-4">
                        <h5 class="fw-bold mb-3 text-secondary text-uppercase small">New Entry</h5>
                        <form method="POST">
                            <?= csrf_field() ?>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Select Asset / Source</label>
                                <select name="investment_id" class="form-select" required>
                                    <option value="">-- Choose Asset --</option>
                                    <?php while($a = $assets->fetch_assoc()): ?>
                                        <option value="<?= $a['investment_id'] ?>">
                                            <?= htmlspecialchars($a['title']) ?> (<?= ucfirst($a['category']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Date Received</label>
                                <input type="date" name="income_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Amount Generated</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-success fw-bold">KES</span>
                                    <input type="number" name="amount" class="form-control fw-bold" step="0.01" min="1" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Reference No.</label>
                                <input type="text" name="ref_no" class="form-control" placeholder="e.g. Receipt / M-Despoit Ref">
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold">Notes</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="e.g. Daily earnings Route 42"></textarea>
                            </div>

                            <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-2">
                                <i class="bi bi-plus-circle me-2"></i> Record Income
                            </button>
                        </form>
                    </div>
                </div>

                <!-- List Column -->
                <div class="col-lg-8">
                    <div class="hope-card p-0 overflow-hidden">
                        <div class="p-3 border-bottom bg-light">
                            <h6 class="fw-bold mb-0">Recent Revenue Entries</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="small text-muted text-uppercase">
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Asset</th>
                                        <th>Details</th>
                                        <th class="text-end pe-4">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent->num_rows === 0): ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">No income records yet.</td></tr>
                                    <?php else: while ($row = $recent->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4 text-nowrap"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= $row['title'] ?? 'Unknown' ?></div>
                                                <small class="text-muted"><?= ucfirst($row['category'] ?? 'General') ?></small>
                                            </td>
                                            <td>
                                                <div class="small text-truncate" style="max-width: 250px;"><?= $row['notes'] ?></div>
                                                <small class="text-muted fst-italic"><?= $row['reference_no'] ?></small>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-success">+ <?= number_format($row['amount']) ?></td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
