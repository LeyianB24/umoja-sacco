<?php
/**
 * Generates a branded PDF report of a member's transactions.
 * Design: Adapted "Deep Forest" theme for print (Inverted styles for ink saving).
 */

declare(strict_types=1);

session_start();

// --- Configuration ---
define('ROOT_PATH', __DIR__ . '/..');

require_once ROOT_PATH . '/inc/SystemPDF.php';

// --- Auth Check ---
if (!isset($_SESSION['member_id']) || !isset($conn)) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

$member_id = (int)$_SESSION['member_id'];

// --- 1. Fetch KPI Totals ---
$kpi_sql = "SELECT 
    SUM(CASE WHEN transaction_type IN ('deposit', 'shares', 'welfare') THEN amount ELSE 0 END) as total_in,
    SUM(CASE WHEN transaction_type = 'loan_disbursement' THEN amount ELSE 0 END) as total_borrowed,
    SUM(CASE WHEN transaction_type = 'loan_repayment' THEN amount ELSE 0 END) as total_repaid,
    SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawn
    FROM transactions WHERE member_id = ?";

$stmt_kpi = $conn->prepare($kpi_sql);
$stmt_kpi->bind_param("i", $member_id);
$stmt_kpi->execute();
$kpi = $stmt_kpi->get_result()->fetch_assoc();
$stmt_kpi->close();

// Cast to float immediately to prevent type errors later
$net_savings = (float)($kpi['total_in'] ?? 0) - (float)($kpi['total_withdrawn'] ?? 0);
$total_borrowed = (float)($kpi['total_borrowed'] ?? 0);
$total_repaid = (float)($kpi['total_repaid'] ?? 0);
$total_withdrawn = (float)($kpi['total_withdrawn'] ?? 0);

// --- 2. Fetch Filtered Transactions ---
$type_filter = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
$date_filter = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);

$sql = "SELECT transaction_type, amount, reference_no, payment_channel, transaction_date, notes 
        FROM transactions 
        WHERE member_id = ? ";
$params = [$member_id];
$types = "i";

if ($type_filter) {
    $sql .= " AND transaction_type = ? ";
    $params[] = $type_filter;
    $types .= "s";
}
if ($date_filter) {
    $sql .= " AND DATE(created_at) = ? "; 
    $params[] = $date_filter;
    $types .= "s";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- 3. Centralized Export Generation ---
require_once ROOT_PATH . '/core/finance/FinancialExportEngine.php';

// Fetch member details for metadata
$stmt_m = $conn->prepare("SELECT full_name, member_reg_no FROM members WHERE member_id = ?");
$stmt_m->bind_param("i", $member_id);
$stmt_m->execute();
$mem_meta = $stmt_m->get_result()->fetch_assoc();
$stmt_m->close();

$member_name = $mem_meta['full_name'] ?? 'Member';
$member_ref = $mem_meta['member_reg_no'] ?? 'MEM-'.$member_id;

FinancialExportEngine::export('pdf', function($pdf) use ($transactions, $net_savings, $total_borrowed, $total_repaid, $total_withdrawn) {
    // Summary Row (Custom layout for this report)
    $pdf->Ln(5);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(45, 10, ' Total Savings:', 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 10, ' KES ' . number_format($net_savings, 2), 1, 0, 'R');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(45, 10, ' Active Loans:', 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 10, ' KES ' . number_format($total_borrowed, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(45, 10, ' Total Repaid:', 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 10, ' KES ' . number_format($total_repaid, 2), 1, 0, 'R');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(45, 10, ' Withdrawn:', 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 10, ' KES ' . number_format($total_withdrawn, 2), 1, 1, 'R');
    $pdf->Ln(10);

    // Table Header
    // FinancialExportEngine doesn't have styledTableHeader helper exposed by default on $pdf unless it extends SystemPDF or we use standard FPDF methods.
    // However, PdfTemplate extends FPDF, but we can implement the table manually or use the helper if we add it to PdfTemplate.
    // For now, manual standard FPDF table:
    $pdf->SetFillColor(27, 94, 32); 
    $pdf->SetTextColor(255);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 8, 'DATE', 1, 0, 'L', true);
    $pdf->Cell(50, 8, 'TYPE', 1, 0, 'L', true);
    $pdf->Cell(35, 8, 'REFERENCE', 1, 0, 'L', true);
    $pdf->Cell(30, 8, 'CHANNEL', 1, 0, 'L', true);
    $pdf->Cell(35, 8, 'AMOUNT', 1, 1, 'R', true);

    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 9);
    $fill = false;
    
    foreach ($transactions as $row) {
        $type = strtolower($row['transaction_type'] ?? '');
        $display_type = ucwords(str_replace('_', ' ', $type));
        $amount = (float)$row['amount'];
        $is_withdrawal = ($type == 'withdrawal');
        $sign = $is_withdrawal ? '-' : '+';
        
        $pdf->Row(
            [
                date('d M Y', strtotime($row['transaction_date'])),
                $display_type,
                $row['reference_no'],
                strtoupper($row['payment_channel'] ?? 'SYS'),
                $sign . ' ' . number_format($amount, 2)
            ],
            [30, 50, 35, 30, 35], // Widths
            ['L', 'L', 'L', 'L', 'R'], // Aligns
            6 // Line Height
        );
    }

}, [
    'title' => 'Financial Transaction Ledger',
    'module' => 'Member Portal',
    'account_ref' => $member_ref,
    'total_value' => count($transactions), // Using count as value proxy or 0 since this is a listing
    'record_count' => count($transactions)
]);
exit;
?>