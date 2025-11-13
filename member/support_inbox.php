<?php
// member/support_inbox.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$member_id = $_SESSION['member_id'];

$tickets = $conn->query("
    SELECT * FROM support_tickets 
    WHERE member_id = $member_id 
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Support Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">

<h3 class="mb-4">My Support Tickets</h3>

<a href="dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>
<a href="../public/support.php" class="btn btn-success mb-3">+ Open New Ticket</a>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>#</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>View</th>
        </tr>
    </thead>
    <tbody>
    <?php while($t = $tickets->fetch_assoc()): ?>
        <tr>
            <td><?= $t['support_id'] ?></td>
            <td><?= htmlspecialchars($t['subject']) ?></td>
            <td><span class="badge bg-info"><?= $t['status'] ?></span></td>
            <td><?= $t['created_at'] ?></td>
            <td>
                <a href="../public/support_reply.php?id=<?= $t['support_id'] ?>" class="btn btn-sm btn-primary">Open</a>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>