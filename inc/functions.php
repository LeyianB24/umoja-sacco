<?php
// usms/inc/functions.php

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Direct access forbidden.');
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If config not loaded, attempt to include
if (!defined('ASSET_BASE')) {
    @include_once __DIR__ . '/../config/app_config.php';
}

/* ==========================================================================
   SECURITY & SANITIZATION
   ========================================================================== */

/**
 * Escape output for HTML (Prevent XSS)
 * @param string|null $str
 * @return string
 */
function esc(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate CSRF Token
 * Call this at the top of your forms
 */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field
 */
function csrf_field()
{
    $token = csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . esc($token) . '">';
}

/**
 * Verify CSRF Token
 * Call this at the top of POST processing
 */
function verify_csrf_token()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die('Security Token Mismatch (CSRF). Please reload the page and try again.');
        }
    }
}

/* ==========================================================================
   USER INTERFACE & FLASH MESSAGES
   ========================================================================== */

/**
 * Set flash message in session
 * @param string $message
 * @param string $type success|error|info|warning
 */
function flash_set($message, $type = 'info')
{
    $_SESSION['flash'][] = [
        'msg' => $message, 
        'type' => $type
    ];
}

/**
 * Render flash messages with Bootstrap Icons
 */
function flash_render()
{
    if (empty($_SESSION['flash'])) return;

    foreach ($_SESSION['flash'] as $f) {
        $type = $f['type'];
        
        // Map types to Bootstrap classes and Icons
        $map = [
            'success' => ['class' => 'success', 'icon' => 'check-circle-fill'],
            'error'   => ['class' => 'danger',  'icon' => 'exclamation-triangle-fill'],
            'warning' => ['class' => 'warning', 'icon' => 'exclamation-circle-fill'],
            'info'    => ['class' => 'info',    'icon' => 'info-circle-fill']
        ];

        $style = $map[$type] ?? $map['info'];

        echo '<div class="alert alert-' . $style['class'] . ' alert-dismissible fade show shadow-sm" role="alert">';
        echo '<i class="bi bi-' . $style['icon'] . ' me-2"></i>';
        echo esc($f['msg']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    
    unset($_SESSION['flash']);
}

/**
 * Helper to repopulate form fields on error
 * Usage: value="<?= old('email') ?>"
 */
function old($key, $default = '')
{
    return isset($_POST[$key]) ? esc($_POST[$key]) : esc($default);
}

/* ==========================================================================
   NAVIGATION & HTTP
   ========================================================================== */

/**
 * Standard Redirect
 */
function redirect_to($url)
{
    if (!headers_sent()) {
        header("Location: " . $url);
    } else {
        echo "<script>window.location.href='" . $url . "';</script>";
    }
    exit;
}

/**
 * Redirect with a Flash Message (Shorthand)
 */
function redirect_with($url, $message, $type = 'success')
{
    flash_set($message, $type);
    redirect_to($url);
}

/* ==========================================================================
   DATA FORMATTING (SACCO SPECIFIC)
   ========================================================================== */

/**
 * Format Money (KES)
 * Usage: format_money(1500.50) -> "1,500.50"
 */
function format_money($amount, $decimals = 2)
{
    return number_format((float)$amount, $decimals, '.', ',');
}

/**
 * Format Date
 */
function format_date($date, $format = 'd M Y')
{
    return date($format, strtotime($date));
}

/* ==========================================================================
   DEVELOPER TOOLS
   ========================================================================== */

/**
 * Dump and Die (Debugging helper)
 */
function dd($data)
{
    echo '<pre style="background:#111; color:#bada55; padding:20px; z-index:9999; position:relative;">';
    var_dump($data);
    echo '</pre>';
    die();
}
?>