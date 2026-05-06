<?php
declare(strict_types=1);

namespace USMS\Database;

/**
 * Migration system for managing database schema versions.
 * Each migration is a timestamped PHP file with up/down methods.
 */
class MigrationRunner
{
    private \mysqli $conn;
    private string $migrationsPath;
    
    public function __construct(\mysqli $conn, string $migrationsPath = __DIR__ . '/../../database/migrations')
    {
        $this->conn = $conn;
        $this->migrationsPath = $migrationsPath;
        $this->createMigrationsTable();
    }
    
    /**
     * Create migrations tracking table if it doesn't exist
     */
    private function createMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $this->conn->query($sql);
    }
    
    /**
     * Run all pending migrations
     */
    public function migrate(): array
    {
        $executed = [];
        $failed = [];
        $batch = $this->getNextBatchNumber();
        
        $files = $this->getPendingMigrations();
        
        foreach ($files as $file) {
            try {
                $this->runMigration($file, $batch);
                $executed[] = $file;
            } catch (\Exception $e) {
                $failed[$file] = $e->getMessage();
            }
        }
        
        return [
            'executed' => $executed,
            'failed' => $failed,
            'batch' => $batch
        ];
    }
    
    /**
     * Rollback last batch of migrations
     */
    public function rollback(): array
    {
        $result = $this->conn->query("SELECT MAX(batch) as batch FROM migrations");
        $batch = (int)$result->fetch_assoc()['batch'];
        
        if ($batch === 0) {
            return ['success' => false, 'message' => 'No migrations to rollback'];
        }
        
        $result = $this->conn->query("SELECT migration FROM migrations WHERE batch = $batch ORDER BY id DESC");
        $migrations = [];
        while ($row = $result->fetch_assoc()) {
            $migrations[] = $row['migration'];
        }
        
        $rolled_back = [];
        foreach ($migrations as $migration) {
            $file = $this->migrationsPath . '/' . $migration . '.php';
            if (file_exists($file)) {
                $this->runRollback($file);
                $this->conn->query("DELETE FROM migrations WHERE migration = '$migration'");
                $rolled_back[] = $migration;
            }
        }
        
        return [
            'success' => true,
            'rolled_back' => $rolled_back,
            'batch' => $batch
        ];
    }
    
    /**
     * Get migration status
     */
    public function status(): array
    {
        $result = $this->conn->query("SELECT * FROM migrations ORDER BY batch, id");
        $executed = [];
        
        while ($row = $result->fetch_assoc()) {
            $executed[] = $row['migration'];
        }
        
        $all = $this->getAllMigrations();
        $pending = array_diff($all, $executed);
        
        return [
            'executed' => $executed,
            'pending' => array_values($pending),
            'total' => count($all),
            'batches' => $result->num_rows > 0 
                ? (int)$this->conn->query("SELECT MAX(batch) as max FROM migrations")->fetch_assoc()['max']
                : 0
        ];
    }
    
    /**
     * Get all migration files
     */
    private function getAllMigrations(): array
    {
        $migrations = [];
        $files = glob($this->migrationsPath . '/*.php');
        
        foreach ($files as $file) {
            $migrations[] = pathinfo($file, PATHINFO_FILENAME);
        }
        
        sort($migrations);
        return $migrations;
    }
    
    /**
     * Get pending migrations (not yet executed)
     */
    private function getPendingMigrations(): array
    {
        $result = $this->conn->query("SELECT migration FROM migrations");
        $executed = [];
        
        while ($row = $result->fetch_assoc()) {
            $executed[] = $row['migration'];
        }
        
        $all = $this->getAllMigrations();
        return array_diff($all, $executed);
    }
    
    /**
     * Run single migration
     */
    private function runMigration(string $name, int $batch): void
    {
        $file = $this->migrationsPath . '/' . $name . '.php';
        
        if (!file_exists($file)) {
            throw new \Exception("Migration file not found: $file");
        }
        
        // Include and execute migration
        $class = $this->getMigrationClass($name);
        include_once $file;
        
        if (!class_exists($class)) {
            throw new \Exception("Migration class not found: $class");
        }
        
        $migration = new $class();
        $migration->up($this->conn);
        
        // Record migration
        $stmt = $this->conn->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->bind_param('si', $name, $batch);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Run migration rollback
     */
    private function runRollback(string $file): void
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $class = $this->getMigrationClass($name);
        
        include_once $file;
        
        if (!class_exists($class)) {
            throw new \Exception("Migration class not found: $class");
        }
        
        $migration = new $class();
        $migration->down($this->conn);
    }
    
    /**
     * Convert filename to class name
     */
    private function getMigrationClass(string $filename): string
    {
        $parts = explode('_', $filename, 2);
        $name = end($parts);
        
        return 'USMS\\Database\\Migrations\\' . ucfirst(str_replace('_', '', ucwords($name, '_')));
    }
    
    /**
     * Get next batch number
     */
    private function getNextBatchNumber(): int
    {
        $result = $this->conn->query("SELECT MAX(batch) as max FROM migrations");
        $max = (int)$result->fetch_assoc()['max'];
        return $max + 1;
    }
}

/**
 * Base migration class - extend this for each migration
 */
abstract class Migration
{
    abstract public function up(\mysqli $conn): void;
    abstract public function down(\mysqli $conn): void;
}
