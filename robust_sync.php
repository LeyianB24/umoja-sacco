<?php
require 'config/db_connect.php';

echo "Starting corrected donation synchronization...\n";

// 1. Get already linked refs
try {
    $res = $conn->query("SELECT reference_no FROM welfare_donations");
    $linked_refs = [];
    while($row = $res->fetch_assoc()) $linked_refs[] = "'" . $conn->real_escape_string($row['reference_no']) . "'";
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

    $case_id = 4;
    $added_count = 0;

    foreach ($orphans as $o) {
        // Use 'created_at' instead of 'donation_date'
        $stmt = $conn->prepare("INSERT INTO welfare_donations (case_id, member_id, amount, created_at, reference_no) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception($conn->error);
        
        $amount = (float)$o['amount'];
        $mid = $o['member_id'];
        $date = $o['created_at'];
        $ref = $o['reference_no'];
        
        $stmt->bind_param("iidss", $case_id, $mid, $amount, $date, $ref);
        
        if ($stmt->execute()) {
            $added_count++;
            echo "Successfully linked Ref: $ref\n";
        } else {
            echo "Failed to link Ref: $ref | Error: " . $stmt->error . "\n";
        }
        $stmt->close();
    }

    echo "Linked $added_count donations to Case #$case_id.\n";

    // 3. Update totals
    // We don't reset to 0 here to be safe and avoid flickering, just update based on SUM
    $res = $conn->query("SELECT case_id, SUM(amount) as total FROM welfare_donations GROUP BY case_id");
    while ($row = $res->fetch_assoc()) {
        $cid = $row['case_id'];
        $total = $row['total'];
        $conn->query("UPDATE welfare_cases SET total_raised = $total WHERE case_id = $cid");
        echo " - Case #$cid updated to $total\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "Sync finished.\n";
?>
