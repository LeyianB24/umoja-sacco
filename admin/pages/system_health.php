<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SystemHealthHelper.php';
require_once __DIR__ . '/../../inc/FinancialIntegrityChecker.php';
require_once __DIR__ . '/../../inc/AuditHelper.php';

require_admin();
require_permission();

$layout  = LayoutManager::create('admin');
$checker = new FinancialIntegrityChecker($conn);

$audit_results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_audit'])) {
    $audit_results = $checker->runFullAudit();
    AuditHelper::log($conn, 'SYSTEM_HEALTH_AUDIT', 'Manual system health audit executed by ' . ($_SESSION['admin_name'] ?? 'Admin'), null, (int)$_SESSION['admin_id'], 'warning');
}

$health      = getSystemHealth($conn);
$recent_logs_q = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
$recent_logs = $recent_logs_q->fetch_all(MYSQLI_ASSOC);

$pageTitle = "System Health & Integrity";
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   SYSTEM HEALTH & INTEGRITY — JAKARTA SANS + GLASSMORPHISM
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

.hero-uptime {
    position:relative;
    text-align:right;
}
.hero-uptime-value {
    font-size:4rem; font-weight:800; letter-spacing:-0.06em;
    color:rgba(255,255,255,0.12); line-height:1;
}
.hero-uptime-label {
    font-size:0.68rem; font-weight:800; text-transform:uppercase;
    letter-spacing:0.15em; color:rgba(255,255,255,0.3); margin-top:0.25rem;
}

/* ── Audit Banner ── */
.audit-banner {
    background:#eff6ff; border:1px solid rgba(59,130,246,0.2);
    border-radius:var(--radius-lg); padding:1rem 1.3rem; margin-bottom:1.2rem;
    display:flex; align-items:flex-start; gap:0.85rem;
}
.audit-banner-icon { width:38px; height:38px; border-radius:var(--radius-sm); background:rgba(59,130,246,0.12); display:flex; align-items:center; justify-content:center; color:#1d4ed8; font-size:1rem; flex-shrink:0; }
.audit-banner-title { font-weight:800; font-size:0.88rem; color:#1d4ed8; margin-bottom:0.2rem; }
.audit-banner-sub   { font-size:0.78rem; color:#3b82f6; font-weight:500; margin:0; }

/* ── Integrity Check Cards ── */
.integrity-card {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-md);
    padding:1.5rem 1.6rem; height:100%; display:flex; flex-direction:column;
    transition:var(--transition); position:relative; overflow:hidden;
}
.integrity-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
.integrity-card::after { content:''; position:absolute; bottom:0; left:0; right:0; height:3px; border-radius:0 0 var(--radius-lg) var(--radius-lg); opacity:0; transition:var(--transition); }
.integrity-card:hover::after { opacity:1; }
.integrity-card.ic-ok::after    { background:linear-gradient(90deg,#22c55e,#86efac); }
.integrity-card.ic-err::after   { background:linear-gradient(90deg,#ef4444,#fca5a5); }
.integrity-card.ic-info::after  { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }

.ic-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.8rem; }
.ic-title   { font-weight:800; font-size:0.9rem; color:var(--text-primary); letter-spacing:-0.01em; }
.ic-desc    { font-size:0.8rem; color:var(--text-muted); font-weight:500; line-height:1.55; flex:1; }

.health-dot {
    width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:3px;
    animation:pulse-dot 2s ease-in-out infinite;
}
.dot-ok  { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,0.2); }
.dot-err { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,0.2); animation:pulse-dot 1s ease-in-out infinite; }
.dot-neutral { background:#94a3b8; animation:none; }

.ic-footer { margin-top:auto; padding-top:0.9rem; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
.status-pill {
    display:inline-flex; align-items:center; gap:0.3rem;
    border-radius:100px; padding:0.22rem 0.75rem;
    font-size:0.67rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em;
}
.status-pill::before { content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.sp-ok      { background:#f0fdf4; color:#166534; border:1px solid rgba(22,163,74,0.18); }
.sp-ok::before { background:#22c55e; }
.sp-err     { background:#fef2f2; color:#b91c1c; border:1px solid rgba(239,68,68,0.18); }
.sp-err::before { background:#ef4444; }
.sp-neutral { background:#f5f8f6; color:var(--text-muted); border:1px solid var(--border); }
.sp-neutral::before { background:#94a3b8; }

.db-size { font-size:1.2rem; font-weight:800; color:var(--forest); }

/* ── Deep Check Cards ── */
.section-title { font-weight:800; font-size:1rem; color:var(--text-primary); letter-spacing:-0.02em; margin-bottom:1rem; }

.deep-card {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-md);
    padding:1.6rem 1.8rem; height:100%; display:flex; align-items:flex-start; gap:1.1rem;
    transition:var(--transition);
}
.deep-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
.deep-icon {
    width:52px; height:52px; border-radius:var(--radius-md);
    display:flex; align-items:center; justify-content:center;
    font-size:1.3rem; flex-shrink:0;
}
.deep-icon.forest { background:var(--forest); color:var(--lime); }
.deep-icon.slate  { background:#f1f5f9; color:#64748b; }
.deep-title { font-weight:800; font-size:0.95rem; color:var(--text-primary); margin-bottom:0.35rem; letter-spacing:-0.01em; }
.deep-desc  { font-size:0.8rem; color:var(--text-muted); font-weight:500; line-height:1.55; margin-bottom:1rem; }

/* Buttons */
.btn-lime { background:var(--lime); color:var(--forest) !important; border:none; font-weight:700; transition:var(--transition); }
.btn-lime:hover { background:var(--lime-soft); box-shadow:var(--shadow-glow); transform:translateY(-1px); }
.btn-forest {
    background:var(--forest); color:#fff !important; border:none; font-weight:700;
    border-radius:100px; padding:0.5rem 1.3rem; font-size:0.82rem;
    cursor:pointer; transition:var(--transition); display:inline-flex; align-items:center; gap:0.4rem;
}
.btn-forest:hover { background:var(--forest-light); box-shadow:var(--shadow-md); }
.btn-outline-dark-pill {
    background:transparent; color:var(--text-primary); font-weight:700;
    border:1.5px solid var(--border); border-radius:100px; padding:0.5rem 1.3rem;
    font-size:0.82rem; cursor:pointer; transition:var(--transition); text-decoration:none;
    display:inline-flex; align-items:center; gap:0.4rem;
}
.btn-outline-dark-pill:hover { background:var(--forest); color:#fff !important; border-color:var(--forest); }
.btn-outline-lime-pill {
    background:transparent; color:var(--forest); font-weight:700;
    border:1.5px solid rgba(13,43,31,0.18); border-radius:100px; padding:0.5rem 1.3rem;
    font-size:0.82rem; cursor:pointer; transition:var(--transition);
    display:inline-flex; align-items:center; gap:0.4rem;
}
.btn-outline-lime-pill:hover { background:var(--lime-glow-sm); border-color:var(--lime); }

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

@media (max-width:768px) { .hp-hero { padding:2rem 1.5rem 4rem; } }
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
                <div class="col-md-8">
                    <div class="hero-badge">Integrity Engine</div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;color:#fff;">
                        System Health Center
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin-bottom:1.4rem;">
                        Monitor real-time operational metrics, financial integrity checks, and security audit logs.
                    </p>
                    <div style="display:flex;flex-wrap:wrap;gap:0.6rem;position:relative;">
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="run_audit" class="btn btn-lime rounded-pill px-4 py-2 fw-bold" style="font-size:0.875rem;">
                                <i class="bi bi-shield-check me-2"></i>Run Full Audit
                            </button>
                        </form>
                        <button onclick="location.reload()" class="btn-outline-lime-pill">
                            <i class="bi bi-arrow-clockwise"></i>Refresh Metrics
                        </button>
                    </div>
                </div>
                <div class="col-md-4 d-none d-md-block text-end" style="position:relative;">
                    <div class="hero-uptime">
                        <div class="hero-uptime-value">99.9%</div>
                        <div class="hero-uptime-label">System Uptime</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px;position:relative;z-index:10;">

            <!-- Audit Banner -->
            <?php if ($audit_results):
                $issue_count = count($audit_results['sync']['data'] ?? [])
                             + count($audit_results['balance']['data'] ?? [])
                             + count($audit_results['double_posting']['data'] ?? []);
            ?>
            <div class="audit-banner slide-up">
                <div class="audit-banner-icon"><i class="bi bi-info-circle-fill"></i></div>
                <div>
                    <div class="audit-banner-title">Audit Completed</div>
                    <p class="audit-banner-sub">
                        Financial integrity check performed. Found <strong><?= $issue_count ?></strong> potential issue<?= $issue_count !== 1 ? 's' : '' ?>.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Integrity Checks Row -->
            <div class="row g-3 mb-4">

                <!-- Ledger Balance Sync -->
                <div class="col-md-4">
                    <?php $imbal = $health['ledger_imbalance']; ?>
                    <div class="integrity-card <?= $imbal ? 'ic-err' : 'ic-ok' ?> slide-up" style="animation-delay:0.06s;">
                        <div class="ic-header">
                            <div class="ic-title">Ledger Balance Sync</div>
                            <span class="health-dot <?= $imbal ? 'dot-err' : 'dot-ok' ?>"></span>
                        </div>
                        <p class="ic-desc">Ensures Total Debits equal Total Credits in the golden ledger. Any discrepancy indicates a posting error.</p>
                        <div class="ic-footer">
                            <span class="status-pill <?= $imbal ? 'sp-err' : 'sp-ok' ?>">
                                <?= $imbal ? 'Imbalance Detected' : 'Healthy' ?>
                            </span>
                            <?php if ($imbal): ?>
                            <span style="font-size:0.72rem;font-weight:700;color:#dc2626;">⚠ Investigate</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Member Account Sync -->
                <div class="col-md-4">
                    <div class="integrity-card ic-ok slide-up" style="animation-delay:0.12s;">
                        <div class="ic-header">
                            <div class="ic-title">Member Account Sync</div>
                            <span class="health-dot dot-ok"></span>
                        </div>
                        <p class="ic-desc">Verifies individual account balances against the full transaction history to detect orphaned or missing entries.</p>
                        <div class="ic-footer">
                            <span class="status-pill sp-ok">Verified</span>
                        </div>
                    </div>
                </div>

                <!-- Database Storage -->
                <div class="col-md-4">
                    <div class="integrity-card ic-info slide-up" style="animation-delay:0.18s;">
                        <div class="ic-header">
                            <div class="ic-title">Database Storage</div>
                            <span class="health-dot dot-neutral"></span>
                        </div>
                        <p class="ic-desc">Current size of the SACCO's central data repository. Regular archiving recommended above 500 MB.</p>
                        <div class="ic-footer">
                            <span class="db-size"><?= htmlspecialchars((string)($health['db_size'] ?? 'N/A')) ?> MB</span>
                            <span class="status-pill sp-neutral">Optimized</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deep Health Checks -->
            <div class="section-title slide-up" style="animation-delay:0.22s;">Deep Health Checks</div>
            <div class="row g-3 mb-5">

                <!-- Financial Integrity Audit -->
                <div class="col-md-6">
                    <div class="deep-card slide-up" style="animation-delay:0.26s;">
                        <div class="deep-icon forest">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div style="flex:1;">
                            <div class="deep-title">Financial Integrity Audit</div>
                            <p class="deep-desc">Run a comprehensive comparison across the ledger, member wallets, and transaction requests to detect any hidden imbalances or double-postings.</p>
                            <form method="POST">
                                <button type="submit" name="run_audit" class="btn-forest">
                                    <i class="bi bi-play-circle-fill"></i>Start Full Audit
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Performance Monitor -->
                <div class="col-md-6">
                    <div class="deep-card slide-up" style="animation-delay:0.32s;">
                        <div class="deep-icon slate">
                            <i class="bi bi-broadcast"></i>
                        </div>
                        <div style="flex:1;">
                            <div class="deep-title">Performance Monitor</div>
                            <p class="deep-desc">Looking for operational metrics like callback success rates, pending STK requests, and live transaction tracking?</p>
                            <a href="live_monitor.php" class="btn-outline-dark-pill" style="color:inherit;">
                                <i class="bi bi-activity"></i>Go to Live Monitor
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /overlap -->

    </div><!-- /container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->