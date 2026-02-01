<?php
// manager/vehicles.php
// Operations Manager - Fleet & Vehicle Management

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

// Enforce Manager Role
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['manager', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['full_name'] ?? 'Manager';
$db = $conn;

$msg = "";
$msg_type = "";

// ---------------------------------------------------------
// 1. HANDLE ACTIONS (Add / Edit / Status)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. ADD VEHICLE
    if (isset($_POST['action']) && $_POST['action'] === 'add_vehicle') {
        $plate    = strtoupper(trim($_POST['number_plate']));
        $model    = trim($_POST['model']);
        $capacity = intval($_POST['capacity']);
        $target   = floatval($_POST['target_revenue']); // Daily target
        $route    = trim($_POST['assigned_route']);

        if (empty($plate) || empty($model)) {
            $msg = "Number Plate and Model are required."; $msg_type = "danger";
        } else {
            // Check for duplicate plate
            $check = $db->query("SELECT vehicle_id FROM vehicles WHERE reg_no = '$plate'");
            if ($check->num_rows > 0) {
                $msg = "Vehicle with this Number Plate already exists."; $msg_type = "danger";
            } else {
                $stmt = $db->prepare("INSERT INTO vehicles (reg_no, model, capacity, target_daily_revenue, assigned_route, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("ssids", $plate, $model, $capacity, $target, $route);
                
                if ($stmt->execute()) {
                    // Audit
                    $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'add_vehicle', 'Added Vehicle $plate', '{$_SERVER['REMOTE_ADDR']}')");
                    $msg = "Vehicle added successfully."; $msg_type = "success";
                } else {
                    $msg = "Error: " . $stmt->error; $msg_type = "danger";
                }
            }
        }
    }

    // B. UPDATE VEHICLE
    if (isset($_POST['action']) && $_POST['action'] === 'update_vehicle') {
        $veh_id   = intval($_POST['vehicle_id']);
        $plate    = strtoupper(trim($_POST['number_plate']));
        $model    = trim($_POST['model']);
        $capacity = intval($_POST['capacity']);
        $route    = trim($_POST['assigned_route']);
        $status   = $_POST['status'];

        $stmt = $db->prepare("UPDATE vehicles SET reg_no=?, model=?, capacity=?, assigned_route=?, status=? WHERE vehicle_id=?");
        $stmt->bind_param("ssissi", $plate, $model, $capacity, $route, $status, $veh_id);

        if ($stmt->execute()) {
            $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'update_vehicle', 'Updated Vehicle $plate', '{$_SERVER['REMOTE_ADDR']}')");
            $msg = "Vehicle record updated."; $msg_type = "success";
        }
    }
}

// ---------------------------------------------------------
// 2. FETCH DATA
// ---------------------------------------------------------
$filter_status = $_GET['status'] ?? 'active';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = "";

if ($filter_status !== 'all') {
    $where[] = "status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($search) {
    $where[] = "(number_plate LIKE ? OR model LIKE ? OR assigned_route LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= "sss";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT * FROM vehicles $where_sql ORDER BY reg_no ASC";

$stmt = $db->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$fleet_res = $stmt->get_result();

// KPIs
$total_fleet = $db->query("SELECT COUNT(*) as c FROM vehicles")->fetch_assoc()['c'];
$active_fleet = $db->query("SELECT COUNT(*) as c FROM vehicles WHERE status='active'")->fetch_assoc()['c'];
$maintenance_count = $db->query("SELECT COUNT(*) as c FROM vehicles WHERE status='maintenance'")->fetch_assoc()['c'];

function ksh($num) { return number_format((float)$num, 2); }

$pageTitle = "Fleet Management";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= $pageTitle ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        /* Shared Glass Theme */
        :root {
            --bg-app: #f4f7f6;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: 1px solid rgba(255, 255, 255, 0.6);
            --text-main: #2c3e50;
            --text-muted: #6c757d;
        }
        [data-bs-theme="dark"] {
            --bg-app: #0f1115;
            --glass-bg: rgba(30, 33, 40, 0.7);
            --glass-border: 1px solid rgba(255, 255, 255, 0.08);
            --text-main: #e0e6ed;
            --text-muted: #a0a0a0;
        }
        body { background-color: var(--bg-app); color: var(--text-main); font-family: 'Segoe UI', sans-serif; transition: background 0.3s; }
        
        .hd-glass {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: var(--glass-border);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }
        .main-content-wrapper { margin-left: 260px; transition: margin-left 0.3s; min-height: 100vh; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .plate-badge { 
            font-family: 'Courier New', monospace; 
            font-weight: bold; 
            letter-spacing: 1px;
            background: #ffc107;
            color: #000;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e0a800;
        }
    </style>
</head>
<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Fleet Management</h3>
                    <h5 class="text-muted small mb-0">Manage vehicles, maintenance, and route assignments.</h5>
                </div>
            </div>

            <?php if($msg): ?>
                <div class="alert alert-<?= $msg_type ?> border-0 bg-<?= $msg_type ?> bg-opacity-10 text-<?= $msg_type ?> alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i> <?= $msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="hd-glass p-3 h-100 stat-card d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold text-uppercase">Total Fleet</small>
                            <h3 class="fw-bold mb-0 text-primary"><?= $total_fleet ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                            <i class="bi bi-bus-front-fill fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hd-glass p-3 h-100 stat-card d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold text-uppercase">Active on Road</small>
                            <h3 class="fw-bold mb-0 text-success"><?= $active_fleet ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                            <i class="bi bi-speedometer2 fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hd-glass p-3 h-100 stat-card d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold text-uppercase">In Maintenance</small>
                            <h3 class="fw-bold mb-0 text-danger"><?= $maintenance_count ?></h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle">
                            <i class="bi bi-wrench-adjustable fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div class="d-flex gap-2">
                    <a href="?status=active" class="btn btn-sm rounded-pill <?= $filter_status=='active'?'btn-dark':'btn-light border' ?> px-3">Active</a>
                    <a href="?status=maintenance" class="btn btn-sm rounded-pill <?= $filter_status=='maintenance'?'btn-dark':'btn-light border' ?> px-3">Maintenance</a>
                    <a href="?status=grounded" class="btn btn-sm rounded-pill <?= $filter_status=='grounded'?'btn-dark':'btn-light border' ?> px-3">Grounded</a>
                    <a href="?status=all" class="btn btn-sm rounded-pill <?= $filter_status=='all'?'btn-dark':'btn-light border' ?> px-3">All</a>
                </div>
                
                <div class="d-flex gap-2">
                    <form class="d-flex">
                        <input type="text" name="q" class="form-control form-control-sm bg-transparent" placeholder="Search plate or model..." value="<?= htmlspecialchars($search) ?>">
                    </form>
                    <button class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                        <i class="bi bi-plus-circle-fill me-2"></i> Add Vehicle
                    </button>
                </div>
            </div>

            <div class="hd-glass overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="background:transparent;">
                        <thead class="bg-light bg-opacity-50 small text-uppercase text-muted">
                            <tr>
                                <th class="ps-4">Vehicle Details</th>
                                <th>Assigned Route</th>
                                <th>Capacity</th>
                                <th>Daily Target</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($fleet_res->num_rows === 0): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No vehicles found.</td></tr>
                            <?php else: while($veh = $fleet_res->fetch_assoc()): 
                                $bg_status = match($veh['status']) {
                                    'active' => 'bg-success',
                                    'maintenance' => 'bg-warning text-dark',
                                    'grounded' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-sm bg-primary bg-opacity-10 text-primary rounded">
                                                <i class="bi bi-truck-front-fill"></i>
                                            </div>
                                            <div>
                                                <div class="mb-1"><span class="plate-badge"><?= htmlspecialchars($veh['reg_no']) ?></span></div>
                                                <div class="small text-muted"><?= htmlspecialchars($veh['model']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-signpost-2 me-1"></i> <?= htmlspecialchars($veh['assigned_route'] ?: 'Unassigned') ?>
                                        </span>
                                    </td>
                                    <td><?= $veh['capacity'] ?> Seater</td>
                                    <td class="text-muted small">KES <?= ksh($veh['target_daily_revenue']) ?></td>
                                    <td>
                                        <span class="badge <?= $bg_status ?> bg-opacity-75 rounded-pill fw-normal px-3">
                                            <?= ucfirst($veh['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" 
                                                onclick="openEditModal(<?= htmlspecialchars(json_encode($veh)) ?>)">
                                            Manage
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="addVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">Add New Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <form method="POST">
                    <input type="hidden" name="action" value="add_vehicle">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Number Plate</label>
                            <input type="text" name="number_plate" class="form-control text-uppercase" required placeholder="KAA 123A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Vehicle Model</label>
                            <input type="text" name="model" class="form-control" required placeholder="Toyota HiAce">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Seating Capacity</label>
                            <input type="number" name="capacity" class="form-control" value="14" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Daily Revenue Target</label>
                            <input type="number" name="target_revenue" class="form-control" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Default Route</label>
                            <select name="assigned_route" class="form-select">
                                <option value="">-- No Route Assigned --</option>
                                <option value="Nairobi - Thika">Nairobi - Thika</option>
                                <option value="Nairobi - Nakuru">Nairobi - Nakuru</option>
                                <option value="Nairobi - Mombasa">Nairobi - Mombasa</option>
                                </select>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill fw-bold">Add to Fleet</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>

<div class="modal fade" id="editVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hd-glass border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">Update Vehicle Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <form method="POST">
                    <input type="hidden" name="action" value="update_vehicle">
                    <input type="hidden" name="vehicle_id" id="edit_id">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Number Plate</label>
                            <input type="text" name="number_plate" id="edit_plate" class="form-control text-uppercase" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Model</label>
                            <input type="text" name="model" id="edit_model" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Capacity</label>
                            <input type="number" name="capacity" id="edit_capacity" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Operational Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="grounded">Grounded</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Assigned Route</label>
                        <select name="assigned_route" id="edit_route" class="form-select">
                            <option value="">-- No Route Assigned --</option>
                            <option value="Nairobi - Thika">Nairobi - Thika</option>
                            <option value="Nairobi - Nakuru">Nairobi - Nakuru</option>
                            <option value="Nairobi - Mombasa">Nairobi - Mombasa</option>
                        </select>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success rounded-pill fw-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openEditModal(veh) {
        document.getElementById('edit_id').value = veh.vehicle_id;
        document.getElementById('edit_plate').value = veh.number_plate;
        document.getElementById('edit_model').value = veh.model;
        document.getElementById('edit_capacity').value = veh.capacity;
        document.getElementById('edit_route').value = veh.assigned_route;
        document.getElementById('edit_status').value = veh.status;
        
        new bootstrap.Modal(document.getElementById('editVehicleModal')).show();
    }
</script>
</body>
</html>