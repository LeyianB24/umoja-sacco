<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// AUTH CHECK
require_admin();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_permission(); 

// DB & SETUP
$db = $conn;
$admin_name = $_SESSION['full_name'] ?? 'System Admin';

// SEARCH LOGIC
$search = trim($_GET['q'] ?? '');
$where = "";
$params = [];
$types  = "";

if ($search !== "") {
    $where = "WHERE (a.action LIKE ? OR a.details LIKE ? OR ad.username LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term];
    $types  = "sss";
}

// HANDLE EXPORT ACTIONS
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {

    // Re-Search for Export (Full list or current filter)
    $where_e = "";
    $params_e = [];
    $types_e  = "";

    if ($search !== "") {
        $where_e = "WHERE (a.action LIKE ? OR a.details LIKE ? OR ad.username LIKE ?)";
        $params_e = [$term, $term, $term];
        $types_e  = "sss";
    }

    $query_e = "
        SELECT a.*, ad.username, r.name as role, ad.full_name
        FROM audit_logs a
        LEFT JOIN admins ad ON a.admin_id = ad.admin_id
        LEFT JOIN roles r ON ad.role_id = r.id
        $where_e
        ORDER BY a.created_at DESC
        LIMIT 1000
    ";
    
    $stmt_e = $db->prepare($query_e);
    if (!empty($params_e)) $stmt_e->bind_param($types_e, ...$params_e);
    $stmt_e->execute();
    $export_logs = $stmt_e->get_result();

    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    while($row = $export_logs->fetch_assoc()) {
        $name = $row['full_name'] ?? $row['username'] ?? 'System';
        $data[] = [
            'Time' => date("d-M-Y H:i", strtotime($row['created_at'])),
            'Actor' => $name,
            'Role' => ucfirst($row['role'] ?? 'System'),
            'Action' => ucwords(str_replace('_', ' ', $row['action'])),
            'Details' => $row['details'],
            'IP' => $row['ip_address']
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'System Audit Logs',
        'module' => 'Security Audit',
        'headers' => ['Time', 'Actor', 'Role', 'Action', 'Details', 'IP'],
        'orientation' => 'L' // Landscape for audit logs due to details length
    ]);
    exit;
}

// FETCH AUDITS
$query = "
    SELECT a.*, ad.username, r.name as role, ad.full_name
    FROM audit_logs a
    LEFT JOIN admins ad ON a.admin_id = ad.admin_id
    LEFT JOIN roles r ON ad.role_id = r.id
    $where
    ORDER BY a.created_at DESC
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// HELPERS
function getInitials($name) {
    return strtoupper(substr($name ?? 'S', 0, 1));
}

function getActionStyle($action) {
    $a = strtolower($action);
    
    // Critical/Destructive -> Red (Standard Alert)
    if (str_contains($a, 'delete') || str_contains($a, 'fail') || str_contains($a, 'error') || str_contains($a, 'lock'))
        return ['class' => 'badge-soft-danger', 'icon' => 'bi-exclamation-octagon-fill'];

    // Updates/Changes -> Warning/Orange
    if (str_contains($a, 'update') || str_contains($a, 'edit') || str_contains($a, 'suspend'))
        return ['class' => 'badge-soft-warning', 'icon' => 'bi-pencil-square'];

    // Positive/Creation -> BRAND MINT/LIME
    if (str_contains($a, 'create') || str_contains($a, 'add') || str_contains($a, 'approve') || str_contains($a, 'unlock'))
        return ['class' => 'badge-soft-lime', 'icon' => 'bi-check-circle-fill'];

    // Access -> BRAND EMERALD
    if (str_contains($a, 'login'))
        return ['class' => 'badge-soft-emerald', 'icon' => 'bi-arrow-right-circle-fill'];

    // Default -> Gray
    return ['class' => 'badge-soft-secondary', 'icon' => 'bi-activity'];
}

$pageTitle = "Audit Logs";
?>
<?php $layout->header($pageTitle); ?>
<style>
        .avatar-box { width: 42px; height: 42px; border-radius: 14px; background-color: #f1f5f9; color: var(--forest-deep); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; }
        .badge-pill { padding: 8px 14px; border-radius: 30px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 8px; }
        .badge-soft-lime { background-color: var(--mint-pale); color: var(--emerald-rich); }
        .badge-soft-emerald { background-color: #d1fae5; color: var(--forest-deep); }
        .badge-soft-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-soft-warning { background-color: #fef3c7; color: #92400e; }
        .badge-soft-secondary { background-color: #f1f5f9; color: #64748b; }

        .text-forest { color: var(--forest-deep); }
        .text-emerald { color: var(--emerald-rich); }
    </style>

</head>
<body>

<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="row align-items-end mb-5">
            <div class="col-md-7">
                <h2 class="fw-bold mb-1 display-6" style="letter-spacing: -0.03em;">System Audit Trails</h2>
                <p class="text-muted mb-0">Track admin activities and security events in real-time.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex justify-content-end gap-2">
                <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank" class="btn btn-hope-outline">
                    <i class="bi bi-printer me-2"></i> Print
                </a>
                <div class="btn-group">
                    <button type="button" class="btn btn-dark rounded-pill px-4 fw-bold dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-2"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg">
                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Export PDF</a></li>
                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Export Excel</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <form method="GET">
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1 search-wrapper d-flex align-items-center bg-white">
                            <i class="bi bi-search text-emerald ps-3 pe-2 fs-5"></i>
                            <input type="text" name="q" class="search-input w-100" 
                                   placeholder="Search logs by actor, action, or details..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <button type="submit" class="btn btn-hope-primary shadow-sm">
                            Search Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-hope">
            <div class="px-4 py-3 border-bottom border-light d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-circle bg-success p-2 border border-white border-2"></span>
                    <h6 class="mb-0 fw-bold text-forest">Recent Activity</h6>
                </div>
                <span class="text-muted small fw-semibold bg-light px-3 py-1 rounded-pill">
                    <?= $logs->num_rows ?> Records Found
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hope mb-0">
                    <thead class="bg-light bg-opacity-50">
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Action Type</th>
                            <th style="width: 35%;">Details</th>
                            <th class="text-end">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-tree text-emerald opacity-25" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2 fw-semibold">No audit logs found.</p>
                                </td>
                            </tr>
                        <?php else: while ($row = $logs->fetch_assoc()):
                            $style = getActionStyle($row['action']);
                            $name  = $row['full_name'] ?? $row['username'] ?? 'System';
                            $role  = ucfirst($row['role'] ?? 'System');
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-forest"><?= date("H:i", strtotime($row['created_at'])) ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date("M d, Y", strtotime($row['created_at'])) ?></small>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-box">
                                            <?= getInitials($name) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-emerald"><?= htmlspecialchars($name) ?></div>
                                            <div class="small text-muted"><?= $role ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge-pill <?= $style['class'] ?>">
                                        <i class="bi <?= $style['icon'] ?>"></i>
                                        <?= ucwords(str_replace('_', ' ', $row['action'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="text-forest opacity-75">
                                        <?= htmlspecialchars($row['details']) ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="font-monospace small text-muted bg-light px-2 py-1 rounded border border-light">
                                        <?= htmlspecialchars($row['ip_address'] ?? '::1') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="px-4 py-3 border-top border-light text-center">
                <small class="text-muted fw-bold">Showing latest 200 entries</small>
            </div>
            <?php $layout->footer(); ?>
        </div>
    </div>
</div>
