<?php
// public/support.php
// Unified Support Center: Managed Support for Members & Admins
// Features: Intelligent Routing, Dark Mode, Mobile Friendly

if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/functions.php';

// Authentication Check
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$my_role = $_SESSION['role'];
$my_id = ($my_role === 'member') ? ($_SESSION['member_id'] ?? 0) : ($_SESSION['admin_id'] ?? 0);

$success = ""; $error = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    verify_csrf_token();
    
    $category = $_POST['category'] ?? 'general';
    $subject  = trim($_POST['subject'] ?? '');
    $message  = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    if (empty($subject) || empty($message)) {
        $error = "Subject and message are required.";
    } else {
        // Intelligent Routing
        // 1. Loan Issue -> Manager
        // 2. Accounting -> Accountant
        // 3. Tech/General -> IT Admin
        
        $assigned_role = match($category) {
            'loan'       => 'manager',
            'accounting' => 'accountant',
            default      => 'admin' // IT Admin
        };

        // Find an admin with the specific role (fallback to first superadmin if none found)
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE role = ? LIMIT 1");
        $stmt->bind_param("s", $assigned_role);
        $stmt->execute();
        $admin_res = $stmt->get_result()->fetch_assoc();
        $assigned_admin = $admin_res['admin_id'] ?? 1; // Fallback to Admin #1

        // Handle Attachment
        $attachment = null;
        if (!empty($_FILES['attachment']['name'])) {
            $dir = __DIR__ . '/uploads/support/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $newName = uniqid('sup_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $newName)) {
                $attachment = 'public/uploads/support/' . $newName;
            }
        }

        $sql = "INSERT INTO support_tickets (admin_id, member_id, subject, message, category, priority, status, attachment, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'Open', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $member_id_field = ($my_role === 'member') ? $my_id : 0;
        $stmt->bind_param("iisssss", $assigned_admin, $member_id_field, $subject, $message, $category, $priority, $attachment);
        
        if ($stmt->execute()) {
            $success = "Ticket #" . $stmt->insert_id . " created successfully! It has been routed to our " . ucfirst($assigned_role) . ".";
        } else {
            $error = "System error. Please try again.";
        }
    }
}

// Fetch Existing Tickets
$tickets = [];
$sql_history = ($my_role === 'member') 
    ? "SELECT * FROM support_tickets WHERE member_id = ? ORDER BY created_at DESC"
    : "SELECT * FROM support_tickets WHERE admin_id = ? OR category = 'general' ORDER BY created_at DESC";

$stmt = $conn->prepare($sql_history);
$stmt->bind_param("i", $my_id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Support Center";
require_once __DIR__ . '/../inc/header.php'; 
?>

<div class="container-fluid py-4 px-lg-5">
    <div class="row g-4">
        <!-- New Ticket Column -->
        <div class="col-lg-5">
            <div class="card-premium h-100">
                <div class="mb-4">
                    <h3 class="fw-bold mb-1">Need Help?</h3>
                    <p class="text-muted">Raise a support ticket and our team will get back to you.</p>
                </div>

                <?php if($success): ?>
                    <div class="alert alert-success border-0 rounded-4 mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="alert alert-danger border-0 rounded-4 mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submit_ticket" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Department / Category</label>
                            <select name="category" class="form-select border-0 bg-light rounded-3 p-3" required>
                                <option value="general">General Inquiry</option>
                                <option value="loan">Loan & Credit Department</option>
                                <option value="accounting">Financial & Payments</option>
                                <option value="tech">Technical Issue</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Priority Level</label>
                            <select name="priority" class="form-select border-0 bg-light rounded-3 p-3">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Subject</label>
                            <input type="text" name="subject" class="form-control border-0 bg-light rounded-3 p-3" placeholder="Brief summary of the issue" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Detailed Message</label>
                            <textarea name="message" class="form-control border-0 bg-light rounded-3 p-3" rows="5" placeholder="Describe the issue in detail..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Attachment (Optional, Max 5MB)</label>
                            <input type="file" name="attachment" class="form-control border-0 bg-light rounded-3 p-3">
                        </div>
                        <div class="col-12 pt-3">
                            <button type="submit" class="btn btn-dark bg-brand-forest w-100 py-3 rounded-pill fw-bold border-0 shadow-sm transition-all hover-up">
                                Submit Ticket <i class="bi bi-send-fill ms-2"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Ticket History Column -->
        <div class="col-lg-7">
            <div class="card-premium h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0">Your Tickets</h4>
                    <span class="badge bg-light text-dark rounded-pill px-3 py-2 border"><?= count($tickets) ?> Total</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0 px-4 py-3">Ticket</th>
                                <th class="border-0 py-3">Category</th>
                                <th class="border-0 py-3">Status</th>
                                <th class="border-0 py-3 text-end px-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($tickets)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-ticket-perforated fs-1 d-block opacity-25"></i>
                                        You haven't raised any tickets yet.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach($tickets as $t): 
                                $status_badge = match($t['status']) {
                                    'Open' => 'success',
                                    'Pending' => 'warning',
                                    'Closed' => 'secondary',
                                    default => 'info'
                                };
                            ?>
                            <tr>
                                <td class="px-4">
                                    <div class="fw-bold text-dark text-truncate" style="max-width: 250px;"><?= htmlspecialchars($t['subject'] ?? '') ?></div>
                                    <small class="text-muted">#<?= $t['support_id'] ?> &bull; <?= date('M d, Y', strtotime($t['created_at'])) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.7rem;"><?= htmlspecialchars($t['category'] ?? 'General') ?></span></td>
                                <td><span class="badge bg-<?= $status_badge ?> bg-opacity-10 text-<?= $status_badge ?> px-3 rounded-pill fw-bold" style="font-size: 0.75rem;"><?= $t['status'] ?></span></td>
                                <td class="text-end px-4">
                                    <a href="messages.php?chat_with=<?= $t['admin_id'] ?>&role=admin" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold">
                                        Chat <i class="bi bi-chat-dots ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-up:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    .transition-all { transition: all 0.3s ease; }
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
