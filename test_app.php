<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require "config/app.php";
echo "app.php loaded OK\n";
echo "BASE_PATH: " . BASE_PATH . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "Connected: " . ($conn->ping() ? 'yes' : 'no') . "\n";
