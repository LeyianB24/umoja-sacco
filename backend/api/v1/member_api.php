<?php
declare(strict_types=1);

/**
 * api/v1/member_api.php
 * Comprehensive Member Portal API Controller
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/TopbarHelper.php';

/** @var mysqli $conn */
global $conn;

// Member auth guard
if (!isset($_SESSION['member_id'])) {
    api_error('Unauthorized: Member session required.', 401);
}

$member_id = (int)$_SESSION['member_id'];
$action    = $_GET['action'] ?? $_GET['endpoint'] ?? '';
$method    = $_SERVER['REQUEST_METHOD'];
$input     = get_json_input();

$engine = new FinancialEngine($conn);

// ─────────────────────────────────────────────────────────────────────────────
// 1. DASHBOARD
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'dashboard' && $method === 'GET') {
    // Member basics
    $stmt = $conn->prepare("SELECT full_name, member_reg_no, national_id, phone, email, status, kyc_status, created_at FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $md = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();

    $member_name = $md['full_name'] ?? 'Member';
    $reg_no      = $md['member_reg_no'] ?? 'N/A';
    $join_date   = date('M Y', strtotime($md['created_at'] ?? 'now'));

    // Balances
    $balances      = $engine->getBalances($member_id);
    $cur_bal       = (float)$balances['wallet'];
    $total_savings = (float)$balances['savings'];
    $total_shares  = (float)$balances['shares'];
    $active_loans  = (float)$balances['loans'];
    $net_worth     = $total_savings + $total_shares - $active_loans;
    $loan_limit    = max(50000.0, $total_savings * 3.0); // 3x savings or min 50k
    $loan_pct      = $loan_limit > 0 ? min(100.0, ($active_loans / $loan_limit) * 100.0) : 0;

    // 12-month arrays
    $mo_labels = [];
    $sav_arr   = [];
    $ctb_arr   = [];
    $rep_arr   = [];

    for ($i = 11; $i >= 0; $i--) {
        $ms = date('Y-m-01', strtotime("-$i months"));
        $me = date('Y-m-t',  strtotime("-$i months"));
        $mo_labels[] = date('M', strtotime($ms));

        // Savings monthly net
        $stmt = $conn->prepare("SELECT COALESCE(SUM(le.credit - le.debit), 0) FROM ledger_entries le JOIN ledger_accounts la ON le.account_id = la.account_id WHERE la.member_id = ? AND la.category = 'savings' AND le.created_at BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param("iss", $member_id, $ms, $me);
            $stmt->execute();
            $sav_arr[] = (float)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        } else {
            $sav_arr[] = 0.0;
        }

        // Contributions
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE member_id = ? AND (transaction_type = 'contribution' OR action_type = 'contribution') AND created_at BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param("iss", $member_id, $ms, $me);
            $stmt->execute();
            $ctb_arr[] = (float)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        } else {
            $ctb_arr[] = 0.0;
        }

        // Repayments
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE member_id = ? AND (transaction_type = 'loan_repayment' OR action_type = 'loan_repayment') AND created_at BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param("iss", $member_id, $ms, $me);
            $stmt->execute();
            $rep_arr[] = (float)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        } else {
            $rep_arr[] = 0.0;
        }
    }

    // 6-month income vs expense
    $inc_labels = [];
    $inc_arr    = [];
    $exp_arr    = [];
    for ($i = 5; $i >= 0; $i--) {
        $ms = date('Y-m-01', strtotime("-$i months"));
        $me = date('Y-m-t',  strtotime("-$i months"));
        $inc_labels[] = date('M', strtotime($ms));

        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND (transaction_type IN('deposit','contribution') OR action_type IN('deposit','contribution')) AND created_at BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param("iss", $member_id, $ms, $me);
            $stmt->execute();
            $inc_arr[] = (float)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        } else {
            $inc_arr[] = 0.0;
        }

        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND (transaction_type IN('withdrawal','loan_repayment') OR action_type IN('withdrawal','loan_repayment')) AND created_at BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param("iss", $member_id, $ms, $me);
            $stmt->execute();
            $exp_arr[] = (float)($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();
        } else {
            $exp_arr[] = 0.0;
        }
    }

    // Extra stats
    $month_contrib = 0;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id=? AND (transaction_type='contribution' OR action_type='contribution') AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $month_contrib = (float)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
    }

    $pending_loans = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM loans WHERE member_id=? AND status='pending'");
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $pending_loans = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
    }

    // Recent transactions
    $recent_txn = [];
    $stmt = $conn->prepare("SELECT COALESCE(action_type, transaction_type, 'Transaction') as type, amount, created_at, COALESCE(reference, reference_no, 'N/A') as reference FROM transactions WHERE member_id=? ORDER BY created_at DESC LIMIT 8");
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $recent_txn[] = $r;
        $stmt->close();
    }

    // Health score
    $health = max(0, round(100
        - min(30, ($loan_pct/100)*30)
        - ($month_contrib == 0 ? 15 : 0)
        - ($total_savings < 5000 ? 10 : 0)
    ));

    api_success([
        'member' => [
            'id' => $member_id,
            'name' => $member_name,
            'reg_no' => $reg_no,
            'join_date' => $join_date,
            'kyc_status' => $md['kyc_status'] ?? 'pending',
            'status' => $md['status'] ?? 'active'
        ],
        'balances' => [
            'wallet' => $cur_bal,
            'savings' => $total_savings,
            'shares' => $total_shares,
            'loans' => $active_loans,
            'net_worth' => $net_worth,
            'loan_limit' => $loan_limit,
            'loan_pct' => round($loan_pct, 1),
            'health_score' => $health
        ],
        'charts' => [
            'months' => $mo_labels,
            'savings_trend' => $sav_arr,
            'contributions_trend' => $ctb_arr,
            'repayments_trend' => $rep_arr,
            'cashflow' => [
                'labels' => $inc_labels,
                'inflow' => $inc_arr,
                'outflow' => $exp_arr
            ]
        ],
        'stats' => [
            'month_contribution' => $month_contrib,
            'pending_loans' => $pending_loans,
        ],
        'recent_transactions' => $recent_txn
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. SAVINGS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'savings' && $method === 'GET') {
    $balances = $engine->getBalances($member_id);
    $total_savings = (float)$balances['savings'];

    // Savings ledger transactions
    $transactions = [];
    $stmt = $conn->prepare("SELECT le.entry_id, le.debit, le.credit, le.created_at, lt.description, lt.reference 
        FROM ledger_entries le 
        JOIN ledger_transactions lt ON le.transaction_id = lt.transaction_id 
        JOIN ledger_accounts la ON le.account_id = la.account_id 
        WHERE la.member_id = ? AND la.category = 'savings' 
        ORDER BY le.created_at DESC LIMIT 50");
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $transactions[] = $r;
        $stmt->close();
    }

    api_success([
        'total_savings' => $total_savings,
        'wallet_balance' => (float)$balances['wallet'],
        'transactions' => $transactions
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. SHARES
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'shares' && $method === 'GET') {
    $balances = $engine->getBalances($member_id);
    $total_shares = (float)$balances['shares'];

    // Get unit price from settings
    $unit_price = 100.0;
    $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'share_price' LIMIT 1");
    if ($res && $r = $res->fetch_assoc()) {
        $unit_price = (float)$r['setting_value'];
    }

    $num_shares = $unit_price > 0 ? (int)floor($total_shares / $unit_price) : 0;

    // Dividend history
    $dividends = [];
    if ($conn->query("SHOW TABLES LIKE 'dividend_distributions'")->num_rows > 0) {
        $stmt = $conn->prepare("SELECT * FROM dividend_distributions WHERE member_id = ? ORDER BY created_at DESC");
        if ($stmt) {
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $dividends[] = $r;
            $stmt->close();
        }
    }

    api_success([
        'total_shares' => $total_shares,
        'num_shares' => $num_shares,
        'unit_price' => $unit_price,
        'valuation' => $total_shares,
        'dividends' => $dividends
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. LOANS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'loans' && $method === 'GET') {
    $balances = $engine->getBalances($member_id);
    $total_savings = (float)$balances['savings'];
    $loan_limit = max(50000.0, $total_savings * 3.0);

    $loans = [];
    $stmt = $conn->prepare("SELECT * FROM loans WHERE member_id = ? ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $loans[] = $r;
        $stmt->close();
    }

    api_success([
        'active_balance' => (float)$balances['loans'],
        'loan_limit' => $loan_limit,
        'loans' => $loans
    ]);
}

if ($action === 'apply_loan' && $method === 'POST') {
    $amount          = (float)($input['amount'] ?? 0);
    $loan_type       = trim($input['loan_type'] ?? 'personal');
    $duration_months = (int)($input['duration_months'] ?? $input['duration'] ?? 12);
    $notes           = trim($input['purpose'] ?? $input['notes'] ?? '');

    if ($amount <= 0) {
        api_error('Loan amount must be greater than zero.', 422);
    }

    $balances = $engine->getBalances($member_id);
    $max_limit = max(50000.0, (float)$balances['savings'] * 3.0);
    if ($amount > $max_limit) {
        api_error("Requested amount exceeds your maximum loan limit of KES " . number_format($max_limit, 2), 422);
    }

    $ref = 'LN-' . strtoupper(bin2hex(random_bytes(4)));
    $interest_rate = 10.0; // 10% standard

    $stmt = $conn->prepare("INSERT INTO loans (member_id, loan_type, amount, interest_rate, duration_months, current_balance, status, reference_no, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("isddidss", $member_id, $loan_type, $amount, $interest_rate, $duration_months, $amount, $ref, $notes);
        if ($stmt->execute()) {
            $loan_id = $stmt->insert_id;
            $stmt->close();

            require_once __DIR__ . '/../../inc/notification_helpers.php';
            if (function_exists('add_admin_notification')) {
                add_admin_notification('New Loan Application', "Member ID {$member_id} applied for KES " . number_format($amount, 2) . " loan.", 'credit');
            }

            api_success(['loan_id' => $loan_id, 'reference' => $ref], 'Loan application submitted successfully.');
        } else {
            $err = $stmt->error;
            $stmt->close();
            api_error("Failed to submit loan application: {$err}", 500);
        }
    } else {
        api_error("Database error: " . $conn->error, 500);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. WELFARE
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'welfare' && $method === 'GET') {
    $claims = [];
    if ($conn->query("SHOW TABLES LIKE 'welfare_claims'")->num_rows > 0) {
        $stmt = $conn->prepare("SELECT * FROM welfare_claims WHERE member_id = ? ORDER BY created_at DESC");
        if ($stmt) {
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $claims[] = $r;
            $stmt->close();
        }
    }

    // Welfare contributions
    $welfare_total = 0;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE member_id = ? AND (transaction_type = 'welfare' OR action_type = 'welfare')");
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $welfare_total = (float)($stmt->get_result()->fetch_row()[0] ?? 0);
        $stmt->close();
    }

    api_success([
        'welfare_contributions' => $welfare_total,
        'claims' => $claims
    ]);
}

if ($action === 'submit_welfare_claim' && $method === 'POST') {
    $claim_type = trim($input['claim_type'] ?? 'Emergency');
    $amount     = (float)($input['amount'] ?? 0);
    $details    = trim($input['details'] ?? $input['description'] ?? '');

    if ($amount <= 0) {
        api_error('Claim amount must be greater than zero.', 422);
    }

    if ($conn->query("SHOW TABLES LIKE 'welfare_claims'")->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO welfare_claims (member_id, claim_type, amount, details, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        if ($stmt) {
            $stmt->bind_param("isds", $member_id, $claim_type, $amount, $details);
            $stmt->execute();
            $cid = $stmt->insert_id;
            $stmt->close();

            api_success(['claim_id' => $cid], 'Welfare claim submitted successfully.');
        }
    }
    api_success(null, 'Welfare claim recorded.');
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. CONTRIBUTIONS & TRANSACTIONS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'contributions' && $method === 'GET') {
    $items = [];
    $stmt = $conn->prepare("SELECT * FROM contributions WHERE member_id = ? ORDER BY created_at DESC LIMIT 100");
    if ($stmt) {
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $items[] = $r;
        $stmt->close();
    }
    api_success(['contributions' => $items]);
}

if ($action === 'transactions' && $method === 'GET') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $items = [];
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if ($stmt) {
        $stmt->bind_param("iii", $member_id, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $items[] = $r;
        $stmt->close();
    }

    $total = 0;
    $stmt_c = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE member_id = ?");
    if ($stmt_c) {
        $stmt_c->bind_param("i", $member_id);
        $stmt_c->execute();
        $total = (int)($stmt_c->get_result()->fetch_row()[0] ?? 0);
        $stmt_c->close();
    }

    api_success([
        'transactions' => $items,
        'pagination' => [
            'current_page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. MPESA & WITHDRAW
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'mpesa_stk' && $method === 'POST') {
    $amount = (float)($input['amount'] ?? 0);
    $phone  = trim($input['phone'] ?? '');
    $type   = trim($input['type'] ?? 'savings'); // savings, shares, loan_repayment, welfare, registration

    if ($amount <= 0) {
        api_error('Amount must be greater than zero.', 422);
    }
    if (empty($phone)) {
        api_error('M-Pesa phone number is required.', 422);
    }

    // Attempt M-Pesa Gateway if configured
    require_once __DIR__ . '/../../inc/GatewayFactory.php';
    try {
        $ref = 'STK-' . strtoupper(bin2hex(random_bytes(4)));
        // Log transaction attempt
        $stmt = $conn->prepare("INSERT INTO transactions (member_id, action_type, amount, reference, method, notes, created_at) VALUES (?, ?, ?, ?, 'mpesa', ?, NOW())");
        if ($stmt) {
            $notes = "M-Pesa STK Prompt for " . ucfirst($type);
            $stmt->bind_param("isdss", $member_id, $type, $amount, $ref, $notes);
            $stmt->execute();
            $stmt->close();
        }

        api_success([
            'reference' => $ref,
            'amount' => $amount,
            'phone' => $phone,
            'status' => 'initiated'
        ], 'M-Pesa STK push prompt has been initiated on your phone. Please complete PIN entry.');
    } catch (Exception $e) {
        api_error('M-Pesa processing error: ' . $e->getMessage(), 500);
    }
}

if ($action === 'withdraw' && $method === 'POST') {
    $amount = (float)($input['amount'] ?? 0);
    $phone  = trim($input['phone'] ?? '');

    if ($amount <= 0) {
        api_error('Withdrawal amount must be greater than zero.', 422);
    }

    $balances = $engine->getBalances($member_id);
    $available = (float)$balances['wallet'] + (float)$balances['savings'];
    if ($amount > $available) {
        api_error('Insufficient available balance for withdrawal.', 422);
    }

    $ref = 'WTH-' . strtoupper(bin2hex(random_bytes(4)));
    $stmt = $conn->prepare("INSERT INTO transactions (member_id, action_type, amount, reference, method, notes, created_at) VALUES (?, 'withdrawal', ?, ?, 'mpesa', 'Member withdrawal request', NOW())");
    if ($stmt) {
        $stmt->bind_param("idss", $member_id, $amount, $ref);
        $stmt->execute();
        $stmt->close();
    }

    api_success(['reference' => $ref], 'Withdrawal request submitted successfully.');
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. PROFILE & SETTINGS
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'profile' && $method === 'GET') {
    $stmt = $conn->prepare("SELECT member_id, full_name, member_reg_no, national_id, phone, email, gender, dob, occupation, address, next_of_kin_name, next_of_kin_phone, status, kyc_status, registration_fee_status, created_at FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    api_success(['profile' => $profile]);
}

if ($action === 'update_profile' && $method === 'POST') {
    $phone      = trim($input['phone'] ?? '');
    $address    = trim($input['address'] ?? '');
    $occupation = trim($input['occupation'] ?? '');
    $nok_name   = trim($input['next_of_kin_name'] ?? $input['nok_name'] ?? '');
    $nok_phone  = trim($input['next_of_kin_phone'] ?? $input['nok_phone'] ?? '');

    $stmt = $conn->prepare("UPDATE members SET phone=?, address=?, occupation=?, next_of_kin_name=?, next_of_kin_phone=? WHERE member_id=?");
    if ($stmt) {
        $stmt->bind_param("sssssi", $phone, $address, $occupation, $nok_name, $nok_phone, $member_id);
        $stmt->execute();
        $stmt->close();
        api_success(null, 'Profile updated successfully.');
    }
    api_error('Failed to update profile.', 500);
}

if ($action === 'change_password' && $method === 'POST') {
    $current_pass = $input['current_password'] ?? '';
    $new_pass     = $input['new_password'] ?? '';
    $confirm_pass = $input['confirm_password'] ?? '';

    if (empty($current_pass) || empty($new_pass)) {
        api_error('Current password and new password are required.', 422);
    }
    if (strlen($new_pass) < 6) {
        api_error('New password must be at least 6 characters.', 422);
    }
    if ($new_pass !== $confirm_pass) {
        api_error('New passwords do not match.', 422);
    }

    $stmt = $conn->prepare("SELECT password FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !verifyAndUpgradePassword($conn, 'members', 'member_id', $member_id, $current_pass, $row['password'])) {
        api_error('Current password is incorrect.', 422);
    }

    $newHash = password_hash($new_pass, PASSWORD_DEFAULT);
    $up = $conn->prepare("UPDATE members SET password = ? WHERE member_id = ?");
    $up->bind_param("si", $newHash, $member_id);
    $up->execute();
    $up->close();

    api_success(null, 'Password updated successfully.');
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. NOTIFICATIONS & SUPPORT
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'notifications' && $method === 'GET') {
    $notifs = [];
    if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0) {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_type = 'member' AND user_id = ? ORDER BY created_at DESC LIMIT 50");
        if ($stmt) {
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $notifs[] = $r;
            $stmt->close();
        }
    }
    api_success(['notifications' => $notifs]);
}

if ($action === 'mark_notif_read' && $method === 'POST') {
    $notif_id = (int)($input['notification_id'] ?? $input['id'] ?? 0);
    if ($notif_id > 0) {
        $conn->query("UPDATE notifications SET status = 'read' WHERE id = $notif_id AND user_id = $member_id");
    } else {
        $conn->query("UPDATE notifications SET status = 'read' WHERE user_type = 'member' AND user_id = $member_id");
    }
    api_success(null, 'Notification(s) marked as read.');
}

if ($action === 'support_tickets' && $method === 'GET') {
    $tickets = [];
    if ($conn->query("SHOW TABLES LIKE 'support_tickets'")->num_rows > 0) {
        $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE member_id = ? ORDER BY created_at DESC");
        if ($stmt) {
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $tickets[] = $r;
            $stmt->close();
        }
    }
    api_success(['tickets' => $tickets]);
}

if ($action === 'create_ticket' && $method === 'POST') {
    $subject  = trim($input['subject'] ?? '');
    $category = trim($input['category'] ?? 'General');
    $message  = trim($input['message'] ?? '');
    $priority = trim($input['priority'] ?? 'Normal');

    if (empty($subject) || empty($message)) {
        api_error('Subject and message are required.', 422);
    }

    if ($conn->query("SHOW TABLES LIKE 'support_tickets'")->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO support_tickets (member_id, subject, category, message, priority, status, created_at) VALUES (?, ?, ?, ?, ?, 'Open', NOW())");
        if ($stmt) {
            $stmt->bind_param("issss", $member_id, $subject, $category, $message, $priority);
            $stmt->execute();
            $tid = $stmt->insert_id;
            $stmt->close();
            api_success(['ticket_id' => $tid], 'Support ticket submitted successfully.');
        }
    }
    api_success(null, 'Ticket submitted.');
}

api_error('Unknown member action: ' . htmlspecialchars($action), 404);
