<?php
require_once __DIR__ . '/config/db_connect.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Architecture Audit</title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 5px; }
        h3 { color: #dcdcaa; margin-top: 20px; }
        .pass { color: #4ec9b0; }
        .warn { color: #ce9178; }
        .fail { color: #f48771; }
        pre { background: #252526; padding: 15px; border-left: 3px solid #007acc; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #3e3e42; }
        th { background: #2d2d30; color: #4ec9b0; }
    </style>
</head>
<body>

<h2>🔍 USMS Database Architecture Audit</h2>
<p>Investment-Centric Financial Engine Validation</p>

<h3>1️⃣ Investments Table Structure</h3>
<?php
$inv_cols = $conn->query("DESCRIBE investments");
echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($col = $inv_cols->fetch_assoc()) {
    echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
}
echo "</table>";

$inv_count = $conn->query("SELECT COUNT(*) as c FROM investments")->fetch_assoc();
echo "<p>Total Investments: <strong>{$inv_count['c']}</strong></p>";
?>

<h3>2️⃣ Transactions Table Structure</h3>
<?php
$trans_cols = $conn->query("DESCRIBE transactions");
echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($col = $trans_cols->fetch_assoc()) {
    echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
}
echo "</table>";

$trans_count = $conn->query("SELECT COUNT(*) as c FROM transactions")->fetch_assoc();
echo "<p>Total Transactions: <strong>{$trans_count['c']}</strong></p>";
?>

<h3>3️⃣ Data Integrity Analysis</h3>
<?php
// Orphaned revenue
$orphan_rev = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();

// Orphaned expenses
$orphan_exp = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow') 
    AND (related_table IS NULL OR related_id IS NULL OR related_id = 0)
")->fetch_assoc();

$rev_class = $orphan_rev['count'] > 0 ? 'fail' : 'pass';
$exp_class = $orphan_exp['count'] > 0 ? 'fail' : 'pass';

echo "<p class='$rev_class'>Orphaned Revenue Records: {$orphan_rev['count']}</p>";
echo "<p class='$exp_class'>Orphaned Expense Records: {$orphan_exp['count']}</p>";
?>

<h3>4️⃣ Revenue Distribution by Source</h3>
<?php
$rev_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('income', 'revenue_inflow')
    GROUP BY related_table
");
echo "<table><tr><th>Source Table</th><th>Count</th><th>Total Amount</th></tr>";
while ($row = $rev_dist->fetch_assoc()) {
    $table = $row['related_table'] ?: '<span class="warn">NULL</span>';
    echo "<tr><td>$table</td><td>{$row['count']}</td><td>KES " . number_format($row['total']) . "</td></tr>";
}
echo "</table>";
?>

<h3>5️⃣ Expense Distribution by Source</h3>
<?php
$exp_dist = $conn->query("
    SELECT related_table, COUNT(*) as count, SUM(amount) as total
    FROM transactions 
    WHERE transaction_type IN ('expense', 'expense_outflow')
    GROUP BY related_table
");
echo "<table><tr><th>Source Table</th><th>Count</th><th>Total Amount</th></tr>";
while ($row = $exp_dist->fetch_assoc()) {
    $table = $row['related_table'] ?: '<span class="warn">NULL</span>';
    echo "<tr><td>$table</td><td>{$row['count']}</td><td>KES " . number_format($row['total']) . "</td></tr>";
}
echo "</table>";
?>

<h3>6️⃣ Investment Categories (Data-Driven)</h3>
<?php
$categories = $conn->query("SELECT category, COUNT(*) as c FROM investments GROUP BY category ORDER BY category");
echo "<table><tr><th>Category</th><th>Count</th></tr>";
while ($cat = $categories->fetch_assoc()) {
    echo "<tr><td>{$cat['category']}</td><td>{$cat['c']}</td></tr>";
}
echo "</table>";
?>

<h3>7️⃣ Target Configuration</h3>
<?php
$no_target = $conn->query("SELECT COUNT(*) as c FROM investments WHERE target_amount IS NULL OR target_amount = 0")->fetch_assoc();
$target_class = $no_target['c'] > 0 ? 'warn' : 'pass';
echo "<p class='$target_class'>Investments without targets: {$no_target['c']}</p>";

$target_periods = $conn->query("SELECT target_period, COUNT(*) as c FROM investments GROUP BY target_period");
echo "<table><tr><th>Target Period</th><th>Count</th></tr>";
while ($tp = $target_periods->fetch_assoc()) {
    $period = $tp['target_period'] ?: '<span class="warn">NULL</span>';
    echo "<tr><td>$period</td><td>{$tp['c']}</td></tr>";
}
echo "</table>";
?>

<h3>8️⃣ Asset Lifecycle Management</h3>
<?php
$statuses = $conn->query("SELECT status, COUNT(*) as c FROM investments GROUP BY status");
echo "<table><tr><th>Status</th><th>Count</th></tr>";
while ($st = $statuses->fetch_assoc()) {
    echo "<tr><td>{$st['status']}</td><td>{$st['c']}</td></tr>";
}
echo "</table>";

$sold_check = $conn->query("
    SELECT COUNT(*) as with_data
    FROM investments 
    WHERE status = 'sold' 
    AND sale_price IS NOT NULL 
    AND sale_date IS NOT NULL
")->fetch_assoc();
$total_sold = $conn->query("SELECT COUNT(*) as c FROM investments WHERE status = 'sold'")->fetch_assoc();
echo "<p>Sold Assets with Complete Data: {$sold_check['with_data']} / {$total_sold['c']}</p>";
?>

<h3>9️⃣ Foreign Key Constraints</h3>
<?php
$fk_check = $conn->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
    AND TABLE_NAME IN ('transactions', 'investments', 'vehicles')
");

if ($fk_check->num_rows > 0) {
    echo "<table><tr><th>Table</th><th>Column</th><th>References</th></tr>";
    while ($fk = $fk_check->fetch_assoc()) {
        echo "<tr><td>{$fk['TABLE_NAME']}</td><td>{$fk['COLUMN_NAME']}</td><td>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warn'>⚠️ No foreign key constraints found on core tables</p>";
}
?>

<h3>🔟 Summary & Recommendations</h3>
<?php
$issues = [];

if ($orphan_rev['count'] > 0) {
    $issues[] = "<span class='fail'>❌ {$orphan_rev['count']} revenue records are not linked to investments</span>";
}

if ($orphan_exp['count'] > 0) {
    $issues[] = "<span class='fail'>❌ {$orphan_exp['count']} expense records are not linked to investments</span>";
}

if ($no_target['c'] > 0) {
    $issues[] = "<span class='warn'>⚠️ {$no_target['c']} investments lack performance targets</span>";
}

if ($fk_check->num_rows == 0) {
    $issues[] = "<span class='warn'>⚠️ No foreign key constraints enforcing referential integrity</span>";
}

if (empty($issues)) {
    echo "<p class='pass'>✅ Architecture appears sound - all checks passed</p>";
} else {
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
}
?>

</body>
</html>
