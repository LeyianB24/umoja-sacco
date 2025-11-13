<?php
// usms/member/transactions.php

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$member_id = $_SESSION['member_id'] ?? 0;

// --- Totals ---
// --- SAFER TOTALS CALCULATION ---
$total_contributions = 0;
$total_loans = 0;
$total_repayments = 0;

// Helper function (using regular function for compatibility)
function get_total($conn, $member_id, $type) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM transactions WHERE member_id = ? AND transaction_type = ?");
    $stmt->bind_param('is', $member_id, $type);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    return (float)$total;
}

// Adjust types to match your system’s logic
$total_contributions = get_total($conn, $member_id, 'deposit');           // Savings / deposits
$total_loans = get_total($conn, $member_id, 'loan_disbursement');         // Disbursed loans
$total_repayments = get_total($conn, $member_id, 'loan_repayment');       // Repayments

$net_savings = $total_contributions + $total_repayments - $total_loans;

// --- Filtering (optional) ---
$type_filter = $_GET['type'] ?? '';
$date_filter = $_GET['date'] ?? '';

$where = "WHERE member_id='$member_id'";
if ($type_filter) $where .= " AND transaction_type='$type_filter'";
if ($date_filter) $where .= " AND DATE(transaction_date)='$date_filter'";

// --- Query transactions ---
$sql = "SELECT transaction_id, transaction_type, amount, reference_no, transaction_date, payment_channel, notes 
        FROM transactions 
        $where
        ORDER BY transaction_date DESC";
$result = $conn->query($sql);
?>
<div class="d-flex justify-content-end mt-3 me-4">
    <a href="dashboard.php" class="btn btn-secondary rounded-pill">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
</div>
<div class="container-fluid px-4 mt-4">
    <h4 class="text-center mb-4 fw-bold text-success">
        <i class="fas fa-wallet me-2"></i>Transaction Summary
    </h4>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-gradient-primary text-white rounded-4 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Contributions</h6>
                        <h4>Ksh <?= number_format($total_contributions, 2) ?></h4>
                    </div>
                    <i class="fas fa-hand-holding-usd fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-gradient-danger text-white rounded-4 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Loans</h6>
                        <h4>Ksh <?= number_format($total_loans, 2) ?></h4>
                    </div>
                    <i class="fas fa-money-check-alt fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-gradient-success text-white rounded-4 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Repayments</h6>
                        <h4>Ksh <?= number_format($total_repayments, 2) ?></h4>
                    </div>
                    <i class="fas fa-undo-alt fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-gradient-dark text-white rounded-4 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Net Savings</h6>
                        <h4>Ksh <?= number_format($net_savings, 2) ?></h4>
                    </div>
                    <i class="fas fa-piggy-bank fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & PDF Controls -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-filter me-2"></i>Filter Transactions
            </div>
            <form method="POST" action="../member/transactions_pdf.php" class="m-0">
                <button type="submit" class="btn btn-light btn-sm rounded-pill">
                    <i class="fas fa-download me-1"></i>Download PDF
                </button>
            </form>
        </div>

        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Transaction Type</label>
                    <select name="type" class="form-select">
                        <option value="">All</option>
                        <option value="contribution" <?= $type_filter === 'contribution' ? 'selected' : '' ?>>Contributions</option>
                        <option value="loan_disbursement" <?= $type_filter === 'loan_disbursement' ? 'selected' : '' ?>>Loans</option>
                        <option value="repayment" <?= $type_filter === 'repayment' ? 'selected' : '' ?>>Repayments</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-success w-100"><i class="fas fa-search me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-success text-white fw-semibold">
            <i class="fas fa-history me-2"></i>Transaction Records
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-success text-dark">
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Amount (Ksh)</th>
                        <th>Reference No</th>
                        <th>Payment Channel</th>
                        <th>Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= ucfirst(htmlspecialchars($row['transaction_type'] ?? '')) ?></td>
                                <td><?= number_format($row['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($row['reference_no'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['payment_channel'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(date("d M Y, h:i A", strtotime($row['transaction_date'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars($row['notes'] ?? '-') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-muted">No transactions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>