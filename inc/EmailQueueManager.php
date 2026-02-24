<?php
/**
 * inc/EmailQueueManager.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\EmailQueueService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('EmailQueueManager')) {
    class_alias(\USMS\Services\EmailQueueService::class, 'EmailQueueManager');
}
