<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// Auth Check
require_permission();

// Initialize Layout Manager
$layout = LayoutManager::create('admin');

$admin_id  = (int) $_SESSION['admin_id'];
$user_role = $_SESSION['role'] ?? 'admin';

// Fetch Notifications
$query = "SELECT notification_id, title, message, is_read, created_at 
          FROM notifications 
          WHERE user_type = 'admin' 
          AND (user_id = ? OR to_role = ? OR to_role = 'all') 
          ORDER BY created_at DESC LIMIT 50";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $admin_id, $user_role);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

function getNotificationStyle($title, $msg) {
    $t = strtolower($title . ' ' . $msg);
    if (strpos($t, 'loan') !== false || strpos($t, 'credit') !== false || strpos($t, 'pay') !== false || strpos($t, 'disburs') !== false) {
        return ['icon' => 'bi-wallet2', 'type' => 'finance'];
    }
    if (strpos($t, 'approv') !== false || strpos($t, 'success') !== false || strpos($t, 'verify') !== false) {
        return ['icon' => 'bi-check-circle-fill', 'type' => 'success'];
    }
    if (strpos($t, 'reject') !== false || strpos($t, 'fail') !== false || strpos($t, 'error') !== false) {
        return ['icon' => 'bi-exclamation-circle-fill', 'type' => 'alert'];
    }
    return ['icon' => 'bi-bell-fill', 'type' => 'general'];
}

$pageTitle = "My Notifications";
?>
<?php $layout->header($pageTitle); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
/* ─── Base ─── */
*, body, .main-content-wrapper {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* ─── Hero Banner ─── */
.nf-hero {
    background: linear-gradient(135deg, #0F392B 0%, #1a5c43 55%, #0d2e22 100%);
    border-radius: 20px;
    padding: 32px 36px;
    position: relative;
    overflow: hidden;
    margin-bottom: 28px;
}
.nf-hero::before {
    content: '';
    position: absolute;
    top: -70px; right: -70px;
    width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(57,181,74,0.18) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.nf-hero::after {
    content: '';
    position: absolute;
    bottom: -50px; left: 20%;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(163,230,53,0.09) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.nf-hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.14);
    border-radius: 100px;
    padding: 5px 13px;
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.7);
    margin-bottom: 12px;
}
.nf-hero-eyebrow i { color: #A3E635; }
.nf-hero h1 {
    font-size: 2.1rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.4px;
    margin: 0 0 6px;
    line-height: 1.15;
}
.nf-hero-sub {
    font-size: 0.87rem;
    color: rgba(255,255,255,0.55);
    font-weight: 500;
    margin: 0;
}
.nf-unread-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.1);
    border: 1.5px solid rgba(255,255,255,0.18);
    border-radius: 14px;
    padding: 10px 18px;
    backdrop-filter: blur(8px);
}
.nf-unread-pill .count {
    font-size: 1.6rem;
    font-weight: 800;
    color: #A3E635;
    line-height: 1;
}
.nf-unread-pill .label {
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255,255,255,0.55);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    line-height: 1.3;
}
.nf-pulse {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #A3E635;
    box-shadow: 0 0 0 3px rgba(163,230,53,0.28);
    animation: nfPulse 2s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes nfPulse {
    0%,100% { box-shadow: 0 0 0 3px rgba(163,230,53,0.28); }
    50%      { box-shadow: 0 0 0 6px rgba(163,230,53,0.1); }
}

/* ─── Filter Bar ─── */
.nf-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    gap: 12px;
    flex-wrap: wrap;
}
.nf-filter-tabs {
    display: flex;
    gap: 6px;
    background: #fff;
    border: 1px solid #E0EDE7;
    border-radius: 12px;
    padding: 4px;
    box-shadow: 0 1px 8px rgba(15,57,43,0.05);
}
.nf-filter-tab {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.75rem;
    font-weight: 700;
    color: #7a9e8e;
    background: transparent;
    border: none;
    border-radius: 9px;
    padding: 6px 14px;
    cursor: pointer;
    transition: all 0.18s;
    white-space: nowrap;
}
.nf-filter-tab:hover { color: #0F392B; background: #F0F7F4; }
.nf-filter-tab.active { background: #0F392B; color: #fff; }
.nf-count-badge {
    font-size: 0.67rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 6px;
    background: rgba(255,255,255,0.18);
    margin-left: 4px;
}
.nf-filter-tab:not(.active) .nf-count-badge { background: #E0EDE7; color: #3a6b55; }
.nf-total-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #a0b8b0;
}

/* ─── Notification Card ─── */
.nf-card {
    background: #fff;
    border: 1.5px solid #E8F0ED;
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    position: relative;
    overflow: hidden;
    transition: transform 0.22s cubic-bezier(0.16,1,0.3,1), box-shadow 0.22s cubic-bezier(0.16,1,0.3,1), border-color 0.2s;
    animation: nfFadeUp 0.4s cubic-bezier(0.16,1,0.3,1) both;
    margin-bottom: 10px;
}
.nf-card:last-child { margin-bottom: 0; }
.nf-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(15,57,43,0.09);
    border-color: #C8DDD6;
}
@keyframes nfFadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Stagger */
.nf-card:nth-child(1)  { animation-delay: 0.04s; }
.nf-card:nth-child(2)  { animation-delay: 0.08s; }
.nf-card:nth-child(3)  { animation-delay: 0.12s; }
.nf-card:nth-child(4)  { animation-delay: 0.16s; }
.nf-card:nth-child(5)  { animation-delay: 0.20s; }
.nf-card:nth-child(6)  { animation-delay: 0.24s; }
.nf-card:nth-child(n+7){ animation-delay: 0.28s; }

/* Unread state */
.nf-card.is-unread {
    background: linear-gradient(to right, rgba(57,181,74,0.04), #fff 55%);
    border-color: rgba(57,181,74,0.22);
}
.nf-card.is-unread::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3.5px;
    background: linear-gradient(to bottom, #39B54A, #A3E635);
    border-radius: 0 2px 2px 0;
}

/* ─── Icon Boxes ─── */
.nf-icon {
    width: 44px; height: 44px;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    transition: transform 0.2s;
}
.nf-card:hover .nf-icon { transform: scale(1.08); }
.nf-icon-finance { background: linear-gradient(135deg, #0F392B, #2d7a56); color: #A3E635; }
.nf-icon-success { background: #D1FAE5; color: #059669; }
.nf-icon-alert   { background: #FEE2E2; color: #DC2626; }
.nf-icon-general { background: #F0F7F4; color: #5a7a6e; }

/* ─── Card Body ─── */
.nf-card-body { flex: 1; min-width: 0; }
.nf-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 5px;
}
.nf-card-title {
    font-size: 0.9rem;
    font-weight: 800;
    color: #0F392B;
    margin: 0;
    line-height: 1.35;
    display: flex;
    align-items: center;
    gap: 7px;
}
.nf-new-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #39B54A;
    box-shadow: 0 0 0 2.5px rgba(57,181,74,0.22);
    flex-shrink: 0;
    animation: dotPulse 2s ease-in-out infinite;
}
@keyframes dotPulse {
    0%,100% { box-shadow: 0 0 0 2.5px rgba(57,181,74,0.22); }
    50%      { box-shadow: 0 0 0 5px rgba(57,181,74,0.08); }
}
.nf-card-time {
    font-size: 0.72rem;
    font-weight: 600;
    color: #a0b8b0;
    white-space: nowrap;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 4px;
}
.nf-card-msg {
    font-size: 0.84rem;
    color: #7a9e8e;
    line-height: 1.6;
    margin: 0;
    font-weight: 500;
}

/* ─── Read badge ─── */
.nf-read-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #F0F7F4;
    color: #a0b8b0;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    align-self: flex-start;
    flex-shrink: 0;
    margin-top: 2px;
}

/* ─── End Divider ─── */
.nf-end {
    text-align: center;
    margin-top: 28px;
    display: flex;
    align-items: center;
    gap: 14px;
    color: #c8ddd6;
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.nf-end::before, .nf-end::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #E0EDE7;
}

/* ─── Empty State ─── */
.nf-empty {
    text-align: center;
    padding: 72px 24px;
    animation: nfFadeUp 0.5s cubic-bezier(0.16,1,0.3,1) both;
}
.nf-empty-icon {
    width: 90px; height: 90px;
    border-radius: 50%;
    background: #F0F7F4;
    border: 1.5px solid #E0EDE7;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 22px;
    color: #a0b8b0;
    font-size: 2rem;
}
.nf-empty h4 {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0F392B;
    margin-bottom: 6px;
}
.nf-empty p {
    font-size: 0.87rem;
    color: #7a9e8e;
    font-weight: 500;
    margin: 0;
}
</style>

<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

        <?php
            $unread_count = 0;
            $all_rows = [];
            while ($row = $result->fetch_assoc()) {
                if ((int)$row['is_read'] === 0) $unread_count++;
                $all_rows[] = $row;
            }
            $total = count($all_rows);
            $read_count = $total - $unread_count;
        ?>

        <!-- ── Hero ── -->
        <div class="nf-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="nf-hero-eyebrow">
                        <i class="bi bi-bell-fill"></i> Notification Center
                    </div>
                    <h1>Admin Notifications.</h1>
                    <p class="nf-hero-sub">Stay up-to-date with system alerts, member actions, and important updates.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <?php if ($unread_count > 0): ?>
                    <div class="nf-unread-pill d-inline-flex">
                        <span class="nf-pulse"></span>
                        <div>
                            <div class="count"><?= $unread_count ?></div>
                            <div class="label">Unread</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="nf-unread-pill d-inline-flex">
                        <i class="bi bi-check-circle-fill" style="color:#A3E635; font-size:1.2rem;"></i>
                        <div>
                            <div class="count" style="font-size:1rem; color:#fff;">All read</div>
                            <div class="label">You're caught up</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-10">

                <!-- Filter Bar -->
                <?php if ($total > 0): ?>
                <div class="nf-filter-bar">
                    <div class="nf-filter-tabs">
                        <button class="nf-filter-tab active" onclick="filterNotifs('all', this)">
                            All <span class="nf-count-badge"><?= $total ?></span>
                        </button>
                        <button class="nf-filter-tab" onclick="filterNotifs('unread', this)">
                            Unread <span class="nf-count-badge"><?= $unread_count ?></span>
                        </button>
                        <button class="nf-filter-tab" onclick="filterNotifs('read', this)">
                            Read <span class="nf-count-badge"><?= $read_count ?></span>
                        </button>
                    </div>
                    <span class="nf-total-label"><?= $total ?> notification<?= $total !== 1 ? 's' : '' ?> total</span>
                </div>
                <?php endif; ?>

                <!-- Notifications -->
                <?php if (!empty($all_rows)): ?>
                <div class="nf-list" id="nfList">
                    <?php foreach ($all_rows as $index => $row):
                        $title       = htmlspecialchars($row['title'] ?: 'Notification');
                        $message     = htmlspecialchars($row['message'] ?: '');
                        $created_raw = $row['created_at'] ?? null;
                        $time_diff   = time() - strtotime($created_raw);

                        if ($time_diff < 60) {
                            $time = 'Just now';
                        } elseif ($time_diff < 3600) {
                            $time = floor($time_diff / 60) . ' min ago';
                        } elseif ($time_diff < 86400) {
                            $time = floor($time_diff / 3600) . ' hr ago';
                        } elseif ($time_diff < 172800) {
                            $time = 'Yesterday';
                        } else {
                            $time = date('d M, Y', strtotime($created_raw));
                        }

                        $is_new = ((int)$row['is_read'] === 0);
                        $style  = getNotificationStyle($title, $message);
                    ?>
                    <div class="nf-card <?= $is_new ? 'is-unread' : '' ?>"
                         data-status="<?= $is_new ? 'unread' : 'read' ?>">
                        <div class="nf-icon nf-icon-<?= $style['type'] ?>">
                            <i class="bi <?= $style['icon'] ?>"></i>
                        </div>
                        <div class="nf-card-body">
                            <div class="nf-card-top">
                                <h6 class="nf-card-title">
                                    <?= $title ?>
                                    <?php if ($is_new): ?>
                                        <span class="nf-new-dot" title="Unread"></span>
                                    <?php endif; ?>
                                </h6>
                                <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                                    <?php if (!$is_new): ?>
                                        <span class="nf-read-badge"><i class="bi bi-check2"></i> Read</span>
                                    <?php endif; ?>
                                    <span class="nf-card-time">
                                        <i class="bi bi-clock" style="font-size:0.65rem;"></i> <?= $time ?>
                                    </span>
                                </div>
                            </div>
                            <p class="nf-card-msg"><?= nl2br($message) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="nf-end">End of Notifications</div>

                <?php else: ?>

                <div class="nf-empty">
                    <div class="nf-empty-icon">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <h4>You're all caught up</h4>
                    <p>No notifications right now. System alerts will appear here when triggered.</p>
                </div>

                <?php endif; ?>

            </div>
        </div>

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->

<script>
function filterNotifs(filter, btn) {
    document.querySelectorAll('.nf-filter-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.nf-card').forEach(card => {
        if (filter === 'all') {
            card.style.display = '';
        } else {
            card.style.display = card.dataset.status === filter ? '' : 'none';
        }
    });
}
</script>

<?php
if (!empty($all_rows)) {
    if ($stmt2 = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'admin' AND (user_id = ? OR to_role = ? OR to_role = 'all') AND is_read = 0")) {
        $stmt2->bind_param("is", $admin_id, $user_role);
        $stmt2->execute();
    }
}
?>

</body>
</html>