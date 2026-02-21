<?php
declare(strict_types=1);

// tests/bootstrap.php

// 1. Load Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Define testing environment constants
if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}

// 3. Try to get connection from GLOBALS first (if already loaded)
if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof \mysqli)) {
    // 4. Force load db_connect.php in global scope
    $host   = 'localhost';
    $user   = 'root';
    $pass   = '';
    $dbname = 'umoja_drivers_sacco';

    $GLOBALS['conn'] = new \mysqli($host, $user, $pass, $dbname);
    
    if ($GLOBALS['conn']->connect_errno) {
        die("DATABASE CONNECTION FAILED: " . $GLOBALS['conn']->connect_error . "\n");
    }
}

$conn = $GLOBALS['conn'];
if (!$conn instanceof \mysqli) {
    die("FAILED TO INITIALIZE DATABASE CONNECTION FOR TESTS.\n");
}
