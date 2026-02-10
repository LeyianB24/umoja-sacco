<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';
require_once __DIR__ . '/../../inc/TransactionHelper.php';
require_once __DIR__ . '/../../inc/paystack_lib.php';
require_once __DIR__ . '/../../inc/notification_helpers.php';

$layout = LayoutManager::create('member');
// member/withdraw.php
// Unified Withdrawal Page - Works like mpesa_request.php but for withdrawals
// Uses Paystack for M-Pesa transfers (sandbox compatible)


// Auth check
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

// Fetch member details
$stmt = $conn->prepare("SELECT full_name, phone, email, account_balance FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) die("Member not found");

$member_name = $member['full_name'];
$member_phone = $member['phone'];
$member_email = $member['email'];
$available_balance = $member['account_balance'];

// Get withdrawal type (like mpesa_request.php)
$type = $_GET['type'] ?? 'wallet';
$source_page = $_GET['source'] ?? 'dashboard';

// Determine what we're withdrawing from
$withdrawal_sources = [
    'wallet' => [
        'title' => 'Wallet Balance',
        'description' => 'Withdraw from your available wallet balance',
        'icon' => 'wallet2',
        'balance' => $available_balance
    ],
    'savings' => [
        'title' => 'Savings Account',
        'description' => 'Withdraw from your savings (subject to rules)',
        'icon' => 'piggy-bank',
        'balance' => 0 // Will be fetched from savings table
    ],
    'shares' => [
        'title' => 'Share Capital',
        'description' => 'Withdraw from your share capital',
        'icon' => 'pie-chart',
        'balance' => 0
    ],
    'welfare' => [
        'title' => 'Welfare Fund',
        'description' => 'Withdraw from your welfare contributions',
        'icon' => 'heart-pulse',
        'balance' => 0
    ],
    'loans' => [
        'title' => 'Loan Funds',
        'description' => 'Withdraw your disbursed loan amount',
        'icon' => 'cash-stack',
        'balance' => $available_balance // Loan funds are typically in the wallet
    ]
];

// Fetch actual balance based on type
if ($type === 'savings') {
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM savings WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $withdrawal_sources['savings']['balance'] = $result['total'] ?? 0;
    $stmt->close();
} elseif ($type === 'shares') {
    $stmt = $conn->prepare("SELECT SUM(total_value) as total FROM shares WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $withdrawal_sources['shares']['balance'] = $result['total'] ?? 0;
    $stmt->close();
} elseif ($type === 'welfare') {
    // Net welfare standing: Contributions - Support received
    $stmtIn = $conn->prepare("SELECT SUM(amount) as total FROM contributions WHERE member_id = ? AND contribution_type IN ('welfare', 'welfare_case') AND status IN ('active', 'completed')");
    $stmtIn->bind_param("i", $member_id);
    $stmtIn->execute();
    $totalIn = $stmtIn->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtIn->close();

    $stmtOut = $conn->prepare("SELECT SUM(amount) as total FROM welfare_support WHERE member_id = ? AND status IN ('approved', 'disbursed')");
    $stmtOut->bind_param("i", $member_id);
    $stmtOut->execute();
    $totalOut = $stmtOut->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtOut->close();

    if ($type === 'savings' && $withdrawal_sources['savings']['balance'] > 0) {
        if ($withdrawal_sources['savings']['balance'] < 500) {
            $withdrawal_sources['savings']['balance'] = 0;
            $withdrawal_sources['savings']['error'] = "A minimum balance of KES 500 must be maintained in your Savings Account.";
        } else {
            $withdrawal_sources['savings']['balance'] -= 500;
        }
    }

    $withdrawal_sources['welfare']['balance'] = 0; 
    $withdrawal_sources['welfare']['error'] = "Welfare contributions are only accessible via specific support cases.";
}

// 4. SHARES RESTRICTION: Usually non-withdrawable
if ($type === 'shares') {
    $withdrawal_sources['shares']['balance'] = 0;
    $withdrawal_sources['shares']['error'] = "Share Capital cannot be withdrawn directly. Please contact the office for membership exit process.";
}

$current_source = $withdrawal_sources[$type] ?? $withdrawal_sources['wallet'];
$max_withdrawal = $current_source['balance'];

$success = '';
$error = '';

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    verify_csrf_token();
    
    $amount = floatval($_POST['amount']);
    $phone = trim($_POST['phone']);
    
    // Validation
    if ($amount <= 0) {
        $error = "Invalid amount.";
    } elseif ($amount > $max_withdrawal) {
        $error = "Insufficient balance. Available: KES " . number_format($max_withdrawal);
    } elseif (empty($phone)) {
        $error = "Phone number is required.";
    } else {
        try {
            $in_txn = $conn->begin_transaction();
            
            // 1. Generate internal reference
            $ref = 'WD-' . strtoupper(substr(md5(uniqid((string)rand(), true)), 0, 10));
            $ledger = FinancialEngine::CAT_WALLET;
            if ($type === 'savings') $ledger = FinancialEngine::CAT_SAVINGS;
            elseif ($type === 'shares') $ledger = FinancialEngine::CAT_SHARES;
            elseif ($type === 'welfare') $ledger = FinancialEngine::CAT_WELFARE;

            // 2. Record in withdrawal_requests (Standardizing Traceability)
            $stmt = $conn->prepare("INSERT INTO withdrawal_requests (member_id, ref_no, amount, source_ledger, phone_number, status) VALUES (?, ?, ?, ?, ?, 'initiated')");
            $stmt->bind_param("isdss", $member_id, $ref, $amount, $type, $phone);
            if (!$stmt->execute()) {
                throw new Exception("Failed to initiate withdrawal request record.");
            }
            $withdrawal_id = $conn->insert_id;
            $stmt->close();

            // 3. Initiate Paystack transfer (Optional / Primarily used for M-Pesa B2C now as per standardization)
            // Standardization says: mirror the loan payment process, M-Pesa request context.
            // I will default to M-Pesa B2C for standardization unless Paystack is explicitly required.
            // Given the SACCO focus, direct B2C is often preferred for control.
            
            require_once __DIR__ . '/../../inc/mpesa_lib.php';
            
            error_log("Initiating M-Pesa B2C for Withdrawal #$withdrawal_id (Ref: $ref)");
            
            // Update to 'pending' before calling API
            $conn->query("UPDATE withdrawal_requests SET status = 'pending' WHERE withdrawal_id = $withdrawal_id");
            $conn->commit(); // Commit the 'pending' state so callback can reconcile even if API call hangs
            $in_txn = false;

            $mpesa_response = mpesa_b2c_request($phone, $amount, $ref, "Withdrawal - $ref");
            
            if ($mpesa_response['success']) {
                $mpesa_conv_id = $mpesa_response['conversation_id'] ?? null;
                if ($mpesa_conv_id) {
                    $conn->query("UPDATE withdrawal_requests SET mpesa_conversation_id = '$mpesa_conv_id' WHERE withdrawal_id = $withdrawal_id");
                }
                
                $success = "Withdrawal initiated! KES " . number_format($amount) . " will be sent to $phone promptly. Check your M-Pesa shortly.";
                
                // Set return URL
                $return_url = $_SESSION['withdrawal_return_url'] ?? BASE_URL . "/member/" . $source_page . ".php";
                header("Refresh:3; URL=" . $return_url);
            } else {
                // API call failed to initiate
                $conn->query("UPDATE withdrawal_requests SET status = 'failed', result_desc = '" . $conn->real_escape_string($mpesa_response['message']) . "' WHERE withdrawal_id = $withdrawal_id");
                $error = "Withdrawal initiation failed: " . ($mpesa_response['message'] ?? 'Connection error');
            }
            
        } catch (Exception $e) {
            if (isset($in_txn) && $in_txn) $conn->rollback();
            $error = "System error: " . $e->getMessage();
            error_log("Withdrawal error: " . $e->getMessage());
        }
    }
}

$pageTitle = "Withdraw Funds";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f3f4f6; }
        .card-custom { border-radius: 24px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .btn-lime { background: #bef264; color: #0f172a; font-weight: 700; border-radius: 50px; padding: 12px 32px; }
        .btn-lime:hover { background: #a3e635; transform: translateY(-2px); color: #0f172a; }
        .balance-display { background: linear-gradient(135deg, #0f392b 0%, #134e3b 100%); color: white; padding: 32px; border-radius: 20px; }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content-wrapper" style="margin-left: 280px; min-height: 100vh;">
    <?php $layout->topbar($pageTitle ?? ''); ?>
    
    <div class="container-fluid p-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                
                <div class="mb-4">
                    <a href="<?= BASE_URL ?>/member/<?= $source_page ?>.php" class="text-decoration-none text-muted d-inline-flex align-items-center mb-2">
                        <i class="bi bi-arrow-left me-2"></i> Back to <?= ucfirst($source_page) ?>
                    </a>
                    <h2 class="fw-bold mb-1">Withdraw Funds</h2>
                    <p class="text-muted mb-0">Cash out to M-Pesa via Paystack</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Source Info Card -->
                <div class="card card-custom mb-3 p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div style="width:48px; height:48px; background: #ecfccb; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-<?= $current_source['icon'] ?> fs-4 text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold"><?= $current_source['title'] ?></h6>
                            <small class="text-muted"><?= $current_source['description'] ?></small>
                        </div>
                    </div>
                </div>

                <!-- Balance Card -->
                <div class="balance-display mb-4">
                    <div class="small text-white-50 mb-2">AVAILABLE FOR WITHDRAWAL</div>
                    <h1 class="display-4 fw-bold mb-0">KES <?= number_format((float)$max_withdrawal, 2) ?></h1>
                    <?php if (isset($current_source['error'])): ?>
                        <div class="mt-2 small text-warning"><i class="bi bi-exclamation-triangle-fill"></i> <?= $current_source['error'] ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!isset($current_source['error'])): ?>
                    <!-- Withdrawal Form -->
                    <div class="card card-custom p-4">
                        <form method="POST">
                            <input type="hidden" name="withdraw" value="1">
                            <?= csrf_field() ?>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Amount to Withdraw (KES)</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">KES</span>
                                    <input type="number" name="amount" class="form-control" 
                                           placeholder="0.00" step="0.01" min="10" 
                                           max="<?= $max_withdrawal ?>" required>
                                </div>
                                <small class="text-muted">Maximum: KES <?= number_format((float)$max_withdrawal, 2) ?></small>
                            </div>
                            <!-- Rest of form... -->

                        <div class="mb-4">
                            <label class="form-label fw-bold">M-Pesa Phone Number</label>
                            <input type="tel" name="phone" class="form-control form-control-lg" 
                                   placeholder="254XXXXXXXXX" value="<?= htmlspecialchars($member_phone) ?>" 
                                   required>
                            <small class="text-muted">Format: 254XXXXXXXXX or 07XXXXXXXX</small>
                        </div>

                        <div class="alert alert-info border-0 mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Processing:</strong> Funds will be sent to your M-Pesa within 1-5 minutes via Paystack.
                        </div>

                        <button type="submit" name="withdraw" class="btn btn-lime w-100 py-3">
                            <i class="bi bi-cash-coin me-2"></i> Withdraw to M-Pesa
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="card card-custom p-5 text-center shadow-lg border-warning">
                    <div class="mb-4">
                        <div style="width:72px; height:72px; background: #fffbeb; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="bi bi-info-circle-fill fs-2 text-warning"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-3">Withdrawal Restricted</h5>
                    <p class="text-secondary mb-4"><?= $current_source['error'] ?></p>
                    <a href="support.php" class="btn btn-outline-dark rounded-pill px-4">Contact Support</a>
                </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="<?= BASE_URL ?>/member/pages/transactions.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left-right me-1"></i> View Transaction History
                    </a>
                </div>

            </div>
        </div>
    </div>

    <?php $layout->footer(); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






