<?php
declare(strict_types=1);
/**
 * inc/LayoutManager.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Http\LayoutManager class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('LayoutManager')) {
    class_alias(\USMS\Http\LayoutManager::class, 'LayoutManager');
}

