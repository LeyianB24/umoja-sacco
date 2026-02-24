<?php
require 'config/app.php';
$res = $conn->query("DESCRIBE loan_repayments");
$out = "";
while($row = $res->fetch_assoc()) {
    $out .= $row['Field'] . " (" . $row['Type'] . ")\n";
}
file_put_contents('repayment_schema.txt', $out);
echo "Done";
