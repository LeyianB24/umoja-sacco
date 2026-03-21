<?php
require_once 'c:/xampp/htdocs/usms/core/Database/Database.php';
use USMS\Database\Database;

$pdo = Database::getInstance()->getPdo();
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

echo "Today: $today\n";

// Ensure loan 26 is overdue
$pdo->prepare("UPDATE loans SET next_repayment_date = ?, status = 'disbursed' WHERE loan_id = 26")
    ->execute([$yesterday]);

// Clear fine for today
$pdo->prepare("DELETE FROM fines WHERE loan_id = 26 AND date_applied = ?")
    ->execute([$today]);

$sql = "SELECT l.loan_id, l.member_id, l.next_repayment_date, l.current_balance 
        FROM loans l
        WHERE l.status IN ('active', 'disbursed') 
        AND l.next_repayment_date < ? 
        AND NOT EXISTS (
            SELECT 1 FROM fines f 
            WHERE f.loan_id = l.loan_id AND f.date_applied = ?
        )";

$stmt = $pdo->prepare($sql);
$stmt->execute([$today, $today]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found Rows: " . count($rows) . "\n";
print_r($rows);

if (count($rows) > 0) {
    echo "SQL logic is CORRECT.\n";
} else {
    echo "SQL logic is FAILING to find the loan.\n";
    
    // Check components
    $res = $pdo->query("SELECT loan_id, next_repayment_date FROM loans WHERE loan_id = 26")->fetch(PDO::FETCH_ASSOC);
    echo "Loan 26 Next Repayment Date in DB: " . $res['next_repayment_date'] . "\n";
    echo "Comparison: " . $res['next_repayment_date'] . " < " . $today . " is " . ($res['next_repayment_date'] < $today ? "TRUE" : "FALSE") . "\n";
}
