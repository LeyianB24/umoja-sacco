<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');

// member/welfare.php
// DASHBOARD: WELFARE ANALYTICS & HISTORY
// Enhanced UI: Forest Green & Lime Theme (Hope UI Style)

if (session_status() === PHP_SESSION_NONE) session_start();

require_member();

// Initialize Layout Manager
$layout = LayoutManager::create('member');

$member_id = $_SESSION['member_id'];
$theme = $_COOKIE['theme'] ?? 'light';

// --- 0. HELPERS ---
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        if (!$datetime) return "unknown";
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $weeks = floor($diff->d / 7);
        $days = $diff->d - ($weeks * 7);

        $string = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute');
        foreach ($string as $k => &$v) {
            $value = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
            if ($value) $v = $value . ' ' . $v . ($value > 1 ? 's' : '');
            else unset($string[$k]);
        }
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

// --- 1. HANDLE CASE SUBMISSION ---
$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_case'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $req_amt = floatval($_POST['requested_amount']);

    if (!empty($title) && $req_amt > 0) {
        $stmt = $conn->prepare("INSERT INTO welfare_cases (title, description, requested_amount, related_member_id, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("ssdi", $title, $desc, $req_amt, $member_id);
        if ($stmt->execute()) {
            $success = "Your welfare request has been submitted for review.";
        } else {
            $error = "System Error: " . $conn->error;
        }
    } else {
        $error = "Please fill all required fields.";
    }
}

// --- 1. FETCH & PROCESS DATA FOR ANALYTICS ---

// 2. Fetch Chart Data (History of Contributions)
$sqlIn = "SELECT amount, status, reference_no, created_at FROM contributions 
          WHERE member_id = ? AND contribution_type = 'welfare'
          ORDER BY created_at ASC";
$stmt = $conn->prepare($sqlIn);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$contribsRaw = $stmt->get_result();

$chart_data = [];
$all_contribs = [];
while($row = $contribsRaw->fetch_assoc()) {
    $monthKey = date('Y-m', strtotime($row['created_at']));
    if(!isset($chart_data[$monthKey])) $chart_data[$monthKey] = 0;
    $chart_data[$monthKey] += $row['amount'];
    $all_contribs[] = $row;
}

// B. Support (Money Out) - from welfare_support table
$sqlOut = "SELECT * FROM welfare_support WHERE member_id = ? ORDER BY date_granted DESC";
$stmt = $conn->prepare($sqlOut);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$supportResult = $stmt->get_result();

$total_received = 0;
$all_support = [];

while($row = $supportResult->fetch_assoc()) {
    if ($row['status'] === 'disbursed' || $row['status'] === 'approved') {
        $total_received += (float)$row['amount'];
    }
    $all_support[] = $row;
}

// B2. Fetch Related Cases for Member (Personal Entries)
$sqlCases = "SELECT * FROM welfare_cases WHERE related_member_id = ? ORDER BY created_at DESC";
$stmtC = $conn->prepare($sqlCases);
$stmtC->bind_param("i", $member_id);
$stmtC->execute();
$member_cases = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);

// B3. Fetch Community Cases (All Active/Approved Situations)
$sqlComm = "SELECT c.*, 
            (SELECT COUNT(*) FROM welfare_donations WHERE case_id = c.case_id) as donor_count 
            FROM welfare_cases c 
            WHERE c.status IN ('active', 'approved', 'funded') 
            ORDER BY c.created_at DESC";
$community_cases = $conn->query($sqlComm)->fetch_all(MYSQLI_ASSOC);

// C. Ledger Balances via Financial Engine (Pooled Logic)
// ---------------------------------------------------------
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine = new FinancialEngine($conn);

$welfare_pool = $engine->getWelfarePoolBalance();
$total_given = $engine->getMemberWelfareLifetime($member_id);
$net_standing = $total_given - $total_received;
$standing_status = ($net_standing >= 0) ? 'contributor' : 'beneficiary';

// D. Prepare Chart Data (Sort by date)
ksort($chart_data);
$json_labels = json_encode(array_keys($chart_data));
$json_values = json_encode(array_values($chart_data));

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    foreach($all_contribs as $row) {
        $data[] = [
            'Date' => date('d-M-Y', strtotime($row['created_at'])),
            'Reference' => $row['reference_no'],
            'Type' => 'Contribution',
            'Amount' => '+ ' . number_format((float)$row['amount'], 2),
            'Status' => ucfirst($row['status'] ?? 'completed')
        ];
    }
    
    foreach($all_support as $row) {
        if (!in_array($row['status'], ['approved', 'disbursed'])) continue;
        $data[] = [
            'Date' => date('d-M-Y', strtotime($row['date_granted'])),
            'Reference' => 'Support: ' . ($row['reason'] ?? 'N/A'),
            'Type' => 'Support Received',
            'Amount' => '- ' . number_format((float)$row['amount'], 2),
            'Status' => ucfirst($row['status'])
        ];
    }

    // Sort combined data by date
    usort($data, function($a, $b) {
        return strtotime($b['Date']) - strtotime($a['Date']);
    });

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Welfare Fund Statement',
        'module' => 'Member Portal',
        'headers' => ['Date', 'Reference', 'Type', 'Amount', 'Status']
    ]);
    exit;
}

$pageTitle = "Welfare Hub";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --hop-dark: #0F2E25;
            --hop-lime: #D0F35D;
            --hop-lime-rgb: 208, 243, 93;
            --hop-bg: #F8F9FA;
            --hop-card-bg: #FFFFFF;
            --hop-text: #1F2937;
            --hop-border: #EDEFF2;
            --accent-rose: #f43f5e;
            --accent-rose-rgb: 244, 63, 94;
            --card-radius: 24px;
        }

        [data-bs-theme="dark"] {
            --hop-bg: #0b1210;
            --hop-card-bg: #1F2937;
            --hop-text: #F9FAFB;
            --hop-border: #374151;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--hop-bg);
            color: var(--hop-text);
        }

        .main-content-wrapper {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; } }

        .hope-card {
            background: var(--hop-card-bg);
            border-radius: var(--card-radius);
            border: 1px solid var(--hop-border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s ease;
        }
        .hope-card:hover { transform: translateY(-3px); }

        .decoration-circle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.05;
            pointer-events: none;
        }

        .nav-pills-modern .nav-link {
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            color: var(--hop-text);
            transition: all 0.2s;
        }
        .nav-pills-modern .nav-link.active {
            background-color: var(--hop-dark);
            color: white;
        }

        .search-box {
            position: relative;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
        }
        .search-box input {
            padding-left: 40px;
            border-radius: 50px;
            border: 1px solid var(--hop-border);
        }

        .table-modern thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9CA3AF;
            background-color: #f8fafc;
            border-bottom: 2px solid var(--hop-border);
            padding: 1rem;
            font-weight: 700;
        }
        .table-modern tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--hop-border);
        }
        .table-modern tbody tr:last-child td { border-bottom: none; }
    </style>
</head>
<body class="member-body">

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content-wrapper d-flex flex-column min-vh-100">
        
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <div class="container-fluid flex-grow-1 py-5 px-4">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Welfare Hub</h2>
                    <p class="text-secondary mb-0">Unified management for your benevolences and community support.</p>
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary d-flex align-items-center gap-2 rounded-pill px-3 fw-bold border-2 dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download"></i> <span>Export</span>
                        </button>
                        <ul class="dropdown-menu shadow">
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export PDF</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel</a></li>
                            <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print Statement</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-outline-success d-flex align-items-center gap-2 rounded-pill px-3 fw-bold border-2" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                        <i class="bi bi-plus-circle"></i> <span>Report Case</span>
                    </button>
                    <a href="<?= BASE_URL ?>/member/pages/mpesa_request.php?type=welfare" class="btn btn-success d-flex align-items-center gap-2 rounded-pill px-4 fw-bold text-dark border-0 shadow-sm" style="background-color: var(--hop-lime);">
                        <i class="bi bi-heart-fill"></i> <span>Contribute</span>
                    </a>
                </div>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="hope-card p-4 h-100 position-relative" style="background: linear-gradient(135deg, var(--hop-dark) 0%, #1a4d40 100%); color: white;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center bg-white bg-opacity-10">
                                <i class="bi bi-shield-lock-fill fs-4 text-lime" style="color: var(--hop-lime) !important;"></i>
                            </div>
                            <span class="text-white-50 text-uppercase fw-bold small">Global Welfare Pool</span>
                        </div>
                        <h3 class="fw-bold mb-0 text-white">KES <?= number_format((float)$welfare_pool, 2) ?></h3>
                        <p class="small text-white-50 mb-0 mt-2">Total Solidarity Funds</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="hope-card p-4 h-100 position-relative">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(var(--hop-lime-rgb), 0.2); color: #4d7c0f;">
                                <i class="bi bi-heart-fill fs-4"></i>
                            </div>
                            <span class="text-secondary text-uppercase fw-bold small">My Lifetime Contributions</span>
                        </div>
                        <h3 class="fw-bold mb-0">KES <?= number_format((float)$total_given, 2) ?></h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="hope-card p-4 h-100 position-relative">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(var(--accent-rose-rgb), 0.1); color: var(--accent-rose);">
                                <i class="bi bi-arrow-down-left fs-4"></i>
                            </div>
                            <span class="text-secondary text-uppercase fw-bold small">Support Received</span>
                        </div>
                        <h3 class="fw-bold mb-0">KES <?= number_format((float)$total_received, 2) ?></h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="hope-card p-4 h-100 position-relative" style="background: linear-gradient(145deg, var(--hop-card-bg) 0%, rgba(var(--hop-lime-rgb), 0.1) 100%);">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center bg-white text-dark shadow-sm">
                                <i class="bi bi-scale fs-4"></i>
                            </div>
                            <span class="text-secondary text-uppercase fw-bold small">Net Impact</span>
                        </div>
                        <h3 class="fw-bold mb-1 <?= $standing_status === 'contributor' ? 'text-success' : 'text-danger' ?>">
                            KES <?= ($net_standing > 0 ? '+' : '') . number_format((float)$net_standing, 2) ?>
                        </h3>
                        <p class="small text-secondary mb-0">
                            Community <span class="fw-bold"><?= ucfirst($standing_status) ?></span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-lg-8">
                    <div class="hope-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Contribution Trend</h5>
                            <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">Lifetime</span>
                        </div>
                        <div style="height: 300px; position: relative;">
                            <canvas id="welfareChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
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

            <div class="hope-card p-0">
                <div class="p-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <ul class="nav nav-pills nav-pills-modern" id="pills-tab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link <?= !isset($_GET['tab']) || $_GET['tab'] === 'contributions' ? 'active' : '' ?>" id="pills-given-tab" data-bs-toggle="pill" data-bs-target="#pills-given" type="button">
                                Contributions
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'community' ? 'active' : '' ?>" id="pills-community-tab" data-bs-toggle="pill" data-bs-target="#pills-community" type="button">
                                Community Situations
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="pills-received-tab" data-bs-toggle="pill" data-bs-target="#pills-received" type="button">
                                Support Received
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="pills-cases-tab" data-bs-toggle="pill" data-bs-target="#pills-cases" type="button">
                                My Cases
                            </button>
                        </li>
                    </ul>
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="tableSearch" class="form-control" placeholder="Search records...">
                    </div>
                </div>

                <div class="tab-content" id="pills-tabContent">
                    
                    <div class="tab-pane fade <?= !isset($_GET['tab']) || $_GET['tab'] === 'contributions' ? 'show active' : '' ?>" id="pills-given">
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
                                        $status = $row['status'] ?? 'completed';
                                        $color = match(strtolower($status)) { 
                                            'active'=>'success', 
                                            'completed'=>'success', 
                                            'pending'=>'warning', 
                                            default=>'secondary' 
                                        };
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-medium"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark">Welfare Contribution</span>
                                                <span class="small text-muted font-monospace"><?= esc($row['reference_no'] ?? 'N/A') ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle rounded-pill px-3"><?= ucfirst($status) ?></span></td>
                                        <td class="text-end pe-4 fw-bold text-success">+ KES <?= number_format((float)$row['amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'community' ? 'show active' : '' ?>" id="pills-community">
                        <div class="p-4">
                            <div class="row g-4">
                                <?php if (empty($community_cases)): ?>
                                    <div class="col-12">
                                        <div class="text-center py-5 bg-light rounded-4 border border-dashed">
                                            <i class="bi bi-emoji-smile text-muted fs-1 mb-2 d-block"></i>
                                            <p class="text-muted">No active community situations. The Sacco is doing well!</p>
                                        </div>
                                    </div>
                                <?php else: foreach ($community_cases as $crow): 
                                    $target = (float)$crow['target_amount'];
                                    $raised = (float)$crow['total_raised'];
                                    $percent = ($target > 0) ? ($raised / $target) * 100 : 0;
                                    $daysAgo = time_elapsed_string($crow['created_at']);
                                ?>
                                <div class="col-md-6 col-xxl-4">
                                    <div class="hope-card shadow-none border" style="height: auto;">
                                        <div class="p-3" style="background: linear-gradient(135deg, var(--hop-dark) 0%, #1a4d40 100%); color: white; border-radius: 20px 20px 0 0;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small fw-bold opacity-75 text-uppercase">Case #<?= $crow['case_id'] ?></span>
                                                <span class="badge bg-white text-dark rounded-pill"><?= ucfirst($crow['status']) ?></span>
                                            </div>
                                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($crow['title']) ?></h6>
                                        </div>
                                        <div class="p-3">
                                            <p class="small text-muted mb-3 line-clamp-2"><?= htmlspecialchars($crow['description']) ?></p>
                                            
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span class="fw-bold">KES <?= number_format($raised) ?></span>
                                                    <span class="text-muted">Target: <?= number_format($target) ?></span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar" style="width: <?= min(100, $percent) ?>%; background: var(--hop-lime);"></div>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><i class="bi bi-people me-1"></i> <?= $crow['donor_count'] ?> Donors</small>
                                                <a href="mpesa_request.php?type=welfare_case&case_id=<?= $crow['case_id'] ?>" class="btn btn-sm btn-success text-dark fw-bold rounded-pill px-3" style="background: var(--hop-lime); border:0;">
                                                    Support
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
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
                                         $color = match($status) { 
                                             'approved'=>'success', 
                                             'disbursed'=>'success', 
                                             'rejected'=>'danger', 
                                             default=>'secondary' 
                                         };
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-medium"><?= date('M d, Y', strtotime($row['date_granted'])) ?></td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['reason'] ?? 'Welfare Support') ?></span>
                                                <span class="small text-muted">Approved by SACCO Admin</span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle rounded-pill px-3"><?= ucfirst($status) ?></span></td>
                                        <td class="text-end pe-4 fw-bold text-danger">- KES <?= number_format((float)$row['amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pills-cases">
                         <div class="table-responsive">
                            <table class="table table-modern table-hover mb-0" id="tableCases">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Created Date</th>
                                        <th>Title / Description</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Approved Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($member_cases)): ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">No welfare cases associated with your account.</td></tr>
                                    <?php else: foreach($member_cases as $row): 
                                         $status = strtolower($row['status']);
                                         $color = match($status) { 
                                             'active'=>'primary',
                                             'approved'=>'success', 
                                             'disbursed'=>'success', 
                                             'funded'=>'success',
                                             'closed'=>'secondary',
                                             'pending'=>'warning',
                                             default=>'secondary' 
                                         };
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-medium"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['title']) ?></span>
                                                <span class="small text-muted text-truncate" style="max-width: 300px;"><?= htmlspecialchars($row['description']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle rounded-pill px-3"><?= ucfirst($status) ?></span></td>
                                        <td class="text-end pe-4 fw-bold text-dark">KES <?= number_format((float)$row['approved_amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<!-- Report Case Modal -->
<div class="modal fade" id="newCaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Report Welfare Situation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Situation Title</label>
                    <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Hospitalization, Bereavement..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Requested Amount</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 fw-bold text-success rounded-start-3">KES</span>
                        <input type="number" name="requested_amount" class="form-control border-start-0 ps-1 rounded-end-3" placeholder="0.00" required>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-uppercase text-muted">Detailed Description</label>
                    <textarea name="description" class="form-control rounded-3" rows="4" placeholder="Please provide details about the situation..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="submit_case" class="btn btn-success rounded-pill px-4 fw-bold text-dark border-0" style="background-color: var(--hop-lime);">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Initialize Chart
    const ctx = document.getElementById('welfareChart').getContext('2d');
    
    const labels = <?= $json_labels ?>;
    const data = <?= $json_values ?>;
    
    // Create gradient
    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(208, 243, 93, 0.4)');
    gradient.addColorStop(1, 'rgba(208, 243, 93, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Contributions (KES)',
                data: data,
                borderColor: '#65a30d',
                backgroundColor: gradient,
                borderWidth: 2,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#65a30d',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0F2E25',
                    titleColor: '#D0F35D',
                    bodyColor: '#fff',
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'KES ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#9CA3AF', font: { family: "'Plus Jakarta Sans', sans-serif" } }
                },
                y: {
                    grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                    ticks: { color: '#9CA3AF', font: { family: "'Plus Jakarta Sans', sans-serif" } },
                    beginAtZero: true
                }
            }
        }
    });

    // Table Search
    document.getElementById('tableSearch')?.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const activeTab = document.querySelector('.tab-pane.active');
        const rows = activeTab.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
</script>
</body>
</html>




