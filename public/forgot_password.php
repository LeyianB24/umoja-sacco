<?php
require_once __DIR__ . '/../config/db_connect.php';


$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT member_id, full_name FROM members WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $member = $result->fetch_assoc();
        $temp_password = substr(md5(uniqid(mt_rand(), true)), 0, 8);

        // Save temporary password and clear any previous token
        $update = $conn->prepare("UPDATE members SET temp_password = ?, remember_token = NULL WHERE member_id = ?");
        $update->bind_param("si", $temp_password, $member['member_id']);

        if ($update->execute()) {
            // Simulate email send (you can replace with PHPMailer later)
            $message = "<div class='alert alert-success'>A temporary password has been generated and sent to your email.<br>
            <strong>Temp Password:</strong> <code>$temp_password</code></div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to generate a temporary password. Please try again later.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>No account found with that email address.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Umoja Drivers Sacco</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a2d9d6c1b3.js" crossorigin="anonymous"></script>
    <style>
        body {
            background: linear-gradient(135deg, #004aad, #38b000);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
        }
        .card-header {
            background-color: #004aad;
            color: white;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        .btn-success {
            background-color: #38b000;
            border: none;
        }
        .btn-success:hover {
            background-color: #2a8700;
        }
        a {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card col-md-5 mx-auto">
            <div class="card-header py-3">
                <h4><i class="fas fa-lock me-2"></i>Forgot Password</h4>
            </div>
            <div class="card-body p-4">
                <?= $message; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Enter Your Registered Email</label>
                        <input type="email" name="email" id="email" class="form-control rounded-pill" placeholder="example@email.com" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100 rounded-pill">
                        <i class="fas fa-paper-plane me-2"></i>Send Temporary Password
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="login.php" class="text-primary fw-semibold"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>