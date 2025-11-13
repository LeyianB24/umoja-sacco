<?php
// usms/inc/functions.php
// Small helpers used across pages

// If config not loaded, attempt to include
if (!defined('ASSET_BASE')) {
    @include_once __DIR__ . '/../config/app_config.php';
}

/**
 * Escape output for HTML
 */
function esc($str)
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Set flash message in session
 * type: success|error|info
 */
function flash_set($message, $type = 'info')
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'][] = ['msg' => $message, 'type' => $type];
}

/**
 * Render flash messages (and clear them)
 */
function flash_render()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $f) {
        $type = $f['type'];
        $cls = 'info';
        if ($type === 'success') $cls = 'success';
        if ($type === 'error') $cls = 'danger';
        echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">';
        echo esc($f['msg']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    unset($_SESSION['flash']);
}

/**
 * Helper redirect
 */
function redirect_to($url)
{
    header("Location: " . $url);
    exit;
}

/**
 * Generate a secure remember token pair (raw token for cookie, hashed for DB)
 */
function remember_generate_pair()
{
    $token = bin2hex(random_bytes(32)); // raw token
    $hash = hash('sha256', $token);
    return ['token' => $token, 'hash' => $hash];
}
