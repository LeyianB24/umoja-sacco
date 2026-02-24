<?php
/**
 * inc/Mailer.php (LEGACY STUB)
 * Redirects to the namespaced USMS\Services\EmailService class.
 */
require_once __DIR__ . '/../config/app.php';

if (!class_exists('Mailer')) {
    class Mailer {
        /**
         * Proxy to EmailService instance for legacy static calls
         */
        public static function send(string $to, string $subject, string $body, array $attachments = [], ?string $altBody = null): bool {
            $service = new \USMS\Services\EmailService();
            return $service->send($to, $subject, $body, $attachments, $altBody);
        }

        /**
         * Static gateway for quick sending
         */
        public static function quickSend(string $to, string $subject, string $body): bool {
            return \USMS\Services\EmailService::quickSend($to, $subject, $body);
        }
    }
}
