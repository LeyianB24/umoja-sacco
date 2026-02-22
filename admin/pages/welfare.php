<?php
/**
 * admin/welfare.php
 * Unified Welfare Management Suite
 * Combines Case Management, Crowdfunding, and Direct Pool Disbursements.
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Dependencies ---
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';
require_once __DIR__ . '/../../inc/FinancialEngine.php';

// --- Security ---
require_permission();
Auth::requireAdmin();
$layout = LayoutManager::create('admin');
$admin_id = $_SESSION['admin_id'];

$pageTitle = "Welfare Management";

// --- Data Aggregation ---
$filter = $_GET['filter'] ?? 'all';
$cases = $conn->query("SELECT c.*, m.full_name, m.phone, m.national_id, m.profile_pic FROM welfare_cases c JOIN members m ON c.related_member_id = m.member_id WHERE c.status = '$filter' OR '$filter' = 'all' ORDER BY c.created_at DESC");

// Fetch stats
$stats = $conn->query("SELECT 
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending, 
    COUNT(CASE WHEN status='active' THEN 1 END) as active, 
    SUM(CASE WHEN status='disbursed' OR status='funded' THEN total_disbursed ELSE 0 END) as total_disbursed, 
    SUM(CASE WHEN status='active' OR status='funded' OR status='disbursed' THEN total_raised ELSE 0 END) as total_raised 
FROM welfare_cases")->fetch_assoc();

// Fetch pool balance using Golden Ledger
$engine = new FinancialEngine($conn);
$pool_balance = $engine->getWelfarePoolBalance();
?>
<?php $layout->header($pageTitle); ?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }

        .glass-card { 
            background: white; border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            overflow: hidden;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .icon-box { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .nav-tabs-custom { border: none; display: flex; gap: 10px; padding: 0.5rem 0.5rem 0 0.5rem; }
        .nav-tabs-custom .nav-link { border: none; border-radius: 10px; padding: 8px 16px; font-weight: 700; color: var(--forest); }
        .nav-tabs-custom .nav-link.active { background: var(--forest); color: white; }

        .badge-pending { background: rgba(217, 119, 6, 0.1); color: #d97706; }
        .badge-active { background: rgba(15, 46, 37, 0.05); color: var(--forest); }
        .badge-approved { background: rgba(208, 243, 93, 0.1); color: var(--forest-mid); }
        .badge-rejected { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper p-0">
        <?php $layout->topbar($pageTitle ?? 'Benevolence Fund'); ?>
        
        <div class="container-fluid">
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="glass-card p-3 h-100 bg-forest text-white overflow-hidden position-relative mb-0 border-0 shadow-lg">
                        <div class="position-absolute end-0 bottom-0 opacity-10 p-2"><i class="bi bi-safe2 display-1"></i></div>
                        <div class="d-flex justify-content-between align-items-start z-1 position-relative">
                            <div>
                                <div class="text-white-50 text-uppercase small fw-bold mb-0">Welfare Pool</div>
                                <h3 class="fw-bold mb-0"><?= ksh($pool_balance) ?></h3>
                            </div>
                            <div class="icon-box bg-white bg-opacity-25"><i class="bi bi-wallet2 text-white"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="glass-card p-3 h-100 mb-0">
                        <div class="d-flex justify-content-between">
                            <div><div class="text-muted text-uppercase small fw-bold">Active Cases</div><h3 class="fw-bold mb-0 "><?= $stats['active'] ?></h3></div>
                            <div class="icon-box bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="glass-card p-3 h-100 mb-0">
                        <div class="d-flex justify-content-between">
                            <div><div class="text-muted text-uppercase small fw-bold">Total Donated</div><h3 class="fw-bold mb-0 "><?= ksh($stats['total_raised']) ?></h3></div>
                            <div class="icon-box bg-info bg-opacity-10 text-info"><i class="bi bi-heart-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="glass-card p-3 h-100 mb-0">
                        <div class="d-flex justify-content-between">
                            <div><div class="text-muted text-uppercase small fw-bold">Total Disbursed</div><h3 class="fw-bold mb-0 "><?= ksh($stats['total_disbursed']) ?></h3></div>
                            <div class="icon-box bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle-fill"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php flash_render(); ?>

            <div class="glass-card p-0">
                <div class="border-bottom px-4 d-flex justify-content-between align-items-center py-3">
                    <ul class="nav nav-tabs-custom">
                        <li class="nav-item"><a class="nav-link <?= $filter=='all'?'active':''?>" href="?filter=all">All Cases</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter=='pending'?'active':''?>" href="?filter=pending">Review Queue</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter=='active'?'active':''?>" href="?filter=active">Crowdfunding</a></li>
                    </ul>
                    <button class="btn btn-forest rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                        <i class="bi bi-plus-lg me-1"></i> New Case
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Beneficiary</th>
                                <th>Type / Title</th>
                                <th>Goal / Raised</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($cases->num_rows > 0): while($row = $cases->fetch_assoc()): 
                                $status_class = match($row['status']) {
                                    'pending' => 'badge-pending',
                                    'active' => 'badge-active',
                                    'approved' => 'badge-approved',
                                    'disbursed', 'funded' => 'badge-active',
                                    'rejected' => 'badge-rejected',
                                    default => 'bg-light text-dark'
                                };
                                $avatar = !empty($row['profile_pic']) ? 'data:image/jpeg;base64,'.base64_encode($row['profile_pic']) : BASE_URL.'/public/assets/images/default_user.png';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= $avatar ?>" class="rounded-circle me-3" width="35" height="35" style="object-fit:cover;">
                                        <div><div class="fw-bold "><?= $row['full_name'] ?></div><div class="small text-muted"><?= $row['phone'] ?></div></div>
                                    </div>
                                </td>
                                <td><div class="fw-bold "><?= ucfirst($row['case_type']) ?></div><div class="small text-muted text-truncate" style="max-width:200px;"><?= $row['title'] ?></div></td>
                                <td>
                                    <?php if($row['target_amount'] > 0): ?>
                                        <div class="fw-bold"><?= ksh($row['total_raised']) ?></div><small class="text-muted">Goal: <?= ksh($row['target_amount']) ?></small>
                                    <?php else: ?>
                                        <div class="fw-bold">Pool Grant</div><small class="text-muted">Req: <?= ksh($row['requested_amount']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $status_class ?> rounded-pill px-3"><?= ucfirst($row['status']) ?></span></td>
                                <td class="text-end pe-4">
                                    <?php if($row['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-dark rounded-pill px-3" onclick='openProcessModal(<?= json_encode($row) ?>)'>Review</button>
                                    <?php elseif($row['status'] === 'approved'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="disburse_case">
                                            <input type="hidden" name="case_id" value="<?= $row['case_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3" onclick="return confirm('Disburse funds to member wallet?')">Disburse</button>
                                        </form>
                                    <?php elseif($row['status'] === 'active' || $row['status'] === 'funded'): ?>
                                        <div class="d-flex justify-content-end gap-1">
                                            <?php if($row['status'] === 'active'): ?>
                                                <button class="btn btn-sm btn-outline-forest rounded-pill px-3" onclick='openDonationModal(<?= json_encode($row) ?>)'>Donation</button>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="disburse_donations">
                                                <input type="hidden" name="case_id" value="<?= $row['case_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3" onclick="return confirm('Close case and disburse collections?')">Disburse</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light border px-3 rounded-pill" onclick='openViewer(<?= json_encode($row) ?>)'>Details</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No welfare cases found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php $layout->footer(); ?>
        </div>
    </div>
</div>

<!-- Modal: New Case -->
<div class="modal fade" id="newCaseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <input type="hidden" name="action" value="create_case">
            <?= csrf_field() ?>
            <div class="modal-header bg-forest text-white p-4">
                <h5 class="modal-title fw-800">Log Welfare Case</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Beneficiary</label>
                    <select name="member_id" class="form-select form-select-lg" required>
                        <option value="">-- Choose Member --</option>
                        <?php 
                        $mems = $conn->query("SELECT member_id, full_name, national_id FROM members WHERE status='active'");
                        while($m = $mems->fetch_assoc()): ?>
                            <option value="<?= $m['member_id'] ?>"><?= $m['full_name'] ?> (<?= $m['national_id'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Case Type</label>
                        <select name="type" class="form-select form-select-lg" required>
                            <option value="sickness">Sickness</option>
                            <option value="bereavement">Bereavement</option>
                            <option value="education">Education</option>
                            <option value="accident">Accident</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Funding Goal (Crowdfunding)</label>
                    <div class="input-group input-group-lg"><span class="input-group-text bg-light border-end-0">KES</span><input type="number" name="target_amount" class="form-control border-start-0" placeholder="Optional for grants"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Requested Pool Amount (Grants)</label>
                    <div class="input-group input-group-lg"><span class="input-group-text bg-light border-end-0">KES</span><input type="number" name="requested_amount" class="form-control border-start-0" placeholder="0.00"></div>
                </div>
                <div class="mb-3"><label class="form-label small fw-bold">Title</label><input type="text" name="title" class="form-control form-control-lg" required></div>
                <div class="mb-3"><label class="form-label small fw-bold">Description</label><textarea name="description" class="form-control" rows="3" required></textarea></div>
            </div>
            <div class="modal-footer bg-white border-0 p-4 pt-0">
                <button type="submit" class="btn btn-forest w-100 fw-bold py-3 rounded-pill shadow-lg">Submit for Review</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Process (Approve/Reject) -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <input type="hidden" name="action" value="approve_case" id="proc_action">
            <?= csrf_field() ?>
            <input type="hidden" name="case_id" id="proc_case_id">
            <div class="modal-header bg-success text-white p-4">
                <h5 class="modal-title fw-800">Review Case</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <div id="pool_approval_section">
                    <div class="mb-3 text-center"><small class="text-muted">Requested Amount</small><h2 class="fw-bold" id="proc_req_amt">KES 0.00</h2></div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Approve for Pool Payout</label>
                        <div class="input-group input-group-lg"><span class="input-group-text bg-light border-end-0">KES</span><input type="number" name="approved_amount" id="proc_app_amt" class="form-control border-start-0"></div>
                    </div>
                    <div class="mb-3"><label class="form-label small fw-bold">Admin Notes</label><textarea name="admin_notes" class="form-control" rows="2"></textarea></div>
                    <button type="submit" class="btn btn-success w-100 fw-bold py-3 rounded-pill shadow-lg mb-2">Confirm Pool Payout</button>
                    <hr>
                    <button type="button" class="btn btn-outline-danger btn-sm w-100 border-0 fw-bold" onclick="switchReject()">Reject Instead</button>
                </div>
                <div id="reject_section" class="d-none">
                    <h6 class="fw-bold text-danger mb-3">Rejection Reason</h6>
                    <textarea name="reason" class="form-control mb-4" rows="3" placeholder="Provide reason for rejection..."></textarea>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" onclick="location.reload()">Cancel</button>
                        <button type="button" class="btn btn-danger flex-fill rounded-pill fw-bold" onclick="confirmReject()">Confirm Rejection</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Donation -->
<div class="modal fade" id="donationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <input type="hidden" name="action" value="record_donation">
            <?= csrf_field() ?>
            <input type="hidden" name="case_id" id="don_case_id">
            <div class="modal-header bg-forest text-white p-4"><h5 class="modal-title fw-800">Record Donation</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 bg-white">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Donor Member (Optional)</label>
                    <select name="donor_member_id" class="form-select form-select-lg">
                        <option value="0">-- Anonymous / External --</option>
                        <?php 
                        $mems2 = $conn->query("SELECT member_id, full_name FROM members WHERE status='active'");
                        while($m = $mems2->fetch_assoc()): ?>
                            <option value="<?= $m['member_id'] ?>"><?= $m['full_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label small fw-bold">Amount</label><div class="input-group input-group-lg"><span class="input-group-text bg-light border-end-0">KES</span><input type="number" name="amount" class="form-control border-start-0" required></div></div>
                <div class="mb-3"><label class="form-label small fw-bold">Reference / Notes</label><input type="text" name="reference_no" class="form-control form-control-lg" placeholder="M-Pesa Ref or Note"></div>
            </div>
            <div class="modal-footer bg-white border-0 p-4 pt-0"><button type="submit" class="btn btn-forest w-100 fw-bold py-3 rounded-pill shadow-lg">Record Donation</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openProcessModal(data) {
        document.getElementById('proc_case_id').value = data.case_id;
        document.getElementById('proc_req_amt').innerText = "KES " + new Intl.NumberFormat().format(data.requested_amount);
        document.getElementById('proc_app_amt').value = data.requested_amount;
        new bootstrap.Modal(document.getElementById('processModal')).show();
    }
    function openDonationModal(data) {
        document.getElementById('don_case_id').value = data.case_id;
        new bootstrap.Modal(document.getElementById('donationModal')).show();
    }
    function switchReject() {
        document.getElementById('pool_approval_section').classList.add('d-none');
        document.getElementById('reject_section').classList.remove('d-none');
    }
    function confirmReject() {
        if(confirm('Are you sure you want to reject this case?')) {
            document.getElementById('proc_action').value = 'reject';
            document.getElementById('processModal').querySelector('form').submit();
        }
    }
</script>
</body>
</html>
