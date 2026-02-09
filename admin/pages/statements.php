<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

/**
 * admin/statements.php
 * Premium Financial Statement Portal - V28 Glassmorphism
 * Corporate-grade ledger generation.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. AUTHENTICATION & ROLE CHECK
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$db = $conn;

// 2. FETCH DATA FOR FILTERS
$members = $db->query("SELECT member_id, full_name, national_id, member_reg_no FROM members WHERE status='active' ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);

// Recent activity for the banner
$stats = $db->query("SELECT 
    COUNT(*) as total_txns, 
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as txns_today
    FROM transactions")->fetch_assoc();

$pageTitle = "Statement Portal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | USMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <style>
        :root { --forest: #0F2E25; --lime: #D0F35D; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f4f3; color: #1a1a1a; }
        
        .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; }
        
        /* Banner Styles */
        .portal-banner {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
            position: relative; overflow: hidden;
        }
        .portal-banner::after {
            content: ''; position: absolute; bottom: -20%; right: -5%; width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(208, 243, 93, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        /* Form & Glass Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
            border-radius: 24px; border: 1px solid rgba(255,255,255,0.5);
            padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }
        
        .form-label { font-weight: 700; color: var(--forest); font-size: 0.9rem; margin-bottom: 10px; }
        .form-control, .form-select {
            border-radius: 15px; border: 1.5px solid #e2e8f0; padding: 12px 20px;
            transition: 0.3s; background: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            background: white; border-color: var(--forest); box-shadow: 0 0 0 4px rgba(15, 46, 37, 0.1);
        }
        
        .type-selector {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;
        }
        .type-option {
            border: 2px solid #e2e8f0; border-radius: 20px; padding: 20px; cursor: pointer;
            transition: 0.3s; text-align: center; background: white;
        }
        .type-option i { font-size: 1.5rem; margin-bottom: 10px; display: block; color: var(--forest); opacity: 0.5; }
        .type-option div { font-weight: 700; font-size: 0.85rem; }
        
        .type-radio { display: none; }
        .type-radio:checked + .type-option {
            border-color: var(--forest); background: rgba(15, 46, 37, 0.02);
        }
        .type-radio:checked + .type-option i { opacity: 1; color: var(--forest); }
        .type-radio:checked + .type-option { box-shadow: 0 10px 20px rgba(15, 46, 37, 0.05); }

        .btn-generate {
            background: var(--forest); color: white; border-radius: 18px;
            padding: 15px 30px; font-weight: 800; border: none;
            transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-generate:hover { background: #1a4d3e; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(15, 46, 37, 0.2); color: white; }

        .preview-box {
            background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 24px;
            padding: 40px; text-align: center; color: #64748b; height: 100%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        
        /* Member Badge in select */
        .member-reg { font-size: 0.7rem; background: #e2e8f0; padding: 2px 8px; border-radius: 5px; color: #475569; margin-left: 10px; }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content">
    <?php $layout->topbar($pageTitle ?? ''); ?>

    <div class="portal-banner shadow">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3">V28 Report Engine</span>
                <h1 class="display-5 fw-800 mb-2">Statement Portal</h1>
                <p class="opacity-75 fs-5">Generate certified financial ledgers and transaction histories for Sacco members.</p>
            </div>
            <div class="col-lg-5 text-end d-none d-lg-block">
                <div class="d-inline-flex gap-4">
                    <div class="text-center">
                        <div class="h3 fw-bold mb-0"><?= number_format((float)($stats['total_txns'] ?? 0)) ?></div>
                        <div class="small opacity-75">Processed</div>
                    </div>
                    <div class="text-center border-start ps-4">
                        <div class="h3 fw-bold mb-0 text-lime"><?= $stats['txns_today'] ?></div>
                        <div class="small opacity-75">Today</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="glass-card">
                <h4 class="fw-800 mb-4 text-forest">Report Configuration</h4>
                
                <form action="generate_statement.php" method="POST" target="_blank">
                    <!-- Member Selection -->
                    <div class="mb-4">
                        <label class="form-label">Search / Select Member</label>
                        <select name="member_id" class="form-select form-select-lg" required id="memberSelect">
                            <option value="">-- Start typing name or ID --</option>
                            <?php foreach($members as $m): ?>
                                <option value="<?= $m['member_id'] ?>">
                                    <?= esc($m['full_name']) ?> (<?= $m['member_reg_no'] ?>) - ID: <?= $m['national_id'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label">From Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-01') ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">To Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Statement Type -->
                    <label class="form-label">Statement Template</label>
                    <div class="type-selector">
                        <label>
                            <input type="radio" name="report_type" value="full" class="type-radio" checked>
                            <div class="type-option">
                                <i class="bi bi-journal-text"></i>
                                <div>Full Ledger</div>
                            </div>
                        </label>
                        <label>
                            <input type="radio" name="report_type" value="savings" class="type-radio">
                            <div class="type-option">
                                <i class="bi bi-piggy-bank"></i>
                                <div>Savings Only</div>
                            </div>
                        </label>
                        <label>
                            <input type="radio" name="report_type" value="loans" class="type-radio">
                            <div class="type-option">
                                <i class="bi bi-cash-stack"></i>
                                <div>Loan Summary</div>
                            </div>
                        </label>
                    </div>

                    <!-- Output Format -->
                    <div class="mb-4">
                        <label class="form-label">Output Delivery</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="fmtPdf" value="pdf" checked>
                                <label class="form-check-label" for="fmtPdf">Certified PDF (Print-ready)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="fmtCsv" value="csv">
                                <label class="form-check-label" for="fmtCsv">Data Export (CSV/Spreadsheet)</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-generate w-100 shadow">
                        <i class="bi bi-file-earmark-pdf"></i>
                        <span>Generate Certified Statement</span>
                    </button>
                    
                    <p class="text-center mt-3 mb-0 small text-muted">
                        <i class="bi bi-shield-check me-1"></i> 
                        All statements include a digital verification stamp and ledger hash.
                    </p>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="preview-box h-100">
                <div class="mb-4">
                    <i class="bi bi-file-earmark-text display-3 opacity-25"></i>
                </div>
                <h5 class="fw-bold mb-2">Live Preview Not Loaded</h5>
                <p class="small px-4">Configure the parameters on the left and click generate to view the official statement in a new secure window.</p>
                
                <hr class="w-75 opacity-25 my-4">
                
                <div class="text-start w-100 px-4">
                    <h6 class="fw-bold small text-uppercase opacity-50 mb-3">Related Tools</h6>
                    <a href="reports.php" class="d-flex align-items-center gap-3 text-decoration-none text-forest mb-3">
                        <div class="bg-light p-2 rounded-3"><i class="bi bi-pie-chart"></i></div>
                        <div class="small fw-bold">Financial Analytics Hub</div>
                        <i class="bi bi-chevron-right ms-auto opacity-25"></i>
                    </a>
                    <a href="audit_logs.php" class="d-flex align-items-center gap-3 text-decoration-none text-forest">
                        <div class="bg-light p-2 rounded-3"><i class="bi bi-shield-lock"></i></div>
                        <div class="small fw-bold">System Audit Logs</div>
                        <i class="bi bi-chevron-right ms-auto opacity-25"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Opting out of Select2 for simplicity in this turn unless requested, standard select is styled -->
</body>
</html>




