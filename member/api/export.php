<?php
// member/export.php
// Unified Export Endpoint for Member Data
session_start();
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/ExportHelper.php';

if (!isset($_SESSION['member_id'])) die("Unauthorized");

$member_id = $_SESSION['member_id'];
$type = $_GET['type'] ?? 'pdf'; // pdf or excel
$page = $_GET['page'] ?? 'loans'; // loans, savings, transactions, shares, welfare

// Fetch Member Name
$stmt = $conn->prepare("SELECT full_name FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member_name = $stmt->get_result()->fetch_assoc()['full_name'] ?? 'Member';
$stmt->close();

$data = [];
$headers = [];
$title = "";

// Route based on page
switch($page) {
    case 'loans':
        $title = "Loan Portfolio - $member_name";
        $headers = ['Date', 'Amount', 'Interest', 'Status', 'Balance'];
        
        $sql = "SELECT created_at, amount, interest_rate, status, current_balance 
                FROM loans WHERE member_id = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while($row = $res->fetch_assoc()) {
            $data[] = [
                date('d M Y', strtotime($row['created_at'])),
                'KES ' . number_format($row['amount'], 2),
                $row['interest_rate'] . '%',
                ucfirst($row['status']),
                'KES ' . number_format($row['current_balance'], 2)
            ];
        }
        $stmt->close();
        break;
        
    case 'savings':
        $title = "Savings Statement - $member_name";
        $headers = ['Date', 'Type', 'Amount', 'Balance'];
        
        // 1. Get Current Balance from Golden Ledger (Single Source of Truth)
        require_once __DIR__ . '/../../inc/FinancialEngine.php';
        $engine = new FinancialEngine($conn);
        $balances = $engine->getBalances($member_id);
        $cur_bal = $balances['savings'];
        
        // 2. Fetch Transactions (Newest First)
        $sql = "SELECT transaction_date, transaction_type, amount FROM transactions 
                WHERE member_id = ? AND transaction_type IN ('deposit', 'withdrawal', 'interest', 'transfer', 'dividend') 
                ORDER BY transaction_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while($row = $res->fetch_assoc()) {
            $amt = (float)$row['amount'];
            $type = $row['transaction_type'];
            
            $data[] = [
                date('d M Y', strtotime($row['transaction_date'])),
                ucfirst(str_replace('_', ' ', $type)),
                'KES ' . number_format($amt, 2),
                'KES ' . number_format($cur_bal, 2)
            ];
            
            // Reverse engineering the balance for the previous (older) row
            // If the transaction INCREASED balance, we SUBTRACT to find pre-txn state.
            // If the transaction DECREASED balance, we ADD to find pre-txn state.
            if (in_array($type, ['deposit', 'interest', 'dividend'])) {
                $cur_bal -= $amt;
            } else {
                $cur_bal += $amt;
            }
        }
        $stmt->close();
        break;
        
    case 'transactions':
        $title = "Transaction Ledger - $member_name";
        $headers = ['Date', 'Type', 'Reference', 'Amount'];
        
        $sql = "SELECT transaction_date, transaction_type, reference_no, amount 
                FROM transactions WHERE member_id = ? ORDER BY transaction_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while($row = $res->fetch_assoc()) {
            $data[] = [
                date('d M Y', strtotime($row['transaction_date'])),
                strtoupper(str_replace('_', ' ', $row['transaction_type'])),
                $row['reference_no'],
                'KES ' . number_format($row['amount'], 2)
            ];
        }
        $stmt->close();
        break;
        
    case 'shares':
        $title = "Share Capital Contributions - $member_name";
        $headers = ['Date', 'Reference', 'Amount', 'Notes'];
        
        $sql = "SELECT transaction_date, reference_no, amount, notes 
                FROM transactions WHERE member_id = ? AND related_table = 'shares' 
                ORDER BY transaction_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while($row = $res->fetch_assoc()) {
            $data[] = [
                date('d M Y', strtotime($row['transaction_date'])),
                $row['reference_no'],
                'KES ' . number_format($row['amount'], 2),
                $row['notes']
            ];
        }
        $stmt->close();
        break;
        
    case 'welfare':
        $title = "Welfare Contributions - $member_name";
        $headers = ['Date', 'Type', 'Amount', 'Notes'];
        
        $sql = "SELECT transaction_date, transaction_type, amount, notes 
                FROM transactions WHERE member_id = ? AND related_table = 'welfare' 
                ORDER BY transaction_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while($row = $res->fetch_assoc()) {
            $data[] = [
                date('d M Y', strtotime($row['transaction_date'])),
                ucfirst($row['transaction_type']), // e.g. 'deposit'
                'KES ' . number_format($row['amount'], 2),
                $row['notes'] ?? '-'
            ];
        }
        $stmt->close();
        break;
}

// Export
if($type === 'excel') {
    ExportHelper::csv("{$page}_" . date('Ymd'), $headers, $data);
} else {
    ExportHelper::pdf($title, $headers, $data, "{$page}_" . date('Ymd') . ".pdf");
}
