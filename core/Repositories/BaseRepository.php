<?php
declare(strict_types=1);
/**
 * core/Repositories/BaseRepository.php
 * USMS\Repositories\BaseRepository — Abstract data-access base class.
 *
 * Provides common CRUD helpers for all concrete repositories.
 * Uses the MySQLi connection via the Connection singleton —
 * so it plugs straight into the existing infrastructure.
 *
 * Usage:
 *   class MemberRepository extends BaseRepository {
 *       protected string $table = 'members';
 *       protected string $primaryKey = 'member_id';
 *   }
 *
 *   $repo = new MemberRepository();
 *   $member = $repo->find(42);
 *   $all    = $repo->all(['status' => 'active']);
 */

namespace USMS\Repositories;

use mysqli;
use USMS\Database\Connection;
use InvalidArgumentException;
use RuntimeException;

abstract class BaseRepository
{
    /** The table this repository operates on. Must be set in subclass. */
    protected string $table = '';

    /** The primary key column. */
    protected string $primaryKey = 'id';

    protected mysqli $db;

    public function __construct(?mysqli $connection = null)
    {
        if (empty($this->table)) {
            throw new RuntimeException(static::class . ' must define a $table property.');
        }
        $this->db = $connection ?? Connection::getInstance()->getConnection();
    }

    // ─── Basic Finders ────────────────────────────────────────────────────────

    /**
     * Find a single record by primary key.
     * Returns null if not found.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Find a single record by a column value.
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $this->assertSafeColumn($column);
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1"
        );
        $type = is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        $stmt->bind_param($type, $value);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    /**
     * Get all records, optionally filtered by a WHERE map.
     * Only supports simple equality conditions.
     *
     * @param  array<string,mixed> $where
     * @param  string              $orderBy  Column to order by
     * @param  string              $dir      ASC or DESC
     * @param  int|null            $limit
     * @return array<int,array<string,mixed>>
     */
    public function all(
        array $where = [],
        string $orderBy = '',
        string $dir = 'ASC',
        ?int $limit = null
    ): array {
        $sql    = "SELECT * FROM `{$this->table}`";
        $types  = '';
        $values = [];

        if (!empty($where)) {
            $clauses = [];
            foreach ($where as $col => $val) {
                $this->assertSafeColumn($col);
                $clauses[] = "`{$col}` = ?";
                $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
                $values[] = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        if (!empty($orderBy)) {
            $this->assertSafeColumn($orderBy);
            $dir  = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$orderBy}` {$dir}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->db->prepare($sql);
        if (!empty($values)) {
            $stmt->bind_param($types, ...$values);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Mutations ────────────────────────────────────────────────────────────

    /**
     * Insert a row. Returns the new primary-key ID.
     *
     * @param  array<string,mixed> $data
     */
    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Cannot insert empty data.');
        }

        $columns = array_keys($data);
        foreach ($columns as $col) {
            $this->assertSafeColumn($col);
        }

        $cols   = implode('`, `', $columns);
        $holders = implode(', ', array_fill(0, count($data), '?'));
        $types  = $this->buildTypes(array_values($data));

        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table}` (`{$cols}`) VALUES ({$holders})"
        );
        $stmt->bind_param($types, ...array_values($data));
        $stmt->execute();

        return (int)$this->db->insert_id;
    }

    /**
     * Update a record by primary key.
     *
     * @param  array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $sets   = [];
        $types  = '';
        $values = [];

        foreach ($data as $col => $val) {
            $this->assertSafeColumn($col);
            $sets[]  = "`{$col}` = ?";
            $types  .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
            $values[] = $val;
        }

        $types   .= 'i';
        $values[] = $id;

        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET " . implode(', ', $sets) .
            " WHERE `{$this->primaryKey}` = ?"
        );
        $stmt->bind_param($types, ...$values);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    /**
     * Soft-delete via a `status` column, or hard-delete if no status column.
     */
    public function delete(int $id, bool $soft = true): bool
    {
        if ($soft) {
            $stmt = $this->db->prepare(
                "UPDATE `{$this->table}` SET `status` = 'deleted' WHERE `{$this->primaryKey}` = ?"
            );
        } else {
            $stmt = $this->db->prepare(
                "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?"
            );
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Count all records (with optional WHERE). */
    public function count(array $where = []): int
    {
        $sql    = "SELECT COUNT(*) FROM `{$this->table}`";
        $types  = '';
        $values = [];

        if (!empty($where)) {
            $clauses = [];
            foreach ($where as $col => $val) {
                $this->assertSafeColumn($col);
                $clauses[] = "`{$col}` = ?";
                $types    .= is_int($val) ? 'i' : 's';
                $values[]  = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $stmt = $this->db->prepare($sql);
        if (!empty($values)) {
            $stmt->bind_param($types, ...$values);
        }
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_row()[0];
    }

    /** Check if a record exists by primary key. */
    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    /** Expose the raw connection for complex queries in subclasses. */
    protected function getConnection(): mysqli
    {
        return $this->db;
    }

    // ─── Security ─────────────────────────────────────────────────────────────

    /**
     * Whitelist column names to prevent SQL injection in dynamic queries.
     * Only allows alphanumeric characters and underscores.
     */
    private function assertSafeColumn(string $column): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException("Invalid column name: '{$column}'");
        }
    }

    /**
     * Build a MySQLi type string from an array of values.
     * @param  mixed[] $values
     */
    private function buildTypes(array $values): string
    {
        return implode('', array_map(
            fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'),
            $values
        ));
    }
}
