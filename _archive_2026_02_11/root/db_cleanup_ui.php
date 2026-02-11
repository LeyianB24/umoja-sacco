<?php
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/inc/Auth.php';
require_once __DIR__ . '/inc/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Require admin authentication
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin/pages/login.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    
    if ($_POST['action'] === 'backfill_targets') {
        $updated = 0;
        $target_defaults = [
            'vehicle_fleet' => ['amount' => 50000, 'period' => 'monthly'],
            'farm' => ['amount' => 100000, 'period' => 'monthly'],
            'apartments' => ['amount' => 150000, 'period' => 'monthly'],
            'petrol_station' => ['amount' => 200000, 'period' => 'monthly'],
        ];
        
        $investments = $conn->query("
            SELECT investment_id, category, created_at, purchase_date
            FROM investments 
            WHERE target_amount IS NULL OR target_amount = 0
        ");
        
        while ($inv = $investments->fetch_assoc()) {
            $defaults = $target_defaults[$inv['category']] ?? ['amount' => 50000, 'period' => 'monthly'];
            $start_date = $inv['purchase_date'] ?: $inv['created_at'];
            
            $stmt = $conn->prepare("
                UPDATE investments 
                SET target_amount = ?, target_period = ?, target_start_date = ?
                WHERE investment_id = ?
            ");
            $stmt->bind_param("dssi", $defaults['amount'], $defaults['period'], $start_date, $inv['investment_id']);
            if ($stmt->execute()) $updated++;
        }
        
        flash_set("Successfully backfilled targets for $updated investments", 'success');
        header('Location: db_cleanup_ui.php');
        exit;
    }
    
    if ($_POST['action'] === 'mark_general') {
        $trans_id = intval($_POST['transaction_id']);
        $stmt = $conn->prepare("UPDATE transactions SET related_table = 'general', related_id = 0 WHERE transaction_id = ?");
        $stmt->bind_param("i", $trans_id);
        if ($stmt->execute()) {
            flash_set("Transaction marked as general", 'success');
        }
        header('Location: db_cleanup_ui.php');
        exit;
    }
    
    if ($_POST['action'] === 'link_to_investment') {
        $trans_id = intval($_POST['transaction_id']);
        $inv_id = intval($_POST['investment_id']);
        $stmt = $conn->prepare("UPDATE transactions SET related_table = 'investments', related_id = ? WHERE transaction_id = ?");
        $stmt->bind_param("ii", $inv_id, $trans_id);
        if ($stmt->execute()) {
            flash_set("Transaction linked to investment", 'success');
        }
        header('Location: db_cleanup_ui.php');
        exit;
    }
}

// Get audit data
$orphan_rev = $conn->query("
    SELECT * FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_all(MYSQLI_ASSOC);

$orphan_exp = $conn->query("
    SELECT * FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_all(MYSQLI_ASSOC);

$no_target = $conn->query("SELECT COUNT(*) as c FROM investments WHERE target_amount IS NULL OR target_amount = 0")->fetch_assoc()['c'];

$investments = $conn->query("SELECT investment_id, title FROM investments WHERE status = 'active' ORDER BY title")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Cleanup Tool - USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; }
        .cleanup-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .cleanup-header { background: linear-gradient(135deg, #0f2e25 0%, #1a4d3d 100%); color: white; padding: 20px; border-radius: 12px 12px 0 0; }
        .trans-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .badge-critical { background: #dc3545; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-success { background: #28a745; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="bi bi-tools me-2"></i>Database Cleanup Tool</h1>
                <p class="text-muted">Fix orphaned transactions and backfill missing data</p>
                <a href="admin/pages/investments.php" class="btn btn-outline-secondary btn-sm">← Back to Investments</a>
            </div>
        </div>


        <?php flash_render(); ?>


        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger"><?= count($orphan_rev) ?></h3>
                        <p class="mb-0">Orphaned Revenue</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger"><?= count($orphan_exp) ?></h3>
                        <p class="mb-0">Orphaned Expenses</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?= $no_target ?></h3>
                        <p class="mb-0">Missing Targets</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fix 1: Backfill Targets -->
        <?php if ($no_target > 0): ?>
        <div class="cleanup-card">
            <div class="cleanup-header">
                <h4 class="mb-0"><i class="bi bi-bullseye me-2"></i>Fix 1: Backfill Missing Targets</h4>
            </div>
            <div class="p-4">
                <p><?= $no_target ?> investments are missing performance targets. This prevents viability calculations.</p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="backfill_targets">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Auto-Backfill Targets
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Fix 2: Orphaned Revenue -->
        <?php if (count($orphan_rev) > 0): ?>
        <div class="cleanup-card">
            <div class="cleanup-header">
                <h4 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Fix 2: Orphaned Revenue (<?= count($orphan_rev) ?>)</h4>
            </div>
            <div class="p-4">
                <?php foreach ($orphan_rev as $trans): ?>
                <div class="trans-card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <strong>KES <?= number_format($trans['amount']) ?></strong><br>
                            <small class="text-muted"><?= $trans['transaction_date'] ?> • <?= $trans['category'] ?></small><br>
                            <small><?= $trans['notes'] ?></small>
                        </div>
                        <div class="col-md-6 text-end">
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="link_to_investment">
                                <input type="hidden" name="transaction_id" value="<?= $trans['transaction_id'] ?>">
                                <select name="investment_id" class="form-select form-select-sm d-inline-block w-auto me-2" required>
                                    <option value="">Link to...</option>
                                    <?php foreach ($investments as $inv): ?>
                                        <option value="<?= $inv['investment_id'] ?>"><?= $inv['title'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Link</button>
                            </form>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_general">
                                <input type="hidden" name="transaction_id" value="<?= $trans['transaction_id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Mark General</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Fix 3: Orphaned Expenses -->
        <?php if (count($orphan_exp) > 0): ?>
        <div class="cleanup-card">
            <div class="cleanup-header">
                <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Fix 3: Orphaned Expenses (<?= count($orphan_exp) ?>)</h4>
            </div>
            <div class="p-4">
                <?php foreach ($orphan_exp as $trans): ?>
                <div class="trans-card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <strong>KES <?= number_format($trans['amount']) ?></strong><br>
                            <small class="text-muted"><?= $trans['transaction_date'] ?> • <?= $trans['category'] ?></small><br>
                            <small><?= $trans['notes'] ?></small>
                        </div>
                        <div class="col-md-6 text-end">
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="link_to_investment">
                                <input type="hidden" name="transaction_id" value="<?= $trans['transaction_id'] ?>">
                                <select name="investment_id" class="form-select form-select-sm d-inline-block w-auto me-2" required>
                                    <option value="">Link to...</option>
                                    <?php foreach ($investments as $inv): ?>
                                        <option value="<?= $inv['investment_id'] ?>"><?= $inv['title'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Link</button>
                            </form>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_general">
                                <input type="hidden" name="transaction_id" value="<?= $trans['transaction_id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Mark General</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (count($orphan_rev) == 0 && count($orphan_exp) == 0 && $no_target == 0): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>All Clear!</strong> No cleanup required. Your database architecture is sound.
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
