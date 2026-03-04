<?php
declare(strict_types=1);

namespace USMS\Middleware;

use USMS\Database\Database;
use USMS\Http\ErrorHandler;

/**
 * USMS\Middleware\AuthMiddleware
 * Modern RBAC Layer utilizing system_modules and admin_module_permissions.
 */
class AuthMiddleware {

    /**
     * Ensure user is logged in as an admin/staff.
     */
    public static function requireAdmin(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (!isset($_SESSION['admin_id'])) {
            if (self::isAjaxRequest()) {
                ErrorHandler::jsonError('Unauthorized: Admin session required', 401);
            }
            header("Location: " . BASE_URL . "/public/login.php?error=unauthorized");
            exit;
        }
    }

    /**
     * Ensure user has a specific role.
     */
    public static function requireRole(string $role_slug): void {
        self::requireAdmin();
        
        $current_role = $_SESSION['role_slug'] ?? '';
        if ($current_role !== $role_slug && $current_role !== 'superadmin') {
            ErrorHandler::abort(403, "Access Denied: Required role [$role_slug] missing.");
        }
    }

    /**
     * Check boolean permission for a specific module without aborting.
     */
    public static function hasModulePermission(string $module_slug, string $action = 'view'): bool {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }

        // Superadmin bypass
        if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) {
            return true;
        }

        $role_id = (int)$_SESSION['role_id'];
        $db = Database::getInstance()->getPdo();

        $action_col = match($action) {
            'create' => 'can_create',
            'edit'   => 'can_edit',
            'delete' => 'can_delete',
            default  => 'can_view'
        };

        $sql = "SELECT m.module_id, p.$action_col 
                FROM system_modules m
                JOIN admin_module_permissions p ON m.module_id = p.module_id
                WHERE m.module_slug = ? AND p.role_id = ? AND m.is_active = 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$module_slug, $role_id]);
        $permission = $stmt->fetch();

        return $permission && $permission[$action_col];
    }

    /**
     * Check permission for a specific module and abort on failure.
     * @param string $module_slug The slug of the module (e.g. 'loans', 'finance')
     * @param string $action 'view', 'create', 'edit', 'delete'
     */
    public static function requireModulePermission(string $module_slug, string $action = 'view'): void {
        self::requireAdmin();

        if (!self::hasModulePermission($module_slug, $action)) {
            ErrorHandler::abort(403, "Access Denied: You do not have permission to $action the $module_slug module.");
        }
    }

    /**
     * Helper to detect AJAX for better error reporting.
     */
    private static function isAjaxRequest(): bool {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));
    }
}
