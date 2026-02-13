<?php
require 'config/db_connect.php';

$res = $conn->query("SELECT reference_no FROM contributions WHERE contribution_type = 'welfare' AND status = 'active'");
$contrib_refs = [];
while($row = $res->fetch_assoc()) $contrib_refs[] = $row['reference_no'];

$res = $conn->query("SELECT reference_no FROM welfare_donations");
$donation_refs = [];
while($row = $res->fetch_assoc()) $donation_refs[] = $row['reference_no'];

$orphans = array_diff($contrib_refs, $donation_refs);

echo "Total Welfare Contributions: " . count($contrib_refs) . "\n";
echo "Total Welfare Donations: " . count($donation_refs) . "\n";
echo "Orphans Found: " . count($orphans) . "\n";

if (count($orphans) > 0) {
    echo "Sample Orphan Ref: " . reset($orphans) . "\n";
}
?>
