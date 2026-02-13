<?php
header('Content-Type: text/plain; charset=utf-8');
require 'config/db_connect.php';
$res = $conn->query("SELECT case_id, title, status, related_member_id, target_amount, total_raised FROM welfare_cases");
$out = "";
while($row = $res->fetch_assoc()) {
    $out .= json_encode($row) . "\n";
}
file_put_contents('cases_output.txt', $out);
echo "Done written to cases_output.txt";
?>
