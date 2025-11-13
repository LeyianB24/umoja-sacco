<?php
// usms/member/dashboard.php
session_start();

// config + DB
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';
// Redirect to login if not logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

$member_id = (int) $_SESSION['member_id'];
$member_name = htmlspecialchars($_SESSION['member_name'] ?? 'Member', ENT_QUOTES);

// Fetch gender + profile_pic
$stmt = $conn->prepare("SELECT full_name, profile_pic, gender FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$memberData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$member_profile_pic = !empty($memberData['profile_pic'])
    ? BASE_URL . '/' . $memberData['profile_pic']
    : BASE_URL . '/public/assets/uploads/' . ($memberData['gender'] === 'female' ? 'female.jpg' : 'male.jpg');

// --- Compute total savings as deposits minus withdrawals ---
$total_savings = 0.00;
$sq = "
    SELECT 
        COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END),0) -
        COALESCE(SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END),0) 
    AS total
    FROM savings
    WHERE member_id = ?
";
if ($stmt = $conn->prepare($sq)) {
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $total_savings = (float)$r['total'];
    $stmt->close();
}
// 2) Total contributions
$total_contrib = 0.00;
$sq = "SELECT COALESCE(SUM(amount),0) AS total FROM contributions WHERE member_id = ?";
if ($stmt = $conn->prepare($sq)) {
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $total_contrib = (float)$r['total'];
    $stmt->close();
}

// 3) Loans: pending / approved counts and outstanding amount (approx)
$loans_pending = 0;
$loans_approved = 0;
$outstanding_loans_amount = 0.00;
$sq = "SELECT status, COALESCE(SUM(amount),0) AS sumamt, COUNT(*) AS cnt FROM loans WHERE member_id = ? GROUP BY status";
if ($stmt = $conn->prepare($sq)) {
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if ($row['status'] === 'pending') $loans_pending = (int)$row['cnt'];
        if ($row['status'] === 'approved' || $row['status'] === 'disbursed') {
            $loans_approved += (int)$row['cnt'];
            $outstanding_loans_amount += (float)$row['sumamt'];
        }
    }
    $stmt->close();
}

// 4) Recent contributions (last 8)
$recent_contrib = [];
$sq = "SELECT amount, contribution_type, contribution_date, payment_method, reference_no FROM contributions WHERE member_id = ? ORDER BY contribution_date DESC LIMIT 8";
if ($stmt = $conn->prepare($sq)) {
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $recent_contrib[] = $row;
    $stmt->close();
}

// 5) Recent transactions (last 8)
$recent_txn = [];
$sq = "SELECT transaction_type, amount, transaction_date, notes FROM transactions WHERE member_id = ? ORDER BY transaction_date DESC LIMIT 8";
if ($stmt = $conn->prepare($sq)) {
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $recent_txn[] = $row;
    $stmt->close();
}

// 6) Notifications (unread count + last 6)
$unread_count = 0;
$notifications = [];
$sq = "SELECT notification_id, message, is_read, created_at FROM notifications WHERE member_id = ? ORDER BY created_at DESC LIMIT 6";
if ($stmt = $conn->prepare($sq)) {
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
        if ((int)$row['is_read'] === 0) $unread_count++;
    }
    $stmt->close();
}

// helper for formatting
function ksh($v) { return number_format((float)$v, 2); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Member Dashboard — <?= htmlspecialchars(SITE_NAME) ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Custom CSS (Sacconic theme green+gold) -->
  <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css">

  <style>
    /* Inline additions to ensure this file looks right even before editing style.css */
    :root {
      --sacco-green: #16a34a;
      --sacco-dark: #0b6623;
      --sacco-gold: #f59e0b;
    }
    body { background:#f6f8f9; font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
    .sidebar { width: 250px; min-height:100vh; background: #0b3d02; color: #fff; }
    .sidebar .nav-link { color: #dbeadf; }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .topbar { height:64px; background: #fff; border-bottom:1px solid #e9ecef; }
    .stat-card { border-radius: 12px; box-shadow: 0 6px 18px rgba(11,54,2,0.06); }
    .stat-card .icon { font-size: 26px; padding: 12px; border-radius: 10px; background: rgba(255,255,255,0.9); display:inline-flex; align-items:center; justify-content:center; }
    .btn-ghost { background: transparent; border: 1px solid rgba(11,54,2,0.08); }
    .small-muted { color:#6b7280; font-size:0.9rem; }
    .notification-badge { position:relative; }
    .notification-badge .badge { position:absolute; top:-6px; right:-6px; }
    /* responsive adjustments */
    @media (max-width: 992px) {
      .sidebar { position: fixed; left:-100%; top:0; z-index: 1040; transition: left .25s ease; }
      .sidebar.show { left:0; }
    }
  </style>
</head>
<body>

<div class="d-flex">
  <!-- SIDEBAR -->
  <aside class="sidebar d-flex flex-column p-3">
    <div class="mb-4 d-flex align-items-center gap-3">
      <img src="<?= htmlspecialchars(ASSET_BASE) ?>/images/people_logo.png" alt="logo" style="width:52px;height:52px;border-radius:50%;object-fit:cover;">
      <div>
        <div class="h6 mb-0"><?= htmlspecialchars(SITE_NAME) ?></div>
        <small class="small-muted">Member area</small>
      </div>
    </div>

    <nav class="nav nav-pills flex-column mb-4">
      <a class="nav-link active" href="<?= BASE_URL ?>/member/dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
      <a class="nav-link" href="<?= BASE_URL ?>/member/savings.php"><i class="bi bi-wallet2 me-2"></i> Savings</a>
      <a class="nav-link" href="<?= BASE_URL ?>/member/loans.php"><i class="bi bi-file-earmark-text me-2"></i> Loans</a>
      <a class="nav-link" href="<?= BASE_URL ?>/member/contributions.php"><i class="bi bi-cash-stack me-2"></i> Contributions</a>
      <a class="nav-link" href="<?= BASE_URL ?>/member/transactions.php"><i class="bi bi-receipt me-2"></i> Transactions</a>
      <a class="nav-link" href="<?= BASE_URL ?>/member/profile.php"><i class="bi bi-person-circle me-2"></i> My Profile</a>
      <a class="nav-link" href="<?= BASE_URL ?>/member/notifications.php"><i class="bi bi-bell me-2"></i> Notifications
        <?php if ($unread_count > 0): ?><span class="badge bg-warning text-dark ms-2"><?= $unread_count ?></span><?php endif; ?>
      </a>
      <a class="nav-link" href="<?= BASE_URL ?>/member/settings.php"><i class="bi bi-gear me-2"></i> Settings</a>
      <a class="nav-link" href="<?= BASE_URL ?>/public/support.php"><i class="bi bi-life-preserver me-2"></i> Support / Help</a>
      <div class="mt-auto">
        <a class="nav-link" href="<?= BASE_URL ?>/public/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
      </div>
    </nav>
  </aside>

  <!-- MAIN -->
  <div class="flex-fill" style="min-height:100vh;">
    <!-- TOPBAR -->
    <div class="topbar d-flex align-items-center px-4 shadow-sm">
      <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" class="btn btn-sm btn-ghost d-lg-none"><i class="bi bi-list"></i></button>
        <h5 class="mb-0"><?= htmlspecialchars($member_name) ?></h5>
        <small class="ms-2 small-muted">— Member Dashboard</small>
      </div>

      <div class="ms-auto d-flex align-items-center gap-3">
        <!-- notifications dropdown -->
        <div class="dropdown notification-badge">
          <button class="btn btn-light btn-sm dropdown-toggle" id="notifDrop" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bell"></i>
            <?php if ($unread_count > 0): ?><span class="badge bg-warning text-dark rounded-pill"><?= $unread_count ?></span><?php endif; ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="notifDrop" style="min-width:320px;">
            <li class="mb-2"><strong>Notifications</strong></li>
            <?php if (count($notifications) === 0): ?>
              <li class="small-muted px-2">No notifications</li>
            <?php else: foreach ($notifications as $n): ?>
              <li class="dropdown-item py-2 <?= $n['is_read'] ? '' : 'fw-bold' ?>">
                <div class="small-muted"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
                <div><?= htmlspecialchars($n['message']) ?></div>
              </li>
            <?php endforeach; endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center" href="<?= BASE_URL ?>/member/notifications.php">View all</a></li>
          </ul>
        </div>

        <!-- profile -->
        <div class="dropdown">
          <a class="d-flex align-items-center text-decoration-none" href="#" id="profileDrop" data-bs-toggle="dropdown" aria-expanded="false">
           <?php
$profile_pic_base64 = '';
if (!empty($memberData['profile_pic'])) {
    $profile_pic_base64 = 'data:image/jpeg;base64,' . base64_encode($memberData['profile_pic']);
} else {
    $profile_pic_base64 = ($memberData['gender'] === 'female')
        ? '../public/assets/uploads/female.jpg'
        : '../public/assets/uploads/male.jpg';
}
?>
<img src="<?= htmlspecialchars($profile_pic_base64) ?>" alt="Profile Picture" class="rounded-circle" style="width:50px;height:50px;object-fit:cover;">
            <div class="ms-2 text-dark">
              <div style="font-size:0.95rem;"><?= htmlspecialchars($member_name) ?></div>
              <small class="small-muted">Member</small>
            </div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDrop">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/member/profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/member/settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/public/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- PAGE CONTENT -->
    <div class="container-fluid px-4 py-4">
      <!-- Stats Row -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="p-3 stat-card bg-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="small-muted">Total Savings</div>
                <div class="h5 fw-bold">KES <?= ksh($total_savings) ?></div>
              </div>
              <div class="icon text-success"><i class="bi bi-piggy-bank-fill text-success"></i></div>
            </div>
            <div class="mt-3 small-muted">Latest savings & balances</div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="p-3 stat-card bg-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="small-muted">Contributions</div>
                <div class="h5 fw-bold">KES <?= ksh($total_contrib) ?></div>
              </div>
              <div class="icon text-warning"><i class="bi bi-cash-stack text-warning"></i></div>
            </div>
            <div class="mt-3 small-muted">All-time contributions</div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="p-3 stat-card bg-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="small-muted">Loans (Pending)</div>
                <div class="h5 fw-bold"><?= $loans_pending ?> pending</div>
              </div>
              <div class="icon text-danger"><i class="bi bi-hourglass-split text-danger"></i></div>
            </div>
            <div class="mt-3 small-muted">Approved: <?= $loans_approved ?> — Outstanding: KES <?= ksh($outstanding_loans_amount) ?></div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="p-3 stat-card bg-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="small-muted">Recent Transactions</div>
                <div class="h5 fw-bold"><?= count($recent_txn) ?></div>
              </div>
              <div class="icon text-primary"><i class="bi bi-clock-history text-primary"></i></div>
            </div>
            <div class="mt-3 small-muted">Last <?= count($recent_txn) ?> transactions</div>
          </div>
        </div>
      </div>

      <!-- Actions row: apply loan + deposit + calculator -->
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card p-3 stat-card">
            <h6 class="mb-3">Quick Actions</h6>
            <div class="d-grid gap-2">
              <a href="<?= BASE_URL ?>/member/apply_loan.php" class="btn btn-success"><i class="bi bi-file-earmark-plus me-2"></i> Apply for Loan</a>
              <a href="<?= BASE_URL ?>/member/contributions.php" class="btn btn-outline-success"><i class="bi bi-wallet2 me-2"></i> Make Contribution</a>
              <a href="<?= BASE_URL ?>/member/mpesa_request.php" class="btn btn-outline-success">
  <i class="bi bi-phone me-2"></i> Pay via M-Pesa
</a>

<!-- M-Pesa Payment Modal -->
<div class="modal fade" id="mpesaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?= BASE_URL ?>/member/mpesa_request.php" method="post">
        <div class="modal-header">
          <h5 class="modal-title">Pay Contribution via M-Pesa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label>Phone Number (07XXXXXXXX):</label>
          <input type="tel" class="form-control mb-3" name="phone" required pattern="^0[0-9]{9}$">
          <label>Amount (KES):</label>
          <input type="number" class="form-control mb-3" name="amount" required min="10">
        </div>
        <label>Contribution Type:</label>
          <select class="form-control mb-3" name="contribution_type" required>
            <option value="savings">Savings</option>
            <option value="shares">Shares</option>
            <option value="welfare">Welfare</option>
            <option value="registration">Registration</option>
            <option value="monthly">Monthly</option>
          </select>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Proceed</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
document.querySelector('#mpesaForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);
  try {
    const resp = await fetch('<?= BASE_URL ?>/member/mpesa_request.php', {
      method: 'POST',
      body: data
    });
    const text = await resp.text();
    // Inject the response into a Bootstrap alert container
    document.body.insertAdjacentHTML('beforeend', `
      <div class="alert alert-info position-fixed bottom-0 end-0 m-4">
        ${text}
      </div>
    `);
  } catch (err) {
    alert('Error: ' + err);
  }
});
</script>

              <a href="<?= BASE_URL ?>/member/savings.php" class="btn btn-outline-primary"><i class="bi bi-piggy-bank me-2"></i> View Savings</a>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="card p-3 stat-card">
            <h6 class="mb-3">Loan Calculator</h6>
            <div class="row g-2 align-items-center">
              <div class="col-md-3">
                <input id="loan_amount" class="form-control" type="number" min="0" step="100" placeholder="Amount (KES)">
              </div>
              <div class="col-md-3">
                <input id="loan_interest" class="form-control" type="number" min="0" step="0.01" value="12.00" placeholder="Interest %">
              </div>
              <div class="col-md-3">
                <input id="loan_months" class="form-control" type="number" min="1" step="1" value="12" placeholder="Months">
              </div>
              <div class="col-md-3 d-grid">
                <button id="calcBtn" class="btn btn-primary"><i class="bi bi-calculator me-2"></i> Calculate</button>
              </div>
              <div class="col-12 mt-3">
                <div id="calcResult" class="small-muted">Enter values and click Calculate to see monthly repayment.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tables: Recent contributions & transactions -->
      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Recent Contributions</h6>
              <a href="<?= BASE_URL ?>/member/contributions.php" class="small-muted">View all</a>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead class="table-light">
                  <tr><th>#</th><th>Type</th><th>Amount</th><th>Date</th></tr>
                </thead>
                <tbody>
                  <?php if (count($recent_contrib) === 0): ?>
                    <tr><td colspan="4" class="text-center small-muted">No contributions yet</td></tr>
                  <?php else: $i=1; foreach ($recent_contrib as $r): ?>
                    <tr>
                      <td><?= $i++ ?></td>
                      <td><?= htmlspecialchars($r['contribution_type']) ?></td>
                      <td>KES <?= ksh($r['amount']) ?></td>
                      <td><?= date('d M Y, H:i', strtotime($r['contribution_date'])) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6 mb-4">
          <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Recent Transactions</h6>
              <a href="<?= BASE_URL ?>/member/transactions.php" class="small-muted">View all</a>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead class="table-light"><tr><th>#</th><th>Type</th><th>Amount</th><th>Date</th></tr></thead>
                <tbody>
                  <?php if (count($recent_txn) === 0): ?>
                    <tr><td colspan="4" class="text-center small-muted">No transactions yet</td></tr>
                  <?php else: $i=1; foreach ($recent_txn as $t): ?>
                    <tr>
                      <td><?= $i++ ?></td>
                      <td><?= htmlspecialchars($t['transaction_type']) ?></td>
                      <td>KES <?= ksh($t['amount']) ?></td>
                      <td><?= date('d M Y, H:i', strtotime($t['transaction_date'])) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-5"></div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // sidebar toggle for small screens
  document.getElementById('sidebarToggle')?.addEventListener('click', function(){
    document.querySelector('.sidebar').classList.toggle('show');
  });

  // Loan calculator
  document.getElementById('calcBtn').addEventListener('click', function(){
    const amount = parseFloat(document.getElementById('loan_amount').value) || 0;
    const interest = parseFloat(document.getElementById('loan_interest').value) || 0;
    const months = parseInt(document.getElementById('loan_months').value) || 1;
    if (amount <= 0 || months <= 0) {
      document.getElementById('calcResult').innerText = 'Please enter a valid amount and duration.';
      return;
    }
    // Simple monthly repayment with flat interest: monthly = (amount + (amount * interest/100)) / months
    const total = amount + (amount * (interest/100));
    const monthly = total / months;
    document.getElementById('calcResult').innerHTML = '<strong>Monthly repayment:</strong> KES ' + monthly.toFixed(2) +
      ' <span class="small-muted"> (Total payable KES ' + total.toFixed(2) + ')</span>';
  });
</script>
</body>
</html>
