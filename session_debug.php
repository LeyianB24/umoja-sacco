<?php
session_start();
echo "<h3>Session Debug</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

require_once 'config/app.php';
echo "<h3>Config Constants</h3>";
echo "BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'NOT DEFINED') . "<br>";
echo "PUBLIC_URL: " . (defined('PUBLIC_URL') ? PUBLIC_URL : 'NOT DEFINED') . "<br>";
echo "ASSET_BASE: " . (defined('ASSET_BASE') ? ASSET_BASE : 'NOT DEFINED') . "<br>";
echo "SITE_URL: " . (defined('SITE_URL') ? SITE_URL : 'NOT DEFINED') . "<br>";

echo "<h3>Server Variables</h3>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "<br>";
?>
