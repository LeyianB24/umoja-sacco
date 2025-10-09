<?php
session_start();
include("../config/db_connect.php");

// Redirect to login if not logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: ../login.php");
    exit;
}

$member_id = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'];

// Handle new contribution submission
if (isset($_POST['add_contribution'])) {
    $amount = $_POST['amount'];
    if (!empty($amount) && $amount > 0) {
        $stmt = $conn->prepare("INSERT INTO contributions (member_id, amount) VALUES (?, ?)");
        $stmt->bind_param("id", $member_id, $amount);
        $stmt->execute();
        $message = "Contribution of Ksh $amount added successfully!";
    } else {
        $error = "Please enter a valid amount.";
    }
}

// Fetch all contributions for this member
$contributions = $conn->prepare("SELECT * FROM contributions WHERE member_id=? ORDER BY contribution_date DESC");
$contributions->bind_param("i", $member_id);
$contributions->execute();
$result = $contributions->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Dashboard - USMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a2d9d5a64b.js" crossorigin="anonymous"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f0f2f5 0%, #dbeafe 100%);
            font-family: 'Poppins', sans-serif;
        }
        .dashboard-card {
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .btn-rounded {
            border-radius: 50px;
        }
        .contribution-table th {
            background-color: #1e3a8a;
            color: white;
        }
        .welcome-text {
            font-weight: 600;
            color: #1e3a8a;
        }
        .dashboard-actions a {
            transition: transform 0.2s ease-in-out;
        }
        .dashboard-actions a:hover {
            transform: translateY(-3px);
        }
        .footer-text {
            text-align: center;
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 15px;
        }
    </style>
</head>
<body>

<div class="container mt-5 mb-4">
    <div class="dashboard-card p-4">
        
        <!-- Welcome Section -->
        <div class="text-center mb-4">
            <h3 class="welcome-text">Welcome, <?php echo htmlspecialchars($member_name); ?> 👋</h3>
            <p class="text-muted mb-0">Manage your contributions and loan requests easily.</p>
        </div>

        <!-- Alerts -->
        <?php if (!empty($message)) echo "<div class='alert alert-success alert-dismissible fade show'>$message<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>"; ?>
        <?php if (!empty($error)) echo "<div class='alert alert-danger alert-dismissible fade show'>$error<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>"; ?>

        <!-- Action Buttons -->
        <div class="dashboard-actions text-center mb-4 d-flex flex-wrap justify-content-center gap-3">
            <a href="apply_loan.php" class="btn btn-primary btn-rounded px-4 py-2 shadow-sm">
                <i class="fa-solid fa-file-invoice-dollar me-2"></i> Apply for Loan
            </a>
            <a href="view_loans.php" class="btn btn-outline-success btn-rounded px-4 py-2 shadow-sm">
                <i class="fa-solid fa-list me-2"></i> My Loan Applications
            </a>
            <a href="../logout.php" class="btn btn-outline-danger btn-rounded px-4 py-2 shadow-sm">
                <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
            </a>
        </div>

        <!-- Contribution Form -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title text-center mb-3 text-primary"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Make a Contribution</h5>
                <form method="POST" class="row g-3 justify-content-center">
                    <div class="col-md-6">
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg text-center" placeholder="Enter contribution amount" required>
                    </div>
                    <div class="col-md-3 text-center">
                        <button type="submit" name="add_contribution" class="btn btn-success btn-rounded px-4 py-2 shadow-sm w-100">
                            Add Contribution
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Contributions Table -->
        <h5 class="mb-3 text-primary"><i class="fa-solid fa-table me-2"></i>Your Contributions</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-striped contribution-table text-center align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Amount (Ksh)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        $i = 1;
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>{$i}</td>
                                    <td>{$row['amount']}</td>
                                    <td>{$row['contribution_date']}</td>
                                  </tr>";
                            $i++;
                        }
                    } else {
                        echo "<tr><td colspan='3' class='text-muted'>No contributions yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer-text">
            <p>© <?php echo date('Y'); ?> USMS | Member Dashboard</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
