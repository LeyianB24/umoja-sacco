<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/SupportTicketWidget.php';

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'System Admin');

// Role-based visibility: Filter tickets by assigned_role_id
$my_role_id = (int)($_SESSION['role_id'] ?? 0);
$where_clauses = ["s.status != 'Closed'"];

if ($my_role_id !== 1) {
    $where_clauses[] = "s.assigned_role_id = $my_role_id";
}

$where_sql = "WHERE " . implode(' AND ', $where_clauses);

// 1. Core Counts
$open_tickets = $conn->query("SELECT COUNT(*) AS c FROM support_tickets s $where_sql")->fetch_assoc()['c'] ?? 0;
$today_logs = $conn->query("SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;

// 2. Member Metrics (Active vs Total)
$member_stats = $conn->query("SELECT COUNT(*) as total, SUM(IF(status='active', 1, 0)) as active FROM members")->fetch_assoc();
$total_members = (int)($member_stats['total'] ?? 0);
$active_members = (int)($member_stats['active'] ?? 0);

// 3. Loan Metrics (Exposure & Pending)
$loan_stats = $conn->query("SELECT COUNT(*) as pending, SUM(current_balance) as exposure FROM loans WHERE status IN ('pending', 'approved', 'disbursed')")->fetch_assoc();
$pending_loans = (int)($loan_stats['pending'] ?? 0);
$total_exposure = (float)($loan_stats['exposure'] ?? 0);

// 4. Financial Status (Cash Position)
$cash_position = 0;
if (Auth::can('view_financials') || $my_role_id === 1) {
    $cash_res = $conn->query("SELECT SUM(current_balance) as balance FROM ledger_accounts WHERE category IN ('cash', 'bank', 'mpesa')");
    $cash_position = (float)($cash_res->fetch_assoc()['balance'] ?? 0);
}

// 5. Database Size
$db_size = "N/A";
if ($my_role_id === 1) {
    try {
        $q = $conn->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema=DATABASE()");
        $row = $q->fetch_assoc();
        $db_size = number_format((float)($row['size'] ?? 0), 1);
    } catch (Exception $e) { $db_size = "0.0"; }
}

// 6. Revenue Trend (Last 7 Days) for Chart.js
$revenue_trend = [];
$trend_res = $conn->query("
    SELECT DATE(t.created_at) as date, SUM(e.credit) as revenue 
    FROM ledger_entries e
    JOIN ledger_transactions t ON e.transaction_id = t.transaction_id
    JOIN ledger_accounts a ON e.account_id = a.account_id
    WHERE a.account_type = 'revenue' 
    AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(t.created_at)
    ORDER BY date ASC
");
while ($row = $trend_res->fetch_assoc()) {
    $revenue_trend[$row['date']] = (float)$row['revenue'];
}

// Fill missing days with zero
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D, M j', strtotime($d));
    $chart_data[] = $revenue_trend[$d] ?? 0;
}

// 7. Recent Items
$tickets = $conn->query("SELECT s.*, COALESCE(m.full_name,'Guest') AS sender FROM support_tickets s LEFT JOIN members m ON s.member_id=m.member_id $where_sql ORDER BY s.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../inc/SystemHealthHelper.php';
$health = getSystemHealth($conn);

$pageTitle = "Command Dashboard";
?>
<?php $layout->header($pageTitle); ?>
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

    <style>
        /* ── Enhanced Dashboard Styles ── */
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root {
            --ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* ── Hero Section ── */
        .hp-hero {
            background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
            border-radius: 28px;
            padding: 52px 56px;
            color: white;
            margin-bottom: 36px;
            box-shadow: 0 24px 60px rgba(15, 46, 37, 0.20);
            position: relative;
            overflow: hidden;
            animation: heroSlideIn 0.7s var(--ease-out-expo) both;
        }

        .hp-hero::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
        }

        .hp-hero::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 180px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.03);
            pointer-events: none;
        }

        .hp-hero .hero-grid-accent {
            position: absolute;
            top: 0; right: 0; bottom: 0;
            width: 50%;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        .hp-hero .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .hp-hero .hero-badge .pulse-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #a3e635;
            box-shadow: 0 0 0 0 rgba(163,230,53,0.4);
            animation: pulseDot 2s infinite;
        }

        .hp-hero h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 3rem;
            letter-spacing: -0.5px;
            margin-bottom: 10px;
            line-height: 1.1;
        }

        .hp-hero p {
            opacity: 0.72;
            font-size: 1rem;
            line-height: 1.6;
            max-width: 480px;
        }

        .hero-integrity-card {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-end;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 24px 32px;
        }

        .hero-integrity-card .label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.55;
            margin-bottom: 4px;
        }

        .hero-integrity-card .value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: #a3e635;
        }

        .hero-integrity-card .sub {
            font-size: 11px;
            opacity: 0.4;
            margin-top: 2px;
        }

        /* ── Stat Cards ── */
        .stat-card {
            background: #fff;
            border-radius: 20px;
            padding: 26px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            transition: transform 0.28s var(--ease-out-expo), box-shadow 0.28s var(--ease-out-expo);
            text-decoration: none;
            color: inherit;
            animation: cardFadeUp 0.6s var(--ease-out-expo) both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.12s; }
        .stat-card:nth-child(3) { animation-delay: 0.19s; }
        .stat-card:nth-child(4) { animation-delay: 0.26s; }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.1);
            color: inherit;
            text-decoration: none;
        }

        .stat-card .stat-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .stat-card .stat-body {
            text-align: right;
        }

        .stat-card .stat-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 4px;
        }

        .stat-card .stat-value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }

        .stat-card .stat-value small {
            font-size: 0.85rem;
            font-weight: 600;
            opacity: 0.5;
        }

        /* ── Monitor Card ── */
        .monitor-card {
            background: #fff;
            border-radius: 20px;
            padding: 28px 32px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            text-decoration: none;
            color: inherit;
            display: block;
            transition: box-shadow 0.28s var(--ease-out-expo);
            animation: cardFadeUp 0.6s var(--ease-out-expo) 0.3s both;
        }

        .monitor-card:hover {
            box-shadow: 0 12px 36px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
        }

        .monitor-card .monitor-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f3f4f6;
        }

        .monitor-card .monitor-header h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            margin: 0;
        }

        .monitor-metric {
            padding: 0 24px;
        }

        .monitor-metric:first-child { padding-left: 0; }
        .monitor-metric:last-child { padding-right: 0; border-right: none !important; }

        .monitor-metric .metric-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 6px;
        }

        .monitor-metric .metric-value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: #111827;
            line-height: 1;
        }

        .monitor-metric .progress {
            height: 3px;
            border-radius: 99px;
            background: #f3f4f6;
            margin-top: 10px;
            overflow: visible;
        }

        .monitor-metric .progress-bar {
            border-radius: 99px;
            transition: width 1s var(--ease-out-expo);
        }

        /* ── Table Card ── */
        .inbox-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden;
            animation: cardFadeUp 0.6s var(--ease-out-expo) 0.4s both;
        }

        .inbox-card .inbox-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 28px 20px;
            border-bottom: 1px solid #f3f4f6;
        }

        .inbox-card .inbox-header h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            margin: 0;
            color: #111827;
        }

        .inbox-card .table {
            margin-bottom: 0;
        }

        .inbox-card .table thead th {
            background: #fafafa;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #9ca3af;
            border: none;
            padding: 12px 20px;
        }

        .inbox-card .table tbody td {
            padding: 14px 20px;
            border-color: #f9fafb;
            vertical-align: middle;
        }

        .inbox-card .table tbody tr {
            transition: background 0.15s ease;
        }

        .inbox-card .table tbody tr:hover {
            background: #fafff8;
        }

        .member-avatar {
            width: 34px; height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--forest, #0f2e25) 0%, #1a5c42 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #a3e635;
            font-weight: 700;
            font-size: 12px;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .priority-badge.high   { background: #fef2f2; color: #dc2626; }
        .priority-badge.medium { background: #fffbeb; color: #d97706; }
        .priority-badge.low    { background: #eff6ff; color: #2563eb; }

        .priority-badge::before {
            content: '';
            width: 5px; height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        /* ── Management Hub ── */
        .hub-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            color: #111827;
            margin-bottom: 16px;
        }

        .nav-pill-enhanced {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 14px;
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            font-size: 0.9rem;
            background: #fff;
            border: 1px solid rgba(0,0,0,0.06);
            margin-bottom: 8px;
            transition: all 0.22s var(--ease-out-expo);
            animation: cardFadeUp 0.5s var(--ease-out-expo) both;
        }

        .nav-pill-enhanced:nth-child(1) { animation-delay: 0.45s; }
        .nav-pill-enhanced:nth-child(2) { animation-delay: 0.50s; }
        .nav-pill-enhanced:nth-child(3) { animation-delay: 0.55s; }
        .nav-pill-enhanced:nth-child(4) { animation-delay: 0.60s; }
        .nav-pill-enhanced:nth-child(5) { animation-delay: 0.65s; }
        .nav-pill-enhanced:nth-child(6) { animation-delay: 0.70s; }

        .nav-pill-enhanced:hover {
            transform: translateX(4px);
            background: #f9fffe;
            border-color: rgba(15,46,37,0.12);
            box-shadow: 0 4px 16px rgba(15,46,37,0.08);
            color: var(--forest, #0f2e25);
            text-decoration: none;
        }

        .nav-pill-enhanced .pill-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            background: #f3f4f6;
            transition: background 0.22s ease, color 0.22s ease;
        }

        .nav-pill-enhanced:hover .pill-icon {
            background: rgba(15,46,37,0.08);
            color: var(--forest, #0f2e25);
        }

        .nav-pill-enhanced .pill-arrow {
            margin-left: auto;
            opacity: 0;
            transform: translateX(-4px);
            transition: all 0.22s var(--ease-out-expo);
            font-size: 0.8rem;
        }

        .nav-pill-enhanced:hover .pill-arrow {
            opacity: 0.4;
            transform: translateX(0);
        }

        /* ── Status Badge ── */
        .status-badge-nominal {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
            font-size: 11.5px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
        }

        .status-badge-danger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            font-size: 11.5px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
        }

        /* ── Animations ── */
        @keyframes heroSlideIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes cardFadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulseDot {
            0%   { box-shadow: 0 0 0 0 rgba(163,230,53,0.5); }
            70%  { box-shadow: 0 0 0 7px rgba(163,230,53,0); }
            100% { box-shadow: 0 0 0 0 rgba(163,230,53,0); }
        }

        /* ── Section label ── */
        .section-eyebrow {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 14px;
        }

        /* ── Divider ── */
        .monitor-divider {
            border-right: 1px solid #f0f0f0;
        }

        @media (max-width: 768px) {
            .hp-hero { padding: 32px 28px; }
            .hp-hero h1 { font-size: 2.2rem; }
            .monitor-metric { padding: 0 12px; }
        }
    </style>

    <!-- ─── HERO ─────────────────────────────────────────── -->
    <div class="hp-hero">
        <div class="hero-grid-accent"></div>
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="hero-badge">
                    <span class="pulse-dot"></span>
                    System Online
                </div>
                <h1>Hello, <?= explode(' ', $admin_name)[0] ?>.</h1>
                <p>Everything is running smoothly. The Sacco's financial heart is beating at <strong style="color:#a3e635;opacity:1;">100% precision</strong>.</p>
                <a href="<?= BASE_URL ?>/admin/pages/loans_reviews.php" class="btn btn-lime rounded-pill px-4 py-2 fw-bold shadow-sm mt-2" style="font-size:0.9rem;">
                    <i class="bi bi-clock-history me-2"></i> Review Pending Loans
                </a>
            </div>
            <div class="col-md-5 text-end d-none d-lg-block">
                <div class="hero-integrity-card">
                    <div class="label">Ledger Integrity</div>
                    <div class="value">ACID Verified</div>
                    <div class="sub">Double-entry balanced · Real-time</div>
                </div>
            </div>
        </div>
    </div>

    <?php render_support_ticket_widget($conn, ['general', 'technical'], 'General & Technical'); ?>

    <!-- ─── STAT CARDS ───────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/admin/pages/members.php" class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-label">Total Members</div>
                    <div class="stat-value"><?= number_format($total_members) ?></div>
                    <div style="font-size:10px; color:#16a34a; font-weight:700;"><?= $active_members ?> Active</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/admin/pages/loans_reviews.php" class="stat-card">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-label">Loan Exposure</div>
                    <div class="stat-value"><?= number_format($total_exposure / 1000, 1) ?><small>K</small></div>
                    <div style="font-size:10px; color:#d97706; font-weight:700;"><?= $pending_loans ?> Pending</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/admin/pages/revenue.php" class="stat-card">
                <div class="stat-icon" style="background:rgba(15,46,37,0.08);color:var(--forest,#0f2e25);">
                    <i class="bi bi-bank2"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-label">Cash Position</div>
                    <div class="stat-value"><?= number_format($cash_position / 1000, 1) ?><small>K</small></div>
                    <div style="font-size:10px; color:#9ca3af; font-weight:600;">Liquid Assets</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/admin/pages/live_monitor.php" class="stat-card">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-cpu-fill"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-label">DB Storage</div>
                    <div class="stat-value"><?= $db_size ?><small>MB</small></div>
                    <div style="font-size:10px; color:#0891b2; font-weight:700;">System Optimized</div>
                </div>
            </a>
        </div>
    </div>

    <!-- ─── REVENUE TREND ─────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-lg-12">
            <div class="inbox-card shadow-sm" style="padding:28px;">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h5 class="fw-800 mb-1" style="font-family:'Plus Jakarta Sans';">Revenue Performance</h5>
                        <p class="text-muted mb-0" style="font-size:0.85rem;">Daily income generation from all revenue streams</p>
                    </div>
                    <div class="text-end">
                        <div class="badge-count px-3 py-2" style="background:rgba(163,230,53,0.1); color:#1a3a2a; border-radius:12px; font-weight:700; font-size:0.8rem;">
                            7-Day Trend Analysis
                        </div>
                    </div>
                </div>
                <div style="height:320px; position:relative;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── LIVE MONITOR ─────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <a href="<?= BASE_URL ?>/admin/pages/live_monitor.php" class="monitor-card">
                <div class="monitor-header">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:8px;height:8px;border-radius:50%;background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,0.2);animation:pulseDot 2s infinite;"></div>
                        <h5>Live Operations Monitor</h5>
                        <span class="text-muted" style="font-size:0.82rem;font-weight:400;">Real-time payment gateways &amp; notification engines</span>
                    </div>
                    <?php if ($health['ledger_imbalance']): ?>
                        <span class="status-badge-danger"><i class="bi bi-exclamation-triangle-fill"></i> Ledger Imbalance</span>
                    <?php else: ?>
                        <span class="status-badge-nominal"><i class="bi bi-check-circle-fill"></i> All Systems Nominal</span>
                    <?php endif; ?>
                </div>
                <div class="row g-0">
                    <div class="col-md-3 monitor-metric monitor-divider">
                        <div class="metric-label">Callback Success Rate</div>
                        <div class="metric-value"><?= $health['callback_success_rate'] ?><span style="font-size:1rem;font-weight:500;opacity:0.4;">%</span></div>
                        <div class="progress mt-2" style="height:3px;">
                            <div class="progress-bar bg-success" style="width:<?= $health['callback_success_rate'] ?>%;"></div>
                        </div>
                    </div>
                    <div class="col-md-3 monitor-metric monitor-divider">
                        <div class="metric-label">Pending STK (&gt;5m)</div>
                        <div class="metric-value <?= $health['pending_transactions'] > 0 ? 'text-warning' : '' ?>">
                            <?= $health['pending_transactions'] ?>
                            <span style="font-size:0.9rem;font-weight:500;opacity:0.45;">txns</span>
                        </div>
                    </div>
                    <div class="col-md-3 monitor-metric monitor-divider">
                        <div class="metric-label">Failed Comms (Today)</div>
                        <div class="metric-value <?= $health['failed_notifications'] > 0 ? 'text-danger' : '' ?>">
                            <?= $health['failed_notifications'] ?>
                            <span style="font-size:0.9rem;font-weight:500;opacity:0.45;">alerts</span>
                        </div>
                    </div>
                    <div class="col-md-3 monitor-metric">
                        <div class="metric-label">Daily Volume</div>
                        <div class="metric-value">
                            <span style="font-size:0.85rem;font-weight:600;opacity:0.4;">KES</span>
                            <?= number_format($health['daily_volume']) ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- ─── INBOX + HUB ──────────────────────────────────── -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="inbox-card">
                <div class="inbox-header">
                    <h5><i class="bi bi-inbox-fill me-2" style="color:var(--forest,#0f2e25);opacity:0.7;"></i>Active Support Inbox</h5>
                    <a href="<?= BASE_URL ?>/admin/pages/support.php" class="btn btn-sm rounded-pill px-4 fw-bold" style="background:#f3f4f6;color:#374151;border:none;font-size:0.82rem;">
                        Open Support Center <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table inbox-card .table mb-0">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Issue</th>
                                <th>Priority</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tickets as $t): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="member-avatar"><?= strtoupper(substr($t['sender'], 0, 1)) ?></div>
                                        <div>
                                            <div class="fw-semibold" style="font-size:0.9rem;color:#111827;"><?= htmlspecialchars($t['sender']) ?></div>
                                            <div style="font-size:0.78rem;color:#9ca3af;"><?= time_ago($t['created_at']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:0.88rem;color:#374151;max-width:200px;">
                                    <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($t['subject']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $p = strtolower($t['priority']); ?>
                                    <span class="priority-badge <?= $p ?>">
                                        <?= strtoupper($t['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/pages/support_view.php?id=<?= $t['support_id'] ?>" class="btn btn-sm btn-forest rounded-pill px-3" style="font-size:0.8rem;">
                                        Reply <i class="bi bi-reply ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5" style="color:#9ca3af;">
                                    <i class="bi bi-inbox d-block" style="font-size:2rem;margin-bottom:8px;opacity:0.3;"></i>
                                    No open tickets at the moment
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="hub-title">Management Hub</div>

            <?php if (Auth::can('members.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/members.php" class="nav-pill-enhanced">
                <div class="pill-icon"><i class="bi bi-people-fill"></i></div>
                <span>Member Directory</span>
                <i class="bi bi-chevron-right pill-arrow"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('loans_reviews.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/loans_reviews.php" class="nav-pill-enhanced">
                <div class="pill-icon"><i class="bi bi-cash-stack"></i></div>
                <span>Loan Review Desk</span>
                <i class="bi bi-chevron-right pill-arrow"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('reports.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/reports.php" class="nav-pill-enhanced">
                <div class="pill-icon"><i class="bi bi-bar-chart-fill"></i></div>
                <span>Analytics &amp; Reports</span>
                <i class="bi bi-chevron-right pill-arrow"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('settings.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/settings.php" class="nav-pill-enhanced">
                <div class="pill-icon"><i class="bi bi-shield-lock-fill"></i></div>
                <span>System Security</span>
                <i class="bi bi-chevron-right pill-arrow"></i>
            </a>
            <?php endif; ?>

            <?php if (Auth::can('live_monitor.php') || $my_role_id === 1): ?>
            <a href="<?= BASE_URL ?>/admin/pages/live_monitor.php" class="nav-pill-enhanced">
                <div class="pill-icon text-danger"><i class="bi bi-broadcast"></i></div>
                <span>Operations &amp; Health</span>
                <i class="bi bi-chevron-right pill-arrow"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ── Revenue Trend Chart ──
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Create gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(163, 230, 53, 0.2)');
    gradient.addColorStop(1, 'rgba(163, 230, 53, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Revenue (KES)',
                data: <?= json_encode($chart_data) ?>,
                borderColor: '#a3e635',
                borderWidth: 3,
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#a3e635',
                pointBorderWidth: 2,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#a3e635',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#111827',
                    titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: '700' },
                    bodyFont: { family: 'Plus Jakarta Sans', size: 14, weight: '600' },
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'KES ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                        color: '#9ca3af'
                    }
                },
                y: {
                    grid: { color: '#f3f4f6', borderDash: [5, 5] },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
                        color: '#9ca3af',
                        callback: function(value) {
                            if (value >= 1000) return (value / 1000) + 'K';
                            return value;
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>