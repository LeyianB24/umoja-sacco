<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

require_once __DIR__ . '/../../inc/TransactionMonitor.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';

require_admin();
require_permission();

$layout    = LayoutManager::create('admin');
$monitorSvc = new TransactionMonitor($conn);
$engine     = new FinancialEngine($conn);
$pageTitle  = "Transaction & M-Pesa Monitor";

$success = "";
$error   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // From transaction_monitor.php: Manual Activation
        if ($_POST['action'] === 'manual_activate' && isset($_POST['contribution_id'])) {
            $cid = (int)$_POST['contribution_id'];
            try {
                $res = $conn->query("SELECT * FROM contributions WHERE contribution_id = $cid");
                $contrib = $res->fetch_assoc();
                if ($contrib && $contrib['status'] === 'pending') {
                    $conn->begin_transaction();
                    $conn->query("UPDATE contributions SET status = 'active' WHERE contribution_id = $cid");
                    $action_map = ['savings' => 'savings_deposit','shares' => 'share_purchase','welfare' => 'welfare_contribution','registration' => 'revenue_inflow'];
                    $action = $action_map[$contrib['contribution_type']] ?? 'savings_deposit';
                    $engine->transact(['member_id' => $contrib['member_id'],'amount' => $contrib['amount'],'action_type' => $action,'reference' => $contrib['reference_no'] ?? ("MANUAL-".$cid),'notes' => "Manually activated by admin via monitor",'method' => 'mpesa']);
                    $conn->query("UPDATE transaction_alerts SET acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = " . ($_SESSION['admin_id'] ?? 0) . " WHERE contribution_id = $cid");
                    $conn->commit();
                    $success = "Transaction activated successfully.";
                }
            } catch (Exception $e) {
                try { $conn->rollback(); } catch (Exception $re) {}
                $error = "Failed to activate: " . $e->getMessage();
            }
        }
        
        // Alert Acknowledgment
        if ($_POST['action'] === 'acknowledge_alert' && isset($_POST['alert_id'])) {
            $aid = (int)$_POST['alert_id'];
            $conn->query("UPDATE transaction_alerts SET acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = " . ($_SESSION['admin_id'] ?? 0) . " WHERE id = $aid");
            $success = "Alert acknowledged.";
        }
    }
}

// Fetch callback logs
$callbacks = [];
$table_check = $conn->query("SHOW TABLES LIKE 'callback_logs'");
if ($table_check && $table_check->num_rows > 0) {
    $callbacks = $conn->query("SELECT cl.*, m.full_name, m.phone
        FROM callback_logs cl
        LEFT JOIN members m ON cl.member_id = m.member_id
        ORDER BY cl.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
}

// Fetch mpesa requests
$requests = [];
$table_check = $conn->query("SHOW TABLES LIKE 'mpesa_requests'");
if ($table_check && $table_check->num_rows > 0) {
    $requests = $conn->query("SELECT r.*, m.full_name, m.phone
        FROM mpesa_requests r
        JOIN members m ON r.member_id = m.member_id
        ORDER BY r.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
}

// Summary stats
$cb_total      = count($callbacks);
$cb_success    = count(array_filter($callbacks, fn($c) => (int)$c['result_code'] === 0));
$cb_failed     = $cb_total - $cb_success;
$req_total     = count($requests);
$req_completed = count(array_filter($requests, fn($r) => $r['status'] === 'completed'));
$req_pending   = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));

// From transaction_monitor.php
$stuck  = $monitorSvc->getStuckPending(5);
$alerts = $monitorSvc->getActiveAlerts();
$stuck_total = count($stuck);
$alert_total = count($alerts);
?>
<?php $layout->header($pageTitle); ?>

<!-- ═══════════════════════════════════════════════════════════ PAGE STYLES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ── Base ───────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
body, .main-content-wrapper, .modal-content,
select, input, textarea, button, table {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Tokens ─────────────────────────────────────────────────── */
:root {
    --forest:       #1a3a2a;
    --forest-mid:   #234d38;
    --forest-light: #2e6347;
    --lime:         #a8e063;
    --lime-glow:    rgba(168,224,99,.18);
    --ink:          #111c14;
    --muted:        #6b7f72;
    --surface:      #ffffff;
    --surface-2:    #f5f8f5;
    --border:       #e3ebe5;
    --shadow-sm:    0 4px 12px rgba(26,58,42,.08);
    --shadow-md:    0 8px 28px rgba(26,58,42,.12);
    --shadow-lg:    0 16px 48px rgba(26,58,42,.16);
    --radius-sm:    10px;
    --radius-md:    16px;
    --radius-lg:    22px;
    --transition:   all .22s cubic-bezier(.4,0,.2,1);
}

/* ── Scaffold ───────────────────────────────────────────────── */
.page-canvas { background: var(--surface-2); min-height: 100vh; padding: 0 0 60px; }

/* ── Breadcrumb ─────────────────────────────────────────────── */
.breadcrumb { background: none; padding: 0; margin: 0 0 28px; font-size: .8rem; font-weight: 500; }
.breadcrumb-item a { color: var(--muted); text-decoration: none; transition: var(--transition); }
.breadcrumb-item a:hover { color: var(--forest); }
.breadcrumb-item.active { color: var(--ink); font-weight: 600; }
.breadcrumb-item + .breadcrumb-item::before { color: var(--border); }

/* ── Hero ───────────────────────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 55%, var(--forest-light) 100%);
    border-radius: var(--radius-lg); padding: 36px 40px; margin-bottom: 28px;
    position: relative; overflow: hidden; box-shadow: var(--shadow-lg);
    animation: fadeUp .35s ease both;
}
.page-header::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 60% 80% at 90% -10%, rgba(168,224,99,.22) 0%, transparent 60%),
                radial-gradient(ellipse 40% 50% at -5% 100%, rgba(168,224,99,.08) 0%, transparent 55%);
    pointer-events: none;
}
.page-header::after {
    content: ''; position: absolute; right: -60px; top: -60px;
    width: 260px; height: 260px; border-radius: 50%;
    border: 1px solid rgba(168,224,99,.1); pointer-events: none;
}
.hero-inner   { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 24px; }
.hero-chip    { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15); color: rgba(255,255,255,.8); font-size: .72rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; border-radius: 100px; padding: 5px 14px; margin-bottom: 14px; }
.hero-title   { font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 800; color: #fff; letter-spacing: -.5px; margin: 0 0 6px; }
.hero-sub     { font-size: .85rem; color: rgba(255,255,255,.65); font-weight: 500; margin: 0 0 22px; }
.hero-stats   { display: flex; gap: 12px; flex-wrap: wrap; }
.hero-stat    { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); border-radius: var(--radius-sm); padding: 10px 18px; backdrop-filter: blur(4px); }
.hero-stat-label { font-size: .65rem; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: 3px; }
.hero-stat-value { font-size: 1.05rem; font-weight: 800; color: #fff; }
.hero-stat-value.lime  { color: var(--lime); }
.hero-stat-value.rose  { color: #fca5a5; }
.hero-stat-value.amber { color: #fde68a; }

/* ── Tab switcher ───────────────────────────────────────────── */
.tab-switcher {
    display: flex; gap: 6px; align-items: center;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 6px;
    box-shadow: var(--shadow-sm); margin-bottom: 20px;
    animation: fadeUp .4s ease both; animation-delay: .06s;
    width: fit-content;
}
.tab-btn {
    display: flex; align-items: center; gap: 8px;
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    font-size: .83rem; font-weight: 700; border: none; cursor: pointer;
    border-radius: 10px; padding: 9px 20px; transition: var(--transition);
    background: transparent; color: var(--muted);
}
.tab-btn:hover { background: var(--surface-2); color: var(--ink); }
.tab-btn.active { background: var(--forest); color: #fff; box-shadow: 0 3px 10px rgba(26,58,42,.25); }
.tab-btn .tab-count {
    font-size: .65rem; font-weight: 800; border-radius: 100px; padding: 2px 8px;
    background: rgba(255,255,255,.2); color: inherit; line-height: 1.4;
}
.tab-btn:not(.active) .tab-count { background: var(--lime-glow); color: var(--forest); }

/* ── Table card ─────────────────────────────────────────────── */
.detail-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); overflow: hidden;
    box-shadow: var(--shadow-sm); transition: var(--transition);
    animation: fadeUp .4s ease both; animation-delay: .12s;
}
.detail-card:hover { box-shadow: var(--shadow-md); border-color: #d0ddd4; }

.card-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 24px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px;
}
.card-toolbar-title { font-size: .7rem; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: var(--forest); display: flex; align-items: center; gap: 8px; }
.card-toolbar-title i { width: 28px; height: 28px; border-radius: 8px; background: var(--lime-glow); color: var(--forest); display: flex; align-items: center; justify-content: center; font-size: .9rem; }
.record-count { font-size: .72rem; font-weight: 700; background: var(--lime-glow); color: var(--forest); border: 1px solid rgba(168,224,99,.35); border-radius: 100px; padding: 4px 12px; }

/* Live indicator */
.live-dot {
    display: flex; align-items: center; gap: 6px;
    font-size: .72rem; font-weight: 700; color: #1a7a3f;
}
.live-dot::before {
    content: ''; width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
    animation: livePulse 2s infinite;
}
@keyframes livePulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,.4); }
    50%       { box-shadow: 0 0 0 5px rgba(34,197,94,0); }
}

/* ── Callback table ─────────────────────────────────────────── */
.mon-table { width: 100%; border-collapse: collapse; }
.mon-table thead th { font-size: .67rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); background: var(--surface-2); padding: 13px 16px; border-bottom: 2px solid var(--border); white-space: nowrap; }
.mon-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
.mon-table tbody tr:last-child { border-bottom: none; }
.mon-table tbody tr:hover { background: #f9fcf9; }
.mon-table td { padding: 13px 16px; vertical-align: middle; }

/* Timestamp */
.ts-val  { font-size: .82rem; font-weight: 700; color: var(--ink); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }
.ts-date { font-size: .72rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

/* Type badge */
.type-chip { display: inline-flex; align-items: center; font-size: .67rem; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; background: var(--surface-2); color: var(--muted); border: 1.5px solid var(--border); border-radius: 7px; padding: 4px 10px; }

/* Member cell */
.mem-name { font-size: .86rem; font-weight: 700; color: var(--ink); }
.mem-phone { font-size: .73rem; color: var(--muted); font-weight: 500; margin-top: 2px; font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; }

/* Amount */
.amount-val { font-size: .9rem; font-weight: 800; color: var(--ink); }

/* Status pills */
.status-pill { display: inline-flex; align-items: center; gap: 5px; font-size: .68rem; font-weight: 800; letter-spacing: .3px; text-transform: uppercase; border-radius: 100px; padding: 5px 13px; white-space: nowrap; }
.status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .7; }
.sp-success   { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.sp-failed    { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.sp-completed { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.sp-pending   { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

/* Mpesa ref */
.mpesa-ref { font-size: .78rem; font-weight: 700; color: var(--forest); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; letter-spacing: .5px; }
.mpesa-ref.na { color: var(--muted); font-weight: 500; }

/* Payload cell */
.payload-cell { max-width: 220px; }
.payload-text {
    font-size: .68rem; color: var(--muted); font-family: 'DM Mono', monospace, 'Plus Jakarta Sans';
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;
    background: var(--surface-2); border: 1px solid var(--border); border-radius: 6px;
    padding: 4px 8px; display: block; cursor: pointer; transition: var(--transition);
}
.payload-text:hover { background: var(--lime-glow); border-color: rgba(168,224,99,.4); color: var(--forest); }

/* Checkout id */
.checkout-id { font-size: .72rem; font-family: 'DM Mono', monospace, 'Plus Jakarta Sans'; color: var(--muted); max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }

/* Empty state */
.empty-state { text-align: center; padding: 60px 24px; color: var(--muted); }
.empty-state i { font-size: 2.8rem; opacity: .15; display: block; margin-bottom: 14px; }
.empty-state p { font-size: .84rem; margin: 0; }

/* ── Payload Modal ──────────────────────────────────────────── */
.modal-content { border: 0 !important; border-radius: var(--radius-lg) !important; overflow: hidden; box-shadow: var(--shadow-lg); }
.modal-header  { border-bottom: 0 !important; padding: 24px 24px 0 !important; }
.modal-body    { padding: 16px 24px 24px !important; }
.payload-code {
    background: #0f1f17; border-radius: 10px; padding: 18px;
    font-family: 'DM Mono', monospace, 'Plus Jakarta Sans' !important;
    font-size: .77rem; color: #a8e063; line-height: 1.7;
    white-space: pre-wrap; word-break: break-all;
    max-height: 400px; overflow-y: auto;
}
.payload-code::-webkit-scrollbar { width: 4px; }
.payload-code::-webkit-scrollbar-thumb { background: #2e6347; border-radius: 4px; }

/* ── Animate ────────────────────────────────────────────────── */
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4 page-canvas">

        <!-- Breadcrumb -->
        <nav class="mb-1" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item active">Transaction Monitor</li>
            </ol>
        </nav>

        <!-- ═══ HERO ══════════════════════════════════════════════════════ -->
        <div class="page-header mb-4">
            <div class="hero-inner">
                <div>
                    <div class="hero-chip"><i class="bi bi-wifi"></i>M-Pesa · Live Monitor</div>
                    <h1 class="hero-title">Transaction Monitor</h1>
                    <p class="hero-sub">Real-time view of M-Pesa callbacks and initiated payment requests.</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Callbacks</div>
                            <div class="hero-stat-value"><?= $cb_total ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Successful</div>
                            <div class="hero-stat-value lime"><?= $cb_success ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Requests</div>
                            <div class="hero-stat-value"><?= $req_total ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Stuck Txns</div>
                            <div class="hero-stat-value <?= $stuck_total > 0 ? 'rose' : '' ?>"><?= $stuck_total ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Alerts</div>
                            <div class="hero-stat-value <?= $alert_total > 0 ? 'amber' : '' ?>"><?= $alert_total ?></div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;padding-top:4px">
                    <div class="d-flex align-items-center gap-2">
                        <button id="toggleRefreshBtn" onclick="toggleRefresh()" style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;border-radius:6px;padding:3px 8px;font-size:0.7rem;cursor:pointer;font-weight:600;transition:all 0.2s;"><i class="bi bi-pause-fill"></i> Pause</button>
                        <div class="live-dot" id="liveIndicator" style="color:#a8e063;font-weight:800;letter-spacing:1px;font-size:0.75rem;">LIVE</div>
                    </div>
                    <div id="refreshTimer" style="font-size:.75rem;color:rgba(255,255,255,.6);font-weight:600">Auto-refreshes in 15s</div>
                    <div style="width:120px;height:4px;background:rgba(255,255,255,0.1);border-radius:4px;overflow:hidden;margin-top:2px">
                        <div id="refreshBar" style="width:100%;height:100%;background:#a8e063;transition:width 1s linear"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ TAB SWITCHER ═════════════════════════════════════════════ -->
        <div class="tab-switcher">
            <button class="tab-btn active" id="tabCallbacks" onclick="switchTab('callbacks')">
                <i class="bi bi-arrow-down-circle-fill"></i> M-Pesa Feed
                <span class="tab-count"><?= $cb_total ?></span>
            </button>
            <button class="tab-btn" id="tabRequests" onclick="switchTab('requests')">
                <i class="bi bi-arrow-up-circle-fill"></i> Requests
                <span class="tab-count"><?= $req_total ?></span>
            </button>
            <button class="tab-btn" id="tabStuck" onclick="switchTab('stuck')">
                <i class="bi bi-hourglass-split"></i> Stuck
                <span class="tab-count"><?= $stuck_total ?></span>
            </button>
            <button class="tab-btn" id="tabAlerts" onclick="switchTab('alerts')">
                <i class="bi bi-bell-fill"></i> Alerts
                <span class="tab-count"><?= $alert_total ?></span>
            </button>
        </div>

        <!-- ═══ CALLBACKS TABLE ═══════════════════════════════════════════ -->
        <div id="sectionCallbacks" class="detail-card">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-arrow-down-circle-fill d-flex"></i>
                    Incoming Payment Callbacks
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="live-dot">Live Feed</div>
                    <?php if ($cb_total): ?>
                    <span class="record-count"><?= $cb_total ?> entries</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="mon-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px">Timestamp</th>
                            <th>Type</th>
                            <th>Member / Phone</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>M-Pesa Ref</th>
                            <th>Payload</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($callbacks)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-arrow-down-circle"></i>
                                <p>No callback logs found.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($callbacks as $cl):
                        $is_success = (int)$cl['result_code'] === 0;
                    ?>
                        <tr>
                            <td style="padding-left:20px">
                                <div class="ts-val"><?= date('H:i:s', strtotime($cl['created_at'])) ?></div>
                                <div class="ts-date"><?= date('d M Y', strtotime($cl['created_at'])) ?></div>
                            </td>
                            <td><span class="type-chip"><?= htmlspecialchars($cl['callback_type']) ?></span></td>
                            <td>
                                <div class="mem-name"><?= htmlspecialchars($cl['full_name'] ?? 'Inbound') ?></div>
                                <div class="mem-phone"><?= htmlspecialchars($cl['phone'] ?? 'Unknown') ?></div>
                            </td>
                            <td><div class="amount-val">KES <?= number_format((float)($cl['amount'] ?? 0)) ?></div></td>
                            <td>
                                <span class="status-pill <?= $is_success ? 'sp-success' : 'sp-failed' ?>">
                                    <?= $is_success ? 'Success' : 'Failed (' . $cl['result_code'] . ')' ?>
                                </span>
                            </td>
                            <td>
                                <span class="mpesa-ref <?= $cl['mpesa_receipt_number'] ? '' : 'na' ?>">
                                    <?= htmlspecialchars($cl['mpesa_receipt_number'] ?: 'N/A') ?>
                                </span>
                            </td>
                            <td class="payload-cell">
                                <span class="payload-text"
                                      onclick="showPayload(<?= htmlspecialchars(json_encode($cl['raw_payload']), ENT_QUOTES) ?>)"
                                      title="Click to view full payload">
                                    <?= htmlspecialchars($cl['raw_payload']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ REQUESTS TABLE ════════════════════════════════════════════ -->
        <div id="sectionRequests" class="detail-card d-none">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-arrow-up-circle-fill d-flex"></i>
                    Initiated Payment Requests
                </div>
                <?php if ($req_total): ?>
                <span class="record-count"><?= $req_total ?> entries</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="mon-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px">Timestamp</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Checkout ID</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-arrow-up-circle"></i>
                                <p>No payment requests found.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr>
                            <td style="padding-left:20px">
                                <div class="ts-val"><?= date('H:i:s', strtotime($r['created_at'])) ?></div>
                                <div class="ts-date"><?= date('d M Y', strtotime($r['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="mem-name"><?= htmlspecialchars($r['full_name']) ?></div>
                                <div class="mem-phone"><?= htmlspecialchars($r['phone']) ?></div>
                            </td>
                            <td><div class="amount-val">KES <?= number_format((float)($r['amount'] ?? 0)) ?></div></td>
                            <td><span class="mpesa-ref"><?= htmlspecialchars($r['reference_no']) ?></span></td>
                            <td><span class="checkout-id" title="<?= htmlspecialchars($r['checkout_request_id']) ?>"><?= htmlspecialchars($r['checkout_request_id']) ?></span></td>
                            <td>
                                <?php
                                $sp = match($r['status']) {
                                    'completed' => ['sp-completed', 'Completed'],
                                    'pending'   => ['sp-pending',   'Pending'],
                                    default     => ['sp-failed',    'Failed'],
                                };
                                ?>
                                <span class="status-pill <?= $sp[0] ?>"><?= $sp[1] ?></span>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <button class="btn btn-sm" 
                                            style="background:var(--lime-glow);color:var(--forest);font-weight:700;border-radius:8px;padding:4px 10px;font-size:0.75rem;border:1px solid rgba(168,224,99,0.3)"
                                            onclick="activateRequest('<?= $r['checkout_request_id'] ?>', this)">
                                        <i class="bi bi-lightning-fill me-1"></i> Activate
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.7rem;font-weight:600">No Action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ STUCK TABLE ═══════════════════════════════════════════════ -->
        <div id="sectionStuck" class="detail-card d-none">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-hourglass-split d-flex"></i>
                    Stuck Pending Contributions
                </div>
                <?php if ($stuck_total): ?>
                    <span class="record-count"><?= $stuck_total ?> entries</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="mon-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px">Timestamp</th>
                            <th>Member</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($stuck)): ?>
                        <tr><td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-check2-all"></i>
                                <p>No stuck transactions found.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($stuck as $s): ?>
                        <tr>
                            <td style="padding-left:20px">
                                <div class="ts-val"><?= date('H:i:s', strtotime($s['created_at'])) ?></div>
                                <div class="ts-date"><?= date('d M Y', strtotime($s['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="mem-name"><?= htmlspecialchars($s['full_name']) ?></div>
                                <div class="mem-phone"><?= htmlspecialchars($s['phone']) ?></div>
                            </td>
                            <td><span class="type-chip"><?= ucfirst($s['contribution_type']) ?></span></td>
                            <td><div class="amount-val">KES <?= number_format((float)$s['amount']) ?></div></td>
                            <td><span class="mpesa-ref"><?= htmlspecialchars($s['reference_no']) ?></span></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Manually activate this transaction?')">
                                    <input type="hidden" name="action" value="manual_activate">
                                    <input type="hidden" name="contribution_id" value="<?= $s['contribution_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-lime rounded-pill px-3 fw-bold" style="font-size:0.7rem">
                                        <i class="bi bi-lightning-charge-fill me-1"></i> Activate
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ ALERTS TABLE ══════════════════════════════════════════════ -->
        <div id="sectionAlerts" class="detail-card d-none">
            <div class="card-toolbar">
                <div class="card-toolbar-title">
                    <i class="bi bi-bell-fill d-flex"></i>
                    System Alerts
                </div>
                <?php if ($alert_total): ?>
                    <span class="record-count"><?= $alert_total ?> alerts</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="mon-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px">Severity</th>
                            <th>Message</th>
                            <th>Date / Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($alerts)): ?>
                        <tr><td colspan="4">
                            <div class="empty-state">
                                <i class="bi bi-shield-check"></i>
                                <p>All systems normal. No active alerts.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($alerts as $a):
                        $sev = strtolower($a['severity'] ?? 'info');
                        $pill_class = match($sev) {
                            'critical' => 'sp-failed',
                            'warning'  => 'sp-pending',
                            default    => 'sp-completed'
                        };
                    ?>
                        <tr>
                            <td style="padding-left:20px">
                                <span class="status-pill <?= $pill_class ?>"><?= strtoupper($sev) ?></span>
                            </td>
                            <td><div class="mem-name" style="font-weight:500; font-size:0.8rem"><?= htmlspecialchars($a['message']) ?></div></td>
                            <td>
                                <div class="ts-val"><?= date('H:i:s', strtotime($a['created_at'])) ?></div>
                                <div class="ts-date"><?= date('d M Y', strtotime($a['created_at'])) ?></div>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="acknowledge_alert">
                                    <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="font-size:0.7rem">
                                        Ack
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ PAYLOAD MODAL ════════════════════════════════════════════ -->
        <div class="modal fade" id="payloadModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:560px">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h6 class="fw-800 mb-1" style="color:var(--ink);font-weight:800">Raw Callback Payload</h6>
                            <p class="text-muted mb-0" style="font-size:.78rem">Full JSON received from M-Pesa gateway.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <pre class="payload-code" id="payloadContent"></pre>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
function switchTab(tab) {
    const cbSection  = document.getElementById('sectionCallbacks');
    const reqSection = document.getElementById('sectionRequests');
    const stuckSection = document.getElementById('sectionStuck');
    const alertsSection = document.getElementById('sectionAlerts');
    
    const tabCb      = document.getElementById('tabCallbacks');
    const tabReq     = document.getElementById('tabRequests');
    const tabStuck   = document.getElementById('tabStuck');
    const tabAlerts  = document.getElementById('tabAlerts');

    [cbSection, reqSection, stuckSection, alertsSection].forEach(s => s.classList.add('d-none'));
    [tabCb, tabReq, tabStuck, tabAlerts].forEach(t => t.classList.remove('active'));

    if (tab === 'callbacks') {
        cbSection.classList.remove('d-none');
        tabCb.classList.add('active');
    } else if (tab === 'requests') {
        reqSection.classList.remove('d-none');
        tabReq.classList.add('active');
    } else if (tab === 'stuck') {
        stuckSection.classList.remove('d-none');
        tabStuck.classList.add('active');
    } else if (tab === 'alerts') {
        alertsSection.classList.remove('d-none');
        tabAlerts.classList.add('active');
    }
    
    // Save state so page reloads don't reset the tab
    sessionStorage.setItem('monitorActiveTab', tab);
}

document.addEventListener('DOMContentLoaded', () => {
    const savedTab = sessionStorage.getItem('monitorActiveTab');
    if (savedTab) {
        switchTab(savedTab);
    }
});

function showPayload(raw) {
    const el = document.getElementById('payloadContent');
    try {
        el.textContent = JSON.stringify(JSON.parse(raw), null, 2);
    } catch {
        el.textContent = raw;
    }
    new bootstrap.Modal(document.getElementById('payloadModal')).show();
}

async function activateRequest(checkoutID, btn) {
    if (!confirm('Simulate receiving M-Pesa payment for this request?')) return;
    
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
    
    try {
        const response = await fetch('../api/simulate_mpesa_callback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ checkout_request_id: checkoutID })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Success feel: Confetti or just a nice alert
            alert('Success! ' + result.message);
            location.reload();
        } else {
            alert('Error: ' + result.message);
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    } catch (err) {
        alert('Simulation failed: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

// Auto-refresh logic
let isPaused = false;
let countdown = 15;
const MAX_TIME = 15;
let hoverPause = false;

// Pause auto-refresh when hovering over data tables to allow reading without interruptions
document.querySelectorAll('.detail-card').forEach(card => {
    card.addEventListener('mouseenter', () => hoverPause = true);
    card.addEventListener('mouseleave', () => hoverPause = false);
});

function toggleRefresh() {
    isPaused = !isPaused;
    const btn = document.getElementById('toggleRefreshBtn');
    const ind = document.getElementById('liveIndicator');
    const timerEl = document.getElementById('refreshTimer');
    
    if (isPaused) {
        btn.innerHTML = '<i class="bi bi-play-fill"></i> Resume';
        ind.style.color = '#fca5a5';
        ind.innerText = 'PAUSED';
        if (timerEl) timerEl.innerText = 'Updates paused';
    } else {
        btn.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
        ind.style.color = '#a8e063';
        ind.innerText = 'LIVE';
        countdown = MAX_TIME; // reset countdown when resuming
    }
}

setInterval(() => {
    if (isPaused) return;

    const timerEl = document.getElementById('refreshTimer');
    const barEl = document.getElementById('refreshBar');
    const modal = document.getElementById('payloadModal');
    
    // Pause refresh if a modal is open or user is hovering a table
    if ((modal && modal.classList.contains('show')) || hoverPause) {
        if (timerEl) timerEl.innerText = 'Refresh paused (Reading...)';
        return;
    }
    
    countdown--;
    
    if (countdown <= 0) {
        if (timerEl) timerEl.innerText = 'Refreshing...';
        if (barEl) barEl.style.width = '0%';
        location.reload();
    } else {
        if (timerEl) timerEl.innerText = `Auto-refreshes in ${countdown}s`;
        if (barEl) barEl.style.width = `${(countdown / MAX_TIME) * 100}%`;
    }
}, 1000);
</script>
</body>
</html>