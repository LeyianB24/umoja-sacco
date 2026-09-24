<?php
session_start();
include('../config/db_connect.php');

// Check login
if (!isset($_SESSION['member_id'])) {
    header("Location: ../login.php");
    exit();
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $member_id = $_SESSION['member_id'];

    $query = "INSERT INTO contributions (member_id, amount, description) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ids", $member_id, $amount, $description);

    if ($stmt->execute()) {
        $message = "✅ Contribution added successfully!";
    } else {
        $message = "❌ Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Contribution</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h3>Add Contribution</h3>
    <p class="text-success"><?= $message ?></p>
    <form method="POST">
        <div class="mb-3">
            <label>Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control" required></textarea>
        </div>
        <button class="btn btn-primary">Submit</button>
        <a href="dashboard.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
