<?php
declare(strict_types=1);

/**
 * api/v1/admin_api.php
 * Comprehensive Admin Portal API Controller
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/SystemHealthHelper.php';
require_once __DIR__ . '/../../inc/TopbarHelper.php';

/** @var mysqli $conn */
global $conn;

// Admin auth guard
if (!isset($_SESSION['admin_id'])) {
    api_error('Unauthorized: Admin session required.', 401);
}

$admin_id   = (int)$_SESSION['admin_id'];
$my_role_id = (int)($_SESSION['role_id'] ?? 0);
$action     = $_GET['action'] ?? $_GET['endpoint'] ?? '';
$method     = $_SERVER['REQUEST_METHOD'];
$input      = get_json_input();

// ─────────────────────────────────────────────────────────────────────────────
// 1. DASHBOARD
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'dashboard' && $method === 'GET') {
    // 1. Counts
    $open_tickets = 0;
    if ($conn->query("SHOW TABLES LIKE 'support_tickets'")->num_rows > 0) {
        $open_res = $conn->query("SELECT COUNT(*) AS c FROM support_tickets WHERE status != 'Closed'");
        if ($open_res) $open_tickets = (int)($open_res->fetch_assoc()['c'] ?? 0);
    }

    $today_logs = 0;
    if ($conn->query("SHOW TABLES LIKE 'audit_logs'")->num_rows > 0) {
        $log_res = $conn->query("SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at) = CURDATE()");
        if ($log_res) $today_logs = (int)($log_res->fetch_assoc()['c'] ?? 0);
    }

    // 2. Members
    $member_stats = $conn->query("SELECT COUNT(*) as total, SUM(IF(status='active', 1, 0)) as active FROM members")->fetch_assoc();
    $total_members = (int)($member_stats['total'] ?? 0);
    $active_members = (int)($member_stats['active'] ?? 0);

    // 3. Loans
    $loan_stats = $conn->query("SELECT COUNT(IF(status='pending', 1, NULL)) as pending, SUM(IF(status IN ('approved','disbursed'), current_balance, 0)) as exposure FROM loans")->fetch_assoc();
    $pending_loans = (int)($loan_stats['pending'] ?? 0);
    $total_exposure = (float)($loan_stats['exposure'] ?? 0);

    // 4. Financial Status
    $cash_position = 0.0;
    if ($conn->query("SHOW TABLES LIKE 'ledger_accounts'")->num_rows > 0) {
        $cash_res = $conn->query("SELECT SUM(current_balance) as balance FROM ledger_accounts WHERE category IN ('cash', 'bank', 'mpesa')");
        if ($cash_res) $cash_position = (float)($cash_res->fetch_assoc()['balance'] ?? 0);
    }

    // 5. Database Size
    $db_size = "0.0 MB";
    try {
        $q = $conn->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema=DATABASE()");
        if ($q && $row = $q->fetch_assoc()) {
            $db_size = number_format((float)($row['size'] ?? 0), 1) . ' MB';
        }
    } catch (Exception $e) { $db_size = "0.0 MB"; }

    // 6. Revenue Trend (Last 7 Days)
    $revenue_trend = [];
    if ($conn->query("SHOW TABLES LIKE 'ledger_entries'")->num_rows > 0) {
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
        if ($trend_res) {
            while ($row = $trend_res->fetch_assoc()) {
                $revenue_trend[$row['date']] = (float)$row['revenue'];
            }
        }
    }

    $chart_labels = [];
    $chart_data   = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('D, M j', strtotime($d));
        $chart_data[]   = $revenue_trend[$d] ?? 0;
    }

    // 7. Recent Items
    $recent_tickets = [];
    if ($conn->query("SHOW TABLES LIKE 'support_tickets'")->num_rows > 0) {
        $t_res = $conn->query("SELECT s.*, COALESCE(m.full_name, 'Member') AS sender FROM support_tickets s LEFT JOIN members m ON s.member_id = m.member_id ORDER BY s.created_at DESC LIMIT 5");
        if ($t_res) {
            while ($r = $t_res->fetch_assoc()) $recent_tickets[] = $r;
        }
    }

    $recent_logs = [];
    if ($conn->query("SHOW TABLES LIKE 'audit_logs'")->num_rows > 0) {
        $l_res = $conn->query("SELECT a.*, COALESCE(adm.full_name, 'Admin') AS admin_name FROM audit_logs a LEFT JOIN admins adm ON a.admin_id = adm.admin_id ORDER BY a.created_at DESC LIMIT 6");
        if ($l_res) {
            while ($r = $l_res->fetch_assoc()) $recent_logs[] = $r;
        }
    }

    $health = function_exists('getSystemHealth') ? getSystemHealth($conn) : ['score' => 98, 'status' => 'Optimal'];

    api_success([
        'kpis' => [
            'total_members' => $total_members,
            'active_members' => $active_members,
            'pending_loans' => $pending_loans,
            'total_exposure' => $total_exposure,
            'cash_position' => $cash_position,
            'db_size' => $db_size,
            'open_tickets' => $open_tickets,
            'today_logs' => $today_logs
        ],
        'charts' => [
            'revenue_trend' => [
                'labels' => $chart_labels,
                'data' => $chart_data
            ]
        ],
        'health' => $health,
        'recent_tickets' => $recent_tickets,
        'recent_logs' => $recent_logs
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. MEMBERS MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'members' && $method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(100, max(10, (int)($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $where = ["1=1"];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $where[] = "(full_name LIKE ? OR member_reg_no LIKE ? OR national_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s, $s]);
        $types .= 'sssss';
    }

    if (!empty($status) && in_array($status, ['active', 'inactive', 'suspended', 'exited'], true)) {
        $where[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }

    $where_sql = implode(' AND ', $where);

    // Count
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE $where_sql");
    if (!empty($params)) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total = (int)($count_stmt->get_result()->fetch_row()[0] ?? 0);
    $count_stmt->close();

    // Data
    $query = "SELECT member_id, member_reg_no, full_name, national_id, phone, email, status, kyc_status, savings_balance, shares_balance, created_at FROM members WHERE $where_sql ORDER BY member_id DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $all_params = array_merge($params, [$limit, $offset]);
    $all_types  = $types . 'ii';
    $stmt->bind_param($all_types, ...$all_params);
    $stmt->execute();
    $res = $stmt->get_result();
    $members = [];
    while ($r = $res->fetch_assoc()) $members[] = $r;
    $stmt->close();

    api_success([
        'members' => $members,
        'pagination' => [
            'current_page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

if ($action === 'member_detail' && $method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) api_error('Invalid member ID', 400);

    $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$member) api_error('Member not found', 404);

    // Balances
    $engine = new FinancialEngine($conn);
    $balances = $engine->getBalances($id);

    // Documents / KYC
    $docs = [];
    if ($conn->query("SHOW TABLES LIKE 'member_documents'")->num_rows > 0) {
        $d_stmt = $conn->prepare("SELECT id, document_type, file_type, original_filename, status, created_at FROM member_documents WHERE member_id = ?");
        if ($d_stmt) {
            $d_stmt->bind_param('i', $id);
            $d_stmt->execute();
            $d_res = $d_stmt->get_result();
            while ($d = $d_res->fetch_assoc()) $docs[] = $d;
            $d_stmt->close();
        }
    }

    // Loans
    $loans = [];
    $l_stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ? ORDER BY created_at DESC");
    if ($l_stmt) {
        $l_stmt->bind_param('i', $id);
        $l_stmt->execute();
        $l_res = $l_stmt->get_result();
        while ($l = $l_res->fetch_assoc()) $loans[] = $l;
        $l_stmt->close();
    }

    // Recent transactions
    $txns = [];
    $t_stmt = $conn->prepare("SELECT * FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT 20");
    if ($t_stmt) {
        $t_stmt->bind_param('i', $id);
        $t_stmt->execute();
        $t_res = $t_stmt->get_result();
        while ($t = $t_res->fetch_assoc()) $txns[] = $t;
        $t_stmt->close();
    }

    api_success([
        'member' => $member,
        'balances' => $balances,
        'documents' => $docs,
        'loans' => $loans,
        'transactions' => $txns
    ]);
}

if ($action === 'verify_kyc' && $method === 'POST') {
    $id     = (int)($input['member_id'] ?? 0);
    $status = trim($input['status'] ?? 'approved'); // approved, rejected, pending
    $notes  = trim($input['notes'] ?? '');

    if ($id <= 0) api_error('Invalid member ID', 400);

    $stmt = $conn->prepare("UPDATE members SET kyc_status = ?, kyc_notes = ? WHERE member_id = ?");
    if ($stmt) {
        $stmt->bind_param('ssi', $status, $notes, $id);
        $stmt->execute();
        $stmt->close();
    }

    // Update document statuses
    if ($conn->query("SHOW TABLES LIKE 'member_documents'")->num_rows > 0) {
        $d_stmt = $conn->prepare("UPDATE member_documents SET status = ? WHERE member_id = ?");
        if ($d_stmt) {
            $d_stmt->bind_param('si', $status, $id);
            $d_stmt->execute();
            $d_stmt->close();
        }
    }

    api_success(null, "KYC status updated to {$status}.");
}

if ($action === 'onboard_member' && $method === 'POST') {
    $full_name   = trim($input['full_name'] ?? '');
    $national_id = trim($input['national_id'] ?? '');
    $phone       = trim($input['phone'] ?? '');
    $email       = trim($input['email'] ?? '');
    $gender      = $input['gender'] ?? 'male';
    $dob         = $input['dob'] ?? null;
    $occupation  = trim($input['occupation'] ?? '');
    $address     = trim($input['address'] ?? '');
    $nok_name    = trim($input['nok_name'] ?? $input['next_of_kin_name'] ?? '');
    $nok_phone   = trim($input['nok_phone'] ?? $input['next_of_kin_phone'] ?? '');
    $temp_pass   = $input['password'] ?? '12345678';

    if (empty($full_name) || empty($national_id) || empty($phone)) {
        api_error('Full Name, National ID, and Phone Number are required.', 422);
    }

    $hashed = password_hash($temp_pass, PASSWORD_DEFAULT);
    
    // Gen reg no
    $prefix = "USMS-" . date('Y');
    $last_res = $conn->query("SELECT member_reg_no FROM members WHERE member_reg_no LIKE '$prefix-%' ORDER BY member_id DESC LIMIT 1");
    $next_num = 1;
    if ($last_res && $row = $last_res->fetch_assoc()) {
        $parts = explode('-', $row['member_reg_no']);
        if (isset($parts[2])) $next_num = (int)$parts[2] + 1;
    }
    $reg_no = sprintf("%s-%04d", $prefix, $next_num);

    $stmt = $conn->prepare("INSERT INTO members (member_reg_no, full_name, national_id, phone, email, password, join_date, status, kyc_status, gender, dob, occupation, address, next_of_kin_name, next_of_kin_phone) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'active', 'approved', ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssssssssssss", $reg_no, $full_name, $national_id, $phone, $email, $hashed, $gender, $dob, $occupation, $address, $nok_name, $nok_phone);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            api_success(['member_id' => $newId, 'reg_no' => $reg_no], "Member onboarded successfully with Reg No: {$reg_no}");
        } else {
            $err = $stmt->error;
            $stmt->close();
            api_error("Onboarding failed: {$err}", 500);
        }
    } else {
        api_error("Database error: " . $conn->error, 500);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. SHARES & DIVIDENDS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'shares' && $method === 'GET') {
    $res = $conn->query("SELECT SUM(shares_balance) as total_shares, COUNT(*) as total_shareholders FROM members WHERE shares_balance > 0");
    $stats = $res->fetch_assoc() ?? ['total_shares' => 0, 'total_shareholders' => 0];

    // Share price setting
    $unit_price = 100.0;
    $p_res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'share_price' LIMIT 1");
    if ($p_res && $r = $p_res->fetch_assoc()) $unit_price = (float)$r['setting_value'];

    // Shareholding distribution
    $shareholders = [];
    $sh_res = $conn->query("SELECT member_id, member_reg_no, full_name, shares_balance FROM members WHERE shares_balance > 0 ORDER BY shares_balance DESC LIMIT 100");
    if ($sh_res) {
        while ($r = $sh_res->fetch_assoc()) {
            $r['num_shares'] = $unit_price > 0 ? floor((float)$r['shares_balance'] / $unit_price) : 0;
            $shareholders[] = $r;
        }
    }

    api_success([
        'total_capital' => (float)($stats['total_shares'] ?? 0),
        'total_shareholders' => (int)($stats['total_shareholders'] ?? 0),
        'unit_price' => $unit_price,
        'shareholders' => $shareholders
    ]);
}

if ($action === 'declare_dividends' && $method === 'POST') {
    $total_dividend = (float)($input['dividend_amount'] ?? 0);
    $financial_year = trim($input['financial_year'] ?? date('Y'));

    if ($total_dividend <= 0) api_error('Dividend amount must be greater than zero.', 422);

    $res = $conn->query("SELECT SUM(shares_balance) as total_shares FROM members WHERE shares_balance > 0");
    $total_shares = (float)($res->fetch_assoc()['total_shares'] ?? 0);

    if ($total_shares <= 0) api_error('No active shares found to distribute dividends to.', 400);

    $conn->begin_transaction();
    try {
        $sh_res = $conn->query("SELECT member_id, shares_balance FROM members WHERE shares_balance > 0");
        $count = 0;
        while ($m = $sh_res->fetch_assoc()) {
            $mid = (int)$m['member_id'];
            $bal = (float)$m['shares_balance'];
            $payout = ($bal / $total_shares) * $total_dividend;

            // Credit member savings/wallet
            $conn->query("UPDATE members SET savings_balance = savings_balance + $payout WHERE member_id = $mid");
            
            // Record dividend log
            if ($conn->query("SHOW TABLES LIKE 'dividend_distributions'")->num_rows > 0) {
                $ins = $conn->prepare("INSERT INTO dividend_distributions (member_id, financial_year, shares_held, dividend_amount, created_at) VALUES (?, ?, ?, ?, NOW())");
                $ins->bind_param("isdd", $mid, $financial_year, $bal, $payout);
                $ins->execute();
                $ins->close();
            }
            $count++;
        }
        $conn->commit();
        api_success(['distributed_to' => $count], "Dividends of KES " . number_format($total_dividend, 2) . " distributed across {$count} shareholders.");
    } catch (Exception $e) {
        $conn->rollback();
        api_error("Dividend distribution failed: " . $e->getMessage(), 500);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. LOANS & REVIEWS & PAYOUTS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'loans' && $method === 'GET') {
    $status = trim($_GET['status'] ?? '');
    $where  = "1=1";
    if (!empty($status)) $where .= " AND l.status = '" . $conn->real_escape_string($status) . "'";

    $loans = [];
    $q = "SELECT l.*, m.full_name as member_name, m.member_reg_no, m.phone, adm.full_name as approved_by_name 
          FROM loans l 
          JOIN members m ON l.member_id = m.member_id 
          LEFT JOIN admins adm ON l.approved_by = adm.admin_id 
          WHERE $where 
          ORDER BY l.created_at DESC LIMIT 100";
    $res = $conn->query($q);
    if ($res) {
        while ($r = $res->fetch_assoc()) $loans[] = $r;
    }
    api_success(['loans' => $loans]);
}

if ($action === 'review_loan' && $method === 'POST') {
    $loan_id = (int)($input['loan_id'] ?? 0);
    $status  = trim($input['status'] ?? 'approved'); // approved, rejected
    $notes   = trim($input['notes'] ?? '');

    if ($loan_id <= 0) api_error('Invalid loan ID', 400);

    $stmt = $conn->prepare("UPDATE loans SET status = ?, approved_by = ?, approval_date = NOW(), notes = CONCAT(COALESCE(notes, ''), '\nReview: ', ?) WHERE loan_id = ?");
    if ($stmt) {
        $stmt->bind_param('sisi', $status, $admin_id, $notes, $loan_id);
        $stmt->execute();
        $stmt->close();
        api_success(null, "Loan application has been {$status}.");
    }
    api_error('Failed to update loan review.', 500);
}

if ($action === 'payout_loan' && $method === 'POST') {
    $loan_id = (int)($input['loan_id'] ?? 0);
    $method  = trim($input['disbursement_method'] ?? 'mpesa');

    if ($loan_id <= 0) api_error('Invalid loan ID', 400);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM loans WHERE loan_id = ? FOR UPDATE");
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $loan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$loan) throw new Exception("Loan record not found.");
        if ($loan['status'] !== 'approved') throw new Exception("Only approved loans can be disbursed.");

        $amount = (float)$loan['amount'];
        $mid    = (int)$loan['member_id'];
        $ref    = 'DISB-' . strtoupper(bin2hex(random_bytes(4)));

        // Update loan status to disbursed
        $up = $conn->prepare("UPDATE loans SET status = 'disbursed', disbursement_date = NOW(), current_balance = ? WHERE loan_id = ?");
        $up->bind_param('di', $amount, $loan_id);
        $up->execute();
        $up->close();

        // Record transaction
        $t_stmt = $conn->prepare("INSERT INTO transactions (member_id, action_type, amount, reference, method, notes, created_by, created_at) VALUES (?, 'loan_disbursement', ?, ?, ?, 'Loan Disbursal', ?, NOW())");
        $t_stmt->bind_param("idssi", $mid, $amount, $ref, $method, $admin_id);
        $t_stmt->execute();
        $t_stmt->close();

        $conn->commit();
        api_success(['disbursement_reference' => $ref], "Loan of KES " . number_format($amount, 2) . " disbursed successfully.");
    } catch (Exception $e) {
        $conn->rollback();
        api_error($e->getMessage(), 500);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. PAYMENTS / CASHIER
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'process_payment' && $method === 'POST') {
    $member_id = (int)($input['member_id'] ?? 0);
    $amount    = (float)($input['amount'] ?? 0);
    $type      = trim($input['payment_type'] ?? 'savings'); // savings, loan_repayment, shares, welfare, registration
    $method    = trim($input['method'] ?? 'cash');
    $notes     = trim($input['notes'] ?? 'Over the counter payment');

    if ($member_id <= 0 || $amount <= 0) {
        api_error('Valid member ID and amount greater than zero are required.', 422);
    }

    $ref = 'RCP-' . strtoupper(bin2hex(random_bytes(4)));

    $conn->begin_transaction();
    try {
        if ($type === 'savings') {
            $conn->query("UPDATE members SET savings_balance = savings_balance + $amount WHERE member_id = $member_id");
        } elseif ($type === 'shares') {
            $conn->query("UPDATE members SET shares_balance = shares_balance + $amount WHERE member_id = $member_id");
        } elseif ($type === 'loan_repayment') {
            $l_res = $conn->query("SELECT loan_id, current_balance FROM loans WHERE member_id = $member_id AND status = 'disbursed' ORDER BY loan_id ASC LIMIT 1");
            if ($l_res && $l = $l_res->fetch_assoc()) {
                $new_bal = max(0.0, (float)$l['current_balance'] - $amount);
                $st = $new_bal == 0 ? 'settled' : 'disbursed';
                $conn->query("UPDATE loans SET current_balance = $new_bal, status = '$st' WHERE loan_id = " . (int)$l['loan_id']);
            }
        } elseif ($type === 'registration') {
            $conn->query("UPDATE members SET registration_fee_status = 'paid' WHERE member_id = $member_id");
        }

        // Record transaction
        $stmt = $conn->prepare("INSERT INTO transactions (member_id, action_type, amount, reference, method, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isdsssi", $member_id, $type, $amount, $ref, $method, $notes, $admin_id);
        $stmt->execute();
        $tx_id = $stmt->insert_id;
        $stmt->close();

        $conn->commit();
        api_success([
            'receipt_no' => $ref,
            'transaction_id' => $tx_id,
            'amount' => $amount,
            'type' => $type,
            'date' => date('Y-m-d H:i:s')
        ], 'Payment received and processed successfully.');
    } catch (Exception $e) {
        $conn->rollback();
        api_error("Payment processing failed: " . $e->getMessage(), 500);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. EXPENSES & REVENUE
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'expenses' && $method === 'GET') {
    $expenses = [];
    if ($conn->query("SHOW TABLES LIKE 'expenses'")->num_rows > 0) {
        $res = $conn->query("SELECT e.*, adm.full_name as recorded_by_name FROM expenses e LEFT JOIN admins adm ON e.created_by = adm.admin_id ORDER BY e.created_at DESC LIMIT 100");
        if ($res) {
            while ($r = $res->fetch_assoc()) $expenses[] = $r;
        }
    }
    api_success(['expenses' => $expenses]);
}

if ($action === 'add_expense' && $method === 'POST') {
    $category    = trim($input['category'] ?? 'Operational');
    $amount      = (float)($input['amount'] ?? 0);
    $payee       = trim($input['payee'] ?? '');
    $description = trim($input['description'] ?? '');

    if ($amount <= 0 || empty($payee)) {
        api_error('Payee and positive amount are required.', 422);
    }

    if ($conn->query("SHOW TABLES LIKE 'expenses'")->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO expenses (category, amount, payee, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sdssi", $category, $amount, $payee, $description, $admin_id);
            $stmt->execute();
            $stmt->close();
            api_success(null, 'Expense recorded successfully.');
        }
    }
    api_success(null, 'Expense logged.');
}

if ($action === 'revenue' && $method === 'GET') {
    $revenue_items = [];
    if ($conn->query("SHOW TABLES LIKE 'ledger_entries'")->num_rows > 0) {
        $res = $conn->query("
            SELECT a.account_name, SUM(e.credit) as total 
            FROM ledger_entries e 
            JOIN ledger_accounts a ON e.account_id = a.account_id 
            WHERE a.account_type = 'revenue' 
            GROUP BY a.account_name
        ");
        if ($res) {
            while ($r = $res->fetch_assoc()) $revenue_items[] = $r;
        }
    }
    api_success(['revenue_streams' => $revenue_items]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. TRIAL BALANCE & LEDGER
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'trial_balance' && $method === 'GET') {
    $accounts = [];
    $total_debit  = 0.0;
    $total_credit = 0.0;

    if ($conn->query("SHOW TABLES LIKE 'ledger_accounts'")->num_rows > 0) {
        $res = $conn->query("
            SELECT a.account_id, a.account_number, a.account_name, a.account_type, 
                   COALESCE(SUM(e.debit), 0) as total_debit, 
                   COALESCE(SUM(e.credit), 0) as total_credit 
            FROM ledger_accounts a 
            LEFT JOIN ledger_entries e ON a.account_id = e.account_id 
            GROUP BY a.account_id 
            ORDER BY a.account_type, a.account_number ASC
        ");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $deb  = (float)$r['total_debit'];
                $cred = (float)$r['total_credit'];
                $total_debit  += $deb;
                $total_credit += $cred;
                $accounts[] = $r;
            }
        }
    }

    api_success([
        'accounts' => $accounts,
        'totals' => [
            'debit' => $total_debit,
            'credit' => $total_credit,
            'is_balanced' => abs($total_debit - $total_credit) < 0.01
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. INVESTMENTS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'investments' && $method === 'GET') {
    $investments = [];
    if ($conn->query("SHOW TABLES LIKE 'investments'")->num_rows > 0) {
        $res = $conn->query("SELECT * FROM investments ORDER BY created_at DESC");
        if ($res) {
            while ($r = $res->fetch_assoc()) $investments[] = $r;
        }
    }
    api_success(['investments' => $investments]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. PAYROLL & EMPLOYEES
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'employees' && $method === 'GET') {
    $employees = [];
    if ($conn->query("SHOW TABLES LIKE 'employees'")->num_rows > 0) {
        $res = $conn->query("SELECT * FROM employees ORDER BY employee_id DESC");
        if ($res) {
            while ($r = $res->fetch_assoc()) $employees[] = $r;
        }
    }
    api_success(['employees' => $employees]);
}

if ($action === 'payroll_runs' && $method === 'GET') {
    $runs = [];
    if ($conn->query("SHOW TABLES LIKE 'payroll_runs'")->num_rows > 0) {
        $res = $conn->query("SELECT * FROM payroll_runs ORDER BY period DESC");
        if ($res) {
            while ($r = $res->fetch_assoc()) $runs[] = $r;
        }
    }
    api_success(['payroll_runs' => $runs]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 10. USERS & ROLES
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'users' && $method === 'GET') {
    $users = [];
    $res = $conn->query("SELECT a.admin_id, a.username, a.full_name, a.email, a.phone, a.role_id, r.name as role_name, a.is_active, a.last_login FROM admins a JOIN roles r ON a.role_id = r.id ORDER BY a.admin_id ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) $users[] = $r;
    }
    api_success(['users' => $users]);
}

if ($action === 'roles' && $method === 'GET') {
    $roles = [];
    $res = $conn->query("SELECT * FROM roles ORDER BY id ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rid = (int)$r['id'];
            $perm_res = $conn->query("SELECT permission FROM role_permissions WHERE role_id = $rid");
            $perms = [];
            if ($perm_res) {
                while ($p = $perm_res->fetch_assoc()) $perms[] = $p['permission'];
            }
            $r['permissions'] = $perms;
            $roles[] = $r;
        }
    }

    $all_permissions = [];
    $p_res = $conn->query("SELECT * FROM permissions ORDER BY id ASC");
    if ($p_res) {
        while ($p = $p_res->fetch_assoc()) $all_permissions[] = $p;
    }

    api_success(['roles' => $roles, 'all_permissions' => $all_permissions]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 11. LIVE MONITOR & AUDIT LOGS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'live_monitor' && $method === 'GET') {
    $logs = [];
    if ($conn->query("SHOW TABLES LIKE 'audit_logs'")->num_rows > 0) {
        $res = $conn->query("SELECT a.*, COALESCE(adm.full_name, 'Admin') AS admin_name FROM audit_logs a LEFT JOIN admins adm ON a.admin_id = adm.admin_id ORDER BY a.created_at DESC LIMIT 50");
        if ($res) {
            while ($r = $res->fetch_assoc()) $logs[] = $r;
        }
    }
    api_success([
        'logs' => $logs,
        'server_time' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 12. BACKUPS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'backups' && $method === 'GET') {
    $backups = [];
    $backup_dir = __DIR__ . '/../../backups';
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $f) {
            if (str_ends_with($f, '.sql') || str_ends_with($f, '.gz') || str_ends_with($f, '.zip')) {
                $backups[] = [
                    'filename' => $f,
                    'size' => filesize($backup_dir . '/' . $f),
                    'created_at' => date('Y-m-d H:i:s', filemtime($backup_dir . '/' . $f))
                ];
            }
        }
    }
    api_success(['backups' => $backups]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 13. SETTINGS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'settings' && $method === 'GET') {
    $settings = [];
    if ($conn->query("SHOW TABLES LIKE 'system_settings'")->num_rows > 0) {
        $res = $conn->query("SELECT * FROM system_settings");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $settings[$r['setting_key']] = $r['setting_value'];
            }
        }
    }
    api_success(['settings' => $settings]);
}

if ($action === 'update_settings' && $method === 'POST') {
    if ($conn->query("SHOW TABLES LIKE 'system_settings'")->num_rows > 0) {
        foreach ($input as $key => $val) {
            $k = $conn->real_escape_string((string)$key);
            $v = $conn->real_escape_string((string)$val);
            $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('$k', '$v') ON DUPLICATE KEY UPDATE setting_value = '$v'");
        }
    }
    api_success(null, 'Settings updated successfully.');
}

api_error('Unknown admin action: ' . htmlspecialchars($action), 404);
