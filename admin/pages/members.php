<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

/**
 * admin/members.php
 * Unified Member Management - V28 Glassmorphism
 */

if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

// 1. AUTHENTICATION & ROLE CHECK
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$my_id = $_SESSION['admin_id'] ?? 0;
$db = $conn;

// 2. HANDLE ACTIONS
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
                $stmt = $db->prepare("UPDATE members SET status = ? WHERE member_id = ?");
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
$sql = "SELECT * FROM members $where_sql ORDER BY join_date DESC";

$stmt = $db->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$st = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as pending FROM members")->fetch_assoc();

$pageTitle = "Member Directory";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <style>
        :root { --forest: #0F2E25; --lime: #D0F35D; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f4f3; }
        .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; }
        .page-banner { 
            background: var(--forest); border-radius: 20px; padding: 30px; color: white; margin-bottom: 30px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .glass-card { 
            background: white; border-radius: 24px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            padding: 30px; margin-bottom: 30px;
        }
        .mini-stat { background: #f8fafc; border-radius: 20px; padding: 20px; text-align: center; }
        .avatar-sm { 
            width: 38px; height: 38px; border-radius: 10px; 
            object-fit: cover; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem; letter-spacing: -0.5px;
        }
        .avatar-placeholder {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            color: var(--lime);
            box-shadow: 0 4px 10px rgba(15, 46, 37, 0.15);
        }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content">
    <?php $layout->topbar($pageTitle ?? ''); ?>

    <div class="page-banner shadow">
        <div>
            <h2 class="fw-bold mb-0">Member Directory</h2>
            <p class="opacity-75 mb-0">Managing <?= $st['total'] ?> registered members</p>
        </div>
        <div class="d-flex gap-3">
            <div class="text-end">
                <div class="small opacity-75">Active</div>
                <div class="fw-bold"><?= $st['active'] ?></div>
            </div>
            <div class="text-end border-start ps-3">
                <div class="small opacity-75">Pending</div>
                <div class="fw-bold"><?= $st['pending'] ?></div>
            </div>
        </div>
    </div>

    <?php flash_render(); ?>

    <div class="glass-card">
        <form method="GET" class="row g-3">
            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control bg-light border-0" placeholder="Search by name, ID or email..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select bg-light border-0">
                    <option value="all">All Status</option>
                    <option value="active" <?= $filter=='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $filter=='inactive'?'selected':'' ?>>Pending</option>
                    <option value="suspended" <?= $filter=='suspended'?'selected':'' ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-forest w-100 rounded-pill fw-bold">Filter</button>
            </div>
        </form>
    </div>

    <div class="glass-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 border-0">Profile</th>
                        <th class="py-3 border-0">Contact</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Joined</th>
                        <th class="pe-4 py-3 border-0 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($members as $m): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <?php if($m['profile_pic']): ?>
                                    <img src="data:image/jpeg;base64,<?= base64_encode($m['profile_pic']) ?>" class="avatar-sm shadow-sm">
                                <?php else: ?>
                                    <div class="avatar-sm avatar-placeholder"><?= strtoupper(substr($m['full_name'],0,1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold fs-6" style="letter-spacing: -0.2px;"><?= htmlspecialchars($m['full_name']) ?></div>
                                    <small class="text-muted">ID: <?= $m['national_id'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-semibold"><?= $m['email'] ?></div>
                            <div class="small text-muted"><?= $m['phone'] ?></div>
                        </td>
                        <td>
                            <span class="badge rounded-pill bg-<?= $m['status']=='active'?'success':($m['status']=='inactive'?'warning':'danger') ?> bg-opacity-10 text-<?= $m['status']=='active'?'success':($m['status']=='inactive'?'warning':'danger') ?> px-3 mt-1">
                                <?= strtoupper($m['status']) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= date('d M Y', strtotime($m['join_date'])) ?></td>
                        <td class="pe-4 text-end">
                            <?php if(can('manage_members')): ?>
                                <?php if($m['status'] == 'inactive'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= $m['member_id'] ?>"><input type="hidden" name="action" value="approve">
                                        <button class="btn btn-success btn-sm rounded-pill px-3">Approve</button>
                                    </form>
                                <?php elseif($m['status'] == 'active'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= $m['member_id'] ?>"><input type="hidden" name="action" value="suspend">
                                        <button class="btn btn-outline-danger btn-sm rounded-pill px-3">Suspend</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= $m['member_id'] ?>"><input type="hidden" name="action" value="reactivate">
                                        <button class="btn btn-outline-success btn-sm rounded-pill px-3">Reactivate</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">Read-Only</span>
                            <?php endif; ?>
                            <a href="member_profile.php?id=<?= $m['member_id'] ?>" class="btn btn-light btn-sm rounded-pill px-3 border ms-1"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>







