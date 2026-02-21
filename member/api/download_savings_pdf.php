<?php
// member/savings_pdf.php

/**
 * Generates a professional PDF report of a member's transaction history.
 * Requires: composer require dompdf/dompdf
 */

// 1. System Setup
ini_set('memory_limit', '512M');
set_time_limit(300);

session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/app_config.php';

use USMS\Reports\SystemPDF;
use USMS\Services\FinancialExportEngine;

// 2. Authentication
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}
$member_id = $_SESSION['member_id'];

// 3. Fetch Member Details
$sqlMember = "SELECT full_name, member_id, phone, email, national_id, address FROM members WHERE member_id = ?";
$stmt = $conn->prepare($sqlMember);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 4. Fetch Transactions
// We map 'created_at' to 't_date' to standardize
$sqlHistory = "
    SELECT 
        transaction_id,
        transaction_type,
        amount,
        payment_channel,
        reference_no,
        notes,
        created_at as t_date
    FROM transactions 
    WHERE member_id = ? 
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sqlHistory);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 5. Calculate Financial Summary
$total_in = 0;      // Savings, Shares, Welfare
$total_loans = 0;   // Loan Repayments (Money In, but tracked separately)
$total_out = 0;     // Withdrawals

foreach ($transactions as $t) {
    $type = strtolower($t['transaction_type']);
    $amt = (float)$t['amount'];

    if (in_array($type, ['deposit', 'savings', 'shares', 'welfare'])) {
        $total_in += $amt;
    } elseif ($type === 'loan_repayment') {
        $total_loans += $amt;
    } elseif ($type === 'withdrawal') {
        $total_out += $amt;
    }
}

$net_savings_balance = $total_in - $total_out;

// 6. Generate PDF with FinancialExportEngine
// Autoloaded via USMS\Services\FinancialExportEngine

FinancialExportEngine::export('pdf', function($pdf) use ($member, $total_in, $total_loans, $total_out, $transactions) {
    // Member Info Section
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 6, "Member Name:", 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 6, strtoupper($member['full_name']), 0, 0);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(20, 6, "Email:", 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 6, $member['email'], 0, 1);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 6, "Member ID:", 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 6, str_pad((string)$member['member_id'], 5, '0', STR_PAD_LEFT), 0, 0);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(20, 6, "Phone:", 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 6, $member['phone'], 0, 1);
    $pdf->Ln(5);

    // Summary Cards Row
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Rect($pdf->GetX(), $pdf->GetY(), 190, 20, 'F');
    $pdf->SetY($pdf->GetY() + 5);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(63, 5, 'TOTAL SAVINGS', 0, 0, 'C');
    $pdf->Cell(63, 5, 'LOANS REPAID', 0, 0, 'C');
    $pdf->Cell(63, 5, 'WITHDRAWALS', 0, 1, 'C');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(13, 131, 75); // Success Green
    $pdf->Cell(63, 7, 'KES ' . number_format($total_in, 2), 0, 0, 'C');
    $pdf->SetTextColor(13, 110, 253); // Primary Blue
    $pdf->Cell(63, 7, 'KES ' . number_format($total_loans, 2), 0, 0, 'C');
    $pdf->SetTextColor(220, 53, 69); // Danger Red
    $pdf->Cell(63, 7, 'KES ' . number_format($total_out, 2), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0); // Reset
    $pdf->Ln(10);

    // Transactions Table
    $headers = ['DATE', 'TYPE', 'REFERENCE', 'DESCRIPTION', 'AMOUNT'];
    $w = [25, 30, 30, 75, 30];
    
    $pdf->SetFillColor(27, 94, 32);
    $pdf->SetTextColor(255);
    $pdf->SetFont('Arial', 'B', 9);
    foreach($headers as $i => $h) {
        $pdf->Cell($w[$i], 8, $h, 1, 0, 'L', true);
    }
    $pdf->Ln();

    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 8);
    
    if (count($transactions) > 0) {
        foreach ($transactions as $row) {
            $type = strtolower($row['transaction_type']);
            $sign = '';
            if (in_array($type, ['deposit', 'savings', 'shares', 'loan_repayment'])) {
                $sign = '+ ';
            } elseif ($type === 'withdrawal') {
                $sign = '- ';
            }

            // Use Row for better text wrapping
            $pdf->Row(
                [
                    date('d M Y', strtotime($row['t_date'])),
                    ucfirst(str_replace('_', ' ', $type)),
                    $row['reference_no'] ?? '-',
                    $row['notes'] ?? $row['payment_channel'], // Removed manual truncation as Row handles wrapping
                    $sign . number_format((float)$row['amount'], 2)
                ],
                $w, // [25, 30, 30, 75, 30]
                ['L', 'L', 'L', 'L', 'R'],
                6 // Line Height
            );
        }
    } else {
        $pdf->Cell(190, 10, 'No transactions found.', 1, 1, 'C');
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 5, "Disclaimer: This statement is computer generated and valid without a signature. If you find any discrepancies, please contact support immediately.");

}, [
    'title' => 'Official Member Statement',
    'module' => 'Member Portal',
    'account_ref' => $member['member_id'], // Or reg no if available
    'record_count' => count($transactions),
    'total_value' => $total_in
]);
exit;
?>