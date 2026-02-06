<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');
// usms/member/pages/transactions.php
// UI THEME: HOPE UI / UMOJA (Slate & Lime)
// Supports Light/Dark Mode via Bootstrap 5.3


// 1. Config & Auth
// Validate Login
require_member();

// Initialize Layout Manager
$layout = LayoutManager::create('member');

$member_id = $_SESSION['member_id'];

// 2. Filter Logic
$type_filter = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
$date_filter = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);

// Base Query
$sql = "SELECT transaction_id, transaction_type, amount, reference_no, created_at, payment_channel, notes 
        FROM transactions 
        WHERE member_id = ? ";
$params = [$member_id];
$types = "i";

// Apply Filters
if ($type_filter) {
    $sql .= " AND transaction_type = ? ";
    $params[] = $type_filter;
    $types .= "s";
}
if ($date_filter) {
    $sql .= " AND DATE(created_at) = ? "; 
    $params[] = $date_filter;
    $types .= "s";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// HANDLE EXPORT
if (isset($_GET['action']) && in_array($_GET['action'], ['export_pdf', 'export_excel', 'print_report'])) {
    require_once __DIR__ . '/../../core/exports/UniversalExportEngine.php';
    
    $format = 'pdf';
    if ($_GET['action'] === 'export_excel') $format = 'excel';
    if ($_GET['action'] === 'print_report') $format = 'print';

    $data = [];
    $result->data_seek(0);
    while($row = $result->fetch_assoc()) {
        $type = strtolower($row['transaction_type'] ?? '');
        $is_wd = ($type == 'withdrawal');
        $sign = $is_wd ? '-' : '+';
        
        $data[] = [
            'Date' => date('d-M-Y H:i', strtotime($row['created_at'])),
            'Type' => ucwords(str_replace('_', ' ', $type)),
            'Reference' => $row['reference_no'],
            'Channel' => strtoupper($row['payment_channel']),
            'Amount' => $sign . ' ' . number_format((float)$row['amount'], 2)
        ];
    }

    UniversalExportEngine::handle($format, $data, [
        'title' => 'Personal Transaction Ledger',
        'module' => 'Member Portal',
        'headers' => ['Date', 'Type', 'Reference', 'Channel', 'Amount']
    ]);
    exit;
}

// 3. KPI Calculations via Financial Engine
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine = new FinancialEngine($conn);
$balances = $engine->getBalances($member_id);

$net_savings = $balances['savings'];
$total_loans = $balances['loans'];
$total_repaid = (float) ($conn->query("SELECT SUM(amount) as total FROM transactions WHERE member_id = $member_id AND transaction_type = 'loan_repayment'")->fetch_assoc()['total'] ?? 0);
$total_withdrawn = (float) ($conn->query("SELECT SUM(amount) as total FROM transactions WHERE member_id = $member_id AND transaction_type = 'withdrawal'")->fetch_assoc()['total'] ?? 0);
$pageTitle = "Transaction Ledger";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" id="htmlTag">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'Umoja Sacco' ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            /* --- LIGHT MODE VARIABLES --- */
            --hope-bg: #f3f4f6;
            --hope-card: #ffffff;
            --hope-text-main: #111827;
            --hope-text-muted: #6b7280;
            --hope-border: #e5e7eb;
            
            /* Brand Colors (Dark Green & Lime) */
            --hope-primary: #063925; /* Umoja Dark Green */
            --hope-accent: #65a30d;  /* Darker lime for text legibility on white */
            --hope-accent-bg: #bef264; /* Bright lime for buttons */
            --hope-accent-text: #063925; /* Text on lime buttons */
            
            /* Status Colors */
            --hope-success-bg: #ecfccb;
            --hope-success-text: #365314;
            --hope-info-bg: #e0f2fe;
            --hope-info-text: #0c4a6e;
        }

        [data-bs-theme="dark"] {
            /* --- DARK MODE VARIABLES (Matches Screenshots) --- */
            --hope-bg: #0f172a;       /* Deep Slate */
            --hope-card: #1e293b;     /* Lighter Slate Card */
            --hope-text-main: #f8fafc;
            --hope-text-muted: #94a3b8;
            --hope-border: #334155;
            
            /* Brand Colors */
            --hope-primary: #bef264;  /* In dark mode, Primary highlight becomes Lime */
            --hope-accent: #bef264;
            --hope-accent-bg: #bef264;
            --hope-accent-text: #0f172a;
            
            /* Status Colors (Adjusted for dark contrast) */
            --hope-success-bg: rgba(190, 242, 100, 0.1);
            --hope-success-text: #bef264;
            --hope-info-bg: rgba(56, 189, 248, 0.1);
            --hope-info-text: #38bdf8;
        }

        body {
            background-color: var(--hope-bg);
            color: var(--hope-text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Layout */
        .main-content-wrapper { margin-left: 280px; min-height: 100vh; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

        /* Custom Card Styling */
        .card-hope {
            background-color: var(--hope-card);
            border: 1px solid var(--hope-border);
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Special Gradient Card for Main Stat */
        .card-hope-gradient {
            background: linear-gradient(135deg, #063925 0%, #0f4e36 100%);
            border: none;
            color: white !important;
        }
        [data-bs-theme="dark"] .card-hope-gradient {
            background: linear-gradient(135deg, rgba(190, 242, 100, 0.1) 0%, rgba(190, 242, 100, 0.02) 100%);
            border: 1px solid rgba(190, 242, 100, 0.2);
            color: var(--hope-accent) !important;
        }

        /* Filter Controls */
        .form-control-hope, .form-select-hope {
            background-color: var(--hope-bg);
            border: 1px solid var(--hope-border);
            color: var(--hope-text-main);
            border-radius: 12px;
            padding: 0.6rem 1rem;
        }
        .form-control-hope:focus, .form-select-hope:focus {
            border-color: var(--hope-accent);
            box-shadow: 0 0 0 2px rgba(190, 242, 100, 0.25);
            background-color: var(--hope-bg);
            color: var(--hope-text-main);
        }

        /* Lime Button */
        .btn-lime {
            background-color: var(--hope-accent-bg);
            color: var(--hope-accent-text);
            font-weight: 700;
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            border: none;
            transition: transform 0.2s;
        }
        .btn-lime:hover {
            transform: translateY(-2px);
            filter: brightness(110%);
            color: var(--hope-accent-text);
        }

        /* Table Styling */
        .table-hope {
            --bs-table-bg: transparent;
            --bs-table-color: var(--hope-text-main);
            --bs-table-border-color: var(--hope-border);
        }
        .table-hope th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--hope-text-muted);
            font-weight: 700;
            padding: 1rem 1.5rem;
            border-bottom-width: 1px;
        }
        .table-hope td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-bottom-width: 1px;
        }
        .table-hope tr:last-child td { border-bottom: 0; }

        /* Icon Box */
        .icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        /* Theme Toggle Button (Floating) */
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--hope-card);
            border: 1px solid var(--hope-border);
            color: var(--hope-text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .theme-toggle:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content-wrapper">
    
    <?php $layout->topbar($pageTitle ?? ''); ?>

    <div class="container-fluid px-4 px-lg-5 py-5">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color: var(--hope-text-main);">Ledger History</h2>
                <p class="mb-0" style="color: var(--hope-text-muted);">View all your deposits, loans, and welfare payments.</p>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle rounded-pill px-4 fw-semibold shadow-sm" data-bs-toggle="dropdown" style="border-color: var(--hope-border); color: var(--hope-text-muted);">
                    <i class="bi bi-download me-2"></i>Export
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_pdf'])) ?>"><i class="bi bi-file-pdf text-danger me-2"></i>Export PDF</a></li>
                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'export_excel'])) ?>"><i class="bi bi-file-excel text-success me-2"></i>Export Excel</a></li>
                    <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['action' => 'print_report'])) ?>" target="_blank"><i class="bi bi-printer text-primary me-2"></i>Print Ledger</a></li>
                </ul>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card-hope card-hope-gradient h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <p class="text-uppercase fw-bold small mb-1 opacity-75">Net Savings</p>
                            <h3 class="fw-bold mb-0">KES <?= number_format((float)$net_savings, 2) ?></h3>
                        </div>
                        <div class="p-2 bg-white bg-opacity-25 rounded-3">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <div class="small opacity-75">
                        <i class="bi bi-arrow-up-circle me-1"></i> Available balance
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card-hope h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <p class="text-uppercase fw-bold small mb-1" style="color: var(--hope-text-muted);">Active Loans</p>
                            <h3 class="fw-bold mb-0" style="color: var(--hope-text-main);">KES <?= number_format((float)($total_loans ?? 0), 2) ?></h3>
                        </div>
                        <div class="icon-box" style="background: var(--hope-info-bg); color: var(--hope-info-text);">
                            <i class="bi bi-bank"></i>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px; background: var(--hope-bg);">
                        <div class="progress-bar bg-info" style="width: 60%"></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card-hope h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <p class="text-uppercase fw-bold small mb-1" style="color: var(--hope-text-muted);">Total Repaid</p>
                            <h3 class="fw-bold mb-0" style="color: var(--hope-accent);">KES <?= number_format((float)($total_repaid ?? 0), 2) ?></h3>
                        </div>
                        <div class="icon-box" style="background: var(--hope-success-bg); color: var(--hope-success-text);">
                            <i class="bi bi-check-lg"></i>
                        </div>
                    </div>
                    <div class="small" style="color: var(--hope-text-muted);">Great track record</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card-hope h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <p class="text-uppercase fw-bold small mb-1" style="color: var(--hope-text-muted);">Withdrawn</p>
                            <h3 class="fw-bold mb-0" style="color: var(--hope-text-main);">KES <?= number_format((float)($total_withdrawn ?? 0), 2) ?></h3>
                        </div>
                        <div class="icon-box bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-arrow-down-left"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-hope p-0 overflow-hidden">
            
            <div class="p-4 border-bottom" style="border-color: var(--hope-border);">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase" style="color: var(--hope-text-muted);">Transaction Type</label>
                        <select name="type" class="form-select form-select-hope">
                            <option value="">All Transactions</option>
                            <option value="deposit" <?= $type_filter === 'deposit' ? 'selected' : '' ?>>Savings Deposit</option>
                            <option value="contribution" <?= $type_filter === 'contribution' ? 'selected' : '' ?>>Contribution</option>
                            <option value="loan_disbursement" <?= $type_filter === 'loan_disbursement' ? 'selected' : '' ?>>Loan Received</option>
                            <option value="loan_repayment" <?= $type_filter === 'loan_repayment' ? 'selected' : '' ?>>Loan Repayment</option>
                            <option value="withdrawal" <?= $type_filter === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase" style="color: var(--hope-text-muted);">Date</label>
                        <input type="date" name="date" class="form-control form-control-hope" value="<?= htmlspecialchars($date_filter ?? '') ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-lime w-100">
                            <i class="bi bi-funnel me-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hope mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Ref No.</th>
                            <th>Channel</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $type = strtolower($row['transaction_type'] ?? '');
                                
                                // Visual Logic
                                $is_in = in_array($type, ['deposit', 'loan_repayment', 'contribution', 'welfare']);
                                $is_loan_out = ($type == 'loan_disbursement'); 
                                $is_wd = ($type == 'withdrawal');

                                if ($is_loan_out) {
                                    $amount_style = 'color: var(--hope-info-text)';
                                    $sign = '+';
                                    $icon = 'bi-bank';
                                    $icon_bg = 'background: var(--hope-info-bg); color: var(--hope-info-text)';
                                } elseif ($is_wd) {
                                    $amount_style = 'color: var(--hope-text-main)';
                                    $sign = '-';
                                    $icon = 'bi-wallet';
                                    $icon_bg = 'background: var(--hope-bg); color: var(--hope-text-muted)';
                                } else {
                                    $amount_style = 'color: var(--hope-accent)';
                                    $sign = '+';
                                    $icon = 'bi-arrow-up-right';
                                    $icon_bg = 'background: var(--hope-success-bg); color: var(--hope-success-text)';
                                }

                                $display_type = ucwords(str_replace('_', ' ', $type));
                                $dt = new DateTime($row['created_at']);
                            ?>
                            <tr>
                                <td style="width: 15%;">
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold" style="color: var(--hope-text-main);"><?= $dt->format('M d, Y') ?></span>
                                        <span class="small" style="color: var(--hope-text-muted);"><?= $dt->format('h:i A') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box" style="<?= $icon_bg ?>">
                                            <i class="bi <?= $icon ?>"></i>
                                        </div>
                                        <div>
                                            <span class="d-block fw-semibold" style="color: var(--hope-text-main);"><?= $display_type ?></span>
                                            <small class="d-block text-truncate" style="max-width: 250px; color: var(--hope-text-muted);">
                                                <?= htmlspecialchars($row['notes'] ?? '-') ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-monospace small px-2 py-1 rounded border" 
                                          style="background: var(--hope-bg); border-color: var(--hope-border) !important; color: var(--hope-text-muted);">
                                        <?= htmlspecialchars($row['reference_no'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge fw-normal" 
                                          style="background: var(--hope-bg); color: var(--hope-text-main); border: 1px solid var(--hope-border);">
                                        <?= strtoupper(htmlspecialchars($row['payment_channel'] ?? 'System')) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold fs-6" style="<?= $amount_style ?>">
                                        <?= $sign ?> KES <?= number_format((float)$row['amount'], 2) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="opacity-50">
                                        <i class="bi bi-inbox display-4" style="color: var(--hope-text-muted);"></i>
                                        <p class="mt-2" style="color: var(--hope-text-muted);">No records found matching filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<div class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme toggle with localStorage persistence
    function toggleTheme() {
        const html = document.getElementById('htmlTag');
        const icon = document.getElementById('themeIcon');
        const current = html.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-bs-theme', next);
        icon.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        
        // Save preference
        localStorage.setItem('theme', next);
    }

    // Load saved theme on page load
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        const html = document.getElementById('htmlTag');
        const icon = document.getElementById('themeIcon');
        
        html.setAttribute('data-bs-theme', savedTheme);
        icon.className = savedTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    });
</script>

</body>
</html>






