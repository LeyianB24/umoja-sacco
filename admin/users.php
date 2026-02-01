<?php
// usms/admin/users.php
// IT Admin - User Account Management (Redesign: Hope Dashboard Theme)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

// Enforce IT Admin Role
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['full_name'] ?? 'System Admin';
$db = $conn;

$msg = "";
$msg_type = "";

// ---------------------------------------------------------
// 1. HANDLE ACTIONS
// ---------------------------------------------------------

// A. PASSWORD RESET
if (isset($_POST['action']) && $_POST['action'] === 'reset_pass') {
    verify_csrf_token();
    $target_id = intval($_POST['member_id']);
    $temp_pass_raw = substr(str_shuffle("23456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 6);
    $hashed = password_hash($temp_pass_raw, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE members SET password = ?, temp_password = ? WHERE member_id = ?");
    $stmt->bind_param("ssi", $hashed, $temp_pass_raw, $target_id);
    
    if ($stmt->execute()) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'pass_reset', 'Manual password reset for Member #$target_id', '$ip')");
        $msg = "Password reset successful. Temporary code: <strong>$temp_pass_raw</strong>";
        $msg_type = "success";
    } else {
        $msg = "Database error during reset."; $msg_type = "danger";
    }
}

// B. SUSPEND/UNLOCK
if (isset($_POST['action']) && isset($_POST['id'])) {
    verify_csrf_token();
    $target_id = intval($_POST['id']);
    $act = $_POST['action'];
    $new_status = ($act === 'lock') ? 'suspended' : 'active';
    
    $stmt = $db->prepare("UPDATE members SET status = ? WHERE member_id = ?");
    $stmt->bind_param("si", $new_status, $target_id);
    if ($stmt->execute()) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($admin_id, 'status_change', 'Changed Member #$target_id status to $new_status', '$ip')");
        $msg = "User status updated to: " . strtoupper($new_status);
        $msg_type = "warning";
    }
}

// ---------------------------------------------------------
// 2. SEARCH & FETCH
// ---------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? 'all';

$where = [];
$params = [];
$types = "";

if ($search) {
    $where[] = "(full_name LIKE ? OR national_id LIKE ? OR email LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term];
    $types = "sss";
}
if ($filter !== 'all') {
    $where[] = "status = ?";
    $params[] = $filter;
    $types .= "s";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT * FROM members $where_sql ORDER BY join_date DESC LIMIT 50";

$stmt = $db->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$members = $stmt->get_result();

function getInitials($name) { return strtoupper(substr($name ?? 'U', 0, 1)); }
$pageTitle = "User Management";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<div class="d-flex">
    
    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="flex-fill main-content-wrapper">
        
        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <?php if($msg): ?>
            <div class="alert alert-<?= $msg_type ?> rounded-4 border-0 d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-<?= $msg_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>-fill me-2 fs-5"></i>
                <div><?= $msg ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row align-items-end mb-5">
            <div class="col-md-8">
                <h2 class="mb-1">User Management</h2>
                <p class="text-secondary mb-0">Overview of all registered members and access controls.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="dashboard.php" class="btn btn-light rounded-pill px-4 fw-bold border shadow-sm">
                    <i class="bi bi-arrow-left me-2"></i>Dashboard
                </a>
            </div>
        </div>

        <div class="card-dashboard p-4 mb-4">
            <form method="get" class="row g-3 align-items-center">
                <div class="col-lg-5">
                    <div class="search-container">
                        <i class="bi bi-search text-secondary fs-5 me-2"></i>
                        <input type="text" name="q" class="search-input" placeholder="Search by Name, ID or Email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="d-flex align-items-center bg-light rounded-pill px-3 py-1 border border-light">
                        <i class="bi bi-funnel text-secondary me-2"></i>
                        <select name="status" class="form-select border-0 bg-transparent shadow-none" style="cursor: pointer;">
                            <option value="all">All Statuses</option>
                            <option value="active" <?= $filter=='active'?'selected':'' ?>>Active Users</option>
                            <option value="suspended" <?= $filter=='suspended'?'selected':'' ?>>Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-2">
                    <button class="btn btn-brand w-100">Apply Filters</button>
                </div>
                <?php if($search || $filter !== 'all'): ?>
                    <div class="col-lg-1">
                        <a href="users.php" class="btn btn-outline-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="bi bi-x-lg"></i></a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <h5 class="mb-0">Total Members: <?= $members->num_rows ?></h5>
                <button class="btn btn-sm btn-white text-secondary"><i class="bi bi-three-dots"></i></button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>PROFILE & IDENTITY</th>
                            <th>CONTACT DETAILS</th>
                            <th>STATUS</th>
                            <th>JOIN DATE</th>
                            <th class="text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($members->num_rows === 0): ?>
                            <tr><td colspan="5" class="text-center py-5 text-secondary">No members found matching your criteria.</td></tr>
                        <?php else: while($m = $members->fetch_assoc()): 
                            $status_class = ($m['status'] === 'active') ? 'active' : (($m['status'] === 'suspended') ? 'suspended' : 'pending');
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php 
                                    $pic = $m['profile_pic'] ?? '';
                                    $phys_path = __DIR__ . '/../uploads/profile_pics/' . $pic;
                                    $web_path = BASE_URL . '/uploads/profile_pics/' . htmlspecialchars($pic);

                                    if (!empty($pic) && file_exists($phys_path)): ?>
                                        <img src="<?= $web_path ?>" alt="img" class="avatar-box">
                                    <?php else: ?>
                                        <div class="avatar-box fs-5">
                                            <?= getInitials($m['full_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($m['full_name']) ?></div>
                                        <div class="small text-secondary font-monospace">#<?= htmlspecialchars($m['national_id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-medium"><?= htmlspecialchars($m['email']) ?></div>
                                <div class="small text-secondary"><?= htmlspecialchars($m['phone']) ?></div>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?>">
                                    <span class="dot"></span>
                                    <?= strtoupper($m['status']) ?>
                                </span>
                            </td>
                            <td class="text-secondary fw-medium">
                                <?= date('M d, Y', strtotime($m['join_date'])) ?>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-light rounded-circle shadow-sm" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2">
                                        <li>
                                            <a class="dropdown-item rounded-3 py-2" href="#" data-bs-toggle="modal" data-bs-target="#resetModal<?= $m['member_id'] ?>">
                                                <i class="bi bi-key me-2 text-warning"></i> Reset Password
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if($m['status'] === 'active'): ?>
                                            <li>
                                                <form method="post" action="?id=<?= $m['member_id'] ?>" style="display:block;" onsubmit="return confirm('Suspend this user?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="lock">
                                                    <input type="hidden" name="id" value="<?= $m['member_id'] ?>">
                                                    <button type="submit" class="dropdown-item rounded-3 py-2 text-danger">
                                                        <i class="bi bi-slash-circle me-2"></i> Suspend Access
                                                    </button>
                                                </form>
                                            </li>
                                        <?php else: ?>
                                            <li>
                                                <form method="post" action="?id=<?= $m['member_id'] ?>" style="display:block;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="unlock">
                                                    <input type="hidden" name="id" value="<?= $m['member_id'] ?>">
                                                    <button type="submit" class="dropdown-item rounded-3 py-2 text-success">
                                                        <i class="bi bi-check-circle me-2"></i> Restore Access
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>

                                <div class="modal fade" id="resetModal<?= $m['member_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">Reset Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-center">
                                                <div class="avatar-box mx-auto mb-3 bg-light text-primary fs-3" style="width: 60px; height: 60px;">
                                                    <i class="bi bi-shield-lock"></i>
                                                </div>
                                                <p class="text-secondary mb-1">You are about to reset the password for</p>
                                                <h4 class="mb-3"><?= htmlspecialchars($m['full_name']) ?></h4>
                                                <div class="alert alert-warning border-0 small text-start">
                                                    <i class="bi bi-info-circle me-1"></i> This will invalidate their current password and generate a temporary 6-character code.
                                                </div>
                                            </div>
                                            <div class="modal-footer justify-content-between">
                                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="reset_pass">
                                                    <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                    <button type="submit" class="btn btn-lime px-4 shadow-sm">Confirm Reset</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
