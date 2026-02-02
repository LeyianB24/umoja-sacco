<?php
/**
 * Generates a branded PDF report of a member's transactions.
 * Design: Adapted "Deep Forest" theme for print (Inverted styles for ink saving).
 */

declare(strict_types=1);

session_start();

// --- Configuration ---
define('ROOT_PATH', __DIR__ . '/..');

try {
    require_once ROOT_PATH . '/config/app_config.php';
    require_once ROOT_PATH . '/config/db_connect.php';
    require_once ROOT_PATH . '/inc/auth.php';
    require_once ROOT_PATH . '/vendor/autoload.php';
} catch (Throwable $e) {
    error_log("Initialization failed: " . $e->getMessage());
    exit("System initialization error.");
}

use Dompdf\Dompdf;
use Dompdf\Options;

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

// --- 3. HTML Generation ---
$current_date = date('d M Y, h:i A');
$site_name = defined('SITE_NAME') ? SITE_NAME : 'SACCO';

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    /* --- Brand Palette (Deep Forest & Lime) --- */
    :root {
        --brand-dark: #0f172a;
        --brand-lime: #bef264;
        --text-dark: #334155;
    }

    body { 
        font-family: "Helvetica", sans-serif; 
        font-size: 12px; 
        color: #334155; 
        margin: 0;
        padding: 0;
    }

    /* --- Header Section --- */
    .header-banner {
        background-color: #0f172a;
        color: #ffffff;
        padding: 30px 40px;
        margin-bottom: 30px;
    }
    
    .company-name {
        font-size: 24px;
        font-weight: bold;
        text-transform: uppercase;
        color: #bef264;
        margin-bottom: 5px;
    }

    .report-title {
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.9;
    }

    .meta-info {
        text-align: right;
        font-size: 10px;
        color: #94a3b8;
    }

    /* --- Summary Cards --- */
    .kpi-table {
        width: 100%;
        margin: 0 40px 30px 40px;
        border-collapse: separate;
        border-spacing: 10px 0;
        width: calc(100% - 80px);
    }

    .kpi-card {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 15px;
        background-color: #ffffff;
    }

    .kpi-title {
        font-size: 9px;
        text-transform: uppercase;
        color: #64748b;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .kpi-value {
        font-size: 16px;
        font-weight: bold;
        color: #0f172a;
    }

    .text-lime { color: #4d7c0f; }
    .text-blue { color: #0284c7; }
    .text-red { color: #dc2626; }
    
    /* --- Main Table --- */
    .table-wrapper {
        margin: 0 40px;
    }

    table.transactions {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }

    .transactions th {
        background-color: #0f172a;
        color: #ffffff;
        text-transform: uppercase;
        font-size: 10px;
        padding: 12px 8px;
        text-align: left;
    }

    .transactions td {
        border-bottom: 1px solid #e2e8f0;
        padding: 10px 8px;
        color: #334155;
    }

    .transactions tr:nth-child(even) {
        background-color: #f8fafc;
    }

    .badge {
        display: inline-block;
        padding: 3px 6px;
        border-radius: 4px;
        font-size: 9px;
        text-transform: uppercase;
        border: 1px solid #e2e8f0;
        background-color: #f1f5f9;
        color: #64748b;
    }
    
    .text-end { text-align: right; }
    .fw-bold { font-weight: bold; }
    .font-mono { font-family: "Courier", monospace; }

    /* Footer */
    .footer {
        position: fixed;
        bottom: 30px;
        left: 40px;
        right: 40px;
        border-top: 1px solid #e2e8f0;
        padding-top: 10px;
        font-size: 9px;
        color: #94a3b8;
        text-align: center;
    }
</style>
</head>
<body>

    <table width="100%" class="header-banner">
        <tr>
            <td width="60%">
                <div class="company-name">' . $site_name . '</div>
                <div class="report-title">Financial Transaction Ledger</div>
            </td>
            <td width="40%" class="meta-info">
                Generated: ' . $current_date . '<br>
                Member ID: #' . str_pad((string)$member_id, 5, "0", STR_PAD_LEFT) . '
            </td>
        </tr>
    </table>

    <table class="kpi-table">
        <tr>
            <td width="25%">
                <div class="kpi-card" style="border-top: 3px solid #65a30d;">
                    <div class="kpi-title">Total Savings</div>
                    <div class="kpi-value text-lime">KES ' . number_format($net_savings, 2) . '</div>
                </div>
            </td>
            <td width="25%">
                <div class="kpi-card" style="border-top: 3px solid #0284c7;">
                    <div class="kpi-title">Active Loans</div>
                    <div class="kpi-value text-blue">KES ' . number_format($total_borrowed, 2) . '</div>
                </div>
            </td>
            <td width="25%">
                <div class="kpi-card" style="border-top: 3px solid #65a30d;">
                    <div class="kpi-title">Total Repaid</div>
                    <div class="kpi-value text-lime">KES ' . number_format($total_repaid, 2) . '</div>
                </div>
            </td>
            <td width="25%">
                <div class="kpi-card" style="border-top: 3px solid #dc2626;">
                    <div class="kpi-title">Withdrawn</div>
                    <div class="kpi-value text-red">KES ' . number_format($total_withdrawn, 2) . '</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="table-wrapper">
        <h4 style="margin-bottom: 10px; color: #0f172a; text-transform: uppercase; font-size: 11px;">Detailed Records</h4>
        
        <table class="transactions">
            <thead>
                <tr>
                    <th width="15%">Date</th>
                    <th width="25%">Transaction Type</th>
                    <th width="15%">Reference</th>
                    <th width="15%">Channel</th>
                    <th width="30%" class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>';

            if (empty($transactions)) {
                $html .= '<tr><td colspan="5" style="text-align:center; padding: 30px; color: #94a3b8;">No records found matching current filters.</td></tr>';
            } else {
                foreach ($transactions as $row) {
                    $type = strtolower($row['transaction_type'] ?? '');
                    
                    // Logic matching the dashboard aesthetics
                    $is_loan = ($type == 'loan_disbursement');
                    $is_withdrawal = ($type == 'withdrawal');

                    if ($is_loan) {
                        $color_class = 'text-blue';
                        $sign = '+';
                    } elseif ($is_withdrawal) {
                        $color_class = 'text-red';
                        $sign = '-';
                    } else {
                        $color_class = 'text-lime'; 
                        $sign = '+';
                    }

                    $display_type = ucwords(str_replace('_', ' ', $type));
                    
                    // FIXED: Cast amount to float here
                    $amount = (float)$row['amount'];

                    $html .= '
                    <tr>
                        <td>' . date('d M Y', strtotime($row['transaction_date'])) . '</td>
                        <td>
                            <span style="font-weight:bold; color: #334155;">' . $display_type . '</span><br>
                            <span style="font-size: 9px; color: #64748b;">' . htmlspecialchars($row['notes'] ?? '') . '</span>
                        </td>
                        <td class="font-mono">' . htmlspecialchars($row['reference_no'] ?? '-') . '</td>
                        <td><span class="badge">' . htmlspecialchars($row['payment_channel'] ?? 'SYS') . '</span></td>
                        <td class="text-end ' . $color_class . ' fw-bold">' . $sign . ' ' . number_format($amount, 2) . '</td>
                    </tr>';
                }
            }

$html .= '
            </tbody>
        </table>
    </div>

    <div class="footer">
        Confidential Financial Report • Generated by ' . $site_name . ' • Page <span class="page-number"></span>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("Helvetica", "normal");
            $pdf->page_text(520, 800, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 9, array(0.58, 0.64, 0.72));
        }
    </script>

</body>
</html>';

// --- Output ---
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false); 

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = "Statement_" . date('Ymd_His') . ".pdf";
    $dompdf->stream($filename, ["Attachment" => true]);

} catch (Exception $e) {
    error_log("PDF Error: " . $e->getMessage());
    exit("Unable to generate PDF.");
}
?>