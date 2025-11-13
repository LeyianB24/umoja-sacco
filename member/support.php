<?php
session_start();
include('../config/db_connect.php');
include('../inc/header.php');

if (!isset($_SESSION['member_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$success_message = $error_message = "";

// Handle ticket submission
if (isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($subject) || empty($message)) {
        $error_message = "Please fill in all fields.";
    } else {
        $insert = "INSERT INTO support_tickets (member_id, subject, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("iss", $member_id, $subject, $message);

        if ($stmt->execute()) {
            $success_message = "Your support request has been sent successfully!";
        } else {
            $error_message = "Error submitting support request. Try again.";
        }
    }
}

// Fetch user's previous tickets
$tickets = $conn->query("SELECT * FROM support_tickets WHERE member_id = $member_id ORDER BY created_at DESC");
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="text-center mb-4"><i class="fas fa-hand-holding-usd me-2"></i>Support Center</h3>
    <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="dashboard.php" class="btn btn-outline-success">
        <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
    </a>
    </div>
</div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Submit Ticket -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <strong>Submit a Support Ticket</strong>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label>Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="Enter issue subject" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Message</label>
                            <textarea name="message" class="form-control" rows="5" placeholder="Describe your issue in detail" required></textarea>
                        </div>
                        <button type="submit" name="submit_ticket" class="btn btn-success w-100">Send Support Request</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Tickets -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <strong>My Previous Tickets</strong>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if ($tickets->num_rows > 0): ?>
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ticket = $tickets->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ticket['subject']) ?></td>
                                        <td>
                                            <?php if ($ticket['status'] == 'Resolved'): ?>
                                                <span class="badge bg-success">Resolved</span>
                                            <?php elseif ($ticket['status'] == 'In Progress'): ?>
                                                <span class="badge bg-warning text-dark">In Progress</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($ticket['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">You haven't submitted any support requests yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../inc/footer.php'); ?>
