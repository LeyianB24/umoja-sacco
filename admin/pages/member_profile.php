<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('admin');

/**
 * admin/member_profile.php
 * Member Portrait & Financial Intelligence Hub
 */

require_permission();

$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($member_id <= 0) {
    flash_set("Invalid Member ID specified.", "danger");
    header("Location: members.php");
    exit;
}

// POST HANDLERS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    // File Upload
    if (!empty($_FILES['kyc_upload']['name'])) {
        $doc_type      = $_POST['doc_type'] ?? '';
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size      = 5 * 1024 * 1024;
        $file_type     = mime_content_type($_FILES['kyc_upload']['tmp_name']);
        $file_size     = $_FILES['kyc_upload']['size'];

        if (!in_array($file_type, $allowed_types)) {
            flash_set("Invalid file type. Only JPG, PNG, and PDF are allowed.", "danger");
        } elseif ($file_size > $max_size) {
            flash_set("File is too large. Max size is 5MB.", "danger");
        } else {
            $upload_dir = __DIR__ . '/../../uploads/kyc/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext      = pathinfo($_FILES['kyc_upload']['name'], PATHINFO_EXTENSION);
            $new_name = "{$doc_type}_{$member_id}_" . time() . ".$ext";
            if (move_uploaded_file($_FILES['kyc_upload']['tmp_name'], $upload_dir . $new_name)) {
                $stmt     = $conn->prepare("INSERT INTO member_documents (member_id, document_type, file_path, status, uploaded_at, verified_by, verified_at, verification_notes) VALUES (?, ?, ?, 'verified', NOW(), ?, NOW(), 'Uploaded by Admin') ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), status = 'verified', uploaded_at = NOW(), verified_by = VALUES(verified_by), verified_at = NOW(), verification_notes = VALUES(verification_notes)");
                $admin_id = $_SESSION['admin_id'];
                $stmt->bind_param("issi", $member_id, $doc_type, $new_name, $admin_id);
                if ($stmt->execute()) {
                    flash_set("Document uploaded and auto-verified successfully.", "success");
                    $q = $conn->query("SELECT status FROM member_documents WHERE member_id = $member_id AND document_type IN ('national_id_front', 'national_id_back', 'passport_photo')");
                    $all_docs = $q->fetch_all(MYSQLI_ASSOC);
                    $verified_count = 0; $rejected_count = 0;
                    foreach ($all_docs as $d) {
                        if ($d['status'] === 'verified') $verified_count++;
                        if ($d['status'] === 'rejected') $rejected_count++;
                    }
                    $new_kyc = $verified_count >= 3 ? 'approved' : ($rejected_count > 0 ? 'rejected' : 'pending');
                    $conn->query("UPDATE members SET kyc_status = '$new_kyc' WHERE member_id = $member_id");
                } else {
                    flash_set("Database error: " . $stmt->error, "danger");
                }
                $stmt->close();
            } else {
                flash_set("Failed to move uploaded file.", "danger");
            }
        }
        header("Location: member_profile.php?id=$member_id#kyc"); exit;
    }

    // KYC Verify/Reject
    if (isset($_POST['kyc_action'])) {
        $doc_id = intval($_POST['doc_id']);
        $action = $_POST['kyc_action'];
        $notes  = trim($_POST['verification_notes'] ?? '');
        $status = ($action === 'verify') ? 'verified' : 'rejected';
        $stmt   = $conn->prepare("UPDATE member_documents SET status = ?, verification_notes = ?, verified_by = ?, verified_at = NOW() WHERE document_id = ? AND member_id = ?");
        $stmt->bind_param("ssiii", $status, $notes, $_SESSION['admin_id'], $doc_id, $member_id);
        if ($stmt->execute()) {
            $q = $conn->query("SELECT status FROM member_documents WHERE member_id = $member_id AND document_type IN ('national_id_front', 'national_id_back', 'passport_photo')");
            $all_docs = $q->fetch_all(MYSQLI_ASSOC);
            $vc = 0; $rc = 0;
            foreach ($all_docs as $d) { if ($d['status']==='verified') $vc++; if ($d['status']==='rejected') $rc++; }
            $new_kyc    = $vc >= 3 ? 'approved' : ($rc > 0 ? 'rejected' : 'pending');
            $sql_update = "UPDATE members SET kyc_status = '$new_kyc'" . ($action==='reject' ? ", kyc_notes = '".$conn->real_escape_string($notes)."'" : "") . " WHERE member_id = $member_id";
            $conn->query($sql_update);
            flash_set("Document " . ($action === 'verify' ? 'approved' : 'rejected') . " successfully.", "success");
        } else { flash_set("Action failed: " . $conn->error, "danger"); }
        header("Location: member_profile.php?id=$member_id#kyc"); exit;
    }

    // Recalculate KYC
    if (isset($_POST['recalc_kyc'])) {
        $q = $conn->query("SELECT status FROM member_documents WHERE member_id = $member_id AND document_type IN ('national_id_front', 'national_id_back', 'passport_photo')");
        $all_docs = $q->fetch_all(MYSQLI_ASSOC);
        $vc = 0; $rc = 0;
        foreach ($all_docs as $d) { if ($d['status']==='verified') $vc++; if ($d['status']==='rejected') $rc++; }
        $new_kyc = $vc >= 3 ? 'approved' : ($rc > 0 ? 'rejected' : 'pending');
        $conn->query("UPDATE members SET kyc_status = '$new_kyc' WHERE member_id = $member_id");
        flash_set("KYC status recalculated: " . strtoupper($new_kyc), "success");
        header("Location: member_profile.php?id=$member_id"); exit;
    }
}

// FETCH MEMBER DATA
$stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) { flash_set("Member not found.", "warning"); header("Location: members.php"); exit; }

// FINANCIAL SUMMARY
require_once __DIR__ . '/../../inc/FinancialEngine.php';
$engine             = new FinancialEngine($conn);
$balances           = $engine->getBalances($member_id);
$savings_balance    = $balances['savings'];
$total_debt         = $balances['loans'];
$total_shares_value = $balances['shares'];
$total_shares_units = $balances['share_units'] ?? 0;
$total_contributions = $engine->getLifetimeCredits($member_id, ['savings', 'shares', 'welfare']);

$q_loans = $conn->prepare("SELECT COUNT(*) as active_count FROM loans WHERE member_id = ? AND status IN ('disbursed', 'active')");
$q_loans->bind_param("i", $member_id);
$q_loans->execute();
$active_loans_count = $q_loans->get_result()->fetch_assoc()['active_count'] ?? 0;
$q_loans->close();

// ACTIVITY LISTS
$q_txns = $conn->prepare("SELECT * FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT 5");
$q_txns->bind_param("i", $member_id);
$q_txns->execute();
$recent_txns = $q_txns->get_result()->fetch_all(MYSQLI_ASSOC);

$q_l_list = $conn->prepare("SELECT * FROM loans WHERE member_id = ? ORDER BY created_at DESC LIMIT 5");
$q_l_list->bind_param("i", $member_id);
$q_l_list->execute();
$member_loans = $q_l_list->get_result()->fetch_all(MYSQLI_ASSOC);

// KYC DOCUMENTS
$q_docs = $conn->prepare("SELECT * FROM member_documents WHERE member_id = ?");
$q_docs->bind_param("i", $member_id);
$q_docs->execute();
$member_docs = $q_docs->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = $member['full_name'] . " — Member Profile";
?>
<?php $layout->header($pageTitle); ?>
<?php $layout->sidebar(); ?>
<div class="main-content-wrapper">
    <?php $layout->topbar($pageTitle ?? ""); ?>
    <div class="container-fluid px-4 py-4">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        :root {
            --ease-expo:   cubic-bezier(0.16, 1, 0.3, 1);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        body, .main-content-wrapper, input, select, textarea, table, button, .nav-link {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        /* ── Breadcrumb ── */
        .prof-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 22px;
            font-size: 0.8rem;
            font-weight: 600;
            animation: fadeUp 0.4s var(--ease-expo) both;
        }

        .prof-breadcrumb a {
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .prof-breadcrumb a:hover { color: var(--forest, #0f2e25); }
        .prof-breadcrumb .sep { color: #d1d5db; }
        .prof-breadcrumb .current { color: #374151; }

        /* ── Profile Hero ── */
        .profile-hero {
            background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%);
            border-radius: 24px;
            padding: 40px 48px;
            color: #fff;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.6s var(--ease-expo) both;
        }

        .profile-hero .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        .profile-hero .hero-circle {
            position: absolute;
            top: -80px; right: -80px;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
        }

        .hero-avatar {
            width: 80px; height: 80px;
            border-radius: 20px;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.2);
            flex-shrink: 0;
        }

        .hero-avatar-initials {
            width: 80px; height: 80px;
            border-radius: 20px;
            background: rgba(255,255,255,0.12);
            border: 3px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            color: #a3e635;
            flex-shrink: 0;
        }

        .hero-name {
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -0.4px;
            line-height: 1.1;
            margin-bottom: 6px;
        }

        .hero-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
        }

        .hero-status-chip.active   { background: rgba(163,230,53,0.15); color: #a3e635; border: 1px solid rgba(163,230,53,0.25); }
        .hero-status-chip.inactive,
        .hero-status-chip.suspended { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }

        .hero-status-chip::before {
            content: '';
            width: 5px; height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 6px;
        }

        .hero-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            opacity: 0.7;
        }

        .hero-meta span i { font-size: 0.85rem; }

        .hero-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-hero-primary {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #a3e635;
            color: var(--forest, #0f2e25);
            font-size: 0.85rem;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.22s var(--ease-expo);
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .btn-hero-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(163,230,53,0.3);
            color: var(--forest, #0f2e25);
        }

        .btn-hero-secondary {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 50px;
            cursor: pointer;
            transition: background 0.18s ease;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .btn-hero-secondary:hover { background: rgba(255,255,255,0.17); color: #fff; }

        /* ── Stat Cards ── */
        .fin-stat {
            background: #fff;
            border-radius: 18px;
            padding: 22px 24px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            height: 100%;
            animation: fadeUp 0.6s var(--ease-expo) both;
            transition: transform 0.22s var(--ease-expo), box-shadow 0.22s ease;
        }

        .fin-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.09); }
        .fin-stat:nth-child(1) { animation-delay: 0.05s; }
        .fin-stat:nth-child(2) { animation-delay: 0.10s; }
        .fin-stat:nth-child(3) { animation-delay: 0.15s; }
        .fin-stat:nth-child(4) { animation-delay: 0.20s; }

        .fin-stat .stat-icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 14px;
        }

        .fin-stat .stat-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 5px;
        }

        .fin-stat .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--forest, #0f2e25);
            line-height: 1;
            margin-bottom: 6px;
        }

        .fin-stat .stat-sub {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        /* KYC recalc row */
        .kyc-recalc-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-recalc {
            width: 28px; height: 28px;
            border-radius: 8px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.18s ease;
            padding: 0;
        }

        .btn-recalc:hover { background: rgba(15,46,37,0.07); color: var(--forest,#0f2e25); border-color: rgba(15,46,37,0.15); }

        /* ── Tabs ── */
        .profile-tabs {
            display: flex;
            gap: 4px;
            background: #f3f4f6;
            border-radius: 14px;
            padding: 5px;
            margin-bottom: 24px;
            border: none;
            animation: fadeUp 0.5s var(--ease-expo) 0.25s both;
        }

        .profile-tabs .nav-item { flex: 1; }

        .profile-tabs .nav-link {
            width: 100%;
            text-align: center;
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 0.83rem;
            font-weight: 600;
            color: #6b7280;
            border: none;
            background: transparent;
            transition: all 0.2s var(--ease-expo);
        }

        .profile-tabs .nav-link.active {
            background: #fff;
            color: var(--forest, #0f2e25);
            font-weight: 700;
            box-shadow: 0 1px 6px rgba(0,0,0,0.07);
        }

        .profile-tabs .nav-link:hover:not(.active) { color: #374151; }

        /* ── Content Cards ── */
        .prof-card {
            background: #fff;
            border-radius: 18px;
            padding: 26px 28px;
            border: 1px solid rgba(0,0,0,0.055);
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .prof-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f3f4f6;
        }

        .prof-card-header h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        /* Info rows */
        .info-row {
            display: flex;
            flex-direction: column;
            margin-bottom: 18px;
        }

        .info-row:last-child { margin-bottom: 0; }

        .info-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #111827;
        }

        /* Risk bar */
        .risk-bar-wrap {
            padding: 16px;
            background: #fafafa;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
        }

        .risk-bar-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .risk-bar-label span:first-child {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #9ca3af;
        }

        .risk-bar-label .risk-val {
            font-size: 11px;
            font-weight: 700;
            color: #16a34a;
        }

        .risk-bar {
            height: 5px;
            border-radius: 99px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .risk-bar-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #16a34a, #4ade80);
        }

        /* ── Tables inside tabs ── */
        .prof-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .prof-table thead th {
            background: #fafafa;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #9ca3af;
            padding: 11px 16px;
            border: none;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap;
        }

        .prof-table tbody tr {
            border-bottom: 1px solid #f9fafb;
            transition: background 0.15s ease;
        }

        .prof-table tbody tr:last-child { border-bottom: none; }
        .prof-table tbody tr:hover { background: #fafff8; }
        .prof-table tbody td { padding: 12px 16px; vertical-align: middle; color: #374151; }

        /* Loan status */
        .loan-badge {
            display: inline-flex;
            align-items: center;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 7px;
        }

        .loan-badge.disbursed { background: #f0fdf4; color: #16a34a; }
        .loan-badge.pending   { background: #fffbeb; color: #d97706; }
        .loan-badge.closed    { background: #f3f4f6; color: #6b7280; }

        /* Txn amount */
        .txn-credit { color: #16a34a; font-weight: 700; }
        .txn-debit  { color: #dc2626; font-weight: 700; }

        /* ── KYC Upload Panel ── */
        .kyc-upload-panel {
            background: #fafafa;
            border: 1.5px dashed #d1d5db;
            border-radius: 16px;
            padding: 22px 24px;
            margin-bottom: 20px;
        }

        .kyc-upload-panel h6 {
            font-size: 0.875rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kyc-upload-panel .form-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .kyc-upload-panel .form-control,
        .kyc-upload-panel .form-select {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 9px 13px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #111827;
            box-shadow: none;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .kyc-upload-panel .form-control:focus,
        .kyc-upload-panel .form-select:focus {
            border-color: rgba(15,46,37,0.35);
            box-shadow: 0 0 0 3px rgba(15,46,37,0.07);
        }

        /* ── KYC Doc Cards ── */
        .kyc-doc-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            margin-bottom: 16px;
            transition: box-shadow 0.2s ease;
        }

        .kyc-doc-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }

        .kyc-doc-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .kyc-doc-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kyc-doc-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 6px;
        }

        .kyc-doc-status.verified  { background: #f0fdf4; color: #16a34a; }
        .kyc-doc-status.rejected  { background: #fef2f2; color: #dc2626; }
        .kyc-doc-status.pending   { background: #fffbeb; color: #d97706; }

        .kyc-doc-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: #fafafa;
            border-radius: 10px;
            border: 1px solid #f0f0f0;
        }

        .kyc-doc-icon {
            width: 36px; height: 36px;
            border-radius: 9px;
            background: rgba(15,46,37,0.07);
            color: var(--forest, #0f2e25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .kyc-doc-filename {
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        .kyc-doc-date {
            font-size: 0.72rem;
            color: #9ca3af;
            margin-top: 2px;
        }

        .kyc-action-row {
            margin-top: 12px;
            padding: 12px 14px;
            background: #fafafa;
            border-radius: 10px;
            border: 1px solid #f0f0f0;
        }

        .kyc-action-row .form-control {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 7px 11px;
            font-size: 0.8rem;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .kyc-notes {
            margin-top: 10px;
            padding: 10px 12px;
            background: #fafafa;
            border-left: 3px solid #e5e7eb;
            border-radius: 0 8px 8px 0;
            font-size: 0.78rem;
            color: #6b7280;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
        }

        .empty-state .empty-icon { font-size: 2.8rem; color: #d1d5db; margin-bottom: 14px; }
        .empty-state h5 { font-weight: 700; color: #374151; margin-bottom: 6px; }
        .empty-state p  { font-size: 0.875rem; color: #9ca3af; }

        /* Transactions info panel */
        .txn-info-panel {
            text-align: center;
            padding: 48px 24px;
            color: #9ca3af;
        }

        .txn-info-panel i { font-size: 2.5rem; margin-bottom: 14px; display: block; opacity: 0.3; }
        .txn-info-panel p { font-size: 0.875rem; margin-bottom: 16px; }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .profile-hero { padding: 28px 24px; }
            .hero-name    { font-size: 1.5rem; }
            .hero-actions { margin-top: 16px; }
        }
    </style>

    <!-- ─── BREADCRUMB ─────────────────────────────────────── -->
    <nav class="prof-breadcrumb" aria-label="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        <span class="sep"><i class="bi bi-chevron-right" style="font-size:0.7rem;"></i></span>
        <a href="members.php">Members</a>
        <span class="sep"><i class="bi bi-chevron-right" style="font-size:0.7rem;"></i></span>
        <span class="current"><?= htmlspecialchars($member['full_name']) ?></span>
    </nav>

    <?php flash_render(); ?>

    <!-- ─── PROFILE HERO ───────────────────────────────────── -->
    <div class="profile-hero mb-4">
        <div class="hero-grid"></div>
        <div class="hero-circle"></div>
        <div class="d-flex align-items-center justify-content-between gap-4 flex-wrap">
            <div class="d-flex align-items-center gap-4">
                <?php if ($member['profile_pic']): ?>
                    <img src="data:image/jpeg;base64,<?= base64_encode($member['profile_pic']) ?>"
                         class="hero-avatar" alt="<?= htmlspecialchars($member['full_name']) ?>">
                <?php else: ?>
                    <div class="hero-avatar-initials">
                        <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="hero-name"><?= htmlspecialchars($member['full_name']) ?></div>
                    <span class="hero-status-chip <?= $member['status'] ?>">
                        <?= strtoupper($member['status']) ?>
                    </span>
                    <div class="hero-meta">
                        <span><i class="bi bi-hash"></i> <?= $member['member_reg_no'] ?></span>
                        <span><i class="bi bi-calendar3"></i> Joined <?= date('M d, Y', strtotime($member['join_date'])) ?></span>
                        <span><i class="bi bi-phone"></i> <?= $member['phone'] ?></span>
                    </div>
                </div>
            </div>
            <div class="hero-actions">
                <a href="generate_statement.php?member_id=<?= $member_id ?>" class="btn-hero-primary">
                    <i class="bi bi-file-earmark-pdf"></i> Statement
                </a>
                <?php if (can('manage_members')): ?>
                <button class="btn-hero-secondary" data-bs-toggle="modal" data-bs-target="#editModal">
                    <i class="bi bi-pencil-square"></i> Edit Member
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ─── FINANCIAL STAT CARDS ──────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="fin-stat">
                <div class="stat-icon" style="background:rgba(15,46,37,0.08);color:var(--forest,#0f2e25);">
                    <i class="bi bi-piggy-bank-fill"></i>
                </div>
                <div class="stat-label">Savings Balance</div>
                <div class="stat-value">KES <?= number_format((float)$savings_balance) ?></div>
                <div class="stat-sub">Lifetime: KES <?= number_format((float)$total_contributions) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="fin-stat">
                <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="stat-label">Share Capital</div>
                <div class="stat-value">KES <?= number_format((float)$total_shares_value) ?></div>
                <div class="stat-sub"><?= number_format((float)$total_shares_units) ?> Units Owned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="fin-stat">
                <div class="stat-icon" style="background:#fef2f2;color:#dc2626;">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-label">Loan Exposure</div>
                <div class="stat-value">KES <?= number_format((float)$total_debt) ?></div>
                <div class="stat-sub"><?= $active_loans_count ?> Active Application<?= $active_loans_count != 1 ? 's' : '' ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="fin-stat">
                <div class="stat-icon" style="background:#fffbeb;color:#d97706;">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="stat-label">KYC Status</div>
                <div class="kyc-recalc-row">
                    <div class="stat-value mb-0"><?= ucwords(str_replace('_', ' ', $member['kyc_status'] ?? 'pending')) ?></div>
                    <form method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" name="recalc_kyc" class="btn-recalc"
                                title="Recalculate KYC"
                                onclick="return confirm('Recalculate KYC status based on current documents?');">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </form>
                </div>
                <?php $reg_paid = ($member['registration_fee_status'] === 'paid' || $member['reg_fee_paid'] == 1); ?>
                <div class="stat-sub" style="margin-top:8px;">
                    <?= $reg_paid
                        ? '<span style="color:#16a34a;font-weight:700;">✓ Reg Fee Paid</span>'
                        : '<span style="color:#dc2626;font-weight:700;">✗ Reg Fee Unpaid</span>' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── TABS ───────────────────────────────────────────── -->
    <ul class="nav profile-tabs" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                <i class="bi bi-person me-1"></i> Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#loans" type="button">
                <i class="bi bi-bank me-1"></i> Loan History
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#txns" type="button">
                <i class="bi bi-journal-text me-1"></i> Transactions
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#kyc" type="button">
                <i class="bi bi-shield-check me-1"></i> KYC &amp; Docs
            </button>
        </li>
    </ul>

    <div class="tab-content" id="profileTabsContent">

        <!-- ── OVERVIEW TAB ── -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="prof-card">
                        <div class="prof-card-header">
                            <h5><i class="bi bi-person-fill me-2" style="color:var(--forest,#0f2e25);opacity:0.6;"></i>Personal Information</h5>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?= htmlspecialchars($member['full_name']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">National ID / Passport</div>
                            <div class="info-value"><?= $member['national_id'] ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?= $member['email'] ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?= $member['phone'] ?></div>
                        </div>
                        <?php if (!empty($member['address'])): ?>
                        <div class="info-row">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?= htmlspecialchars($member['address']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($member['occupation'])): ?>
                        <div class="info-row">
                            <div class="info-label">Occupation</div>
                            <div class="info-value"><?= htmlspecialchars($member['occupation']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($member['next_of_kin_name'])): ?>
                        <div class="info-row">
                            <div class="info-label">Next of Kin</div>
                            <div class="info-value"><?= htmlspecialchars($member['next_of_kin_name']) ?>
                                <?php if ($member['next_of_kin_phone']): ?>
                                <span style="color:#9ca3af;font-size:0.8rem;"> · <?= htmlspecialchars($member['next_of_kin_phone']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <hr style="border-color:#f3f4f6;margin:18px 0;">
                        <div class="risk-bar-wrap">
                            <div class="risk-bar-label">
                                <span>Risk Profile</span>
                                <span class="risk-val">Low Risk</span>
                            </div>
                            <div class="risk-bar">
                                <div class="risk-bar-fill" style="width:85%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="prof-card p-0 overflow-hidden">
                        <div class="prof-card-header" style="padding:22px 24px 18px;">
                            <h5><i class="bi bi-clock-history me-2" style="color:var(--forest,#0f2e25);opacity:0.6;"></i>Recent Activity</h5>
                            <a href="payments.php?search=<?= urlencode($member['full_name']) ?>"
                               class="btn btn-sm rounded-pill px-4 fw-bold"
                               style="background:#f3f4f6;color:#374151;border:none;font-size:0.8rem;">
                               View All <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="prof-table">
                                <thead>
                                    <tr>
                                        <th style="padding-left:24px;">Date</th>
                                        <th>Description</th>
                                        <th>Reference</th>
                                        <th style="text-align:right;padding-right:24px;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_txns)): foreach ($recent_txns as $tx):
                                        $is_credit = in_array($tx['transaction_type'], ['deposit','repayment','income','loan_repayment','share_capital']);
                                    ?>
                                    <tr>
                                        <td style="padding-left:24px;">
                                            <div style="font-weight:600;font-size:0.83rem;color:#374151;"><?= date('d M, Y', strtotime($tx['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:700;font-size:0.875rem;color:#111827;"><?= ucwords(str_replace('_', ' ', $tx['transaction_type'])) ?></div>
                                            <div style="font-size:0.75rem;color:#9ca3af;"><?= htmlspecialchars($tx['notes']) ?></div>
                                        </td>
                                        <td style="font-size:0.8rem;font-weight:600;color:#6b7280;"><?= $tx['reference_no'] ?></td>
                                        <td style="text-align:right;padding-right:24px;" class="<?= $is_credit ? 'txn-credit' : 'txn-debit' ?>">
                                            <?= $is_credit ? '+' : '−' ?> <?= number_format((float)$tx['amount']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="empty-state" style="padding:40px 24px;">
                                                <div class="empty-icon"><i class="bi bi-receipt"></i></div>
                                                <p style="color:#9ca3af;font-size:0.875rem;">No recent transactions.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── LOANS TAB ── -->
        <div class="tab-pane fade" id="loans" role="tabpanel">
            <div class="prof-card p-0 overflow-hidden">
                <div class="prof-card-header" style="padding:22px 24px 18px;">
                    <h5><i class="bi bi-bank2 me-2" style="color:var(--forest,#0f2e25);opacity:0.6;"></i>Loan History</h5>
                    <button class="btn btn-sm rounded-pill px-4 fw-bold btn-forest">
                        Apply For Loan
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="prof-table">
                        <thead>
                            <tr>
                                <th style="padding-left:24px;">Type</th>
                                <th>Principal</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th style="padding-right:24px;text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($member_loans)): foreach ($member_loans as $l):
                                $loan_status = in_array($l['status'], ['disbursed','active']) ? 'disbursed' : ($l['status'] === 'pending' ? 'pending' : 'closed');
                            ?>
                            <tr>
                                <td style="padding-left:24px;">
                                    <div style="font-weight:700;color:#111827;"><?= $l['loan_type'] ?></div>
                                    <div style="font-size:0.75rem;color:#9ca3af;"><?= $l['interest_rate'] ?>% Interest</div>
                                </td>
                                <td style="font-weight:700;color:#374151;">KES <?= number_format((float)$l['amount']) ?></td>
                                <td style="font-weight:700;color:var(--forest,#0f2e25);">KES <?= number_format((float)$l['current_balance']) ?></td>
                                <td><span class="loan-badge <?= $loan_status ?>"><?= strtoupper($l['status']) ?></span></td>
                                <td style="font-size:0.8rem;color:#6b7280;"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                                <td style="text-align:right;padding-right:24px;">
                                    <a href="loans.php?id=<?= $l['loan_id'] ?>"
                                       style="width:30px;height:30px;border-radius:8px;background:#f3f4f6;border:1px solid #e5e7eb;color:#6b7280;display:inline-flex;align-items:center;justify-content:center;font-size:0.85rem;text-decoration:none;">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="bi bi-bank"></i></div>
                                        <h5>No Loan History</h5>
                                        <p>This member has no loan records yet.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── TRANSACTIONS TAB ── -->
        <div class="tab-pane fade" id="txns" role="tabpanel">
            <div class="prof-card">
                <div class="txn-info-panel">
                    <i class="bi bi-bar-chart-line"></i>
                    <p>Use the Financial Intelligence reports for advanced filtering and full ledger access.</p>
                    <a href="payments.php?search=<?= urlencode($member['full_name']) ?>"
                       class="btn btn-forest rounded-pill px-5 fw-bold">
                        <i class="bi bi-journal-text me-2"></i>Go to Payments Ledger
                    </a>
                </div>
            </div>
        </div>

        <!-- ── KYC TAB ── -->
        <div class="tab-pane fade" id="kyc" role="tabpanel">

            <!-- Upload Panel -->
            <div class="kyc-upload-panel">
                <h6><i class="bi bi-cloud-upload-fill" style="color:var(--forest,#0f2e25);"></i> Upload Document for Member</h6>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Document Type</label>
                            <select name="doc_type" class="form-select" required>
                                <option value="national_id_front">National ID (Front)</option>
                                <option value="national_id_back">National ID (Back)</option>
                                <option value="passport_photo">Passport Photo</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Select File (PDF, JPG, PNG · Max 5MB)</label>
                            <input type="file" name="kyc_upload" class="form-control" required accept="image/jpeg,image/png,application/pdf">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-forest w-100 fw-bold rounded-3">
                                <i class="bi bi-upload me-2"></i>Upload &amp; Verify
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Doc Cards -->
            <?php if (!empty($member_docs)): ?>
            <div class="row g-3">
                <?php foreach ($member_docs as $doc):
                    $doc_status = $doc['status'] ?? 'pending';
                ?>
                <div class="col-md-6">
                    <div class="kyc-doc-card">
                        <div class="kyc-doc-top">
                            <span class="kyc-doc-title">
                                <i class="bi bi-file-earmark me-1"></i>
                                <?= str_replace('_', ' ', $doc['document_type']) ?>
                            </span>
                            <span class="kyc-doc-status <?= $doc_status ?>">
                                <?= strtoupper($doc_status) ?>
                            </span>
                        </div>

                        <div class="kyc-doc-meta">
                            <div class="kyc-doc-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                            <div style="flex:1;min-width:0;">
                                <div class="kyc-doc-filename"><?= htmlspecialchars($doc['file_path']) ?></div>
                                <div class="kyc-doc-date">Uploaded: <?= date('d M Y', strtotime($doc['uploaded_at'])) ?></div>
                            </div>
                            <a href="<?= BASE_URL ?>/uploads/kyc/<?= $doc['file_path'] ?>" target="_blank"
                               style="flex-shrink:0;font-size:0.78rem;font-weight:700;color:var(--forest,#0f2e25);text-decoration:none;background:rgba(15,46,37,0.07);padding:5px 12px;border-radius:7px;">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </div>

                        <?php if ($doc_status === 'pending'): ?>
                        <div class="kyc-action-row">
                            <form method="POST" class="d-flex align-items-center gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="doc_id" value="<?= $doc['document_id'] ?>">
                                <input type="text" name="verification_notes" class="form-control form-control-sm flex-grow-1" placeholder="Notes (required if rejecting)">
                                <button type="submit" name="kyc_action" value="verify"
                                        style="font-size:0.78rem;font-weight:700;padding:6px 14px;border-radius:7px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;cursor:pointer;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;">
                                    <i class="bi bi-check-lg me-1"></i>Verify
                                </button>
                                <button type="submit" name="kyc_action" value="reject"
                                        style="font-size:0.78rem;font-weight:700;padding:6px 14px;border-radius:7px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;cursor:pointer;white-space:nowrap;font-family:'Plus Jakarta Sans',sans-serif;">
                                    <i class="bi bi-x-lg me-1"></i>Reject
                                </button>
                            </form>
                        </div>
                        <?php elseif ($doc['verification_notes']): ?>
                        <div class="kyc-notes">
                            <strong>Admin Notes:</strong> <?= esc($doc['verification_notes'] ?? '') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="prof-card">
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-shield-exclamation"></i></div>
                    <h5>No KYC Documents Found</h5>
                    <p>The member hasn't uploaded any verification documents yet. Use the panel above to upload on their behalf.</p>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /kyc tab -->

    </div><!-- /tab-content -->

    </div><!-- /container-fluid -->
    <?php $layout->footer(); ?>
</div><!-- /main-content-wrapper -->
</body>
</html>