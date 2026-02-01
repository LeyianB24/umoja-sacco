<?php
// usms/manager/expenses.php
// Operations Manager - Expense Tracking Console

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Enforce Manager Role
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'manager' && $_SESSION['role'] !== 'superadmin')) {
    redirect_to('../public/login.php');
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Manager';
$db = $conn;

$msg = "";
$msg_type = "";

// =========================================================
// 0. SELF-HEALING: Create Table if Missing
// =========================================================
$table_check = $db->query("SHOW TABLES LIKE 'expenses'");
if ($table_check->num_rows == 0) {
    $create_sql = "CREATE TABLE `expenses` (
      `expense_id` int(11) NOT NULL AUTO_INCREMENT,
      `category` varchar(50) NOT NULL DEFAULT 'operational',
      `amount` decimal(12,2) NOT NULL,
      `expense_date` date NOT NULL,
      `description` text DEFAULT NULL,
      `investment_id` int(11) DEFAULT NULL,
      `recorded_by` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`expense_id`),
      KEY `investment_id` (`investment_id`),
      KEY `recorded_by` (`recorded_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($db->query($create_sql)) {
        $msg = "System setup: 'expenses' table created successfully.";
        $msg_type = "info";
    }
}

// ---------------------------------------------------------
// 1. HANDLE EXPENSE ENTRY
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_expense'])) {
    $amount      = floatval($_POST['amount']);
    $category    = $_POST['category']; 
    $date        = $_POST['expense_date'];
    $desc        = trim($_POST['description']);
    $invest_id   = !empty($_POST['investment_id']) ? intval($_POST['investment_id']) : NULL;
    
    if ($amount <= 0 || empty($desc)) {
        $msg = "Amount and description are required."; $msg_type = "danger";
    } else {
        $stmt = $db->prepare("INSERT INTO expenses (category, amount, expense_date, description, investment_id, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdssii", $category, $amount, $date, $desc, $invest_id, $admin_id);
        
        if ($stmt->execute()) {
            // Log action
            $db->query("INSERT INTO audit_logs (admin_id, action, details) VALUES ($admin_id, 'add_expense', 'Recorded expense: $category - KES $amount')");
            $msg = "Expense recorded successfully."; $msg_type = "success";
        } else {
            $msg = "Error recording expense: " . $db->error; $msg_type = "danger";
        }
    }
}

// ---------------------------------------------------------
// 2. FETCH EXPENSES
// ---------------------------------------------------------
$filter_cat = $_GET['cat'] ?? 'all';
$search_q   = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = "";

if ($filter_cat !== 'all') {
    $where[] = "e.category = ?";
    $params[] = $filter_cat;
    $types .= "s";
}
if ($search_q) {
    $where[] = "(e.description LIKE ? OR i.title LIKE ?)";
    $term = "%$search_q%";
    $params[] = $term; $params[] = $term;
    $types .= "ss";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT e.*, i.title as asset_name, a.username as recorder 
        FROM expenses e 
        LEFT JOIN investments i ON e.investment_id = i.investment_id 
        LEFT JOIN admins a ON e.recorded_by = a.admin_id
        $where_sql 
        ORDER BY e.expense_date DESC LIMIT 100";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$expenses = $stmt->get_result();

// Fetch Investments for Dropdown
$inv_res = $db->query("SELECT investment_id, title FROM investments WHERE status='active'");

// KPIs
$month_total = $db->query("SELECT SUM(amount) as s FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE())")->fetch_assoc()['s'] ?? 0;
$year_total  = $db->query("SELECT SUM(amount) as s FROM expenses WHERE YEAR(expense_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['s'] ?? 0;

function ksh($num) { return number_format((float)$num, 2); }
function getInitials($name) { return strtoupper(substr($name ?? 'M', 0, 1)); }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>Expenses — Manager Console</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0d834b; 
            --secondary-color: #e2b34a;
            --sidebar-width: 260px;
            --header-height: 70px;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f4f7fe; transition: background 0.3s; }
        [data-bs-theme="dark"] body { background-color: #0f1214; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-width); position: fixed; top: 0; left: 0; bottom: 0; z-index: 1000; background: linear-gradient(180deg, #053d25 0%, #022214 100%); padding-top: 1rem; border-right: none; transition: 0.3s; }
        [data-bs-theme="dark"] .sidebar { background: #1b1f22 !important; border-right: 1px solid #2c3035; }
        
        .nav-link { color: rgba(255,255,255,0.7); padding: 0.8rem 1.5rem; display: flex; gap: 12px; font-size: 0.9rem; border-left: 3px solid transparent; text-decoration: none; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); border-left-color: var(--secondary-color); }
        
        /* Layout */
        .main-content { margin-left: var(--sidebar-width); padding: var(--header-height) 1.5rem; min-height: 100vh; transition: margin-left 0.3s; }
        .topbar { height: var(--header-height); position: fixed; top: 0; right: 0; left: var(--sidebar-width); background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); z-index: 999; display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; border-bottom: 1px solid rgba(0,0,0,0.05); transition: left 0.3s, background-color 0.3s; }
        [data-bs-theme="dark"] .topbar { background: rgba(33,37,41,0.8); border-bottom: 1px solid #343a40; }

        /* Cards */
        .card-hd { border: none; border-radius: 12px; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.03); transition: transform 0.2s; }
        [data-bs-theme="dark"] .card-hd { background: #1e2125; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        
        @media (max-width: 991px) { .sidebar { transform: translateX(-100%); } .sidebar.show { transform: translateX(0); } .main-content { margin-left: 0; } .topbar { left: 0; } }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="px-4 pb-4 border-bottom border-white border-opacity-10 mb-3">
        <div class="fw-bold text-white fs-5"><?= SITE_NAME ?></div>
        <div class="text-white-50 small">Operations Manager</div>
    </div>
    <nav>
        <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Command Center</a>
        <a href="loans.php" class="nav-link"><i class="bi bi-check-square"></i> Loan Reviews</a>
        <a href="members.php" class="nav-link"><i class="bi bi-person-check"></i> Member Vetting</a>
        <a href="investments.php" class="nav-link"><i class="bi bi-buildings"></i> Investments</a>
        <a href="expenses.php" class="nav-link active"><i class="bi bi-receipt"></i> Operational Exp.</a>
        <a href="<?= BASE_URL ?>/public/messages.php" class="nav-link"><i class="bi bi-chat-dots"></i> Message Center</a>
        <a href="<?= BASE_URL ?>/public/logout.php" class="nav-link text-warning mt-3"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</aside>

<div class="main-content">
    
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <h5 class="mb-0 fw-bold">Expense Tracking</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link text-secondary" id="themeToggle"><i class="bi bi-moon-stars-fill fs-5"></i></button>
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width:38px;height:38px;font-weight:bold">
                <?= getInitials($admin_name) ?>
            </div>
        </div>
    </header>

    <div class="p-4">
        
        <?php if($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card-hd p-4 mb-4">
                    <h6 class="fw-bold mb-3">Record New Expense</h6>
                    <form method="POST">
                        <input type="hidden" name="record_expense" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Amount (KES)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">KES</span>
                                <input type="number" name="amount" class="form-control fw-bold" step="0.01" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="operational">General Operations</option>
                                <option value="maintenance">Asset Maintenance</option>
                                <option value="fuel">Fuel & Transport</option>
                                <option value="office">Office Supplies</option>
                                <option value="salary">Staff Salary</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Link Asset <span class="fw-normal text-muted">(Optional)</span></label>
                            <select name="investment_id" class="form-select">
                                <option value="">-- None --</option>
                                <?php while($i = $inv_res->fetch_assoc()): ?>
                                    <option value="<?= $i['investment_id'] ?>"><?= htmlspecialchars($i['title']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Receipt No, Details..." required></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success py-2 shadow-sm">Save Record</button>
                        </div>
                    </form>
                </div>

                <div class="card-hd p-4 bg-primary text-white text-center">
                    <small class="text-white-50 text-uppercase fw-bold">This Month's Total</small>
                    <h2 class="fw-bold mt-1 mb-0">KES <?= ksh($month_total) ?></h2>
                    <small class="opacity-75">YTD: KES <?= ksh($year_total) ?></small>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-hd h-100">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-0">Expense Ledger</h6>
                            </div>
                            <div class="col-md-6">
                                <form class="d-flex gap-2">
                                    <select name="cat" class="form-select form-select-sm w-auto">
                                        <option value="all">All Cats</option>
                                        <option value="maintenance" <?= $filter_cat=='maintenance'?'selected':'' ?>>Maintenance</option>
                                        <option value="fuel" <?= $filter_cat=='fuel'?'selected':'' ?>>Fuel</option>
                                    </select>
                                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search_q) ?>">
                                    <button class="btn btn-sm btn-light border"><i class="bi bi-search"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Details</th>
                                    <th>Category</th>
                                    <th class="text-end pe-4">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($expenses->num_rows === 0): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">No expenses recorded yet.</td></tr>
                                <?php else: while($e = $expenses->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= date('M d, Y', strtotime($e['expense_date'])) ?></td>
                                    <td>
                                        <div class="text-dark fw-bold text-truncate" style="max-width: 200px;">
                                            <?= htmlspecialchars($e['description']) ?>
                                        </div>
                                        <?php if($e['asset_name']): ?>
                                            <div class="small text-primary fst-italic"><i class="bi bi-link-45deg"></i> <?= htmlspecialchars($e['asset_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-uppercase" style="font-size:0.7rem">
                                            <?= htmlspecialchars($e['category']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-danger">
                                        -<?= ksh($e['amount']) ?>
                                    </td>
                                </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle
    document.getElementById('sidebarToggle').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('show');
    });

    // Theme Logic
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    const icon = themeToggle.querySelector('i');
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-bs-theme', savedTheme);
    updateIcon(savedTheme);

    themeToggle.addEventListener('click', () => {
        const next = html.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
        updateIcon(next);
    });

    function updateIcon(theme) {
        icon.className = theme === 'dark' ? 'bi bi-sun-fill fs-5' : 'bi bi-moon-stars-fill fs-5';
    }
</script>
</body>
</html>