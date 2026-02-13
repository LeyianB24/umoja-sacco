<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'config/db_connect.php';

echo "Starting donation synchronization...\n";

try {
    // 1. Identify orphan welfare contributions
    $sqlOrphans = "SELECT c.* FROM contributions c 
                   LEFT JOIN welfare_donations wd ON c.reference_no = wd.reference_no 
                   WHERE c.contribution_type = 'welfare' 
                   AND c.status = 'active'
                   AND wd.donation_id IS NULL";

    $res = $conn->query($sqlOrphans);
    $orphans = [];
    while($row = $res->fetch_assoc()) {
        $orphans[] = $row;
    }

    echo "Found " . count($orphans) . " orphaned welfare contributions.\n";

    $case_id = 4; // Target Case identified by USER
    $added_count = 0;

    foreach ($orphans as $o) {
        $ref = $o['reference_no'];
        $amount = (float)$o['amount'];
        $mid = $o['member_id'];
        $date = $o['created_at'];

        echo "Processing Ref: $ref | Amount: $amount | Member: $mid\n";

        // Insert into welfare_donations
        $sql = "INSERT INTO welfare_donations (case_id, member_id, amount, donation_date, reference_no, status) VALUES (?, ?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("iidss", $case_id, $mid, $amount, $date, $ref);
        
        if ($stmt->execute()) {
            $added_count++;
            echo " - Linked successfully.\n";
        } else {
            echo " - Failed: " . $stmt->error . "\n";
        }
        $stmt->close();
    }

    echo "Successfully linked $added_count donations to Case #$case_id.\n";

    // 2. Recalculate total_raised
    echo "Recalculating totals...\n";
    $agg = $conn->query("SELECT case_id, SUM(amount) as total FROM welfare_donations GROUP BY case_id");
    while ($row = $agg->fetch_assoc()) {
        $cid = $row['case_id'];
        $total = $row['total'];
        $conn->query("UPDATE welfare_cases SET total_raised = $total WHERE case_id = $cid");
        echo " - Case #$cid updated to $total\n";
    }

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "\nSynchronization process finished.\n";
?>
