<?php
declare(strict_types=1);
/**
 * core/Middleware/CsrfMiddleware.php
 * USMS\Middleware\CsrfMiddleware — CSRF token generation and validation.
 *
 * Usage:
 *   // In any form view — embed the token:
 *   echo CsrfMiddleware::field();   // hidden input
 *   echo CsrfMiddleware::token();   // raw token string
 *
 *   // On form submission — validate before processing:
 *   CsrfMiddleware::verify();       // throws RuntimeException on failure
 *
 *   // OR in API handlers (returns bool):
 *   if (!CsrfMiddleware::check()) { ... }
 */

namespace USMS\Middleware;

use RuntimeException;

class CsrfMiddleware
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME  = 'csrf_token';
    private const TOKEN_BYTES = 32;

    // ── Token Generation ──────────────────────────────────────────────────────

    /**
     * Get (or create) the CSRF token for the current session.
     */
    public static function token(): string
    {
        self::ensureSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_BYTES));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Render a hidden HTML input field containing the CSRF token.
     */
    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::FIELD_NAME, ENT_QUOTES),
            htmlspecialchars(self::token(), ENT_QUOTES)
        );
    }

    // ── Verification ──────────────────────────────────────────────────────────

    /**
     * Check whether the submitted token matches the session token.
     * Constant-time comparison guards against timing attacks.
     */
    public static function check(): bool
    {
        self::ensureSession();

        $submitted = $_POST[self::FIELD_NAME]
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']   // for AJAX calls with header
            ?? '';

        $expected = $_SESSION[self::SESSION_KEY] ?? '';

        if (empty($expected) || empty($submitted)) {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    /**
     * Validate the CSRF token and halt execution if invalid.
     * Use this for state-changing form handlers and API endpoints.
     *
     * @throws RuntimeException when token is missing or invalid
     */
    public static function verify(): void
    {
        if (!self::check()) {
            self::logFailure();

            // Respond appropriately based on context
            if (self::isAjax()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed.']);
                exit;
            }

            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>CSRF token validation failed. Please go back and try again.</p>');
        }
    }

    /**
     * Rotate the token. Call this after successful login / high-value actions.
     */
    public static function regenerate(): void
    {
        self::ensureSession();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    // ── AJAX Helpers ──────────────────────────────────────────────────────────

    /**
     * Render a <meta> tag for JavaScript-based AJAX CSRF usage.
     *
     * In JS:  headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }
     */
    public static function metaTag(): string
    {
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES)
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private static function ensureSession(): void
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
            isset($_SERVER['HTTP_ACCEPT']) &&
            str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
        );
    }

    private static function logFailure(): void
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $uid = $_SESSION['admin_id'] ?? $_SESSION['member_id'] ?? 'guest';
        error_log("[CSRF] Token mismatch — user={$uid}, ip={$ip}, uri={$uri}");
    }
}
