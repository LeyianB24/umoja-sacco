<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/functions.php';

$layout = LayoutManager::create('admin');

// usms/admin/pages/investments.php
// Operations Manager - Asset & Investment Portfolio

if (session_status() === PHP_SESSION_NONE) session_start();

require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');


// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id   = $_SESSION['admin_id'];
$db = $conn;

// ---------------------------------------------------------
// 1. HANDLE ACTIONS (Add / Update Asset)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        flash_set("Invalid session token.", 'error');
    } else {
        // A. ADD NEW INVESTMENT
        if (isset($_POST['action']) && $_POST['action'] === 'add_asset') {
            $title    = trim($_POST['title']);
            $category = $_POST['category'];
            $cost     = floatval($_POST['purchase_cost']);
            $value    = floatval($_POST['current_value']);
            $date     = $_POST['purchase_date'];
            $desc     = trim($_POST['description']);
            
            // Unified fields
            $reg_no   = trim($_POST['reg_no'] ?? '');
            $model    = trim($_POST['model'] ?? '');
            $route    = trim($_POST['assigned_route'] ?? '');
            $target   = floatval($_POST['target_amount'] ?? 0);
            $period   = $_POST['target_period'] ?? 'monthly';

            if (empty($title) || $cost <= 0) {
                flash_set("Title and valid cost are required.", 'error');
            } else {
                $stmt = $db->prepare("INSERT INTO investments (title, category, reg_no, model, assigned_route, description, purchase_date, purchase_cost, current_value, target_amount, target_period, status, manager_admin_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())");
                $stmt->bind_param("ssssssdddds", $title, $category, $reg_no, $model, $route, $desc, $date, $cost, $value, $target, $period, $admin_id);
                
                if ($stmt->execute()) {
                    flash_set("Asset registered successfully.", 'success');
                } else {
                    flash_set("Database error: " . $stmt->error, 'error');
                }
            }
        }

        // B. UPDATE ASSET STATUS/VALUE
        if (isset($_POST['action']) && $_POST['action'] === 'update_asset') {
            $inv_id = intval($_POST['investment_id']);
            $val    = floatval($_POST['current_value']);
            $status = $_POST['status'];
            
            $stmt = $db->prepare("UPDATE investments SET current_value = ?, status = ? WHERE investment_id = ?");
            $stmt->bind_param("dsi", $val, $status, $inv_id);
            
            if ($stmt->execute()) {
                $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'update_asset', 'Updated Asset #$inv_id value/status', '{$_SERVER['REMOTE_ADDR']}')");
                flash_set("Asset details updated.", 'success');
            }
        }
    }
}

// ---------------------------------------------------------
// 2. FETCH DATA
// ---------------------------------------------------------
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
    $where[] = "title LIKE ? OR reg_no LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT * FROM investments $where_sql ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$portfolio_raw = $stmt->get_result();

$portfolio_enriched = [];
while($a = $portfolio_raw->fetch_assoc()) {
    $aid = $a['investment_id'];
    
    // A. Realized Income Aggregation (Unified)
    $inc_stats = $db->query("
        SELECT (
            (SELECT COALESCE(SUM(amount), 0) FROM investment_income WHERE investment_id = $aid) +
            (SELECT COALESCE(SUM(amount), 0) FROM vehicle_income WHERE vehicle_id = $aid)
        ) as total_yield
    ")->fetch_assoc();
    $a['realized_income'] = $inc_stats['total_yield'];
    
    // B. Operating Expenses Aggregation (Unified)
    $exp_stats = $db->query("
        SELECT (
            (SELECT COALESCE(SUM(amount), 0) FROM investment_expenses WHERE investment_id = $aid) +
            (SELECT COALESCE(SUM(amount), 0) FROM vehicle_expenses WHERE vehicle_id = $aid)
        ) as total_costs
    ")->fetch_assoc();
    $a['total_expenses'] = $exp_stats['total_costs'];
    
    // C. Net Balance
    $a['net_balance'] = $a['realized_income'] - $a['total_expenses'];
    
    // D. Performance Metrics
    $cost = (float)$a['purchase_cost'] ?: 1;
    $a['roi_pct'] = (($a['current_value'] - $a['purchase_cost']) + $a['net_balance']) / $cost * 100;
    
    $portfolio_enriched[] = $a;
}

// Global KPIs
$total_stats = $db->query("
    SELECT 
        SUM(current_value) as total_val, 
        SUM(purchase_cost) as total_cost,
        (SELECT COALESCE(SUM(amount), 0) FROM investment_income) + (SELECT COALESCE(SUM(amount), 0) FROM vehicle_income) as total_rev,
        (SELECT COALESCE(SUM(amount), 0) FROM investment_expenses) + (SELECT COALESCE(SUM(amount), 0) FROM vehicle_expenses) as total_exp
    FROM investments
")->fetch_assoc();

$portfolio_valuation = $total_stats['total_val'] ?? 0;
$portfolio_cost = $total_stats['total_cost'] ?? 0;
$portfolio_profit = ($total_stats['total_rev'] ?? 0) - ($total_stats['total_exp'] ?? 0);

function ksh($v, $d = 2) { return number_format((float)($v ?? 0), $d); }
function getIcon($cat) {
    return match($cat) {
        'farm' => 'bi-flower1',
        'vehicle_fleet' => 'bi-truck-front',
        'petrol_station' => 'bi-fuel-pump',
        'apartments' => 'bi-building',
        'land' => 'bi-geo-alt',
        default => 'bi-briefcase'
    };
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>Investment Portfolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Emerald/Lime Theme */
            --bg-app: #f0fdf4;       
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: 1px solid rgba(22, 163, 74, 0.15);
            
            --text-dark: #064e3b;    
            --text-muted: #64748b;
            
            --primary-green: #10b981; 
            --dark-green: #065f46;    
            --lime-accent: #84cc16;   
        }
        
        body { background-color: var(--bg-app); color: var(--text-dark); font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .main-content-wrapper { margin-left: 260px; transition: margin-left 0.3s; padding: 20px; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

        /* Cards */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: var(--glass-border);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .glass-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }

        /* Buttons & Pills */
        .btn-lime { background-color: var(--lime-accent); color: white; border: none; font-weight: 600; }
        .btn-lime:hover { background-color: #65a30d; color: white; }

        .nav-pills .nav-link { color: var(--text-muted); font-weight: 500; font-size: 0.85rem; padding: 0.5rem 1rem; border-radius: 50px; background: white; border: 1px solid #e2e8f0; margin-right: 0.5rem; transition: all 0.2s; }
        .nav-pills .nav-link:hover { background: #f1f5f9; }
        .nav-pills .nav-link.active { background-color: var(--dark-green); color: white; border-color: var(--dark-green); }

        /* Badges */
        .badge-status { padding: 5px 10px; border-radius: 6px; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .st-active { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .st-disposed { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        .asset-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
    </style>
</head>
<body>

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1" style="color: var(--dark-green);">Asset Portfolio</h4>
                    <p class="text-muted small mb-0">Manage Sacco investments and fixed assets.</p>
                </div>
            </div>

            <?php flash_render(); ?>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase small text-muted fw-bold ls-1 mb-1">Portfolio Value</div>
                            <h2 class="fw-bold mb-0 text-dark">KES <?= ksh($portfolio_valuation/1000000) ?>M</h2>
                            <small class="text-success fw-bold"><i class="bi bi-arrow-up"></i> Valuation</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:50px; height:50px; background: #dcfce7; color: var(--primary-green);">
                            <i class="bi bi-wallet2 fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase small text-muted fw-bold ls-1 mb-1">Total Costs</div>
                            <h2 class="fw-bold mb-0 text-dark">KES <?= ksh($portfolio_cost/1000000) ?>M</h2>
                            <small class="text-muted">Capital Invested</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:50px; height:50px; background: #ecfccb; color: #65a30d;">
                            <i class="bi bi-cash-stack fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase small text-muted fw-bold ls-1 mb-1">Net Operating Profit</div>
                            <h2 class="fw-bold mb-0 <?= $portfolio_profit >= 0 ? 'text-success' : 'text-danger' ?>">KES <?= ksh($portfolio_profit/1000) ?>K</h2>
                            <small class="text-muted">Inflow vs Outflow</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:50px; height:50px; background: #ccfbf1; color: #0f766e;">
                            <i class="bi bi-graph-up-arrow fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div class="nav nav-pills d-flex overflow-auto">
                    <a href="?cat=all" class="nav-link shadow-sm <?= $filter=='all'?'active':'' ?>">All Assets</a>
                    <a href="?cat=vehicle_fleet" class="nav-link shadow-sm <?= $filter=='vehicle_fleet'?'active':'' ?>">Vehicles</a>
                    <a href="?cat=farm" class="nav-link shadow-sm <?= $filter=='farm'?'active':'' ?>">Farms</a>
                    <a href="?cat=apartments" class="nav-link shadow-sm <?= $filter=='apartments'?'active':'' ?>">Real Estate</a>
                    <a href="?cat=petrol_station" class="nav-link shadow-sm <?= $filter=='petrol_station'?'active':'' ?>">Fuel</a>
                </div>
                
                <button class="btn btn-lime shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                    <i class="bi bi-plus-lg me-2"></i> New Investment
                </button>
            </div>

            <div class="row g-4">
                <?php if(empty($portfolio_enriched)): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-box-seam fs-1 mb-3 opacity-50"></i>
                        <p>No assets found in this category.</p>
                    </div>
                <?php else: foreach($portfolio_enriched as $a): 
                    $net_bal = $a['net_balance'];
                    $bal_class = $net_bal >= 0 ? 'text-success' : 'text-danger';
                    $status_class = ($a['status'] === 'active') ? 'st-active' : 'st-disposed';
                    
                    // Target Progress
                    $target_raw = $a['target_amount'] ?: 0;
                    $progress = ($target_raw > 0) ? ($a['realized_income'] / $target_raw) * 100 : 0;
                ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="glass-card h-100 d-flex flex-column position-relative">
                            
                            <div class="p-4 pb-0 d-flex justify-content-between align-items-start mb-3">
                                <div class="asset-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi <?= getIcon($a['category']) ?>"></i>
                                </div>
                                <div class="text-end">
                                    <span class="badge-status <?= $status_class ?> d-block mb-1"><?= $a['status'] ?></span>
                                    <small class="fw-bold text-muted"><?= strtoupper($a['target_period'] ?? 'monthly') ?> TARGET</small>
                                </div>
                            </div>

                            <div class="card-body px-4 pt-0">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0 text-dark text-truncate"><?= htmlspecialchars($a['title']) ?></h5>
                                    <?php if($a['reg_no']): ?>
                                        <span class="badge bg-light text-dark border small"><?= esc($a['reg_no']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-uppercase small fw-bold mb-3" style="color: var(--text-muted);"><?= str_replace('_', ' ', $a['category']) ?></div>
                                
                                <div class="p-3 bg-light rounded-3 mb-3 border border-light">
                                    <div class="row g-2">
                                        <div class="col-6 border-end">
                                            <div class="small text-muted mb-1" style="font-size:0.65rem">Net Balance</div>
                                            <div class="fw-bold <?= $bal_class ?>">
                                                KES <?= number_format((float)$net_bal) ?>
                                            </div>
                                        </div>
                                        <div class="col-6 ps-2">
                                            <div class="small text-muted mb-1" style="font-size:0.65rem">Total Yield</div>
                                            <div class="fw-bold text-dark">KES <?= number_format((float)$a['realized_income']) ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center small mb-2">
                                    <span class="text-muted">Target Progress (<?= number_format((float)$a['target_amount']) ?>)</span>
                                    <span class="fw-bold <?= $progress >= 100 ? 'text-success' : 'text-primary' ?>"><?= number_format((float)$progress, 1) ?>%</span>
                                </div>
                                <div class="progress progress-micro mb-3" style="height:6px; border-radius: 10px;">
                                    <div class="progress-bar <?= $progress >= 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= min(100, $progress) ?>%"></div>
                                </div>

                                <?php if($a['assigned_route']): ?>
                                    <div class="small text-muted mb-2">
                                        <i class="bi bi-geo-alt me-1"></i> <?= esc($a['assigned_route']) ?> 
                                        <?php if($a['capacity']): ?> • <?= esc($a['capacity']) ?> Pax<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-3 border-top border-light mt-auto">
                                <button class="btn btn-outline-success btn-sm w-100 rounded-pill fw-semibold" 
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($a)) ?>)">
                                    Audit & Adjust
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<div class="modal fade" id="addAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-success bg-opacity-10">
                <h5 class="modal-title fw-bold" style="color: var(--dark-green);">Add New Investment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="add_asset">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-muted text-uppercase">Asset Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Ruiru Farm A or Toyota Hiace">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Category</label>
                            <select name="category" class="form-select" id="assetCategorySelector" required onchange="toggleVehicleFields(this.value)">
                                <option value="farm">Farm / Land</option>
                                <option value="vehicle_fleet">Vehicle Fleet</option>
                                <option value="petrol_station">Petrol Station</option>
                                <option value="apartments">Real Estate</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Vehicle Specific Fields (Visible when Category is Vehicle) -->
                        <div class="col-12 d-none" id="vehicleFields">
                            <div class="row g-3 p-3 rounded-4 border bg-light bg-opacity-50">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Reg No</label>
                                    <input type="text" name="reg_no" class="form-control" placeholder="KDA 123">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Make/Model</label>
                                    <input type="text" name="model" class="form-control" placeholder="Toyota Hiace">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Capacity</label>
                                    <input type="text" name="capacity" class="form-control" placeholder="14 Pax">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Route</label>
                                    <input type="text" name="assigned_route" class="form-control" placeholder="Nairobi-Thika">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Acquisition Cost</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">KES</span>
                                <input type="number" name="purchase_cost" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Opening Valuation</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">KES</span>
                                <input type="number" name="current_value" class="form-control" step="0.01" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Expected Projection</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">KES</span>
                                <input type="number" name="target_amount" class="form-control" placeholder="Target Income">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Projection Period</label>
                            <select name="target_period" class="form-select">
                                <option value="daily">Daily Target</option>
                                <option value="monthly" selected>Monthly Target</option>
                                <option value="yearly">Yearly Target</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" required max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted text-uppercase">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Location, specifications, or notes..."></textarea>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-lime py-2 rounded-pill shadow-sm">Register Investment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editAssetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-success bg-opacity-10">
                <h5 class="modal-title fw-bold" style="color: var(--dark-green);">Update Valuation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Update the current market value or status of <strong id="edit_title_display" class="text-dark"></strong>.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="update_asset">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="investment_id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">New Current Value</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">KES</span>
                            <input type="number" name="current_value" id="edit_value" class="form-control fw-bold" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Active (Operating)</option>
                            <option value="maintenance">Under Maintenance</option>
                            <option value="disposed">Disposed / Sold</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-lime rounded-pill shadow-sm">Save Updates</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openEditModal(data) {
        document.getElementById('edit_id').value = data.investment_id;
        document.getElementById('edit_value').value = data.current_value;
        document.getElementById('edit_status').value = data.status;
        document.getElementById('edit_title_display').textContent = data.title;
        new bootstrap.Modal(document.getElementById('editAssetModal')).show();
    }

    function toggleVehicleFields(val) {
        const wrap = document.getElementById('vehicleFields');
        if(val === 'vehicle_fleet') {
            wrap.classList.remove('d-none');
        } else {
            wrap.classList.add('d-none');
        }
    }
</script>
</body>
</html>





