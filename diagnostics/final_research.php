<?php
$conn = new mysqli('localhost', 'root', '', 'umoja_drivers_sacco');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$results = "--- ROLES ---\n";
$res = $conn->query("SELECT id, name, slug FROM roles");
while ($row = $res->fetch_assoc()) {
    $results .= "ID: {$row['id']} | Name: {$row['name']} | Slug: {$row['slug']}\n";
}

$results .= "\n--- SUPPORT PERMISSIONS ---\n";
$res = $conn->query("SELECT id, name, slug FROM permissions WHERE slug LIKE 'support_%'");
while ($row = $res->fetch_assoc()) {
    $results .= "ID: {$row['id']} | Name: {$row['name']} | Slug: {$row['slug']}\n";
}

$results .= "\n--- SAMPLE ADMIN ---\n";
$res = $conn->query("SELECT admin_id, full_name, role_id FROM admins LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $results .= "Admin: {$row['full_name']} | Role ID: {$row['role_id']}\n";
}

file_put_contents(__DIR__ . '/research_results.txt', $results);
echo "Results saved to research_results.txt\n";
