<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

// usms/admin/pages/backups.php
// Enhanced IT Admin - Database Maintenance (Matches "Hope UI" Visual Style)

if (session_status() === PHP_SESSION_NONE) session_start();

// Enforce Admin Role
require_admin();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_permission();

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['full_name'] ?? 'IT Admin';
$db         = $conn;

?>
<?php $layout->header($pageTitle ?? 'Database Backups'); ?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }
        
        .glass-table-card { 
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); 
            border-radius: 24px; border: 1px solid rgba(255,255,255,0.4); 
            overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.03);
        }

        .card-custom { border-radius: 24px; border: 1px solid var(--border-color); }

        /* --- Table Styling --- */
        .table-custom thead th {
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            border-top: none;
            padding: 16px;
        }
        .table-custom tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        /* --- Buttons --- */
        .btn-lime {
            background-color: var(--lime);
            color: var(--forest);
            font-weight: 700;
            border-radius: 12px;
            padding: 12px 24px;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-lime:hover { 
            background-color: var(--lime-hover);
            transform: translateY(-2px); 
            box-shadow: 0 10px 20px rgba(208, 243, 93, 0.2);
        }

        .avatar-circle {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem;
        }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'Database Backups'); ?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1" style="color: var(--forest);">Database Backups</h2>
                    <p class="text-muted mb-0">Monitor system health and generate recovery points</p>
                </div>
                <form method="post" onsubmit="return confirm('Generate full SQL backup?');">
                    <input type="hidden" name="action" value="create_backup">
                    <button class="btn btn-lime">
                        <i class="bi bi-cloud-arrow-down me-2"></i> Create Backup Now
                    </button>
                </form>
            </div>

            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card-custom p-4 mb-4" style="background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%); color: white;">
                        <h6 class="text-white-50 small text-uppercase fw-bold mb-4">Storage Overview</h6>
                        <div class="d-flex align-items-end gap-2 mb-2">
                            <h1 class="display-5 fw-bold mb-0 text-white"><?= $stats['size_mb'] ?></h1>
                            <span class="h4 mb-2 opacity-50">MB</span>
                        </div>
                        <p class="small opacity-75 mb-0">Total footprint of Umoja SACCO DB</p>
                    </div>

                    <div class="card-custom p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="stat-circle bg-light text-success">
                                <i class="bi bi-table"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0"><?= $stats['tables'] ?></h4>
                                <small class="text-muted">Total Tables</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-circle bg-light text-primary">
                                <i class="bi bi-hdd-stack"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0"><?= number_format((float)($stats['rows'] ?? 0)) ?></h4>
                                <small class="text-muted">Database Records</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-custom mt-4 p-4 bg-light border-0">
                        <h6 class="fw-bold small mb-2"><i class="bi bi-shield-check text-success me-2"></i>Security Policy</h6>
                        <p class="text-muted small mb-0">Backups are generated as plain SQL files. Ensure they are stored in an encrypted external volume after download.</p>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card-custom overflow-hidden">
                        <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">Backup Logs</h5>
                            <span class="badge bg-dark text-white rounded-pill px-3 py-2">Last 8 Sessions</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Date & Time</th>
                                        <th>Administrator</th>
                                        <th class="text-end">Format</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($backup_logs->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                                No backup history found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($log = $backup_logs->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 small">
                                                        <i class="bi bi-check-circle-fill me-1"></i> Success
                                                    </span>
                                                </td>
                                                <td class="fw-medium ">
                                                    <?= date('M d, Y', strtotime($log['created_at'])) ?>
                                                    <div class="text-muted x-small"><?= date('H:i A', strtotime($log['created_at'])) ?></div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:30px; height:30px; font-size:0.7rem;">
                                                            <?= getInitials($log['username']) ?>
                                                        </div>
                                                        <span class="small fw-semibold"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <code class="bg-light px-2 py-1 rounded  fw-bold">.SQL</code>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 bg-light text-center">
                            <a href="audit_logs.php" class="text-decoration-none small fw-bold ">View Full Audit Trail <i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php $layout->footer(); ?>
    </div>
</div>






