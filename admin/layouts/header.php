<?php
// admin/layouts/header.php — Umoja Sacco Admin Layout Header
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/app_config.php';

// CSRF token seeding
if (class_exists('USMS\\Middleware\\CsrfMiddleware')) {
    \USMS\Middleware\CsrfMiddleware::token();
}

// Auth guard — redirect to login if no admin session
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/public/login.php?error=unauthorized');
    exit;
}

$pageTitle   = $pageTitle   ?? 'Admin Portal';
$activePage  = $activePage  ?? '';          // set by page before including layout
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php if (class_exists('USMS\\Middleware\\CsrfMiddleware')): ?>
    <?= \USMS\Middleware\CsrfMiddleware::metaTag() ?>
    <?php endif; ?>
    <title><?= htmlspecialchars($pageTitle) ?> — <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Sacco Admin' ?></title>

    <!-- Theme init: must be first to prevent FOUC -->
    <script>(function(){const t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Umoja Admin Design System (canonical single include) -->
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/admin.css">
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/layout.css">
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/darkmode.css">

    <?php if (isset($extraCss)): ?>
    <?= $extraCss ?>
    <?php endif; ?>
</head>
<body class="admin-body">
<!-- Page layout continues in admin/layouts/footer.php or individual pages -->
