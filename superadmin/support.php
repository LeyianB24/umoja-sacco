<?php
// usms/superadmin/support.php
// Superadmin Support Tickets Management

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

require_superadmin();

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

$db = $conn;

// -----------------------------------------------------------
// FILTER
// -----------------------------------------------------------
$status_filter = $_GET['status'] ?? 'all';

$where = ($status_filter !== 'all')
    ? "WHERE s.status = '" . $db->real_escape_string($status_filter) . "'"
    : "";


// -----------------------------------------------------------
// FETCH ALL SUPPORT TICKETS (CORRECTED LOGIC)
// -----------------------------------------------------------
// RULES:
// - member_id = 0 → ticket was created by an ADMIN/MANAGER/ACCOUNTANT
// - admin_id  = assigned admin (superadmin)
// - members ALWAYS have real member_id
// - Use admin_id from members/admins table correctly
//-------------------------------------------------------------

$sql = "
    SELECT 
        s.support_id,
        s.admin_id,
        s.member_id,
        s.subject,
        s.message,
        s.status,
        s.created_at,
        s.attachment,

        -- Correct sender name detection
        CASE 
            WHEN s.member_id > 0 THEN m.full_name               -- member
            ELSE a.full_name                                    -- admin/manager/accountant
        END AS sender_name,

        CASE 
            WHEN s.member_id > 0 THEN 'Member'
            ELSE 'Admin'
        END AS sender_role

    FROM support_tickets s
    LEFT JOIN members m ON s.member_id = m.member_id
    LEFT JOIN admins a  ON s.member_id = 0 AND s.admin_id = a.admin_id

    $where
    ORDER BY s.created_at DESC
";

$res = $db->query($sql);
$tickets = [];
while ($row = $res->fetch_assoc()) {
    $tickets[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Support Center — Superadmin | <?= SITE_NAME ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css?v=1.3">

<style>
:root {
  --sacco-green: <?= $theme['primary'] ?? '#16a34a' ?>;
  --sacco-dark: <?= $theme['primary_dark'] ?? '#0b6623' ?>;
  --sacco-gold: <?= $theme['accent'] ?? '#f4c430' ?>;
}
body { background:#f6f8f9; }
.sidebar { width:260px; background:linear-gradient(180deg,var(--sacco-dark),#0a3d1d); color:#fff; min-height:100vh; position:fixed; }
.sidebar nav a { color:#fff; padding:12px 18px; display:block; }
.sidebar nav a:hover, .sidebar nav a.active { background:rgba(255,255,255,0.08); }
.main { margin-left:260px; padding:26px; }
.badge-pending { background:#f59e0b; }
.badge-open    { background:#0ea5e9; }
.badge-closed  { background:#16a34a; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand p-3 d-flex align-items-center gap-2">
        <img src="<?= ASSET_BASE ?>/images/people_logo.png" width="55" style="border-radius:50%;border:2px solid var(--sacco-gold)">
        <div>
            <div class="fw-bold"><?= SITE_NAME ?></div>
            <small><?= TAGLINE ?></small>
        </div>
    </div>

    <nav>
        <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a href="manage_admins.php"><i class="bi bi-person-gear me-2"></i> Admins</a>
        <a href="manage_members.php"><i class="bi bi-people me-2"></i> Members</a>
        <a href="manage_loans.php"><i class="bi bi-cash-coin me-2"></i> Loans</a>
        <a href="notifications.php"><i class="bi bi-bell me-2"></i> Notifications</a>
        <a href="support.php" class="active"><i class="bi bi-life-preserver me-2"></i> Support Center</a>
        <a href="audit_logs.php"><i class="bi bi-activity me-2"></i> Logs</a>
        <a href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a>

        <div class="p-3">
            <a href="<?= BASE_URL ?>/public/logout.php" class="btn btn-warning w-100">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </nav>
</aside>

<!-- MAIN -->
<main class="main">

<h4 class="mb-3">Support Tickets</h4>
<p class="text-muted">Manage support tickets from members, managers, accountants, and admins.</p>

<!-- FILTER -->
<div class="mb-3">
    <form method="get" class="d-flex gap-2">
        <select name="status" class="form-select w-auto">
            <option value="all"     <?= $status_filter=='all'?'selected':'' ?>>All</option>
            <option value="Pending" <?= $status_filter=='Pending'?'selected':'' ?>>Pending</option>
            <option value="Open"    <?= $status_filter=='Open'?'selected':'' ?>>Open</option>
            <option value="Closed"  <?= $status_filter=='Closed'?'selected':'' ?>>Closed</option>
        </select>
        <button class="btn btn-primary">Apply</button>
    </form>
</div>

<!-- TICKETS TABLE -->
<div class="card">
    <div class="card-header bg-white"><strong>All Tickets</strong></div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Sender</th>
                    <th>Role</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$tickets): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No tickets found.</td></tr>

                <?php else: foreach ($tickets as $t): ?>
                    <tr>
                        <td><?= $t['support_id'] ?></td>
                        <td><?= htmlspecialchars($t['sender_name']) ?></td>
                        <td><?= htmlspecialchars($t['sender_role']) ?></td>
                        <td><?= htmlspecialchars($t['subject']) ?></td>

                        <td>
                            <span class="badge 
                                <?= $t['status']=='Pending'?'badge-pending':'' ?>
                                <?= $t['status']=='Open'?'badge-open':'' ?>
                                <?= $t['status']=='Closed'?'badge-closed':'' ?>
                            ">
                            <?= $t['status'] ?>
                            </span>
                        </td>

                        <td><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>

                        <td>
                            <a href="support_view.php?id=<?= $t['support_id'] ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

</main>

</body>
</html>