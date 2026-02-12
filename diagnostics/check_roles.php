<?php
require_once __DIR__ . '/../config/db_connect.php';
$res = $conn->query("SELECT id, slug FROM roles");
$roles = [];
while($r = $res->fetch_assoc()) $roles[$r['slug']] = $r['id'];
echo json_encode($roles, JSON_PRETTY_PRINT);
