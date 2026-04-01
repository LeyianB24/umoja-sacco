<?php
/**
 * check_duplicates.php
 * Standalone audit tool (v6 - with error reporting)
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');

echo "--- 🔍 DOUBLE ENTRY AUDIT START ---\n\n";

try {
    echo "[1] Checking Contributions...\n";
    $res = $conn->query("SELECT reference_no, COUNT(*) as cnt FROM contributions WHERE status='active' AND reference_no != '' AND reference_no IS NOT NULL GROUP BY reference_no HAVING cnt > 1");
    if ($res->num_rows > 0) {
        while($row = $res->fetch_assoc()) echo "  ⚠️ Duplicate Reference: " . $row['reference_no'] . " (Found " . $row['cnt'] . ")\n";
    } else echo "  ✅ clean.\n";

    echo "\n[2] Checking Callbacks...\n";
    $res = $conn->query("SELECT mpesa_receipt_number, COUNT(*) as cnt FROM callback_logs WHERE processed=1 AND mpesa_receipt_number != '' AND mpesa_receipt_number IS NOT NULL GROUP BY mpesa_receipt_number HAVING cnt > 1");
    if ($res->num_rows > 0) {
        while($row = $res->fetch_assoc()) echo "  ⚠️ Duplicate Receipt: " . $row['mpesa_receipt_number'] . " (Found " . $row['cnt'] . ")\n";
    } else echo "  ✅ clean.\n";

    echo "\n[3] Checking Ledger suspects...\n";
    // Modified to be simpler and bypass potential TEXT issues
    $res = $conn->query("SELECT member_id, entry_date, credit, debit, COUNT(*) as cnt FROM ledger_entries GROUP BY member_id, entry_date, credit, debit HAVING cnt > 2");
    if ($res->num_rows > 0) {
        while($row = $res->fetch_assoc()) echo "  ⚠️ Suspicious Ledger Cluster: Member #" . $row['member_id'] . " on " . $row['entry_date'] . " (Count: " . $row['cnt'] . ")\n";
    } else echo "  ✅ clean.\n";

} catch (Exception $e) {
    echo "\n❌ AUDIT FAILED: " . $e->getMessage() . "\n";
}

echo "\n--- AUDIT COMPLETE ---\n";
?>
