<?php
declare(strict_types=1);
/**
 * core/Middleware/AuthMiddleware.php
 * USMS\Middleware\AuthMiddleware — Formal middleware-pattern auth guards.
 *
 * This wraps the existing Auth class logic from inc/auth.php in a
 * composable, PSR-style middleware that can be stacked and tested.
 *
 * Usage:
 *   // At the top of any admin page or API handler:
 *   AuthMiddleware::admin();                   // must be logged in as admin
 *   AuthMiddleware::permission('manage_loans'); // must have slug permission
 *   AuthMiddleware::superAdmin();              // role_id = 1 only
 *   AuthMiddleware::member();                  // member session
 */

namespace USMS\Middleware;

use RuntimeException;

class AuthMiddleware
{
    // ── Admin Guards ──────────────────────────────────────────────────────────

    /**
     * Require an active admin session.
     * Redirects to login on failure.
     */
    public static function admin(): void
    {
        self::startSession();

        if (!isset($_SESSION['admin_id'])) {
            self::failAdmin('unauthorized');
        }
    }

    /**
     * Require a specific RBAC permission slug.
     * Always implies admin() guard first.
     */
    public static function permission(string $slug): void
    {
        self::admin();

        if (!self::can($slug)) {
            self::log("Permission denied: [{$slug}] for admin=" . ($_SESSION['admin_id'] ?? '?'));

            if (self::isAjax()) {
                self::jsonForbidden("You do not have the '{$slug}' permission.");
            }

            $base = defined('BASE_URL') ? BASE_URL : '';
            header("Location: {$base}/admin/pages/dashboard.php?error=no_permission&perm=" . urlencode($slug));
            exit;
        }
    }

    /**
     * Require Superadmin (role_id = 1).
     */
    public static function superAdmin(): void
    {
        self::admin();

        if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
            self::log("Superadmin access denied for admin=" . ($_SESSION['admin_id'] ?? '?'));
            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>Restricted Area: Superadmin Access Only.</p>');
        }
    }

    // ── Member Guards ─────────────────────────────────────────────────────────

    /**
     * Require an active member session.
     */
    public static function member(): void
    {
        self::startSession();

        if (!isset($_SESSION['member_id'])) {
            $base = defined('BASE_URL') ? BASE_URL : '';
            header("Location: {$base}/public/login.php?error=member_only");
            exit;
        }
    }

    // ── RBAC Check (no redirect) ──────────────────────────────────────────────

    /**
     * Return true/false whether the current admin has a permission slug.
     * Matches the can() / has_permission() helpers in inc/auth.php.
     */
    public static function can(string $slug): bool
    {
        $permissions = $_SESSION['permissions'] ?? [];

        // Superadmin always passes
        if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) {
            return true;
        }

        return in_array($slug, $permissions, true);
    }

    // ── Rate Limiting ─────────────────────────────────────────────────────────

    /**
     * Simple session-based rate limiter.
     * Useful for login forms, OTP endpoints, etc.
     *
     * @param string   $key      Unique action identifier (e.g. 'login_attempt')
     * @param int      $maxHits  Max attempts allowed in the window
     * @param int      $windowSec Time window in seconds
     * @throws RuntimeException when rate limit is exceeded
     */
    public static function rateLimit(string $key, int $maxHits = 5, int $windowSec = 60): void
    {
        self::startSession();

        $sessionKey = "_rl_{$key}";
        $now        = time();

        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = ['count' => 0, 'reset_at' => $now + $windowSec];
        }

        // Reset window if expired
        if ($now >= $_SESSION[$sessionKey]['reset_at']) {
            $_SESSION[$sessionKey] = ['count' => 0, 'reset_at' => $now + $windowSec];
        }

        $_SESSION[$sessionKey]['count']++;

        if ($_SESSION[$sessionKey]['count'] > $maxHits) {
            $retryAfter = $_SESSION[$sessionKey]['reset_at'] - $now;
            self::log("Rate limit exceeded: key={$key}, ip=" . ($_SERVER['REMOTE_ADDR'] ?? '?'));

            if (self::isAjax()) {
                http_response_code(429);
                header("Retry-After: {$retryAfter}");
                header('Content-Type: application/json');
                echo json_encode([
                    'status'      => 'error',
                    'message'     => 'Too many requests. Please wait and try again.',
                    'retry_after' => $retryAfter,
                ]);
                exit;
            }

            http_response_code(429);
            die("<h1>429 Too Many Requests</h1><p>Please wait {$retryAfter} seconds before trying again.</p>");
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private static function isAjax(): bool
    {
        return (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            !empty($_SERVER['HTTP_ACCEPT']) &&
            str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
        );
    }

    private static function failAdmin(string $reason): never
    {
        if (self::isAjax()) {
            self::jsonForbidden('Authentication required.');
        }
        $base = defined('BASE_URL') ? BASE_URL : '';
        header("Location: {$base}/public/login.php?error=" . urlencode($reason));
        exit;
    }

    private static function jsonForbidden(string $message): never
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }

    private static function log(string $msg): void
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        error_log("[AuthMiddleware] {$msg} | ip={$ip} | uri={$uri}");
    }
}
