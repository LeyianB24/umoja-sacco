<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/functions.php';

// Auth Check
Auth::requireAdmin();

header('Content-Type: application/json');

$loan_id = intval($_GET['loan_id'] ?? 0);

if ($loan_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Loan ID']);
    exit;
}

try {
    // 1. Fetch Loan Details
    $stmt = $conn->prepare("SELECT l.*, m.full_name, m.national_id, m.phone, m.profile_pic FROM loans l JOIN members m ON l.member_id = m.member_id WHERE l.loan_id = ?");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        echo json_encode(['success' => false, 'message' => 'Loan not found']);
        exit;
    }

    // 2. Fetch Guarantors
    $guarantors = [];
    $stmt = $conn->prepare("SELECT lg.*, m.full_name, m.phone, m.member_reg_no FROM loan_guarantors lg JOIN members m ON lg.member_id = m.member_id WHERE lg.loan_id = ?");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $guarantors[] = $row;
    }
    $stmt->close();

    // 3. Fetch Repayment History (if any)
    $payments = [];
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE related_table = 'loans' AND related_id = ? AND transaction_type = 'loan_repayment' ORDER BY created_at DESC");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'loan' => $loan,
        'guarantors' => $guarantors,
        'payments' => $payments
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
