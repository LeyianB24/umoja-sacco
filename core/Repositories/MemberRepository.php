<?php
declare(strict_types=1);
/**
 * core/Repositories/MemberRepository.php
 * USMS\Repositories\MemberRepository — Member data access.
 *
 * Example concrete repository extending BaseRepository.
 * Provides member-specific query methods on top of the standard CRUD.
 */

namespace USMS\Repositories;

class MemberRepository extends BaseRepository
{
    protected string $table      = 'members';
    protected string $primaryKey = 'member_id';

    /**
     * Find a member by their national ID.
     */
    public function findByNationalId(string $nationalId): ?array
    {
        return $this->findBy('national_id', $nationalId);
    }

    /**
     * Search members by name, national ID, or phone.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, int $limit = 20): array
    {
        $q = '%' . $query . '%';
        $stmt = $this->db->prepare(
            "SELECT member_id, full_name, national_id, phone, status
             FROM members
             WHERE full_name LIKE ? OR national_id LIKE ? OR phone LIKE ?
             ORDER BY full_name ASC
             LIMIT ?"
        );
        $stmt->bind_param('sssi', $q, $q, $q, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get active member count.
     */
    public function countActive(): int
    {
        return $this->count(['status' => 'active']);
    }

    /**
     * Get all active members (for dropdowns / exports).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getActive(): array
    {
        return $this->all(['status' => 'active'], 'full_name', 'ASC');
    }

    /**
     * Get member balances summary.
     * Note: In this system, balances are derived via getMemberSavings() helper.
     */
    public function getBalanceSummary(int $memberId): array
    {
        // This repository currently doesn't implement derived balance joins.
        // Returning ID for now to satisfy the interface.
        return ['member_id' => $memberId];
    }
}
