<?php
/**
 * inc/MessageHelper.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\MessageService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('MessageHelper')) {
    class_alias(\USMS\Services\MessageService::class, 'MessageHelper');
}
