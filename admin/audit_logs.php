<?php
// usms/admin/audit_logs.php
// System Audit Trails - Enhanced 'Hope' Theme (Forest & Lime)

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// AUTH CHECK
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit;
}

// DB & SETUP
$db = $conn;
$admin_name = $_SESSION['full_name'] ?? 'System Admin';

// SEARCH LOGIC
$search = trim($_GET['q'] ?? '');
$where = "";
$params = [];
$types  = "";

if ($search !== "") {
    $where = "WHERE (a.action LIKE ? OR a.details LIKE ? OR ad.username LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term];
    $types  = "sss";
}

// FETCH AUDITS
$query = "
    SELECT a.*, ad.username, ad.role, ad.full_name
    FROM audit_logs a
    LEFT JOIN admins ad ON a.admin_id = ad.admin_id
    $where
    ORDER BY a.created_at DESC
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// HELPERS
function getInitials($name) {
    return strtoupper(substr($name ?? 'S', 0, 1));
}

function getActionStyle($action) {
    $a = strtolower($action);
    
    // Critical/Destructive -> Red (Standard Alert)
    if (str_contains($a, 'delete') || str_contains($a, 'fail') || str_contains($a, 'error') || str_contains($a, 'lock'))
        return ['class' => 'badge-soft-danger', 'icon' => 'bi-exclamation-octagon-fill'];

    // Updates/Changes -> Warning/Orange
    if (str_contains($a, 'update') || str_contains($a, 'edit') || str_contains($a, 'suspend'))
        return ['class' => 'badge-soft-warning', 'icon' => 'bi-pencil-square'];

    // Positive/Creation -> BRAND MINT/LIME
    if (str_contains($a, 'create') || str_contains($a, 'add') || str_contains($a, 'approve') || str_contains($a, 'unlock'))
        return ['class' => 'badge-soft-lime', 'icon' => 'bi-check-circle-fill'];

    // Access -> BRAND EMERALD
    if (str_contains($a, 'login'))
        return ['class' => 'badge-soft-emerald', 'icon' => 'bi-arrow-right-circle-fill'];

    // Default -> Gray
    return ['class' => 'badge-soft-secondary', 'icon' => 'bi-activity'];
}

$pageTitle = "Audit Logs";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            /* --- HOPE PALETTE --- */
            /* Deep Forest Green: Used for Text High Contrast & Sidebar */
            --forest-deep: #022c22;
            
            /* Rich Emerald: Used for Secondary Emphasis & Accents */
            --emerald-rich: #064e3b;
            
            /* Vibrant Lime: Used for Primary Actions (Buttons) & Highlights */
            --lime-vibrant: #bef264; 
            --lime-hover: #a3e635;
            
            /* Pale Mint: Used for Background tints */
            --mint-pale: #ecfccb; 
            
            /* Neutrals */
            --bg-body: #f8fafc;
            --surface: #ffffff;
            --text-muted: #64748b;
            --border-subtle: #f1f5f9;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--forest-deep);
        }

        /* Layout */
        .main-content-wrapper { margin-left: 260px; transition: 0.3s; padding: 2.5rem; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }

        /* Card System */
        .card-hope {
            background: var(--surface);
            border: none;
            border-radius: 24px; /* Large radius per screenshot */
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        /* Search Bar Enhancement */
        .search-wrapper {
            background: var(--surface);
            border-radius: 50px;
            padding: 8px 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            border: 1px solid transparent;
            transition: all 0.3s ease;
        }
        .search-wrapper:focus-within {
            border-color: var(--lime-vibrant);
            box-shadow: 0 0 0 4px rgba(190, 242, 100, 0.2);
        }
        .search-input {
            border: none;
            outline: none;
            background: transparent;
            color: var(--forest-deep);
            font-weight: 600;
        }
        .search-input::placeholder { color: #94a3b8; font-weight: 400; }

        /* Primary Action Button (Lime) */
        .btn-hope-primary {
            background-color: var(--lime-vibrant);
            color: var(--forest-deep);
            border: none;
            border-radius: 50px;
            padding: 10px 28px;
            font-weight: 700;
            transition: all 0.2s;
        }
        .btn-hope-primary:hover {
            background-color: var(--lime-hover);
            transform: translateY(-1px);
        }

        /* Print Button (Outline) */
        .btn-hope-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: var(--text-muted);
            border-radius: 50px;
            padding: 8px 24px;
            font-weight: 600;
        }
        .btn-hope-outline:hover {
            border-color: var(--forest-deep);
            color: var(--forest-deep);
        }

        /* Table Styling */
        .table-hope { border-collapse: separate; border-spacing: 0; width: 100%; }
        .table-hope thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            font-weight: 700;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-subtle);
        }
        .table-hope tbody td {
            padding: 1.25rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-subtle);
            color: var(--text-muted);
            font-weight: 500;
        }
        .table-hope tbody tr:last-child td { border-bottom: none; }
        .table-hope tbody tr:hover { background-color: #fcfdfd; }

        /* Avatar */
        .avatar-box {
            width: 42px; height: 42px; 
            border-radius: 14px;
            background-color: #f1f5f9;
            color: var(--forest-deep);
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.9rem;
        }

        /* Badge System - Utilizing the Green Palette */
        .badge-pill {
            padding: 8px 14px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.75rem;
            display: inline-flex; align-items: center; gap: 8px;
        }
        
        /* The Mint/Lime Badge for Positive actions */
        .badge-soft-lime {
            background-color: var(--mint-pale);
            color: var(--emerald-rich);
        }
        
        /* The Emerald Badge for Access */
        .badge-soft-emerald {
            background-color: #d1fae5;
            color: var(--forest-deep);
        }
        
        .badge-soft-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-soft-warning { background-color: #fef3c7; color: #92400e; }
        .badge-soft-secondary { background-color: #f1f5f9; color: #64748b; }

        /* Text Utilities */
        .text-forest { color: var(--forest-deep); }
        .text-emerald { color: var(--emerald-rich); }

    </style>
</head>
<body>

<div class="d-flex">

    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="flex-fill main-content-wrapper">

        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <div class="row align-items-end mb-5">
            <div class="col-md-7">
                <h2 class="fw-bold mb-1 display-6" style="letter-spacing: -0.03em;">System Audit Trails</h2>
                <p class="text-muted mb-0">Track admin activities and security events in real-time.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <button onclick="window.print()" class="btn btn-hope-outline me-2">
                    <i class="bi bi-printer me-2"></i> Print
                </button>
                <button class="btn btn-dark rounded-pill px-4 fw-bold">
                    <i class="bi bi-download me-2"></i> Export
                </button>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <form method="GET">
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1 search-wrapper d-flex align-items-center bg-white">
                            <i class="bi bi-search text-emerald ps-3 pe-2 fs-5"></i>
                            <input type="text" name="q" class="search-input w-100" 
                                   placeholder="Search logs by actor, action, or details..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <button type="submit" class="btn btn-hope-primary shadow-sm">
                            Search Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-hope">
            <div class="px-4 py-3 border-bottom border-light d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-circle bg-success p-2 border border-white border-2"></span>
                    <h6 class="mb-0 fw-bold text-forest">Recent Activity</h6>
                </div>
                <span class="text-muted small fw-semibold bg-light px-3 py-1 rounded-pill">
                    <?= $logs->num_rows ?> Records Found
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hope mb-0">
                    <thead class="bg-light bg-opacity-50">
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Action Type</th>
                            <th style="width: 35%;">Details</th>
                            <th class="text-end">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-tree text-emerald opacity-25" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2 fw-semibold">No audit logs found.</p>
                                </td>
                            </tr>
                        <?php else: while ($row = $logs->fetch_assoc()):
                            $style = getActionStyle($row['action']);
                            $name  = $row['full_name'] ?? $row['username'] ?? 'System';
                            $role  = ucfirst($row['role'] ?? 'System');
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-forest"><?= date("H:i", strtotime($row['created_at'])) ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date("M d, Y", strtotime($row['created_at'])) ?></small>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-box">
                                            <?= getInitials($name) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-emerald"><?= htmlspecialchars($name) ?></div>
                                            <div class="small text-muted"><?= $role ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge-pill <?= $style['class'] ?>">
                                        <i class="bi <?= $style['icon'] ?>"></i>
                                        <?= ucwords(str_replace('_', ' ', $row['action'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="text-forest opacity-75">
                                        <?= htmlspecialchars($row['details']) ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="font-monospace small text-muted bg-light px-2 py-1 rounded border border-light">
                                        <?= htmlspecialchars($row['ip_address'] ?? '::1') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="px-4 py-3 border-top border-light text-center">
                <small class="text-muted fw-bold">Showing latest 200 entries</small>
            </div>
        </div>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
