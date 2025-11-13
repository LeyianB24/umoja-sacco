<?php
// public/support_view.php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['admin', 'superadmin', 'manager', 'accountant'])) {
    die("Access denied.");
}

$result = $conn->query("
    SELECT s.*, m.full_name AS member_name, a.full_name AS admin_name
    FROM support_tickets s
    LEFT JOIN members m ON s.member_id = m.member_id
    LEFT JOIN admins a ON s.admin_id = a.admin_id
    ORDER BY s.created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Support Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">

<h3 class="mb-4">Support Tickets</h3>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>From</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>View</th>
        </tr>
    </thead>
    <tbody>
    <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['support_id'] ?></td>
            <td>
                <?= $row['member_name'] ?: $row['admin_name'] ?>
            </td>
            <td><?= htmlspecialchars($row['subject']) ?></td>
            <td>
                <span class="badge bg-info"><?= $row['status'] ?></span>
            </td>
            <td><?= $row['created_at'] ?></td>
            <td>
                <a href="support_reply.php?id=<?= $row['support_id'] ?>" class="btn btn-sm btn-primary">
                    Open
                </a>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>