<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

\USMS\Middleware\AuthMiddleware::requireModulePermission('support', 'view');
$layout = LayoutManager::create('admin');

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['full_name'] ?? 'IT Admin';
$db         = $conn;

$my_role_id = (int)($_SESSION['role_id'] ?? 0);
$role_where = ($my_role_id === 1) ? "1=1" : "assigned_role_id = $my_role_id";

$stats = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='Open'    THEN 1 ELSE 0 END) as open,
    SUM(CASE WHEN status='Closed'  THEN 1 ELSE 0 END) as closed
    FROM support_tickets WHERE $role_where")->fetch_assoc();

$status_filter   = $_GET['status']   ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$search_query    = trim($_GET['q']   ?? '');
$where_clauses   = [];

if ($my_role_id !== 1) $where_clauses[] = "s.assigned_role_id = $my_role_id";
if ($status_filter   !== 'all') $where_clauses[] = "s.status   = '" . $db->real_escape_string($status_filter)   . "'";
if ($category_filter !== 'all') $where_clauses[] = "s.category = '" . $db->real_escape_string($category_filter) . "'";
if ($search_query) {
    $q = $db->real_escape_string($search_query);
    $where_clauses[] = "(s.subject LIKE '%$q%' OR s.message LIKE '%$q%' OR s.support_id LIKE '%$q%')";
}
$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

$sql = "SELECT s.support_id, s.member_id, s.subject, s.message,
    s.status, s.created_at, s.attachment, s.category,
    CASE WHEN s.member_id > 0 THEN m.full_name ELSE 'System' END AS sender_name,
    CASE WHEN s.member_id > 0 THEN 'Member' ELSE 'Internal' END AS sender_role
    FROM support_tickets s
    LEFT JOIN members m ON s.member_id = m.member_id
    $where_sql
    ORDER BY s.created_at DESC";

$res = $db->query($sql);
$tickets = [];
while ($row = $res->fetch_assoc()) $tickets[] = $row;

if (!function_exists('getInitials')) {
    function getInitials($name) { return strtoupper(substr($name ?? 'U', 0, 1)); }
}

$pageTitle = "Helpdesk Support";
$layout->header($pageTitle);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   HELPDESK SUPPORT — JAKARTA SANS + GLASSMORPHISM THEME
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; }

:root {
    --forest:       #0d2b1f;
    --forest-mid:   #1a3d2b;
    --forest-light: #234d36;
    --lime:         #b5f43c;
    --lime-soft:    #d6fb8a;
    --lime-glow:    rgba(181,244,60,0.18);
    --lime-glow-sm: rgba(181,244,60,0.08);
    --surface:      #ffffff;
    --bg-muted:     #f5f8f6;
    --text-primary: #0d1f15;
    --text-muted:   #6b7c74;
    --border:       rgba(13,43,31,0.07);
    --radius-sm:    8px;
    --radius-md:    14px;
    --radius-lg:    20px;
    --radius-xl:    28px;
    --shadow-sm:    0 2px 8px rgba(13,43,31,0.07);
    --shadow-md:    0 8px 28px rgba(13,43,31,0.11);
    --shadow-lg:    0 20px 60px rgba(13,43,31,0.16);
    --shadow-glow:  0 0 0 3px var(--lime-glow), 0 6px 24px rgba(181,244,60,0.15);
    --transition:   all 0.22s cubic-bezier(0.4,0,0.2,1);
}

body, *, input, select, textarea, button, .btn, table, th, td,
h1,h2,h3,h4,h5,h6,p,span,div,label,a,.modal,.offcanvas {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Hero ── */
.hp-hero {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, #0e3522 100%);
    border-radius: var(--radius-xl);
    padding: 2.6rem 3rem 5rem;
    position: relative; overflow: hidden; color: #fff; margin-bottom: 0;
}
.hp-hero::before {
    content:''; position:absolute; inset:0;
    background:
        radial-gradient(ellipse 55% 70% at 95% 5%,  rgba(181,244,60,0.13) 0%, transparent 60%),
        radial-gradient(ellipse 35% 45% at 5%  95%, rgba(181,244,60,0.06) 0%, transparent 60%);
    pointer-events:none;
}
.hp-hero .ring { position:absolute; border-radius:50%; border:1px solid rgba(181,244,60,0.1); pointer-events:none; }
.hp-hero .ring1 { width:320px; height:320px; top:-80px; right:-80px; }
.hp-hero .ring2 { width:500px; height:500px; top:-160px; right:-160px; }
.hero-badge {
    display:inline-flex; align-items:center; gap:0.45rem;
    background:rgba(181,244,60,0.12); border:1px solid rgba(181,244,60,0.25);
    color:var(--lime-soft); border-radius:100px; padding:0.28rem 0.85rem;
    font-size:0.68rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase;
    margin-bottom:0.9rem; position:relative;
}
.hero-badge::before { content:''; width:6px; height:6px; border-radius:50%; background:var(--lime); animation:pulse-dot 2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

/* ── KPI Cards ── */
.stat-card {
    background: var(--surface); border-radius: var(--radius-lg);
    border: 1px solid var(--border); box-shadow: var(--shadow-md);
    padding: 1.5rem 1.6rem; height: 100%;
    position: relative; overflow: hidden; transition: var(--transition);
}
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.stat-card::after { content:''; position:absolute; bottom:0; left:0; right:0; height:3px; border-radius:0 0 var(--radius-lg) var(--radius-lg); opacity:0; transition:var(--transition); }
.stat-card:hover::after { opacity:1; }
.stat-card.sc-dark  { background:linear-gradient(135deg,var(--forest) 0%,var(--forest-mid) 100%); border:none; }
.stat-card.sc-dark::after  { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }
.stat-card.sc-open::after  { background:linear-gradient(90deg,#3b82f6,#93c5fd); }
.stat-card.sc-resolved::after { background:linear-gradient(90deg,#22c55e,#86efac); }
.stat-card.sc-total::after { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }

.stat-top  { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.9rem; }
.stat-icon { width:46px; height:46px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.stat-label { font-size:0.67rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.3rem; }
.stat-value { font-size:1.8rem; font-weight:800; letter-spacing:-0.04em; line-height:1; }

/* ── Filter Bar ── */
.filter-bar {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-sm);
    padding:1rem 1.4rem; margin-bottom:1.2rem;
    display:flex; flex-wrap:wrap; gap:0.6rem; align-items:center;
}
.filter-label { font-size:0.67rem; font-weight:800; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-muted); white-space:nowrap; display:flex; align-items:center; gap:0.4rem; }
.form-select-enh {
    border-radius:var(--radius-md); border:1.5px solid rgba(13,43,31,0.1);
    font-size:0.82rem; font-weight:600; padding:0.45rem 2.2rem 0.45rem 0.9rem;
    color:var(--text-primary); background:#f8faf9;
    font-family:'Plus Jakarta Sans',sans-serif !important; transition:var(--transition);
    appearance:none; height:36px; min-width:145px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7c74' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 0.9rem center;
}
.form-select-enh:focus { outline:none; border-color:var(--lime); background-color:#fff; box-shadow:var(--shadow-glow); }
.search-wrap { position:relative; flex:1; min-width:200px; }
.search-wrap i { position:absolute; top:50%; left:12px; transform:translateY(-50%); color:var(--text-muted); font-size:0.8rem; pointer-events:none; }
.search-input {
    width:100%; padding:0.45rem 1rem 0.45rem 2.3rem; height:36px;
    border-radius:var(--radius-md); border:1.5px solid rgba(13,43,31,0.1);
    background:#f8faf9; font-size:0.82rem; font-weight:500; color:var(--text-primary);
    font-family:'Plus Jakarta Sans',sans-serif !important; transition:var(--transition);
}
.search-input:focus { outline:none; border-color:var(--lime); background:#fff; box-shadow:var(--shadow-glow); }
.btn-search {
    background:var(--forest); color:#fff; border:none; border-radius:100px;
    padding:0.45rem 1.1rem; font-size:0.82rem; font-weight:700;
    cursor:pointer; transition:var(--transition); height:36px;
    display:flex; align-items:center; gap:0.35rem; white-space:nowrap;
}
.btn-search:hover { background:var(--forest-light); }
.btn-clear {
    font-size:0.78rem; font-weight:700; color:#dc2626; text-decoration:none;
    display:flex; align-items:center; gap:0.3rem; transition:var(--transition); white-space:nowrap;
}
.btn-clear:hover { color:#b91c1c; }

/* ── Ticket Table Card ── */
.ticket-card {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-md); overflow:hidden;
}
.ticket-table { width:100%; border-collapse:separate; border-spacing:0; }
.ticket-table thead th {
    background:#f5f8f6; color:var(--text-muted); font-size:0.67rem;
    font-weight:800; text-transform:uppercase; letter-spacing:0.1em;
    padding:0.8rem 1rem; border-bottom:1px solid var(--border); white-space:nowrap;
}
.ticket-table thead th:first-child { padding-left:1.5rem; }
.ticket-table thead th:last-child  { padding-right:1.5rem; text-align:right; }
.ticket-table tbody tr { border-bottom:1px solid rgba(13,43,31,0.04); transition:var(--transition); }
.ticket-table tbody tr:last-child  { border-bottom:none; }
.ticket-table tbody tr:hover { background:#f0faf4; }
.ticket-table tbody td { padding:0.9rem 1rem; vertical-align:middle; }
.ticket-table tbody td:first-child { padding-left:1.5rem; }
.ticket-table tbody td:last-child  { padding-right:1.5rem; text-align:right; }

/* Cells */
.ref-badge { font-family:'Courier New',monospace !important; font-size:0.78rem; font-weight:800; color:var(--forest); background:var(--lime-glow-sm); border:1px solid rgba(181,244,60,0.2); border-radius:6px; padding:0.18rem 0.55rem; }
.ticket-subject { font-size:0.875rem; font-weight:700; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:260px; display:block; }
.ticket-preview { font-size:0.75rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:260px; display:block; margin-top:0.1rem; }
.cat-pill { display:inline-block; border-radius:100px; padding:0.2rem 0.65rem; font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; background:#f5f8f6; border:1px solid var(--border); color:var(--text-muted); }
.sender-cell { display:flex; align-items:center; gap:0.6rem; }
.sender-avatar { width:32px; height:32px; border-radius:50%; background:var(--forest); color:var(--lime); display:flex; align-items:center; justify-content:center; font-size:0.68rem; font-weight:800; flex-shrink:0; }
.sender-name  { font-size:0.82rem; font-weight:700; color:var(--text-primary); }
.sender-role  { font-size:0.68rem; color:var(--text-muted); margin-top:0.1rem; }
.cell-time    { font-size:0.78rem; font-weight:600; color:var(--text-muted); }

/* Status badges */
.status-pill {
    display:inline-flex; align-items:center; gap:0.3rem;
    border-radius:100px; padding:0.22rem 0.75rem;
    font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em;
}
.status-pill::before { content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.sp-pending  { background:#fffbeb; color:#b45309; border:1px solid rgba(245,158,11,0.2); }
.sp-pending::before  { background:#f59e0b; }
.sp-open     { background:#eff6ff; color:#1d4ed8; border:1px solid rgba(59,130,246,0.2); }
.sp-open::before     { background:#3b82f6; }
.sp-closed   { background:#f0fdf4; color:#166534; border:1px solid rgba(22,163,74,0.2); }
.sp-closed::before   { background:#22c55e; }
.sp-default  { background:#f5f8f6; color:var(--text-muted); border:1px solid var(--border); }
.sp-default::before  { background:#94a3b8; }

/* Action button */
.btn-view {
    width:34px; height:34px; border-radius:50%; border:1.5px solid var(--border);
    background:var(--surface); display:inline-flex; align-items:center; justify-content:center;
    color:#3b82f6; font-size:0.85rem; transition:var(--transition); text-decoration:none;
}
.btn-view:hover { background:#eff6ff; border-color:rgba(59,130,246,0.3); transform:scale(1.1); }

/* Dashboard link button */
.btn-outline-hero {
    display:inline-flex; align-items:center; gap:0.5rem;
    background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2);
    color:#fff; border-radius:100px; padding:0.48rem 1.2rem;
    font-size:0.82rem; font-weight:700; text-decoration:none; transition:var(--transition);
}
.btn-outline-hero:hover { background:rgba(255,255,255,0.18); color:#fff; }

/* Empty state */
.empty-cell { text-align:center; padding:5rem 2rem !important; }
.empty-icon { width:64px; height:64px; border-radius:16px; background:#f5f8f6; display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:#c4d4cb; margin:0 auto 0.9rem; border:1px solid var(--border); }

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

@media (max-width:768px) {
    .hp-hero { padding:2rem 1.5rem 4rem; }
    .filter-bar { flex-direction:column; align-items:stretch; }
    .form-select-enh { min-width:100%; }
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <!-- Hero -->
        <div class="hp-hero fade-in">
            <div class="ring ring1"></div>
            <div class="ring ring2"></div>
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="hero-badge">Service Management</div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;color:#fff;">
                        Helpdesk Console.
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin:0;">
                        Resolving member inquiries and system alerts with <span style="color:var(--lime);font-weight:700;">priority care</span>.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0 d-none d-lg-block" style="position:relative;">
                    <a href="<?= BASE_URL ?>/admin/pages/dashboard.php" class="btn-outline-hero">
                        <i class="bi bi-grid-fill"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px; position:relative; z-index:10;">

            <!-- KPI Row -->
            <div class="row g-3 mb-4">
                <!-- Pending -->
                <div class="col-md-3">
                    <div class="stat-card sc-dark slide-up" style="animation-delay:0.06s;">
                        <div class="stat-top">
                            <div class="stat-icon" style="background:rgba(255,255,255,0.1);color:var(--lime);">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color:#fff;"><?= $stats['pending'] ?></div>
                        <div class="stat-label" style="color:rgba(255,255,255,0.45);">Pending</div>
                    </div>
                </div>
                <!-- In Progress -->
                <div class="col-md-3">
                    <div class="stat-card sc-open slide-up" style="animation-delay:0.12s;">
                        <div class="stat-top">
                            <div class="stat-icon" style="background:#eff6ff;color:#1d4ed8;">
                                <i class="bi bi-cpu"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color:#1d4ed8;"><?= $stats['open'] ?></div>
                        <div class="stat-label" style="color:var(--text-muted);">In Progress</div>
                    </div>
                </div>
                <!-- Resolved -->
                <div class="col-md-3">
                    <div class="stat-card sc-resolved slide-up" style="animation-delay:0.18s;">
                        <div class="stat-top">
                            <div class="stat-icon" style="background:#f0fdf4;color:#166534;">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color:#166534;"><?= $stats['closed'] ?></div>
                        <div class="stat-label" style="color:var(--text-muted);">Resolved</div>
                    </div>
                </div>
                <!-- Total -->
                <div class="col-md-3">
                    <div class="stat-card sc-total slide-up" style="animation-delay:0.24s;">
                        <div class="stat-top">
                            <div class="stat-icon" style="background:var(--lime-glow-sm);color:var(--forest);">
                                <i class="bi bi-archive"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color:var(--forest);"><?= $stats['total'] ?></div>
                        <div class="stat-label" style="color:var(--text-muted);">Total Issues</div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar slide-up" style="animation-delay:0.28s;">
                <span class="filter-label"><i class="bi bi-funnel"></i>Filter</span>
                <form method="get" style="display:contents;">
                    <select name="status" class="form-select-enh" onchange="this.form.submit()">
                        <option value="all"     <?= $status_filter==='all'     ?'selected':''?>>All Statuses</option>
                        <option value="Pending" <?= $status_filter==='Pending' ?'selected':''?>>Pending</option>
                        <option value="Open"    <?= $status_filter==='Open'    ?'selected':''?>>In Progress</option>
                        <option value="Closed"  <?= $status_filter==='Closed'  ?'selected':''?>>Resolved</option>
                    </select>
                    <select name="category" class="form-select-enh" onchange="this.form.submit()">
                        <option value="all"         <?= $category_filter==='all'         ?'selected':''?>>All Categories</option>
                        <option value="loans"        <?= $category_filter==='loans'        ?'selected':''?>>Loans</option>
                        <option value="savings"      <?= $category_filter==='savings'      ?'selected':''?>>Savings</option>
                        <option value="shares"       <?= $category_filter==='shares'       ?'selected':''?>>Shares</option>
                        <option value="welfare"      <?= $category_filter==='welfare'      ?'selected':''?>>Welfare</option>
                        <option value="withdrawals"  <?= $category_filter==='withdrawals'  ?'selected':''?>>Withdrawals</option>
                        <option value="technical"    <?= $category_filter==='technical'    ?'selected':''?>>Technical</option>
                        <option value="profile"      <?= $category_filter==='profile'      ?'selected':''?>>Profile</option>
                        <option value="investments"  <?= $category_filter==='investments'  ?'selected':''?>>Investments</option>
                        <option value="general"      <?= $category_filter==='general'      ?'selected':''?>>General</option>
                    </select>
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" class="search-input"
                               placeholder="Search subject or ID..."
                               value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    <button type="submit" class="btn-search">
                        <i class="bi bi-search" style="font-size:0.72rem;"></i>Search
                    </button>
                    <?php if($status_filter !== 'all' || $category_filter !== 'all' || $search_query): ?>
                    <a href="support.php" class="btn-clear">
                        <i class="bi bi-x-circle"></i>Clear
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Ticket Table -->
            <div class="ticket-card mb-5 slide-up" style="animation-delay:0.32s;">
                <div class="table-responsive">
                    <table class="ticket-table">
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Issue Summary</th>
                                <th>Category</th>
                                <th>Sender</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="7" class="empty-cell">
                                    <div class="empty-icon"><i class="bi bi-chat-left-dots"></i></div>
                                    <div style="font-weight:800;font-size:0.95rem;color:var(--text-primary);margin-bottom:0.25rem;">No Tickets Found</div>
                                    <div style="font-size:0.8rem;color:var(--text-muted);">
                                        <?= ($status_filter !== 'all' || $category_filter !== 'all' || $search_query) ? 'Try adjusting your filters or search query.' : 'No support tickets match your role permissions.' ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else: foreach ($tickets as $t):
                                $sp_class = match($t['status']) {
                                    'Pending' => 'sp-pending',
                                    'Open'    => 'sp-open',
                                    'Closed'  => 'sp-closed',
                                    default   => 'sp-default',
                                };
                                $sp_label = match($t['status']) {
                                    'Open'  => 'In Progress',
                                    default => $t['status'],
                                };
                                $sname    = $t['sender_name'] ?? 'Guest';
                                $parts    = explode(' ', trim($sname));
                                $initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
                            ?>
                            <tr>
                                <td>
                                    <span class="ref-badge">#<?= $t['support_id'] ?></span>
                                </td>
                                <td style="max-width:280px;">
                                    <span class="ticket-subject"><?= htmlspecialchars($t['subject'] ?? '') ?></span>
                                    <span class="ticket-preview"><?= htmlspecialchars(strip_tags($t['message'] ?? '')) ?></span>
                                </td>
                                <td>
                                    <span class="cat-pill"><?= htmlspecialchars($t['category'] ?? 'General') ?></span>
                                </td>
                                <td>
                                    <div class="sender-cell">
                                        <div class="sender-avatar"><?= htmlspecialchars($initials ?: 'U') ?></div>
                                        <div>
                                            <div class="sender-name"><?= htmlspecialchars($sname) ?></div>
                                            <div class="sender-role"><?= htmlspecialchars($t['sender_role']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-pill <?= $sp_class ?>"><?= htmlspecialchars($sp_label) ?></span>
                                </td>
                                <td>
                                    <span class="cell-time"><?= date('M d, Y · H:i', strtotime($t['created_at'])) ?></span>
                                </td>
                                <td>
                                    <a href="support_view.php?id=<?= $t['support_id'] ?>" class="btn-view" title="Manage Ticket">
                                        <i class="bi bi-chat-left-dots-fill"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /overlap -->

    </div><!-- /container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->