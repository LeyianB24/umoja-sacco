<?php
// member/loans.php
session_start();
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../inc/header.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// Fetch Loans
$sql = "SELECT * FROM loans WHERE member_id = ? ORDER BY loan_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$loans = $stmt->get_result();

// Fetch Loan Repayments
$sqlRepay = "SELECT * FROM loan_repayments WHERE loan_id = ? ORDER BY repayment_id DESC";
$stmt2 = $conn->prepare($sqlRepay);
$stmt2->bind_param("i", $loan_id);
$stmt2->execute();
$repayments = $stmt2->get_result();
?>

<div class="container mt-4 mb-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="<?= BASE_URL ?>/member/dashboard.php" class="btn btn-outline-success">
            <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
        </a>
        <div class="d-flex gap-2">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#applyLoanModal">
                <i class="bi bi-journal-plus"></i> Apply for Loan
            </button>
            <a href="<?= BASE_URL ?>/member/mpesa_request.php" class="btn btn-outline-primary">
                <i class="bi bi-phone"></i> Pay via M-Pesa
            </a>
        </div>
    </div>

    <h3 class="text-success fw-bold mb-3"><i class="bi bi-cash-stack"></i> My Loans</h3>
    <hr>

    <!-- Active Loans Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-credit-card-2-front"></i> Active Loans</h5>
        </div>
        <div class="card-body">
            <?php if ($loans->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Loan Type</th>
                                <th>Amount (KSh)</th>
                                <th>Status</th>
                                <th>Balance</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; while ($loan = $loans->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($loan['loan_type'] ?? 'N/A') ?></td>
                                <td class="fw-bold text-success"><?= number_format($loan['amount'], 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= strtolower($loan['status']) == 'approved' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($loan['status']) ?>
                                    </span>
                                </td>
                                <td>KSh <?= number_format($loan['balance'] ?? 0, 2) ?></td>
                                <td><?= date('d M Y', strtotime($loan['loan_date'] ?? 'now')) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No loan records found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loan Repayment History -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Loan Repayments</h5>
        </div>
        <div class="card-body">
            <?php if ($repayments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Loan ID</th>
                                <th>Amount (KSh)</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $j=1; while ($rep = $repayments->fetch_assoc()): ?>
                            <tr>
                                <td><?= $j++ ?></td>
                                <td><?= $rep['loan_id'] ?></td>
                                <td class="text-success fw-bold"><?= number_format($rep['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($rep['payment_method'] ?? '—') ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($rep['payment_date'] ?? 'now')) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">No repayment records found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Loan Application Modal -->
<div class="modal fade" id="applyLoanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="apply_loan.php" method="POST">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Apply for Loan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Loan Type</label>
          <select name="loan_type" class="form-select mb-3" required>
            <option value="">Select Type</option>
            <option value="emergency">Emergency</option>
            <option value="development">Development</option>
            <option value="education">Education</option>
          </select>

          <label class="form-label">Amount (KSh)</label>
          <input type="number" name="amount" min="500" class="form-control mb-3" required>

          <label class="form-label">Repayment Period (Months)</label>
          <input type="number" name="repayment_period" min="1" class="form-control mb-3" required>

          <label class="form-label">Purpose</label>
          <input type="text" name="purpose" class="form-control mb-3" placeholder="Brief description" required>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Submit Application</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>