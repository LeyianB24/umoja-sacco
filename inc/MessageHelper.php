<?php
// inc/MessageHelper.php

class MessageHelper {
    public static function markRead($conn, $msg_id, $user_id, $role = 'member') {
        // Validation: Ensure the message belongs to the user
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND (to_member_id = ? OR to_admin_id = ?)");
        $stmt->bind_param("iii", $msg_id, $user_id, $user_id);
        return $stmt->execute();
    }
}
?>
