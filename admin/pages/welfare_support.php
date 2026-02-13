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
                <div class="d-flex gap-3">
                     <!-- Stat: Total This Month -->
                    <div class="d-flex align-items-center bg-white px-4 py-2 rounded-pill shadow-sm border">
                        <i class="bi bi-calendar-check text-success me-2 fs-5"></i>
                        <div>
                            <div class="small text-muted fw-bold" style="font-size: 0.7rem; line-height: 1;">THIS MONTH</div>
                            <div class="fw-bold text-dark">KES <?= number_format((float)$stats['total']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php flash_render(); ?>

            <div class="row g-4">
                
                <!-- Left Column: Grant Form -->
                <div class="col-lg-4 animate-slide-up" style="animation-delay: 0.1s;">
                    <div class="card-custom h-100 position-relative overflow-hidden">
                        <div class="position-absolute top-0 end-0 p-3 opacity-10">
                            <i class="bi bi-heart-pulse-fill fs-1 text-success"></i>
                        </div>
                        
                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                            <i class="bi bi-plus-circle-fill text-success me-2"></i> New Grant
                        </h5>
                        
                        <form method="POST">
                            <?= csrf_field() ?>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Beneficiary</label>
                                <select name="member_id" id="beneficiary_select" class="form-select" required>
                                    <option value="">-- Search Member --</option>
                                    <?php while($m = $members->fetch_assoc()): ?>
                                        <option value="<?= $m['member_id'] ?>">
                                            <?= htmlspecialchars($m['full_name']) ?> (<?= $m['national_id'] ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Case Reference (Optional)</label>
                                <select name="case_id" id="case_select" class="form-select" onchange="updateMemberFromCase(this)">
                                    <option value="" data-member="">-- General / No Case --</option>
                                    <?php foreach($cases_array as $c): ?>
                                        <option value="<?= $c['case_id'] ?>" data-member="<?= $c['related_member_id'] ?>" data-rem="<?= $c['remaining'] ?>" <?= (isset($_GET['case_id']) && $_GET['case_id'] == $c['case_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['title']) ?> (Bal: <?= number_format($c['remaining'], 0) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Amount to Grant</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white fw-bold text-muted border-end-0">KES</span>
                                    <input type="number" name="amount" class="form-control border-start-0 ps-1 fw-bold text-dark fs-5" step="0.01" min="1" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Reason / Details</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Hospital Bill Support for Kin..." required></textarea>
                            </div>

                            <button type="submit" class="btn btn-lime w-100 py-3 d-flex align-items-center justify-content-center shadow-sm">
                                <i class="bi bi-check-lg me-2 fs-5"></i>
                                <span>Approve & Disburse</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Recent Grants -->
                <div class="col-lg-8 animate-slide-up" style="animation-delay: 0.2s;">
                    <div class="card-custom p-0 overflow-hidden h-100">
                        <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark">Recent Activity</h6>
                            <div class="d-flex gap-2">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-white border shadow-sm text-muted dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="bi bi-download me-1"></i> Export
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>">Export PDF</a></li>
                                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>">Export Excel</a></li>
                                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank">Print Report</a></li>
                                    </ul>
                                </div>
                                <a href="#" class="btn btn-sm btn-white border shadow-sm text-muted">View All</a>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-custom table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Beneficiary</th>
                                        <th>Reason/Case</th>
                                        <th class="text-end pe-4">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($history->num_rows === 0): ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">No welfare grants recorded yet.</td></tr>
                                    <?php else: while ($row = $history->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-medium text-dark"><?= date('M d, Y', strtotime($row['date_granted'])) ?></div>
                                                <small class="text-muted"><?= date('h:i A', strtotime($row['date_granted'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3 text-success fw-bold border" style="width: 40px; height: 40px;">
                                                        <?= substr($row['full_name'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= $row['full_name'] ?></div>
                                                        <small class="text-muted text-uppercase" style="font-size: 0.75rem;"><?= $row['national_id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                             <td>
                                                <div class="text-truncate" style="max-width: 250px;">
                                                    <?php if($row['case_title']): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle me-1">CASE</span>
                                                        <?= htmlspecialchars($row['case_title']) ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark border me-1">GENERAL</span>
                                                        <?= htmlspecialchars($row['reason']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted d-block"><?= htmlspecialchars($row['reason']) ?></small>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="fw-bold text-success fs-6">
                                                    + KES <?= number_format((float)$row['amount']) ?>
                                                </div>
                                                <small class="text-muted">Approved</small>
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
function updateMemberFromCase(sel) {
    const opt = sel.options[sel.selectedIndex];
    const mid = opt.getAttribute('data-member');
    const rem = opt.getAttribute('data-rem');
    if (mid) {
        document.getElementById('beneficiary_select').value = mid;
    }
    if (rem && rem > 0) {
        document.getElementsByName('amount')[0].value = rem;
    }
}
// Initial trigger if case_id is set via GET
window.onload = function() {
    const cs = document.getElementById('case_select');
    if (cs.value) updateMemberFromCase(cs);
};
</script>
</body>
</html>
