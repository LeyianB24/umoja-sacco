<?php
/**
 * inc/Mailer.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\EmailService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('Mailer')) {
    class_alias(\USMS\Services\EmailService::class, 'Mailer');
}
