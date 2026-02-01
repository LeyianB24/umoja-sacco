<?php
// member/welfare_situations.php
// Enhanced UI: Forest Green & Lime Theme + Responsive Sidebar

session_start();

// 1. Config & Auth
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../inc/auth.php';

// Validate Login
if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

// Helper: Time Ago
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $string = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute');
    foreach ($string as $k => &$v) {
        $value = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
        if ($value) $v = $value . ' ' . $v . ($value > 1 ? 's' : '');
        else unset($string[$k]);
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// 2. Fetch Active Cases
$sql = "SELECT c.*, 
        (SELECT COALESCE(SUM(amount), 0) FROM welfare_donations WHERE case_id = c.case_id) as raised,
        (SELECT COUNT(*) FROM welfare_donations WHERE case_id = c.case_id) as donor_count
        FROM welfare_cases c 
        WHERE c.status = 'active' 
        ORDER BY c.created_at DESC";
$result = $conn->query($sql);

$pageTitle = "Welfare Situations";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= defined('SITE_NAME') ? SITE_NAME : 'SACCO' ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* --- HOPE UI VARIABLES --- */
            --hop-dark: #0F2E25;      /* Deep Forest Green */
            --hop-lime: #D0F35D;      /* Vibrant Lime */
            --hop-lime-hover: #bce045;
            --hop-bg: #F8F9FA;        /* Light Background */
            --hop-card-bg: #FFFFFF;
            --hop-text: #1F2937;
            --hop-border: #EDEFF2;
            --card-radius: 24px;
        }

        [data-bs-theme="dark"] {
            --hop-bg: #0b1210;
            --hop-card-bg: #1F2937;
            --hop-text: #F9FAFB;
            --hop-border: #374151;
            --hop-dark: #13241f;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--hop-bg);
            color: var(--hop-text);
        }

        /* --- LAYOUT WRAPPER FOR SIDEBAR --- */
        .main-content-wrapper {
            margin-left: 280px; 
            transition: margin-left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0 !important; } }

        /* --- CARDS --- */
        .hope-card {
            background: var(--hop-card-bg);
            border-radius: var(--card-radius);
            border: 1px solid var(--hop-border);
            box-shadow: 0 10px 40px rgba(0,0,0,0.03);
            overflow: hidden;
            display: flex; flex-direction: column;
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .hope-card:hover { transform: translateY(-5px); box-shadow: 0 15px 50px rgba(0,0,0,0.06); }

        /* Card Header Visual */
        .card-visual {
            height: 120px;
            background: linear-gradient(135deg, var(--hop-dark) 0%, #1a4d40 100%);
            position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .visual-icon { font-size: 3.5rem; color: rgba(255,255,255,0.1); }
        
        .badge-urgent {
            position: absolute; top: 1rem; right: 1rem;
            background: rgba(255, 255, 255, 0.9); color: #dc2626;
            font-weight: 700; font-size: 0.7rem; padding: 0.4rem 0.8rem;
            border-radius: 50px; text-transform: uppercase; letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Card Body */
        .card-body-custom { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
        
        .donor-pill {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--hop-bg); border: 2px solid var(--hop-card-bg);
            padding: 6px 12px; border-radius: 50px;
            font-size: 0.75rem; font-weight: 600; color: #6b7280;
            margin-top: -30px; margin-bottom: 15px; position: relative; z-index: 2;
        }

        .campaign-title { font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem; line-height: 1.3; }
        .campaign-desc { 
            font-size: 0.9rem; color: #6b7280; line-height: 1.5; margin-bottom: 1.5rem;
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
        }

        /* Progress Bar */
        .progress-container { margin-top: auto; }
        .progress { height: 8px; background-color: var(--hop-border); border-radius: 10px; overflow: hidden; }
        .progress-bar { background-color: var(--hop-dark); transition: width 1s ease; }
        .progress-bar.high-impact { background-color: var(--hop-lime); }

        /* Button */
        .btn-donate {
            width: 100%; padding: 0.75rem; border-radius: 12px;
            background: var(--hop-lime); color: var(--hop-dark);
            font-weight: 700; border: none; margin-top: 1.5rem;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.2s;
        }
        .btn-donate:hover { background: var(--hop-lime-hover); transform: scale(1.02); }
    </style>
</head>
<body>

<div class="d-flex">
    
    <?php require_once __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="flex-fill main-content-wrapper d-flex flex-column min-vh-100">
        
        <?php require_once __DIR__ . '/../inc/topbar.php'; ?>

        <div class="container-fluid flex-grow-1 py-5 px-4">
            
            <div class="row align-items-center mb-5">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-1">Community Welfare</h2>
                    <p class="text-secondary mb-0">Stand together. Support members in times of need.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="d-inline-flex align-items-center bg-white rounded-pill px-4 py-2 shadow-sm border">
                        <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                            <i class="bi bi-shield-check text-success"></i>
                        </div>
                        <div class="text-start lh-1">
                            <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Status</div>
                            <div class="fw-bold text-dark">Verified Cases</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $target = (float)$row['target_amount'];
                        $raised = (float)$row['raised'];
                        $percent = ($target > 0) ? ($raised / $target) * 100 : 0;
                        
                        $barColorClass = ($percent >= 70) ? 'high-impact' : '';
                        $daysAgo = time_elapsed_string($row['created_at']);
                        $donors = $row['donor_count'];
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="hope-card">
                            
                            <div class="card-visual">
                                <i class="bi bi-heart-pulse visual-icon"></i>
                                <span class="badge-urgent">
                                    <i class="bi bi-clock-history me-1"></i> Urgent
                                </span>
                            </div>

                            <div class="card-body-custom">
                                <div>
                                    <span class="donor-pill shadow-sm">
                                        <i class="bi bi-people-fill text-success"></i> <?= $donors ?> Supporters
                                    </span>
                                </div>

                                <div class="mb-1">
                                    <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">
                                        Posted <?= $daysAgo ?>
                                    </small>
                                </div>

                                <h5 class="campaign-title"><?= htmlspecialchars($row['title']) ?></h5>
                                <p class="campaign-desc">
                                    <?= nl2br(htmlspecialchars($row['description'])) ?>
                                </p>
                                
                                <div class="progress-container">
                                    <div class="d-flex justify-content-between align-items-end mb-2">
                                        <span class="fw-bold text-dark fs-5">KES <?= number_format($raised) ?></span>
                                        <span class="small text-muted">of <?= number_format($target) ?> goal</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?= $barColorClass ?>" role="progressbar" 
                                             style="width: <?= min(100, $percent) ?>%"></div>
                                    </div>
                                    <div class="mt-2 d-flex justify-content-between">
                                        <small class="text-success fw-bold"><?= number_format($percent, 0) ?>% Funded</small>
                                    </div>
                                </div>

                                <a href="mpesa_request.php?type=welfare_case&case_id=<?= $row['case_id'] ?>" class="btn-donate shadow-sm">
                                    <span>Donate Now</span> <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>

                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5 bg-white rounded-4 shadow-sm border border-dashed">
                            <div class="mb-3">
                                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="bi bi-emoji-smile text-muted fs-1"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-dark mb-2">No Active Situations</h4>
                            <p class="text-muted">The community is currently doing well. Check back later.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div> 
        
        <?php require_once __DIR__ . '/../inc/footer.php'; ?>
        
    </div> 
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>