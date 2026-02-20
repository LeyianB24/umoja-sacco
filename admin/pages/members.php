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
?>

<?php
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
<?php $layout->header($pageTitle); ?>
<body>
<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content">
        <?php $layout->topbar($pageTitle ?? 'Member Directory'); ?>
        
        <!-- Header -->
        <div class="hp-hero fade-in">
            <div class="hp-hero-content">
                <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3 small letter-spacing-1">MEMBERSHIP CONTROL ENGINE</span>
                <h1 class="display-5 fw-800 mb-2">Member Directory</h1>
                <p class="opacity-75 fs-5 mb-0">Managing the core registry of <span class="text-lime fw-bold"><?= number_format((float)$stats['total']) ?></span> USMS members.</p>
            </div>
            <div class="hp-hero-action">
                <div class="dropdown">
                    <button class="btn btn-lime shadow-lg px-4 dropdown-toggle fw-bold" data-bs-toggle="dropdown">
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

        <div class="container-fluid px-4" style="margin-top: -40px;">
            <?php flash_render(); ?>

            <!-- KPIs -->
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="glass-stat slide-up">
                        <div class="glass-stat-icon bg-lime-soft text-lime">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="glass-stat-label">Active Members</div>
                        <div class="glass-stat-value"><?= number_format((float)$stats['active']) ?></div>
                        <div class="glass-stat-trend text-lime">Authorized & Online</div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="glass-stat slide-up" style="animation-delay: 0.1s">
                        <div class="glass-stat-icon bg-red-soft text-danger">
                            <i class="bi bi-person-x-fill"></i>
                        </div>
                        <div class="glass-stat-label">Suspended</div>
                        <div class="glass-stat-value"><?= number_format((float)$stats['suspended']) ?></div>
                        <div class="glass-stat-trend text-danger">Restricted Access</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="glass-stat slide-up" style="animation-delay: 0.2s">
                        <div class="glass-stat-icon bg-forest-soft text-white">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="glass-stat-label">Pending Review</div>
                        <div class="glass-stat-value"><?= number_format((float)$stats['pending']) ?></div>
                        <div class="glass-stat-trend opacity-50">Awaiting Approval</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="glass-stat slide-up h-100" style="animation-delay: 0.3s">
                        <form method="GET" id="memberFilter" class="p-2 h-100 d-flex flex-column justify-content-center">
                            <label class="small text-muted fw-bold text-uppercase mb-2 d-block letter-spacing-1">Registry Filter</label>
                            <select name="status" class="form-select border-0 bg-white bg-opacity-10 rounded-3 text-white" onchange="this.form.submit()" style="backdrop-filter: blur(10px);">
                                <option value="all" class="text-dark" <?= $filter === 'all' ? 'selected' : '' ?>>All Registrants</option>
                                <option value="active" class="text-dark" <?= $filter === 'active' ? 'selected' : '' ?>>Active Status</option>
                                <option value="suspended" class="text-dark" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="inactive" class="text-dark" <?= $filter === 'inactive' ? 'selected' : '' ?>>Pending (Inactive)</option>
                            </select>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                        </form>
                    </div>
                </div>
            </div>

            <!-- Ledger -->
            <div class="glass-card slide-up" style="animation-delay: 0.4s">
                <div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 border-bottom border-white border-opacity-10">
                    <form method="GET" class="position-relative flex-grow-1" style="max-width: 500px;">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" name="q" class="form-control ps-5 rounded-pill border-0 bg-white bg-opacity-5" placeholder="Search by name, ID or email..." value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="status" value="<?= $filter ?>">
                    </form>
                    <div class="text-muted small fw-medium">
                        Showing latest <span class="text-white"><?= count($members) ?></span> entries
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Identity & Profile</th>
                                <th>Contact Channels</th>
                                <th>Registry Status</th>
                                <th>Onboarding Date</th>
                                <th class="text-end pe-4">Management</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($members)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="opacity-25 mb-4"><i class="bi bi-person-slash display-2"></i></div>
                                        <h5 class="fw-bold text-muted">No Members Found</h5>
                                        <p class="text-muted">No records match your criteria.</p>
                                    </td>
                                </tr>
                            <?php else: 
                            foreach($members as $m): 
                                $status_class = match($m['status']) {
                                    'active' => 'bg-success bg-opacity-10 text-success',
                                    'suspended' => 'bg-danger bg-opacity-10 text-danger',
                                    'inactive' => 'bg-warning bg-opacity-10 text-warning',
                                    default => 'bg-light text-dark border'
                                };
                            ?>
                                <tr class="member-row">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if(!empty($m['profile_pic'])): ?>
                                                <img src="data:image/jpeg;base64,<?= base64_encode($m['profile_pic']) ?>" class="rounded-3 shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-3 shadow-sm bg-lime text-forest d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                                    <?= strtoupper(substr($m['full_name'],0,1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold "><?= esc($m['full_name']) ?></div>
                                                <div class="small text-muted opacity-75">ID: <?= esc($m['national_id']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-600  small"><?= esc($m['email']) ?></div>
                                        <div class="small text-muted"><?= esc($m['phone']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status_class ?> rounded-pill px-3 py-2 fw-bold" style="font-size: 0.65rem;">
                                            <?= strtoupper($m['status'] === 'inactive' ? 'pending' : $m['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold "><?= date('d M, Y', strtotime($m['join_date'])) ?></div>
                                        <div class="small text-muted opacity-75">System Registered</div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <?php if(can('manage_members')): ?>
                                                <?php if($m['status'] == 'inactive'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= $m['member_id'] ?>"><input type="hidden" name="action" value="approve">
                                                        <button class="btn btn-lime btn-sm px-3 fw-bold rounded-pill">Approve</button>
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
                                            <a href="transactions.php?member_id=<?= $m['member_id'] ?>" class="btn btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="View Ledger">
                                                <i class="bi bi-journal-text text-forest small"></i>
                                            </a>
                                            <a href="member_profile.php?id=<?= $m['member_id'] ?>" class="btn btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="View Profile">
                                                <i class="bi bi-person-lines-fill text-forest small"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php $layout->footer(); ?>
        </div>
    </div>
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
