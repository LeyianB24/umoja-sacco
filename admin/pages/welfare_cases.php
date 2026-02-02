<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');
// usms/admin/pages/welfare_cases.php

// 1. Auth Check (System Model V22 - Open Operations)

require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
Auth::requireAdmin();

$my_role_id = $_SESSION['role_id'] ?? 0;
// All admins can view, but only certain roles can create/close (logic preservation)
$can_edit = in_array($my_role_id, [1, 2, 5]); // Superadmin, Manager, Welfare Officer (based on prev list IDs)
// Actually, let's keep it simple as per prompt "All staff trusted to operate".
$can_edit = true; 


// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $error = '';

// --- HELPER: Send Email Notification ---
function sendWelfareNotification($conn, $member_id, $title, $description) {
    // Fetch member email
    $stmt = $conn->prepare("SELECT full_name, email FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($member = $res->fetch_assoc()) {
        $to = $member['email'];
        $subject = "Welfare Situation Created: " . $title;
        $message = "Dear " . htmlspecialchars($member['full_name']) . ",\n\n";
        $message .= "A new welfare situation has been created on your behalf regarding: " . $title . ".\n\n";
        $message .= "Details: " . $description . "\n\n";
        $message .= "Members can now view this case and contribute via the portal.\n\n";
        $message .= "Regards,\nUmoja Drivers Sacco Management";
        
        $headers = "From: no-reply@umojadrivers.co.ke";
        
        // Send email (Ensure your server is configured to send mail)
        @mail($to, $subject, $message, $headers);
    }
}

// 2. KPI Data
// Total Raised
$res = $conn->query("SELECT SUM(amount) as val FROM welfare_donations");
$total_raised = $res->fetch_assoc()['val'] ?? 0;

// Active Cases
$res = $conn->query("SELECT COUNT(*) as cnt FROM welfare_cases WHERE status='active'");
$active_count = $res->fetch_assoc()['cnt'] ?? 0;

// 3. Handle Create (Only Manager/Superadmin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_case'])) {
    // CSRF Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Invalid session token.";
    } elseif (!$can_edit) {
        $error = "Permission Denied.";
    } else {
        $title = trim($_POST['title']);
        $desc  = trim($_POST['description']);
        $target = floatval($_POST['target_amount']);
        $related_member_id = !empty($_POST['related_member_id']) ? intval($_POST['related_member_id']) : null;
        $admin_id = $_SESSION['admin_id'];

        if ($title && $target > 0 && $related_member_id) {
            $stmt = $conn->prepare("INSERT INTO welfare_cases (title, description, target_amount, created_by, related_member_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssdii", $title, $desc, $target, $admin_id, $related_member_id);
            
            if ($stmt->execute()) {
                $success = "Welfare Situation Published successfully.";
                $active_count++;
                
                // Send Email
                sendWelfareNotification($conn, $related_member_id, $title, $desc);
                
            } else {
                $error = "Database Error: " . $stmt->error;
            }
        } else {
            $error = "Please fill all fields and select a member.";
        }
    }
}

// 4. Handle Close
if (isset($_GET['close']) && $can_edit) {
    $id = intval($_GET['close']);
    $conn->query("UPDATE welfare_cases SET status='closed' WHERE case_id=$id");
    header("Location: welfare_cases.php");
    exit;
}

// 5. Fetch Cases (Joined with Members table to get names)
$sql = "SELECT c.*, m.full_name as member_name, m.member_id as m_id,
        (SELECT COALESCE(SUM(amount), 0) FROM welfare_donations WHERE case_id = c.case_id) as raised
        FROM welfare_cases c 
        LEFT JOIN members m ON c.related_member_id = m.member_id
        ORDER BY c.status ASC, c.created_at DESC";
$cases = $conn->query($sql);

// 6. Fetch Active Members for Dropdown
$members_res = $conn->query("SELECT member_id, full_name, national_id FROM members WHERE status='active' ORDER BY full_name ASC");

function ksh($val) { return number_format((float)($val ?? 0), 2); }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welfare Management</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Theme Colors extracted from images */
            --bg-app: #f0fdf4;       /* Very light mint background */
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: 1px solid rgba(22, 163, 74, 0.15);
            
            --text-dark: #064e3b;    /* Deep Emerald */
            --text-muted: #64748b;
            
            --primary-green: #10b981; /* Standard Green */
            --dark-green: #065f46;    /* Darker Green for text/headers */
            --lime-accent: #84cc16;   /* Lime accent for progress/buttons */
        }
        
        body { background-color: var(--bg-app); color: var(--text-dark); font-family: 'Inter', sans-serif; }
        
        .main-content-wrapper { margin-left: 260px; transition: margin-left 0.3s; padding: 20px; }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; } }

        /* Cards */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: var(--glass-border);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        /* Buttons */
        .btn-lime {
            background-color: var(--lime-accent);
            color: white;
            border: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-lime:hover { background-color: #65a30d; color: white; transform: translateY(-1px); }

        /* Badges */
        .badge-status { padding: 6px 12px; border-radius: 30px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; }
        .st-active { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .st-closed { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        /* Progress Bars */
        .progress-custom { height: 8px; border-radius: 10px; background-color: #e2e8f0; overflow: hidden; }
        .progress-bar-lime { background-color: var(--lime-accent); }
    </style>
</head>
<body>

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1" style="color: var(--dark-green);">Benevolent Fund</h4>
                    <p class="text-muted small mb-0">Manage welfare cases and track donations.</p>
                </div>
                <?php if ($can_edit): ?>
                    <button class="btn btn-lime rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                        <i class="bi bi-plus-lg me-2"></i> Create Situation
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($success) echo "<div class='alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3 mb-4'><i class='bi bi-check-circle me-2'></i>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-4'><i class='bi bi-exclamation-circle me-2'></i>$error</div>"; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-4">
                    <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase small text-muted fw-bold ls-1 mb-1">Active Cases</div>
                            <h2 class="fw-bold mb-0" style="color: var(--dark-green);"><?= $active_count ?></h2>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:50px; height:50px; background: #dcfce7; color: var(--primary-green);">
                            <i class="bi bi-heart-pulse-fill fs-4"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="glass-card p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-uppercase small text-muted fw-bold ls-1 mb-1">Total Funds Raised</div>
                            <h2 class="fw-bold mb-0" style="color: var(--dark-green);">KES <?= number_format((float)($total_raised/1000), 1) ?>K</h2>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:50px; height:50px; background: #ecfccb; color: #65a30d;">
                            <i class="bi bi-currency-exchange fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase" style="color: var(--dark-green);">
                            <tr>
                                <th class="ps-4 py-3">Beneficiary</th>
                                <th>Situation Details</th>
                                <th>Financials</th>
                                <th style="width: 20%;">Progress</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            <?php if($cases->num_rows === 0): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No welfare cases found.</td></tr>
                            <?php else: while ($row = $cases->fetch_assoc()): 
                                $target = $row['target_amount'];
                                $raised = $row['raised'];
                                $percent = ($target > 0) ? ($raised / $target) * 100 : 0;
                                $statusClass = ($row['status'] == 'active') ? 'st-active' : 'st-closed';
                                $beneficiary = $row['member_name'] ? $row['member_name'] : 'General Case';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center fw-bold text-success" style="width:35px;height:35px;">
                                            <?= strtoupper(substr($beneficiary, 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($beneficiary) ?></div>
                                            <?php if($row['m_id']): ?>
                                                <small class="text-muted" style="font-size: 0.75rem;">ID: <?= str_pad($row['m_id'], 4, '0', STR_PAD_LEFT) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['title']) ?></div>
                                    <small class="text-muted d-block text-truncate" style="max-width: 250px;">
                                        <?= htmlspecialchars($row['description']) ?>
                                    </small>
                                    <small class="text-muted opacity-75" style="font-size: 0.75rem;">
                                        <i class="bi bi-calendar3 me-1"></i> <?= date('M d, Y', strtotime($row['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="small text-muted">Goal: <span class="fw-semibold text-dark">KES <?= ksh($target) ?></span></span>
                                        <span class="small text-success fw-bold">Raised: KES <?= ksh($raised) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-bold text-success"><?= number_format((float)$percent, 0) ?>%</span>
                                    </div>
                                    <div class="progress progress-custom">
                                        <div class="progress-bar progress-bar-lime" role="progressbar" style="width: <?= min(100, $percent) ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($can_edit && $row['status'] == 'active'): ?>
                                        <a href="?close=<?= $row['case_id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Close this case? No further donations will be accepted.')">
                                            Close
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light rounded-pill text-muted border px-3" disabled>
                                            <i class="bi bi-lock-fill"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<?php if ($can_edit): ?>
<div class="modal fade" id="newCaseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-success bg-opacity-10">
                <h5 class="modal-title fw-bold" style="color: var(--dark-green);">Create Welfare Situation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Select Member (Beneficiary)</label>
                    <select name="related_member_id" class="form-select" required>
                        <option value="" selected disabled>Choose a member...</option>
                        <?php while($m = $members_res->fetch_assoc()): ?>
                            <option value="<?= $m['member_id'] ?>">
                                <?= htmlspecialchars($m['full_name']) ?> (ID: <?= htmlspecialchars($m['national_id']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="form-text small">This member will receive an email notification.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Situation Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Medical Support..." required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Explain the situation clearly..." required></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Target Amount</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold text-success">KES</span>
                        <input type="number" name="target_amount" class="form-control fw-bold" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="create_case" class="btn btn-lime rounded-pill px-4 shadow-sm">Publish & Notify</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





