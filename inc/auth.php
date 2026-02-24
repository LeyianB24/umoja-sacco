<?php
declare(strict_types=1);
/**
 * inc/auth.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Middleware\Auth class and loads helper functions.
 */
require_once __DIR__ . '/../config/app.php';

// ALWAYS load the file to ensure functions like require_admin() are defined
require_once __DIR__ . '/../core/Middleware/Auth.php';

if (!class_exists('Auth')) {
    class_alias(\USMS\Middleware\Auth::class, 'Auth');
}

/**
 * Procedural Helpers in Global Namespace
 */
if (!function_exists('can')) {
    function can($slug) { return \USMS\Middleware\Auth::can($slug); }
}

if (!function_exists('has_permission')) {
    function has_permission($slug) { return \USMS\Middleware\Auth::can($slug); }
}

if (!function_exists('require_admin')) {
    function require_admin() { \USMS\Middleware\Auth::requireAdmin(); }
}

if (!function_exists('require_permission')) {
    function require_permission($slug = null) { \USMS\Middleware\Auth::requirePermission($slug); }
}

if (!function_exists('require_superadmin')) {
    function require_superadmin() { \USMS\Middleware\Auth::requireSuperAdmin(); }
}

if (!function_exists('require_member')) {
    function require_member() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['member_id'])) {
            \USMS\Http\ErrorHandler::abort(401, "Access Restricted: Member login required.");
        }
    }
}
