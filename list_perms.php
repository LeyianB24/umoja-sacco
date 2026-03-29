<?php
require_once 'config/app.php';
$r = $conn->query('SELECT slug FROM permissions');
if (!$r) die($conn->error);
while($row=$r->fetch_assoc()){ echo $row['slug'] . "\n"; }
