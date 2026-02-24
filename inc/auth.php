<?php
declare(strict_types=1);
/**
 * inc/auth.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Middleware\Auth class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('Auth')) {
    class_alias(\USMS\Middleware\Auth::class, 'Auth');
}

