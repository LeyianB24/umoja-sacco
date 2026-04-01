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
                    // Double Entry Prevention: Check if this reference is already in the ledger
                    $ref = $contrib['reference_no'] ?? ("MANUAL-".$cid);
                    $check = $conn->prepare("SELECT transaction_id FROM ledger_transactions WHERE reference_no = ? LIMIT 1");
                    $check->bind_param("s", $ref);
                    $check->execute();
                    if ($check->get_result()->num_rows > 0) {
                        throw new Exception("Transaction already processed in ledger (found matching reference).");
                    }
                    $check->close();

                    $conn->begin_transaction();
                    $conn->query("UPDATE contributions SET status = 'active' WHERE contribution_id = $cid");
                    $action_map = ['savings' => 'savings_deposit','shares' => 'share_purchase','welfare' => 'welfare_contribution','registration' => 'revenue_inflow'];
                    $action = $action_map[$contrib['contribution_type']] ?? 'savings_deposit';
                    $engine->transact(['member_id' => $contrib['member_id'],'amount' => $contrib['amount'],'action_type' => $action,'reference' => $contrib['reference_no'] ?? ("MANUAL-".$cid),'notes' => "Manually activated by admin via monitor",'method' => 'mpesa']);
                    $conn->query("UPDATE transaction_alerts SET acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = " . ($_SESSION['admin_id'] ?? 0) . " WHERE contribution_id = $cid");
                    $conn->commit();
                    $success = "Transaction activated successfully.";
                } else {
                    $error = "Transaction is already active or not found.";
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
/* ── Base & Global Tokens ───────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
:root {
    --forest:       #0d1f14;
    --forest-mid:   #1a3a2a;
    --forest-light: #2e6347;
    --lime:         #a8e063;
    --lime-glow:    rgba(168, 224, 99, 0.25);
    --ink:          #0a0f0c;
    --muted:        #8da394;
    --surface:      rgba(255, 255, 255, 0.85);
    --surface-2:    #f0f4f1;
    --border:       rgba(227, 235, 229, 0.6);
    --glass:        rgba(255, 255, 255, 0.7);
    --glass-border: rgba(255, 255, 255, 0.4);
    
    --shadow-sm:    0 4px 10px rgba(10, 15, 12, 0.04);
    --shadow-md:    0 12px 32px rgba(10, 15, 12, 0.08);
    --shadow-lg:    0 24px 64px rgba(10, 15, 12, 0.12);
    
    --radius-sm:    12px;
    --radius-md:    20px;
    --radius-lg:    28px;
    --transition:   all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body { 
    background-color: var(--surface-2); 
    color: var(--ink);
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ── Mesh Gradient Background ───────────────────────────────── */
.page-canvas { 
    background: radial-gradient(at 0% 0%, rgba(168,224,99,0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(46,99,71,0.05) 0px, transparent 50%);
    min-height: 100vh; padding-bottom: 80px; 
}

/* ── Hero & Glassmorphism ───────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
    border-radius: var(--radius-lg); padding: 48px 40px; margin-bottom: 32px;
    position: relative; overflow: hidden; box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255,255,255,0.08);
    animation: slideDownFade 0.6s ease-out;
}

/* Animated Mesh Mesh effect */
.page-header::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(168,224,99,0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(46,99,71,0.2) 0%, transparent 40%);
    filter: blur(60px); opacity: 0.8; animation: meshMove 12s infinite alternate;
}

@keyframes meshMove {
    0% { transform: scale(1) translate(0, 0); }
    100% { transform: scale(1.1) translate(20px, 10px); }
}

.hero-inner { position: relative; z-index: 2; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 32px; }

.hero-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px); color: var(--lime);
    font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
    padding: 6px 16px; border-radius: 100px; margin-bottom: 18px;
}

.hero-title { font-size: clamp(1.8rem, 4vw, 2.8rem); font-weight: 800; color: #fff; letter-spacing: -1.5px; margin-bottom: 8px; }
.hero-sub   { font-size: 0.95rem; color: rgba(255,255,255,0.6); max-width: 500px; line-height: 1.6; }

.hero-stats {
    display: flex; gap: 16px; flex-wrap: wrap; margin-top: 32px;
}
.hero-stat {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(12px); padding: 14px 22px; border-radius: var(--radius-md);
    min-width: 140px; transition: var(--transition);
}
.hero-stat:hover { background: rgba(255,255,255,0.08); transform: translateY(-4px); border-color: rgba(168,224,99,0.3); }
.hero-stat-label { font-size: 0.65rem; color: rgba(255,255,255,0.5); text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 4px; }
.hero-stat-value { font-size: 1.5rem; font-weight: 800; color: #fff; }
.hero-stat-value.lime { color: var(--lime); text-shadow: 0 0 15px rgba(168,224,99,0.4); }

.hero-controls {
    display: flex; flex-direction: column; align-items: flex-end; gap: 12px;
}

/* ── Live Pulse ─────────────────────────────────────────────── */
.live-pulse {
    position: relative; width: 10px; height: 10px; background: var(--lime);
    border-radius: 50%; box-shadow: 0 0 10px var(--lime);
}
.live-pulse::after {
    content: ''; position: absolute; inset: -4px; border: 2px solid var(--lime);
    border-radius: 50%; opacity: 0; animation: pulseRing 1.5s infinite;
}
@keyframes pulseRing {
    0% { transform: scale(0.5); opacity: 0; }
    50% { opacity: 0.5; }
    100% { transform: scale(1.5); opacity: 0; }
}

/* ── Tab Switcher ───────────────────────────────────────────── */
.tab-switcher {
    display: flex; gap: 12px; background: var(--glass);
    backdrop-filter: blur(16px); border: 1px solid var(--glass-border);
    padding: 8px; border-radius: var(--radius-md);
    box-shadow: var(--shadow-md); margin-bottom: 32px;
    overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none;
}
.tab-switcher::-webkit-scrollbar { display: none; }

.tab-btn {
    display: flex; align-items: center; gap: 10px; padding: 12px 24px;
    border-radius: 14px; border: none; background: transparent;
    font-weight: 700; font-size: 0.88rem; color: var(--muted);
    transition: var(--transition); white-space: nowrap; cursor: pointer;
    scroll-snap-align: start;
}
.tab-btn i { font-size: 1.1rem; }
.tab-btn:hover { background: rgba(0,0,0,0.03); color: var(--ink); }
.tab-btn.active {
    background: var(--forest); color: #fff;
    box-shadow: 0 8px 16px rgba(13,31,20,0.2);
}
.tab-count {
    background: rgba(168,224,99,0.15); color: var(--forest-light);
    font-size: 0.72rem; padding: 2px 8px; border-radius: 6px; font-weight: 800;
}
.tab-btn.active .tab-count { background: rgba(255,255,255,0.2); color: #fff; }

/* ── Content Cards ──────────────────────────────────────────── */
.detail-card {
    background: var(--surface); backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border); border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md); overflow: hidden;
    animation: fadeInUp 0.6s ease both;
}

.card-toolbar {
    padding: 24px 32px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;
}

/* ── Search Bar ─────────────────────────────────────────────── */
.search-box {
    position: relative; min-width: 280px;
}
.search-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted); }
.search-input {
    width: 100%; background: var(--surface-2); border: 1px solid var(--border);
    border-radius: 12px; padding: 10px 16px 10px 42px; font-size: 0.85rem;
    font-weight: 500; transition: var(--transition);
}
.search-input:focus { outline: none; border-color: var(--lime); box-shadow: 0 0 0 4px var(--lime-glow); }

/* ── Table Styling ──────────────────────────────────────────── */
.mon-table th {
    background: transparent; color: var(--muted); font-size: 0.72rem;
    text-transform: uppercase; letter-spacing: 1px; font-weight: 800;
    padding: 18px 24px; border-bottom: 1px solid var(--border);
}
.mon-table td { padding: 20px 24px; font-size: 0.88rem; vertical-align: middle; transition: var(--transition); }
.mon-table tr:hover td { background: rgba(168,224,99,0.03); }

/* ── Utilities ──────────────────────────────────────────────── */
.copy-btn {
    background: transparent; border: none; color: var(--muted); cursor: pointer;
    padding: 4px; border-radius: 4px; transition: var(--transition);
}
.copy-btn:hover { color: var(--forest); background: rgba(0,0,0,0.05); }

@keyframes slideDownFade { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4 page-canvas">

        <!-- ═══ BREADCRUMB ═══════════════════════════════════════════════ -->
        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none" style="color:var(--muted); font-weight:600"><i class="bi bi-house-door me-1"></i>Dashboard</a></li>
                <li class="breadcrumb-item active" style="color:var(--forest); font-weight:700">Transaction Monitor</li>
            </ol>
        </nav>

        <!-- ═══ HERO ══════════════════════════════════════════════════════ -->
        <div class="page-header">
            <div class="hero-inner">
                <div class="hero-content">
                    <div class="hero-chip"><i class="bi bi-activity"></i>Gateway Status: <span class="ms-1" style="color:#fff">Operational</span></div>
                    <h1 class="hero-title">Transaction Hub</h1>
                    <p class="hero-sub">Deep-level monitoring of incoming M-Pesa callbacks and initiated STK push requests. Real-time data feed with automated refresh.</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Feed Count</div>
                            <div class="hero-stat-value"><?= $cb_total ?></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Success Rate</div>
                            <div class="hero-stat-value lime"><?= $cb_total > 0 ? round(($cb_success/$cb_total)*100) : 0 ?>%</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Pending Issues</div>
                            <div class="hero-stat-value <?= ($stuck_total + $alert_total) > 0 ? 'text-warning' : '' ?>"><?= $stuck_total + $alert_total ?></div>
                        </div>
                    </div>
                </div>
                <div class="hero-controls">
                    <div class="d-flex align-items-center gap-3 glass-pill p-2" style="background:rgba(255,255,255,0.1); border-radius:16px; border:1px solid rgba(255,255,255,0.1)">
                        <div id="liveIndicator" class="d-flex align-items-center gap-2 px-2">
                            <div class="live-pulse"></div>
                            <span style="color:#fff; font-weight:800; font-size:0.75rem; letter-spacing:1px">LIVE</span>
                        </div>
                        <button id="toggleRefreshBtn" onclick="toggleRefresh()" style="background:var(--lime); border:none; color:var(--forest); border-radius:10px; padding:6px 16px; font-size:0.75rem; cursor:pointer; font-weight:800; transition:all 0.2s;">
                            <i class="bi bi-pause-fill"></i> PAUSE
                        </button>
                    </div>
                    <div class="mt-2 text-end">
                        <span id="refreshTimer" style="font-size:0.7rem; color:rgba(255,255,255,0.5); font-weight:700; text-transform:uppercase; letter-spacing:0.5px">Sync in 15s</span>
                        <div style="width:140px; height:3px; background:rgba(255,255,255,0.1); border-radius:10px; overflow:hidden; margin-top:6px">
                            <div id="refreshBar" style="width:100%; height:100%; background:var(--lime); transition:width 1s linear"></div>
                        </div>
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
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" placeholder="Search logs..." onkeyup="filterTable(this, 'sectionCallbacks')">
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="record-count"><?= $cb_total ?> entries</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="mon-table" id="tableCallbacks">
                    <thead>
                        <tr>
                            <th style="padding-left:24px">Timestamp</th>
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
                            <div class="empty-state text-center py-5" style="color:var(--muted)">
                                <i class="bi bi-inbox" style="font-size:3rem"></i>
                                <p class="mt-2 fw-600">No callback logs found.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($callbacks as $cl):
                        $is_success = (int)$cl['result_code'] === 0;
                    ?>
                        <tr>
                            <td style="padding-left:24px">
                                <div style="font-weight:700; color:var(--ink)"><?= date('H:i:s', strtotime($cl['created_at'])) ?></div>
                                <div style="font-size:0.7rem; color:var(--muted); font-weight:600"><?= date('d M Y', strtotime($cl['created_at'])) ?></div>
                            </td>
                            <td><span class="type-chip" style="background:var(--surface-2); padding:4px 10px; border-radius:8px; font-weight:700; font-size:0.75rem"><?= htmlspecialchars($cl['callback_type']) ?></span></td>
                            <td>
                                <div style="font-weight:700; color:var(--ink)"><?= htmlspecialchars($cl['full_name'] ?? 'Inbound') ?></div>
                                <div style="font-size:0.75rem; color:var(--muted); font-weight:600"><?= htmlspecialchars($cl['phone'] ?? 'Unknown') ?></div>
                            </td>
                            <td><div style="font-weight:800; color:var(--forest)">KES <?= number_format((float)($cl['amount'] ?? 0)) ?></div></td>
                            <td>
                                <span class="status-pill <?= $is_success ? 'sp-success' : 'sp-failed' ?>" style="padding:4px 12px; border-radius:100px; font-size:0.7rem; font-weight:800; background:<?= $is_success ? 'rgba(168,224,99,0.15)' : 'rgba(255,0,0,0.05)' ?>; color:<?= $is_success ? 'var(--forest-mid)' : '#d00' ?>">
                                    <?= $is_success ? 'Success' : 'Failed (' . $cl['result_code'] . ')' ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="mpesa-ref" style="font-family:monospace; font-weight:700; color:var(--muted)"><?= htmlspecialchars($cl['mpesa_receipt_number'] ?: 'N/A') ?></span>
                                    <?php if($cl['mpesa_receipt_number']): ?>
                                    <button class="copy-btn" onclick="copyText('<?= $cl['mpesa_receipt_number'] ?>', this)" title="Copy Receipt">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="payload-cell">
                                <button class="btn btn-sm btn-outline-secondary" onclick="showPayload(<?= htmlspecialchars(json_encode($cl['raw_payload']), ENT_QUOTES) ?>)" style="border-radius:8px; font-size:0.7rem; font-weight:700">
                                    <i class="bi bi-code-slash me-1"></i> View
                                </button>
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
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" placeholder="Search requests..." onkeyup="filterTable(this, 'sectionRequests')">
                </div>
                <?php if ($req_total): ?>
                <span class="record-count"><?= $req_total ?> entries</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="mon-table" id="tableRequests">
                    <thead>
                        <tr>
                            <th style="padding-left:24px">Timestamp</th>
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
                        <tr><td colspan="7">
                            <div class="empty-state text-center py-5" style="color:var(--muted)">
                                <i class="bi bi-send-x" style="font-size:3rem"></i>
                                <p class="mt-2 fw-600">No payment requests found.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr>
                            <td style="padding-left:24px">
                                <div style="font-weight:700; color:var(--ink)"><?= date('H:i:s', strtotime($r['created_at'])) ?></div>
                                <div style="font-size:0.7rem; color:var(--muted); font-weight:600"><?= date('d M Y', strtotime($r['created_at'])) ?></div>
                            </td>
                            <td>
                                <div style="font-weight:700; color:var(--ink)"><?= htmlspecialchars($r['full_name']) ?></div>
                                <div style="font-size:0.75rem; color:var(--muted); font-weight:600"><?= htmlspecialchars($r['phone']) ?></div>
                            </td>
                            <td><div style="font-weight:800; color:var(--forest)">KES <?= number_format((float)($r['amount'] ?? 0)) ?></div></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span style="font-family:monospace; font-weight:700; color:var(--muted)"><?= htmlspecialchars($r['reference_no']) ?></span>
                                    <button class="copy-btn" onclick="copyText('<?= $r['reference_no'] ?>', this)" title="Copy Reference">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span style="font-size:0.75rem; font-family:monospace; color:var(--muted)"><?= substr($r['checkout_request_id'], 0, 12) ?>...</span>
                                    <button class="copy-btn" onclick="copyText('<?= $r['checkout_request_id'] ?>', this)" title="Copy Full ID">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <?php
                                $sp = match($r['status']) {
                                    'completed' => ['background:rgba(168,224,99,0.15); color:var(--forest-mid)', 'Completed'],
                                    'pending'   => ['background:rgba(255,193,7,0.1); color:#856404',   'Pending'],
                                    default     => ['background:rgba(220,53,69,0.1); color:#721c24',    'Failed'],
                                };
                                ?>
                                <span style="padding:4px 12px; border-radius:100px; font-size:0.7rem; font-weight:800; <?= $sp[0] ?>">
                                    <?= $sp[1] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <button class="btn btn-sm" 
                                            style="background:var(--lime); color:var(--forest); font-weight:800; border-radius:10px; padding:4px 14px; font-size:0.7rem; border:none; box-shadow:0 4px 10px var(--lime-glow)"
                                            onclick="activateRequest('<?= $r['checkout_request_id'] ?>', this)">
                                        <i class="bi bi-lightning-fill"></i> Activate
                                    </button>
                                <?php else: ?>
                                    <i class="bi bi-check2-circle text-success" title="Processed"></i>
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
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" placeholder="Search stuck..." onkeyup="filterTable(this, 'sectionStuck')">
                </div>
                <?php if ($stuck_total): ?>
                    <span class="record-count"><?= $stuck_total ?> entries</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="mon-table" id="tableStuck">
                    <thead>
                        <tr>
                            <th style="padding-left:24px">Timestamp</th>
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
                            <div class="empty-state text-center py-5" style="color:var(--muted)">
                                <i class="bi bi-check-circle" style="font-size:3rem; color:var(--lime)"></i>
                                <p class="mt-2 fw-600">No stuck transactions found.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($stuck as $s): ?>
                        <tr>
                            <td style="padding-left:24px">
                                <div style="font-weight:700; color:var(--ink)"><?= date('H:i:s', strtotime($s['created_at'])) ?></div>
                                <div style="font-size:0.7rem; color:var(--muted); font-weight:600"><?= date('d M Y', strtotime($s['created_at'])) ?></div>
                            </td>
                            <td>
                                <div style="font-weight:700; color:var(--ink)"><?= htmlspecialchars($s['full_name']) ?></div>
                                <div style="font-size:0.75rem; color:var(--muted); font-weight:600"><?= htmlspecialchars($s['phone']) ?></div>
                            </td>
                            <td><span style="background:var(--surface-2); padding:4px 10px; border-radius:8px; font-weight:700; font-size:0.75rem"><?= ucfirst($s['contribution_type']) ?></span></td>
                            <td><div style="font-weight:800; color:var(--forest)">KES <?= number_format((float)$s['amount']) ?></div></td>
                            <td><span style="font-family:monospace; font-weight:700; color:var(--muted)"><?= htmlspecialchars($s['reference_no']) ?></span></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Manually activate this transaction?')">
                                    <input type="hidden" name="action" value="manual_activate">
                                    <input type="hidden" name="contribution_id" value="<?= $s['contribution_id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="background:var(--forest); color:#fff; font-weight:800; border-radius:10px; padding:6px 16px; font-size:0.7rem; border:none; box-shadow:0 4px 12px rgba(13,31,20,0.2)">
                                        <i class="bi bi-lightning-charge-fill me-1"></i> Resolve
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
                            <th style="padding-left:24px">Severity</th>
                            <th>Message</th>
                            <th>Date / Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($alerts)): ?>
                        <tr><td colspan="4">
                            <div class="empty-state text-center py-5" style="color:var(--muted)">
                                <i class="bi bi-shield-check" style="font-size:3rem; color:var(--lime)"></i>
                                <p class="mt-2 fw-600">All systems normal. No active alerts.</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($alerts as $a):
                        $sev = strtolower($a['severity'] ?? 'info');
                        $pill_style = match($sev) {
                            'critical' => 'background:rgba(220,53,69,0.1); color:#721c24',
                            'warning'  => 'background:rgba(255,193,7,0.1); color:#856404',
                            default    => 'background:rgba(23,162,184,0.1); color:#0c5460'
                        };
                    ?>
                        <tr>
                            <td style="padding-left:24px">
                                <span style="padding:4px 12px; border-radius:100px; font-size:0.7rem; font-weight:800; <?= $pill_style ?>"><?= strtoupper($sev) ?></span>
                            </td>
                            <td><div style="font-weight:600; color:var(--ink); font-size:0.85rem"><?= htmlspecialchars($a['message']) ?></div></td>
                            <td>
                                <div style="font-weight:700; color:var(--ink)"><?= date('H:i:s', strtotime($a['created_at'])) ?></div>
                                <div style="font-size:0.7rem; color:var(--muted); font-weight:600"><?= date('d M Y', strtotime($a['created_at'])) ?></div>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="acknowledge_alert">
                                    <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" style="border-radius:10px; font-size:0.7rem; padding:4px 14px; font-weight:700">
                                        Acknowledge
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
            <div class="modal-dialog modal-dialog-centered" style="max-width:600px">
                <div class="modal-content" style="border:none; box-shadow:var(--shadow-lg); border-radius:var(--radius-lg)">
                    <div class="modal-header px-4 pt-4 pb-0" style="border:none">
                        <div>
                            <h5 class="fw-800" style="font-weight:800; letter-spacing:-1px">Transaction Payload</h5>
                            <p style="font-size:0.75rem; color:var(--muted); font-weight:600">Secure breakdown of the raw M-Pesa callback JSON.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="d-flex justify-content-end mb-2">
                             <button class="btn btn-sm btn-light" onclick="copyPayload()" style="font-size:0.7rem; font-weight:700; border-radius:8px">
                                <i class="bi bi-clipboard me-1"></i> Copy JSON
                             </button>
                        </div>
                        <pre class="payload-code" id="payloadContent" style="background:#0a0f0c; color:var(--lime); padding:20px; border-radius:16px; font-size:0.8rem; line-height:1.6; max-height:450px"></pre>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
function switchTab(tab) {
    const sections = ['sectionCallbacks', 'sectionRequests', 'sectionStuck', 'sectionAlerts'];
    const buttons = ['tabCallbacks', 'tabRequests', 'tabStuck', 'tabAlerts'];
    
    // Smooth Transition Out
    const currentActiveSection = sections.find(s => !document.getElementById(s).classList.contains('d-none'));
    if (currentActiveSection) {
        const el = document.getElementById(currentActiveSection);
        el.style.opacity = '0';
        el.style.transform = 'translateY(10px)';
        
        setTimeout(() => {
            sections.forEach(s => document.getElementById(s).classList.add('d-none'));
            buttons.forEach(b => document.getElementById(b).classList.remove('active'));

            const targetSection = document.getElementById('section' + tab.charAt(0).toUpperCase() + tab.slice(1));
            const targetBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));

            targetSection.classList.remove('d-none');
            targetSection.style.opacity = '0';
            targetSection.style.transform = 'translateY(10px)';
            targetBtn.classList.add('active');

            // Trigger reflow for animation
            targetSection.offsetHeight; 
            targetSection.style.transition = 'all 0.4s ease';
            targetSection.style.opacity = '1';
            targetSection.style.transform = 'translateY(0)';
        }, 200);
    } else {
        // Initial load
        const targetSection = document.getElementById('section' + tab.charAt(0).toUpperCase() + tab.slice(1));
        const targetBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        targetSection.classList.remove('d-none');
        targetBtn.classList.add('active');
    }
    
    sessionStorage.setItem('monitorActiveTab', tab);
}

document.addEventListener('DOMContentLoaded', () => {
    const savedTab = sessionStorage.getItem('monitorActiveTab') || 'callbacks';
    switchTab(savedTab);
});

function filterTable(input, sectionID) {
    const filter = input.value.toLowerCase();
    const table = document.getElementById(sectionID).querySelector('table');
    const trs = table.getElementsByTagName('tr');

    for (let i = 1; i < trs.length; i++) {
        let text = trs[i].textContent.toLowerCase();
        trs[i].style.display = text.indexOf(filter) > -1 ? "" : "none";
    }
}

function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2 text-success"></i>';
        setTimeout(() => btn.innerHTML = original, 2000);
    });
}

function showPayload(raw) {
    const el = document.getElementById('payloadContent');
    try {
        const obj = typeof raw === 'string' ? JSON.parse(raw) : raw;
        el.textContent = JSON.stringify(obj, null, 2);
    } catch {
        el.textContent = raw;
    }
    new bootstrap.Modal(document.getElementById('payloadModal')).show();
}

function copyPayload() {
    const text = document.getElementById('payloadContent').textContent;
    navigator.clipboard.writeText(text);
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

document.querySelectorAll('.detail-card').forEach(card => {
    card.addEventListener('mouseenter', () => hoverPause = true);
    card.addEventListener('mouseleave', () => hoverPause = false);
});

function toggleRefresh() {
    isPaused = !isPaused;
    const btn = document.getElementById('toggleRefreshBtn');
    const pulse = document.querySelector('.live-pulse');
    const timerEl = document.getElementById('refreshTimer');
    
    if (isPaused) {
        btn.innerHTML = '<i class="bi bi-play-fill"></i> RESUME';
        btn.style.background = 'var(--muted)';
        if(pulse) pulse.style.background = '#fca5a5';
        if (timerEl) timerEl.innerText = 'Sync Paused';
    } else {
        btn.innerHTML = '<i class="bi bi-pause-fill"></i> PAUSE';
        btn.style.background = 'var(--lime)';
        if(pulse) pulse.style.background = 'var(--lime)';
        countdown = MAX_TIME;
    }
}

setInterval(() => {
    if (isPaused) return;

    const timerEl = document.getElementById('refreshTimer');
    const barEl = document.getElementById('refreshBar');
    const modal = document.getElementById('payloadModal');
    
    if ((modal && modal.classList.contains('show')) || hoverPause) {
        if (timerEl) timerEl.innerText = 'Wait (Reading...)';
        return;
    }
    
    countdown--;
    
    if (countdown <= 0) {
        if (timerEl) timerEl.innerText = 'Syncing...';
        if (barEl) barEl.style.width = '0%';
        location.reload();
    } else {
        if (timerEl) timerEl.innerText = `Sync in ${countdown}s`;
        if (barEl) barEl.style.width = `${(countdown / MAX_TIME) * 100}%`;
    }
}, 1000);
</script>
</body>
</html>