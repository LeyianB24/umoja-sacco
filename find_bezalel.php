<?php
require_once 'c:/xampp/htdocs/usms/config/app.php';
$res = $conn->query("SELECT member_id, email, full_name FROM members WHERE email = 'bezaleltomaka@gmail.com'");
if ($row = $res->fetch_assoc()) {
    print_r($row);
} else {
    echo "Member not found.";
}
