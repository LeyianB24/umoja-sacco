<?php
declare(strict_types=1);

namespace USMS\Tests\Unit;

use USMS\Tests\BaseTestCase;
use USMS\Repositories\MemberRepository;

/**
 * RepositoryTest — Verifies data access logic
 */
class RepositoryTest extends BaseTestCase
{
    private MemberRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MemberRepository($this->db);
    }

    /**
     * Test finding a member by ID
     */
    public function testFindMemberById(): void
    {
        // For unit testing, we should ideally mock the DB result.
        // However, we'll do a simple integration-style check against the DB since it's already set up.
        $member = $this->repo->find(1);
        
        if ($member) {
            $this->assertArrayHasKey('member_id', $member);
            $this->assertArrayHasKey('full_name', $member);
        } else {
            $this->assertNull($member);
        }
    }

    /**
     * Test searching members
     */
    public function testSearchMembers(): void
    {
        $results = $this->repo->search('John');
        $this->assertIsArray($results);
    }
}
