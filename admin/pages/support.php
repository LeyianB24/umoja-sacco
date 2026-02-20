<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// Auth Check
require_admin();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_permission();

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['full_name'] ?? 'IT Admin';
$db = $conn;

// Role-based visibility: Filter tickets by assigned_role_id
$my_role_id = (int)($_SESSION['role_id'] ?? 0);
$role_where = ($my_role_id === 1) ? "1=1" : "assigned_role_id = $my_role_id";

// KPI COUNTERS (Role-Filtered)
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed
    FROM support_tickets
    WHERE $role_where
")->fetch_assoc();

// FILTER LOGIC
$status_filter   = $_GET['status'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$search_query    = trim($_GET['q'] ?? '');
$where_clauses   = [];

// Strict filtering for non-superadmins
if ($my_role_id !== 1) {
    $where_clauses[] = "s.assigned_role_id = $my_role_id";
}

if ($status_filter !== 'all') $where_clauses[] = "s.status = '" . $db->real_escape_string($status_filter) . "'";
if ($category_filter !== 'all') $where_clauses[] = "s.category = '" . $db->real_escape_string($category_filter) . "'";
if ($search_query) {
    $q = $db->real_escape_string($search_query);
    $where_clauses[] = "(s.subject LIKE '%$q%' OR s.message LIKE '%$q%' OR s.support_id LIKE '%$q%')";
}
$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

// FETCH TICKETS
$sql = "
    SELECT 
        s.support_id, s.member_id, s.subject, s.message, 
        s.status, s.created_at, s.attachment, s.category,
        CASE 
            WHEN s.member_id > 0 THEN m.full_name
            ELSE 'System'
        END AS sender_name,
        CASE 
            WHEN s.member_id > 0 THEN 'Member'
            ELSE 'Internal'
        END AS sender_role
    FROM support_tickets s
    LEFT JOIN members m ON s.member_id = m.member_id
    $where_sql
    ORDER BY s.created_at DESC
";

$res = $db->query($sql);
$tickets = [];
while ($row = $res->fetch_assoc()) { $tickets[] = $row; }

function getInitials($name) { return strtoupper(substr($name ?? 'U', 0, 1)); }

$pageTitle = "Helpdesk Support";
$layout->header($pageTitle);
?>
<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="main-content">
        <?php $layout->topbar($pageTitle ?? ''); ?>
            <h1 class="fw-800 mb-1">Helpdesk Console</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item small text-muted">Admin</li>
                    <li class="breadcrumb-item small active fw-bold " aria-current="page">Support</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/admin/pages/dashboard.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm fw-bold">
                <i class="bi bi-grid-fill me-2"></i>Dashboard
            </a>
        </div>
    </div>

            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="hope-card p-4 hope-card-dark shadow-lg">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="small opacity-75 fw-bold text-uppercase">Pending</div>
                                <h2 class="fw-bold mt-1 mb-0"><?= $stats['pending'] ?></h2>
                            </div>
                            <div class="icon-box" style="background: rgba(190, 242, 100, 0.2); color: var(--hope-lime);">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="hope-card p-4 shadow-sm">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="small text-muted fw-bold text-uppercase">In Progress</div>
                                <h2 class="fw-bold mt-1 mb-0"><?= $stats['open'] ?></h2>
                            </div>
                            <div class="icon-box" style="background: #eff6ff; color: #2563eb;">
                                <i class="bi bi-cpu"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="hope-card p-4 shadow-sm">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="small text-muted fw-bold text-uppercase">Resolved</div>
                                <h2 class="fw-bold mt-1 mb-0"><?= $stats['closed'] ?></h2>
                            </div>
                            <div class="icon-box" style="background: #ecfdf5; color: #10b981;">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="hope-card p-4 shadow-sm">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="small text-muted fw-bold text-uppercase">Total Tickets</div>
                                <h2 class="fw-bold mt-1 mb-0"><?= $stats['total'] ?></h2>
                            </div>
                            <div class="icon-box" style="background: #f3f4f6; color: #374151;">
                                <i class="bi bi-archive"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hope-card shadow-sm mb-5">
                <div class="p-4 border-bottom bg-white">
                    <form method="get" class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <select name="status" class="form-select bg-light border-0 shadow-none" onchange="this.form.submit()">
                                <option value="all" <?= $status_filter=='all'?'selected':'' ?>>Status (All)</option>
                                <option value="Pending" <?= $status_filter=='Pending'?'selected':'' ?>>Pending</option>
                                <option value="Open" <?= $status_filter=='Open'?'selected':'' ?>>In Progress</option>
                                <option value="Closed" <?= $status_filter=='Closed'?'selected':'' ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="category" class="form-select bg-light border-0 shadow-none" onchange="this.form.submit()">
                                <option value="all" <?= $category_filter=='all'?'selected':'' ?>>All Categories</option>
                                <option value="loans" <?= $category_filter=='loans'?'selected':'' ?>>Loans</option>
                                <option value="savings" <?= $category_filter=='savings'?'selected':'' ?>>Savings</option>
                                <option value="shares" <?= $category_filter=='shares'?'selected':'' ?>>Shares</option>
                                <option value="welfare" <?= $category_filter=='welfare'?'selected':'' ?>>Welfare</option>
                                <option value="withdrawals" <?= $category_filter=='withdrawals'?'selected':'' ?>>Withdrawals</option>
                                <option value="technical" <?= $category_filter=='technical'?'selected':'' ?>>Technical</option>
                                <option value="profile" <?= $category_filter=='profile'?'selected':'' ?>>Profile</option>
                                <option value="investments" <?= $category_filter=='investments'?'selected':'' ?>>Investments</option>
                                <option value="general" <?= $category_filter=='general'?'selected':'' ?>>General</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" name="q" class="form-control bg-light border-0 shadow-none" placeholder="Search subject or ID..." value="<?= htmlspecialchars($search_query) ?>">
                                <button class="btn btn-hope-lime px-3" type="submit"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <?php if($status_filter !== 'all' || $category_filter !== 'all' || $search_query): ?>
                        <div class="col-md-2 text-end">
                            <a href="support.php" class="small text-danger text-decoration-none fw-bold"><i class="bi bi-x-circle me-1"></i>Clear Filters</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hope mb-0">
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Issue Summary</th>
                                <th>Category</th>
                                <th>Sender</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($tickets)): ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted">No tickets found matching your permissions or filters.</td></tr>
                            <?php else: foreach($tickets as $t): 
                                $statusClass = match($t['status']){
                                    'Pending'=>'bg-pending',
                                    'Open'=>'bg-open',
                                    'Closed'=>'bg-closed',
                                    default=>'bg-light'
                                };
                            ?>
                            <tr>
                                <td class="fw-bold ">#<?= $t['support_id'] ?></td>
                                <td style="max-width:280px;">
                                    <div class="fw-semibold  text-truncate mb-0"><?= htmlspecialchars($t['subject'] ?? '') ?></div>
                                    <div class="small text-muted text-truncate"><?= htmlspecialchars(strip_tags($t['message'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light  border text-uppercase" style="font-size: 0.65rem;">
                                        <?= htmlspecialchars($t['category'] ?? 'General') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-initial"><?= getInitials($t['sender_name']) ?></div>
                                        <div>
                                            <div class="small fw-bold  mb-0"><?= htmlspecialchars($t['sender_name'] ?? 'Guest') ?></div>
                                            <div class="x-small text-muted" style="font-size: 0.7rem;"><?= $t['sender_role'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge-hope <?= $statusClass ?>"><?= strtoupper($t['status']) ?></span></td>
                                <td class="small text-muted fw-medium"><?= date('M d, Y • H:i', strtotime($t['created_at'])) ?></td>
                                <td class="text-end">
                                    <a href="support_view.php?id=<?= $t['support_id'] ?>" class="btn btn-light btn-sm rounded-circle p-2 shadow-sm" title="Manage Ticket">
                                        <i class="bi bi-chat-left-dots-fill text-primary"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </div><!-- End main-content -->
</div><!-- End d-flex -->

<?php $layout->footer(); ?>
