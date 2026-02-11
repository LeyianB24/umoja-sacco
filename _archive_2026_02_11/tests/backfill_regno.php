<?php
require_once __DIR__ . '/../config/db_connect.php';

echo "Checking for members without RegNo...\n";
$res = $conn->query("SELECT member_id, created_at FROM members WHERE member_reg_no IS NULL OR member_reg_no = '' ORDER BY member_id ASC");
$count = $res->num_rows;

echo "Found $count members needing backfill.\n";

if ($count > 0) {
    while ($row = $res->fetch_assoc()) {
        $mid = $row['member_id'];
        $year = date('Y', strtotime($row['created_at']));
        
        // Generate Next Number
        // We need to be careful inside the loop to get the *latest* assigned number to increment correctly
        $prefix = "UDS-$year-";
        $q = $conn->query("SELECT member_reg_no FROM members WHERE member_reg_no LIKE '$prefix%' ORDER BY LENGTH(member_reg_no) DESC, member_reg_no DESC LIMIT 1");
        
        $next_num = 1;
        if ($q->num_rows > 0) {
            $last_reg = $q->fetch_assoc()['member_reg_no'];
            $parts = explode('-', $last_reg);
            $next_num = intval(end($parts)) + 1;
        }

        $new_reg = $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        
        $conn->query("UPDATE members SET member_reg_no = '$new_reg' WHERE member_id = $mid");
        echo "Updated Member ID $mid -> $new_reg\n";
    }
}
echo "Backfill complete.\n";
?>
