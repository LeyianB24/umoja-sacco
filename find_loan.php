<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT loan_id, member_id FROM loans WHERE status IN ('disbursed', 'active') LIMIT 1");
if ($row = $res->fetch_assoc()) {
    echo "ID:" . $row['loan_id'] . "\nMEMBER:" . $row['member_id'] . "\n";
} else {
    echo "NONE\n";
}
