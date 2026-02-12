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
require_once __DIR__ . '/../../inc/SystemPDF.php'; 

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
$txn_result = $stmt->get_result();
$record_count = $txn_result->num_rows;

// 5. CALCULATE OPENING BALANCE FROM LEDGER
$sqlOpen = "SELECT SUM(le.credit - le.debit) as bal
            FROM ledger_entries le
            JOIN ledger_accounts la ON le.account_id = la.account_id
            WHERE la.member_id = ? AND la.category IN ($cat_list)
            AND DATE(le.created_at) < ?";
$stmtO = $conn->prepare($sqlOpen);
$stmtO->bind_param("is", $member_id, $start_date);
$stmtO->execute();
$opening_bal = (float)($stmtO->get_result()->fetch_assoc()['bal'] ?? 0);
$stmtO->close();

// 6. OUTPUT VIA FINANCIAL EXPORT ENGINE
require_once __DIR__ . '/../../core/finance/FinancialExportEngine.php';

if ($out_format === 'csv' || $out_format === 'excel') {
    // For Excel, we iterate and format row by row
    $exportRows = [];
    $cur_bal = $opening_bal;
    $exportRows[] = [$start_date, '-', 'OPENING BALANCE', '-', '-', number_format($cur_bal, 2, '.', '')];
    
    while($t = $txn_result->fetch_assoc()) {
        $impact = ($t['category'] === 'loans') ? ($t['debit'] - $t['credit']) : ($t['credit'] - $t['debit']);
        $cur_bal += $impact;
        $exportRows[] = [
            date('Y-m-d', strtotime($t['transaction_date'])),
            $t['reference_no'],
            strtoupper($t['transaction_type']),
            $t['notes'],
            number_format(($t['debit'] > 0 ? $t['debit'] : $t['credit']), 2, '.', ''),
            number_format($cur_bal, 2, '.', '')
        ];
    }
    
    FinancialExportEngine::export('excel', $exportRows, [
        'title' => 'Statement of Accounts - ' . strtoupper($report_type),
        'module' => 'Statement Module',
        'account_ref' => $member['member_reg_no'],
        'headers' => ['Date', 'Reference', 'Type', 'Description', 'Amount', 'Balance'],
        'record_count' => $record_count,
        'total_value' => $opening_bal
    ]);
    exit;
} else {
    // PDF Mode - Optimized Streaming approach inside Closure
    FinancialExportEngine::export('pdf', function($pdf) use ($member, $start_date, $end_date, $report_type, $opening_bal, $txn_result) {
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
        $pdf->Rect(15, $pdf->GetY(), 180, 15, 'F');
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
        
        // Table HEADER
        $w = [25, 35, 65, 30, 35];
        $headers = ['DATE', 'REFERENCE', 'DESCRIPTION', 'AMOUNT', 'BALANCE'];
        
        $pdf->SetFillColor(27, 94, 32);
        $pdf->SetTextColor(255);
        $pdf->SetFont('Arial', 'B', 9);
        foreach($headers as $i => $h) {
            $pdf->Cell($w[$i], 8, $h, 1, 0, 'L', true);
        }
        $pdf->Ln();
        
        $pdf->SetTextColor(0);
        $pdf->SetFont('Arial', '', 8);
        $running = $opening_bal;
        
        // LOOP through database result directly (Memory efficient)
        while($t = $txn_result->fetch_assoc()) {
            $impact = ($t['category'] === 'loans') ? ($t['debit'] - $t['credit']) : ($t['credit'] - $t['debit']);
            $running += $impact;
            
            // Draw Row
            $pdf->Cell($w[0], 7, date('d-m-Y', strtotime($t['transaction_date'])), 1);
            $pdf->Cell($w[1], 7, $t['reference_no'], 1);
            $pdf->Cell($w[2], 7, substr(ucfirst(str_replace('_', ' ', (string)$t['transaction_type'])), 0, 30), 1);
            $pdf->Cell($w[3], 7, number_format(($t['debit'] > 0 ? $t['debit'] : $t['credit']), 2), 1, 0, 'R');
            $pdf->Cell($w[4], 7, number_format($running, 2), 1, 1, 'R');
        }
        
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(15, 46, 37);
        $pdf->Cell(155, 10, 'FINAL SETTLED BALANCE (AS OF ' . date('d/m/Y', strtotime($end_date)) . '):', 0, 0, 'R');
        $pdf->SetFillColor(208, 243, 93);
        $pdf->Cell(35, 10, 'KES ' . number_format($running, 2), 0, 1, 'R', true);

    }, [
        'title' => 'Member Financial Statement',
        'module' => 'Statement Module',
        'account_ref' => $member['member_reg_no'],
        'record_count' => $record_count
    ]);
}
exit;
?>
