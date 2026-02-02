<?php
// member/auth_check.php
// Consolidated Member Security & Pay-Gate Enforcer

require_once __DIR__ . '/../../../inc/Auth.php';

// 1. Mandatory Member Check
require_member();

// 2. Pay-Gate enforcement (The "Walled Garden")
// Only allow these pages if fee is unpaid
$currentPage = basename($_SERVER['PHP_SELF']);
$allowedPages = ['pay_registration.php', 'logout.php'];

if (($_SESSION['registration_fee_status'] ?? 'unpaid') === 'unpaid') {
    if (!in_array($currentPage, $allowedPages)) {
        flash_set('Account Activation Required: Please pay your registration fee to continue.', 'warning');
        header("Location: pay_registration.php");
        exit;
    }
}
