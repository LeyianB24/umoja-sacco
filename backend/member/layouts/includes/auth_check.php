<?php
// member/auth_check.php
// Consolidated Member Security

require_once __DIR__ . '/../../../inc/auth.php';

// 1. Mandatory Member Check
require_member();

// 2. Pay-Gate enforcement (REMOVED as per user request)
// Members can now access all pages regardless of registration fee status.
/*
$currentPage = basename($_SERVER['PHP_SELF']);
$allowedPages = ['pay_registration.php', 'logout.php'];

if (($_SESSION['registration_fee_status'] ?? 'unpaid') === 'unpaid') {
    if (!in_array($currentPage, $allowedPages)) {
        flash_set('Account Activation Required: Please pay your registration fee to continue.', 'warning');
        header("Location: pay_registration.php");
        exit;
    }
}
*/
