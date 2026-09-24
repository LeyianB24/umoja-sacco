<?php
// admin/members.php
// Unified Member Management Console (Shared by all Admin Roles)
// Design: Hope UI Premium - Forest Green & Lime Theme

if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/functions.php';

// 1. AUTHENTICATION & ROLE CHECK
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'accountant', 'superadmin'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$my_role = $_SESSION['role'];
$my_id   = $_SESSION['admin_id'] ?? 0;
$db      = $conn;

$success_msg = "";
$error_msg   = "";

// Helper: Permissions Check
$is_it_admin    = in_array($my_role, ['admin', 'superadmin']);
$is_manager     = in_array($my_role, ['manager', 'superadmin']);
$is_accountant  = in_array($my_role, ['accountant', 'superadmin']);

// 2. HANDLE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $target_id = intval($_POST['member_id']);
    $action    = $_POST['action'];

    // A. STATUS UPDATES (Requires Management Permission)
    if (in_array($action, ['approve', 'suspend', 'reactivate'])) {
        if (!$is_manager) {
            $error_msg = "Access Denied: You do not have permission to modify member status.";
        } else {
            $new_status = match($action) {
                'approve'    => 'active',
                'suspend'    => 'suspended',
                'reactivate' => 'active',
                default      => null
            };

            if ($new_status) {
                $db->begin_transaction();
                try {
                    $stmt = $db->prepare("UPDATE members SET status = ? WHERE member_id = ?");
                    $stmt->bind_param("si", $new_status, $target_id);
                    $stmt->execute();

                    // Audit Log
                    $log_desc = ucfirst($action) . " Member #$target_id";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($my_id, 'member_update', '$log_desc', '$ip')");

                    // Notification
                    $notif_msg = match($action) {
                        'approve'    => "Your account has been verified and activated. Welcome!",
                        'suspend'    => "Your account has been suspended. Please contact support.",
                        'reactivate' => "Your account suspension has been lifted.",
                    };
                    $conn->query("INSERT INTO notifications (member_id, title, message, status) VALUES ($target_id, 'Account Update', '$notif_msg', 'unread')");

                    $db->commit();
                    $success_msg = "Member status successfully updated to " . strtoupper($new_status);
                } catch (Exception $e) {
                    $db->rollback();
                    $error_msg = "System error: " . $e->getMessage();
                }
            }
        }
    }

    // B. PASSWORD RESET (Requires IT Admin Permission)
    if ($action === 'reset_pass') {
        if (!$is_it_admin) {
            $error_msg = "Access Denied: You do not have permission to reset passwords.";
        } else {
            $temp_pass = substr(str_shuffle("23456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 6);
            $hashed = password_hash($temp_pass, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE members SET password = ?, temp_password = ? WHERE member_id = ?");
            $stmt->bind_param("ssi", $hashed, $temp_pass, $target_id);
            if ($stmt->execute()) {
                $ip = $_SERVER['REMOTE_ADDR'];
                $db->query("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES ($my_id, 'pass_reset', 'Manual reset for Member #$target_id', '$ip')");
                $success_msg = "Password reset successful. Temporary Code: <strong>$temp_pass</strong>";
            }
        }
    }
}

// 3. FETCH DATA & FILTERS
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
    $where[] = "(full_name LIKE ? OR national_id LIKE ? OR email LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= "sss";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT member_id, full_name, national_id, email, phone, profile_pic, status, join_date 
        FROM members $where_sql 
        ORDER BY join_date DESC";

$stmt = $db->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function getInitials($name) { return strtoupper(substr($name ?? 'U', 0, 1)); }

$pageTitle = "Member Management";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-fluid py-4 px-lg-5">
    
    <!-- Top KPI Bar (Conditional based on role) -->
    <div class="row g-4 mb-5">
        <?php 
        $st = $db->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as pending
            FROM members")->fetch_assoc();
        ?>
        <div class="col-md-4">
            <div class="card-premium p-4 d-flex align-items-center justify-content-between border-0 shadow-sm" style="background: var(--brand-forest); color: white;">
                <div>
                    <h6 class="text-uppercase small opacity-75 fw-bold mb-1">Total Members</h6>
                    <h2 class="mb-0 fw-bold"><?= $st['total'] ?></h2>
                </div>
                <div class="icon-box bg-white bg-opacity-10 p-3 rounded-circle text-white">
                    <i class="bi bi-people fs-4"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-premium p-4 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-uppercase small text-muted fw-bold mb-1">Active Accounts</h6>
                    <h2 class="mb-0 fw-bold text-success"><?= $st['active'] ?></h2>
                </div>
                <div class="icon-box bg-success bg-opacity-10 text-success p-3 rounded-circle">
                    <i class="bi bi-check-circle fs-4"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-premium p-4 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-uppercase small text-muted fw-bold mb-1">Pending Approval</h6>
                    <h2 class="mb-0 fw-bold text-warning"><?= $st['pending'] ?></h2>
                </div>
                <div class="icon-box bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="card-premium p-3 mb-4 shadow-sm">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control bg-light border-0 py-2 shadow-none" placeholder="Search by Name, ID, or Email..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-lg-3">
                <select name="status" class="form-select bg-light border-0 py-2 shadow-none" onchange="this.form.submit()">
                    <option value="all">All Statuses</option>
                    <option value="active" <?= $filter=='active'?'selected':'' ?>>Active Only</option>
                    <option value="inactive" <?= $filter=='inactive'?'selected':'' ?>>Pending Review</option>
                    <option value="suspended" <?= $filter=='suspended'?'selected':'' ?>>Suspended</option>
                </select>
            </div>
            <div class="col-lg-2">
                <button class="btn btn-dark bg-brand-forest w-100 py-2 rounded-pill fw-bold">Filter</button>
            </div>
            <?php if($search || $filter !== 'all'): ?>
                <div class="col-lg-2">
                    <a href="admin_members.php" class="btn btn-outline-secondary w-100 py-2 rounded-pill fw-bold">Clear All</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if($success_msg): ?>
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4"><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4"><?= $error_msg ?></div>
    <?php endif; ?>

    <!-- Main Table -->
    <div class="card-premium p-0 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 border-0">Member Profile</th>
                        <th class="py-3 border-0">Contact Details</th>
                        <th class="py-3 border-0">Account Status</th>
                        <th class="py-3 border-0">Join Date</th>
                        <th class="pe-4 py-3 border-0 text-end">Management</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($members)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No members found matching your criteria.</td></tr>
                    <?php else: foreach($members as $m): 
                        $status_badge = match($m['status']) {
                            'active'    => 'success',
                            'inactive'  => 'warning',
                            'suspended' => 'danger',
                            default     => 'secondary'
                        };
                        
                        // Image Path Logic
                        $img_src = "";
                        if (!empty($m['profile_pic'])) {
                            $img_src = 'data:image/jpeg;base64,' . base64_encode($m['profile_pic']);
                        }
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <?php if($img_src): ?>
                                    <img src="<?= $img_src ?>" class="rounded-circle shadow-sm" style="width: 45px; height: 45px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-brand-forest text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 45px; height: 45px;">
                                        <?= getInitials($m['full_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($m['full_name']) ?></div>
                                    <small class="text-muted font-monospace">#<?= htmlspecialchars($m['national_id']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark"><?= htmlspecialchars($m['email']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($m['phone']) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $status_badge ?> bg-opacity-10 text-<?= $status_badge ?> rounded-pill px-3 py-2 fw-bold text-uppercase" style="font-size: 0.65rem;">
                                <?= $m['status'] ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= date('M d, Y', strtotime($m['join_date'])) ?></td>
                        <td class="pe-4 text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border-0 bg-light rounded-pill px-3 fw-bold" type="button" data-bs-toggle="dropdown">
                                    Manage <i class="bi bi-chevron-down ms-1"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2 mt-2">
                                    <li><a class="dropdown-item py-2 rounded-3" href="member_profile.php?id=<?= $m['member_id'] ?>"><i class="bi bi-person-lines-fill me-2"></i>View Portfolio</a></li>
                                    
                                    <?php if($is_manager): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if($m['status'] === 'inactive'): ?>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button class="dropdown-item py-2 rounded-3 text-success fw-bold"><i class="bi bi-check-circle-fill me-2"></i>Approve Account</button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if($m['status'] === 'active'): ?>
                                            <li>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Suspend this member?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <button class="dropdown-item py-2 rounded-3 text-danger"><i class="bi bi-slash-circle me-2"></i>Suspend Membership</button>
                                                </form>
                                            </li>
                                        <?php elseif($m['status'] === 'suspended'): ?>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                    <input type="hidden" name="action" value="reactivate">
                                                    <button class="dropdown-item py-2 rounded-3 text-success"><i class="bi bi-arrow-repeat me-2"></i>Reactivate Access</button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if($is_it_admin): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item py-2 rounded-3 text-warning" data-bs-toggle="modal" data-bs-target="#resetModal<?= $m['member_id'] ?>">
                                                <i class="bi bi-shield-lock-fill me-2"></i>Reset Password
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <!-- Password Reset Modal -->
                            <div class="modal fade" id="resetModal<?= $m['member_id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-5 shadow-lg overflow-hidden">
                                        <div class="modal-header bg-warning py-3">
                                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-key-fill me-2"></i>Security Action</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 text-center">
                                            <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px;">
                                                <i class="bi bi-shield-lock display-6"></i>
                                            </div>
                                            <h4 class="fw-bold">Regenerate Credentials?</h4>
                                            <p class="text-muted">You are about to reset the password for <strong><?= htmlspecialchars($m['full_name']) ?></strong>.</p>
                                            <div class="alert alert-warning border-0 small text-start">
                                                This will generate a 6-character temporary code. The member must change it upon login.
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 p-4 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                <input type="hidden" name="action" value="reset_pass">
                                                <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">Proceed Reset</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .card-premium { background: var(--bg-surface); border-radius: 24px; border: 1px solid var(--border-color); }
    .bg-brand-forest { background: var(--brand-forest) !important; color: white !important; }
    .icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
