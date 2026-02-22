<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/functions.php';

$pageTitle = "Investment Management";

require_permission();
Auth::requireAdmin();
$layout = LayoutManager::create('admin');
?>
<?php $layout->header($pageTitle); ?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }

        /* Page-specific overrides */
        .asset-card { 
            background: white; border-radius: 24px; padding: 1.5rem; 
            border: 1px solid var(--glass-border); transition: 0.3s;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }
        .asset-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .asset-icon-box { 
            width: 50px; height: 50px; border-radius: 16px; 
            background: rgba(15, 46, 37, 0.05); color: var(--forest);
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        }
        .stat-card-dark { background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%); color: white; }
        .stat-card-accent { background: var(--lime); color: var(--forest); }
    </style>

<?php
$admin_id = $_SESSION['admin_id'];

// 1. HANDLE POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    // A. ADD NEW INVESTMENT
    if (isset($_POST['action']) && $_POST['action'] === 'add_asset') {
        $title    = trim($_POST['title']);
        $category = $_POST['category'];
        $cost     = floatval($_POST['purchase_cost']);
        $value    = floatval($_POST['current_value']);
        $date     = $_POST['purchase_date'];
        $desc     = trim($_POST['description']);
        
        $reg_no   = trim($_POST['reg_no'] ?? '');
        $model    = trim($_POST['model'] ?? '');
        $route    = trim($_POST['assigned_route'] ?? '');
        $target   = floatval($_POST['target_amount'] ?? 0);
        $period   = $_POST['target_period'] ?? 'monthly';
        $target_start = $_POST['target_start_date'] ?? date('Y-m-d');

        // Validate targets are provided
        if ($target <= 0) {
            flash_set("Revenue target is required for all investments.", 'error');
        } elseif (empty($title) || $cost <= 0) {
            flash_set("Title and valid cost are required.", 'error');
        } else {
            // Check for duplicate reg_no
            if (!empty($reg_no)) {
                $check_stmt = $conn->prepare("SELECT investment_id FROM investments WHERE reg_no = ? AND status != 'disposed'");
                $check_stmt->bind_param("s", $reg_no);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    flash_set("An active asset with registration number $reg_no already exists.", 'error');
                    header("Location: investments.php");
                    exit;
                }
            }

            $stmt = $conn->prepare("INSERT INTO investments (title, category, reg_no, model, assigned_route, description, purchase_date, purchase_cost, current_value, target_amount, target_period, target_start_date, viability_status, status, manager_admin_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'active', ?, NOW())");
            $stmt->bind_param("sssssssdddssi", $title, $category, $reg_no, $model, $route, $desc, $date, $cost, $value, $target, $period, $target_start, $admin_id);
            
            if ($stmt->execute()) {
                flash_set("Asset registered successfully with performance targets.", 'success');
                header("Location: investments.php");
                exit;
            } else {
                flash_set("Database error: " . $stmt->error, 'error');
            }
        }
    }

    // B. UPDATE ASSET (Valuation Only)
    if (isset($_POST['action']) && $_POST['action'] === 'update_asset') {
        $inv_id = intval($_POST['investment_id']);
        $val    = floatval($_POST['current_value']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE investments SET current_value = ?, status = ? WHERE investment_id = ?");
        $stmt->bind_param("dsi", $val, $status, $inv_id);
        
        if ($stmt->execute()) {
            $conn->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'update_asset', 'Updated investment #$inv_id status to $status', '{$_SERVER['REMOTE_ADDR']}')");
            flash_set("Asset status updated.", 'success');
            header("Location: investments.php");
            exit;
        }
    }

    // C. EDIT INVESTMENT (Full Details)
    if (isset($_POST['action']) && $_POST['action'] === 'edit_investment') {
        $inv_id = intval($_POST['investment_id']);
        $title = trim($_POST['title']);
        $category = $_POST['category'];
        $desc = trim($_POST['description'] ?? '');
        $target = floatval($_POST['target_amount']);
        $period = $_POST['target_period'];
        
        if (empty($title) || $target <= 0) {
            flash_set("Title and valid revenue target are required.", 'error');
        } else {
            $stmt = $conn->prepare("UPDATE investments SET title = ?, category = ?, description = ?, target_amount = ?, target_period = ? WHERE investment_id = ?");
            $stmt->bind_param("sssdsi", $title, $category, $desc, $target, $period, $inv_id);
            
            if ($stmt->execute()) {
                $conn->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'edit_investment', 'Edited investment #$inv_id', '{$_SERVER['REMOTE_ADDR']}')");
                flash_set("Investment updated successfully.", 'success');
                header("Location: investments.php");
                exit;
            } else {
                flash_set("Database error: " . $stmt->error, 'error');
            }
        }
    }
        // D. SELL / DISPOSE ASSET
    if (isset($_POST['action']) && $_POST['action'] === 'sell_asset') {
        $inv_id = intval($_POST['investment_id']);
        $price  = floatval($_POST['sale_price']);
        $date   = $_POST['sale_date'];
        $reason = trim($_POST['sale_reason']);

        $stmt = $conn->prepare("UPDATE investments SET status = 'sold', sale_price = ?, sale_date = ? WHERE investment_id = ?");
        $stmt->bind_param("dsi", $price, $date, $inv_id);

        if ($stmt->execute()) {
            require_once __DIR__ . '/../../inc/TransactionHelper.php';
            // Record Sale in Ledger
            TransactionHelper::record([
                'type'           => 'income',
                'category'       => 'asset_sale',
                'amount'         => $price,
                'notes'          => "Proceeds from sale of asset #$inv_id. Reason: $reason",
                'related_table'  => 'investments',
                'related_id'     => $inv_id
            ]);

            $conn->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'dispose_asset', 'Sold Asset #$inv_id for KES $price', '{$_SERVER['REMOTE_ADDR']}')");
            flash_set("Asset disposed successfully. Sale proceeds recorded in ledger.", 'success');
            header("Location: investments.php");
            exit;
        }
    }
}

// 2. DATA AGGREGATION
$filter = $_GET['cat'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = "";

if ($filter !== 'all') {
    $where[] = "category = ?";
    $params[] = $filter;
    $types .= "s";
}
if ($search) {
    $where[] = "(title LIKE ? OR reg_no LIKE ? OR model LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= "sss";
}

// Global Export Handler
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    $where_investments_e = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    $search_only_where_e = [];
    foreach($where as $w) if(strpos($w, 'LIKE') !== false) $search_only_where_e[] = $w;
    $search_sql_e = count($search_only_where_e) > 0 ? " AND " . implode(" AND ", $search_only_where_e) : "";

    $sql_e = "SELECT title, category, reg_no, purchase_cost, current_value, status, purchase_date FROM investments $where_investments_e ORDER BY purchase_date DESC";
    
    $stmt_e = $conn->prepare($sql_e);
    if (!empty($params)) {
        $stmt_e->bind_param($types, ...$params);
    }
    $stmt_e->execute();
    $raw_data = $stmt_e->get_result();

    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    $total_val = 0;
    while($a = $raw_data->fetch_assoc()) {
        $total_val += (float)$a['current_value'];
        $data[] = [
            'Asset' => $a['title'],
            'Category' => ucfirst(str_replace('_', ' ', $a['category'])),
            'Reg No' => $a['reg_no'] ?: '-',
            'Cost' => number_format((float)$a['purchase_cost']),
            'Valuation' => number_format((float)$a['current_value']),
            'Status' => strtoupper($a['status']),
            'Purchased' => date('d-M-Y', strtotime($a['purchase_date']))
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Investment Portfolio',
        'module' => 'Asset Management',
        'headers' => ['Asset', 'Category', 'Reg No', 'Cost', 'Valuation', 'Status', 'Purchased'],
        'total_value' => $total_val
    ]);
    exit;
}

// Display Data - Optimized Fetch (Unified Investments + Standalone Vehicles)
$where_investments = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$search_only_where = [];
foreach($where as $w) if(strpos($w, 'LIKE') !== false) $search_only_where[] = $w;
$search_sql = count($search_only_where) > 0 ? " AND " . implode(" AND ", $search_only_where) : "";

$sql = "SELECT investment_id as id, title, category, status, purchase_cost, current_value, target_amount, target_period, viability_status, created_at, 'investments' as source_table 
        FROM investments $where_investments 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$portfolio_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Batch fetch performance metrics
$ledger_stats = [];
if (!empty($portfolio_raw)) {
    foreach($portfolio_raw as $p) {
        $tid = $p['id'];
        $stats_res = $conn->query("
            SELECT 
                SUM(CASE WHEN transaction_type IN ('income','revenue_inflow') THEN amount ELSE 0 END) as rev,
                SUM(CASE WHEN transaction_type IN ('expense','expense_outflow') THEN amount ELSE 0 END) as exp
            FROM transactions 
            WHERE related_table='investments' AND related_id=$tid
        ")->fetch_assoc();
        $ledger_stats[$tid] = $stats_res;
    }
}

// Initialize Viability Engine
require_once __DIR__ . '/../../inc/InvestmentViabilityEngine.php';
$viability_engine = new InvestmentViabilityEngine($conn);

$portfolio = [];
foreach($portfolio_raw as $a) {
    $aid = $a['id'];
    $table = $a['source_table'];
    
    // Merge stats from ledger
    $st = $ledger_stats[$aid] ?? ['rev' => 0, 'exp' => 0];
    
    $a['revenue'] = (float)$st['rev'];
    $a['expenses'] = (float)$st['exp'];
    $a['roi'] = (($a['current_value'] - $a['purchase_cost']) + ($a['revenue'] - $a['expenses'])) / ((float)($a['purchase_cost'] ?? 0) ?: 1) * 100;
    
    // Calculate viability metrics using engine
    $performance = $viability_engine->calculatePerformance((int)$aid, 'investments');
    if ($performance) {
        $a['target_achievement'] = $performance['target_achievement_pct'];
        $a['net_profit'] = $performance['net_profit'];
        $a['viability_status'] = $performance['viability_status'];
        $a['is_profitable'] = $performance['is_profitable'];
        
        // Update database with latest viability (only for investments table which has the column)
        if ($table === 'investments' && $a['viability_status'] != $performance['viability_status']) {
            $viability_engine->updateViabilityStatus((int)$aid, 'investments');
        }
    } else {
        $a['target_achievement'] = 0;
        $a['net_profit'] = $a['revenue'] - $a['expenses'];
        $a['viability_status'] = 'pending';
        $a['is_profitable'] = $a['net_profit'] > 0;
    }
    
    $portfolio[] = $a;
}

// Category Distribution for Chart
$cat_stats = [];
foreach($portfolio as $p) {
    $c = ucfirst(str_replace('_', ' ', $p['category']));
    if(!isset($cat_stats[$c])) $cat_stats[$c] = 0;
    $cat_stats[$c] += (float)$p['current_value'];
}

// Global Stats - Financial Intelligence
$global = $conn->query("SELECT 
    COUNT(CASE WHEN status='active' THEN 1 END) as active_count,
    COUNT(*) as total_count,
    SUM(CASE WHEN status='active' THEN current_value ELSE 0 END) as active_valuation,
    SUM(CASE WHEN status='active' THEN purchase_cost ELSE 0 END) as active_cost,
    SUM(CASE WHEN status='sold' THEN (sale_price - purchase_cost) ELSE 0 END) as realized_gains,
    SUM(CASE WHEN status='sold' THEN sale_price ELSE 0 END) as total_exit_value
    FROM investments")->fetch_assoc();
?>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'Investment Portfolio'); ?>
        <div class="container-fluid">
        
        <!-- Header -->
        <div class="hp-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">Capital Assets Portfolio</span>
                    <h1 class="display-4 fw-800 mb-2">Asset Management.</h1>
                    <p class="opacity-75 fs-5">Managing <?= $global['total_count'] ?> high-value investments and fleet assets with <span class="text-lime fw-bold">precision intelligence</span>.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <button class="btn btn-lime shadow-lg px-4 fs-6 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                        <i class="bi bi-plus-lg me-2"></i>Register New Asset
                    </button>
                    <div class="mt-3 d-flex flex-wrap justify-content-lg-end gap-3 no-print">
                        <a href="revenue.php" class="text-white opacity-75 small text-decoration-none">
                            <i class="bi bi-cash-coin me-1 text-lime"></i> Revenue
                        </a>
                        <a href="expenses.php" class="text-white opacity-75 small text-decoration-none">
                            <i class="bi bi-receipt me-1 text-lime"></i> Expenses
                        </a>
                        <a href="?action=print_report" target="_blank" class="text-white opacity-75 small text-decoration-none">
                            <i class="bi bi-printer me-1 text-lime"></i> Print Matrix
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php flash_render(); ?>

        <!-- KPIs -->
        <div class="row g-4 mb-5">
            <div class="col-md-9">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="glass-stat h-100">
                            <div class="stat-icon bg-forest bg-opacity-10 text-forest p-3 rounded-4 fs-3 mb-3 d-inline-block">
                                <i class="bi bi-safe2"></i>
                            </div>
                            <div class="text-muted small fw-800 text-uppercase ls-1">Active Valuation</div>
                            <div class="h2 fw-800 text-forest mt-1 mb-0">KES <?= number_format((float)($global['active_valuation'] ?? 0)) ?></div>
                            <div class="small text-muted mt-2"><i class="bi bi-building-check text-success me-1"></i> Live Assets</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="glass-stat h-100 stat-card-dark">
                            <div class="stat-icon bg-white bg-opacity-10 text-lime p-3 rounded-4 fs-3 mb-3 d-inline-block">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <div class="text-white opacity-50 small fw-800 text-uppercase ls-1">Realized Exit Gains</div>
                            <div class="h2 fw-800 text-white mt-1 mb-0">KES <?= number_format((float)($global['realized_gains'] ?? 0)) ?></div>
                            <div class="small text-white opacity-50 mt-2"><i class="bi bi-check-all text-lime me-1"></i> Sold Profit</div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="glass-stat h-100 stat-card-accent">
                            <div class="stat-icon bg-forest bg-opacity-10 text-forest p-3 rounded-4 fs-3 mb-3 d-inline-block">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <div class="text-forest opacity-50 small fw-800 text-uppercase ls-1">Projected Multiplier</div>
                            <?php 
                                $total_val = ($global['active_valuation'] ?? 0) + ($global['total_exit_value'] ?? 0);
                                $q_cost_res = $conn->query("SELECT SUM(purchase_cost) as c FROM investments")->fetch_assoc();
                                $q_cost = (float)($q_cost_res['c'] ?? 0) ?: 1;
                                $multiplier = $total_val / $q_cost;
                            ?>
                            <div class="h2 fw-800 text-forest mt-1 mb-0"><?= number_format($multiplier, 2) ?>x</div>
                            <div class="small text-forest opacity-50 mt-2"><i class="bi bi-stars text-forest me-1"></i> Total Growth</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-stat h-100 text-center">
                    <h6 class="small fw-800 text-uppercase text-muted mb-4">Asset Mix</h6>
                    <div style="height: 140px;">
                        <canvas id="portfolioChart" data-labels='<?= json_encode(array_keys($cat_stats)) ?>' data-values='<?= json_encode(array_values($cat_stats)) ?>'></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row g-3 mb-4 align-items-center">
            <div class="col-lg-6">
                <div class="d-flex gap-2 overflow-auto py-1">
                    <a href="?cat=all" class="btn btn-<?= $filter=='all'?'forest':'white' ?> rounded-pill px-4 btn-sm fw-bold border">All Assets</a>
                    <a href="?cat=vehicle_fleet" class="btn btn-<?= $filter=='vehicle_fleet'?'forest':'white' ?> rounded-pill px-4 btn-sm fw-bold border">Vehicles</a>
                    <a href="?cat=farm" class="btn btn-<?= $filter=='farm'?'forest':'white' ?> rounded-pill px-4 btn-sm fw-bold border">Farms</a>
                    <a href="?cat=apartments" class="btn btn-<?= $filter=='apartments'?'forest':'white' ?> rounded-pill px-4 btn-sm fw-bold border">Real Estate</a>
                    <a href="?cat=petrol_station" class="btn btn-<?= $filter=='petrol_station'?'forest':'white' ?> rounded-pill px-4 btn-sm fw-bold border">Fuel</a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="assetSearch" class="form-control rounded-pill ps-5 border-0 shadow-sm" placeholder="Search assets by name or reg..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-2 text-lg-end">
                <div class="dropdown">
                    <button class="btn btn-light rounded-pill px-4 border dropdown-toggle fw-bold" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-2"></i> Export
                    </button>
                    <ul class="dropdown-menu shadow border-0">
                        <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Portfolio PDF</a></li>
                        <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Spreadsheet (XLS)</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <?php if(empty($portfolio)): ?>
                <div class="col-12 text-center py-5">
                    <div class="opacity-25 mb-4"><i class="bi bi-box-seam display-1"></i></div>
                    <h5 class="fw-bold text-muted">No assets found</h5>
                    <p class="text-muted">Register an asset to start tracking its value and performance.</p>
                </div>
            <?php else: 
            foreach($portfolio as $a): 
                $icon = match($a['category']) {
                    'farm' => 'bi-flower2',
                    'vehicle_fleet' => 'bi-truck-front',
                    'petrol_station' => 'bi-fuel-pump',
                    'apartments' => 'bi-building',
                    default => 'bi-box-seam'
                };
            ?>
            <div class="col-xl-4 col-md-6">
                <div class="asset-card slide-up">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="asset-icon-box">
                            <i class="bi <?= $icon ?>"></i>
                        </div>
                        <div class="d-flex flex-column align-items-end">
                            <span class="status-badge st-<?= $a['status'] ?> mb-2"><?= $a['status'] ?></span>
                            <?php 
                            // Viability Status Badge
                            $viability_badge = match($a['viability_status']) {
                                'viable' => '<span class="badge bg-success text-white px-2 py-1 mb-1" style="font-size: 0.65rem;"><i class="bi bi-check-circle-fill me-1"></i>Viable</span>',
                                'underperforming' => '<span class="badge bg-warning  px-2 py-1 mb-1" style="font-size: 0.65rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Underperforming</span>',
                                'loss_making' => '<span class="badge bg-danger text-white px-2 py-1 mb-1" style="font-size: 0.65rem;"><i class="bi bi-x-circle-fill me-1"></i>Loss Making</span>',
                                default => '<span class="badge bg-secondary text-white px-2 py-1 mb-1" style="font-size: 0.65rem;"><i class="bi bi-clock-history me-1"></i>Pending</span>'
                            };
                            echo $viability_badge;
                            ?>
                            <?php if ($a['status'] === 'active'): ?>
                                <?php 
                                    $suggestion = 'Retain Asset';
                                    $s_class = 'text-primary';
                                    if ($a['roi'] > 25) { $suggestion = 'Expand/Reinvest'; $s_class = 'text-success'; }
                                    elseif ($a['roi'] < 5) { $suggestion = 'Optimize or Sell'; $s_class = 'text-danger'; }
                                ?>
                                <small class="fw-bold <?= $s_class ?> px-2 py-1 bg-light rounded-pill" style="font-size: 0.6rem;"><?= $suggestion ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h5 class="fw-800  mb-1"><?= esc($a['title']) ?></h5>
                    <div class="small text-muted fw-bold text-uppercase ls-1 mb-3"><?= str_replace('_',' ',$a['category']) ?></div>

                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-4 h-100">
                                <div class="small text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Current Valuation</div>
                                <div class="fw-800 text-forest">KES <?= number_format((float)$a['current_value']) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-4 h-100">
                                <div class="small text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Yield (ROI)</div>
                                <div class="fw-800 <?= $a['roi'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format((float)$a['roi'], 1) ?>%
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center small mb-2">
                            <span class="text-muted fw-medium">Net Profit/Loss</span>
                            <span class="fw-bold <?= $a['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                KES <?= number_format((float)$a['net_profit']) ?>
                            </span>
                        </div>
                        
                        <!-- Target Achievement Progress -->
                        <div class="d-flex justify-content-between align-items-center small mb-1 mt-3">
                            <span class="text-muted fw-medium">Target Achievement (<?= ucfirst($a['target_period']) ?>)</span>
                            <span class="fw-bold <?= $a['target_achievement'] >= 100 ? 'text-success' : ($a['target_achievement'] >= 70 ? 'text-warning' : 'text-danger') ?>">
                                <?= number_format($a['target_achievement'], 1) ?>%
                            </span>
                        </div>
                        <div class="progress" style="height: 8px; border-radius: 10px; background: #f1f5f9;">
                            <div class="progress-bar <?= $a['target_achievement'] >= 100 ? 'bg-success' : ($a['target_achievement'] >= 70 ? 'bg-warning' : 'bg-danger') ?>" 
                                 style="width: <?= min(100, $a['target_achievement']) ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1 fs-xs text-muted" style="font-size: 0.65rem;">
                            <span>Actual: KES <?= number_format((float)$a['revenue']) ?></span>
                            <span>Target: KES <?= number_format((float)$a['target_amount']) ?></span>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if ($a['status'] === 'active'): ?>
                            <?php if ($a['source_table'] === 'investments'): ?>
                                <button class="btn btn-outline-primary btn-sm px-3 rounded-pill" onclick="openEditModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Edit Investment">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-outline-forest btn-sm flex-grow-1 fw-bold rounded-pill" onclick="openValuationModal(<?= htmlspecialchars(json_encode($a)) ?>)">
                                Audit Valuation
                            </button>
                            <button class="btn btn-outline-danger btn-sm px-3 rounded-pill" onclick="openDisposeModal(<?= htmlspecialchars(json_encode($a)) ?>)" title="Dispose/Sell Asset">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-light btn-sm flex-grow-1 fw-bold rounded-pill disabled" style="opacity: 0.6; cursor: not-allowed;">
                                Asset Disposed
                            </button>
                        <?php endif; ?>
                        <a href="transactions.php?filter=<?= $a['id'] ?>&related_table=investments" class="btn btn-light btn-sm border px-3 rounded-pill" title="View Ledger">
                            <i class="bi bi-list-task"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
         
    </div>
    
   
    </div>
   
</div>

<!-- Add Asset Modal -->
<div class="modal fade" id="addAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-2xl">
            <div class="modal-header bg-forest text-white border-0 py-4 px-5">
                <h5 class="modal-title fw-800"><i class="bi bi-box-seam me-2 text-lime"></i>Asset Registration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_asset">
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Asset Title / Name</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Ruiru Apartments Block B, Matatu KDA 123" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category" class="form-select" id="catSelector" onchange="checkVehicle(this.value)" required>
                                <option value="farm">Farming / Agriculture</option>
                                <option value="vehicle_fleet">Vehicle / Fleet</option>
                                <option value="petrol_station">Petroleum / Energy</option>
                                <option value="apartments">Real Estate / Housing</option>
                                <option value="other">Miscellaneous</option>
                            </select>
                        </div>

                        <div id="vehExtra" class="col-12 d-none">
                            <div class="row g-3 p-3 bg-light rounded-4 border">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Registration No.</label>
                                    <input type="text" name="reg_no" class="form-control" placeholder="e.g. KCA 001X">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Make/Model</label>
                                    <input type="text" name="model" class="form-control" placeholder="Toyota Hiace">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Operational Route</label>
                                    <input type="text" name="assigned_route" class="form-control" placeholder="e.g. Nairobi - Thika">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Purchase Cost (KES)</label>
                            <input type="number" name="purchase_cost" class="form-control fw-bold" step="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Opening Valuation (KES)</label>
                            <input type="number" name="current_value" class="form-control fw-bold" step="1" required>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info border-0 rounded-4 mb-3">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <strong>Performance Targets Required:</strong> Every investment must have measurable financial goals for viability tracking.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Revenue Target (KES) <span class="text-danger">*</span></label>
                            <input type="number" name="target_amount" class="form-control fw-bold" placeholder="Expected revenue" min="1" step="1" required>
                            <small class="text-muted">Minimum expected revenue per period</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Target Period <span class="text-danger">*</span></label>
                            <select name="target_period" class="form-select" required>
                                <option value="daily">Daily</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="annually">Annually</option>
                            </select>
                            <small class="text-muted">Evaluation frequency</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Target Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="target_start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            <small class="text-muted">When tracking begins</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Asset Description / Notes</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Audit notes, location details, spec sheet..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lime rounded-pill px-5 shadow-lg">Confirm & Register Asset</button>
                </div>
            </form>
              <?php $layout->footer(); ?>
        </div>
    </div>
</div>

<!-- Valuation Modal -->
<div class="modal fade" id="valuationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-2xl">
            <div class="modal-header bg-forest text-white border-0 py-4 px-5">
                <h5 class="modal-title fw-800"><i class="bi bi-graph-up me-2 text-lime"></i>Valuation Audit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_asset">
                <input type="hidden" name="investment_id" id="val_id">
                <input type="hidden" name="source_table" id="val_source">
                <div class="modal-body p-4">
                    <h6 class="fw-bold mb-3" id="val_title"></h6>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">New Market Valuation (KES)</label>
                        <input type="number" name="current_value" id="val_input" class="form-control fw-bold fs-5 py-3" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted text-uppercase">Asset Status</label>
                        <select name="status" id="val_status" class="form-select py-3">
                            <option value="active">Active & Operating</option>
                            <option value="maintenance">Under Maintenance</option>
                            <option value="disposed">Disposed / Terminated</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-lime w-100 rounded-pill py-3 fw-bold">Apply Valuation Change</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Investment Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-2xl">
            <div class="modal-header bg-forest text-white border-0 py-4 px-5">
                <h5 class="modal-title fw-800"><i class="bi bi-pencil-square me-2 text-lime"></i>Edit Investment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_investment">
                <input type="hidden" name="investment_id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Investment Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                            <select name="category" id="edit_category" class="form-select" required>
                                <option value="farm">Farm</option>
                                <option value="apartments">Apartments</option>
                                <option value="petrol_station">Petrol Station</option>
                                <option value="vehicle_fleet">Vehicle Fleet</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Revenue Target (KES) <span class="text-danger">*</span></label>
                            <input type="number" name="target_amount" id="edit_target" class="form-control" min="1" step="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Target Period <span class="text-danger">*</span></label>
                            <select name="target_period" id="edit_period" class="form-select" required>
                                <option value="daily">Daily</option>
                                <option value="monthly">Monthly</option>
                                <option value="annually">Annually</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dispose Modal -->
<div class="modal fade" id="disposeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <input type="hidden" name="action" value="sell_asset">
                <input type="hidden" name="investment_id" id="dispose_id">
                <input type="hidden" name="source_table" id="dispose_source">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="fw-800">Finalize Asset Disposal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">You are about to mark <strong id="dispose_title" class=""></strong> as sold. This will record the proceeds in the treasury and halt future revenue tracking.</p>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Sale Price (KES)</label>
                        <input type="number" name="sale_price" id="dispose_price" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Sale Date</label>
                        <input type="date" name="sale_date" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted">Reason for Sale</label>
                        <textarea name="sale_reason" class="form-control rounded-3" rows="3" placeholder="e.g. Asset depreciation, fleet upgrade..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Confirm Sale & Archive</button>
                </div>
            </form>
        </div>
    </div>
</div>
        </div>
        
    </div>
</div>
