<?php
// usms/admin/dashboard.php
// Admin Console – Re-skinned with "Hope" Design Language

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../inc/auth.php';

// Enforce Admin Auth
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['admin','superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

// Admin Name
$admin_name = htmlspecialchars($_SESSION['full_name'] ?? 'System Admin');

// -------------------------
// METRICS
// -------------------------
$open_tickets = $conn->query("SELECT COUNT(*) AS c FROM support_tickets WHERE status!='Closed'")
                    ->fetch_assoc()['c'] ?? 0;

$today_logs = $conn->query("SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at)=CURDATE()")
                    ->fetch_assoc()['c'] ?? 0;

// -------------------------
// DATABASE SIZE
// -------------------------
try {
    $q = $conn->query("
        SELECT SUM(data_length + index_length) / 1024 / 1024 AS size 
        FROM information_schema.TABLES 
        WHERE table_schema='umoja_drivers_sacco'
    ");
    $db_size = number_format($q->fetch_assoc()['size'], 2);
} catch (Exception $e) { 
    $db_size = "N/A"; 
}

// -------------------------
// RECENT TICKETS
// -------------------------
$tickets = [];
$sql = "SELECT s.*, COALESCE(m.full_name,'Guest') AS sender
        FROM support_tickets s
        LEFT JOIN members m ON s.member_id=m.member_id
        WHERE s.status!='Closed'
        ORDER BY s.created_at DESC
        LIMIT 5";
$r = $conn->query($sql);
while ($row = $r->fetch_assoc()) $tickets[] = $row;

// -------------------------
// SYSTEM SIMULATION
// -------------------------
$server_load   = rand(10, 40);
$memory_usage  = rand(30, 70);
$backup_usage  = rand(5, 30);

// -------------------------
// SERVER INFO
// -------------------------
$php_version   = phpversion();
$mysql_version = $conn->server_info;
$server_os     = PHP_OS;

// -------------------------
$pageTitle = "System Console";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* =============================================================
   THEME: HOPE (Color & Typography Skin)
   ============================================================= */
:root {
    /* Core Palette */
    --primary-dark: #0F2620;  /* Deep Forest Green */
    --accent-lime: #B8EA48;   /* Electric Lime */
    --surface-light: #FFFFFF; /* Clean White */
    --bg-body: #F4F6F8;       /* Light Blue-Grey Background */
    
    /* Text Colors */
    --text-main: #1A1C1E;
    --text-muted: #8F959D;

    /* Spacing & Borders */
    --card-radius: 24px;      /* Very rounded corners */
    --sidebar-width: 260px;
}

[data-bs-theme="dark"] {
    --primary-dark: #B8EA48;  /* Flip primary to Lime in dark mode */
    --accent-lime: #0F2620;   
    --surface-light: #16181A;
    --bg-body: #0b0c0f;
    --text-main: #e7e9ec;
    --text-muted: #a8b0ba;
}

body {
    background: var(--bg-body);
    font-family: "Plus Jakarta Sans", sans-serif; /* Applied Font */
    color: var(--text-main);
    letter-spacing: -0.01em; /* Slight tightening for modern look */
    transition: background .35s ease-in-out;
}

/* OVERRIDE: The Card Style (Solid instead of Glass) */
.hd-glass {
    background: var(--surface-light);
    border: 1px solid rgba(0,0,0,0.04);
    border-radius: var(--card-radius);
    box-shadow: 0 12px 24px rgba(0,0,0,0.04); /* Soft, diffuse shadow */
    transition: all .3s ease;
}
.hd-glass:hover {
    box-shadow: 0 16px 32px rgba(0,0,0,0.08);
    transform: translateY(-4px);
}

/* MAIN CONTENT OFFSET */
.main-content-wrapper {
    margin-left: var(--sidebar-width);
}
@media (max-width: 992px) { .main-content-wrapper { margin-left: 0; } }

/* ICON CIRCLE */
.stat-icon {
    width: 52px; 
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f2f5; /* Light gray backing */
    color: var(--primary-dark);
    transition: all .35s ease;
}

/* Typography Overrides */
h1, h2, h3, h4, h5, h6 {
    font-weight: 700;
    color: var(--primary-dark);
}
[data-bs-theme="dark"] h1, [data-bs-theme="dark"] h2 { color: white; }

/* Custom Badge/Button Colors to match theme */
.badge-lime {
    background-color: var(--accent-lime);
    color: var(--primary-dark);
}

/* Table Styling */
.table-hover tbody tr:hover { background: rgba(184, 234, 72, 0.05); } /* Lime tint on hover */
.table th { 
    font-size: 0.75rem; 
    letter-spacing: 0.05em; 
    color: var(--text-muted); 
    border-bottom-width: 1px;
}

/* Progress Bars - Force the Lime/Green look */
.progress-bar { 
    background-color: var(--primary-dark) !important;
    transition: width .8s ease-in-out; 
}

/* Buttons */
.btn-dark {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
}
.btn-dark:hover {
    background-color: #1a3e34;
}
</style>
</head>

<body>

<div class="d-flex">
        <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php require_once __DIR__ . '/../inc/topbar.php'; ?>
            
            <div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">System Console</h3>
        <div class="d-flex align-items-center gap-2 mt-1">
            <span class="badge badge-lime rounded-pill px-3 py-1 fw-bold">
                <span class="spinner-grow spinner-grow-sm me-1"></span>
                <?= $server_load < 30 ? 'Online' : ($server_load < 50 ? 'Busy' : 'Critical') ?>
            </span>
            <small class="text-muted">v3.1 HD</small>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="backups.php" class="btn btn-light border rounded-pill px-3 fw-semibold">
            <i class="bi bi-database-down me-1"></i> Backup
        </a>
        <a href="settings.php" class="btn btn-dark rounded-pill px-3 fw-semibold">
            <i class="bi bi-sliders me-1"></i> Config
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="hd-glass p-4 h-100">
            <div class="d-flex justify-content-between">
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Tickets</small>
                    <h2 class="fw-bold mt-2 display-6"><?= $open_tickets ?></h2>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-ticket-perforated-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="hd-glass p-4 h-100">
            <div class="d-flex justify-content-between">
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">System Logs</small>
                    <h2 class="fw-bold mt-2 display-6"><?= $today_logs ?></h2>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-activity fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="hd-glass p-4 h-100">
            <div class="d-flex justify-content-between">
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">CPU Usage</small>
                    <h2 class="fw-bold mt-2 display-6"><?= $server_load ?>%</h2>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-cpu-fill fs-4"></i>
                </div>
            </div>
            <div class="progress mt-3" style="height:6px; background: #e9ecef;">
                <div class="progress-bar" style="width:<?= $server_load ?>%"></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="hd-glass p-4 h-100">
            <div class="d-flex justify-content-between">
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Database</small>
                    <h2 class="fw-bold mt-2 display-6"><?= $db_size ?> <span class="fs-6 text-muted">MB</span></h2>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-database-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="hd-glass">
            <div class="d-flex justify-content-between align-items-center px-4 py-4 border-bottom">
                <h6 class="fw-bold mb-0">Incoming Support Tickets</h6>
                <a href="support.php" class="fw-bold small text-decoration-none" style="color: var(--primary-dark)">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Subject</th>
                            <th>User</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-check2-circle fs-1 opacity-25 d-block"></i>
                                    No pending tickets
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark text-truncate" style="max-width:230px;">
                                            <?= htmlspecialchars($t['subject']) ?>
                                        </div>
                                        <div class="small text-muted">
                                            #<?= $t['support_id'] ?> • <?= date('M d, H:i', strtotime($t['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td class="small fw-semibold text-dark"><?= htmlspecialchars($t['sender']) ?></td>
                                    <td>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3">Pending</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="support_view.php?id=<?= $t['support_id'] ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3">Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="hd-glass p-4 h-100">
            <h6 class="fw-bold mb-4">System Diagnostics</h6>

            <div class="mb-4">
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted fw-semibold">Memory</span>
                    <span class="fw-bold text-dark"><?= $memory_usage ?>%</span>
                </div>
                <div class="progress" style="height:8px; border-radius: 4px;">
                    <div class="progress-bar" style="width:<?= $memory_usage ?>%; background-color: var(--accent-lime) !important;"></div>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted fw-semibold">Backup Storage</span>
                    <span class="fw-bold text-dark"><?= $backup_usage ?>%</span>
                </div>
                <div class="progress" style="height:8px; border-radius: 4px;">
                    <div class="progress-bar bg-dark" style="width:<?= $backup_usage ?>%"></div>
                </div>
            </div>

            <div class="p-3 rounded-3 mb-4 small" style="background: #f8f9fa;">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">PHP Version</span>
                    <span class="fw-bold"><?= $php_version ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">MySQL</span>
                    <span class="fw-bold"><?= $mysql_version ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">OS</span>
                    <span class="fw-bold"><?= $server_os ?></span>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <a href="users.php" class="btn btn-light border rounded-3 py-2 text-start fw-semibold">
                    <i class="bi bi-person-badge me-2 text-muted"></i> User Management
                </a>
                <a href="audit_logs.php" class="btn btn-light border rounded-3 py-2 text-start fw-semibold">
                    <i class="bi bi-shield-lock me-2 text-muted"></i> Security Logs
                </a>
                <a href="../public/phpinfo.php" target="_blank" class="btn btn-light border rounded-3 py-2 text-start fw-semibold">
                    <i class="bi bi-code-slash me-2 text-muted"></i> System Config
                </a>
            </div>
        </div>
    </div>
</div>

</div>
<?php require_once __DIR__.'/../inc/footer.php'; ?>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// THEME ENGINE
document.addEventListener("DOMContentLoaded", () => {
    const html = document.documentElement;
    const toggle = document.getElementById("themeToggle");
    const savedTheme = localStorage.getItem("theme") || "light";
    html.setAttribute("data-bs-theme", savedTheme);
    updateThemeIcon(savedTheme);

    if (toggle) toggle.addEventListener("click", () => {
        const current = html.getAttribute("data-bs-theme");
        const next = current === "light" ? "dark" : "light";
        html.setAttribute("data-bs-theme", next);
        localStorage.setItem("theme", next);
        updateThemeIcon(next);
    });

    function updateThemeIcon(theme) {
        if (!toggle) return;
        toggle.querySelector("i").className = theme === "dark" 
            ? "bi bi-sun-fill fs-5" 
            : "bi bi-moon-stars-fill fs-5";
    }
});
</script>


</body>
</html>