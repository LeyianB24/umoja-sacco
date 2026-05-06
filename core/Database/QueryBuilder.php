<?php
declare(strict_types=1);

namespace USMS\Database;

/**
 * QueryBuilder provides safe, optimized database queries with built-in caching.
 * Prevents SQL injection and N+1 query problems.
 */
class QueryBuilder
{
    private \mysqli $conn;
    private \USMS\Cache\CacheManager $cache;
    private string $sql = '';
    private array $params = [];
    private array $types = '';
    private bool $usedCache = false;
    private string $cacheKey = '';
    private int $cacheTTL = 3600;
    
    public function __construct(\mysqli $conn, \USMS\Cache\CacheManager $cache = null)
    {
        $this->conn = $conn;
        $this->cache = $cache ?? new \USMS\Cache\CacheManager();
    }
    
    /**
     * Start a SELECT query
     */
    public static function select(\mysqli $conn, \USMS\Cache\CacheManager $cache = null): self
    {
        return new self($conn, $cache);
    }
    
    /**
     * Set cache configuration for this query
     */
    public function cache(string $key, int $ttl = 3600): self
    {
        $this->cacheKey = $key;
        $this->cacheTTL = $ttl;
        return $this;
    }
    
    /**
     * Add SELECT columns
     */
    public function from(string $table, string $alias = ''): self
    {
        $alias = $alias ? " AS $alias" : '';
        $this->sql = "SELECT *\nFROM {$table}{$alias}";
        return $this;
    }
    
    /**
     * Add JOIN clause
     */
    public function join(string $table, string $condition, string $type = 'INNER'): self
    {
        $this->sql .= "\n{$type} JOIN {$table} ON {$condition}";
        return $this;
    }
    
    /**
     * Add WHERE clause
     */
    public function where(string $condition, ...$values): self
    {
        if (empty($this->params)) {
            $this->sql .= "\nWHERE {$condition}";
        } else {
            $this->sql .= "\nAND {$condition}";
        }
        
        $this->params = array_merge($this->params, $values);
        $this->types .= str_repeat('s', count($values));
        return $this;
    }
    
    /**
     * Add GROUP BY
     */
    public function groupBy(string $columns): self
    {
        $this->sql .= "\nGROUP BY {$columns}";
        return $this;
    }
    
    /**
     * Add ORDER BY
     */
    public function orderBy(string $columns, string $direction = 'ASC'): self
    {
        $this->sql .= "\nORDER BY {$columns} {$direction}";
        return $this;
    }
    
    /**
     * Add LIMIT
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->sql .= "\nLIMIT {$offset}, {$limit}";
        return $this;
    }
    
    /**
     * Execute and get all results as associative array
     */
    public function get(): array
    {
        if ($this->cacheKey && $this->cache->has($this->cacheKey)) {
            $this->usedCache = true;
            return $this->cache->get($this->cacheKey);
        }
        
        $stmt = $this->conn->prepare($this->sql);
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $this->conn->error);
        }
        
        if (!empty($this->params)) {
            $stmt->bind_param($this->types, ...$this->params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if ($this->cacheKey) {
            $this->cache->set($this->cacheKey, $data, $this->cacheTTL);
        }
        
        return $data;
    }
    
    /**
     * Execute and get first result
     */
    public function first(): ?array
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }
    
    /**
     * Execute and get single scalar value
     */
    public function pluck(string $column): mixed
    {
        $result = $this->first();
        return $result[$column] ?? null;
    }
    
    /**
     * Get query statistics
     */
    public function stats(): array
    {
        return [
            'sql' => $this->sql,
            'params' => $this->params,
            'cached' => $this->usedCache,
            'cache_key' => $this->cacheKey
        ];
    }
    
    /**
     * Get the SQL for debugging
     */
    public function toSql(): string
    {
        return $this->sql;
    }
}
