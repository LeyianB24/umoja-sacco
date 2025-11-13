<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure member is logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';

// Base query
$sql = "
    SELECT reference_no, contribution_type, amount, payment_method, contribution_date
    FROM contributions
    WHERE member_id = ?
";

// Apply date filter if provided
if (!empty($filter_from) && !empty($filter_to)) {
    $sql .= " AND DATE(contribution_date) BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $member_id, $filter_from, $filter_to);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $member_id);
}

$stmt->execute();
$result = $stmt->get_result();

// Calculate total contributions
$total_sql = "SELECT SUM(amount) AS total FROM contributions WHERE member_id = ?";
$stmt_total = $conn->prepare($total_sql);
$stmt_total->bind_param("i", $member_id);
$stmt_total->execute();
$total_result = $stmt_total->get_result()->fetch_assoc();
$total_contributions = $total_result['total'] ?? 0;
?>

<div class="container-fluid px-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-success">
            <i class="fas fa-hand-holding-usd me-2"></i>My Contributions
        </h4>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/member/mpesa_request.php" class="btn btn-success">
                <i class="bi bi-phone"></i> Deposit via M-Pesa
            </a>
        </div>
        <a href="dashboard.php" class="btn btn-secondary rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <!-- Filter Form -->
    <form method="GET" class="row g-3 mb-3">
        <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filter_from); ?>" class="form-control rounded-pill">
        </div>
        <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filter_to); ?>" class="form-control rounded-pill">
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-success rounded-pill px-4">
                <i class="fas fa-filter me-1"></i> Filter
            </button>
        </div>
    </form>

    <!-- Total Contributions -->
    <div class="alert alert-info rounded-pill fw-semibold">
        <i class="fas fa-coins me-2"></i>
        Total Contributions: 
        <span class="text-success">KSh <?= number_format($total_contributions, 2); ?></span>
    </div>

    <!-- Contributions Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <table class="table table-hover align-middle text-center">
                <thead class="table-success">
                    <tr>
                        <th>Reference No</th>
                        <th>Type</th>
                        <th>Payment Method</th>
                        <th>Amount (KSh)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['reference_no']); ?></td>
                                <td class="text-capitalize"><?= htmlspecialchars($row['contribution_type']); ?></td>
                                <td class="text-uppercase"><?= htmlspecialchars($row['payment_method']); ?></td>
                                <td class="fw-semibold text-success"><?= number_format($row['amount'], 2); ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($row['contribution_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-muted">No contributions found for this period.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
