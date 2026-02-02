<?php
// admin/auth_check.php
// Consolidated Administrative Security Gatekeeper

require_once __DIR__ . '/../../inc/auth.php';

// 1. Mandatory Admin Check
require_admin();

// 2. Dynamic Permission Check
// Usage: Define $permission_required BEFORE including this file
if (isset($permission_required)) {
    require_permission($permission_required);
}

// 3. Prevent 'member' role from accessing admin (Redundant but safe)
if (($_SESSION['role'] ?? '') === 'member') {
    header("Location: ../member/pages/dashboard.php");
    exit;
}

