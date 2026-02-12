<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($conn->connect_error) die("Failed: " . $conn->connect_error);

echo "--- ROLES ---\n";
$res = $conn->query("SELECT id, name, slug FROM roles");
while ($row = $res->fetch_assoc()) echo "ID: {$row['id']} | Name: {$row['name']} | Slug: {$row['slug']}\n";

echo "\n--- PERMISSIONS ---\n";
$res = $conn->query("SELECT id, name, slug FROM permissions WHERE slug LIKE 'support_%'");
while ($row = $res->fetch_assoc()) echo "ID: {$row['id']} | Name: {$row['name']} | Slug: {$row['slug']}\n";
