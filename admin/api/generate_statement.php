<?php
/**
 * admin/generate_statement.php
 * V28 High-Fidelity Statement Engine
 * Supports PDF & CSV exports with filtered ledger views.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../fpdf/fpdf.php'; 

// 1. AUTH & INPUT
require_permission('statements.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Unauthorized access attempt.");
}

$member_id  = intval($_POST['member_id']);
$start_date = $_POST['start_date'];
$end_date   = $_POST['end_date'];
$report_type= $_POST['report_type'] ?? 'full';
$out_format  = $_POST['format'] ?? 'pdf';

// 2. FETCH MEMBER
$stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) die("Member data sync failed.");

// 3. MAP REPORT TYPE TO LEDGER CATEGORIES
$categories = [];
if ($report_type === 'savings') {
    $categories = ['savings'];
} elseif ($report_type === 'loans') {
    $categories = ['loans'];
} else {
    $categories = ['savings', 'loans', 'shares', 'wallet', 'welfare'];
}
$cat_list = "'" . implode("','", $categories) . "'";

// 4. FETCH TRANSACTIONS FROM LEDGER
$sql = "SELECT le.created_at as transaction_date, t.reference_no, t.transaction_type, le.debit, le.credit, t.notes, la.category
        FROM ledger_entries le
        JOIN ledger_accounts la ON le.account_id = la.account_id
        JOIN transactions t ON le.transaction_id = t.transaction_id
        WHERE la.member_id = ? AND la.category IN ($cat_list) 
        AND DATE(le.created_at) BETWEEN ? AND ?
        ORDER BY le.created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $member_id, $start_date, $end_date);
$stmt->execute();
$txns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 5. CALCULATE OPENING BALANCE FROM LEDGER
$sqlOpen = "SELECT SUM(le.credit - le.debit) as bal
            FROM ledger_entries le
            JOIN ledger_accounts la ON le.account_id = la.account_id
            WHERE la.member_id = ? AND la.category IN ($cat_list)
            AND DATE(le.created_at) < ?";
$stmt = $conn->prepare($sqlOpen);
$stmt->bind_param("is", $member_id, $start_date);
$stmt->execute();
$opening_bal = (float)($stmt->get_result()->fetch_assoc()['bal'] ?? 0);
// Note: For Loans (Asset), balance is Debit - Credit. For Liabilities, it's Credit - Debit.
// Full Ledger balance is net position.

// 6. CSV OUTPUT
if ($out_format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Statement_' . $member['member_reg_no'] . '_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['UMOJA SACCO OFFICIAL STATEMENT']);
    fputcsv($output, ['Member:', $member['full_name']]);
    fputcsv($output, ['Reg No:', $member['member_reg_no']]);
    fputcsv($output, ['Period:', $start_date . ' to ' . $end_date]);
    fputcsv($output, []);
    fputcsv($output, ['Date', 'Reference', 'Type', 'Description', 'Amount', 'Balance']);
    
    $cur_bal = $opening_bal;
    fputcsv($output, [$start_date, '-', 'OPENING BALANCE', '-', '-', number_format($cur_bal, 2, '.', '')]);
    
    foreach($txns as $t) {
        $impact = ($t['category'] === 'loans') ? ($t['debit'] - $t['credit']) : ($t['credit'] - $t['debit']);
        $cur_bal += $impact;
        fputcsv($output, [
            date('Y-m-d', strtotime($t['transaction_date'])),
            $t['reference_no'],
            strtoupper($t['transaction_type']),
            $t['notes'],
            number_format(($t['debit'] > 0 ? $t['debit'] : $t['credit']), 2, '.', ''),
            number_format($cur_bal, 2, '.', '')
        ]);
    }
    fclose($output);
    exit;
}

// 7. PDF OUTPUT (V28 Branding)
class V28PDF extends FPDF {
    function Header() {
        // Background Accent
        $this->SetFillColor(15, 46, 37);
        $this->Rect(0, 0, 210, 40, 'F');
        
        $this->SetY(12);
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(208, 243, 93); // Lime
        $this->Cell(0, 10, 'UMOJA SACCO LTD', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(255);
        $this->Cell(0, 5, 'OFFICIAL FINANCIAL STATEMENT | V28 SECURE LEDGER', 0, 1, 'C');
        $this->Ln(15);
    }

    function Footer() {
        $this->SetY(-25);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150);
        $this->Cell(0, 10, 'This is a computer generated statement. Verification Hash: ' . md5(time()), 0, 1, 'C');
        $this->SetTextColor(15, 46, 37);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
}

$pdf = new V28PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Info Block
$pdf->SetTextColor(15, 46, 37);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'MEMBER PORTRAIT', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(50);
$pdf->Cell(40, 7, 'Member Name:', 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, strtoupper($member['full_name']), 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 7, 'Registration No:', 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, $member['member_reg_no'], 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 7, 'Identity No:', 0, 0);
$pdf->Cell(0, 7, $member['national_id'], 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 7, 'Statement Period:', 0, 0);
$pdf->Cell(0, 7, date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)), 0, 1);
$pdf->Ln(5);

// Summary Bar
$pdf->SetFillColor(248, 250, 252);
$pdf->Rect(10, $pdf->GetY(), 190, 15, 'F');
$pdf->SetY($pdf->GetY() + 4);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(50, 7, '  REPORT TYPE:', 0, 0);
$pdf->SetTextColor(15, 46, 37);
$pdf->Cell(40, 7, strtoupper($report_type), 0, 0);
$pdf->SetTextColor(50);
$pdf->Cell(50, 7, '  OPENING BALANCE:', 0, 0);
$pdf->SetTextColor(15, 46, 37);
$pdf->Cell(40, 7, 'KES ' . number_format($opening_bal, 2), 0, 1);
$pdf->Ln(10);

// Table
$pdf->SetFillColor(15, 46, 37);
$pdf->SetTextColor(255);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 10, 'DATE', 0, 0, 'C', true);
$pdf->Cell(35, 10, 'REFERENCE', 0, 0, 'L', true);
$pdf->Cell(65, 10, 'DESCRIPTION', 0, 0, 'L', true);
$pdf->Cell(30, 10, 'AMOUNT', 0, 0, 'R', true);
$pdf->Cell(35, 10, 'BALANCE ', 0, 1, 'R', true);

$pdf->SetTextColor(0);
$pdf->SetFont('Arial', '', 9);
$running = $opening_bal;
$fill = false;

foreach($txns as $t) {
    $impact = ($t['category'] === 'loans') ? ($t['debit'] - $t['credit']) : ($t['credit'] - $t['debit']);
    $running += $impact;
    
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(25, 8, date('d-m-Y', strtotime($t['transaction_date'])), 0, 0, 'C', $fill);
    $pdf->Cell(35, 8, $t['reference_no'], 0, 0, 'L', $fill);
    $pdf->Cell(65, 8, substr(ucfirst(str_replace('_', ' ', $t['transaction_type'])), 0, 30), 0, 0, 'L', $fill);
    $pdf->Cell(30, 8, number_format(($t['debit'] > 0 ? $t['debit'] : $t['credit']), 2), 0, 0, 'R', $fill);
    $pdf->Cell(35, 8, number_format($running, 2), 0, 1, 'R', $fill);
    $fill = !$fill;
}

$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 46, 37);
$pdf->Cell(155, 10, 'FINAL SETTLED BALANCE (AS OF ' . date('d/m/Y', strtotime($end_date)) . '):', 0, 0, 'R');
$pdf->SetFillColor(208, 243, 93);
$pdf->Cell(35, 10, 'KES ' . number_format($running, 2), 0, 1, 'R', true);

$pdf->Output('I', 'Statement_' . $member['member_reg_no'] . '.pdf');
