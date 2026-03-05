<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/TransactionMonitor.php';

require_admin();

$layout = LayoutManager::create('admin');
$monitor = new TransactionMonitor($conn);

$success = "";
$error   = "";

if (isset($_POST['action']) && $_POST['action'] === 'activate' && isset($_POST['contribution_id'])) {
    $cid = (int)$_POST['contribution_id'];
    try {
        require_once __DIR__ . '/../../inc/FinancialEngine.php';
        $engine = new FinancialEngine($conn);
        $res    = $conn->query("SELECT * FROM contributions WHERE contribution_id = $cid");
        $contrib = $res->fetch_assoc();
        if ($contrib && $contrib['status'] === 'pending') {
            $conn->begin_transaction();
            $conn->query("UPDATE contributions SET status = 'active' WHERE contribution_id = $cid");
            $action_map = ['savings' => 'savings_deposit','shares' => 'share_purchase','welfare' => 'welfare_contribution','registration' => 'revenue_inflow'];
            $action = $action_map[$contrib['contribution_type']] ?? 'savings_deposit';
            $engine->transact(['member_id' => $contrib['member_id'],'amount' => $contrib['amount'],'action_type' => $action,'reference' => $contrib['reference_no'] ?? ("MANUAL-".$cid),'notes' => "Manually activated by admin",'method' => 'mpesa']);
            $conn->query("UPDATE transaction_alerts SET acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = " . ($_SESSION['admin_id'] ?? 0) . " WHERE contribution_id = $cid");
            $conn->commit();
            $success = "Transaction activated successfully.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to activate: " . $e->getMessage();
    }
}

$stuck  = $monitor->getStuckPending(5);
$alerts = $monitor->getActiveAlerts();

$pageTitle = "Transaction Monitor";
$layout->header($pageTitle);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   TRANSACTION MONITOR — JAKARTA SANS + GLASSMORPHISM THEME
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

/* Hero counter badges */
.hero-counter {
    display:inline-flex;flex-direction:column;align-items:center;
    background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);
    border-radius:var(--radius-md);padding:0.85rem 1.2rem;min-width:100px;
}
.hero-counter.warn { border-color:rgba(245,158,11,0.35); }
.hero-counter.danger { border-color:rgba(239,68,68,0.3); }
.hero-counter-val { font-size:1.7rem;font-weight:800;letter-spacing:-0.04em;color:#fff;line-height:1; }
.hero-counter-val.warn   { color:#fcd34d; }
.hero-counter-val.danger { color:#fca5a5; }
.hero-counter-label { font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.4);margin-top:0.25rem; }

/* ── Tab Navigation ── */
.tab-nav-wrap {
    background:var(--surface);border-radius:var(--radius-lg);
    border:1px solid var(--border);box-shadow:var(--shadow-sm);
    padding:0.5rem;display:inline-flex;gap:0.35rem;margin-bottom:1.5rem;
}
.tab-nav-btn {
    padding:0.5rem 1.3rem;border-radius:var(--radius-md);
    font-size:0.82rem;font-weight:700;cursor:pointer;border:none;
    background:transparent;color:var(--text-muted);transition:var(--transition);
    display:flex;align-items:center;gap:0.5rem;
}
.tab-nav-btn:hover { background:var(--bg-muted);color:var(--text-primary); }
.tab-nav-btn.active { background:var(--forest);color:#fff;box-shadow:var(--shadow-sm); }
.tab-count {
    display:inline-flex;align-items:center;justify-content:center;
    min-width:20px;height:20px;border-radius:100px;padding:0 0.35rem;
    font-size:0.65rem;font-weight:800;
}
.tab-nav-btn.active .tab-count { background:rgba(255,255,255,0.2);color:#fff; }
.tab-nav-btn:not(.active) .tab-count { background:var(--bg-muted);color:var(--text-muted); }
.tab-nav-btn.active.has-warn .tab-count { background:#f59e0b;color:#fff; }
.tab-nav-btn.active.has-danger .tab-count { background:#ef4444;color:#fff; }

/* ── Tables ── */
.table-card {
    background:var(--surface);border-radius:var(--radius-lg);
    border:1px solid var(--border);box-shadow:var(--shadow-md);overflow:hidden;
}
.monitor-table { width:100%;border-collapse:separate;border-spacing:0; }
.monitor-table thead th {
    background:#f5f8f6;color:var(--text-muted);font-size:0.67rem;
    font-weight:800;text-transform:uppercase;letter-spacing:0.1em;
    padding:0.8rem 1rem;border-bottom:1px solid var(--border);white-space:nowrap;
}
.monitor-table thead th:first-child { padding-left:1.5rem; }
.monitor-table thead th:last-child  { padding-right:1.5rem;text-align:right; }
.monitor-table tbody tr { border-bottom:1px solid rgba(13,43,31,0.04);transition:var(--transition); }
.monitor-table tbody tr:last-child  { border-bottom:none; }
.monitor-table tbody tr:hover { background:#f0faf4; }
.monitor-table tbody td { padding:0.9rem 1rem;vertical-align:middle; }
.monitor-table tbody td:first-child { padding-left:1.5rem; }
.monitor-table tbody td:last-child  { padding-right:1.5rem;text-align:right; }

/* Cell typography */
.cell-date  { font-size:0.82rem;font-weight:700;color:var(--text-primary); }
.cell-sub   { font-size:0.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.07em;margin-top:0.15rem; }
.cell-name  { font-size:0.875rem;font-weight:700;color:var(--forest); }
.cell-phone { font-size:0.72rem;color:var(--text-muted);margin-top:0.1rem; }
.cell-ref   { font-family:'Courier New',monospace !important;font-size:0.75rem;font-weight:700;background:#f5f8f6;border:1px solid var(--border);border-radius:6px;padding:0.18rem 0.55rem;color:var(--text-muted); }
.cell-amount { font-size:0.9rem;font-weight:800;color:var(--forest); }
.cell-msg   { font-size:0.82rem;color:var(--text-primary);font-weight:500;max-width:380px; }

/* Type pill */
.type-pill {
    display:inline-block;border-radius:100px;
    padding:0.22rem 0.75rem;font-size:0.67rem;font-weight:800;
    text-transform:uppercase;letter-spacing:0.07em;
    background:#f0faf4;color:var(--forest);border:1px solid rgba(13,43,31,0.1);
}

/* Severity badges */
.sev-badge {
    display:inline-flex;align-items:center;gap:0.3rem;
    border-radius:100px;padding:0.22rem 0.7rem;
    font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;
}
.sev-badge::before { content:'';width:5px;height:5px;border-radius:50%;flex-shrink:0; }
.sev-warning  { background:#fffbeb;color:#b45309;border:1px solid rgba(245,158,11,0.18); }
.sev-warning::before  { background:#f59e0b; }
.sev-critical, .sev-error { background:#fef2f2;color:#b91c1c;border:1px solid rgba(239,68,68,0.18); }
.sev-critical::before, .sev-error::before { background:#ef4444; }
.sev-info     { background:#eff6ff;color:#1d4ed8;border:1px solid rgba(59,130,246,0.18); }
.sev-info::before { background:#3b82f6; }
.sev-success  { background:#f0fdf4;color:#166534;border:1px solid rgba(22,163,74,0.18); }
.sev-success::before { background:#22c55e; }

/* Action buttons */
.btn-activate {
    padding:0.35rem 1rem;border-radius:100px;font-size:0.75rem;font-weight:800;
    border:none;background:var(--lime);color:var(--forest);cursor:pointer;
    transition:var(--transition);white-space:nowrap;
}
.btn-activate:hover { background:var(--lime-soft);box-shadow:var(--shadow-glow); }
.btn-logs {
    padding:0.35rem 0.85rem;border-radius:100px;font-size:0.75rem;font-weight:700;
    border:1.5px solid var(--border);background:transparent;color:var(--text-muted);
    cursor:pointer;transition:var(--transition);white-space:nowrap;
}
.btn-logs:hover { background:var(--bg-muted);color:var(--text-primary);border-color:rgba(13,43,31,0.15); }
.btn-ack {
    padding:0.35rem 0.9rem;border-radius:100px;font-size:0.75rem;font-weight:700;
    border:1.5px solid var(--border);background:transparent;color:var(--text-muted);
    cursor:pointer;transition:var(--transition);
}
.btn-ack:hover { background:#f0faf4;color:var(--forest);border-color:rgba(13,43,31,0.15); }

/* Empty state */
.empty-cell { text-align:center;padding:4rem 2rem !important; }
.empty-icon { width:60px;height:60px;border-radius:14px;background:#f5f8f6;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#c4d4cb;margin:0 auto 0.8rem; }

/* Alert/flash */
.flash-ok  { background:#f0fdf4;border:1px solid rgba(22,163,74,0.2);border-radius:var(--radius-md);padding:0.85rem 1.1rem;font-size:0.85rem;font-weight:700;color:#166534;margin-bottom:1rem;display:flex;align-items:center;gap:0.6rem; }
.flash-err { background:#fef2f2;border:1px solid rgba(239,68,68,0.2);border-radius:var(--radius-md);padding:0.85rem 1.1rem;font-size:0.85rem;font-weight:700;color:#b91c1c;margin-bottom:1rem;display:flex;align-items:center;gap:0.6rem; }

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
                    <div class="hero-badge">Recovery Engine</div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;color:#fff;">
                        Transaction Monitor
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin:0;">
                        Detect and recover stuck payments, acknowledge system alerts, and manually activate pending transactions.
                    </p>
                </div>
                <div class="col-md-4 d-none d-md-flex align-items-center justify-content-end gap-3" style="position:relative;">
                    <div class="hero-counter <?= count($stuck) > 0 ? 'warn' : '' ?>">
                        <span class="hero-counter-val <?= count($stuck) > 0 ? 'warn' : '' ?>"><?= count($stuck) ?></span>
                        <span class="hero-counter-label">Stuck Txns</span>
                    </div>
                    <div class="hero-counter <?= count($alerts) > 0 ? 'danger' : '' ?>">
                        <span class="hero-counter-val <?= count($alerts) > 0 ? 'danger' : '' ?>"><?= count($alerts) ?></span>
                        <span class="hero-counter-label">Active Alerts</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px;position:relative;z-index:10;">

            <?php if ($success): ?>
            <div class="flash-ok"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="flash-err"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="tab-nav-wrap slide-up" style="animation-delay:0.06s;">
                <button class="tab-nav-btn active has-warn" id="tab-stuck-btn" onclick="switchTab('stuck')">
                    <i class="bi bi-hourglass-split" style="font-size:0.8rem;"></i>
                    Stuck Pending
                    <span class="tab-count"><?= count($stuck) ?></span>
                </button>
                <button class="tab-nav-btn <?= count($alerts) > 0 ? 'has-danger' : '' ?>" id="tab-alerts-btn" onclick="switchTab('alerts')">
                    <i class="bi bi-bell-fill" style="font-size:0.8rem;"></i>
                    Active Alerts
                    <span class="tab-count"><?= count($alerts) ?></span>
                </button>
            </div>

            <!-- Tab: Stuck Pending -->
            <div id="tab-stuck" class="slide-up" style="animation-delay:0.1s;">
                <div class="table-card mb-5">
                    <div class="table-responsive">
                        <table class="monitor-table">
                            <thead>
                                <tr>
                                    <th>Date / Time</th>
                                    <th>Member</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Reference</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($stuck)): ?>
                                <tr>
                                    <td colspan="6" class="empty-cell">
                                        <div class="empty-icon"><i class="bi bi-check2-all"></i></div>
                                        <div style="font-weight:800;font-size:0.95rem;color:var(--text-primary);margin-bottom:0.25rem;">All Clear</div>
                                        <div style="font-size:0.8rem;color:var(--text-muted);">No stuck transactions found.</div>
                                    </td>
                                </tr>
                                <?php else: foreach ($stuck as $s): ?>
                                <tr>
                                    <td>
                                        <div class="cell-date"><?= date('H:i:s', strtotime($s['created_at'])) ?></div>
                                        <div class="cell-sub"><?= date('M d, Y', strtotime($s['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="cell-name"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <div class="cell-phone"><?= htmlspecialchars($s['phone']) ?></div>
                                    </td>
                                    <td>
                                        <span class="type-pill"><?= ucfirst($s['contribution_type']) ?></span>
                                    </td>
                                    <td>
                                        <span class="cell-amount">KES <?= number_format((float)$s['amount'], 2) ?></span>
                                    </td>
                                    <td>
                                        <span class="cell-ref"><?= htmlspecialchars($s['reference_no']) ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:0.4rem;justify-content:flex-end;flex-wrap:wrap;">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="contribution_id" value="<?= $s['contribution_id'] ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn-activate"
                                                        onclick="return confirm('Manually activate this transaction?')">
                                                    <i class="bi bi-lightning-charge-fill me-1" style="font-size:0.7rem;"></i>Activate
                                                </button>
                                            </form>
                                            <button class="btn-logs" onclick="viewCallbackLog('<?= htmlspecialchars($s['checkout_request_id']) ?>')">
                                                <i class="bi bi-terminal me-1" style="font-size:0.7rem;"></i>Logs
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Active Alerts -->
            <div id="tab-alerts" style="display:none;" class="slide-up" style="animation-delay:0.1s;">
                <div class="table-card mb-5">
                    <div class="table-responsive">
                        <table class="monitor-table">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Message</th>
                                    <th>Date / Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alerts)): ?>
                                <tr>
                                    <td colspan="4" class="empty-cell">
                                        <div class="empty-icon"><i class="bi bi-shield-check"></i></div>
                                        <div style="font-weight:800;font-size:0.95rem;color:var(--text-primary);margin-bottom:0.25rem;">No Active Alerts</div>
                                        <div style="font-size:0.8rem;color:var(--text-muted);">System is running clean.</div>
                                    </td>
                                </tr>
                                <?php else: foreach ($alerts as $a):
                                    $sev = strtolower($a['severity'] ?? 'info');
                                    $sev_class = match($sev) {
                                        'critical' => 'sev-critical',
                                        'error'    => 'sev-error',
                                        'warning'  => 'sev-warning',
                                        'success'  => 'sev-success',
                                        default    => 'sev-info',
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <span class="sev-badge <?= $sev_class ?>"><?= strtoupper($sev) ?></span>
                                    </td>
                                    <td>
                                        <span class="cell-msg"><?= htmlspecialchars($a['message']) ?></span>
                                    </td>
                                    <td>
                                        <div class="cell-date"><?= date('H:i:s', strtotime($a['created_at'])) ?></div>
                                        <div class="cell-sub"><?= date('M d, Y', strtotime($a['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <button class="btn-ack">
                                            <i class="bi bi-check2 me-1" style="font-size:0.75rem;"></i>Acknowledge
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- /overlap -->

    </div><!-- /container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function switchTab(tab) {
        document.getElementById('tab-stuck').style.display   = tab === 'stuck'  ? 'block' : 'none';
        document.getElementById('tab-alerts').style.display  = tab === 'alerts' ? 'block' : 'none';
        document.getElementById('tab-stuck-btn').classList.toggle('active',  tab === 'stuck');
        document.getElementById('tab-alerts-btn').classList.toggle('active', tab === 'alerts');
    }

    function viewCallbackLog(checkoutId) {
        if (!checkoutId) { alert("No checkout ID associated with this transaction."); return; }
        alert("Lookup for: " + checkoutId);
    }
    </script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->