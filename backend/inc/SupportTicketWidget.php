<?php
/**
 * inc/SupportTicketWidget.php
 * Reusable support ticket alert widget for admin domain pages.
 *
 * Usage (include once at top of PHP block after $conn is available):
 *   require_once __DIR__ . '/../../inc/SupportTicketWidget.php';
 *
 * Then render in HTML where you want the bar to appear:
 *   <?php render_support_ticket_widget($conn, ['loans']); ?>
 *   <?php render_support_ticket_widget($conn, ['savings', 'withdrawals']); ?>
 */

declare(strict_types=1);

/**
 * Fetch pending/open support tickets for given categories,
 * respecting the current admin's role so non-superadmins
 * only see tickets assigned to their role.
 */
function get_support_tickets_for_widget(mysqli $conn, array $categories): array
{
    if (empty($categories)) return [];

    $my_role_id = (int)($_SESSION['role_id'] ?? 0);
    $is_super   = ($my_role_id === 1);

    // Build category IN clause safely
    $placeholders = implode(',', array_fill(0, count($categories), '?'));
    $types        = str_repeat('s', count($categories));

    $role_clause = $is_super ? '' : " AND st.assigned_role_id = $my_role_id";

    $sql = "SELECT st.support_id, st.category, st.subject, st.status,
                   st.created_at, m.full_name AS member_name
            FROM support_tickets st
            LEFT JOIN members m ON st.member_id = m.member_id
            WHERE st.category IN ($placeholders)
              AND st.status IN ('Pending','Open')
              $role_clause
            ORDER BY st.created_at DESC
            LIMIT 5";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$categories);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Render the compact ticket alert bar.
 *
 * @param mysqli $conn         DB connection
 * @param array  $categories   Ticket category slugs (e.g. ['loans'])
 * @param string $label        Human-readable label (e.g. 'Loans')
 */
function render_support_ticket_widget(mysqli $conn, array $categories, string $label = ''): void
{
    $tickets = get_support_tickets_for_widget($conn, $categories);
    if (empty($tickets)) return;

    $count = count($tickets);
    if ($label === '') {
        $label = implode(' / ', array_map('ucfirst', $categories));
    }

    // Build the "View All" URL (use first category for filter)
    $primary_cat  = $categories[0];
    $view_all_url = BASE_URL . '/admin/pages/support.php?category=' . urlencode($primary_cat);

    // Urgent count (tickets > 24h old and still Pending)
    $urgent = array_filter($tickets, function ($t) {
        return $t['status'] === 'Pending'
            && (time() - strtotime($t['created_at'])) > 86400;
    });
    $is_urgent = count($urgent) > 0;
    ?>
<style>
/* ── SupportTicketWidget ─────────────────────────────────────────── */
.stw-bar {
    background: #fff;
    border-radius: 16px;
    border: 1px solid <?= $is_urgent ? 'rgba(220,38,38,.2)' : 'rgba(245,158,11,.2)' ?>;
    border-left: 4px solid <?= $is_urgent ? '#dc2626' : '#f59e0b' ?>;
    box-shadow: 0 2px 14px <?= $is_urgent ? 'rgba(220,38,38,.07)' : 'rgba(245,158,11,.07)' ?>;
    margin-bottom: 20px;
    overflow: hidden;
    animation: stwFadeIn .4s ease both;
}
@keyframes stwFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
.stw-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    background: <?= $is_urgent ? '#fef2f2' : '#fffbeb' ?>;
    cursor: pointer;
    user-select: none;
    gap: 12px;
    flex-wrap: wrap;
}
.stw-head-left  { display: flex; align-items: center; gap: 10px; }
.stw-icon {
    width: 32px; height: 32px;
    border-radius: 9px;
    background: <?= $is_urgent ? 'rgba(220,38,38,.12)' : 'rgba(245,158,11,.12)' ?>;
    color: <?= $is_urgent ? '#dc2626' : '#b45309' ?>;
    display: flex; align-items: center; justify-content: center;
    font-size: .88rem; flex-shrink: 0;
}
.stw-title {
    font-size: .82rem; font-weight: 800;
    color: <?= $is_urgent ? '#b91c1c' : '#92400e' ?>;
}
.stw-sub {
    font-size: .7rem; font-weight: 500;
    color: <?= $is_urgent ? '#dc2626' : '#b45309' ?>;
    opacity: .75;
}
.stw-count {
    background: <?= $is_urgent ? '#dc2626' : '#f59e0b' ?>;
    color: #fff; border-radius: 100px;
    padding: .18rem .7rem; font-size: .7rem; font-weight: 800;
    white-space: nowrap;
}
.stw-actions { display: flex; align-items: center; gap: 8px; }
.stw-toggle-btn {
    background: none; border: none; cursor: pointer;
    color: <?= $is_urgent ? '#b91c1c' : '#92400e' ?>;
    font-size: .78rem; opacity: .6; padding: 0; line-height: 1;
    transition: opacity .15s;
}
.stw-toggle-btn:hover { opacity: 1; }
.stw-view-all {
    font-size: .75rem; font-weight: 700;
    color: <?= $is_urgent ? '#dc2626' : '#b45309' ?>;
    text-decoration: none; white-space: nowrap;
    border: 1.5px solid <?= $is_urgent ? 'rgba(220,38,38,.3)' : 'rgba(245,158,11,.3)' ?>;
    border-radius: 100px; padding: .25rem .85rem;
    transition: all .18s ease;
}
.stw-view-all:hover {
    background: <?= $is_urgent ? '#dc2626' : '#f59e0b' ?>;
    color: #fff;
}
.stw-body { padding: 0; display: none; }
.stw-body.open { display: block; }
.stw-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 18px; border-bottom: 1px solid rgba(0,0,0,.04);
    transition: background .12s ease;
}
.stw-row:last-child { border-bottom: none; }
.stw-row:hover { background: rgba(0,0,0,.015); }
.stw-row-id {
    font-size: .7rem; font-weight: 800;
    color: #888; font-family: monospace; flex-shrink: 0; min-width: 42px;
}
.stw-row-subject {
    flex: 1; font-size: .82rem; font-weight: 600; color: #1c2f1e;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.stw-row-member {
    font-size: .72rem; color: #6b7c74; white-space: nowrap; flex-shrink: 0;
}
.stw-row-status {
    font-size: .65rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .04em; border-radius: 7px; padding: .2rem .6rem;
    flex-shrink: 0;
}
.stw-row-status.pending { background: #fffbeb; color: #b45309; border: 1px solid rgba(245,158,11,.2); }
.stw-row-status.open    { background: #eff6ff; color: #1d4ed8; border: 1px solid rgba(59,130,246,.2); }
.stw-row-age { font-size: .68rem; color: #9cb; flex-shrink: 0; white-space: nowrap; }
.stw-row-link {
    font-size: .72rem; color: #3b82f6; text-decoration: none; flex-shrink: 0;
    font-weight: 700; transition: color .15s;
}
.stw-row-link:hover { color: #1d4ed8; }
/* ─────────────────────────────────────────────────────────────────── */
</style>

<div class="stw-bar">
    <div class="stw-head" onclick="stwToggle(this)">
        <div class="stw-head-left">
            <div class="stw-icon">
                <i class="bi bi-<?= $is_urgent ? 'exclamation-triangle-fill' : 'chat-dots-fill' ?>"></i>
            </div>
            <div>
                <div class="stw-title">
                    <?= $count ?> Pending <?= htmlspecialchars($label) ?> Support <?= $count === 1 ? 'Ticket' : 'Tickets' ?>
                </div>
                <div class="stw-sub">
                    <?= $is_urgent
                        ? count($urgent) . ' ticket(s) waiting over 24 hrs — action needed'
                        : 'Members are waiting for a response' ?>
                </div>
            </div>
        </div>
        <div class="stw-actions">
            <span class="stw-count"><?= $count ?> <?= $is_urgent ? '⚠' : '' ?></span>
            <a href="<?= htmlspecialchars($view_all_url) ?>" class="stw-view-all" onclick="event.stopPropagation()">
                View All <i class="bi bi-arrow-right" style="font-size:.65rem"></i>
            </a>
            <button class="stw-toggle-btn" title="Toggle"><i class="bi bi-chevron-down"></i></button>
        </div>
    </div>

    <div class="stw-body" id="stw-body-<?= md5(implode(',', $categories)) ?>">
        <?php foreach ($tickets as $t):
            $age_secs = time() - strtotime($t['created_at']);
            $age_str  = $age_secs < 3600
                ? round($age_secs / 60) . 'm ago'
                : ($age_secs < 86400 ? round($age_secs / 3600) . 'h ago' : round($age_secs / 86400) . 'd ago');
            $status_class = strtolower($t['status']);
        ?>
        <div class="stw-row">
            <span class="stw-row-id">#<?= $t['support_id'] ?></span>
            <span class="stw-row-subject" title="<?= htmlspecialchars($t['subject']) ?>">
                <?= htmlspecialchars($t['subject']) ?>
            </span>
            <span class="stw-row-member">
                <i class="bi bi-person-fill" style="font-size:.65rem;opacity:.5"></i>
                <?= htmlspecialchars($t['member_name'] ?? 'Member') ?>
            </span>
            <span class="stw-row-status <?= $status_class ?>"><?= htmlspecialchars($t['status']) ?></span>
            <span class="stw-row-age"><?= $age_str ?></span>
            <a href="<?= BASE_URL ?>/admin/pages/support_view.php?id=<?= $t['support_id'] ?>"
               class="stw-row-link">
                Reply <i class="bi bi-arrow-right" style="font-size:.6rem"></i>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function stwToggle(head) {
    var bodyId = head.nextElementSibling.id;
    var body   = document.getElementById(bodyId);
    var icon   = head.querySelector('.stw-toggle-btn i');
    if (body.classList.contains('open')) {
        body.classList.remove('open');
        icon.className = 'bi bi-chevron-down';
    } else {
        body.classList.add('open');
        icon.className = 'bi bi-chevron-up';
    }
}
// Auto-expand if there are urgent tickets
<?php if ($is_urgent): ?>
document.addEventListener('DOMContentLoaded', function() {
    var bodyId = '<?= 'stw-body-' . md5(implode(',', $categories)) ?>';
    var body = document.getElementById(bodyId);
    if (body) {
        body.classList.add('open');
        var icon = body.previousElementSibling.querySelector('.stw-toggle-btn i');
        if (icon) icon.className = 'bi bi-chevron-up';
    }
});
<?php endif; ?>
</script>
    <?php
}
