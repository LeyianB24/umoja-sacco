<?php
include('../config/db_connect.php');


if (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  
  $delete_query = "DELETE FROM members WHERE id = $id";
  
  if ($conn->query($delete_query)) {
    header('Location: manage_members.php');
    exit;
  } else {
    echo "Error deleting record: " . $conn->error;
  }
}
?>
