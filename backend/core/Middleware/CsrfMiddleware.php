<?php
declare(strict_types=1);

namespace USMS\Middleware;

use USMS\Http\ErrorHandler;

/**
 * USMS\Middleware\CsrfMiddleware
 * Handles CSRF token generation and validation.
 */
class CsrfMiddleware {

    /**
     * Start the session and ensure a CSRF token exists.
     */
    public static function boot(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Validate CSRF token for state-changing requests (POST, PUT, DELETE, PATCH).
     */
    public static function validate(): void {
        self::boot();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

            if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
                ErrorHandler::abort(403, "CSRF validation failed: Invalid or missing security token.");
            }
        }
    }

    /**
     * Get the current CSRF token.
     */
    public static function getToken(): string {
        self::boot();
        return $_SESSION['csrf_token'];
    }

    /**
     * Get the current CSRF token (alias for getToken).
     */
    public static function token(): string {
        return self::getToken();
    }

    /**
     * Check if the CSRF token is valid (returns boolean, doesn't abort).
     */
    public static function check(): bool {
        self::boot();
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generate an HTML hidden input field with the CSRF token.
     */
    public static function field(): string {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Generate a meta tag for the CSRF token.
     */
    public static function metaTag(): string {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
}
