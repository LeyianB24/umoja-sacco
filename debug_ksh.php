<?php
require_once 'config/app_config.php';
require_once 'inc/Auth.php'; // Includes functions.php

if (function_exists('ksh')) {
    echo "ksh() exists";
} else {
    echo "ksh() does NOT exist";
}
?>
