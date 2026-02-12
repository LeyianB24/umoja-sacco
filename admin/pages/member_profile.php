<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

/**
 * admin/member_profile.php
 * Member Portrait & Financial Intelligence Hub - V28 Glassmorphism
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. AUTHENTICATION & PERMISSION
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($member_id <= 0) {
    flash_set("Invalid Member ID specified.", "danger");
    header("Location: members.php");
    exit;
}

// 1.5 HANDLE KYC ACTIONS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kyc_action'])) {
    verify_csrf_token();
    $doc_id = intval($_POST['doc_id']);
    $action = $_POST['kyc_action']; // 'verify' or 'reject'
    $notes  = trim($_POST['verification_notes'] ?? '');
    $status = ($action === 'verify') ? 'verified' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE member_documents SET status = ?, verification_notes = ?, verified_by = ?, verified_at = NOW() WHERE document_id = ? AND member_id = ?");
    $stmt->bind_param("ssiii", $status, $notes, $_SESSION['admin_id'], $doc_id, $member_id);
    
    if ($stmt->execute()) {
        // Recalculate overall KYC status
        $q = $conn->query("SELECT status FROM member_documents WHERE member_id = $member_id AND document_type IN ('national_id_front', 'national_id_back', 'passport_photo')");
        $all_docs = $q->fetch_all(MYSQLI_ASSOC);
        $verified_count = 0;
        $rejected_count = 0;
        foreach($all_docs as $d) {
            if ($d['status'] === 'verified') $verified_count++;
            if ($d['status'] === 'rejected') $rejected_count++;
        }
        
        $new_kyc_status = 'pending';
        if ($verified_count >= 3) $new_kyc_status = 'approved'; 
        elseif ($rejected_count > 0) $new_kyc_status = 'rejected';
        
        $sql_update = "UPDATE members SET kyc_status = '$new_kyc_status'";
        if ($action === 'reject') {
            $sql_update .= ", kyc_notes = '" . $conn->real_escape_string($notes) . "'";
        }
        $sql_update .= " WHERE member_id = $member_id";
        $conn->query($sql_update);
        
        flash_set("Document " . ($action === 'verify' ? 'approved' : 'rejected') . " successfully.", "success");
    } else {
        flash_set("Action failed: " . $conn->error, "danger");
    }
    header("Location: member_profile.php?id=$member_id#kyc");
    exit;
}

// 2. FETCH MEMBER CORE DATA
$stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (!$member) {
    flash_set("Member not found.", "warning");
    header("Location: members.php");
    exit;
}

// 3. FETCH FINANCIAL SUMMARY (Unified Ledger)
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine = new FinancialEngine($conn);
$balances = $engine->getBalances($member_id);

$savings_balance     = $balances['savings'];
$total_debt          = $balances['loans'];
$total_shares_value  = $balances['shares'];
$total_shares_units  = $balances['share_units'] ?? 0;

// Total Contributions (Log only)
$q_cont = $conn->prepare("SELECT SUM(amount) as total FROM contributions WHERE member_id = ? AND status = 'active'");
$q_cont->bind_param("i", $member_id);
$q_cont->execute();
$total_contributions = $q_cont->get_result()->fetch_assoc()['total'] ?? 0;
$q_cont->close();

$q_loans = $conn->prepare("SELECT COUNT(*) as active_count FROM loans WHERE member_id = ? AND status IN ('disbursed', 'active')");
$q_loans->bind_param("i", $member_id);
$q_loans->execute();
$active_loans_count = $q_loans->get_result()->fetch_assoc()['active_count'] ?? 0;
$q_loans->close();

// 4. FETCH ACTIVITY LISTS (Limited for overview)
// Recent Transactions
$q_txns = $conn->prepare("SELECT * FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT 5");
$q_txns->bind_param("i", $member_id);
$q_txns->execute();
$recent_txns = $q_txns->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent Loans
$q_l_list = $conn->prepare("SELECT * FROM loans WHERE member_id = ? ORDER BY created_at DESC LIMIT 5");
$q_l_list->bind_param("i", $member_id);
$q_l_list->execute();
$member_loans = $q_l_list->get_result()->fetch_all(MYSQLI_ASSOC);

// 5. FETCH KYC DOCUMENTS
$q_docs = $conn->prepare("SELECT * FROM member_documents WHERE member_id = ?");
$q_docs->bind_param("i", $member_id);
$q_docs->execute();
$member_docs = $q_docs->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = $member['full_name'] . " - Member Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | USMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <style>
        :root { --forest: #0F2E25; --lime: #D0F35D; --glass: rgba(255, 255, 255, 0.9); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f4f3; color: #1a1a1a; }
        
        .main-content { margin-left: 280px; padding: 40px; min-height: 100vh; }
        
        /* Profile Header */
        .profile-hero {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(15, 46, 37, 0.15);
            position: relative; overflow: hidden;
        }
        .profile-hero::after {
            content: ''; position: absolute; top: -20%; right: -5%; width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(208, 243, 93, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .profile-avatar {
            width: 120px; height: 120px; border-radius: 30px;
            object-fit: cover; border: 4px solid rgba(255,255,255,0.2);
            background: var(--lime); color: var(--forest);
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem; font-weight: 800;
        }
        
        /* Stats Grid */
        .stat-card {
            background: white; border-radius: 24px; padding: 25px;
            border: 1px solid rgba(0,0,0,0.05); transition: 0.3s;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.05); }
        .icon-box {
            width: 50px; height: 50px; border-radius: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; margin-bottom: 20px;
        }
        
        /* Tabs & Content */
        .nav-tabs-custom { border: none; margin-bottom: 25px; gap: 10px; }
        .nav-tabs-custom .nav-link {
            border: none; border-radius: 15px; padding: 12px 25px;
            font-weight: 700; color: #64748b; background: white;
            transition: 0.3s;
        }
        .nav-tabs-custom .nav-link.active {
            background: var(--forest); color: white;
            box-shadow: 0 10px 20px rgba(15, 46, 37, 0.1);
        }
        
        .glass-card {
            background: var(--glass); backdrop-filter: blur(10px);
            border-radius: 24px; border: 1px solid rgba(255,255,255,0.5);
            padding: 30px; margin-bottom: 30px;
        }
        
        .info-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 1px; margin-bottom: 5px; }
        .info-value { font-weight: 600; color: var(--forest); }
        
        .badge-status { padding: 6px 16px; border-radius: 50px; font-weight: 700; font-size: 0.75rem; }
    </style>
</head>
<div class="d-flex">
    <?php $layout->sidebar(); ?>

    <div class="flex-fill main-content">
    <?php $layout->topbar($pageTitle ?? ''); ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="members.php" class="text-decoration-none">Members</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($member['full_name']) ?></li>
        </ol>
    </nav>

    <!-- Profile Hero -->
    <div class="profile-hero shadow">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php if($member['profile_pic']): ?>
                    <img src="data:image/jpeg;base64,<?= base64_encode($member['profile_pic']) ?>" class="profile-avatar shadow">
                <?php else: ?>
                    <div class="profile-avatar shadow"><?= strtoupper(substr($member['full_name'], 0, 1)) ?></div>
                <?php endif; ?>
            </div>
            <div class="col px-md-4 mt-3 mt-md-0">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <h1 class="fw-800 mb-0"><?= htmlspecialchars($member['full_name']) ?></h1>
                    <span class="badge-status bg-<?= $member['status']=='active'?'success':'danger' ?> text-white">
                        <?= strtoupper($member['status']) ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-4 opacity-75">
                    <span><i class="bi bi-hash me-1"></i> REG NO: <?= $member['member_reg_no'] ?></span>
                    <span><i class="bi bi-calendar3 me-1"></i> Joined <?= date('M d, Y', strtotime($member['join_date'])) ?></span>
                    <span><i class="bi bi-phone me-1"></i> <?= $member['phone'] ?></span>
                </div>
            </div>
            <div class="col-md-auto text-md-end mt-4 mt-md-0">
                <div class="d-flex gap-2">
                    <a href="generate_statement.php?member_id=<?= $member_id ?>" class="btn btn-lime rounded-pill px-4 fw-bold">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Statement
                    </a>
                    <?php if(can('manage_members')): ?>
                        <button class="btn btn-white bg-white bg-opacity-10 text-white border-0 rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#editModal">
                            <i class="bi bi-pencil-square me-2"></i>Edit
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Metrics -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-piggy-bank"></i>
                </div>
                <div class="info-label">Savings Balance</div>
                <div class="h3 fw-800 text-forest">KES <?= number_format((float)$savings_balance) ?></div>
                <div class="small text-muted mt-2">Total contributed: KES <?= number_format((float)$total_contributions) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="info-label">Share Capital</div>
                <div class="h3 fw-800 text-forest">KES <?= number_format((float)$total_shares_value) ?></div>
                <div class="small text-muted mt-2"><?= number_format((float)$total_shares_units) ?> Units Owned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon-box bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="info-label">Loan Exposure</div>
                <div class="h3 fw-800 text-forest">KES <?= number_format((float)$total_debt) ?></div>
                <div class="small text-muted mt-2"><?= $active_loans_count ?> Active Applications</div>
            </div>
        </div>
        <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="info-label">KYC Status</div>
                    <div class="h3 fw-800 text-forest"><?= ucwords(str_replace('_', ' ', $member['kyc_status'] ?? 'pending')) ?></div>
                    <div class="small text-muted mt-2">
                        <?php 
                        $reg_paid = ($member['registration_fee_status'] === 'paid' || $member['reg_fee_paid'] == 1);
                        echo $reg_paid ? 'Reg Fee Paid' : '<span class="text-danger">Reg Fee Unpaid</span>';
                        ?>
                    </div>
                </div>
            </div>
    </div>

    <!-- Details & History Tabs -->
    <ul class="nav nav-tabs nav-tabs-custom shadow-sm p-2 bg-light rounded-4" id="profileTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Overview</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="loans-tab" data-bs-toggle="tab" data-bs-target="#loans" type="button" role="tab">Loan History</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="txns-tab" data-bs-toggle="tab" data-bs-target="#txns" type="button" role="tab">Transactions</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="kyc-tab" data-bs-toggle="tab" data-bs-target="#kyc" type="button" role="tab">KYC & Documents</button>
        </li>
    </ul>

    <div class="tab-content" id="profileTabsContent">
        <!-- OVERVIEW TAB -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <div class="row">
                <div class="col-lg-4">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-4">Personal Information</h5>
                        <div class="mb-4">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?= htmlspecialchars($member['full_name']) ?></div>
                        </div>
                        <div class="mb-4">
                            <div class="info-label">National ID / Passport</div>
                            <div class="info-value"><?= $member['national_id'] ?></div>
                        </div>
                        <div class="mb-4">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?= $member['email'] ?></div>
                        </div>
                        <div class="mb-4">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?= $member['phone'] ?></div>
                        </div>
                        <hr class="opacity-10 my-4">
                        <div class="info-label">Risk Profile</div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <div class="flex-grow-1 progress" style="height: 8px; border-radius: 10px;">
                                <div class="progress-bar bg-success" style="width: 85%"></div>
                            </div>
                            <small class="fw-bold text-success">Low Risk</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="glass-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Recent Activity</h5>
                            <a href="payments.php?search=<?= urlencode($member['full_name']) ?>" class="btn btn-light btn-sm rounded-pill px-3">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="border-0 rounded-start">Date</th>
                                        <th class="border-0">Description</th>
                                        <th class="border-0">Reference</th>
                                        <th class="border-0 text-end rounded-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_txns as $tx): 
                                        $is_credit = in_array($tx['transaction_type'], ['deposit', 'repayment', 'income', 'loan_repayment', 'share_capital']);
                                    ?>
                                    <tr>
                                        <td class="small text-muted"><?= date('d M, Y', strtotime($tx['created_at'])) ?></td>
                                        <td>
                                            <div class="fw-bold"><?= ucwords(str_replace('_', ' ', $tx['transaction_type'])) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($tx['notes']) ?></small>
                                        </td>
                                        <td class="small fw-semibold"><?= $tx['reference_no'] ?></td>
                                        <td class="text-end fw-bold text-<?= $is_credit?'success':'danger' ?>">
                                            <?= $is_credit?'+':'-' ?><?= number_format((float)$tx['amount']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($recent_txns)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No recent transactions.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LOANS TAB -->
        <div class="tab-pane fade" id="loans" role="tabpanel">
            <div class="glass-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Loan History</h5>
                    <button class="btn btn-forest btn-sm rounded-pill px-4 fw-bold">Apply For Loan</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>TYPE</th>
                                <th>PRINCIPAL</th>
                                <th>BALANCE</th>
                                <th>STATUS</th>
                                <th>DATE</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($member_loans as $l): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= $l['loan_type'] ?></div>
                                    <small class="text-muted"><?= $l['interest_rate'] ?>% Interest</small>
                                </td>
                                <td class="fw-bold">KES <?= number_format((float)$l['amount']) ?></td>
                                <td class="fw-bold text-forest">KES <?= number_format((float)$l['current_balance']) ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-<?= $l['status']=='disbursed'?'success':($l['status']=='pending'?'warning':'secondary') ?> bg-opacity-10 text-<?= $l['status']=='disbursed'?'success':($l['status']=='pending'?'warning':'secondary') ?> px-3">
                                        <?= strtoupper($l['status']) ?>
                                    </span>
                                </td>
                                <td class="small"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                                <td>
                                    <a href="loans.php?id=<?= $l['loan_id'] ?>" class="btn btn-light btn-sm rounded-pill border"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($member_loans)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No loan history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TRANSACTIONS TAB -->
        <div class="tab-pane fade" id="txns" role="tabpanel">
             <div class="glass-card p-0 overflow-hidden">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Full Transaction Ledger</h5>
                    <button class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="bi bi-download me-2"></i>Export CSV</button>
                </div>
                <!-- This could be loaded via AJAX for better performance if too large -->
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-info-circle me-2"></i> Use the Financial Intelligence reports for advanced filtering.
                    <br><br>
                    <a href="payments.php?search=<?= urlencode($member['full_name']) ?>" class="btn btn-forest rounded-pill px-4">Go to Payments Ledger</a>
                </div>
             </div>
        </div>

        <!-- KYC TAB -->
        <div class="tab-pane fade" id="kyc" role="tabpanel">
            <div class="row g-4">
                <?php foreach($member_docs as $doc): ?>
                <div class="col-md-6">
                    <div class="glass-card">
                        <h6 class="fw-bold mb-3 text-uppercase"><?= str_replace('_', ' ', $doc['document_type']) ?></h6>
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-4 border">
                            <div class="icon-box bg-white shadow-sm mb-0"><i class="bi bi-file-earmark-text"></i></div>
                            <div class="flex-grow-1">
                                <div class="fw-bold small"><?= htmlspecialchars($doc['file_path']) ?></div>
                                <div class="text-muted x-small">Uploaded: <?= date('d M Y', strtotime($doc['uploaded_at'])) ?></div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= BASE_URL ?>/uploads/kyc/<?= $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3">View</a>
                                <span class="badge bg-<?= $doc['status'] == 'verified' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning') ?> bg-opacity-10 text-<?= $doc['status'] == 'verified' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning') ?> d-flex align-items-center"><?= strtoupper($doc['status']) ?></span>
                            </div>
                        </div>
                        <?php if ($doc['status'] === 'pending'): ?>
                            <div class="mt-3 p-3 bg-white rounded-3 border">
                                <form method="POST" class="d-flex align-items-center gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="doc_id" value="<?= $doc['document_id'] ?>">
                                    <input type="text" name="verification_notes" class="form-control form-control-sm" placeholder="Notes (required if rejecting)">
                                    <button type="submit" name="kyc_action" value="verify" class="btn btn-success btn-sm fw-bold rounded-pill px-3">Verify</button>
                                    <button type="submit" name="kyc_action" value="reject" class="btn btn-danger btn-sm fw-bold rounded-pill px-3">Reject</button>
                                </form>
                            </div>
                        <?php elseif ($doc['verification_notes']): ?>
                            <div class="mt-2 x-small text-muted ps-2 border-start ms-4">
                                <strong>Notes:</strong> <?= esc($doc['verification_notes'] ?? '') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($member_docs)): ?>
                <div class="col-12">
                    <div class="glass-card text-center py-5">
                        <i class="bi bi-shield-exclamation text-muted fs-1 mb-3 d-block"></i>
                        <h5 class="text-muted">No KYC documents found.</h5>
                        <p class="text-secondary small">The member hasn't uploaded any verification documents yet.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>







