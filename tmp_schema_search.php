<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$output = "";
function inspect($conn, $query, &$output) {
    $res = $conn->query($query);
    while($row = $res->fetch_row()) {
        $table = $row[0];
        $output .= "--- $table ---\n";
        $res2 = $conn->query("DESCRIBE $table");
        while($row2 = $res2->fetch_assoc()) {
            $output .= "- {$row2['Field']} ({$row2['Type']})\n";
        }
    }
}
inspect($conn, "SHOW TABLES LIKE '%loan%'", $output);
inspect($conn, "SHOW TABLES LIKE '%repayment%'", $output);
inspect($conn, "SHOW TABLES LIKE '%penalty%'", $output);
inspect($conn, "SHOW TABLES LIKE '%fine%'", $output);
file_put_contents('c:/xampp/htdocs/usms/schema_inspection.txt', $output);
