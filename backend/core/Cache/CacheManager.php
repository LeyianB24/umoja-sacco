<?php
declare(strict_types=1);

namespace USMS\Cache;

/**
 * Simple cache manager supporting file-based and in-memory caching.
 * Provides a fast, secure way to cache frequently accessed data.
 */
class CacheManager
{
    private static array $memoryCache = [];
    private string $cacheDir;
    private int $defaultTTL;
    
    public function __construct(string $cacheDir = __DIR__ . '/../../storage/cache', int $defaultTTL = 3600)
    {
        $this->cacheDir = $cacheDir;
        $this->defaultTTL = $defaultTTL;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get a cached value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check memory cache first (fastest)
        if (isset(self::$memoryCache[$key])) {
            $entry = self::$memoryCache[$key];
            if ($entry['expires'] > time()) {
                return $entry['value'];
            }
            unset(self::$memoryCache[$key]);
        }
        
        // Check file cache
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            $entry = unserialize(file_get_contents($file));
            if ($entry['expires'] > time()) {
                self::$memoryCache[$key] = $entry;
                return $entry['value'];
            }
            unlink($file);
        }
        
        return $default;
    }
    
    /**
     * Set a cached value
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTTL;
        $entry = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        // Store in memory cache
        self::$memoryCache[$key] = $entry;
        
        // Store in file cache
        $file = $this->getCacheFile($key);
        return (bool)file_put_contents($file, serialize($entry), LOCK_EX);
    }
    
    /**
     * Check if key exists and hasn't expired
     */
    public function has(string $key): bool
    {
        $entry = self::$memoryCache[$key] ?? null;
        if ($entry && $entry['expires'] > time()) {
            return true;
        }
        
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            $entry = unserialize(file_get_contents($file));
            return $entry['expires'] > time();
        }
        
        return false;
    }
    
    /**
     * Delete a cached value
     */
    public function delete(string $key): bool
    {
        unset(self::$memoryCache[$key]);
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function flush(): bool
    {
        self::$memoryCache = [];
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }
    
    /**
     * Get cache file path - sanitized key
     */
    private function getCacheFile(string $key): string
    {
        $filename = hash('sha256', $key) . '.cache';
        return $this->cacheDir . '/' . $filename;
    }
    
    /**
     * Remember pattern - get from cache or compute and cache
     */
    public function remember(string $key, callable $callback, int $ttl = null): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
    
    /**
     * Get cache statistics (memory usage, file count)
     */
    public function stats(): array
    {
        $files = glob($this->cacheDir . '/*.cache');
        $size = 0;
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return [
            'memory_items' => count(self::$memoryCache),
            'file_count' => count($files),
            'file_size_bytes' => $size,
            'file_size_mb' => round($size / 1024 / 1024, 2)
        ];
    }
}
