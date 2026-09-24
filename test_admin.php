<?php
include("config/db_connect.php");

$email = 'admin@usms.com';
$sql = "SELECT * FROM admin WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $row = $result->fetch_assoc();
  echo "<pre>";
  print_r($row);
  echo "</pre>";
} else {
  echo "No admin found.";
}
?>
