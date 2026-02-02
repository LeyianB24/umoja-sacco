<?php
// admin/layouts/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/app_config.php';

// Force Admin Login Check if not already handled
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$pageTitle = isset($pageTitle) ? $pageTitle : 'Admin Portal';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?> - <?= SITE_NAME ?></title>

    <!-- FONTS -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- BOOTSTRAP 5 & ICONS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- GLOBAL CSS -->
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css"> 
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/ui_kit_v9.css">
    
    <!-- LAYOUT SPECIFIC CSS can be loaded here if separated -->
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/layout.css">
    
    <script>
        // Pre-apply theme to avoid flash
        (function() {
            const saved = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', saved);
        })();
    </script>
</head>
<body class="admin-body">
    <!-- Main Wrapper usually starts here or in the page -->
