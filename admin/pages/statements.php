<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/functions.php';

Auth::requireAdmin();
$layout = LayoutManager::create('admin');
$db = $conn;

$members = $db->query("SELECT member_id, full_name, national_id, member_reg_no FROM members WHERE status='active' ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);

$stats = $db->query("SELECT 
    COUNT(*) as total_txns, 
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as txns_today
    FROM transactions")->fetch_assoc();

$pageTitle = "Statement Portal";
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

<style>
/* ============================================================
   STATEMENT PORTAL — JAKARTA SANS + GLASSMORPHISM THEME
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
.hp-hero .ring { position:absolute;border-radius:50%;border:1px solid rgba(181,244,60,0.1);pointer-events:none; }
.hp-hero .ring1 { width:320px;height:320px;top:-80px;right:-80px; }
.hp-hero .ring2 { width:500px;height:500px;top:-160px;right:-160px; }
.hero-badge {
    display:inline-flex;align-items:center;gap:0.45rem;
    background:rgba(181,244,60,0.12);border:1px solid rgba(181,244,60,0.25);
    color:var(--lime-soft);border-radius:100px;padding:0.28rem 0.85rem;
    font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;
    margin-bottom:0.9rem;position:relative;
}
.hero-badge::before { content:'';width:6px;height:6px;border-radius:50%;background:var(--lime);animation:pulse-dot 2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

.stat-badge {
    background:rgba(255,255,255,0.08);
    border:1px solid rgba(255,255,255,0.12);
    border-radius:var(--radius-md);
    padding:1rem 1.4rem;
    text-align:center;
    min-width:120px;
    position:relative;
}
.stat-badge.lime-border { border-color:rgba(181,244,60,0.35); }
.stat-badge-value { font-size:1.7rem;font-weight:800;letter-spacing:-0.04em;color:#fff;line-height:1; }
.stat-badge-value.lime  { color:var(--lime); }
.stat-badge-label { font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.45);margin-top:0.3rem; }

/* ── Layout Cards ── */
.form-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    height: 100%;
}
.form-card-header {
    padding: 1.4rem 1.8rem;
    border-bottom: 1px solid var(--border);
    background: #fff;
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.form-card-header-icon {
    width: 40px; height: 40px; border-radius: var(--radius-sm);
    background: var(--lime-glow-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: var(--forest); flex-shrink: 0;
}
.form-card-header h4 { font-weight: 800; font-size: 1rem; color: var(--text-primary); margin: 0; letter-spacing: -0.02em; }
.form-card-body { padding: 1.8rem; }

/* ── Form Fields ── */
.field-label {
    font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.09em; color: var(--text-muted); margin-bottom: 0.55rem; display: block;
}
.form-control-enh, .form-select-enh {
    border-radius: var(--radius-md); border: 1.5px solid rgba(13,43,31,0.1);
    font-size: 0.875rem; font-weight: 500; padding: 0.65rem 1rem;
    width: 100%; color: var(--text-primary); background: #f8faf9;
    font-family: 'Plus Jakarta Sans', sans-serif !important; transition: var(--transition);
    appearance: none;
}
.form-control-enh:focus, .form-select-enh:focus {
    outline: none; border-color: var(--lime); background: #fff; box-shadow: var(--shadow-glow);
}
.date-input-wrap { position: relative; }
.date-input-wrap i {
    position: absolute; top: 50%; left: 14px;
    transform: translateY(-50%); color: var(--text-muted); font-size: 0.82rem; pointer-events: none;
}
.date-input-wrap .form-control-enh { padding-left: 2.4rem; }

/* Select2 override */
.select2-container--bootstrap-5 .select2-selection {
    border-radius: var(--radius-md) !important;
    border: 1.5px solid rgba(13,43,31,0.1) !important;
    background: #f8faf9 !important;
    font-size: 0.875rem; font-weight: 500;
    min-height: 44px !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    padding: 0.45rem 1rem !important;
    transition: var(--transition);
}
.select2-container--bootstrap-5.select2-container--focus .select2-selection,
.select2-container--bootstrap-5.select2-container--open .select2-selection {
    border-color: var(--lime) !important;
    background: #fff !important;
    box-shadow: var(--shadow-glow) !important;
}
.select2-container--bootstrap-5 .select2-dropdown {
    border-radius: var(--radius-md) !important;
    border: 1.5px solid rgba(13,43,31,0.1) !important;
    box-shadow: var(--shadow-lg) !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}
.select2-container--bootstrap-5 .select2-results__option--highlighted {
    background-color: #f0faf4 !important;
    color: var(--forest) !important;
}
.select2-container--bootstrap-5 .select2-search__field {
    border-radius: var(--radius-sm) !important;
    border: 1.5px solid rgba(13,43,31,0.1) !important;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Report Type Cards ── */
.type-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
.type-input { display: none; }
.type-card {
    border: 1.5px solid var(--border);
    border-radius: var(--radius-md);
    padding: 1.1rem 0.75rem;
    cursor: pointer;
    text-align: center;
    transition: var(--transition);
    background: #f8faf9;
    position: relative;
    overflow: hidden;
}
.type-card::before {
    content:''; position:absolute; inset:0;
    background:var(--lime-glow); opacity:0; transition:var(--transition);
}
.type-card i {
    font-size: 1.5rem; display: block; margin-bottom: 0.6rem;
    color: var(--text-muted); transition: var(--transition);
}
.type-card-label { font-weight: 700; font-size: 0.78rem; color: var(--text-muted); transition: var(--transition); }
.type-input:checked + .type-card {
    border-color: var(--lime);
    background: #fff;
    box-shadow: 0 4px 16px rgba(181,244,60,0.25);
}
.type-input:checked + .type-card::before { opacity: 1; }
.type-input:checked + .type-card i { color: var(--forest); transform: scale(1.1); }
.type-input:checked + .type-card .type-card-label { color: var(--forest); }
.type-card:hover { border-color: rgba(181,244,60,0.5); background: #fff; }
.type-card:hover i { color: var(--forest-light); }

/* ── Format Toggle ── */
.format-section {
    background: var(--bg-muted);
    border-radius: var(--radius-md);
    padding: 1.1rem 1.2rem;
    border: 1px solid var(--border);
}
.format-btn-group { display: flex; gap: 0.5rem; margin-top: 0.75rem; }
.format-input { display: none; }
.format-label {
    flex: 1; padding: 0.6rem 1rem;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border);
    background: var(--surface);
    font-size: 0.82rem; font-weight: 700;
    color: var(--text-muted);
    cursor: pointer;
    text-align: center;
    transition: var(--transition);
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.format-label:hover { background: #f0faf4; border-color: rgba(13,43,31,0.15); color: var(--forest); }
.format-input:checked + .format-label {
    background: var(--forest);
    color: #fff;
    border-color: var(--forest);
    box-shadow: var(--shadow-sm);
}

/* ── Generate Button ── */
.btn-generate {
    width: 100%; padding: 0.85rem;
    border-radius: var(--radius-md);
    border: none;
    background: var(--lime);
    color: var(--forest);
    font-weight: 800;
    font-size: 0.9rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 0.65rem;
    box-shadow: 0 4px 20px rgba(181,244,60,0.35);
    transition: var(--transition);
}
.btn-generate:hover {
    background: var(--lime-soft);
    box-shadow: 0 6px 28px rgba(181,244,60,0.45);
    transform: translateY(-2px);
}
.btn-generate i { font-size: 1rem; }

/* ── Preview Panel ── */
.preview-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-md);
    height: 100%;
    display: flex;
    flex-direction: column;
}
.preview-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    text-align: center;
}
.preview-icon-wrap {
    width: 80px; height: 80px; border-radius: 20px;
    background: var(--bg-muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; color: #c4d4cb;
    margin-bottom: 1.2rem;
    border: 1px solid var(--border);
}
.preview-title { font-weight: 800; font-size: 1rem; color: var(--text-primary); margin-bottom: 0.35rem; }
.preview-sub { font-size: 0.82rem; color: var(--text-muted); max-width: 240px; line-height: 1.55; margin: 0 auto; }

/* Utility Links */
.utility-links { padding: 1.4rem 1.8rem; border-top: 1px solid var(--border); }
.utility-label { font-size: 0.67rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); margin-bottom: 0.75rem; }
.utility-link {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    background: var(--bg-muted);
    border: 1px solid var(--border);
    text-decoration: none;
    transition: var(--transition);
    margin-bottom: 0.5rem;
}
.utility-link:last-child { margin-bottom: 0; }
.utility-link:hover { background: #f0faf4; border-color: rgba(13,43,31,0.12); transform: translateX(3px); }
.utility-link-icon {
    width: 36px; height: 36px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; flex-shrink: 0;
}
.utility-link-title { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
.utility-link-sub   { font-size: 0.7rem; color: var(--text-muted); margin-top: 1px; }
.utility-link-arrow { margin-left: auto; color: var(--text-muted); font-size: 0.75rem; opacity: 0.4; transition: var(--transition); flex-shrink: 0; }
.utility-link:hover .utility-link-arrow { opacity: 1; transform: translateX(2px); }

/* Section divider */
.field-divider { border: none; border-top: 1px solid var(--border); margin: 1.4rem 0; }

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

@media (max-width:768px) {
    .hp-hero { padding:2rem 1.5rem 4rem; }
    .type-grid { grid-template-columns: repeat(2,1fr); }
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
                <div class="col-lg-7">
                    <div class="hero-badge">V28 Report Engine</div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;">
                        Statement Portal
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin:0;">
                        Generate certified financial ledgers and transaction histories with digital verification.
                    </p>
                </div>
                <div class="col-lg-5 text-end d-none d-lg-flex align-items-center justify-content-end gap-3" style="position:relative;">
                    <div class="stat-badge">
                        <div class="stat-badge-value"><?= number_format((float)($stats['total_txns'] ?? 0)) ?></div>
                        <div class="stat-badge-label">Total Processed</div>
                    </div>
                    <div class="stat-badge lime-border">
                        <div class="stat-badge-value lime"><?= $stats['txns_today'] ?></div>
                        <div class="stat-badge-label">Today</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px; position:relative; z-index:10;">

            <?php if (function_exists('flash_render')) flash_render(); ?>

            <div class="row g-4 mt-1">

                <!-- Config Form -->
                <div class="col-lg-7">
                    <div class="form-card slide-up" style="animation-delay:0.06s;">
                        <div class="form-card-header">
                            <div class="form-card-header-icon">
                                <i class="bi bi-gear-fill"></i>
                            </div>
                            <h4>Report Configuration</h4>
                        </div>
                        <div class="form-card-body">
                            <form action="../api/generate_statement.php" method="POST" target="_blank">

                                <!-- Member Selection -->
                                <div class="mb-4">
                                    <label class="field-label">Select SACCO Member</label>
                                    <select name="member_id" class="form-select select2-member" required>
                                        <option value="">Search by Name, Reg No or National ID...</option>
                                        <?php foreach($members as $m): ?>
                                            <option value="<?= $m['member_id'] ?>">
                                                <?= esc($m['full_name']) ?> (<?= $m['member_reg_no'] ?>) — <?= $m['national_id'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <hr class="field-divider">

                                <!-- Date Range -->
                                <div class="mb-4">
                                    <label class="field-label">Date Range</label>
                                    <div class="row g-2">
                                        <div class="col-sm-6">
                                            <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);margin-bottom:0.35rem;text-transform:uppercase;letter-spacing:0.07em;">Start Date</div>
                                            <div class="date-input-wrap">
                                                <i class="bi bi-calendar-event"></i>
                                                <input type="date" name="start_date" class="form-control-enh" value="<?= date('Y-m-01') ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);margin-bottom:0.35rem;text-transform:uppercase;letter-spacing:0.07em;">End Date</div>
                                            <div class="date-input-wrap">
                                                <i class="bi bi-calendar-check"></i>
                                                <input type="date" name="end_date" class="form-control-enh" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="field-divider">

                                <!-- Report Type -->
                                <div class="mb-4">
                                    <label class="field-label">Report Template</label>
                                    <div class="type-grid">
                                        <label class="mb-0">
                                            <input type="radio" name="report_type" value="full" class="type-input" checked>
                                            <div class="type-card">
                                                <i class="bi bi-journal-check"></i>
                                                <div class="type-card-label">Full Ledger</div>
                                            </div>
                                        </label>
                                        <label class="mb-0">
                                            <input type="radio" name="report_type" value="savings" class="type-input">
                                            <div class="type-card">
                                                <i class="bi bi-piggy-bank-fill"></i>
                                                <div class="type-card-label">Savings Flow</div>
                                            </div>
                                        </label>
                                        <label class="mb-0">
                                            <input type="radio" name="report_type" value="loans" class="type-input">
                                            <div class="type-card">
                                                <i class="bi bi-person-badge-fill"></i>
                                                <div class="type-card-label">Loan Audit</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <hr class="field-divider">

                                <!-- Export Format -->
                                <div class="mb-5">
                                    <div class="format-section">
                                        <label class="field-label" style="margin-bottom:0;">Export Format</label>
                                        <div class="format-btn-group">
                                            <input type="radio" class="format-input" name="format" id="fmtPdf" value="pdf" checked>
                                            <label class="format-label" for="fmtPdf">
                                                <i class="bi bi-file-earmark-pdf-fill" style="color:#ef4444;"></i>PDF Document
                                            </label>
                                            <input type="radio" class="format-input" name="format" id="fmtExcel" value="excel">
                                            <label class="format-label" for="fmtExcel">
                                                <i class="bi bi-file-earmark-excel-fill" style="color:#16a34a;"></i>Excel Sheet
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Generate Button -->
                                <button type="submit" class="btn-generate">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    Generate Certified Report
                                </button>

                            </form>
                        </div>
                    </div>
                </div>

                <!-- Preview / Utilities Panel -->
                <div class="col-lg-5">
                    <div class="preview-card slide-up" style="animation-delay:0.12s;">
                        <div class="preview-empty">
                            <div class="preview-icon-wrap">
                                <i class="bi bi-file-earmark-medical"></i>
                            </div>
                            <div class="preview-title">Awaiting Generation</div>
                            <p class="preview-sub">Configure the report parameters on the left and click Generate. The certified statement will open in a secure new window.</p>

                            <!-- Quick Stats row -->
                            <div style="display:flex;gap:0.75rem;margin-top:1.5rem;width:100%;max-width:320px;">
                                <div style="flex:1;background:var(--bg-muted);border:1px solid var(--border);border-radius:var(--radius-md);padding:0.85rem;text-align:center;">
                                    <div style="font-size:1.4rem;font-weight:800;color:var(--forest);letter-spacing:-0.04em;"><?= number_format((float)($stats['total_txns'] ?? 0)) ?></div>
                                    <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.09em;color:var(--text-muted);margin-top:0.2rem;">Total Txns</div>
                                </div>
                                <div style="flex:1;background:var(--lime-glow-sm);border:1px solid rgba(181,244,60,0.25);border-radius:var(--radius-md);padding:0.85rem;text-align:center;">
                                    <div style="font-size:1.4rem;font-weight:800;color:var(--forest);letter-spacing:-0.04em;"><?= $stats['txns_today'] ?></div>
                                    <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.09em;color:var(--text-muted);margin-top:0.2rem;">Today</div>
                                </div>
                                <div style="flex:1;background:var(--bg-muted);border:1px solid var(--border);border-radius:var(--radius-md);padding:0.85rem;text-align:center;">
                                    <div style="font-size:1.4rem;font-weight:800;color:var(--forest);letter-spacing:-0.04em;"><?= count($members) ?></div>
                                    <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.09em;color:var(--text-muted);margin-top:0.2rem;">Members</div>
                                </div>
                            </div>
                        </div>

                        <!-- Utility Links -->
                        <div class="utility-links">
                            <div class="utility-label">Related Utilities</div>
                            <a href="reports.php" class="utility-link">
                                <div class="utility-link-icon" style="background:var(--lime-glow-sm);color:var(--forest);">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <div>
                                    <div class="utility-link-title">Analytics Dashboard</div>
                                    <div class="utility-link-sub">Real-time performance metrics</div>
                                </div>
                                <i class="bi bi-chevron-right utility-link-arrow"></i>
                            </a>
                            <a href="audit_logs.php" class="utility-link">
                                <div class="utility-link-icon" style="background:#f0fdf4;color:#166534;">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                                <div>
                                    <div class="utility-link-title">System Audit</div>
                                    <div class="utility-link-sub">Verified transaction logs</div>
                                </div>
                                <i class="bi bi-chevron-right utility-link-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div><!-- /overlap -->

    </div><!-- /container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
        $('.select2-member').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search by name, reg no or national ID...',
            allowClear: true
        });
    });
    </script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->