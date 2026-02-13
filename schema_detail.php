// schema_detail.php
require 'c:\xampp\htdocs\usms\config\db_connect.php';

$output = "";
function detail($table) {
    global $conn, $output;
    $output .= "### Table: $table ###\n";
    $res = $conn->query("DESCRIBE `$table` ");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $output .= "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
        }
    } else {
        $output .= "Error on $table: " . $conn->error . "\n";
    }
    $output .= "\n";
}

detail('welfare_cases');
detail('welfare_support');
file_put_contents('schema_debug.txt', $output);
echo "Dumped to schema_debug.txt\n";
