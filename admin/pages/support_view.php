<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

// usms/admin/support_view.php
// IT Admin - View Ticket Details (Hope UI Edition)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../inc/email.php'; 
require_once __DIR__ . '/../../inc/sms.php';
// Auth Check
require_admin();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');
require_permission();

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['full_name'] ?? 'IT Support'; 
$db = $conn;

// Fetch Ticket
$support_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($support_id < 1) { header("Location: support.php"); exit; }

$sql = "SELECT s.*, m.full_name AS member_name, m.email AS member_email, 
               a.full_name AS admin_name, a.email AS admin_email
        FROM support_tickets s
        LEFT JOIN members m ON s.member_id = m.member_id
        LEFT JOIN admins a  ON s.member_id = 0 AND s.admin_id = a.admin_id
        WHERE s.support_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $support_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) { header("Location: support.php?msg=NotFound"); exit; }

$creator_is_member = ($ticket['member_id'] > 0);
$creator_name      = $creator_is_member ? $ticket['member_name'] : $ticket['admin_name'];
$creator_email     = $creator_is_member ? $ticket['member_email'] : $ticket['admin_email'];
$member_id_target  = $ticket['member_id']; 

// Handle Reply Logic
$success = ""; $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply_msg  = trim($_POST['reply_message']);
    $new_status = $_POST['status'] ?? "Pending";

    if ($reply_msg === "") {
        $error = "Reply cannot be empty.";
    } else {
        $db->begin_transaction();
        try {
            // 1. Record the reply in support_replies (Legacy compatibility)
            $stmt_rep = $db->prepare("INSERT INTO support_replies (support_id, sender_type, sender_id, message, user_role) VALUES (?, 'admin', ?, ?, 'admin')");
            $stmt_rep->bind_param("iis", $support_id, $admin_id, $reply_msg);
            $stmt_rep->execute();

            // 2. Integration: Push to Messages System
            $subject_chat = "Re: Ticket #$support_id - " . $ticket['subject'];
            $stmt_msg = $db->prepare("INSERT INTO messages (from_admin_id, to_member_id, subject, body, sent_at, is_read) VALUES (?, ?, ?, ?, NOW(), 0)");
            $stmt_msg->bind_param("iiss", $admin_id, $member_id_target, $subject_chat, $reply_msg);
            $stmt_msg->execute();

            // 3. Update Ticket Status
            $stmt_stat = $db->prepare("UPDATE support_tickets SET status = ?, is_resolved = ? WHERE support_id = ?");
            $is_res_flag = ($new_status === 'Closed') ? 1 : 0;
            $stmt_stat->bind_param("sii", $new_status, $is_res_flag, $support_id);
            $stmt_stat->execute();

            $db->commit();
            header("Location: support_view.php?id=$support_id&msg=success");
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'success') $success = "Reply transmitted successfully.";

// Fetch Conversation
$conversation = [];
$sql_chat = "SELECT r.message, r.created_at, r.sender_type,
             CASE WHEN r.sender_type='admin' THEN a.full_name ELSE m.full_name END as sender_name
             FROM support_replies r
             LEFT JOIN admins a ON r.sender_type='admin' AND r.sender_id = a.admin_id
             LEFT JOIN members m ON r.sender_type='member' AND r.sender_id = m.member_id
             WHERE r.support_id = ? ORDER BY r.created_at ASC";
$stmt_chat = $db->prepare($sql_chat);
$stmt_chat->bind_param("i", $support_id);
$stmt_chat->execute();
$res_chat = $stmt_chat->get_result();
while($row = $res_chat->fetch_assoc()) { $conversation[] = $row; }

function getInitials($name) { return strtoupper(substr($name ?? 'U', 0, 1)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket #<?= $support_id ?> | Helpdesk</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (() => {
            const saved = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', saved);
        })();
    </script>
    
    <style>
        :root {
            --hope-bg: #f3f4f6;
            --hope-green-dark: #102a1e;
            --hope-lime: #bef264;
            --hope-border: #e5e7eb;
        }
        
        body { 
            background: var(--hope-bg); 
            color: #111827; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
        }

        .hope-card {
            background: #fff;
            border-radius: 28px;
            border: 1px solid var(--hope-border);
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            overflow: hidden;
        }

        /* Chat UI */
        .chat-area {
            background: #fafafa;
            border-radius: 20px;
            padding: 25px;
            max-height: 500px;
            overflow-y: auto;
        }

        .msg-bubble {
            max-width: 80%;
            padding: 14px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            font-size: 0.92rem;
            position: relative;
        }

        .msg-member {
            background: #fff;
            color: #374151;
            border: 1px solid var(--hope-border);
            border-bottom-left-radius: 4px;
            margin-right: auto;
        }

        .msg-admin {
            background: var(--hope-green-dark);
            color: #fff;
            border-bottom-right-radius: 4px;
            margin-left: auto;
        }

        .avatar-circle {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.75rem;
        }

        .btn-hope-lime {
            background: var(--hope-lime);
            color: var(--hope-green-dark);
            border-radius: 50px;
            font-weight: 700;
            padding: 12px 30px;
            border: none;
            transition: transform 0.2s;
        }
        .btn-hope-lime:hover { transform: scale(1.02); background: #a3e635; }

        .sidebar-space { margin-left: 260px; }
        @media (max-width: 991px) { .sidebar-space { margin-left: 0; } }
    </style>
</head>
<body>

<div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>
            
            <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <a href="support.php" class="text-decoration-none text-muted small fw-bold"><i class="bi bi-chevron-left"></i> BACK TO QUEUE</a>
                    <h2 class="fw-bold mt-2">Ticket #<?= $support_id ?></h2>
                </div>
                <div class="text-end">
                    <span class="badge rounded-pill px-3 py-2 bg-white text-dark border fw-bold"><?= strtoupper($ticket['status']) ?></span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="hope-card p-4">
                        <div class="mb-4 pb-3 border-bottom">
                            <h4 class="fw-bold text-dark"><?= htmlspecialchars($ticket['subject']) ?></h4>
                            <p class="text-muted small mb-0">Original request filed by <?= htmlspecialchars($creator_name) ?></p>
                        </div>

                        <div class="chat-area mb-4" id="chatBox">
                            <div class="d-flex gap-3 mb-4">
                                <div class="avatar-circle bg-light border text-dark"><?= getInitials($creator_name) ?></div>
                                <div class="msg-bubble msg-member shadow-sm">
                                    <?= nl2br(htmlspecialchars($ticket['message'])) ?>
                                    <?php if($ticket['attachment']): ?>
                                        <div class="mt-2 pt-2 border-top small">
                                            <a href="<?= BASE_URL . '/' . htmlspecialchars($ticket['attachment']) ?>" target="_blank" class="text-primary fw-bold">
                                                <i class="bi bi-file-earmark-arrow-down"></i> Download Attachment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-end small opacity-50 mt-1" style="font-size:0.7rem;"><?= date('H:i A', strtotime($ticket['created_at'])) ?></div>
                                </div>
                            </div>

                            <?php foreach ($conversation as $msg): 
                                $is_admin = ($msg['sender_type'] === 'admin');
                            ?>
                                <div class="d-flex gap-3 <?= $is_admin ? 'flex-row-reverse' : '' ?> mb-3">
                                    <div class="avatar-circle <?= $is_admin ? 'bg-success text-white' : 'bg-light border' ?>">
                                        <?= getInitials($msg['sender_name']) ?>
                                    </div>
                                    <div class="msg-bubble <?= $is_admin ? 'msg-admin' : 'msg-member shadow-sm' ?>">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        <div class="small opacity-75 mt-1 <?= $is_admin ? 'text-start' : 'text-end' ?>" style="font-size:0.7rem;">
                                            <?= date('M d, H:i', strtotime($msg['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bg-light p-4 rounded-4">
                            <?php if($success): ?>
                                <div class="alert alert-success border-0 rounded-3 small"><?= $success ?></div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <textarea name="reply_message" class="form-control border-0 rounded-4 shadow-sm mb-3 p-3" rows="4" placeholder="Type your response to the member..." required></textarea>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="small fw-bold text-muted">Update Status:</span>
                                        <select name="status" class="form-select form-select-sm border-0 shadow-sm rounded-pill px-3">
                                            <option value="Pending" <?= $ticket['status']=='Pending'?'selected':'' ?>>Pending</option>
                                            <option value="Open" <?= $ticket['status']=='Open'?'selected':'' ?>>Open</option>
                                            <option value="Closed" <?= $ticket['status']=='Closed'?'selected':'' ?>>Closed</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-hope-lime">
                                        Send Reply <i class="bi bi-send ms-2"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="hope-card p-4 text-center mb-4 border-0" style="background: var(--hope-green-dark); color: #fff;">
                        <div class="avatar-circle mx-auto mb-3" style="width:70px; height:70px; background: var(--hope-lime); color: var(--hope-green-dark); font-size: 1.5rem;">
                            <?= getInitials($creator_name) ?>
                        </div>
                        <?php if($creator_is_member): ?>
                            <a href="member_profile.php?id=<?= $ticket['member_id'] ?>" class="text-decoration-none text-white">
                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($creator_name) ?> <i class="bi bi-arrow-up-right-circle small ms-1"></i></h5>
                            </a>
                        <?php else: ?>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($creator_name) ?></h5>
                        <?php endif; ?>
                        <p class="small opacity-75 mb-3"><?= $creator_is_member ? 'OFFICIAL MEMBER' : 'ADMIN STAFF' ?></p>
                        <hr class="opacity-25">
                        <div class="d-grid gap-2">
                            <a href="mailto:<?= htmlspecialchars($creator_email) ?>" class="btn btn-sm btn-outline-light rounded-pill border-opacity-25">
                                <i class="bi bi-envelope me-2"></i> Email Contact
                            </a>
                        </div>
                    </div>

                    <div class="hope-card p-4">
                        <h6 class="fw-bold text-uppercase small text-muted mb-4">Ticket Details</h6>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted small">Reference</span>
                            <span class="fw-bold small">#<?= $support_id ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted small">Submitted</span>
                            <span class="fw-bold small"><?= date('d M, Y', strtotime($ticket['created_at'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Category</span>
                            <span class="fw-bold small text-primary">Technical Support</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php $layout->footer(); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chatBox = document.getElementById('chatBox');
        if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;

        window.addEventListener('themeChanged', (e) => {
            document.documentElement.setAttribute('data-bs-theme', e.detail.theme);
        });
    });
</script>
</body>
</html>






