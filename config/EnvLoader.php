<?php
/**
 * config/EnvLoader.php
 * Loads environment variables from .env.local file
 * Provides secure access to sensitive configuration
 */

namespace USMS\Config;

class EnvLoader
{
    private static array $env = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from .env.local file and system environment
     * Priority: System env vars > $_ENV > .env.local > defaults
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // First, try to load from .env.local (for local development)
        $env_file = dirname(__DIR__) . '/.env.local';

        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                // Parse KEY=VALUE
                if (strpos($line, '=') === false) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                self::$env[$key] = $value;
                // Also set in $_ENV and putenv for backward compatibility
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        } else {
            // On production/cloud (Railway, etc), .env.local won't exist
            // System environment variables are already available
            error_log(".env.local file not found (OK for cloud deployments). Using system environment variables.");
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default value
     * Priority: getenv() (system) > $_ENV > self::$env (.env.local) > default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();

        // Priority: System environment variables (used by Railway, Docker, etc)
        $system_value = getenv($key);
        if ($system_value !== false) {
            return $system_value;
        }

        // Fallback to $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Fallback to .env.local
        if (isset(self::$env[$key])) {
            return self::$env[$key];
        }

        // Return default
        return $default;
    }

    /**
     * Get environment variable as boolean
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Get environment variable as integer
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return (int) $value;
    }

    /**
     * Check if an environment variable is set
     */
    public static function has(string $key): bool
    {
        self::load();
        return isset(self::$env[$key]) || isset($_ENV[$key]);
    }

    /**
     * Check if all required environment variables are set
     * @param array $required List of required env var keys
     * @throws \RuntimeException If any required var is missing
     */
    public static function requireAll(array $required): void
    {
        self::load();

        $missing = [];
        foreach ($required as $key) {
            if (!self::has($key)) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                "Missing required environment variables: " . implode(', ', $missing) .
                ". Check your .env.local file."
            );
        }
    }

    /**
     * Configure secure session settings
     * Call this early in application bootstrap
     */
    public static function configureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return; // Already configured
        }

        $is_production = self::get('APP_ENV') === 'production';
        $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        // Session security settings
        session_set_cookie_params([
            'lifetime' => 3600,           // 1 hour
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'secure' => $is_production && $is_https,  // HTTPS only in production
            'httponly' => true,           // No JavaScript access
            'samesite' => 'Strict',       // CSRF protection
        ]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Enforce HTTPS in production
     */
    public static function enforceHttps(): void
    {
        if (self::get('APP_ENV') !== 'production') {
            return;
        }

        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
            exit;
        }

        // Set HSTS header - tells browsers to always use HTTPS
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', true);
    }

    /**
     * Add security headers
     */
    public static function addSecurityHeaders(): void
    {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff', true);

        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block', true);

        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN', true);

        // Content Security Policy
        // Content Security Policy: Relaxed to allow all HTTPS sources (CDN, Fonts, etc)
        header("Content-Security-Policy: default-src 'self' https:; script-src 'self' https: 'unsafe-inline' 'unsafe-eval'; style-src 'self' https: 'unsafe-inline'; font-src 'self' https: data:; img-src 'self' https: data:;", true);



        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin', true);
    }
}

