<?php
// member/welfare.php
// DASHBOARD: WELFARE ANALYTICS & HISTORY
// Enhanced UI: Forest Green & Lime Theme (Hope UI Style)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$theme = $_COOKIE['theme'] ?? 'light';

// --- 1. FETCH & PROCESS DATA FOR ANALYTICS ---

// A. Contributions (Money In)
$sqlIn = "SELECT * FROM contributions 
          WHERE member_id = ? 
          AND contribution_type IN ('welfare', 'welfare_case') 
          ORDER BY created_at DESC";
$stmt = $conn->prepare($sqlIn);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$contribsResult = $stmt->get_result();

$total_given = 0;
$all_contribs = [];
$chart_data = []; // Array for Chart.js

while($row = $contribsResult->fetch_assoc()) {
    if ($row['status'] == 'active' || $row['status'] == 'completed') {
        $total_given += $row['amount'];
        
        // Group by Month for Chart (Format: Y-m)
        $monthKey = date('Y-m', strtotime($row['created_at']));
        if(!isset($chart_data[$monthKey])) $chart_data[$monthKey] = 0;
        $chart_data[$monthKey] += $row['amount'];
    }
    $all_contribs[] = $row;
}

// B. Support (Money Out)
$sqlOut = "SELECT * FROM welfare_support WHERE member_id = ? ORDER BY date_granted DESC";
$stmt = $conn->prepare($sqlOut);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$supportResult = $stmt->get_result();

$total_received = 0;
$all_support = [];

while($row = $supportResult->fetch_assoc()) {
    if ($row['status'] == 'approved' || $row['status'] == 'disbursed') {
        $total_received += $row['amount'];
    }
    $all_support[] = $row;
}

// C. Net Calculations
$net_standing = $total_given - $total_received;
$standing_status = ($net_standing >= 0) ? 'contributor' : 'beneficiary';

// D. Prepare Chart Data (Sort by date)
ksort($chart_data);
$json_labels = json_encode(array_keys($chart_data));
$json_values = json_encode(array_values($chart_data));

$pageTitle = "Welfare Dashboard";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom Assets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="member-body">

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper d-flex flex-column min-vh-100">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid flex-grow-1 py-5 px-4">
                
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 animate__animated animate__fadeInDown">
                    <div>
                        <h2 class="fw-bold mb-1">Welfare Fund</h2>
                        <p class="text-secondary mb-0">Your benevolent history and support tracking.</p>
                    </div>
                    <div class="d-flex gap-2 mt-3 mt-md-0">
                        <button onclick="window.print()" class="btn btn-outline-secondary d-flex align-items-center gap-2 rounded-pill px-3 fw-bold border-2">
                            <i class="bi bi-printer"></i> <span>Report</span>
                        </button>
                        <a href="<?= BASE_URL ?>/member/mpesa_request.php?type=welfare" class="btn btn-success d-flex align-items-center gap-2 rounded-pill px-4 fw-bold text-dark border-0 shadow-sm" style="background-color: var(--hop-lime);">
                            <i class="bi bi-heart-fill"></i> <span>Contribute</span>
                        </a>
                    </div>
                </div>

                
                <div class="row g-4 mb-4 animate__animated animate__fadeInUp">
                    <div class="col-md-4">
                        <div class="hope-card p-4 h-100 position-relative">
                            <div class="decoration-circle bg-success" style="width: 100px; height: 100px; top: -20px; right: -20px;"></div>
                            <div class="d-flex align-items-center gap-3 mb-3 position-relative z-1">
                                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(var(--hop-lime-rgb), 0.2); color: #4d7c0f;">
                                    <i class="bi bi-arrow-up-right fs-4"></i>
                                </div>
                                <span class="text-secondary text-uppercase fw-bold small">Total Contributed</span>
                            </div>
                            <h3 class="fw-bold mb-0">KES <?= number_format($total_given) ?></h3>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="hope-card p-4 h-100 position-relative">
                            <div class="decoration-circle bg-danger" style="width: 100px; height: 100px; top: -20px; right: -20px;"></div>
                            <div class="d-flex align-items-center gap-3 mb-3 position-relative z-1">
                                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(var(--accent-rose-rgb), 0.1); color: var(--accent-rose);">
                                    <i class="bi bi-arrow-down-left fs-4"></i>
                                </div>
                                <span class="text-secondary text-uppercase fw-bold small">Support Received</span>
                            </div>
                            <h3 class="fw-bold mb-0">KES <?= number_format($total_received) ?></h3>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="hope-card p-4 h-100 position-relative" style="background: linear-gradient(145deg, var(--hop-card-bg) 0%, rgba(var(--hop-lime-rgb), 0.1) 100%);">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center bg-white text-dark shadow-sm">
                                    <i class="bi bi-scale fs-4"></i>
                                </div>
                                <span class="text-secondary text-uppercase fw-bold small">Net Standing</span>
                            </div>
                            <h3 class="fw-bold mb-1 <?= $standing_status === 'contributor' ? 'text-success' : 'text-danger' ?>">
                                <?= ($net_standing > 0 ? '+' : '') . number_format($net_standing) ?>
                            </h3>
                            <p class="small text-secondary mb-0">
                                You are a net <span class="fw-bold"><?= ucfirst($standing_status) ?></span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-5">
                    <div class="col-lg-8 animate__animated animate__fadeInLeft" style="animation-delay: 0.1s;">
                        <div class="hope-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0">Contribution Trend</h5>
                                <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">Lifetime</span>
                            </div>
                            <div style="height: 300px; position: relative;">
                                <canvas id="welfareChart"
                                    data-labels="<?= htmlspecialchars($json_labels) ?>"
                                    data-values="<?= htmlspecialchars($json_values) ?>">
                                </canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 animate__animated animate__fadeInRight" style="animation-delay: 0.2s;">
                        <div class="hope-card p-4 h-100 bg-dark text-white border-0" style="background: linear-gradient(135deg, var(--hop-dark) 0%, #1a4d40 100%);">
                            <h4 class="fw-bold mb-3">Welfare Policy</h4>
                            <p class="text-white-50 mb-4 small">
                                Contributions assist members in times of bereavement and hospitalization. 
                                Active status is required to be eligible for support.
                            </p>
                            <hr class="border-white opacity-25">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span class="small fw-medium">Bereavement Support</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span class="small fw-medium">Medical Emergency</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hope-card p-0 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                    <div class="p-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <ul class="nav nav-pills nav-pills-modern" id="pills-tab" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" id="pills-given-tab" data-bs-toggle="pill" data-bs-target="#pills-given" type="button">
                                    Contributions
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="pills-received-tab" data-bs-toggle="pill" data-bs-target="#pills-received" type="button">
                                    Support Received
                                </button>
                            </li>
                        </ul>
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" id="tableSearch" class="form-control" placeholder="Search records...">
                        </div>
                    </div>

                    <div class="tab-content" id="pills-tabContent">
                        
                        <div class="tab-pane fade show active" id="pills-given">
                            <div class="table-responsive">
                                <table class="table table-modern table-hover mb-0" id="tableGiven">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Date</th>
                                            <th>Ref / Context</th>
                                            <th>Status</th>
                                            <th class="text-end pe-4">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($all_contribs)): ?>
                                            <tr><td colspan="4" class="text-center py-5 text-muted">No records found.</td></tr>
                                        <?php else: foreach($all_contribs as $row): 
                                            $status = strtolower($row['status']);
                                            $color = match($status) { 'active'=>'success', 'completed'=>'success', 'pending'=>'warning', default=>'secondary' };
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-medium"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold text-dark"><?= ucfirst(str_replace('_', ' ', $row['contribution_type'])) ?></span>
                                                    <span class="small text-muted font-monospace"><?= $row['reference_no'] ?></span>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle rounded-pill px-3"><?= ucfirst($status) ?></span></td>
                                            <td class="text-end pe-4 fw-bold text-success">+ <?= number_format($row['amount']) ?></td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pills-received">
                             <div class="table-responsive">
                                <table class="table table-modern table-hover mb-0" id="tableReceived">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Date</th>
                                            <th>Reason / Approver</th>
                                            <th>Status</th>
                                            <th class="text-end pe-4">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($all_support)): ?>
                                            <tr><td colspan="4" class="text-center py-5 text-muted">No support records found.</td></tr>
                                        <?php else: foreach($all_support as $row): 
                                             $status = strtolower($row['status']);
                                             $color = match($status) { 'approved'=>'success', 'disbursed'=>'success', 'rejected'=>'danger', default=>'secondary' };
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-medium"><?= date('M d, Y', strtotime($row['date_granted'])) ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['reason']) ?></span>
                                                    <span class="small text-muted">Approved by #<?= $row['granted_by'] ?? 'sys' ?></span>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle rounded-pill px-3"><?= ucfirst($status) ?></span></td>
                                            <td class="text-end pe-4 fw-bold text-danger">- <?= number_format($row['amount']) ?></td>
                                        </tr>
                                        <?php endforeach; endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
</body>
</html>