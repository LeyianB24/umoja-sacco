<?php
declare(strict_types=1);

namespace USMS\Middleware;

use USMS\Database\Database;

/**
 * USMS\Middleware\Auth
 * The Security Gatekeeper - V25 Master Build
 */
class Auth {
    /**
     * Cache all permissions for the logged-in role
     */
    public static function loadPermissions($role_id) {
        $db = Database::getInstance()->getPdo();
        
        $sql = "SELECT p.slug FROM permissions p 
                JOIN role_permissions rp ON p.id = rp.permission_id 
                WHERE rp.role_id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$role_id]);
        
        $permissions = [];
        while ($row = $stmt->fetch()) {
            $permissions[] = $row['slug'];
        }
        
        // Superadmin Override: If role_id is 1, give them ALL permissions dynamically
        if ($role_id == 1) {
            $all = $db->query("SELECT slug FROM permissions");
            while($r = $all->fetch()) $permissions[] = $r['slug'];
            $permissions = array_unique($permissions);
        }
        
        $_SESSION['permissions'] = $permissions;
        return true;
    }

    /**
     * The Brain: Check if user has permission
     */
    public static function can($slug) {
        // Always ensure permissions are loaded for the current session/request
        if (!isset($_SESSION['permissions']) || empty($_SESSION['permissions'])) {
            if (isset($_SESSION['role_id'])) {
                self::loadPermissions($_SESSION['role_id']);
            } else {
                return false;
            }
        }
        
        // RELOAD CHECK: If we suspect stale data, we could force reload here. 
        // But for now, let's rely on the calling page to have handled the session start.
        // To be absolutely sure "Role Matrix" updates work immediately:
        // We will reload if the session setup time is older than the last permission update? 
        // Simplest: Just reload every time. It's a few ms query.
        if (isset($_SESSION['role_id'])) {
             self::loadPermissions($_SESSION['role_id']);
        }

        // Strict Check: Is the slug in our "Passport"?
        return in_array($slug, $_SESSION['permissions'] ?? []);
    }

    /**
     * Middlewares
     */
    public static function requireAdmin() {
        if (!isset($_SESSION['admin_id'])) {
            \USMS\Http\ErrorHandler::abort(401, "Unauthorized: Admin session required.");
        }
    }

    public static function requirePermission($slug = null) {
        if ($slug === null) {
            $slug = basename($_SERVER['PHP_SELF']);
        }
        self::requireAdmin();
        if (!self::can($slug)) {
            \USMS\Http\ErrorHandler::abort(403, "Access Denied: You do not have permission to access [$slug].");
        }
    }

    /**
     * V22: The Superadmin Gatekeeper
     */
    public static function requireSuperAdmin() {
        self::requireAdmin();
        if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
            // Log the attempt
            error_log("Unauthorized access attempt to System Files by Admin ID: " . ($_SESSION['admin_id'] ?? 'Unknown'));
            \USMS\Http\ErrorHandler::abort(403, "Restricted Area: Superadmin Access Only.");
        }
    }

    /**
     * Terminate Session
     */
    public static function logout($redirect_url = 'login.php') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Unset all session variables
        $_SESSION = array();

        // Destroy the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destroy the session
        session_destroy();

        // Redirect
        header("Location: " . $redirect_url);
        exit;
    }
}
?>

