<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SystemHealthHelper.php';
require_once __DIR__ . '/../../inc/AuditHelper.php';

require_admin();
require_permission();

$layout = LayoutManager::create('admin');
$health = getSystemHealth($conn);

$recent_logs_q = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
$recent_logs = $recent_logs_q->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Live Operations Monitor";
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   LIVE OPERATIONS MONITOR — JAKARTA SANS + GLASSMORPHISM
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

.live-pill {
    display:inline-flex;align-items:center;gap:0.5rem;
    background:rgba(181,244,60,0.12);border:1px solid rgba(181,244,60,0.25);
    color:var(--lime-soft);border-radius:100px;padding:0.28rem 0.85rem;
    font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;
    margin-bottom:0.9rem;position:relative;
}
.live-dot {
    width:7px;height:7px;border-radius:50%;background:var(--lime);
    animation:pulse-dot 1.4s ease-in-out infinite;flex-shrink:0;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.5)} }

/* ── KPI Cards ── */
.stat-card {
    background:var(--surface);border-radius:var(--radius-lg);
    border:1px solid var(--border);box-shadow:var(--shadow-md);
    padding:1.5rem 1.6rem;position:relative;overflow:hidden;
    transition:var(--transition);height:100%;
}
.stat-card:hover { transform:translateY(-3px);box-shadow:var(--shadow-lg); }
.stat-card::after { content:'';position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 var(--radius-lg) var(--radius-lg);opacity:0;transition:var(--transition); }
.stat-card:hover::after { opacity:1; }
.stat-card.sc-success::after { background:linear-gradient(90deg,#22c55e,#86efac); }
.stat-card.sc-warn::after   { background:linear-gradient(90deg,#f59e0b,#fcd34d); }
.stat-card.sc-danger::after { background:linear-gradient(90deg,#ef4444,#fca5a5); }
.stat-card.sc-dark::after   { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }
.stat-card.sc-dark { background:linear-gradient(135deg,var(--forest) 0%,var(--forest-mid) 100%);border:none; }

.stat-icon { width:44px;height:44px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:1rem;flex-shrink:0; }
.stat-label { font-size:0.67rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.35rem; }
.stat-value { font-size:1.75rem;font-weight:800;letter-spacing:-0.04em;line-height:1;margin-bottom:0.5rem; }
.stat-sub   { font-size:0.73rem;font-weight:700;display:flex;align-items:center;gap:0.3rem; }

/* Progress bar */
.stat-progress { height:5px;border-radius:100px;background:rgba(13,43,31,0.08);overflow:hidden;margin-top:0.85rem; }
.stat-progress-bar { height:100%;border-radius:100px;transition:width 0.8s ease; }

/* ── Feed Header ── */
.feed-header {
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:1rem;padding:0 0.25rem;
}
.feed-title { font-weight:800;font-size:1rem;color:var(--text-primary);letter-spacing:-0.02em; }
.feed-sub   { font-size:0.75rem;color:var(--text-muted);font-weight:600;margin-top:0.15rem; }
.feed-count {
    background:var(--bg-muted);border:1px solid var(--border);border-radius:100px;
    padding:0.25rem 0.85rem;font-size:0.72rem;font-weight:800;color:var(--text-muted);
}

/* ── Log Table ── */
.log-table-card {
    background:var(--surface);border-radius:var(--radius-lg);
    border:1px solid var(--border);box-shadow:var(--shadow-md);overflow:hidden;
}
.log-table { width:100%;border-collapse:separate;border-spacing:0; }
.log-table thead th {
    background:#f5f8f6;color:var(--text-muted);font-size:0.67rem;
    font-weight:800;text-transform:uppercase;letter-spacing:0.1em;
    padding:0.8rem 1rem;border-bottom:1px solid var(--border);white-space:nowrap;
}
.log-table thead th:first-child { padding-left:1.5rem; }
.log-table thead th:last-child  { padding-right:1.5rem;text-align:right; }
.log-table tbody tr { border-bottom:1px solid rgba(13,43,31,0.04);transition:var(--transition); }
.log-table tbody tr:last-child  { border-bottom:none; }
.log-table tbody tr:hover { background:#f0faf4; }
.log-table tbody td { padding:0.85rem 1rem;vertical-align:middle; }
.log-table tbody td:first-child { padding-left:1.5rem; }
.log-table tbody td:last-child  { padding-right:1.5rem;text-align:right; }

.log-time { font-size:0.82rem;font-weight:700;color:var(--text-primary);line-height:1.2; }
.log-date { font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-top:0.15rem; }
.log-action { font-size:0.875rem;font-weight:700;color:var(--text-primary); }
.log-type   { font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-top:0.15rem; }
.log-detail { font-size:0.8rem;color:var(--text-muted);font-weight:500;max-width:320px; }
.log-ip     { font-family:'Courier New',monospace !important;font-size:0.75rem;color:var(--text-muted);font-weight:600; }

/* Severity badges */
.sev-badge {
    display:inline-flex;align-items:center;gap:0.3rem;
    border-radius:100px;padding:0.22rem 0.7rem;
    font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;
    white-space:nowrap;
}
.sev-badge::before { content:'';width:5px;height:5px;border-radius:50%;flex-shrink:0; }
.sev-info     { background:#eff6ff;color:#1d4ed8;border:1px solid rgba(59,130,246,0.18); }
.sev-info::before { background:#3b82f6; }
.sev-warning  { background:#fffbeb;color:#b45309;border:1px solid rgba(245,158,11,0.18); }
.sev-warning::before { background:#f59e0b; }
.sev-error, .sev-critical {
    background:#fef2f2;color:#b91c1c;border:1px solid rgba(239,68,68,0.18);
}
.sev-error::before, .sev-critical::before { background:#ef4444; }
.sev-success  { background:#f0fdf4;color:#166534;border:1px solid rgba(22,163,74,0.18); }
.sev-success::before { background:#22c55e; }

/* Empty state */
.empty-state-row td {
    text-align:center;padding:5rem 2rem;
}
.empty-icon { width:64px;height:64px;border-radius:16px;background:#f5f8f6;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#c4d4cb;margin:0 auto 0.9rem; }

/* Buttons */
.btn-lime  { background:var(--lime);color:var(--forest) !important;border:none;font-weight:700;transition:var(--transition); }
.btn-lime:hover  { background:var(--lime-soft);box-shadow:var(--shadow-glow);transform:translateY(-1px); }

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

@media (max-width:768px) {
    .hp-hero { padding:2rem 1.5rem 4rem; }
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
                <div class="col-md-8">
                    <div class="live-pill">
                        <span class="live-dot"></span>
                        Live System Feed
                    </div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;color:#fff;">
                        Operations Monitor
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin:0;">
                        Tracking real-time payment callbacks, notification delivery, and system activity logs.
                    </p>
                </div>
                <div class="col-md-4 text-end d-none d-md-block" style="position:relative;">
                    <button onclick="location.reload()" class="btn btn-lime rounded-pill px-4 py-2 fw-bold" style="font-size:0.875rem;">
                        <i class="bi bi-arrow-clockwise me-2"></i>Refresh Feed
                    </button>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px;position:relative;z-index:10;">

            <!-- KPI Row -->
            <div class="row g-3 mb-4">

                <!-- Callback Success -->
                <div class="col-md-3">
                    <?php
                    $cb = (float)$health['callback_success_rate'];
                    $cb_color = $cb >= 90 ? '#22c55e' : ($cb >= 70 ? '#f59e0b' : '#ef4444');
                    $cb_sub_color = $cb >= 90 ? '#166534' : ($cb >= 70 ? '#b45309' : '#b91c1c');
                    ?>
                    <div class="stat-card sc-success slide-up" style="animation-delay:0.04s;">
                        <div class="stat-icon" style="background:#f0fdf4;color:#166534;">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="stat-label" style="color:var(--text-muted);">Callback Success</div>
                        <div class="stat-value" style="color:var(--forest);"><?= $cb ?>%</div>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width:<?= $cb ?>%;background:<?= $cb_color ?>;"></div>
                        </div>
                        <div class="stat-sub mt-2" style="color:<?= $cb_sub_color ?>;">
                            <i class="bi bi-activity"></i>
                            <?= $cb >= 90 ? 'Healthy rate' : ($cb >= 70 ? 'Needs attention' : 'Critical — investigate') ?>
                        </div>
                    </div>
                </div>

                <!-- Pending STK -->
                <div class="col-md-3">
                    <?php
                    $pend = (int)$health['pending_transactions'];
                    $pend_warn = $pend > 5;
                    ?>
                    <div class="stat-card <?= $pend_warn ? 'sc-warn' : 'sc-success' ?> slide-up" style="animation-delay:0.1s;">
                        <div class="stat-icon" style="background:<?= $pend_warn ? '#fffbeb' : '#f0fdf4' ?>;color:<?= $pend_warn ? '#b45309' : '#166534' ?>;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="stat-label" style="color:var(--text-muted);">Pending STK</div>
                        <div class="stat-value" style="color:<?= $pend_warn ? '#b45309' : '#166534' ?>;"><?= $pend ?></div>
                        <div class="stat-sub mt-2" style="color:<?= $pend_warn ? '#b45309' : '#166534' ?>;">
                            <i class="bi bi-clock"></i>
                            <?= $pend_warn ? 'Stuck &gt; 5 mins' : 'All clear' ?>
                        </div>
                    </div>
                </div>

                <!-- Failed Comms -->
                <div class="col-md-3">
                    <?php $fail = (int)$health['failed_notifications']; ?>
                    <div class="stat-card <?= $fail > 0 ? 'sc-danger' : 'sc-success' ?> slide-up" style="animation-delay:0.16s;">
                        <div class="stat-icon" style="background:<?= $fail > 0 ? '#fef2f2' : '#f0fdf4' ?>;color:<?= $fail > 0 ? '#dc2626' : '#166534' ?>;">
                            <i class="bi bi-envelope-exclamation<?= $fail > 0 ? '' : '-fill' ?>"></i>
                        </div>
                        <div class="stat-label" style="color:var(--text-muted);">Failed Comms</div>
                        <div class="stat-value" style="color:<?= $fail > 0 ? '#dc2626' : '#166534' ?>;"><?= $fail ?></div>
                        <div class="stat-sub mt-2" style="color:<?= $fail > 0 ? '#dc2626' : '#166534' ?>;">
                            <i class="bi bi-envelope<?= $fail > 0 ? '-x' : '-check' ?>"></i>
                            <?= $fail > 0 ? 'Delivery errors today' : 'All delivered' ?>
                        </div>
                    </div>
                </div>

                <!-- Daily Volume -->
                <div class="col-md-3">
                    <div class="stat-card sc-dark slide-up" style="animation-delay:0.22s;">
                        <div class="stat-icon" style="background:rgba(255,255,255,0.12);color:var(--lime);">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <div class="stat-label" style="color:rgba(255,255,255,0.45);">Daily Volume</div>
                        <div class="stat-value" style="color:#fff;font-size:1.4rem;">KES <?= number_format($health['daily_volume'], 0) ?></div>
                        <div class="stat-sub mt-2" style="color:rgba(255,255,255,0.45);">
                            <i class="bi bi-check-all" style="color:var(--lime);"></i>
                            Successful processed
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Feed -->
            <div class="feed-header slide-up" style="animation-delay:0.28s;">
                <div>
                    <div class="feed-title">Operation Audit Feed</div>
                    <div class="feed-sub">Real-time system activity — auto-sorted by latest</div>
                </div>
                <span class="feed-count"><?= count($recent_logs) ?> events</span>
            </div>

            <div class="log-table-card mb-5 slide-up" style="animation-delay:0.32s;">
                <div class="table-responsive">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Severity</th>
                                <th>Details</th>
                                <th>Origin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_logs)): ?>
                            <tr class="empty-state-row">
                                <td colspan="5">
                                    <div class="empty-icon"><i class="bi bi-broadcast"></i></div>
                                    <div style="font-weight:800;font-size:0.95rem;color:var(--text-primary);margin-bottom:0.25rem;">No Events Streaming</div>
                                    <div style="font-size:0.8rem;color:var(--text-muted);">No operational logs recorded yet.</div>
                                </td>
                            </tr>
                            <?php else: foreach ($recent_logs as $log):
                                $sev = strtolower((string)($log['severity'] ?? 'info'));
                                $sev_class = match($sev) {
                                    'warning'  => 'sev-warning',
                                    'error'    => 'sev-error',
                                    'critical' => 'sev-critical',
                                    'success'  => 'sev-success',
                                    default    => 'sev-info',
                                };
                                $sev_label = strtoupper($sev ?: 'INFO');
                            ?>
                            <tr>
                                <td>
                                    <div class="log-time"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                    <div class="log-date"><?= date('M d', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="log-action"><?= htmlspecialchars((string)($log['action'] ?? '')) ?></div>
                                    <div class="log-type"><?= htmlspecialchars((string)($log['user_type'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <span class="sev-badge <?= $sev_class ?>"><?= $sev_label ?></span>
                                </td>
                                <td>
                                    <span class="log-detail"><?= htmlspecialchars((string)($log['details'] ?? '')) ?></span>
                                </td>
                                <td>
                                    <span class="log-ip"><?= htmlspecialchars((string)($log['ip_address'] ?? '')) ?></span>
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