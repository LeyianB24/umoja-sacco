<?php
declare(strict_types=1);

namespace USMS\Services;

use USMS\Database\Database;
use PDO;

/**
 * USMS\Services\MessageService
 * Handles user messaging and read status.
 */
class MessageService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Mark a message as read
     */
    public function markRead(int $msg_id, int $user_id): bool {
        $stmt = $this->db->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND (to_member_id = ? OR to_admin_id = ?)");
        return $stmt->execute([$msg_id, $user_id, $user_id]);
    }

    /**
     * Static gateway for convenience
     */
    public static function quickMarkRead(int $msg_id, int $user_id): bool {
        return (new self())->markRead($msg_id, $user_id);
    }
}
