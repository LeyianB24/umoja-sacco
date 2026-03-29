<?php
$log = "C:/xampp/apache/logs/error.log";
if (!file_exists($log)) {
    die("Not found");
}
$lines = file($log);
$last = array_slice($lines, -50);
echo implode("", $last);
