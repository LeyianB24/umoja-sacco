<?php
// usms/inc/auth.php
// Central Authentication & Authorization Helper for Umoja Sacco System

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and DB connection
if (!defined('BASE_URL')) {
    @include_once __DIR__ . '/../config/app_config.php';
}
if (!isset($conn)) {
    @include_once __DIR__ . '/../config/db_connect.php';
}

/**
 * Attempt to restore session from remember-me cookie.
 */
function restore_session_from_cookie()
{
    global $conn;

    if (empty($_COOKIE['usms_rem']) || !$conn) return false;

    $token = $_COOKIE['usms_rem'];
    $token_hash = hash('sha256', $token);

    // Try member session restore
    $sql = "SELECT member_id, full_name FROM members
            WHERE remember_token = ? AND remember_expires > NOW() LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $token_hash);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $_SESSION['member_id'] = (int)$row['member_id'];
        $_SESSION['member_name'] = $row['full_name'];
        $_SESSION['role'] = 'member';
        return true;
    }

    // Try admin session restore
    $sql = "SELECT admin_id, full_name, role FROM admins
            WHERE remember_token = ? AND remember_expires > NOW() LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $token_hash);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $_SESSION['admin_id'] = (int)$row['admin_id'];
        $_SESSION['admin_name'] = $row['full_name'];
        $_SESSION['role'] = strtolower(trim($row['role']));
        return true;
    }

    return false;
}

/**
 * Auto-restore session if user has a valid remember-me cookie
 */
if (
    empty($_SESSION['member_id']) &&
    empty($_SESSION['admin_id']) &&
    !empty($_COOKIE['usms_rem'])
) {
    restore_session_from_cookie();
}

/**
 * Enforce member authentication
 */
function require_member()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['member_id'])) {
        if (!restore_session_from_cookie()) {
            header("Location: ../public/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
}

/**
 * Enforce admin authentication
 */
function require_admin()
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) {
        if (!restore_session_from_cookie()) {
            header("Location: ../public/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
}

/**
 * Check role for current admin
 */
function is_admin_role($role)
{
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === strtolower($role);
}

/**
 * Require specific admin roles
 */
function require_superadmin()
{
    require_admin();
    if (!is_admin_role('superadmin')) {
        header("Location: ../public/login.php?error=unauthorized");
        exit;
    }
}

function require_manager()
{
    require_admin();
    if (!is_admin_role('manager')) {
        header("Location: ../public/login.php?error=unauthorized");
        exit;
    }
}

function require_accountant()
{
    require_admin();
    if (!is_admin_role('accountant')) {
        header("Location: ../public/login.php?error=unauthorized");
        exit;
    }
}

/**
 * Logout and clear sessions
 */
function logout_and_redirect($redirect = null)
{
    global $conn;

    if (session_status() === PHP_SESSION_NONE) session_start();

    $cookie_name = 'usms_rem';
    $token = $_COOKIE[$cookie_name] ?? '';

    if ($token && isset($conn)) {
        $token_hash = hash('sha256', $token);
        $queries = [
            "UPDATE members SET remember_token = NULL, remember_expires = NULL WHERE remember_token = ?",
            "UPDATE admins  SET remember_token = NULL, remember_expires = NULL WHERE remember_token = ?"
        ];
        foreach ($queries as $q) {
            if ($stmt = $conn->prepare($q)) {
                $stmt->bind_param('s', $token_hash);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Clear session + cookies
    setcookie($cookie_name, '', time() - 3600, '/', '', false, true);
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    // Redirect
    $redirect ??= '../public/login.php';
    header("Location: $redirect");
    exit;
}

/**
 * Debug helper (optional)
 */
function whoami()
{
    if (isset($_SESSION['role'])) {
        return ucfirst($_SESSION['role']);
    }
    return 'Guest';
}
?>