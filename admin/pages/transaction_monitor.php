<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/TransactionMonitor.php';

require_admin();

$layout = LayoutManager::create('admin');
$monitor = new TransactionMonitor($conn);

// Handle manual activation if requested
$success = "";
$error = "";
if (isset($_POST['action']) && $_POST['action'] === 'activate' && isset($_POST['contribution_id'])) {
    $cid = (int)$_POST['contribution_id'];
    try {
        require_once __DIR__ . '/../../inc/FinancialEngine.php';
        $engine = new FinancialEngine($conn);
        
        // Get contribution details
        $res = $conn->query("SELECT * FROM contributions WHERE contribution_id = $cid");
        $contrib = $res->fetch_assoc();
        
        if ($contrib && $contrib['status'] === 'pending') {
            $conn->begin_transaction();
            
            // 1. Mark as active
            $conn->query("UPDATE contributions SET status = 'active' WHERE contribution_id = $cid");
            
            // 2. Process through engine
            $action_map = [
                'savings' => 'savings_deposit',
                'shares' => 'share_purchase',
                'welfare' => 'welfare_contribution',
                'registration' => 'revenue_inflow'
            ];
            $action = $action_map[$contrib['contribution_type']] ?? 'savings_deposit';
            
            $engine->transact([
                'member_id' => $contrib['member_id'],
                'amount' => $contrib['amount'],
                'action_type' => $action,
                'reference' => $contrib['reference_no'] ?? ("MANUAL-".$cid),
                'notes' => "Manually activated by admin",
                'method' => 'mpesa'
            ]);
            
            // 3. Acknowledge alerts
            $conn->query("UPDATE transaction_alerts SET acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = " . ($_SESSION['admin_id'] ?? 0) . " WHERE contribution_id = $cid");
            
            $conn->commit();
            $success = "Transaction activated successfully.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to activate: " . $e->getMessage();
    }
}

// Get stuck transactions
$stuck = $monitor->getStuckPending(5);
$alerts = $monitor->getActiveAlerts();

$layout->header("Transaction Monitor"); ?>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar("Transaction Monitor"); ?>

        <div class="container-fluid p-4">
    <div class="row">
        <div class="col-12">
            <div class="card card-flush">
                <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                    <div class="card-title">
                        <div class="d-flex align-items-center position-relative my-1">
                            <h3>Transaction Monitoring & Recovery</h3>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#stuck_tab">Stuck Pending (<?php echo count($stuck); ?>)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#alerts_tab">Active Alerts (<?php echo count($alerts); ?>)</a>
                        </li>
                    </ul>

                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="stuck_tab" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table align-middle table-row-dashed fs-6 gy-5">
                                    <thead>
                                        <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                            <th>Date</th>
                                            <th>Member</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Ref</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600">
                                        <?php if (empty($stuck)): ?>
                                            <tr><td colspan="6" class="text-center">No stuck transactions found.</td></tr>
                                        <?php else: foreach ($stuck as $s): ?>
                                            <tr>
                                                <td><?php echo date('d M, H:i', strtotime($s['created_at'])); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="d-flex flex-column">
                                                            <a href="#" class="text-gray-800 text-hover-primary mb-1"><?php echo $s['full_name']; ?></a>
                                                            <span><?php echo $s['phone']; ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo ucfirst($s['contribution_type']); ?></td>
                                                <td>KES <?php echo number_format($s['amount'], 2); ?></td>
                                                <td><code><?php echo $s['reference_no']; ?></code></td>
                                                <td>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="contribution_id" value="<?php echo $s['contribution_id']; ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Manually activate this transaction?')">Activate</button>
                                                    </form>
                                                    <button class="btn btn-sm btn-light" onclick="viewCallbackLog('<?php echo $s['checkout_request_id']; ?>')">Logs</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="alerts_tab" role="tabpanel">
                             <div class="table-responsive">
                                <table class="table align-middle table-row-dashed fs-6 gy-5">
                                    <thead>
                                        <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                            <th>Severity</th>
                                            <th>Message</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fw-semibold text-gray-600">
                                        <?php if (empty($alerts)): ?>
                                            <tr><td colspan="4" class="text-center">No active alerts found.</td></tr>
                                        <?php else: foreach ($alerts as $a): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                        $badge = 'bg-warning';
                                                        if ($a['severity'] === 'critical') $badge = 'bg-danger';
                                                        if ($a['severity'] === 'info') $badge = 'bg-info';
                                                    ?>
                                                    <span class="badge <?php echo $badge; ?>"><?php echo strtoupper($a['severity']); ?></span>
                                                </td>
                                                <td><?php echo $a['message']; ?></td>
                                                <td><?php echo date('d M, H:i', strtotime($a['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-light">Acknowledge</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewCallbackLog(checkoutId) {
    if (!checkoutId) {
        alert("No checkout ID associated with this transaction.");
        return;
    }
    // Simple popup or modal could be here
    alert("Lookup for: " + checkoutId);
}
</script>

    <?php $layout->footer(); ?>
    </div>
</div>
