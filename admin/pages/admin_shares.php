<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SupportTicketWidget.php';
require_once __DIR__ . '/../../inc/ShareValuationEngine.php';

\USMS\Middleware\AuthMiddleware::requireModulePermission('shares');
$layout = LayoutManager::create('admin');

$pageTitle = "Equity & Share Management";
$svEngine = new ShareValuationEngine($conn);
$valuation = $svEngine->getValuation();

// Handle Process Exit Request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_exit') {
    $req_id = (int)$_POST['request_id'];
    $status = $_POST['status'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    $req_q = $conn->prepare("SELECT * FROM withdrawal_requests WHERE withdrawal_id = ? AND source_ledger = 'shares' AND status = 'pending'");
    $req_q->bind_param("i", $req_id);
    $req_q->execute();
    $req = $req_q->get_result()->fetch_assoc();
    if ($req) {
        $mem_id = (int)$req['member_id'];
        $amt = (float)$req['amount'];
        $ref = $req['ref_no'];
        require_once __DIR__ . '/../../inc/FinancialEngine.php';
        $fEngine = new FinancialEngine($conn);
        try {
            if ($status === 'approved') {
                $payout_method = $_POST['payout_method'] ?? 'bank';
                $fEngine->transact(['member_id' => $mem_id,'amount' => $amt,'action_type' => 'withdrawal_finalize','method' => $payout_method,'reference' => $ref,'notes' => "Exit Request Approved: " . $admin_notes]);
                $conn->query("UPDATE withdrawal_requests SET status = 'completed', notes = CONCAT(IFNULL(notes, ''), '\nAdmin: ', '$admin_notes'), updated_at = NOW() WHERE withdrawal_id = $req_id");
                $conn->query("UPDATE members SET status = 'inactive' WHERE member_id = $mem_id");
                $msg = "<div class='alert alert-success rounded-3'>Exit request approved. Member deactivated and payout processed.</div>";
            } elseif ($status === 'rejected') {
                $fEngine->transact(['member_id' => $mem_id,'amount' => $amt,'action_type' => 'withdrawal_revert','dest_cat' => FinancialEngine::CAT_SHARES,'reference' => $ref."-REV",'notes' => "Exit Request Rejected: " . $admin_notes]);
                $conn->query("UPDATE withdrawal_requests SET status = 'rejected', notes = CONCAT(IFNULL(notes, ''), '\nAdmin: ', '$admin_notes'), updated_at = NOW() WHERE withdrawal_id = $req_id");
                $msg = "<div class='alert alert-warning rounded-3'>Exit request rejected and shares reinstated.</div>";
            }
        } catch (Exception $e) {
            $msg = "<div class='alert alert-danger rounded-3'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

$msg = $msg ?? "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'distribute_dividend') {
    $pool = (float)$_POST['dividend_pool'];
    $year = $_POST['fiscal_year'] ?? date('Y');
    $ref = "DIV-" . $year . "-" . strtoupper(uniqid());
    if ($pool > 0) {
        try {
            // Using the updated engine that now handles pro-rata and taxes
            if ($svEngine->distributeDividends($pool, $ref)) {
                $msg = "<div class='alert alert-success rounded-3 shadow-sm border-0 d-flex align-items-center'>
                            <i class='bi bi-check-circle-fill me-3 fs-4'></i>
                            <div>
                                <strong class='d-block'>Distribution Successful!</strong>
                                <span>KES " . number_format($pool, 2) . " has been distributed proportionally (pro-rata) for FY $year. 5% WHT has been deducted.</span>
                            </div>
                        </div>";
                $valuation = $svEngine->getValuation();
            }
        } catch (Exception $e) {
            $msg = "<div class='alert alert-danger rounded-3 shadow-sm border-0 d-flex align-items-center'>
                        <i class='bi bi-exclamation-octagon-fill me-3 fs-4'></i>
                        <div>
                            <strong class='d-block'>Distribution Failed</strong>
                            <span>" . $e->getMessage() . "</span>
                        </div>
                    </div>";
        }
    }
}

$sqlTop = "SELECT m.full_name, ms.units_owned, ms.total_amount_paid, (ms.units_owned / ?) * 100 as ownership_pct FROM member_shareholdings ms JOIN members m ON ms.member_id = m.member_id WHERE ms.units_owned > 0 ORDER BY ms.units_owned DESC LIMIT 5";
$stmt = $conn->prepare($sqlTop);
$totalU = (float)$valuation['total_units'] ?: 1;
$stmt->bind_param("d", $totalU);
$stmt->execute();
$topHolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sqlExits = "SELECT w.*, m.full_name FROM withdrawal_requests w JOIN members m ON w.member_id = m.member_id WHERE w.source_ledger = 'shares' AND w.status = 'pending' ORDER BY w.created_at ASC";
$exitsResult = $conn->query($sqlExits);
$pendingExits = $exitsResult ? $exitsResult->fetch_all(MYSQLI_ASSOC) : [];

$sqlHistory = "SELECT st.created_at, st.reference_no, st.units as share_units, st.unit_price, st.total_value, st.transaction_type, m.full_name FROM share_transactions st LEFT JOIN members m ON st.member_id = m.member_id ORDER BY st.created_at DESC";
$historyResult = $conn->query($sqlHistory);

$transactions = [];
$chartLabels = [];
$chartData = [];
$runningUnits = 0;

if ($historyResult) {
    while ($row = $historyResult->fetch_assoc()) {
        $row['share_units'] = (float)$row['total_value'] / (float)$valuation['price'];
        $row['unit_price'] = (float)$valuation['price'];
        $transactions[] = $row;
    }
    $chronological_transactions = array_reverse($transactions);
    foreach ($chronological_transactions as $txn) {
        if ($txn['transaction_type'] === 'purchase' || $txn['transaction_type'] === 'migration') {
            $runningUnits += (float)$txn['share_units'];
        }
        $chartLabels[] = date('M d', strtotime($txn['created_at']));
        $chartData[] = $runningUnits * (float)$valuation['price'];
    }
}

$jsLabels = json_encode($chartLabels);
$jsData   = json_encode($chartData);
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<style>
/* ============================================================
   EQUITY & SHARE MANAGEMENT — JAKARTA SANS + GLASSMORPHISM
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

/* ── Hero Banner ── */
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
.hp-hero .ring1 { width:320px;height:320px;top:-80px;right:-80px; }
.hp-hero .ring2 { width:500px;height:500px;top:-160px;right:-160px; }
.hero-badge {
    display:inline-flex; align-items:center; gap:0.45rem;
    background:rgba(181,244,60,0.12); border:1px solid rgba(181,244,60,0.25);
    color:var(--lime-soft); border-radius:100px; padding:0.28rem 0.85rem;
    font-size:0.68rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase;
    margin-bottom:0.9rem; position:relative;
}
.hero-badge::before { content:''; width:6px;height:6px; border-radius:50%; background:var(--lime); animation:pulse-dot 2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

/* ── Buttons ── */
.btn-lime  { background:var(--lime); color:var(--forest) !important; border:none; font-weight:700; transition:var(--transition); }
.btn-lime:hover  { background:var(--lime-soft); box-shadow:var(--shadow-glow); transform:translateY(-1px); }
.btn-forest { background:var(--forest); color:#fff !important; border:none; font-weight:700; transition:var(--transition); }
.btn-forest:hover { background:var(--forest-light); }

/* ── Exit Alert Banner ── */
.exit-alert {
    background: var(--surface);
    border-radius: var(--radius-lg);
    border: 1px solid rgba(245,158,11,0.2);
    border-left: 4px solid #f59e0b;
    box-shadow: var(--shadow-md);
    overflow: hidden;
}
.exit-alert-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(245,158,11,0.1);
    display: flex; justify-content: space-between; align-items: center;
    background: #fffbeb;
}
.exit-alert-header h5 { font-weight:800; font-size:0.9rem; color:#b45309; margin:0; }

/* ── Glass Cards ── */
.glass-card {
    background: var(--surface); border-radius: var(--radius-lg);
    border: 1px solid var(--border); box-shadow: var(--shadow-md);
    transition: var(--transition); overflow: hidden;
}
.glass-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }

/* NAV hero card */
.nav-card {
    background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
    border-radius: var(--radius-lg);
    padding: 1.8rem;
    position: relative; overflow: hidden; color: #fff; height: 100%;
    border: none; box-shadow: var(--shadow-lg);
}
.nav-card::before {
    content:''; position:absolute; inset:0;
    background: radial-gradient(ellipse 60% 80% at 90% 10%, rgba(181,244,60,0.18), transparent 60%);
    pointer-events:none;
}
.nav-card-label {
    display:inline-flex; align-items:center; gap:0.5rem;
    background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.15);
    border-radius:100px; padding:0.25rem 0.8rem;
    font-size:0.7rem; font-weight:700; color:rgba(255,255,255,0.8);
    letter-spacing:0.08em; text-transform:uppercase; margin-bottom:1.2rem;
    position:relative;
}
.nav-amount {
    font-size:2.5rem; font-weight:800; letter-spacing:-0.04em; line-height:1; color:#fff;
    position:relative;
}
.nav-sub { font-size:0.82rem; color:rgba(255,255,255,0.55); margin-top:0.4rem; position:relative; }
.nav-footer {
    margin-top:1.5rem; padding-top:1.2rem; border-top:1px solid rgba(255,255,255,0.12);
    display:flex; justify-content:space-between; align-items:flex-end; position:relative;
}
.nav-price-label { font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:var(--lime-soft); margin-bottom:0.2rem; }
.nav-price-value { font-size:1.15rem; font-weight:800; color:#fff; }

/* Metric cards */
.metric-card {
    background:var(--surface); border-radius:var(--radius-lg);
    border:1px solid var(--border); box-shadow:var(--shadow-md);
    padding:1.4rem 1.6rem; height:100%; transition:var(--transition);
    position:relative; overflow:hidden;
}
.metric-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
.metric-card::after { content:''; position:absolute; bottom:0;left:0;right:0;height:3px; border-radius:0 0 var(--radius-lg) var(--radius-lg); opacity:0; transition:var(--transition); }
.metric-card:hover::after { opacity:1; }
.metric-card.mc-units::after  { background:linear-gradient(90deg,var(--lime),var(--lime-soft)); }
.metric-card.mc-chart { background:#f5f8f6; border:none; }

.metric-icon { width:46px;height:46px; border-radius:var(--radius-sm); display:flex;align-items:center;justify-content:center; font-size:1.2rem; margin-bottom:0.9rem; }
.metric-label { font-size:0.67rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted); margin-bottom:0.3rem; }
.metric-value { font-size:1.6rem;font-weight:800;letter-spacing:-0.04em;line-height:1; color:var(--text-primary); }
.metric-divider { border:none;border-top:1px solid var(--border);margin:0.9rem 0; }
.metric-row { display:flex;justify-content:space-between;align-items:center;padding:0.35rem 0; }
.metric-row:last-child { border-bottom:none; }
.metric-row-label { font-size:0.78rem;color:var(--text-muted);font-weight:500; }
.metric-row-value { font-size:0.82rem;font-weight:800; }
.chart-badge {
    display:inline-flex;align-items:center;gap:0.35rem; border-radius:100px;
    background:var(--surface); border:1px solid var(--border);
    padding:0.2rem 0.7rem; font-size:0.72rem; font-weight:700; color:#166534;
    box-shadow:var(--shadow-sm);
}

/* ── Table Cards ── */
.table-card { background:var(--surface); border-radius:var(--radius-lg); border:1px solid var(--border); box-shadow:var(--shadow-md); overflow:hidden; height:100%; }
.table-card-header {
    padding:1rem 1.5rem; border-bottom:1px solid var(--border);
    display:flex;justify-content:space-between;align-items:center; background:#fff;
}
.table-card-header h5 { font-weight:800;font-size:0.95rem;color:var(--text-primary);margin:0; }

.table-share thead th {
    background:#f5f8f6; color:var(--text-muted); font-size:0.67rem;
    font-weight:800; text-transform:uppercase; letter-spacing:0.1em;
    padding:0.8rem 1rem; border-bottom:1px solid var(--border); white-space:nowrap;
}
.table-share thead th:first-child { padding-left:1.5rem; }
.table-share thead th:last-child  { padding-right:1.5rem; }
.table-share tbody tr { border-bottom:1px solid rgba(13,43,31,0.04); transition:var(--transition); }
.table-share tbody tr:last-child { border-bottom:none; }
.table-share tbody tr:hover { background:#f0faf4; }
.table-share tbody td { padding:0.85rem 1rem;vertical-align:middle;font-size:0.875rem;color:var(--text-primary); }
.table-share tbody td:first-child { padding-left:1.5rem; }
.table-share tbody td:last-child  { padding-right:1.5rem; }

.ref-badge {
    font-family:'Courier New',monospace !important; font-size:0.72rem; font-weight:700;
    background:#f5f8f6; border:1px solid var(--border); border-radius:6px;
    padding:0.2rem 0.55rem; color:var(--text-muted); letter-spacing:0.03em;
}
.units-cell { display:flex;align-items:center;gap:0.5rem; }
.units-icon {
    width:22px;height:22px; border-radius:50%; background:#f0fdf4;
    display:flex;align-items:center;justify-content:center; font-size:0.6rem; color:#166534; flex-shrink:0;
}

/* Shareholders list */
.holder-item {
    display:flex; justify-content:space-between; align-items:center;
    padding:0.85rem 1.4rem; border-bottom:1px solid rgba(13,43,31,0.04);
    transition:var(--transition);
}
.holder-item:last-child { border-bottom:none; }
.holder-item:hover { background:#f0faf4; }
.holder-rank {
    width:26px;height:26px; border-radius:8px; background:var(--bg-muted);
    display:flex;align-items:center;justify-content:center;
    font-size:0.7rem;font-weight:800;color:var(--text-muted); margin-right:0.75rem; flex-shrink:0;
}
.holder-rank.top1 { background:rgba(181,244,60,0.2); color:var(--forest); }
.holder-rank.top2 { background:rgba(209,213,219,0.4); color:#374151; }
.holder-rank.top3 { background:rgba(234,179,8,0.15); color:#854d0e; }
.holder-name  { font-weight:700;font-size:0.875rem;color:var(--text-primary); }
.holder-units { font-size:0.72rem;color:var(--text-muted);margin-top:1px; }
.holder-value { font-weight:800;font-size:0.875rem;color:var(--forest); }
.pct-badge {
    display:inline-block; border-radius:100px;
    background:rgba(13,43,31,0.07); color:var(--forest);
    padding:0.15rem 0.6rem; font-size:0.68rem; font-weight:800;
    margin-top:0.2rem;
}

/* Exit table */
.exit-table { width:100%;border-collapse:separate;border-spacing:0; }
.exit-table thead th { background:#fffbeb;color:#92400e;font-size:0.67rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;padding:0.75rem 1rem;border-bottom:1px solid rgba(245,158,11,0.1); }
.exit-table thead th:first-child { padding-left:1.5rem; }
.exit-table thead th:last-child  { padding-right:1.5rem; }
.exit-table tbody tr { border-bottom:1px solid rgba(245,158,11,0.06);transition:var(--transition); }
.exit-table tbody tr:hover { background:#fffbeb; }
.exit-table tbody td { padding:0.85rem 1rem;vertical-align:middle;font-size:0.875rem; }
.exit-table tbody td:first-child { padding-left:1.5rem; }
.exit-table tbody td:last-child  { padding-right:1.5rem; }
.btn-review {
    padding:0.32rem 0.9rem;border-radius:100px;font-size:0.75rem;font-weight:700;
    border:1.5px solid var(--forest);color:var(--forest);background:transparent;
    cursor:pointer;transition:var(--transition);
}
.btn-review:hover { background:var(--forest);color:#fff; }

/* Animations */
@keyframes fadeIn  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
.fade-in  { animation:fadeIn  0.5s ease-out both; }
.slide-up { animation:slideUp 0.5s cubic-bezier(0.4,0,0.2,1) both; }

/* ── Modal Shared ── */
.modal-content { border-radius:var(--radius-xl) !important; border:none !important; overflow:hidden; }
.modal-top-forest {
    background:linear-gradient(135deg,var(--forest) 0%,var(--forest-mid) 100%);
    padding:1.5rem 1.8rem; position:relative; overflow:hidden;
}
.modal-top-forest::before { content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 90% 20%,rgba(181,244,60,0.18),transparent 60%); }
.modal-top-forest h5 { color:#fff;font-weight:800;font-size:1rem;margin:0;position:relative; }
.modal-top-warning { background:linear-gradient(135deg,#92400e 0%,#b45309 100%); padding:1.5rem 1.8rem; }
.modal-top-warning h5 { color:#fff;font-weight:800;font-size:1rem;margin:0; }

.modal-body-pad { padding:1.6rem 1.8rem;background:#fff; }
.modal-footer-pad { padding:0 1.8rem 1.6rem;background:#fff;display:flex;gap:0.65rem;justify-content:flex-end; }

.field-label { font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.09em;color:var(--text-muted);margin-bottom:0.5rem;display:block; }
.form-control-enh, .form-select-enh {
    border-radius:var(--radius-md);border:1.5px solid rgba(13,43,31,0.1);
    font-size:0.875rem;font-weight:500;padding:0.65rem 1rem;
    width:100%;color:var(--text-primary);background:#f8faf9;
    font-family:'Plus Jakarta Sans',sans-serif !important;transition:var(--transition); appearance:none;
}
.form-control-enh:focus, .form-select-enh:focus { outline:none;border-color:var(--lime);background:#fff;box-shadow:var(--shadow-glow); }
.form-select-enh {
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7c74' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 1rem center;padding-right:2.4rem;
}
.input-group-enh { display:flex; }
.input-group-enh .prefix {
    background:#f0f4f2;border:1.5px solid rgba(13,43,31,0.1);border-right:none;
    border-radius:var(--radius-md) 0 0 var(--radius-md);padding:0 1rem;
    font-size:0.82rem;font-weight:700;color:var(--text-muted);display:flex;align-items:center;
}
.input-group-enh .form-control-enh { border-radius:0 var(--radius-md) var(--radius-md) 0; }

.exit-summary-row {
    display:flex;justify-content:space-between;align-items:center;
    background:var(--bg-muted);border-radius:var(--radius-md);padding:0.9rem 1.1rem;margin-bottom:0.6rem;
    border:1px solid var(--border);
}
.exit-summary-label { font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted); }
.exit-summary-value { font-weight:800;font-size:0.95rem;color:var(--text-primary); }
.exit-summary-value.danger { color:#dc2626; }

.info-box { background:#eff6ff;border:1px solid rgba(59,130,246,0.18);border-radius:var(--radius-md);padding:0.8rem 1rem;font-size:0.8rem;font-weight:600;color:#1d4ed8;display:flex;align-items:flex-start;gap:0.6rem; }
.info-box i { flex-shrink:0;margin-top:1px; }

/* DataTables overrides */
.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background:var(--forest) !important; color:#fff !important; border:none !important; border-radius:8px !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background:#f0faf4 !important; color:var(--forest) !important; border:none !important; border-radius:8px !important;
}
.dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_length { font-size:0.8rem; color:var(--text-muted); font-weight:600; }

/* Dropdown */
.dropdown-menu { border-radius:var(--radius-md) !important;border:1px solid var(--border) !important;box-shadow:var(--shadow-lg) !important;padding:0.4rem !important; }
.dropdown-item { border-radius:8px;font-size:0.84rem;font-weight:600;padding:0.58rem 0.9rem !important;color:var(--text-primary) !important;transition:var(--transition); }
.dropdown-item:hover { background:#f0faf4 !important; }

@media (max-width:768px) {
    .hp-hero { padding:2rem 1.5rem 4rem; }
    .hp-hero h1 { font-size:1.7rem !important; }
    .nav-amount { font-size:1.8rem; }
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
                <div class="col-lg-8">
                    <div class="hero-badge">Corporate Equity</div>
                    <h1 style="font-weight:800;letter-spacing:-0.03em;font-size:2.2rem;line-height:1.15;position:relative;margin-bottom:0.5rem;">
                        Share & Equity Management
                    </h1>
                    <p style="color:rgba(255,255,255,0.55);font-size:0.93rem;font-weight:500;position:relative;margin:0;">
                        Global Sacco share portfolio, valuation, and dividend operations.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0" style="position:relative;">
                    <button class="btn btn-lime rounded-pill px-4 py-2 fw-bold" style="font-size:0.875rem;" data-bs-toggle="modal" data-bs-target="#dividendModal">
                        <i class="bi bi-cash-stack me-2"></i>Distribute Dividend
                    </button>
                </div>
            </div>
        </div>

        <div style="margin-top:-36px; position:relative; z-index:10;">

            <?php if (!empty($msg)): ?>
                <div class="mb-4"><?= $msg ?></div>
            <?php endif; ?>

            <!-- Pending Exit Requests -->
            <?php if (!empty($pendingExits)): ?>
            <div class="exit-alert mb-4 slide-up">
                <div class="exit-alert-header">
                    <div style="display:flex;align-items:center;gap:0.6rem;">
                        <div style="width:30px;height:30px;border-radius:8px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;font-size:0.85rem;"></i>
                        </div>
                        <h5>Pending SACCO Exit Requests</h5>
                    </div>
                    <span style="background:#f59e0b;color:#fff;border-radius:100px;padding:0.2rem 0.75rem;font-size:0.72rem;font-weight:800;">
                        <?= count($pendingExits) ?> Pending
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="exit-table">
                        <thead>
                            <tr>
                                <th>Date Requested</th>
                                <th>Member</th>
                                <th>Reference</th>
                                <th>Refund Amount</th>
                                <th>Phone</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingExits as $exit): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700;font-size:0.82rem;color:var(--text-primary);"><?= date('d M Y', strtotime($exit['created_at'])) ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-muted);"><?= date('h:i A', strtotime($exit['created_at'])) ?></div>
                                </td>
                                <td><span style="font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($exit['full_name']) ?></span></td>
                                <td><span class="ref-badge"><?= htmlspecialchars($exit['ref_no']) ?></span></td>
                                <td><span style="font-weight:800;color:#dc2626;">KES <?= number_format((float)$exit['amount'], 2) ?></span></td>
                                <td><span style="font-size:0.82rem;color:var(--text-muted);"><?= htmlspecialchars($exit['phone_number']) ?></span></td>
                                <td style="text-align:right;">
                                    <button class="btn-review" onclick='openExitModal(<?= json_encode($exit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-eye me-1" style="font-size:0.7rem;"></i>Review Exit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pending Support Tickets -->
            <?php render_support_ticket_widget($conn, ['shares'], 'Shares & Equity'); ?>

            <!-- KPI Row -->
            <div class="row g-3 mb-4">
                <!-- NAV Card -->
                <div class="col-xl-4 col-lg-5">
                    <div class="nav-card slide-up" style="animation-delay:0.04s">
                        <div class="nav-card-label">
                            <i class="bi bi-bank"></i> Corporate Net Worth (NAV)
                        </div>
                        <div class="nav-amount">KES <?= number_format((float)$valuation['equity'], 2) ?></div>
                        <div class="nav-sub">Total SACCO Equity</div>
                        <div class="nav-footer">
                            <div>
                                <div class="nav-price-label">Current Unit Price</div>
                                <div class="nav-price-value">KES <?= number_format((float)$valuation['price'], 2) ?></div>
                            </div>
                            <span style="background:rgba(181,244,60,0.15);border:1px solid rgba(181,244,60,0.25);border-radius:100px;padding:0.22rem 0.8rem;font-size:0.68rem;font-weight:800;color:var(--lime-soft);letter-spacing:0.08em;">
                                DYNAMIC
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Units Card -->
                <div class="col-xl-3 col-lg-3">
                    <div class="metric-card mc-units slide-up" style="animation-delay:0.1s">
                        <div class="metric-icon" style="background:var(--lime-glow-sm);color:var(--forest);">
                            <i class="bi bi-pie-chart-fill" style="font-size:1.2rem;"></i>
                        </div>
                        <div class="metric-label">Total Issued Units</div>
                        <div class="metric-value"><?= number_format((float)$valuation['total_units'], 4) ?></div>
                        <hr class="metric-divider">
                        <div class="metric-row">
                            <span class="metric-row-label">Total Assets</span>
                            <span class="metric-row-value" style="color:#166534;">KES <?= number_format((float)$valuation['total_assets'], 2) ?></span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-row-label">Total Liabilities</span>
                            <span class="metric-row-value" style="color:#dc2626;">KES <?= number_format((float)$valuation['liabilities'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Chart Card -->
                <div class="col-xl-5 col-lg-4">
                    <div class="metric-card mc-chart slide-up" style="animation-delay:0.16s">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                            <div>
                                <div class="metric-label">Corporate Portfolio Growth</div>
                                <div style="font-size:0.78rem;color:var(--text-muted);font-weight:500;">Cumulative equity over time</div>
                            </div>
                            <span class="chart-badge"><i class="bi bi-graph-up-arrow me-1"></i>Live</span>
                        </div>
                        <div style="height:150px;position:relative;">
                            <canvas id="growthChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Transactions + Shareholders -->
            <div class="row g-3">
                <!-- Transactions Table -->
                <div class="col-lg-8">
                    <div class="table-card slide-up" style="animation-delay:0.22s">
                        <div class="table-card-header">
                            <h5>Global Share Transactions</h5>
                            <span style="background:#f0faf4;color:#166534;border:1px solid rgba(22,163,74,0.15);border-radius:100px;padding:0.2rem 0.7rem;font-size:0.7rem;font-weight:800;">
                                <?= count($transactions) ?> records
                            </span>
                        </div>
                        <div class="table-responsive p-3">
                            <table id="historyTable" class="table-share w-100">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Reference</th>
                                        <th>Units</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($transactions)): foreach ($transactions as $row): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:700;font-size:0.82rem;"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                            <div style="font-size:0.7rem;color:var(--text-muted);"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <span style="font-weight:700;color:var(--forest);"><?= htmlspecialchars($row['full_name'] ?? 'System') ?></span>
                                        </td>
                                        <td><span class="ref-badge"><?= htmlspecialchars($row['reference_no']) ?></span></td>
                                        <td>
                                            <div class="units-cell">
                                                <div class="units-icon"><i class="bi bi-plus-lg"></i></div>
                                                <span style="font-weight:700;"><?= number_format((float)$row['share_units'], 2) ?></span>
                                            </div>
                                        </td>
                                        <td><span style="font-weight:800;color:var(--forest);">KES <?= number_format((float)$row['total_value'], 2) ?></span></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Shareholders -->
                <div class="col-lg-4">
                    <div class="table-card slide-up" style="animation-delay:0.28s">
                        <div class="table-card-header">
                            <h5>Top Shareholders</h5>
                            <span style="background:#f5f8f6;color:var(--text-muted);border:1px solid var(--border);border-radius:100px;padding:0.2rem 0.7rem;font-size:0.7rem;font-weight:800;">
                                Top 5
                            </span>
                        </div>
                        <div>
                            <?php foreach ($topHolders as $idx => $holder):
                                $rankClass = match($idx) { 0 => 'top1', 1 => 'top2', 2 => 'top3', default => '' };
                            ?>
                            <div class="holder-item">
                                <div style="display:flex;align-items:center;">
                                    <div class="holder-rank <?= $rankClass ?>">#<?= $idx + 1 ?></div>
                                    <div>
                                        <div class="holder-name"><?= htmlspecialchars($holder['full_name']) ?></div>
                                        <div class="holder-units"><?= number_format((float)$holder['units_owned'], 2) ?> units</div>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div class="holder-value">KES <?= number_format((float)$holder['units_owned'] * (float)$valuation['price'], 2) ?></div>
                                    <span class="pct-badge"><?= number_format((float)$holder['ownership_pct'], 2) ?>%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /overlap -->

    </div><!-- /container-fluid -->


    <!-- ═══════════════════════════
         MODAL: EXIT REQUEST REVIEW
    ═══════════════════════════ -->
    <div class="modal fade" id="exitModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
            <div class="modal-content">
                <div class="modal-top-warning d-flex justify-content-between align-items-center">
                    <div style="display:flex;align-items:center;gap:0.65rem;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-door-open-fill" style="color:#fff;font-size:0.9rem;"></i>
                        </div>
                        <h5>Review Exit Request</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="process_exit">
                    <input type="hidden" name="request_id" id="exit_req_id">
                    <div class="modal-body-pad">
                        <div class="exit-summary-row">
                            <span class="exit-summary-label">Member</span>
                            <span class="exit-summary-value" id="exit_member_name">—</span>
                        </div>
                        <div class="exit-summary-row">
                            <span class="exit-summary-label">Refund Amount</span>
                            <span class="exit-summary-value danger" id="exit_amount">KES 0.00</span>
                        </div>

                        <div class="mb-3" style="margin-top:1rem;">
                            <label class="field-label">Action</label>
                            <select name="status" class="form-select-enh" id="exitStatusSelect" required onchange="togglePayout()">
                                <option value="">— Choose Action —</option>
                                <option value="approved">✅ Approve & Pay (Complete Exit)</option>
                                <option value="rejected">❌ Reject & Cancel</option>
                            </select>
                        </div>

                        <div class="mb-3" id="payoutMethodSection" style="display:none;">
                            <label class="field-label">Payout Channel</label>
                            <select name="payout_method" class="form-select-enh">
                                <option value="bank">🏦 SACCO Bank Account</option>
                                <option value="cash">💵 SACCO Cash at Hand</option>
                                <option value="mpesa">📱 M-Pesa B2C/Paybill</option>
                            </select>
                        </div>

                        <div>
                            <label class="field-label">Admin Notes <span style="color:#dc2626;">*</span></label>
                            <textarea name="admin_notes" class="form-control-enh" rows="2"
                                      placeholder="Reason for approval or rejection..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer-pad" style="padding-top:0.5rem;">
                        <button type="button" style="background:none;border:1.5px solid var(--border);border-radius:100px;padding:0.55rem 1.2rem;font-weight:700;font-size:0.85rem;cursor:pointer;color:var(--text-muted);" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" style="flex:1;background:var(--forest);color:#fff;border:none;border-radius:100px;padding:0.6rem;font-weight:800;font-size:0.875rem;cursor:pointer;box-shadow:var(--shadow-md);">
                            Submit Action
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ═══════════════════════════
         MODAL: DISTRIBUTE DIVIDEND
    ═══════════════════════════ -->
    <div class="modal fade" id="dividendModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <div class="modal-top-forest d-flex justify-content-between align-items-center">
                    <div style="display:flex;align-items:center;gap:0.65rem;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-cash-stack" style="color:var(--lime);font-size:0.9rem;"></i>
                        </div>
                        <h5>Distribute Dividend</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="distribute_dividend">
                    <div class="modal-body-pad">
                        <div class="mb-3">
                            <label class="field-label">Fiscal Year</label>
                            <select name="fiscal_year" class="form-select-enh" required>
                                <option value="<?= date('Y') ?>"><?= date('Y') ?> (Current)</option>
                                <option value="<?= date('Y')-1 ?>"><?= date('Y')-1 ?></option>
                                <option value="<?= date('Y')-2 ?>"><?= date('Y')-2 ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="field-label">Total Dividend Pool (KES)</label>
                            <div class="input-group-enh">
                                <span class="prefix">KES</span>
                                <input type="number" step="0.01" name="dividend_pool" class="form-control-enh"
                                       placeholder="Enter amount to distribute" required>
                            </div>
                        </div>
                        <div class="info-box">
                            <i class="bi bi-info-circle-fill"></i>
                            <span>This amount will be distributed <strong>proportionally</strong> to all unit holders based on their ownership percentage.</span>
                        </div>
                    </div>
                    <div class="modal-footer-pad" style="padding-top:0.5rem;">
                        <button type="button" style="background:none;border:1.5px solid var(--border);border-radius:100px;padding:0.55rem 1.2rem;font-weight:700;font-size:0.85rem;cursor:pointer;color:var(--text-muted);" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" style="flex:1;background:var(--lime);color:var(--forest);border:none;border-radius:100px;padding:0.6rem;font-weight:800;font-size:0.875rem;cursor:pointer;box-shadow:0 4px 16px rgba(181,244,60,0.3);">
                            <i class="bi bi-check-circle-fill me-2"></i>Confirm Distribution
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <script>
    function openExitModal(exit) {
        document.getElementById('exit_req_id').value = exit.withdrawal_id;
        document.getElementById('exit_member_name').innerText = exit.full_name;
        document.getElementById('exit_amount').innerText = 'KES ' + parseFloat(exit.amount).toLocaleString('en-KE', {minimumFractionDigits:2});
        document.getElementById('exitStatusSelect').value = '';
        togglePayout();
        new bootstrap.Modal(document.getElementById('exitModal')).show();
    }

    function togglePayout() {
        const stat = document.getElementById('exitStatusSelect').value;
        document.getElementById('payoutMethodSection').style.display = (stat === 'approved') ? 'block' : 'none';
    }

    $(document).ready(function() {
        $('#historyTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 8,
            language: { search: '', searchPlaceholder: 'Filter transactions...' },
            dom: '<"d-flex justify-content-between align-items-center mb-3"f>t<"d-flex justify-content-between align-items-center mt-3 px-1"ip>'
        });
        // Style datatable search
        $('.dataTables_filter input').addClass('form-control-enh').css({'width':'220px','font-size':'0.82rem'});
    });

    // Growth Chart
    const ctx = document.getElementById('growthChart').getContext('2d');
    let gradient = ctx.createLinearGradient(0, 0, 0, 160);
    gradient.addColorStop(0, 'rgba(181,244,60,0.35)');
    gradient.addColorStop(1, 'rgba(181,244,60,0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= $jsLabels ?>,
            datasets: [{
                data: <?= $jsData ?>,
                borderColor: '#3d8f1a',
                backgroundColor: gradient,
                fill: true,
                tension: 0.45,
                pointRadius: 0,
                borderWidth: 2.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: {
                callbacks: { label: ctx => ' KES ' + new Intl.NumberFormat('en-KE').format(ctx.raw) }
            }},
            scales: { x: { display: false }, y: { display: false } }
        }
    });
    </script>

    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>