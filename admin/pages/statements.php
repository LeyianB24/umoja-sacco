<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/functions.php';

// 1. AUTHENTICATION & ROLE CHECK
Auth::requireAdmin();

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
<?php $layout->header($pageTitle); ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<?php $layout->header($pageTitle); ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }

        .portal-header {
            position: relative; overflow: hidden;
            border-radius: 2rem; padding: 3rem; margin-bottom: 2.5rem;
            background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
            color: white;
        }
        .portal-header::before {
            content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(190, 242, 100, 0.05) 0%, transparent 70%);
            border-radius: 50%;
        }
        .stat-badge {
            background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 1rem;
            padding: 1rem 1.5rem; text-align: center;
        }

        /* Forms & Cards */
        .glass-card { border-radius: 1.5rem; padding: 2.5rem; height: 100%; border: 1px solid var(--border-color); background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); }

        .form-label { font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--forest); }
        .form-control, .form-select { border-radius: 0.85rem; padding: 0.75rem 1.25rem; font-weight: 500; }

        /* Type Selector */
        .type-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .type-card {
            border: 2px solid var(--border-color); border-radius: 1rem; padding: 1.5rem; cursor: pointer;
            text-align: center; transition: all 0.2s;
        }
        .type-card i { font-size: 1.75rem; display: block; margin-bottom: 0.75rem; opacity: 0.4; }
        .type-card div { font-weight: 700; font-size: 0.85rem; }
        
        .type-input { display: none; }
        .type-input:checked + .type-card { border-color: var(--lime); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .type-input:checked + .type-card i { opacity: 1; transform: scale(1.1); color: var(--forest); }

        /* Submit Button */
        .btn-premium {
            background: var(--lime); color: #000000; border-radius: 1rem;
            padding: 1.25rem; font-weight: 800; border: none; width: 100%;
            display: flex; align-items: center; justify-content: center; gap: 0.75rem;
            transition: all 0.3s;
        }
        .btn-premium:hover { opacity: 0.9; transform: translateY(-2px); }

        /* Preview State */
        .preview-placeholder {
            border: 2px dashed var(--border-color); border-radius: 1.5rem;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 3rem; height: 100%; min-height: 400px;
        }

        /* Select2 Customization */
        .select2-container--bootstrap-5 .select2-selection { border-radius: 0.85rem; height: auto; padding: 0.5rem 0.75rem; }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle); ?>
        
        <div class="container-fluid">
            <!-- Portal Header -->
            <div class="portal-header shadow-lg">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 mb-3 fw-bold">V28 Report Engine</span>
                        <h1 class="display-5 fw-800 mb-2">Statement Portal</h1>
                        <p class="opacity-75 fs-5">Generate certified financial ledgers and transaction histories with digital verification.</p>
                    </div>
                    <div class="col-lg-5 text-end d-none d-lg-block">
                        <div class="d-flex justify-content-end gap-3">
                            <div class="stat-badge">
                                <div class="h4 fw-800 mb-0"><?= number_format((float)($stats['total_txns'] ?? 0)) ?></div>
                                <div class="small opacity-60 fw-bold">Total Processed</div>
                            </div>
                            <div class="stat-badge" style="border-color: var(--lime);">
                                <div class="h4 fw-800 mb-0 text-lime"><?= $stats['txns_today'] ?></div>
                                <div class="small opacity-60 fw-bold text-white">Processed Today</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../../inc/finance_nav.php'; ?>

            <div class="row g-4 mt-1">
                <div class="col-lg-7">
                    <div class="glass-card">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-forest bg-opacity-10 text-forest p-3 rounded-4 me-3">
                                <i class="bi bi-gear-fill fs-4"></i>
                            </div>
                            <h4 class="fw-800 mb-0">Report Configuration</h4>
                        </div>

                        <form action="../api/generate_statement.php" method="POST" target="_blank">
                            <!-- Member Selection -->
                            <div class="mb-4">
                                <label class="form-label">Select Sacco Member</label>
                                <select name="member_id" class="form-select select2-member" required>
                                    <option value="">Search by Name, Reg No or National ID...</option>
                                    <?php foreach($members as $m): ?>
                                        <option value="<?= $m['member_id'] ?>">
                                            <?= esc($m['full_name']) ?> (<?= $m['member_reg_no'] ?>) - <?= $m['national_id'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Date Range -->
                            <div class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <label class="form-label">Start Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-calendar-event"></i></span>
                                        <input type="date" name="start_date" class="form-control border-start-0 ps-0" value="<?= date('Y-m-01') ?>" required>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">End Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-calendar-check"></i></span>
                                        <input type="date" name="end_date" class="form-control border-start-0 ps-0" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Statement Type -->
                            <div class="mb-4">
                                <label class="form-label mb-3">Report Template</label>
                                <div class="type-grid">
                                    <label class="mb-0">
                                        <input type="radio" name="report_type" value="full" class="type-input" checked>
                                        <div class="type-card">
                                            <i class="bi bi-journal-check"></i>
                                            <div>Full Ledger</div>
                                        </div>
                                    </label>
                                    <label class="mb-0">
                                        <input type="radio" name="report_type" value="savings" class="type-input">
                                        <div class="type-card">
                                            <i class="bi bi-piggy-bank-fill"></i>
                                            <div>Savings Flow</div>
                                        </div>
                                    </label>
                                    <label class="mb-0">
                                        <input type="radio" name="report_type" value="loans" class="type-input">
                                        <div class="type-card">
                                            <i class="bi bi-person-badge-fill"></i>
                                            <div>Loan Audit</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Format -->
                            <div class="mb-5 p-3 rounded-4 bg-light">
                                <label class="form-label d-block mb-3">Export Format</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="format" id="fmtPdf" value="pdf" checked>
                                    <label class="btn btn-outline-dark rounded-start-3 fw-bold py-2" for="fmtPdf">
                                        <i class="bi bi-file-earmark-pdf-fill me-2"></i>PDF
                                    </label>
                                    <input type="radio" class="btn-check" name="format" id="fmtExcel" value="excel">
                                    <label class="btn btn-outline-dark rounded-end-3 fw-bold py-2" for="fmtExcel">
                                        <i class="bi bi-file-earmark-excel-fill me-2"></i>Excel
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn-premium shadow">
                                <i class="bi bi-lightning-charge-fill"></i>
                                <span>GENERATE CERTIFIED REPORT</span>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="glass-card">
                        <div class="preview-placeholder">
                            <div class="mb-4">
                                <div class="bg-white rounded-circle shadow-sm p-4">
                                    <i class="bi bi-file-earmark-medical display-4 text-muted opacity-50"></i>
                                </div>
                            </div>
                            <h5 class="fw-800  mb-2">Awaiting Generation</h5>
                            <p class="small text-center px-lg-5 text-muted">Please configure the report parameters and click the generate button. The official statement will open in a secure window.</p>
                            
                            <div class="w-100 mt-5 pt-4 border-top">
                                <h6 class="form-label mb-3 opacity-50">Related Utilities</h6>
                                <div class="d-grid gap-2">
                                    <a href="reports.php" class="btn btn-light text-start p-3 rounded-4 border-0 d-flex align-items-center">
                                        <div class="bg-forest bg-opacity-10 text-forest p-2 rounded-3 me-3">
                                            <i class="bi bi-graph-up-arrow"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small ">Analytics Dashboard</div>
                                            <div class="text-muted" style="font-size: 0.7rem;">Real-time performance metrics</div>
                                        </div>
                                        <i class="bi bi-chevron-right opacity-25"></i>
                                    </a>
                                    <a href="audit_logs.php" class="btn btn-light text-start p-3 rounded-4 border-0 d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 text-success p-2 rounded-3 me-3">
                                            <i class="bi bi-shield-lock"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small ">System Audit</div>
                                            <div class="text-muted" style="font-size: 0.7rem;">Verified transaction logs</div>
                                        </div>
                                        <i class="bi bi-chevron-right opacity-25"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php $layout->footer(); ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery !== 'undefined') {
            $('.select2-member').select2({
                theme: 'bootstrap-5',
                placeholder: $(this).data('placeholder'),
                width: '100%'
            });
        }
    });
</script>
</body>
</html>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof jQuery !== 'undefined') {
                $('.select2-member').select2({
                    theme: 'bootstrap-5',
                    placeholder: $(this).data('placeholder'),
                    width: '100%'
                });
            }
        });
    </script>
