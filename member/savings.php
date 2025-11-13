<?php
// member/savings.php
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

// === FILTER HANDLING ===
$typeFilter = $_GET['type'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Build dynamic WHERE clause
$where = "WHERE member_id = ?";
$params = [$member_id];
$types = "i";

if ($typeFilter && in_array($typeFilter, ['deposit', 'withdrawal'])) {
    $where .= " AND transaction_type = ?";
    $params[] = $typeFilter;
    $types .= "s";
}
if ($startDate && $endDate) {
    $where .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}

// === TOTAL SAVINGS ===
$sqlTotal = "
    SELECT 
        COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END), 0) AS total_deposits,
        COALESCE(SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END), 0) AS total_withdrawals
    FROM savings
    WHERE member_id = ?
";
$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->bind_param("i", $member_id);
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result()->fetch_assoc();

$totalDeposits = (float) ($resultTotal['total_deposits'] ?? 0);
$totalWithdrawals = (float) ($resultTotal['total_withdrawals'] ?? 0);
$netSavings = $totalDeposits - $totalWithdrawals;

// === SAVINGS HISTORY ===
$sqlHistory = "SELECT * FROM savings $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sqlHistory);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result();
?>

<div class="container mt-4 mb-5">

    <!-- Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="<?= BASE_URL ?>/member/dashboard.php" class="btn btn-outline-success">
            <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
        </a>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/member/mpesa_request.php" class="btn btn-success">
                <i class="bi bi-phone"></i> Deposit via M-Pesa
            </a>
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                <i class="bi bi-cash"></i> Withdraw
            </button>
            <button id="downloadPdf" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </button>
        </div>
    </div>

    <h3 class="text-success fw-bold mb-3">
        <i class="bi bi-piggy-bank-fill"></i> My Savings
    </h3>
    <hr>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm rounded-4 bg-light text-center">
                <div class="card-body">
                    <h6>Total Deposits</h6>
                    <h2 class="text-success fw-bold">KSh <?= number_format($totalDeposits, 2) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm rounded-4 bg-light text-center">
                <div class="card-body">
                    <h6>Total Withdrawals</h6>
                    <h2 class="text-danger fw-bold">KSh <?= number_format($totalWithdrawals, 2) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm rounded-4 bg-success text-white text-center">
                <div class="card-body">
                    <h6>Net Savings</h6>
                    <h2 class="fw-bold">KSh <?= number_format($netSavings, 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-body">
            <form method="GET" class="row gy-2 gx-3 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small">Transaction Type</label>
                    <select name="type" class="form-select">
                        <option value="">All</option>
                        <option value="deposit" <?= $typeFilter === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                        <option value="withdrawal" <?= $typeFilter === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-success"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="savings.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Savings History -->
    <div class="card shadow-sm border-0" id="savingsTable">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Savings History</h5>
        </div>
        <div class="card-body">
            <?php if ($history->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Transaction</th>
                                <th>Amount (KSh)</th>
                                <th>Description</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while ($row = $history->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <span class="badge bg-<?= strtolower($row['transaction_type']) === 'deposit' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($row['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td class="<?= strtolower($row['transaction_type']) === 'deposit' ? 'text-success' : 'text-danger' ?> fw-bold">
                                        <?= number_format($row['amount'], 2) ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['description'] ?? '—') ?></td>
                                    <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No savings found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="withdrawForm" method="POST" action="<?= BASE_URL ?>/member/withdrawal.php">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="withdrawModalLabel">
            <i class="bi bi-cash"></i> Withdraw Funds
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Amount (KSh)</label>
            <input type="number" name="amount" min="10" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" placeholder="Reason for withdrawal" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-danger w-100">Confirm Withdraw</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- JavaScript to handle form submission -->
<script>
document.getElementById("withdrawForm").addEventListener("submit", function (e) {
  // Optional: confirm before submitting
  const confirmed = confirm("Are you sure you want to withdraw this amount?");
  if (!confirmed) {
    e.preventDefault(); // stop submission if cancelled
  }
});
</script>

<!-- PDF Download Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
document.getElementById("downloadPdf").addEventListener("click", () => {
    const element = document.getElementById("savingsTable");
    const opt = {
        margin: 0.5,
        filename: 'Savings_History_<?= date('Ymd') ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>