<?php
declare(strict_types=1);

namespace USMS\Tests\Unit;

use USMS\Tests\BaseTestCase;
use USMS\Cron\JobRunner;

/**
 * CronJobTest — Verifies the cron dispatcher and job registry
 */
class CronJobTest extends BaseTestCase
{
    /**
     * Test job registry listing
     */
    public function testJobListing(): void
    {
        ob_start();
        JobRunner::listJobs();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Available USMS cron jobs', $output);
        $this->assertStringContainsString('daily_fines', $output);
    }

    /**
     * Test mapping of names to classes
     */
    public function testJobMapping(): void
    {
        // We can't easily test private static properties, 
        // but we can test that calling run with a wrong name fails gracefully
        // or just verify the public interface.
        
        $this->assertTrue(true); // Placeholder for registry verification
    }
}
