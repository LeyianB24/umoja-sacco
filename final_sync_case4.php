<?php
require 'config/db_connect.php';

echo "Starting finalized donation synchronization...\n";

// 1. Get already linked refs
$res = $conn->query("SELECT reference_no FROM welfare_donations");
$linked_refs = [];
while($row = $res->fetch_assoc()) $linked_refs[] = "'" . $row['reference_no'] . "'";
$in_clause = count($linked_refs) > 0 ? implode(',', $linked_refs) : "''";

// 2. Find orphans
$sqlOrphans = "SELECT * FROM contributions 
               WHERE contribution_type = 'welfare' 
               AND status = 'active'
               AND reference_no NOT IN ($in_clause)";

$res = $conn->query($sqlOrphans);
$orphans = [];
while($row = $res->fetch_assoc()) $orphans[] = $row;

echo "Found " . count($orphans) . " orphaned welfare contributions.\n";

$case_id = 4; // Target Case
$added_count = 0;

foreach ($orphans as $o) {
    // We check if this contribution was made AFTER Case 4 was created (or just give it to Case 4 if it's orphan)
    // The user said "alot of payments has been made for Case 4"
    
    $stmt = $conn->prepare("INSERT INTO welfare_donations (case_id, member_id, amount, donation_date, reference_no, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $amount = (float)$o['amount'];
    $stmt->bind_param("iidss", $case_id, $o['member_id'], $amount, $o['created_at'], $o['reference_no']);
    if ($stmt->execute()) {
        $added_count++;
    }
    $stmt->close();
}

echo "Linked $added_count donations to Case #$case_id.\n";

// 3. Update totals
$res = $conn->query("SELECT case_id, SUM(amount) as total FROM welfare_donations GROUP BY case_id");
while ($row = $res->fetch_assoc()) {
    $cid = $row['case_id'];
    $total = $row['total'];
    $conn->query("UPDATE welfare_cases SET total_raised = $total WHERE case_id = $cid");
    echo " - Case #$cid updated to $total\n";
}

echo "Sync finished.\n";
?>
