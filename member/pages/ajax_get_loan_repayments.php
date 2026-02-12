<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/functions.php';

header('Content-Type: application/json');

// Auth Check
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$member_id = $_SESSION['member_id'];
$loan_id = intval($_GET['loan_id'] ?? 0);

if ($loan_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Loan ID']);
    exit;
}

try {
    // 1. Verify ownership
    $stmt = $conn->prepare("SELECT loan_id, loan_type, amount, status, current_balance FROM loans WHERE loan_id = ? AND member_id = ?");
    $stmt->bind_param("ii", $loan_id, $member_id);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        echo json_encode(['success' => false, 'message' => 'Loan not found or access denied']);
        exit;
    }

    // 2. Fetch Repayments from loan_repayments (more specific)
    $repayments = [];
    $stmt = $conn->prepare("
        SELECT 
            repayment_id, 
            amount_paid as amount, 
            payment_method as method, 
            reference_no as ref, 
            payment_date as date, 
            status
        FROM loan_repayments 
        WHERE loan_id = ? 
        ORDER BY payment_date DESC, repayment_id DESC
    ");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $repayments[] = [
            'id' => $row['repayment_id'],
            'amount' => (float)$row['amount'],
            'method' => ucfirst($row['method']),
            'ref' => $row['ref'],
            'date' => date('d M Y', strtotime($row['date'])),
            'status' => $row['status']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'loan' => $loan,
        'repayments' => $repayments
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
