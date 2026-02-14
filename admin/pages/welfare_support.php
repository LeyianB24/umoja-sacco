<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// 1. Auth & Permissions
require_permission();

// Initialize Layout
$layout = LayoutManager::create('admin');
$admin_id = $_SESSION['admin_id'];

// 2. Handle Payout Grant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $member_id = intval($_POST['member_id']);
    $amount = floatval($_POST['amount']);
    $reason = trim($_POST['reason']);
    $case_id = !empty($_POST['case_id']) ? intval($_POST['case_id']) : null;
    
    if ($member_id > 0 && $amount > 0 && !empty($reason)) {
        require_once __DIR__ . '/../../inc/FinancialEngine.php';
        $engine = new FinancialEngine($conn);
        $pool_balance = $engine->getWelfarePoolBalance();

        if ($pool_balance < $amount) {
            flash_set("Error: Insufficient funds in Welfare Pool (Current: KES " . number_format($pool_balance, 2) . ")", "error");
        } else {
            $conn->begin_transaction();
            try {
                // A. Insert into Welfare Support Table
                $stmt = $conn->prepare("INSERT INTO welfare_support (member_id, amount, reason, case_id, granted_by, status, date_granted) VALUES (?, ?, ?, ?, ?, 'disbursed', NOW())");
                $stmt->bind_param("idsis", $member_id, $amount, $reason, $case_id, $admin_id);
                $stmt->execute();
                $support_id = $conn->insert_id;
                $stmt->close();
                
                // B. Update Case Totals
                if ($case_id) {
                    $conn->query("UPDATE welfare_cases SET total_disbursed = total_disbursed + $amount WHERE case_id = $case_id");
                    
                    // Check if fully funded
                    $res = $conn->query("SELECT approved_amount, total_disbursed FROM welfare_cases WHERE case_id = $case_id");
                    $c_data = $res->fetch_assoc();
                    if ($c_data['total_disbursed'] >= $c_data['approved_amount']) {
                        $conn->query("UPDATE welfare_cases SET status = 'funded' WHERE case_id = $case_id");
                    }
                }

                // C. Record via Financial Engine
                $ref = "WS-" . str_pad((string)$support_id, 6, '0', STR_PAD_LEFT);
                $engine->transact([
                    'member_id'     => $member_id,
                    'amount'        => $amount,
                    'action_type'   => 'welfare_payout',
                    'reference'     => $ref,
                    'notes'         => "Welfare Disbursement: " . $reason,
                    'related_id'    => $support_id,
                    'related_table' => 'welfare_support'
                ]);
                
                // D. Notify Member
                $msg = "Welfare Support Disbursed: KES " . number_format((float)$amount) . " has been credited to your wallet for: $reason.";
                $st_not = $conn->prepare("INSERT INTO notifications (user_type, user_id, message, is_read, created_at) VALUES ('member', ?, ?, 0, NOW())");
                $st_not->bind_param("is", $member_id, $msg);
                $st_not->execute();
                
                $conn->commit();
                flash_set("Welfare support disbursed successfully.", "success");
                header("Location: welfare_support.php");
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                flash_set("Error: " . $e->getMessage(), "error");
            }
        }
    } else {
        flash_set("Please fill all required fields.", "error");
    }
}

// 3. Data Fetching
// History - Join with welfare_cases to show case title
$history = $conn->query("SELECT w.*, m.full_name, m.national_id, c.title as case_title 
                         FROM welfare_support w 
                         JOIN members m ON w.member_id = m.member_id 
                         LEFT JOIN welfare_cases c ON w.case_id = c.case_id
                         ORDER BY w.date_granted DESC LIMIT 20");

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    $sql_export = "SELECT w.*, m.full_name, m.national_id 
                   FROM welfare_support w 
                   JOIN members m ON w.member_id = m.member_id 
                   ORDER BY w.date_granted DESC";
    $res_e = $conn->query($sql_export);
    $export_data_raw = $res_e->fetch_all(MYSQLI_ASSOC);

    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    $total_val = 0;
    foreach ($export_data_raw as $row) {
        $total_val += (float)$row['amount'];
        $data[] = [
            'Date' => date('d-M-Y', strtotime($row['date_granted'])),
            'Member' => $row['full_name'],
            'National ID' => $row['national_id'],
            'Amount' => number_format((float)$row['amount'], 2),
            'Reason' => $row['reason']
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Welfare Support History',
        'module' => 'Welfare Management',
        'headers' => ['Date', 'Member', 'National ID', 'Amount', 'Reason'],
        'total_value' => $total_val
    ]);
    exit;
}

// Active Members for Dropdown
$members = $conn->query("SELECT member_id, full_name, national_id FROM members WHERE status='active' ORDER BY full_name ASC");

// Active Cases
$cases_sql = "SELECT case_id, title, related_member_id, (approved_amount - total_disbursed) as remaining 
              FROM welfare_cases 
              WHERE status IN ('active', 'approved') 
              ORDER BY created_at DESC";
$cases_res = $conn->query($cases_sql);
$cases_array = [];
while($c = $cases_res->fetch_assoc()) $cases_array[] = $c;

// Statistics (This Month)
$stats = $conn->query("SELECT 
                        COUNT(*) as count, 
                        COALESCE(SUM(amount), 0) as total 
                       FROM welfare_support 
                       WHERE MONTH(date_granted) = MONTH(CURRENT_DATE()) 
                       AND YEAR(date_granted) = YEAR(CURRENT_DATE())")->fetch_assoc();

// --- CHART DATA: Disbursement Trends (Last 6 Months) ---
$trend_sql = "SELECT DATE_FORMAT(date_granted, '%Y-%m') as m, SUM(amount) as total 
              FROM welfare_support 
              WHERE date_granted >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
              GROUP BY m 
              ORDER BY m ASC";
$trend_res = $conn->query($trend_sql);
$trend_labels = [];
$trend_data = [];
while ($row = $trend_res->fetch_assoc()) {
    $trend_labels[] = date('M Y', strtotime($row['m'] . '-01'));
    $trend_data[] = (float)$row['total'];
}

// --- CHART DATA: Fund Utilization (Total Raised vs Disbursed across all cases) ---
$util_sql = "SELECT SUM(total_raised) as raised, SUM(total_disbursed) as disbursed FROM welfare_cases";
$util_res = $conn->query($util_sql);
$util_row = $util_res->fetch_assoc();
$total_raised = (float)($util_row['raised'] ?? 0);
$total_disbursed = (float)($util_row['disbursed'] ?? 0);
$pool_available = 0; 
// Better metric: Use FinancialEngine for actual pool balance if needed, 
// but for "Utilization" chart, Raised vs Disbursed is good context.

$pageTitle = "Welfare Support";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        /* =============================
           HOPE UI PREMIUM THEME
           Forest & Lime Edition
        ============================= */
        :root {
            --forest-deep: #0f2e25;
            --forest-light: #1a4d3d;
            --lime-vibrant: #d0f35d;
            --lime-dark: #a8cf12;
            --glass-bg: rgba(255, 255, 255, 0.95);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: #f0f2f5;
            color: var(--forest-deep);
        }

        /* --- Animations --- */
        @keyframes slideInUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .animate-slide-up { animation: slideInUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
        .animate-fade-in { animation: fadeIn 0.8s ease-out forwards; }

        /* --- Cards --- */
        .card-custom {
            background: white;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        }

        /* --- Buttons --- */
        .btn-lime {
            background: var(--lime-vibrant);
            color: var(--forest-deep);
            font-weight: 700;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .btn-lime:hover {
            background: var(--lime-dark);
            box-shadow: 0 4px 15px rgba(208, 243, 93, 0.4);
        }

        /* --- Table --- */
        .table-custom {
            margin-bottom: 0;
        }
        .table-custom th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 1rem;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .table-custom td {
            vertical-align: middle;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .table-custom tr:last-child td { border-bottom: none; }
        
        /* --- Form Elements --- */
        .form-label {
            font-size: 0.85rem;
            color: var(--forest-light);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--lime-dark);
            box-shadow: 0 0 0 4px rgba(208, 243, 93, 0.2);
        }

        /* --- Stat Badge --- */
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="container-fluid py-4">
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 animate-slide-up">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--forest-deep);">Welfare Support</h2>
                    <p class="text-muted small mb-0">Manage benevolent grants and member support payouts.</p>
                </div>
                <!-- Welfare Pool Balance Card (Small) -->
                <div class="glass-card px-4 py-2 d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-safe2 fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-bold text-uppercase ls-1">Pool Balance</div>
                        <?php 
                            require_once __DIR__ . '/../../inc/FinancialEngine.php';
                            $fEngine = new FinancialEngine($conn);
                            $poolBal = $fEngine->getWelfarePoolBalance(); 
                        ?>
                        <div class="fw-bold fs-4 text-dark">KES <?= number_format($poolBal, 2) ?></div>
                    </div>
                </div>
            </div>

            <?php flash_render(); ?>

            <div class="row g-4 mb-4">
                <!-- Analytics: Utilization -->
                <div class="col-md-5 animate-slide-up" style="animation-delay: 0.05s;">
                    <div class="card-custom h-100 p-4 border-0 shadow-sm d-flex flex-column">
                        <h6 class="fw-bold text-dark mb-3">Fund Utilization</h6>
                        <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                             <div id="chart-utilization" class="w-100"></div>
                        </div>
                    </div>
                </div>
                <!-- Analytics: Trends -->
                <div class="col-md-7 animate-slide-up" style="animation-delay: 0.1s;">
                    <div class="card-custom h-100 p-4 border-0 shadow-sm d-flex flex-column">
                        <h6 class="fw-bold text-dark mb-3">Disbursement Trends (6 Months)</h6>
                        <div class="flex-grow-1">
                             <div id="chart-trends" class="w-100"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <!-- Left Column: Grant Terminal -->
                <div class="col-lg-4 animate-slide-up" style="animation-delay: 0.2s;">
                    <div class="card-custom h-100 position-relative overflow-hidden border-0 shadow-lg">
                        <div class="position-absolute top-0 start-0 w-100 h-100 bg-gradient-forest opacity-10" style="pointer-events: none;"></div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0 d-flex align-items-center">
                                <i class="bi bi-terminal-plus text-success me-2 fs-4"></i> New Grant
                            </h5>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-3">DISBURSEMENT</span>
                        </div>
                        
                        <form method="POST" class="d-flex flex-column gap-3">
                            <?= csrf_field() ?>
                            
                            <!-- Member Search -->
                            <div class="form-floating">
                                <select name="member_id" id="beneficiary_select" class="form-select border-0 bg-light fw-bold" required style="border-radius: 12px;">
                                    <option value="">Select Beneficiary...</option>
                                    <?php while($m = $members->fetch_assoc()): ?>
                                        <option value="<?= $m['member_id'] ?>">
                                            <?= htmlspecialchars($m['full_name']) ?> (<?= $m['national_id'] ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <label for="beneficiary_select" class="fw-bold text-uppercase text-muted small">Beneficiary</label>
                            </div>

                            <!-- Case Link -->
                            <div class="form-floating">
                                <select name="case_id" id="case_select" class="form-select border-0 bg-light fw-bold" onchange="updateMemberFromCase(this)" style="border-radius: 12px;">
                                    <option value="" data-member="">General / No Case</option>
                                    <?php foreach($cases_array as $c): ?>
                                        <option value="<?= $c['case_id'] ?>" data-member="<?= $c['related_member_id'] ?>" data-rem="<?= $c['remaining'] ?>" <?= (isset($_GET['case_id']) && $_GET['case_id'] == $c['case_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['title']) ?> (Bal: <?= number_format($c['remaining'], 0) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="case_select" class="fw-bold text-uppercase text-muted small">Related Case (Optional)</label>
                            </div>

                            <!-- Amount -->
                            <div class="bg-light p-3 rounded-4 border border-light">
                                <label class="small text-uppercase fw-bold text-muted mb-1 d-block">Amount</label>
                                <div class="d-flex align-items-center">
                                    <span class="fs-4 fw-bold text-muted me-2">KES</span>
                                    <input type="number" name="amount" class="form-control border-0 bg-transparent fs-2 fw-bold text-dark p-0 shadow-none" step="0.01" min="1" placeholder="0.00" required>
                                </div>
                            </div>

                            <!-- Reason -->
                            <div class="form-floating">
                                <input type="text" name="reason" class="form-control border-0 bg-light fw-medium" placeholder="Reason" required style="border-radius: 12px;">
                                <label class="fw-bold text-uppercase text-muted small">Reason / Note</label>
                            </div>

                            <button type="submit" class="btn btn-lime w-100 py-3 mt-2 d-flex align-items-center justify-content-center shadow-lg rounded-4 transition-all">
                                <i class="bi bi-wallet2 me-2 fs-5"></i>
                                <span class="fw-bold">PROCESS PAYOUT</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Recent Grants -->
                <div class="col-lg-8 animate-slide-up" style="animation-delay: 0.3s;">
                    <div class="card-custom p-0 overflow-hidden h-100 border-0 shadow-sm">
                        <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Recent Disbursements</h6>
                                <p class="text-muted small mb-0">History of the last 20 welfare grants.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm fw-bold border text-muted dropdown-toggle rounded-pill px-3" data-bs-toggle="dropdown">
                                        <i class="bi bi-download me-1"></i> Export
                                    </button>
                                    <ul class="dropdown-menu shadow border-0 radius-12">
                                        <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>PDF Report</a></li>
                                        <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Excel Sheet</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print View</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-custom table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 text-uppercase text-muted small fw-bold">Date & Time</th>
                                        <th class="text-uppercase text-muted small fw-bold">Beneficiary</th>
                                        <th class="text-uppercase text-muted small fw-bold">Details</th>
                                        <th class="text-end text-uppercase text-muted small fw-bold pe-4">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($history->num_rows === 0): ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="bi bi-inbox fs-1 opacity-25 mb-2"></i>
                                                <span>No welfare grants recorded recently.</span>
                                            </div>
                                        </td></tr>
                                    <?php else: while ($row = $history->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?= date('M d, Y', strtotime($row['date_granted'])) ?></div>
                                                <small class="text-muted"><?= date('h:i A', strtotime($row['date_granted'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center me-3 text-success fw-bold border border-success-subtle" style="width: 40px; height: 40px;">
                                                        <?= substr($row['full_name'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['full_name']) ?></div>
                                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-0" style="font-size: 0.65rem;">ID: <?= $row['national_id'] ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                             <td>
                                                <div class="text-truncate" style="max-width: 280px;">
                                                    <?php if($row['case_title']): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle me-1 rounded-1">CASE</span>
                                                        <span class="fw-medium"><?= htmlspecialchars($row['case_title']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted border me-1 rounded-1">GENERAL</span>
                                                        <span class="text-muted"><?= htmlspecialchars($row['reason']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if($row['case_title']): ?>
                                                    <small class="text-muted d-block ps-1 border-start ms-1 mt-1" style="font-size: 0.75rem;">Note: <?= htmlspecialchars($row['reason']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="fw-bold text-dark fs-6 font-monospace">
                                                    KES <?= number_format((float)$row['amount'], 2) ?>
                                                </div>
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2" style="font-size: 0.65rem;">DISBURSED</span>
                                            </td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div> <!-- Row -->
<?php $layout->footer(); ?>
        </div>
        
    </div>
</div>

<script src="<?= BASE_URL ?>/public/assets/js/main.js?v=<?= time() ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- 1. JS Map for Member -> Active Case ---
const memberToCaseMap = {};
<?php foreach($cases_array as $c): ?>
    memberToCaseMap[<?= $c['related_member_id'] ?>] = <?= $c['case_id'] ?>;
<?php endforeach; ?>

function updateMemberFromCase(sel) {
    const opt = sel.options[sel.selectedIndex];
    const mid = opt.getAttribute('data-member');
    const rem = opt.getAttribute('data-rem');
    
    // Only update member if one is linked to the case
    if (mid) {
        document.getElementById('beneficiary_select').value = mid;
    }
    
    // Auto-fill remaining amount if available
    if (rem && rem > 0) {
        document.getElementsByName('amount')[0].value = rem;
    }
}

// Auto-Select Case when Member is selected
document.getElementById('beneficiary_select').addEventListener('change', function() {
    const memberId = this.value;
    const caseSelect = document.getElementById('case_select');
    
    // Reset first
    caseSelect.value = "";
    
    if (memberId && memberToCaseMap[memberId]) {
        const targetCaseId = memberToCaseMap[memberId];
        caseSelect.value = targetCaseId;
        
        // Trigger visual update for amount if needed
        updateMemberFromCase(caseSelect);
    }
});

// Initial trigger if case_id is set via GET
window.onload = function() {
    const cs = document.getElementById('case_select');
    if (cs.value) updateMemberFromCase(cs);
    
    // --- 2. Chart Initialization ---
    initCharts();
};

function initCharts() {
    // A. TRENDS CHART
    const trendOptions = {
        series: [{
            name: "Disbursed",
            data: <?= json_encode($trend_data) ?>
        }],
        chart: {
            type: 'area',
            height: 250,
            toolbar: { show: false },
            fontFamily: 'Outfit, sans-serif'
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3, colors: ['#10b981'] },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.1,
                stops: [0, 90, 100],
                colorStops: [
                    { offset: 0, color: '#10b981', opacity: 0.5 },
                    { offset: 100, color: '#10b981', opacity: 0 }
                ]
            }
        },
        xaxis: {
            categories: <?= json_encode($trend_labels) ?>,
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: { formatter: (val) => { return (val/1000).toFixed(0) + 'K' } }
        },
        grid: {
            borderColor: '#f1f5f9',
            strokeDashArray: 4,
            yaxis: { lines: { show: true } }
        },
        colors: ['#10b981'],
        tooltip: {
            y: { formatter: function (val) { return "KES " + val.toLocaleString() } }
        }
    };
    new ApexCharts(document.querySelector("#chart-trends"), trendOptions).render();

    // B. UTILIZATION CHART
    const utilOptions = {
        series: [<?= $total_disbursed ?>, <?= max(0, $total_raised - $total_disbursed) ?>],
        labels: ['Disbursed', 'Remaining'],
        chart: {
            type: 'donut',
            height: 250,
            fontFamily: 'Outfit, sans-serif'
        },
        colors: ['#0f2e25', '#d0f35d'], 
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total Raised',
                            formatter: function (w) {
                                return '<?= number_format($total_raised/1000, 1) ?>K';
                            }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { position: 'bottom' },
        stroke: { show: false }
    };
    new ApexCharts(document.querySelector("#chart-utilization"), utilOptions).render();
}
</script>
</body>
</html>
