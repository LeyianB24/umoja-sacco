<?php
// superadmin/seed_permissions.php
// Seeder for RBAC Permissions - Modern UI
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/auth.php';
require_permission();
require_once __DIR__ . '/../../inc/functions.php';

require_superadmin();

$permissions = [
    // Operations
    ['member_directory', 'Access Member Directory', 'Operations'],
    ['loan_reviews', 'Review & Approve Loans', 'Operations'],
    ['welfare_cases', 'Manage Welfare Cases', 'Operations'],
    ['investment_mgmt', 'Manage Shared Investments', 'Operations'],
    ['fleet_mgmt', 'Manage Vehicle Fleet', 'Operations'],
    ['employee_mgmt', 'Staff Management', 'Operations'],
    ['member_registration', 'Register New Members', 'Operations'],
    ['approve_loans', 'Final Loan Approval', 'Operations'],
    ['manage_members', 'Edit Member Details', 'Operations'],
    
    // Finance
    ['revenue_tracking', 'Track Revenue (Investments & Fleet)', 'Finance'],
    ['payment_processing', 'Process Member Payments', 'Finance'],
    ['expense_mgmt', 'Manage System Expenses', 'Finance'],
    ['loan_disbursement', 'Process Loan Disbursements', 'Finance'],
    ['financial_statements', 'View Ledger & Statements', 'Finance'],
    ['financial_reports', 'Generate Financial Reports', 'Finance'],
    ['record_income', 'Record New Income', 'Finance'],
    ['record_expense', 'Record New Expense', 'Finance'],
    ['view_financials', 'View Financial Dashboards', 'Finance'],
    
    // Maintenance
    ['audit_logs', 'View System Security Logs', 'Maintenance'],
    ['system_backups', 'Generate DB Backups', 'Maintenance'],
    ['system_settings', 'Global System Configuration', 'Maintenance'],
    ['tech_support', 'Resolve Support Tickets', 'Maintenance'],
    ['view_reports', 'Access System Reports', 'Maintenance'],
    ['view_dashboard', 'Access System Dashboard', 'Maintenance']
];

$results = [];
$mapping_results = [];

if (isset($_POST['run_seed'])) {
    foreach ($permissions as $p) {
        $stmt = $conn->prepare("INSERT IGNORE INTO permissions (slug, name, category) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $p[0], $p[1], $p[2]);
        if ($stmt->execute()) {
            $results[] = ['key' => $p[0], 'status' => 'Synced'];
        }
    }

    $initial_mappings = [
        'manager' => ['view_dashboard', 'member_directory', 'loan_reviews', 'approve_loans', 'welfare_cases', 'investment_mgmt', 'fleet_mgmt', 'employee_mgmt', 'view_reports', 'manage_members', 'tech_support'],
        'accountant' => ['view_dashboard', 'member_directory', 'revenue_tracking', 'payment_processing', 'expense_mgmt', 'loan_disbursement', 'financial_statements', 'financial_reports', 'record_income', 'record_expense', 'view_financials', 'tech_support'],
        'admin' => ['view_dashboard', 'member_directory', 'audit_logs', 'system_backups', 'system_settings', 'tech_support'],
        'clerk' => ['view_dashboard', 'member_directory', 'member_registration', 'view_members', 'tech_support']
    ];

    foreach ($initial_mappings as $role_name => $keys) {
        $r_stmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
        $r_stmt->bind_param("s", $role_name);
        $r_stmt->execute();
        $role_id = $r_stmt->get_result()->fetch_assoc()['id'] ?? 0;
        $r_stmt->close();
        
        if ($role_id > 0) {
            foreach ($keys as $key) {
                $p_stmt = $conn->prepare("SELECT id FROM permissions WHERE slug = ?");
                $p_stmt->bind_param("s", $key);
                $p_stmt->execute();
                $p_id = $p_stmt->get_result()->fetch_assoc()['id'] ?? 0;
                $p_stmt->close();
                
                if ($p_id > 0) {
                    $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES ($role_id, $p_id)");
                    $mapping_results[] = ['role' => $role_name, 'permission' => $key];
                }
            }
        }
    }
    flash_set("RBAC Seeding complete!", "success");
}

$pageTitle = "Seed Permissions";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title><?= $pageTitle ?> | Umoja Drivers Sacco</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-dark: #0F2E25;
            --lime-bright: #D0F35D;
            --soft-bg: #F8F9FA;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--soft-bg); }
        .iq-card { background: white; border-radius: 20px; border: 1px solid #eee; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .btn-forest { background: var(--forest-dark); color: white; border-radius: 12px; font-weight: 700; }
        .btn-forest:hover { background: #164e3f; color: white; }
        .badge-finance { background: #e0f2fe; color: #0369a1; }
        .badge-ops { background: #fef3c7; color: #92400e; }
        .badge-maint { background: #f3e8ff; color: #7e22ce; }
        .permission-item { border-left: 4px solid #eee; transition: all 0.2s; }
        .permission-item:hover { border-left-color: var(--lime-bright); background: #f0fdf4; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-5">
                <img src="<?= BASE_URL ?>/public/assets/images/people_logo.png" alt="Logo" height="60" class="mb-3">
                <h2 class="fw-bold text-dark">RBAC Permissions Seeder</h2>
                <p class="text-muted">Initialize or sync system permissions and role mappings.</p>
            </div>

            <?php flash_render(); ?>

            <div class="iq-card p-4 p-md-5">
                <?php if (empty($results)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-shield-lock fs-1 text-muted opacity-25 d-block mb-3"></i>
                        <h4 class="fw-bold">Ready to Seed?</h4>
                        <p class="text-muted mb-4">This will sync <?= count($permissions) ?> permissions across 3 system roles.</p>
                        <form method="POST">
                            <input type="hidden" name="run_seed" value="1">
                            <button type="submit" class="btn btn-forest px-5 py-3 fs-5 shadow-sm">
                                <i class="bi bi-rocket-takeoff me-2"></i>Run System Seeder
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="mb-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Seeding Results</h5>
                        <a href="dashboard.php" class="btn btn-sm btn-light border rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                    
                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <span class="d-block small text-muted text-uppercase fw-bold mb-1">Permissions Synced</span>
                                <span class="h3 fw-bold mb-0"><?= count($results) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <span class="d-block small text-muted text-uppercase fw-bold mb-1">Role Mappings</span>
                                <span class="h3 fw-bold mb-0"><?= count($mapping_results) ?></span>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3">Permissions Matrix</h6>
                    <div class="list-group list-group-flush border rounded-3 overflow-hidden">
                        <?php foreach($permissions as $p): ?>
                            <div class="list-group-item permission-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <div class="fw-bold text-dark"><?= $p[1] ?></div>
                                    <div class="small text-muted">Key: <code><?= $p[0] ?></code></div>
                                </div>
                                <span class="badge rounded-pill px-3 <?= strtolower($p[2]) == 'finance' ? 'badge-finance' : (strtolower($p[2]) == 'operations' ? 'badge-ops' : 'badge-maint') ?>">
                                    <?= $p[2] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center mt-4">
                <p class="small text-muted">Powered by Umoja Drivers Sacco &copy; <?= date('Y') ?></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
