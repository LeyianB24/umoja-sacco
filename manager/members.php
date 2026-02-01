<?php
// manager/members.php
// Operations Manager - Member Vetting & Directory

// 1. SESSION & CONFIG
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/functions.php';

// 2. SECURITY & AUTH
// Enforce Manager Role
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['manager', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$admin_id   = $_SESSION['admin_id'];
$db = $conn;

// 3. HANDLE ACTIONS (Post-Redirect-Get Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Validate CSRF
    verify_csrf_token();

    $member_id = intval($_POST['member_id']);
    $action    = $_POST['action'];
    
    if ($member_id > 0) {
        $new_status = '';
        $log_desc   = '';
        $notify_msg = '';

        switch ($action) {
            case 'approve':
                $new_status = 'active';
                $log_desc   = "Approved Member #$member_id";
                $notify_msg = "Your account has been verified and activated. Welcome!";
                break;
            case 'suspend':
                $new_status = 'suspended';
                $log_desc   = "Suspended Member #$member_id";
                $notify_msg = "Your account has been suspended. Please contact support.";
                break;
            case 'reactivate':
                $new_status = 'active';
                $log_desc   = "Re-activated Member #$member_id";
                $notify_msg = "Your suspension has been lifted.";
                break;
        }

        if ($new_status) {
            $db->begin_transaction();
            try {
                // A. Update Status
                $stmt = $db->prepare("UPDATE members SET status = ? WHERE member_id = ?");
                $stmt->bind_param("si", $new_status, $member_id);
                $stmt->execute();

                // B. Audit Log
                // Check if table exists to prevent crash on new setups
                $chk_audit = $db->query("SHOW TABLES LIKE 'audit_logs'");
                if($chk_audit->num_rows > 0) {
                    $stmt_log = $db->prepare("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES (?, 'member_update', ?, ?)");
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $stmt_log->bind_param("iss", $admin_id, $log_desc, $ip);
                    $stmt_log->execute();
                }

                // C. Notify Member
                $chk_notif = $db->query("SHOW TABLES LIKE 'notifications'");
                if($chk_notif->num_rows > 0) {
                    $stmt_n = $db->prepare("INSERT INTO notifications (user_type, user_id, message, status, created_at) VALUES ('member', ?, ?, 'unread', NOW())");
                    $stmt_n->bind_param("is", $member_id, $notify_msg);
                    $stmt_n->execute();
                }

                $db->commit();
                
                // Set Flash Message
                flash_set("Member status updated to " . strtoupper($new_status), "success");

            } catch (Exception $e) {
                $db->rollback();
                flash_set("Error updating member: " . $e->getMessage(), "danger");
            }
        }
    }
    
    // Redirect to clear POST data
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
    exit;
}

// 4. FETCH DATA & KPIs
// Flash Message Retrieval
$msg = $_SESSION['flash_msg'] ?? '';
$msg_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// KPIs
$stats = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) as suspended
    FROM members")->fetch_assoc();

// Filter Logic
$filter = $_GET['status'] ?? 'active'; // Default to active for cleaner initial view
$search = trim($_GET['q'] ?? '');

$where_clauses = [];
$params = [];
$types = "";

if ($filter !== 'all') {
    $where_clauses[] = "status = ?";
    $params[] = $filter;
    $types .= "s";
}

if ($search) {
    $where_clauses[] = "(full_name LIKE ? OR national_id LIKE ? OR phone LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= "sss";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch Members
$sql = "SELECT member_id, full_name, national_id, email, phone, profile_pic, status, join_date 
        FROM members $where_sql 
        ORDER BY join_date DESC LIMIT 50";
$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$members_res = $stmt->get_result();

$pageTitle = "Member Directory";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> - Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="manager-body">

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Member Directory</h4>
                    <p class="text-muted small mb-0">Manage member verification and account status.</p>
                </div>
            </div>

            <?php flash_render(); ?>

            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-sm-6">
                    <div class="glass-card p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small text-uppercase fw-bold mb-1">Total Members</p>
                            <h4 class="mb-0 fw-bold text-dark"><?= $stats['total'] ?></h4>
                        </div>
                        <div class="icon-box bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="glass-card p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small text-uppercase fw-bold mb-1">Active</p>
                            <h4 class="mb-0 fw-bold text-success"><?= $stats['active'] ?></h4>
                        </div>
                        <div class="icon-box bg-success bg-opacity-10 text-success p-3 rounded-circle">
                            <i class="bi bi-check-lg fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="glass-card p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small text-uppercase fw-bold mb-1">Pending Review</p>
                            <h4 class="mb-0 fw-bold text-warning"><?= $stats['pending'] ?></h4>
                        </div>
                        <div class="icon-box bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                            <i class="bi bi-hourglass-split fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="glass-card p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small text-uppercase fw-bold mb-1">Suspended</p>
                            <h4 class="mb-0 fw-bold text-danger"><?= $stats['suspended'] ?></h4>
                        </div>
                        <div class="icon-box bg-danger bg-opacity-10 text-danger p-3 rounded-circle">
                            <i class="bi bi-ban fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card p-3 mb-4">
                <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                    <div class="btn-group shadow-sm" role="group">
                        <a href="?status=active" class="btn btn-sm btn-outline-secondary <?= $filter=='active'?'active':'' ?>">Active</a>
                        <a href="?status=inactive" class="btn btn-sm btn-outline-secondary <?= $filter=='inactive'?'active':'' ?>">Pending</a>
                        <a href="?status=suspended" class="btn btn-sm btn-outline-secondary <?= $filter=='suspended'?'active':'' ?>">Suspended</a>
                        <a href="?status=all" class="btn btn-sm btn-outline-secondary <?= $filter=='all'?'active':'' ?>">All</a>
                    </div>

                    <form class="d-flex" method="GET">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Search by name, ID, email..." value="<?= htmlspecialchars($search) ?>" style="min-width: 250px;">
                        </div>
                    </form>
                </div>
            </div>

            <div class="glass-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover table-manager align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Member Profile</th>
                                <th>Contact Details</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($members_res->num_rows == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="text-muted opacity-50 mb-2"><i class="bi bi-person-x display-4"></i></div>
                                        <p class="text-muted mb-0">No members found matching your criteria.</p>
                                    </td>
                                </tr>
                            <?php else: while($m = $members_res->fetch_assoc()): 
                                // Status styling
                                $statusClass = match($m['status']) {
                                    'active' => 'status-active',
                                    'inactive' => 'status-inactive',
                                    'suspended' => 'status-suspended',
                                    default => 'bg-secondary text-white'
                                };
                                
                                // Image handling
                                $img = !empty($m['profile_pic']) 
                                    ? 'data:image/jpeg;base64,' . base64_encode($m['profile_pic']) 
                                    : BASE_URL . '/public/assets/images/default_user.png';
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= $img ?>" class="rounded-circle avatar-circle" alt="User">
                                            <div>
                                                <div class="fw-bold text-dark mb-0"><?= esc($m['full_name']) ?></div>
                                                <div class="small text-muted font-monospace"><i class="bi bi-card-text me-1"></i><?= esc($m['national_id']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column small">
                                            <span class="text-dark mb-1"><i class="bi bi-envelope me-2 text-muted"></i><?= esc($m['email']) ?></span>
                                            <span class="text-muted"><i class="bi bi-telephone me-2"></i><?= esc($m['phone']) ?></span>
                                        </div>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M d, Y', strtotime($m['join_date'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill badge-status <?= $statusClass ?>">
                                            <?= ucfirst($m['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border rounded-circle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 p-1">
                                                <li><a class="dropdown-item small rounded mb-1" href="member_profile.php?id=<?= $m['member_id'] ?>"><i class="bi bi-eye me-2"></i>View Profile</a></li>
                                                
                                                <?php if($m['status'] !== 'active'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                            <input type="hidden" name="action" value="<?= $m['status'] == 'suspended' ? 'reactivate' : 'approve' ?>">
                                                            <button class="dropdown-item small text-success rounded fw-bold"><i class="bi bi-check-lg me-2"></i>Activate Access</button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if($m['status'] === 'active'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to suspend this member? They will lose access to their dashboard.');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                            <input type="hidden" name="action" value="suspend">
                                                            <button class="dropdown-item small text-danger rounded"><i class="bi bi-ban me-2"></i>Suspend Account</button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
