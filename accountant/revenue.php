<?php
// accountant/revenue.php
// Track Investment Income (Matatus, Rentals, etc.)
// UI: Forest Green & Lime (Hope UI)

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

// 1. Auth
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['accountant', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}
// require_permission('revenue_tracking'); // Uncomment if permissions are active

// 2. Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // verify_csrf_token(); // Uncomment if CSRF function exists
    
    $entry_type = $_POST['entry_type'] ?? 'income'; // 'income' or 'expense'
    $source_type = $_POST['source_type'] ?? 'investment';
    $amount = floatval($_POST['amount']);
    $date = $_POST['income_date'];
    $desc = trim($_POST['description']);
    $ref = trim($_POST['ref_no']);
    
    if ($amount > 0) {
        $conn->begin_transaction();
        try {
            $related_id = 0;
            $source_label = "";
            $table_context = 'unknown'; // For transactions table related_table enum

            if ($source_type === 'investment') {
                $investment_id = intval($_POST['investment_id']);
                $stmt = $conn->prepare("SELECT title FROM investments WHERE investment_id = ?");
                $stmt->bind_param("i", $investment_id);
                $stmt->execute();
                $asset = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$asset) throw new Exception("Invalid Asset Selected.");
                
                $related_id = $investment_id;
                $source_label = $asset['title'];
                // Note: Ensure 'investments' is added to your transactions related_table ENUM if you want specific tracking
                $table_context = 'investments'; 

                if ($entry_type === 'income') {
                    $stmt = $conn->prepare("INSERT INTO investment_income (investment_id, amount, income_date, description, recorded_by) VALUES (?, ?, ?, ?, ?)");
                } else {
                    $stmt = $conn->prepare("INSERT INTO investment_expenses (investment_id, amount, expense_date, description, recorded_by) VALUES (?, ?, ?, ?, ?)");
                }
            } else {
                $vehicle_id = intval($_POST['vehicle_id']);
                $stmt = $conn->prepare("SELECT reg_no FROM vehicles WHERE vehicle_id = ?");
                $stmt->bind_param("i", $vehicle_id);
                $stmt->execute();
                $v = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$v) throw new Exception("Invalid Vehicle Selected.");

                $related_id = $vehicle_id;
                $source_label = $v['reg_no'];
                $table_context = 'vehicles'; // Mapping to DB enum

                if ($entry_type === 'income') {
                    $stmt = $conn->prepare("INSERT INTO vehicle_income (vehicle_id, amount, income_date, description, recorded_by) VALUES (?, ?, ?, ?, ?)");
                } else {
                    $stmt = $conn->prepare("INSERT INTO vehicle_expenses (vehicle_id, amount, expense_date, description, recorded_by) VALUES (?, ?, ?, ?, ?)");
                }
            }

            $stmt->bind_param("idssi", $related_id, $amount, $date, $desc, $_SESSION['admin_id']);
            $stmt->execute();
            $stmt->close();
            
            // Central Ledger (Transactions Table)
            // Note: We set member_id to 0 for system/operational transactions
            $tran_type = ($entry_type === 'income') ? 'income' : 'expense'; // Ensure enum matches db
            // Map to existing transaction types if strictly enforced, otherwise use generic strings
            
            $notes = ucfirst($entry_type) . " - $source_label: $desc";
            $timestamp = $date . ' ' . date('H:i:s');
            
            // We use member_id = NULL (System) since this is operational revenue, not member-specific
            $stmt = $conn->prepare("INSERT INTO transactions (member_id, transaction_type, amount, related_id, related_table, reference_no, notes, created_at, payment_channel, recorded_by) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 'cash', ?)");
            $stmt->bind_param("sdissssi", $tran_type, $amount, $related_id, $table_context, $ref, $notes, $timestamp, $_SESSION['admin_id']);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['flash_msg'] = ucfirst($entry_type) . " recorded successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: revenue.php");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_msg'] = "Error: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        $_SESSION['flash_msg'] = "Please enter a valid amount.";
        $_SESSION['flash_type'] = "warning";
    }
}

// 3. Fetch Data for Dropdowns & Lists
$assets = $conn->query("SELECT investment_id, title, category, target_amount, target_period FROM investments WHERE status = 'active' AND category != 'vehicle_fleet' ORDER BY title ASC");
$vehicles = $conn->query("SELECT investment_id, reg_no, model, target_amount, target_period FROM investments WHERE status = 'active' AND category = 'vehicle_fleet' ORDER BY reg_no ASC");

// 4. Handle Duration Filtering
$duration = $_GET['duration'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$date_filter_inc = "";
$date_filter_exp = "";
$activity_filter = "";
$params = [];
$types = "";

if ($duration !== 'all') {
    switch ($duration) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'weekly':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
            break;
        case 'monthly':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
            break;
        case '3months':
            $start_date = date('Y-m-d', strtotime('-3 months'));
            $end_date = date('Y-m-d');
            break;
        case 'custom':
            // keep start_date and end_date as provided
            break;
    }

    if ($start_date && $end_date) {
        $date_filter_inc = " AND income_date BETWEEN '$start_date' AND '$end_date'";
        $date_filter_exp = " AND expense_date BETWEEN '$start_date' AND '$end_date'";
        $activity_filter = " WHERE date BETWEEN '$start_date' AND '$end_date'";
    }
}

// 5. KPI Stats (Filtered)
$stats = $conn->query("
    SELECT 
        (SELECT COALESCE(SUM(amount), 0) FROM investment_income WHERE 1=1 $date_filter_inc) + (SELECT COALESCE(SUM(amount), 0) FROM vehicle_income WHERE 1=1 $date_filter_inc) as total_rev,
        (SELECT COALESCE(SUM(amount), 0) FROM investment_expenses WHERE 1=1 $date_filter_exp) + (SELECT COALESCE(SUM(amount), 0) FROM vehicle_expenses WHERE 1=1 $date_filter_exp) as total_exp
")->fetch_assoc();

$net_profit = $stats['total_rev'] - $stats['total_exp'];

// 6. Recent History (Filtered)
$recentQuery = "
    SELECT * FROM (
        (SELECT i_inc.income_date as date, i.title as source, 'Income' as type, i_inc.amount, 'investment' as cat, i_inc.description
         FROM investment_income i_inc JOIN investments i ON i_inc.investment_id = i.investment_id)
        UNION ALL
        (SELECT v_inc.income_date as date, i.reg_no as source, 'Income' as type, v_inc.amount, 'fleet' as cat, v_inc.description
         FROM vehicle_income v_inc JOIN investments i ON v_inc.vehicle_id = i.investment_id)
        UNION ALL
        (SELECT i_exp.expense_date as date, i.title as source, 'Expense' as type, i_exp.amount, 'investment' as cat, i_exp.description
         FROM investment_expenses i_exp JOIN investments i ON i_exp.investment_id = i.investment_id)
        UNION ALL
        (SELECT v_exp.expense_date as date, i.reg_no as source, 'Expense' as type, v_exp.amount, 'fleet' as cat, v_exp.description
         FROM vehicle_expenses v_exp JOIN investments i ON v_exp.vehicle_id = i.investment_id)
    ) as combined
    $activity_filter
    ORDER BY date DESC LIMIT 20
";
$recent = $conn->query($recentQuery);

// 7. Chart Data (Monthly Last 6 Months) - Kept as trend overview
$chartQuery = "
    SELECT DATE_FORMAT(date, '%Y-%m') as m, SUM(income) as inc, SUM(expense) as exp FROM (
        SELECT income_date as date, amount as income, 0 as expense FROM investment_income
        UNION ALL SELECT income_date, amount, 0 FROM vehicle_income
        UNION ALL SELECT expense_date, 0, amount FROM investment_expenses
        UNION ALL SELECT expense_date, 0, amount FROM vehicle_expenses
    ) as combined 
    WHERE date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY m ORDER BY m ASC
";
$chartRes = $conn->query($chartQuery);
$chartLabels = [];
$chartInc = [];
$chartExp = [];
while($c = $chartRes->fetch_assoc()){
    $chartLabels[] = date('M Y', strtotime($c['m'].'-01'));
    $chartInc[] = $c['inc'];
    $chartExp[] = $c['exp'];
}

$pageTitle = "Revenue Tracking";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            --hop-dark: #0F2E25;
            --hop-lime: #D0F35D;
            --hop-lime-hover: #bce045;
            --hop-bg: #F8F9FA;
            --hop-card-bg: #FFFFFF;
            --hop-text: #1F2937;
            --hop-border: #EDEFF2;
            --card-radius: 24px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--hop-bg); color: var(--hop-text); }
        
        #wrapper { display: flex; width: 100%; overflow-x: hidden; }
        .main-content-wrapper { flex-grow: 1; min-height: 100vh; transition: all 0.3s ease; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0 !important; } }

        .hope-card {
            background: var(--hop-card-bg); border-radius: var(--card-radius);
            border: 1px solid var(--hop-border); box-shadow: 0 10px 40px rgba(0,0,0,0.03);
            overflow: hidden; transition: transform 0.2s;
        }
        .hope-card:hover { transform: translateY(-3px); }
        
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        
        .form-label { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
        .form-control, .form-select { border-radius: 10px; padding: 0.7rem 1rem; border: 1px solid var(--hop-border); }
        .form-control:focus { border-color: var(--hop-dark); box-shadow: 0 0 0 4px rgba(15, 46, 37, 0.1); }
        
        .table-custom th { background: #f9fafb; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; padding: 1rem; }
        .table-custom td { padding: 1rem; vertical-align: middle; font-size: 0.9rem; }
        
        .btn-check:checked + .btn-outline-custom { background-color: var(--hop-dark); color: var(--hop-lime); border-color: var(--hop-dark); }
        .btn-outline-custom { color: var(--hop-text); border-color: var(--hop-border); font-weight: 600; }
        .btn-outline-custom:hover { background-color: #f3f4f6; }

        /* Quick Select Styling */
        .asset-card-mini {
            border: 1px solid var(--hop-border);
            border-radius: 16px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }
        .asset-card-mini:hover {
            border-color: var(--hop-dark);
            background: #f0fdf4;
            transform: translateY(-2px);
        }
        .asset-icon-sm {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        /* Tabs & Search */
        .nav-tabs-custom .nav-link {
            border: none;
            color: var(--hop-text);
            font-weight: 600;
            padding: 10px 20px;
            border-bottom: 2px solid transparent;
        }
        .nav-tabs-custom .nav-link.active {
            color: var(--hop-dark);
            border-bottom-color: var(--hop-dark);
            background: transparent;
        }
        .search-box-mini {
            background: #f3f4f6;
            border: none;
            border-radius: 12px;
            padding: 8px 15px;
            font-size: 0.85rem;
            width: 200px;
        }

        /* Pulse Highlight */
        @keyframes pulse-highlight {
            0% { box-shadow: 0 0 0 0 rgba(15, 46, 37, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(15, 46, 37, 0); }
            100% { box-shadow: 0 0 0 0 rgba(15, 46, 37, 0); }
        }
        .pulse-input {
            animation: pulse-highlight 1.5s infinite;
        }

        .clickable-source {
            cursor: pointer;
            text-decoration: underline dashed transparent;
            transition: all 0.2s;
        }
        .clickable-source:hover {
            text-decoration-color: var(--hop-dark);
            color: var(--hop-dark) !important;
        }
    </style>
</head>
<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeInDown">
                <div>
                    <h2 class="fw-bold mb-1 text-dark">Revenue Tracking</h2>
                    <p class="text-secondary mb-0">Monitor assets, fleet income, and operational expenses.</p>
                </div>
                <div>
                    <button class="btn btn-outline-dark rounded-pill px-4 d-none d-md-block" onclick="window.print()">
                        <i class="bi bi-printer me-2"></i> Report
                    </button>
                </div>
            </div>

            <?php if(isset($_SESSION['flash_msg'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_type'] ?> border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center animate__animated animate__shakeX">
                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                    <?= $_SESSION['flash_msg'] ?>
                    <?php unset($_SESSION['flash_msg'], $_SESSION['flash_type']); ?>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4 animate__animated animate__fadeInUp">
                <!-- Duration Filter Toolbar -->
                <div class="col-12">
                    <div class="hope-card p-3">
                        <form method="GET" class="row g-3 align-items-center" id="filterForm">
                            <div class="col-auto">
                                <label class="form-label mb-0 me-2">Duration:</label>
                                <select name="duration" class="form-select form-select-sm d-inline-block w-auto" onchange="toggleDateInputs(this.value)">
                                    <option value="all" <?= $duration === 'all' ? 'selected' : '' ?>>All Time</option>
                                    <option value="today" <?= $duration === 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="weekly" <?= $duration === 'weekly' ? 'selected' : '' ?>>Last 7 Days</option>
                                    <option value="monthly" <?= $duration === 'monthly' ? 'selected' : '' ?>>This Month</option>
                                    <option value="3months" <?= $duration === '3months' ? 'selected' : '' ?>>Last 3 Months</option>
                                    <option value="custom" <?= $duration === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-auto d-flex gap-2 <?= $duration !== 'custom' ? 'd-none' : '' ?>" id="customDateRange">
                                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>" placeholder="Start">
                                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>" placeholder="End">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3">
                                    <i class="bi bi-filter me-1"></i> Apply
                                </button>
                                <?php if($duration !== 'all'): ?>
                                    <a href="revenue.php" class="btn btn-sm btn-light rounded-pill px-3">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-9">
                    <div class="hope-card p-4 h-100">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                            <ul class="nav nav-tabs nav-tabs-custom border-0" id="assetTabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-inv" type="button">Investments</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-fleet" type="button">Vehicle Fleet</button>
                                </li>
                            </ul>
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.8rem;"></i>
                                <input type="text" class="search-box-mini ps-5" placeholder="Search assets..." onkeyup="filterAssets(this.value)">
                            </div>
                        </div>

                        <div class="tab-content">
                            <!-- Investments Tab -->
                            <div class="tab-pane fade show active" id="tab-inv">
                                <div class="row g-3" id="invGrid">
                                    <?php 
                                    $assets_list = $conn->query("SELECT investment_id, title, category FROM investments WHERE status = 'active' ORDER BY title ASC");
                                    while($a = $assets_list->fetch_assoc()): 
                                        $icon = match($a['category']) {
                                            'farm' => 'bi-flower1',
                                            'vehicle_fleet' => 'bi-truck-front',
                                            'petrol_station' => 'bi-fuel-pump',
                                            'apartments' => 'bi-building',
                                            'land' => 'bi-geo-alt',
                                            default => 'bi-briefcase'
                                        };
                                        $safe_id = json_encode($a['investment_id']);
                                    ?>
                                        <div class="col-md-3 asset-item" data-title="<?= strtolower($a['title']) ?>">
                                            <div class="asset-card-mini p-2">
                                                <div class="d-flex align-items-center gap-2 mb-2" onclick="prefillForm('investment', <?= $safe_id ?>, 'income')">
                                                    <div class="asset-icon-sm bg-success bg-opacity-10 text-success">
                                                        <i class="bi <?= $icon ?>"></i>
                                                    </div>
                                                    <div class="text-truncate">
                                                        <small class="fw-bold d-block text-truncate"><?= htmlspecialchars($a['title']) ?></small>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <button class="btn btn-sm btn-light flex-grow-1 py-0 text-success" onclick="prefillForm('investment', <?= $safe_id ?>, 'income')" title="Add Income">
                                                        <i class="bi bi-plus-circle"></i> <small>Inc</small>
                                                    </button>
                                                    <button class="btn btn-sm btn-light flex-grow-1 py-0 text-danger" onclick="prefillForm('investment', <?= $safe_id ?>, 'expense')" title="Add Expense">
                                                        <i class="bi bi-dash-circle"></i> <small>Exp</small>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <!-- Fleet Tab -->
                            <div class="tab-pane fade" id="tab-fleet">
                                <div class="row g-3" id="fleetGrid">
                                    <?php 
                                    mysqli_data_seek($vehicles, 0); 
                                    while($v = $vehicles->fetch_assoc()): 
                                        $safe_id = json_encode($v['investment_id']);
                                    ?>
                                        <div class="col-md-3 asset-item" data-title="<?= strtolower($v['reg_no']) ?> <?= strtolower($v['model']) ?>">
                                            <div class="asset-card-mini p-2">
                                                <div class="d-flex align-items-center gap-2 mb-2" onclick="prefillForm('fleet', <?= $safe_id ?>, 'income')">
                                                    <div class="asset-icon-sm bg-primary bg-opacity-10 text-primary">
                                                        <i class="bi bi-bus-front"></i>
                                                    </div>
                                                    <div class="text-truncate">
                                                        <small class="fw-bold d-block text-truncate"><?= htmlspecialchars($v['reg_no']) ?></small>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <button class="btn btn-sm btn-light flex-grow-1 py-0 text-success" onclick="prefillForm('fleet', <?= $safe_id ?>, 'income')" title="Add Income">
                                                        <i class="bi bi-plus-circle"></i> <small>Inc</small>
                                                    </button>
                                                    <button class="btn btn-sm btn-light flex-grow-1 py-0 text-danger" onclick="prefillForm('fleet', <?= $safe_id ?>, 'expense')" title="Add Expense">
                                                        <i class="bi bi-dash-circle"></i> <small>Exp</small>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="hope-card p-4" style="background: linear-gradient(135deg, #0F2E25 0%, #164e3f 100%); color: white; height: 100%;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-white-50 small fw-bold text-uppercase mb-1">Net Profit</p>
                                <h3 class="fw-bold text-white mb-0">KES <?= number_format($net_profit) ?></h3>
                            </div>
                            <div class="stat-icon bg-white bg-opacity-25 text-white">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4 animate__animated animate__fadeInUp">
                <div class="col-md-6">
                    <div class="hope-card p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-secondary small fw-bold text-uppercase mb-1">Total Revenue</p>
                                <h3 class="fw-bold text-dark mb-0">KES <?= number_format($stats['total_rev']) ?></h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="hope-card p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-secondary small fw-bold text-uppercase mb-1">Total Expenses</p>
                                <h3 class="fw-bold text-dark mb-0">KES <?= number_format($stats['total_exp']) ?></h3>
                            </div>
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-graph-down-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <div class="col-lg-4 animate__animated animate__fadeInLeft" style="animation-delay: 0.1s;">
                    <div class="hope-card h-100">
                        <div class="p-4 border-bottom bg-light">
                            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-pencil-square me-2"></i>Record Entry</h6>
                        </div>
                        <div class="p-4">
                            <form method="POST" id="revenueForm">
                                <?= defined('csrf_field') ? csrf_field() : '' ?> <div class="mb-4">
                                    <label class="form-label">Transaction Type</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="entry_type" id="typeInc" value="income" checked onchange="updateUI('income')">
                                        <label class="btn btn-outline-custom py-2" for="typeInc">Revenue (+)</label>
                                        
                                        <input type="radio" class="btn-check" name="entry_type" id="typeExp" value="expense" onchange="updateUI('expense')">
                                        <label class="btn btn-outline-custom py-2" for="typeExp">Expense (-)</label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Source Category</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="source_type" id="srcInv" value="investment" checked onchange="toggleSource('investment')">
                                        <label class="btn btn-outline-custom py-2" for="srcInv">Investment</label>
                                        
                                        <input type="radio" class="btn-check" name="source_type" id="srcFleet" value="fleet" onchange="toggleSource('fleet')">
                                        <label class="btn btn-outline-custom py-2" for="srcFleet">Vehicle Fleet</label>
                                    </div>
                                </div>

                                         <div class="mb-3" id="group_inv">
                                    <label class="form-label">Select Asset</label>
                                    <select name="investment_id" class="form-select bg-light">
                                        <option value="">-- Choose Asset --</option>
                                        <?php 
                                        mysqli_data_seek($assets, 0); // Restore pointer
                                        while($a = $assets->fetch_assoc()): ?>
                                            <option value="<?= $a['investment_id'] ?>"><?= htmlspecialchars($a['title']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3 d-none" id="group_fleet">
                                    <label class="form-label">Select Vehicle</label>
                                    <select name="vehicle_id" class="form-select bg-light">
                                        <option value="">-- Choose Vehicle --</option>
                                        <?php 
                                        mysqli_data_seek($vehicles, 0); // Restore pointer
                                        while($v = $vehicles->fetch_assoc()): ?>
                                            <option value="<?= $v['investment_id'] ?>"><?= htmlspecialchars($v['reg_no']) ?> - <?= htmlspecialchars($v['model']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0 bg-white text-muted">KES</span>
                                            <input type="number" name="amount" class="form-control border-start-0 ps-0 fw-bold" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Date</label>
                                        <input type="date" name="income_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Reference No.</label>
                                    <input type="text" name="ref_no" class="form-control" placeholder="Receipt / Invoice #">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Description / Notes</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Brief details..."></textarea>
                                </div>

                                <button type="submit" id="submitBtn" class="btn btn-success w-100 py-3 rounded-3 fw-bold" style="background-color: var(--hop-dark); border:none;">
                                    <i class="bi bi-save me-2"></i> Record Income
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 animate__animated animate__fadeInRight" style="animation-delay: 0.2s;">
                    
                    <div class="hope-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Financial Overview (6 Months)</h6>
                            <span class="badge bg-light text-dark border">Trends</span>
                        </div>
                        <div style="height: 250px;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <div class="hope-card overflow-hidden">
                        <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0">Recent Activity</h6>
                            <a href="#" class="small text-decoration-none fw-bold" style="color: var(--hop-dark);">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Source</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="text-end pe-4">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent->num_rows === 0): ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted">No records found.</td></tr>
                                    <?php else: while ($row = $recent->fetch_assoc()): 
                                        $isInc = $row['type'] === 'Income';
                                        $badgeClass = $isInc ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                                        $icon = $row['cat'] === 'fleet' ? 'bi-bus-front' : 'bi-building';
                                    ?>
                                    <tr>
                                         <td class="ps-4 text-nowrap text-secondary"><?= date('d M, Y', strtotime($row['date'])) ?></td>
                                         <td>
                                            <div class="d-flex align-items-center gap-2 clickable-source" 
                                                 onclick='prefillForm(<?= json_encode($row['cat']) ?>, <?= json_encode($row['source']) ?>, <?= json_encode(strtolower($row['type'])) ?>)'>
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-muted" style="width: 32px; height: 32px;">
                                                    <i class="bi <?= $icon ?>"></i>
                                                </div>
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['source']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge rounded-pill <?= $badgeClass ?> border border-opacity-10 px-3"><?= $row['type'] ?></span></td>
                                        <td class="text-muted small text-truncate" style="max-width: 150px;"><?= htmlspecialchars($row['description']) ?></td>
                                        <td class="text-end pe-4 fw-bold <?= $isInc ? 'text-success' : 'text-danger' ?>">
                                            <?= $isInc ? '+' : '-' ?> <?= number_format($row['amount']) ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

        </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // 1. Chart Initialization
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?= json_encode($chartInc) ?>,
                    backgroundColor: '#0F2E25', // Forest Green
                    borderRadius: 4
                },
                {
                    label: 'Expense',
                    data: <?= json_encode($chartExp) ?>,
                    backgroundColor: '#ef4444', // Red
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } }
            },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. UI Toggles
    function updateUI(type) {
        const btn = document.getElementById('submitBtn');
        if(type === 'income') {
            btn.innerHTML = '<i class="bi bi-save me-2"></i> Record Income';
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-success');
            btn.style.backgroundColor = 'var(--hop-dark)';
        } else {
            btn.innerHTML = '<i class="bi bi-dash-circle me-2"></i> Record Expense';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-danger');
            btn.style.backgroundColor = '#ef4444';
        }
    }

    function toggleSource(source) {
        if(source === 'investment') {
            document.getElementById('group_inv').classList.remove('d-none');
            document.getElementById('group_fleet').classList.add('d-none');
        } else {
            document.getElementById('group_inv').classList.add('d-none');
            document.getElementById('group_fleet').classList.remove('d-none');
        }
    }

    function prefillForm(type, id, entryMode = 'income') {
        // 1. Set Type (Income / Expense)
        if(entryMode === 'income') {
            document.getElementById('typeInc').checked = true;
            updateUI('income');
        } else {
            document.getElementById('typeExp').checked = true;
            updateUI('expense');
        }

        const amountInput = document.querySelector('input[name="amount"]');
        
        // 2. Set Source Category
        if(type === 'investment' || type === 'asset') {
            document.getElementById('srcInv').checked = true;
            toggleSource('investment');
            
            const sel = document.querySelector('select[name="investment_id"]');
            if(!sel) return;
            
            if(isNaN(id)) {
                // Find by text
                Array.from(sel.options).forEach(opt => {
                    if(opt.text.trim() === id.trim()) sel.value = opt.value;
                });
            } else {
                sel.value = id;
            }
        } else {
            document.getElementById('srcFleet').checked = true;
            toggleSource('fleet');
            
            const sel = document.querySelector('select[name="vehicle_id"]');
            if(!sel) return;

            if(isNaN(id)) {
                Array.from(sel.options).forEach(opt => {
                    if(opt.text.includes(id)) sel.value = opt.value;
                });
            } else {
                sel.value = id;
            }
        }

        // 3. Visual Feedback
        if(amountInput) {
            amountInput.classList.add('pulse-input');
            setTimeout(() => amountInput.classList.remove('pulse-input'), 3000);
            
            // 4. Interaction
            document.getElementById('revenueForm').scrollIntoView({ behavior: 'smooth' });
            setTimeout(() => amountInput.focus(), 500);
        }
    }

    function filterAssets(q) {
        q = q.toLowerCase();
        document.querySelectorAll('.asset-item').forEach(item => {
            const title = item.getAttribute('data-title');
            item.style.display = title.includes(q) ? 'block' : 'none';
        });
    }

    function toggleDateInputs(val) {
        const range = document.getElementById('customDateRange');
        if(val === 'custom') {
            range.classList.remove('d-none');
        } else {
            range.classList.add('d-none');
            // Optionally auto-submit if not custom
            document.getElementById('filterForm').submit();
        }
    }
</script>

    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
