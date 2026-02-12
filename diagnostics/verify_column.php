<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$res = $conn->query("SHOW COLUMNS FROM support_tickets LIKE 'assigned_role_id'");
if ($res->num_rows > 0) {
    echo "COLUMN_EXISTS: assigned_role_id\n";
} else {
    echo "COLUMN_MISSING: assigned_role_id\n";
}
