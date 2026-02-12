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
require_once __DIR__ . '/../../inc/mpesa_lib.php';

$layout = LayoutManager::create('member');
// member/withdraw.php
// Unified Withdrawal Page - Real-Time Ledger Updates

// Auth check
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$engine = new FinancialEngine($conn);

// Fetch member details & Real-Time Balances
$stmt = $conn->prepare("SELECT full_name, phone, email FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) die("Member not found");

$member_name = $member['full_name'];
$member_phone = $member['phone'];
$balances = $engine->getBalances($member_id);

// Get withdrawal type and source context
$type = $_GET['type'] ?? 'wallet'; // What account to withdraw FROM (ledger category)
$source_page = $_GET['source'] ?? 'dashboard'; // Where to redirect AFTER

// Configuration for Source Accounts
$withdrawal_sources = [
    'wallet' => [
        'title' => 'Wallet Balance',
        'description' => 'Withdraw from your available wallet balance',
        'icon' => 'wallet2',
        'balance' => $balances['wallet'],
        'ledger_cat' => FinancialEngine::CAT_WALLET
    ],
    'savings' => [
        'title' => 'Savings Account',
        'description' => 'Withdraw from your savings (Min. bal KES 500)',
        'icon' => 'piggy-bank',
        'balance' => $balances['savings'],
        'ledger_cat' => FinancialEngine::CAT_SAVINGS
    ],
    'loans' => [
        'title' => 'Loan Funds',
        'description' => 'Withdraw disbursed loan funds from wallet',
        'icon' => 'cash-stack',
        // Loan withdrawals usually come from the Wallet (where loans are disbursed to)
        'balance' => $balances['wallet'], 
        'ledger_cat' => FinancialEngine::CAT_WALLET 
    ],
    'shares' => [
        'title' => 'Share Capital',
        'description' => 'Non-withdrawable',
        'icon' => 'pie-chart',
        'balance' => $balances['shares'],
        'ledger_cat' => FinancialEngine::CAT_SHARES,
        'error' => "Share Capital cannot be withdrawn directly."
    ],
    'welfare' => [
        'title' => 'Welfare Fund',
        'description' => 'Restricted Withdrawal',
        'icon' => 'heart-pulse',
        'balance' => $balances['welfare'],
        'ledger_cat' => FinancialEngine::CAT_WELFARE,
        'error' => "Welfare contributions are only accessible via support cases."
    ]
];

// Fallback if type not found
if (!isset($withdrawal_sources[$type])) $type = 'wallet';
$current_source = $withdrawal_sources[$type];

// Apply Business Rules
$max_withdrawal = $current_source['balance'];

// Rule: Savings Min Balance
if ($type === 'savings') {
    if ($max_withdrawal < 500) {
        $current_source['error'] = "Insufficient savings. Minimum balance of KES 500 required.";
        $max_withdrawal = 0;
    } else {
        $max_withdrawal -= 500; // Leave 500
    }
}

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
        $conn->begin_transaction();
        try {
            // 1. GENERATE REFERENCE
            $ref = 'WD-' . strtoupper(substr(md5(uniqid((string)rand(), true)), 0, 10));
            
            // 2. INITIATE WITHDRAWAL (Pending Hold)
            $txn_id = $engine->transact([
                'member_id'   => $member_id,
                'amount'      => $amount,
                'action_type' => 'withdrawal_initiate',
                'method'      => 'mpesa', // Target destination
                'source_cat'  => $current_source['ledger_cat'], // debit this
                'reference'   => $ref,
                'notes'       => "Withdrawal Initiated ($ref) to $phone"
            ]);

            // 3. LOG REQUEST
            $stmt = $conn->prepare("INSERT INTO withdrawal_requests (member_id, ref_no, amount, source_ledger, phone_number, status) VALUES (?, ?, ?, ?, ?, 'initiated')");
            $stmt->bind_param("isdss", $member_id, $ref, $amount, $type, $phone);
            if (!$stmt->execute()) throw new Exception("Failed to log request.");
            $withdrawal_id = $conn->insert_id;
            $stmt->close();

            // 4. CALL M-PESA API
            $mpesa_response = mpesa_b2c_request($phone, $amount, $ref, "Withdrawal");

            if ($mpesa_response['success']) {
                $mpesa_conv_id = $mpesa_response['conversation_id'] ?? null;
                
                // Update request with M-Pesa Trace IDs
                $conn->query("UPDATE withdrawal_requests SET status = 'pending', mpesa_conversation_id = '$mpesa_conv_id' WHERE withdrawal_id = $withdrawal_id");
                
                $conn->commit(); // COMMIT THE HOLD
                
                $success = "Processing: KES " . number_format($amount) . " has been reserved. Waiting for M-Pesa confirmation.";
                
                // Redirect back to source context
                $return_url = BASE_URL . "/member/pages/" . $source_page . ".php?msg=withdrawal_initiated";
                header("Refresh:2; URL=" . $return_url);
            } else {
                throw new Exception("M-Pesa API Failed: " . $mpesa_response['message']);
            }

        } catch (Exception $e) {
            $conn->rollback(); // Rollback the debit if API call fails immediately
            $error = "Transaction Failed: " . $e->getMessage();
            error_log("Withdrawal Exception: " . $e->getMessage());
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
                    <a href="<?= BASE_URL ?>/member/pages/<?= htmlspecialchars($source_page) ?>.php" class="text-decoration-none text-muted d-inline-flex align-items-center mb-2">
                        <i class="bi bi-arrow-left me-2"></i> Back to <?= ucfirst(htmlspecialchars($source_page)) ?>
                    </a>
                    <h2 class="fw-bold mb-1">Withdraw Funds</h2>
                    <p class="text-muted mb-0">Withdraw from <?= htmlspecialchars($current_source['title']) ?></p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= esc($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Source Info Card -->
                <div class="card card-custom mb-3 p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div style="width:48px; height:48px; background: #ecfccb; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-<?= esc($current_source['icon']) ?> fs-4 text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold"><?= esc($current_source['title']) ?></h6>
                            <small class="text-muted"><?= esc($current_source['description']) ?></small>
                        </div>
                    </div>
                </div>

                <!-- Balance Card -->
                <div class="balance-display mb-4">
                    <div class="small text-white-50 mb-2">AVAILABLE FOR WITHDRAWAL</div>
                    <h1 class="display-4 fw-bold mb-0">KES <?= number_format((float)$max_withdrawal, 2) ?></h1>
                    <?php if (isset($current_source['error'])): ?>
                        <div class="mt-2 small text-warning"><i class="bi bi-exclamation-triangle-fill"></i> <?= esc($current_source['error']) ?></div>
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
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted">Max: KES <?= number_format((float)$max_withdrawal, 2) ?></small>
                                    <small class="text-muted">Min: KES 10.00</small>
                                </div>
                            </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">M-Pesa Phone Number</label>
                            <input type="tel" name="phone" class="form-control form-control-lg" 
                                   placeholder="254XXXXXXXXX" value="<?= htmlspecialchars($member_phone) ?>" 
                                   required>
                            <small class="text-muted">Format: 254XXXXXXXXX or 07XXXXXXXX</small>
                        </div>

                        <div class="alert alert-info border-0 mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Processing:</strong> Funds will be sent to your M-Pesa immediately.
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
