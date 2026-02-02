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
require_once __DIR__ . '/../../vendor/autoload.php'; // Ensure this path is correct

use Dompdf\Dompdf;
use Dompdf\Options;

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

// 6. Prepare Logo (Base64 encoding for PDF compatibility)
$logo_path = __DIR__ . '/../public/assets/images/people_logo.png'; // Verify this path
$logo_data = '';
if (file_exists($logo_path)) {
    $type = pathinfo($logo_path, PATHINFO_EXTENSION);
    $data = file_get_contents($logo_path);
    $logo_data = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// 7. Build HTML Layout
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 100px 40px 60px 40px; } /* Margins for Header/Footer */
        
        body { font-family: "Helvetica", sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
        
        /* Fixed Header */
        header { position: fixed; top: -70px; left: 0; right: 0; height: 70px; border-bottom: 2px solid #0d834b; }
        .header-table { width: 100%; }
        .brand-name { font-size: 18pt; font-weight: bold; color: #0d834b; text-transform: uppercase; margin: 0; }
        .brand-sub { font-size: 9pt; color: #666; }

        /* Fixed Footer */
        footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 30px; text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
        .page-number:after { content: counter(page); }

        /* Member Info Box */
        .info-box { width: 100%; background: #f8f9fa; padding: 15px; border: 1px solid #e9ecef; margin-bottom: 25px; border-radius: 5px; }
        .info-table td { padding: 2px 0; vertical-align: top; }
        .label { font-weight: bold; color: #555; font-size: 9pt; width: 100px; display: inline-block; }

        /* Summary Cards Table */
        .summary-table { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-bottom: 30px; }
        .summary-box { background: #fff; border: 1px solid #ddd; padding: 10px; text-align: center; border-radius: 4px; }
        .summary-val { font-size: 14pt; font-weight: bold; margin-top: 5px; }
        .text-success { color: #0d834b; }
        .text-danger { color: #dc3545; }
        .text-primary { color: #0d6efd; }

        /* Transaction Table */
        .txn-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .txn-table th { background: #0d834b; color: #fff; text-align: left; padding: 8px; font-size: 9pt; text-transform: uppercase; }
        .txn-table td { padding: 8px; border-bottom: 1px solid #eee; font-size: 9pt; vertical-align: top; }
        .txn-table tr:nth-child(even) { background-color: #fcfcfc; }
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 8pt; font-weight: bold; color: #fff; background: #6c757d; }
        .bg-in { background: #d1e7dd; color: #0f5132; } /* Green tint */
        .bg-out { background: #f8d7da; color: #842029; } /* Red tint */
    </style>
</head>
<body>

    <header>
        <table class="header-table">
            <tr>
                <td width="60">
                    ' . ($logo_data ? '<img src="'.$logo_data.'" width="50" />' : '') . '
                </td>
                <td>
                    <div class="brand-name">' . (defined('SITE_NAME') ? SITE_NAME : 'UMOJA SACCO') . '</div>
                    <div class="brand-sub">Official Member Statement</div>
                </td>
                <td align="right">
                    <div style="font-weight:bold;">DATE</div>
                    <div>' . date('d M Y') . '</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        Generated by Umoja Sacco Portal • Page <span class="page-number"></span>
    </footer>

    <main>
        <div class="info-box">
            <table class="info-table" width="100%">
                <tr>
                    <td width="50%">
                        <span class="label">Name:</span> ' . htmlspecialchars($member['full_name']) . '<br>
                        <span class="label">Member ID:</span> ' . str_pad($member['member_id'], 5, '0', STR_PAD_LEFT) . '<br>
                        <span class="label">National ID:</span> ' . htmlspecialchars($member['national_id']) . '
                    </td>
                    <td width="50%">
                        <span class="label">Email:</span> ' . htmlspecialchars($member['email']) . '<br>
                        <span class="label">Phone:</span> ' . htmlspecialchars($member['phone']) . '<br>
                        <span class="label">Address:</span> ' . htmlspecialchars($member['address'] ?? 'N/A') . '
                    </td>
                </tr>
            </table>
        </div>

        <table class="summary-table">
            <tr>
                <td class="summary-box" style="border-top: 3px solid #0d834b;">
                    <div style="font-size:9pt; color:#666;">TOTAL SAVINGS</div>
                    <div class="summary-val text-success">KES ' . number_format($total_in, 2) . '</div>
                </td>
                <td class="summary-box" style="border-top: 3px solid #0d6efd;">
                    <div style="font-size:9pt; color:#666;">LOANS REPAID</div>
                    <div class="summary-val text-primary">KES ' . number_format($total_loans, 2) . '</div>
                </td>
                <td class="summary-box" style="border-top: 3px solid #dc3545;">
                    <div style="font-size:9pt; color:#666;">WITHDRAWALS</div>
                    <div class="summary-val text-danger">KES ' . number_format($total_out, 2) . '</div>
                </td>
            </tr>
        </table>

        <h4 style="border-bottom: 1px solid #ccc; padding-bottom: 5px; color:#444;">Detailed History</h4>
        
        <table class="txn-table">
            <thead>
                <tr>
                    <th width="15%">Date</th>
                    <th width="15%">Type</th>
                    <th width="15%">Reference</th>
                    <th width="40%">Description</th>
                    <th width="15%" align="right">Amount</th>
                </tr>
            </thead>
            <tbody>';

if (count($transactions) > 0) {
    foreach ($transactions as $row) {
        $type = strtolower($row['transaction_type']);
        $date = date('d M Y', strtotime($row['t_date']));
        $ref = htmlspecialchars($row['reference_no'] ?? '-');
        $desc = htmlspecialchars($row['notes'] ?? $row['payment_channel']);
        $amt = number_format($row['amount'], 2);
        
        // Styling Logic
        $row_class = '';
        $sign = '';
        
        if (in_array($type, ['deposit', 'savings', 'shares', 'loan_repayment'])) {
            $row_class = 'bg-in';
            $sign = '+';
        } elseif ($type === 'withdrawal') {
            $row_class = 'bg-out';
            $sign = '-';
        }

        $type_display = ucfirst(str_replace('_', ' ', $type));

        $html .= '
            <tr>
                <td>' . $date . '</td>
                <td><b>' . $type_display . '</b></td>
                <td style="font-family:monospace; font-size:8pt;">' . $ref . '</td>
                <td>' . $desc . '</td>
                <td align="right" style="font-weight:bold;">' . $sign . ' ' . $amt . '</td>
            </tr>';
    }
} else {
    $html .= '<tr><td colspan="5" align="center" style="padding:20px; color:#999;">No transactions found for this account.</td></tr>';
}

$html .= '
            </tbody>
        </table>
        
        <div style="margin-top: 30px; font-size: 8pt; color: #777; border-top: 1px solid #eee; padding-top: 10px;">
            <strong>Disclaimer:</strong> This statement is computer generated and valid without a signature. 
            If you find any discrepancies, please contact support immediately.
        </div>
    </main>
</body>
</html>';

// 8. Output PDF
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = "Statement_" . $member_id . "_" . date('M_Y') . ".pdf";
    $dompdf->stream($filename, ["Attachment" => true]); // Force Download
} catch (Exception $e) {
    echo "PDF Error: " . $e->getMessage();
}
?>