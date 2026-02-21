<?php
declare(strict_types=1);

namespace USMS\Tests;

use PHPUnit\Framework\TestCase;

/**
 * BaseTestCase — Core testing infrastructure for USMS
 */
abstract class BaseTestCase extends TestCase
{
    protected \mysqli $db;

    protected function setUp(): void
    {
        parent::setUp();
        
        global $conn;
        if (!$conn instanceof \mysqli) {
             throw new \RuntimeException("Database connection not initialized. Check tests/bootstrap.php");
        }
        $this->db = $conn;
    }

    /**
     * Helper to create a mock database connection if needed
     */
    protected function getMockDb()
    {
        return $this->createMock(\mysqli::class);
    }
}
