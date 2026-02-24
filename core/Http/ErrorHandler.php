<?php
declare(strict_types=1);

namespace USMS\Http;

/**
 * USMS\Http\ErrorHandler
 * Centralized Error & Exception Handler
 * Replaces ad-hoc die() calls with structured responses.
 */
class ErrorHandler {

    /**
     * Register global handlers for errors and exceptions.
     */
    public static function register(): void {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
    }

    /**
     * Convert PHP errors to ErrorException.
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle uncaught exceptions.
     */
    public static function handleException(\Throwable $e): void {
        error_log("[USMS FATAL] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        if (self::isAjaxRequest()) {
            self::jsonError($e->getMessage(), self::getHttpCode($e));
            return;
        }

        $isDev = defined('APP_ENV') && APP_ENV === 'development';
        http_response_code(self::getHttpCode($e));
        echo self::renderErrorPage(
            self::getHttpCode($e),
            $isDev ? $e->getMessage() : 'An unexpected error occurred. Please try again later.',
            $isDev ? $e->getTraceAsString() : null
        );
    }

    /**
     * Abort with HTTP status — replacement for die().
     */
    public static function abort(int $code, string $message = ''): never {
        $defaults = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
        ];

        $message = $message ?: ($defaults[$code] ?? 'Error');

        if (self::isAjaxRequest()) {
            self::jsonError($message, $code);
            exit;
        }

        http_response_code($code);
        echo self::renderErrorPage($code, $message);
        exit;
    }

    /**
     * JSON error response for API / AJAX endpoints.
     */
    public static function jsonError(string $message, int $code = 500): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Render a styled error page.
     */
    private static function renderErrorPage(int $code, string $message, ?string $trace = null): string {
        $escapedMsg   = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $traceBlock   = $trace ? '<pre style="text-align:left;font-size:12px;background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:8px;overflow-x:auto;margin-top:20px;">' . htmlspecialchars($trace) . '</pre>' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error {$code} — Umoja Sacco</title>
    <style>
        body{margin:0;padding:0;font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:#0F2E25;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;}
        .card{background:rgba(255,255,255,.06);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:48px;text-align:center;max-width:560px;width:90%;}
        h1{font-size:72px;margin:0;color:#D0F764;font-weight:800;}
        p{font-size:16px;color:rgba(255,255,255,.75);line-height:1.6;}
        a{display:inline-block;margin-top:24px;padding:12px 28px;background:#D0F764;color:#0F2E25;border-radius:8px;text-decoration:none;font-weight:600;transition:.2s;}
        a:hover{opacity:.85;transform:translateY(-1px);}
    </style>
</head>
<body>
    <div class="card">
        <h1>{$code}</h1>
        <p>{$escapedMsg}</p>
        {$traceBlock}
        <a href="javascript:history.back()">← Go Back</a>
    </div>
</body>
</html>
HTML;
    }

    private static function isAjaxRequest(): bool {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));
    }

    private static function getHttpCode(\Throwable $e): int {
        $code = $e->getCode();
        return (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
    }
}
