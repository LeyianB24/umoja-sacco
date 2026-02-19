<?php
// usms/simulate.php
// DEVELOPMENT TOOL: Manually process pending M-Pesa requests
// DELETE THIS FILE BEFORE GOING LIVE!

session_start();
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';

// Handle Simulation
if (isset($_POST['action']) && isset($_POST['ref'])) {
    $ref = $_POST['ref'];
    $action = $_POST['action']; // 'complete' or 'fail'
    
    $conn->begin_transaction();
    try {
        if ($action === 'complete') {
            // 1. Update Request Log
            $stmt = $conn->prepare("UPDATE mpesa_requests SET status = 'completed', updated_at = NOW() WHERE reference_no = ?");
            $stmt->bind_param("s", $ref);
            $stmt->execute();

            // 2. Update Contributions (Savings, Shares, Welfare)
            $stmt = $conn->prepare("UPDATE contributions SET status = 'active' WHERE reference_no = ?");
            $stmt->bind_param("s", $ref);
            $stmt->execute();

            // 3. Update Loan Repayments
            $stmt = $conn->prepare("UPDATE loan_repayments SET status = 'Completed' WHERE reference_no = ?");
            $stmt->bind_param("s", $ref);
            $stmt->execute();

            // 4. Update Loans (If fully paid)
            // Check if this ref belonged to a loan repayment
            $chk = $conn->query("SELECT loan_id FROM loan_repayments WHERE reference_no = '$ref'");
            if ($chk->num_rows > 0) {
                $lid = $chk->fetch_assoc()['loan_id'];
                // Check balance logic here if needed, or assume manual check
                // For simplicity in simulation:
                // $conn->query("UPDATE loans SET status='completed' WHERE loan_id=$lid"); 
            }

            $msg = "Transaction $ref marked as SUCCESS.";
            $msg_type = "success";

        } else {
            // Mark as Failed
            $conn->query("UPDATE mpesa_requests SET status = 'failed' WHERE reference_no = '$ref'");
            $conn->query("UPDATE contributions SET status = 'failed' WHERE reference_no = '$ref'");
            $conn->query("UPDATE loan_repayments SET status = 'Failed' WHERE reference_no = '$ref'");
            
            // Optional: Delete from ledger if it failed? 
            // Usually we keep it but mark as failed, but your ledger has no status column.
            // In dev, let's delete the ledger entry to keep math correct:
            $conn->query("DELETE FROM transactions WHERE reference_no = '$ref'");

            $msg = "Transaction $ref marked as FAILED.";
            $msg_type = "danger";
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// Fetch Pending
$pending = $conn->query("SELECT * FROM mpesa_requests WHERE status = 'pending' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <title>M-Pesa Simulator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">M-Pesa Callback Simulator (Localhost)</h5>
            <a href="member/dashboard.php" class="btn btn-sm btn-outline-light">Go to Dashboard</a>
        </div>
        <div class="card-body">
            
            <?php if(isset($msg)): ?>
                <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
            <?php endif; ?>

            <?php if($pending->num_rows === 0): ?>
                <div class="text-center py-5 text-muted">
                    <h4>No Pending Transactions</h4>
                    <p>Go make a payment request first!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Reference</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $pending->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['created_at'] ?></td>
                                <td><?= $row['phone'] ?></td>
                                <td class="fw-bold">KES <?= number_format($row['amount']) ?></td>
                                <td class="font-monospace small"><?= $row['reference_no'] ?></td>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="ref" value="<?= $row['reference_no'] ?>">
                                        <button type="submit" name="action" value="complete" class="btn btn-sm btn-success">
                                            Simulate Success
                                        </button>
                                        <button type="submit" name="action" value="fail" class="btn btn-sm btn-danger">
                                            Simulate Failure
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>