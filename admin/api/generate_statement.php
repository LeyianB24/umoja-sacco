<?php
declare(strict_types=1);

/**
 * admin/api/generate_statement.php
 * Unified controller for SACCO member statements
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/ExportHelper.php';
require_once __DIR__ . '/../../inc/functions.php';

Auth::requireAdmin();

// 1. Inputs
$member_id   = isset($_POST['member_id'])   ? intval($_POST['member_id'])   : 0;
$start_date  = isset($_POST['start_date'])  ? $_POST['start_date']           : '';
$end_date    = isset($_POST['end_date'])    ? $_POST['end_date']             : '';
$report_type = isset($_POST['report_type']) ? $_POST['report_type']          : 'full';
$format      = isset($_POST['format'])      ? $_POST['format']               : 'pdf';

if (!$member_id || !$start_date || !$end_date) {
    die("Error: Required parameters (Member, Start Date, End Date) are missing.");
}

// 2. Member Fetching
$stmt = $conn->prepare("SELECT member_id, full_name, member_reg_no, national_id, phone FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (!$member) {
    die("Error: Member registry record not found for ID: " . $member_id);
}

// 3. Opening Balance Calculation (Cumulative flow before start_date)
$sql_ob = "SELECT 
    SUM(CASE 
        WHEN transaction_type IN ('deposit','income','revenue_inflow','loan_repayment','share_capital') THEN amount 
        ELSE -amount 
    END) as ob
    FROM transactions 
    WHERE member_id = ? AND created_at < ?";
$start_dt_full = "$start_date 00:00:00";
$stmt_ob = $conn->prepare($sql_ob);
$stmt_ob->bind_param("is", $member_id, $start_dt_full);
$stmt_ob->execute();
$openingBalance = (float)($stmt_ob->get_result()->fetch_assoc()['ob'] ?? 0);

// 4. Transaction Fetching
$end_dt_full = "$end_date 23:59:59";
$where = "member_id = ? AND created_at BETWEEN ? AND ?";
$params = [$member_id, $start_dt_full, $end_dt_full];
$types = "iss";

if ($report_type === 'savings') {
    $where .= " AND transaction_type IN ('deposit', 'withdrawal')";
} elseif ($report_type === 'loans') {
    $where .= " AND transaction_type IN ('loan_disbursement', 'loan_repayment')";
}

$sql = "SELECT * FROM transactions WHERE $where ORDER BY created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 5. Data Formatting
$reportData = [];
$runningBalance = $openingBalance;

// Add Opening Balance Row
$reportData[] = [
    'Date'      => date('d-M-Y', strtotime($start_date)),
    'Reference' => 'B/F',
    'Type'      => 'OPENING BALANCE',
    'In'        => '-',
    'Out'       => '-',
    'Balance'   => number_format($openingBalance, 2)
];

foreach ($transactions as $row) {
    $is_in = in_array($row['transaction_type'], ['deposit','income','revenue_inflow','loan_repayment','share_capital']);
    $amount = (float)$row['amount'];
    
    if ($is_in) {
        $in = $amount;
        $out = 0;
        $runningBalance += $amount;
    } else {
        $in = 0;
        $out = $amount;
        $runningBalance -= $amount;
    }

    $reportData[] = [
        'Date'      => date('d-M-Y H:i', strtotime($row['created_at'])),
        'Reference' => $row['reference_no'],
        'Type'      => ucwords(str_replace('_', ' ', $row['transaction_type'])),
        'In'        => $in > 0 ? number_format($in, 2) : '-',
        'Out'       => $out > 0 ? number_format($out, 2) : '-',
        'Balance'   => number_format($runningBalance, 2)
    ];
}

// Metadata for the report
$reportTitle = strtoupper($report_type) . " STATEMENT";
$subTitle = "Member: " . $member['full_name'] . " (" . $member['member_reg_no'] . ")";
$period = "Period: " . date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));

$headers = ['Date', 'Reference', 'Type', 'In (KES)', 'Out (KES)', 'Balance (KES)'];

// 6. Export Execution
if ($format === 'pdf') {
    // We pass custom info as options to the PDF engine
    ExportHelper::pdf($reportTitle, $headers, $reportData, "statement_" . $member['member_reg_no'] . ".pdf", 'D', [
        'module'   => $subTitle,
        'filters'  => ['Range' => $period, 'National ID' => $member['national_id']],
        'orientation' => 'P'
    ]);
} else {
    // Excel/CSV
    ExportHelper::csv("statement_" . $member['member_reg_no'] . ".csv", $headers, $reportData);
}

exit;
