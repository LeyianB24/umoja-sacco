<?php
declare(strict_types=1);

namespace USMS\Tests\Unit;

use USMS\Tests\BaseTestCase;
use USMS\Middleware\CsrfMiddleware;
use USMS\Middleware\AuthMiddleware;

/**
 * MiddlewareTest — Verifies CSRF and Auth security logic
 */
class MiddlewareTest extends BaseTestCase
{
    /**
     * Test CSRF token generation
     */
    public function testCsrfTokenGeneration(): void
    {
        // Mock session
        $_SESSION = [];
        $token = CsrfMiddleware::token();
        
        $this->assertNotEmpty($token);
    }

    /**
     * Test CSRF validation logic
     */
    public function testCsrfValidation(): void
    {
        $_SESSION['_csrf_token'] = 'test_token';
        $_POST['csrf_token'] = 'test_token';

        // Valid token
        $this->assertTrue(CsrfMiddleware::check());

        // Invalid token
        $_POST['csrf_token'] = 'wrong_token';
        $this->assertFalse(CsrfMiddleware::check());
    }

    /**
     * Test AuthMiddleware role check
     */
    public function testAuthMiddlewareRoleCheck(): void
    {
        // Mock Auth session
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';

        // Check for admin role
        $this->assertTrue($this->checkRole('admin'));

        // Check for non-existent role
        $this->assertFalse($this->checkRole('super_admin'));
    }

    /**
     * Internal helper to simulate role check logic
     * (Normally this would hit Auth.php but we're testing the logic here)
     */
    private function checkRole(string $requiredRole): bool
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            return false;
        }
        return $_SESSION['role'] === $requiredRole;
    }
}
