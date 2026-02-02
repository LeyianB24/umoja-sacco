<?php
/**
 * inc/TopbarHelper.php
 * Logic for Topbar Data (Messages, Notifications)
 */

require_once __DIR__ . '/../config/db_connect.php';

class TopbarHelper {
    public static function getData($user_id, $role) {
        global $conn;
        
        $data = [
            'recent_messages' => [],
            'recent_notifs' => [],
            'unread_msgs_count' => 0,
            'unread_notif_count' => 0,
            'profile_pic' => null,
            'gender' => 'male',
            'full_name' => 'User'
        ];

        if ($user_id > 0 && isset($conn)) {
            // PROFILE
            $stmt = ($role === 'member')
                ? $conn->prepare("SELECT full_name, profile_pic, gender FROM members WHERE member_id=?")
                : $conn->prepare("SELECT full_name, NULL AS profile_pic, 'male' AS gender FROM admins WHERE admin_id=?");

            if($stmt){
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $profile_res = $stmt->get_result();
                if($u = $profile_res->fetch_assoc()){
                    $data['full_name'] = $u['full_name'];
                    $data['profile_pic'] = $u['profile_pic']; // Blob
                    $data['gender'] = $u['gender'];
                }
                $stmt->close();
            }

            // MESSAGES
            $msg_sql = ($role === 'member')
                ? "SELECT m.message_id, m.body, m.subject, m.sent_at, m.is_read,
                           COALESCE(a.full_name, mem.full_name, 'System') AS sender_name,
                           m.from_admin_id, m.from_member_id
                   FROM messages m
                   LEFT JOIN admins a ON m.from_admin_id=a.admin_id
                   LEFT JOIN members mem ON m.from_member_id=mem.member_id
                   WHERE m.to_member_id=?
                   ORDER BY m.sent_at DESC LIMIT 5"
                : "SELECT m.message_id, m.body, m.subject, m.sent_at, m.is_read,
                           mem.full_name AS sender_name,
                           m.from_member_id
                   FROM messages m
                   JOIN members mem ON m.from_member_id=mem.member_id
                   WHERE m.to_admin_id=?
                   ORDER BY m.sent_at DESC LIMIT 5";

            if($stmt = $conn->prepare($msg_sql)){
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $msg_res = $stmt->get_result();
                while($row = $msg_res->fetch_assoc()) $data['recent_messages'][] = $row;
                $stmt->close();
            }

            // UNREAD MSG COUNT
            $cnt_sql = ($role === 'member')
                ? "SELECT COUNT(*) AS cnt FROM messages WHERE to_member_id=? AND is_read=0"
                : "SELECT COUNT(*) AS cnt FROM messages WHERE to_admin_id=? AND is_read=0";
            if($stmt = $conn->prepare($cnt_sql)){
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $data['unread_msgs_count'] = $stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();
            }

            // NOTIFICATIONS
            if($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0){
                $sql = "SELECT * FROM notifications WHERE user_type=? AND user_id=? ORDER BY created_at DESC LIMIT 5";
                if($stmt = $conn->prepare($sql)){
                    $stmt->bind_param("si", $role, $user_id);
                    $stmt->execute();
                    $notif_res = $stmt->get_result();
                    while($n = $notif_res->fetch_assoc()) $data['recent_notifs'][] = $n;
                    $stmt->close();
                }

                $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_type=? AND user_id=? AND status='unread'");
                $stmt->bind_param("si", $role, $user_id);
                $stmt->execute();
                $data['unread_notif_count'] = $stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();
            }
        }
        
        return $data;
    }
    
    public static function timeElapsed($datetime) {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->i < 1 && $diff->h < 1 && $diff->d < 1) return "just now";
        foreach (['y'=>'yr','m'=>'mo','d'=>'day','h'=>'hr','i'=>'min','s'=>'sec'] as $k=>$v){
            if ($diff->$k > 0) return $diff->$k . " {$v}" . ($diff->$k>1?'s':'') . " ago";
        }
        return "just now";
    }
}
?>
