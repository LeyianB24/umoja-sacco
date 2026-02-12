<?php
$c = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
$res = $c->query("SELECT id, slug FROM roles");
$mapping = [];
while($row = $res->fetch_assoc()) $mapping[$row['slug']] = $row['id'];
echo json_encode($mapping);
