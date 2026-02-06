<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');
// admin/members.php

require_permission();

// 1. HANDLE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $target_id = intval($_POST['member_id']);
    $action = $_POST['action'];

    if (in_array($action, ['approve', 'suspend', 'reactivate'])) {
        if (!can('manage_members')) {
            flash_set("Access Denied: manage_members permission required.", "danger");
        } else {
            $new_status = match($action) {
                'approve'    => 'active',
                'suspend'    => 'suspended',
                'reactivate' => 'active',
                default      => null
            };

            if ($new_status) {
                $stmt = $conn->prepare("UPDATE members SET status = ? WHERE member_id = ?");
                $stmt->bind_param("si", $new_status, $target_id);
                if ($stmt->execute()) {
                    flash_set("Member #$target_id updated to $new_status.", "success");
                }
            }
        }
    }
    header("Location: members.php");
    exit;
}

// 2. DEFINE FILTERS
$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = "";

if ($filter !== 'all') {
    $where[] = "status = ?";
    $params[] = $filter;
    $types .= "s";
}
if ($search) {
    if (is_numeric($search)) {
        $where[] = "national_id LIKE ?";
    } else {
        $where[] = "(full_name LIKE ? OR email LIKE ?)";
    }
    $term = "%$search%";
    if (is_numeric($search)) {
        $params[] = $term;
        $types .= "s";
    } else {
        $params[] = $term; $params[] = $term;
        $types .= "ss";
    }
}

// 2b. HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    $where_sql_export = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    $sql_export = "SELECT * FROM members $where_sql_export ORDER BY join_date DESC";
    $stmt_e = $conn->prepare($sql_export);
    if (!empty($params)) $stmt_e->bind_param($types, ...$params);
    $stmt_e->execute();
    $export_members = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    foreach ($export_members as $m) {
        $data[] = [
            'Name' => $m['full_name'],
            'National ID' => $m['national_id'],
            'Phone' => $m['phone'],
            'Email' => $m['email'],
            'Status' => strtoupper($m['status']),
            'Joined' => date('d-M-Y', strtotime($m['join_date']))
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Member Directory',
        'module' => 'Member Management',
        'headers' => ['Name', 'National ID', 'Phone', 'Email', 'Status', 'Joined']
    ]);
    exit;
}

// 3. FETCH DATA
$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT * FROM members $where_sql ORDER BY join_date DESC LIMIT 500";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// KPIs
$stats = $conn->query("SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) as suspended,
    SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as pending
    FROM members")->fetch_assoc();

$pageTitle = "Member Directory";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | USMS Administration</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest: #0f2e25;
            --forest-light: #1a4d3d;
            --lime: #d0f35d;
            --lime-dark: #a8cf12;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(15, 46, 37, 0.05);
            --glass-shadow: 0 10px 40px rgba(15, 46, 37, 0.06);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f4f7f6;
            color: var(--forest);
        }

        .main-content { margin-left: 280px; padding: 30px; transition: 0.3s; }
        
        /* Banner Styles */
        .portal-header {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
            position: relative; overflow: hidden;
        }

        /* Stat Cards */
        .stat-card {
            background: white; border-radius: 24px; padding: 25px;
            box-shadow: var(--glass-shadow); border: 1px solid var(--glass-border);
            height: 100%; transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(15, 46, 37, 0.08); }

        .icon-circle {
            width: 50px; height: 50px; border-radius: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; margin-bottom: 15px;
        }
        .bg-lime-soft { background: rgba(208, 243, 93, 0.2); color: var(--forest); }
        .bg-forest-soft { background: rgba(15, 46, 37, 0.05); color: var(--forest); }
        .bg-red-soft { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* Ledger Table */
        .ledger-container {
            background: white; border-radius: 28px; 
            box-shadow: var(--glass-shadow); border: 1px solid var(--glass-border);
            overflow: hidden;
        }
        .ledger-header { padding: 30px; border-bottom: 1px solid #f1f5f9; background: #fff; }
        
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th {
            background: #f8fafc; color: #64748b; font-weight: 700;
            text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;
            padding: 18px 25px; border-bottom: 2px solid #edf2f7;
        }
        .table-custom tbody td {
            padding: 18px 25px; border-bottom: 1px solid #f1f5f9;
            vertical-align: middle; font-size: 0.95rem;
        }
        .table-custom tbody tr:hover td { background-color: #fcfdfe; }

        /* Avatars */
        .member-avatar {
            width: 44px; height: 44px; border-radius: 12px;
            object-fit: cover; background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            display: flex; align-items: center; justify-content: center;
            color: var(--lime); font-weight: 800; font-size: 1rem;
            box-shadow: 0 4px 10px rgba(15, 46, 37, 0.1);
        }

        /* Statuses */
        .status-pill {
            padding: 6px 14px; border-radius: 10px; font-size: 0.7rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .status-active { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .status-suspended { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .status-pending { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }

        .btn-lime {
            background: var(--lime); color: var(--forest);
            border-radius: 12px; font-weight: 800; border: none; padding: 10px 20px;
            transition: 0.3s;
        }
        .btn-lime:hover { background: var(--lime-dark); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(208, 243, 93, 0.3); }

        .btn-outline-forest {
            background: transparent; border: 2px solid var(--forest); color: var(--forest);
            border-radius: 12px; font-weight: 700; padding: 8px 18px; transition: 0.3s;
        }
        .btn-outline-forest:hover { background: var(--forest); color: white; }

        .search-box {
            background: #f8fafc; border: none; border-radius: 12px;
            padding: 10px 15px 10px 40px; width: 100%; transition: 0.3s;
        }
        .search-box:focus { background: white; box-shadow: 0 0 0 4px rgba(15, 46, 37, 0.05); outline: none; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .fade-in { animation: fadeIn 0.6s ease-out; }
        .slide-up { animation: slideUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (max-width: 991.98px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? ''); ?>
        
        <!-- Header -->
        <div class="portal-header fade-in">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3 small">Membership Control Engine</span>
                    <h1 class="display-5 fw-800 mb-2">Member Directory</h1>
                    <p class="opacity-75 fs-5 mb-0">Managing the core registry of <?= number_format((float)$stats['total']) ?> USMS members.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <div class="dropdown">
                        <button class="btn btn-lime shadow-lg px-4 dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-2"></i>Export Registry
                        </button>
                        <ul class="dropdown-menu shadow-lg border-0 mt-2">
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Full List (PDF)</a></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Spreadsheet (XLS)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Print Registry</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php flash_render(); ?>

        <!-- KPIs -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card slide-up">
                    <div class="icon-circle bg-lime-soft">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="text-muted small fw-bold text-uppercase">Active Members</div>
                    <div class="h2 fw-800 text-dark mt-2 mb-0"><?= number_format((float)$stats['active']) ?></div>
                    <div class="small text-muted mt-1">Authorized & Online</div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card slide-up" style="animation-delay: 0.1s">
                    <div class="icon-circle bg-red-soft">
                        <i class="bi bi-person-x-fill"></i>
                    </div>
                    <div class="text-muted small fw-bold text-uppercase">Suspended</div>
                    <div class="h2 fw-800 text-dark mt-2 mb-0"><?= number_format((float)$stats['suspended']) ?></div>
                    <div class="small text-muted mt-1">Restricted access</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card slide-up" style="animation-delay: 0.2s">
                    <div class="icon-circle bg-forest-soft">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="text-muted small fw-bold text-uppercase">Pending Review</div>
                    <div class="h2 fw-800 text-dark mt-2 mb-0"><?= number_format((float)$stats['pending']) ?></div>
                    <div class="small text-muted mt-1">Awaiting approval</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card slide-up" style="animation-delay: 0.3s">
                    <form method="GET" id="memberFilter" class="h-100 d-flex flex-column justify-content-between">
                        <div>
                            <label class="small text-muted fw-bold text-uppercase mb-2 d-block">Status Filter</label>
                            <select name="status" class="form-select border-0 bg-light rounded-3" onchange="this.form.submit()">
                                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Registrants</option>
                                <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active Status</option>
                                <option value="suspended" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Pending (Inactive)</option>
                            </select>
                        </div>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    </form>
                </div>
            </div>
        </div>

        <!-- Ledger -->
        <div class="ledger-container slide-up" style="animation-delay: 0.4s">
            <div class="ledger-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <form method="GET" class="position-relative flex-grow-1" style="max-width: 500px;">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" name="q" class="search-box" placeholder="Search by name, National ID or email..." value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="status" value="<?= $filter ?>">
                </form>
                <div class="text-muted small fw-medium">
                    Showing latest <?= count($members) ?> entries
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table-custom" id="memberTable">
                    <thead>
                        <tr>
                            <th>Identity & Profile</th>
                            <th>Contact Channels</th>
                            <th>Registry Status</th>
                            <th>Onboarding Date</th>
                            <th class="text-end">Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($members)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="opacity-25 mb-4"><i class="bi bi-person-slash display-2"></i></div>
                                    <h5 class="fw-bold text-muted">No Members Found</h5>
                                    <p class="text-muted">No records match your current search or filter criteria.</p>
                                </td>
                            </tr>
                        <?php else: 
                        foreach($members as $m): 
                            $status_class = match($m['status']) {
                                'active' => 'status-active',
                                'suspended' => 'status-suspended',
                                'inactive' => 'status-pending',
                                default => 'bg-light text-dark border'
                            };
                        ?>
                            <tr class="member-row">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if(!empty($m['profile_pic'])): ?>
                                            <img src="data:image/jpeg;base64,<?= base64_encode($m['profile_pic']) ?>" class="member-avatar">
                                        <?php else: ?>
                                            <div class="member-avatar"><?= strtoupper(substr($m['full_name'],0,1)) ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-800 text-dark"><?= esc($m['full_name']) ?></div>
                                            <div class="small text-muted opacity-75 mt-1">ID: <?= esc($m['national_id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-600 text-dark small"><?= esc($m['email']) ?></div>
                                    <div class="small text-muted mt-1"><?= esc($m['phone']) ?></div>
                                </td>
                                <td>
                                    <span class="status-pill <?= $status_class ?>">
                                        <?= strtoupper($m['status'] === 'inactive' ? 'pending' : $m['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><?= date('d M, Y', strtotime($m['join_date'])) ?></div>
                                    <div class="small text-muted opacity-75 mt-1">System Registered</div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <?php if(can('manage_members')): ?>
                                            <?php if($m['status'] == 'inactive'): ?>
                                                <form method="POST" class="d-inline">
                                                    <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= $m['member_id'] ?>"><input type="hidden" name="action" value="approve">
                                                    <button class="btn btn-lime btn-sm px-3 fw-bold">Approve</button>
                                                </form>
                                            <?php elseif($m['status'] == 'active'): ?>
                                                <form method="POST" class="d-inline">
                                                    <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= $m['member_id'] ?>"><input type="hidden" name="action" value="suspend">
                                                    <button class="btn btn-outline-danger btn-sm px-3 fw-bold rounded-pill">Suspend</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= $m['member_id'] ?>"><input type="hidden" name="action" value="reactivate">
                                                    <button class="btn btn-outline-success btn-sm px-3 fw-bold rounded-pill">Reactivate</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <a href="transactions.php?member_id=<?= $m['member_id'] ?>" class="btn btn-light rounded-circle shadow-sm" title="View Transaction Ledger">
                                            <i class="bi bi-journal-text text-forest"></i>
                                        </a>
                                        <a href="member_profile.php?id=<?= $m['member_id'] ?>" class="btn btn-light rounded-circle shadow-sm" title="View Profile">
                                            <i class="bi bi-person-lines-fill text-forest"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php $layout->footer(); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/main.js?v=<?= time() ?>"></script>
<script>
    // Real-time Ledger Search
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.member-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>
