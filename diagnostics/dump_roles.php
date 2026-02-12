<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$out = "ROLES:\n";
$res = $conn->query("SELECT id, name, slug FROM roles");
while($r=$res->fetch_assoc()) $out .= "{$r['id']}|{$r['slug']}\n";
file_put_contents('db_dump.txt', $out);
echo "DONE";
