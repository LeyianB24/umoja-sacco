<?php
declare(strict_types=1);

namespace USMS\Services;

/**
 * PasswordMigrationService migrates admin passwords from SHA256 to bcrypt.
 * Maintains backward compatibility during migration period.
 */
class PasswordMigrationService
{
    private \mysqli $conn;
    private bool $logMigrations = true;
    
    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }
    
    /**
     * Check if password needs migration
     */
    public function needsMigration(string $passwordHash): bool
    {
        // SHA256 hashes are 64 characters, bcrypt hashes start with $2
        return strlen($passwordHash) === 64 && !str_starts_with($passwordHash, '$');
    }
    
    /**
     * Verify password during migration (supports both SHA256 and bcrypt)
     */
    public function verifyPassword(string $plainPassword, string $hash): bool
    {
        // Try bcrypt first (new format)
        if (password_verify($plainPassword, $hash)) {
            return true;
        }
        
        // Fall back to SHA256 (old format) during migration
        if ($this->needsMigration($hash)) {
            return hash('sha256', $plainPassword) === $hash;
        }
        
        return false;
    }
    
    /**
     * Migrate single admin password
     */
    public function migrateAdmin(int $adminId, string $plainPassword): bool
    {
        if (!$this->isPlainText($plainPassword)) {
            // Password already hashed, skip
            return false;
        }
        
        $newHash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $this->conn->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
        $stmt->bind_param('si', $newHash, $adminId);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result && $this->logMigrations) {
            $this->logMigration($adminId, 'password_migrated');
        }
        
        return $result;
    }
    
    /**
     * Upgrade password hash on successful login
     * This happens automatically - when admin logs in, hash is upgraded to bcrypt
     */
    public function upgradePasswordOnLogin(int $adminId, string $plainPassword): bool
    {
        $stmt = $this->conn->prepare("SELECT password FROM admin WHERE admin_id = ? LIMIT 1");
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            return false;
        }
        
        $hash = $result['password'];
        
        // If already migrated to bcrypt, no action needed
        if (!$this->needsMigration($hash)) {
            return true;
        }
        
        // Verify password is correct before upgrading
        if (!$this->verifyPassword($plainPassword, $hash)) {
            return false;
        }
        
        // Upgrade to bcrypt
        return $this->migrateAdmin($adminId, $plainPassword);
    }
    
    /**
     * Check if string is plain text (not a hash)
     */
    private function isPlainText(string $value): bool
    {
        // Simple heuristic: plain text is typically shorter than hashes
        // and doesn't match hash patterns
        return strlen($value) < 40 && 
               !preg_match('/^[a-f0-9]{64}$/', $value) &&
               !preg_match('/^\$2/', $value);
    }
    
    /**
     * Migrate all admin passwords from SHA256 to bcrypt
     */
    public function migrateAll(): array
    {
        $stmt = $this->conn->prepare("
            SELECT admin_id, username, password FROM admin
            WHERE LENGTH(password) = 64 AND password NOT LIKE '\$2%'
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $migrated = 0;
        $failed = 0;
        $adminsMigrated = [];
        
        while ($row = $result->fetch_assoc()) {
            // For bulk migration, we cannot verify plain password
            // Instead, mark them for forced password reset
            $adminsMigrated[] = [
                'admin_id' => $row['admin_id'],
                'username' => $row['username'],
                'action' => 'force_reset'
            ];
            $failed++;
        }
        
        $stmt->close();
        
        return [
            'migrated' => $migrated,
            'failed' => $failed,
            'total' => count($adminsMigrated),
            'admins' => $adminsMigrated,
            'note' => 'Bulk migration not supported without plain passwords. Use automatic upgrade on login.'
        ];
    }
    
    /**
     * Get migration status
     */
    public function getStatus(): array
    {
        // Count SHA256 hashes (not migrated)
        $query1 = "
            SELECT COUNT(*) as count FROM admin 
            WHERE LENGTH(password) = 64 AND password NOT LIKE '\$2%'
        ";
        
        // Count bcrypt hashes (migrated)
        $query2 = "
            SELECT COUNT(*) as count FROM admin 
            WHERE password LIKE '\$2%'
        ";
        
        $notMigrated = (int)$this->conn->query($query1)->fetch_assoc()['count'];
        $migrated = (int)$this->conn->query($query2)->fetch_assoc()['count'];
        
        return [
            'migrated' => $migrated,
            'not_migrated' => $notMigrated,
            'total_admins' => $migrated + $notMigrated,
            'migration_progress' => $migrated + $notMigrated > 0 
                ? round(($migrated / ($migrated + $notMigrated)) * 100, 1)
                : 0,
            'status' => $notMigrated === 0 ? 'complete' : 'in_progress'
        ];
    }
    
    /**
     * Log password migration event
     */
    private function logMigration(int $adminId, string $event): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO audit_logs (admin_id, action, details, created_at)
            VALUES (?, 'password_migration', ?, NOW())
        ");
        
        $details = json_encode(['event' => $event, 'timestamp' => time()]);
        $stmt->bind_param('is', $adminId, $details);
        $stmt->execute();
        $stmt->close();
    }
}
