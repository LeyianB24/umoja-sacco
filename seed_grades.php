<?php
require_once __DIR__ . '/config/db_connect.php';
$conn->query("INSERT IGNORE INTO salary_grades (grade_name, basic_salary, house_allowance, transport_allowance) VALUES 
('M1 - Senior Manager', 120000, 40000, 15000),
('M2 - Assistant Manager', 90000, 30000, 10000),
('O1 - Senior Officer', 70000, 20000, 8000),
('O2 - Officer', 50000, 15000, 5000),
('S1 - Support Staff', 30000, 10000, 3000),
('S2 - Intern/Casual', 15000, 5000, 2000)");
echo "Grades seeded.";
?>
