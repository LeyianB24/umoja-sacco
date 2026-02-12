<?php
// inc/topbar.php
// HOPE UI Topbar - Matches Sidebar Design
// Logic: 100% Preserved

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Auth.php';

/* -----------------------------------------------------------
   1. USER SESSION VARIABLES (UNTOUCHED)
----------------------------------------------------------- */
$my_user_id  = $_SESSION['member_id'] ?? $_SESSION['admin_id'] ?? 0;
$user_role   = $_SESSION['role']      ?? 'member';
$member_name = $_SESSION['member_name'] ?? $_SESSION['full_name'] ?? 'User';
$profile_pic_db = null;
$user_gender = 'male';

/* -----------------------------------------------------------
   2. COUNTS + ARRAYS (UNTOUCHED)
----------------------------------------------------------- */
$recent_messages    = [];
$recent_notifs      = [];
$unread_msgs_count  = 0;
$unread_notif_count = 0;

/* -----------------------------------------------------------
   3. TIME AGO FUNCTION (UNTOUCHED)
----------------------------------------------------------- */
if (!function_exists('time_elapsed_string_topbar')) {
    function time_elapsed_string_topbar($datetime) {
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

/* -----------------------------------------------------------
   4. DATABASE QUERIES (UNTOUCHED)
----------------------------------------------------------- */
if ($my_user_id > 0 && isset($conn)) {

    // PROFILE
    $stmt = ($user_role==='member')
        ? $conn->prepare("SELECT full_name, profile_pic, gender FROM members WHERE member_id=?")
        : $conn->prepare("SELECT full_name, NULL AS profile_pic, 'male' AS gender FROM admins WHERE admin_id=?");

    if($stmt){
        $stmt->bind_param("i",$my_user_id);
        $stmt->execute();
        $res=$stmt->get_result();
        if($u=$res->fetch_assoc()){
            $member_name=$u['full_name'];
            $profile_pic_db=$u['profile_pic'];
            $user_gender=$u['gender'];
        }
        $stmt->close();
    }

    // MESSAGES
    $msg_sql = ($user_role==='member')
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

    if($stmt=$conn->prepare($msg_sql)){
        $stmt->bind_param("i",$my_user_id);
        $stmt->execute();
        $res=$stmt->get_result();
        while($row=$res->fetch_assoc()) $recent_messages[]=$row;
        $stmt->close();
    }

    // UNREAD MSG COUNT
    $cnt_sql = ($user_role==='member')
        ? "SELECT COUNT(*) AS cnt FROM messages WHERE to_member_id=? AND is_read=0"
        : "SELECT COUNT(*) AS cnt FROM messages WHERE to_admin_id=? AND is_read=0";
    if($stmt=$conn->prepare($cnt_sql)){
        $stmt->bind_param("i",$my_user_id);
        $stmt->execute();
        $unread_msgs_count=$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    }

    // NOTIFICATIONS
    if($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows>0){
        $sql="SELECT * FROM notifications WHERE user_type=? AND user_id=? ORDER BY created_at DESC LIMIT 5";
        if($stmt=$conn->prepare($sql)){
            $stmt->bind_param("si",$user_role,$my_user_id);
            $stmt->execute();
            $res=$stmt->get_result();
            while($n=$res->fetch_assoc()) $recent_notifs[]=$n;
            $stmt->close();
        }

        $stmt=$conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_type=? AND user_id=? AND status='unread'");
        $stmt->bind_param("si",$user_role,$my_user_id);
        $stmt->execute();
        $unread_notif_count=$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    }
}

/* -----------------------------------------------------------
   5. PROFILE IMAGE LOGIC (UNTOUCHED)
----------------------------------------------------------- */
$base = defined("BASE_URL") ? BASE_URL : '..';
$default_avatar = strtolower($user_gender ?? 'male')==='female'?'female.jpg':'male.jpg';
$pic_src = !empty($profile_pic_db)
    ? "data:image/jpeg;base64,".base64_encode($profile_pic_db)
    : "{$base}/public/assets/uploads/{$default_avatar}";
$msgs_link   = "{$base}/public/messages.php";
$notif_link  = ($user_role==='member') ? "{$base}/member/pages/notifications.php" : "#";

// Unified Profile Link Logic
$profile_link = ($user_role === 'member') ? "{$base}/member/pages/profile.php" : "{$base}/public/admin_settings.php";
?>

<style>
/* --- HOPE UI TOPBAR VARIABLES --- */
:root {
    --nav-height: 80px;
    --font-family: 'Plus Jakarta Sans', sans-serif;
    
    /* Colors (Matching Sidebar) */
    --nav-bg: #FFFFFF;
    --nav-border: #F3F4F6;
    --nav-text: #6B7280;
    --nav-text-dark: #111827;
    
    /* Accents */
    --accent-green: #0F392B;  /* Deep Forest */
    --accent-lime: #D0F764;   /* Electric Lime */
    
    /* Interactive Elements */
    --btn-bg: #FFFFFF;
    --btn-border: #E5E7EB;
    --btn-hover-bg: #0F392B;
    --btn-hover-text: #D0F764;
}

[data-bs-theme="dark"] {
    --nav-bg: #0b1210;
    --nav-border: rgba(255,255,255,0.05);
    --nav-text: #9CA3AF;
    --nav-text-dark: #F3F4F6;
    --btn-bg: rgba(255,255,255,0.03);
    --btn-border: transparent;
}

/* --- LAYOUT --- */
.top-navbar {
    height: var(--nav-height);
    background: var(--nav-bg);
    border-bottom: 1px solid var(--nav-border);
    position: sticky;
    top: 0;
    z-index: 1020;
    font-family: var(--font-family);
    transition: background 0.3s ease;
}

/* --- INTERACTIVE ICONS --- */
.btn-icon-nav {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    color: var(--nav-text);
    background: var(--btn-bg);
    border: 1px solid var(--btn-border);
    transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
}
.btn-icon-nav:hover, .btn-icon-nav[aria-expanded="true"] {
    background: var(--btn-hover-bg);
    color: var(--btn-hover-text);
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 57, 43, 0.15);
}
.btn-icon-nav i { font-size: 1.2rem; }

/* Badge Dots */
.badge-dot {
    position: absolute;
    top: 10px; right: 10px;
    width: 8px; height: 8px;
    background: #EF4444; /* Standard Red for alerts */
    border: 2px solid var(--btn-bg);
    border-radius: 50%;
}
.btn-icon-nav:hover .badge-dot {
    background: var(--accent-lime); /* Turns lime on hover */
    border-color: var(--accent-green);
}

/* --- USER PROFILE PILL --- */
.user-info-pill {
    padding: 6px 6px 6px 16px;
    background: var(--btn-bg);
    border: 1px solid var(--btn-border);
    border-radius: 50px;
    transition: all 0.2s;
    cursor: pointer;
}
.user-info-pill:hover {
    border-color: var(--accent-green);
}

.profile-avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

/* Role Badge */
.role-badge {
    background: var(--accent-green);
    color: var(--accent-lime);
    font-size: 0.6rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}

/* --- DROPDOWNS --- */
.custom-dropdown {
    border: 1px solid var(--nav-border);
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    background: var(--nav-bg);
    border-radius: 16px;
    padding: 10px;
    margin-top: 10px !important;
    animation: fadeUp 0.2s ease-out forwards;
}
.dropdown-header-custom {
    padding: 8px 12px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--nav-text);
    opacity: 0.7;
    letter-spacing: 1px;
}
.list-item-custom {
    display: block;
    padding: 10px 14px;
    border-radius: 10px;
    text-decoration: none;
    color: var(--nav-text-dark);
    transition: all 0.2s;
    position: relative;
    margin-bottom: 2px;
}
.list-item-custom:hover {
    background: #F9FAFB;
    color: var(--accent-green);
}
.list-item-custom.unread {
    background: rgba(208, 247, 100, 0.1); /* Lime tint */
}
.list-item-custom.unread::before {
    content: '';
    position: absolute; left: 6px; top: 50%; transform: translateY(-50%);
    width: 4px; height: 4px;
    background: var(--accent-green);
    border-radius: 50%;
}
[data-bs-theme="dark"] .list-item-custom:hover { background: rgba(255,255,255,0.05); }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="top-navbar d-flex align-items-center justify-content-between px-3 px-lg-4">
    
    
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light d-lg-none" id="mobileToggle">
    <i class="bi bi-list"></i>
</button>
        <button id="mobileSidebarToggle" class="btn-icon-nav d-lg-none border-0 shadow-none bg-transparent">
            <i class="bi bi-list-nested"></i>
        </button>

        <div>
            <h5 class="fw-bold mb-0 text-dark" style="color: var(--nav-text-dark) !important; letter-spacing: -0.5px;">
                <?= $pageTitle ?? 'Dashboard' ?>
            </h5>
            <div class="d-none d-md-flex align-items-center gap-2">
                <span style="width:6px; height:6px; background:var(--accent-lime); border-radius:50%;"></span>
                <small style="color: var(--nav-text); font-size: 0.8rem;">
                    Welcome back, <?= htmlspecialchars(explode(' ', $member_name)[0]) ?>
                </small>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 gap-md-3">
        
        <button id="themeToggle" class="btn-icon-nav" title="Switch Theme">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </button>

       <style>
    /* Hope UI Dropdown Styles */
    .btn-icon-nav {
        background: transparent; border: none; position: relative;
        font-size: 1.3rem; color: var(--iq-text-body); transition: 0.3s;
        width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;
    }
    .btn-icon-nav:hover, .btn-icon-nav[aria-expanded="true"] {
        background-color: rgba(13, 131, 75, 0.1); color: var(--iq-secondary);
    }
    .badge-dot {
        position: absolute; top: 10px; right: 10px;
        width: 8px; height: 8px; background-color: #ff5b5b;
        border-radius: 50%; border: 1px solid #fff;
    }
    .custom-dropdown {
        border: none; border-radius: 1rem;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        padding: 0; overflow: hidden; animation-duration: 0.3s;
    }
    .dropdown-header-custom {
        padding: 1rem 1.25rem; background: var(--iq-body-bg);
        border-bottom: 1px solid #eee; font-weight: 700; color: var(--iq-text-title);
    }
    
    /* List Items */
    .list-item-custom {
        display: block; padding: 0.75rem 1.25rem;
        border-bottom: 1px solid #f5f5f5; text-decoration: none;
        color: var(--iq-text-title); transition: all 0.2s; position: relative;
    }
    .list-item-custom:hover { background-color: #f9f9f9; color: var(--iq-secondary); }
    .list-item-custom.unread { background-color: rgba(13, 131, 75, 0.03); }
    .list-item-custom.unread::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0;
        width: 3px; background-color: var(--iq-secondary);
    }
    
    /* Avatars & Icons */
    .msg-avatar { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }
    .notif-icon-box {
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: rgba(13, 131, 75, 0.1); color: var(--iq-secondary); font-size: 1.1rem;
    }
</style>

<div class="dropdown">
    <button class="btn-icon-nav" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-chat-dots"></i>
        <?php if($unread_msgs_count > 0): ?><span class="badge-dot animate__animated animate__pulse animate__infinite"></span><?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end custom-dropdown animate__animated animate__fadeInUp" style="width: 360px;">
        <div class="dropdown-header-custom d-flex justify-content-between align-items-center">
            <span>Messages <small class="text-muted fw-normal ms-1">(<?= $unread_msgs_count ?> new)</small></span>
            <a href="<?= $msgs_link ?>" class="text-decoration-none fw-bold" style="font-size:0.75rem; color: var(--iq-secondary)">VIEW ALL</a>
        </div>
        
        <div style="max-height: 350px; overflow-y: auto;">
        <?php if(!empty($recent_messages)): foreach($recent_messages as $msg):
            $chat_id = $user_role==='member'?($msg['from_admin_id']?:$msg['from_member_id']):$msg['from_member_id'];
            // Generate a placeholder avatar or use real one if available
            $initial = strtoupper(substr($msg['sender_name'] ?? 'U', 0, 1));
            $avatar_html = "<div class='msg-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold'>{$initial}</div>";
        ?>
            <a href="javascript:void(0);" 
               onclick="markReadAndRedirect('message', <?= $msg['message_id'] ?>, '<?= $msgs_link ?>?chat_with=<?= $chat_id ?>')" 
               class="list-item-custom <?= $msg['is_read']==0?'unread':'' ?>">
                <div class="d-flex align-items-center gap-3">
                    <?= $avatar_html ?>
                    <div class="grow overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-truncate text-dark" style="font-size:0.9rem; max-width: 160px;"><?= htmlspecialchars($msg['sender_name'] ?? 'User') ?></strong>
                            <span class="small text-muted" style="font-size: 0.7rem;"><?= time_elapsed_string_topbar($msg['sent_at']) ?></span>
                        </div>
                        <div class="text-truncate small text-secondary"><?= htmlspecialchars($msg['body'] ?: 'Sent an attachment') ?></div>
                    </div>
                </div>
            </a>
        <?php endforeach; else: ?>
            <div class="py-5 text-center text-muted">
                <i class="bi bi-chat-square-dots display-6 opacity-25"></i>
                <p class="small mt-2 mb-0">No recent messages</p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<div class="dropdown ms-2">
    <button class="btn-icon-nav" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell"></i>
        <?php if($unread_notif_count > 0): ?><span class="badge-dot animate__animated animate__swing animate__infinite"></span><?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end custom-dropdown animate__animated animate__fadeInUp" style="width: 360px;">
        <div class="dropdown-header-custom d-flex justify-content-between align-items-center">
            <span>Notifications</span>
            <a href="<?= $notif_link ?>" class="text-decoration-none fw-bold" style="font-size:0.75rem; color: var(--iq-secondary)">MARK ALL READ</a>
        </div>
        
        <div style="max-height: 350px; overflow-y: auto;">
       <?php if(!empty($recent_notifs)): foreach($recent_notifs as $n): 
            // 1. Determine Icon
            $icon = 'bi-bell';
            if(stripos($n['message'], 'payment')!==false) $icon = 'bi-credit-card';
            if(stripos($n['message'], 'approved')!==false) $icon = 'bi-check-circle';
            if(stripos($n['message'], 'reject')!==false || stripos($n['message'], 'alert')!==false) $icon = 'bi-exclamation-circle';
            
            // 2. Safe Variables for HTML
            $dest_link = $notif_link; 
            $notif_id = $n['notification_id'];
            $status_class = ($n['status'] === 'unread') ? 'unread' : '';
        ?>
            <a href="javascript:void(0);" 
               onclick="markReadAndRedirect('notification', <?php echo $notif_id; ?>, '<?php echo $dest_link; ?>')"
               class="list-item-custom <?php echo $status_class; ?>">
                
                <div class="d-flex gap-3">
                    <div class="notif-icon-box shrink-0">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <div class="grow">
                        <p class="mb-1 small fw-medium text-dark lh-sm"><?= htmlspecialchars($n['message']) ?></p>
                        <div class="small opacity-50 d-flex align-items-center gap-1">
                            <i class="bi bi-clock" style="font-size:0.6rem"></i> <?= time_elapsed_string_topbar($n['created_at']) ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; else: ?>
            <div class="py-5 text-center text-muted">
                <i class="bi bi-bell-slash display-6 opacity-25"></i>
                <p class="small mt-2 mb-0">No notifications</p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
        <div class="d-none d-md-block" style="width: 1px; height: 30px; background: var(--nav-border);"></div>

        <div class="dropdown">
            <div class="user-info-pill d-flex align-items-center gap-3" data-bs-toggle="dropdown">
                <div class="d-none d-md-block text-end lh-1">
                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--nav-text-dark);"><?= htmlspecialchars($member_name) ?></div>
                    <span class="role-badge"><?= strtoupper($user_role) ?></span>
                </div>
                <img src="<?= $pic_src ?>" alt="User" class="profile-avatar">
            </div>

            <ul class="dropdown-menu dropdown-menu-end custom-dropdown" style="min-width: 200px;">
                <li class="px-3 py-2 border-bottom border-light mb-2">
                    <span class="small text-muted fw-bold">MY ACCOUNT</span>
                </li>
                <li><a class="dropdown-item list-item-custom small" href="<?= $profile_link ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                <?php if($user_role === 'member'): ?>
                    <li><a class="dropdown-item list-item-custom small" href="<?= $base ?>/member/pages/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <?php else: ?>
                    <li><a class="dropdown-item list-item-custom small" href="<?= $base ?>/public/admin_settings.php"><i class="bi bi-gear me-2"></i>Config</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider opacity-10"></li>
                <li><a class="dropdown-item list-item-custom small text-danger" href="<?= $base ?>/public/logout.php"><i class="bi bi-power me-2"></i>Sign Out</a></li>
            </ul>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Theme Logic
    const html = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');
    const saved = localStorage.getItem('theme');

    const updateIcon = (theme) => {
        if(theme === 'dark'){
            if(themeIcon) themeIcon.classList.replace('bi-moon-stars', 'bi-sun');
        } else {
            if(themeIcon) themeIcon.classList.replace('bi-sun', 'bi-moon-stars');
        }
    };

    if (saved) {
        html.setAttribute('data-bs-theme', saved);
        updateIcon(saved);
    }

    const themeToggle = document.getElementById('themeToggle');
    if(themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = html.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            updateIcon(next);
        });
    }

    // 2. Mobile Sidebar Toggle
    // Triggers the class logic defined in your sidebar.php
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if(mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && 
                sidebar.classList.contains('show') && 
                !sidebar.contains(e.target) && 
                !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }
});
</script>
<script>
function markReadAndRedirect(type, id, redirectUrl) {
    // 1. Create FormData
    const formData = new FormData();
    formData.append('type', type);
    formData.append('id', id);

    // 2. Send Background Request (AJAX)
    // We use fetch with 'keepalive: true' to ensure the request finishes 
    // even if the page redirects immediately.
    fetch('<?= BASE_URL ?>/public/ajax_mark_read.php', {
        method: 'POST',
        body: formData,
        keepalive: true 
    }).then(response => {
        // Optional: Log success
        console.log('Marked as read');
    }).catch(error => {
        console.error('Error marking as read:', error);
    });

    // 3. Redirect immediately (don't wait for the fetch to finish)
    window.location.href = redirectUrl;
}
</script>
