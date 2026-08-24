<?php
/**
 * database/run_migration.php
 * USMS Sacco - Database Migration Runner
 *
 * Runs all numbered migrations in order, tracks which have been applied
 * in the `migrations` DB table, and reports results.
 *
 * Usage (CLI):  php database/run_migration.php
 *               php database/run_migration.php --seeds     (run seeds only)
 *               php database/run_migration.php --all       (migrations + seeds)
 *               php database/run_migration.php --rollback  (show applied list)
 *
 * Usage (Web):  Wrap in a CLI-only guard and run via terminal for safety.
 */
declare(strict_types=1);

// ─── Security Guard ──────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Migration runner is CLI-only.");
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/app.php';

$args      = $argv ?? [];
$runSeeds  = in_array('--seeds', $args) || in_array('--all', $args);
$runMigs   = !in_array('--seeds', $args);
$rollback  = in_array('--rollback', $args);

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║    USMS Sacco — Database Migration Runner    ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// ─── Ensure migrations tracking table exists ─────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS _migrations (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        filename    VARCHAR(255) NOT NULL UNIQUE,
        batch       INT NOT NULL DEFAULT 1,
        applied_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ─── Rollback / status mode ───────────────────────────────────────────────────
if ($rollback) {
    $res = $conn->query("SELECT filename, batch, applied_at FROM _migrations ORDER BY id");
    echo "Applied Migrations:\n";
    echo str_repeat('-', 60) . "\n";
    $count = 0;
    while ($row = $res->fetch_assoc()) {
        echo sprintf("  [Batch %d] %s  (%s)\n", $row['batch'], $row['filename'], $row['applied_at']);
        $count++;
    }
    echo "\nTotal applied: $count\n\n";
    exit(0);
}

// ─── Helper: get next batch number ───────────────────────────────────────────
$batchResult = $conn->query("SELECT MAX(batch) as max_batch FROM _migrations");
$maxBatch    = (int)($batchResult->fetch_assoc()['max_batch'] ?? 0);
$currentBatch = $maxBatch + 1;

// ─── Helper: run a SQL file ───────────────────────────────────────────────────
function runSqlFile(mysqli $conn, string $filepath, string $filename, int $batch): array
{
    $sql = file_get_contents($filepath);
    if ($sql === false) {
        return ['success' => 0, 'errors' => 1, 'messages' => ["✗ Could not read: $filename"]];
    }

    // Split on semicolons, skip pure comments/empty blocks
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !preg_match('/^(--.*)?\s*$/m', $s)
    );

    $success  = 0;
    $errors   = 0;
    $messages = [];

    foreach ($statements as $stmt) {
        if (empty(trim($stmt))) continue;
        try {
            if ($conn->query($stmt)) {
                $success++;
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $stmt, $m)) {
                    $messages[] = "  ✓ Created table: {$m[1]}";
                } elseif (preg_match('/ALTER TABLE.*?`?(\w+)`?/i', $stmt, $m)) {
                    $messages[] = "  ✓ Altered table: {$m[1]}";
                } elseif (preg_match('/^INSERT/i', $stmt)) {
                    $messages[] = "  ✓ Seeded data (" . $conn->affected_rows . " rows)";
                }
            } else {
                throw new RuntimeException($conn->error);
            }
        } catch (RuntimeException $e) {
            $errors++;
            $messages[] = "  ✗ Error: " . $e->getMessage();
        }
    }

    // Record in _migrations (only for migrations, not seeds re-runs)
    if ($errors === 0) {
        $stmt = $conn->prepare("INSERT IGNORE INTO _migrations (filename, batch) VALUES (?, ?)");
        $stmt->bind_param('si', $filename, $batch);
        $stmt->execute();
    }

    return compact('success', 'errors', 'messages');
}

// ─── Get already-applied migrations ──────────────────────────────────────────
$appliedRes = $conn->query("SELECT filename FROM _migrations");
$applied = [];
while ($row = $appliedRes->fetch_assoc()) {
    $applied[] = $row['filename'];
}

$totalSuccess = 0;
$totalErrors  = 0;

// ─── Run Migrations ───────────────────────────────────────────────────────────
if ($runMigs) {
    $migDir   = __DIR__ . '/migrations';
    $migFiles = glob($migDir . '/*.sql');
    sort($migFiles);

    echo "📦 Migrations  (Batch #$currentBatch)\n";
    echo str_repeat('─', 50) . "\n";

    $newMigs = 0;
    foreach ($migFiles as $filepath) {
        $filename = basename($filepath);

        if (in_array($filename, $applied)) {
            echo "  ⊙ Skipped (already applied): $filename\n";
            continue;
        }

        echo "  ▶ Applying: $filename\n";
        $result = runSqlFile($conn, $filepath, $filename, $currentBatch);

        foreach ($result['messages'] as $msg) echo "$msg\n";
        $totalSuccess += $result['success'];
        $totalErrors  += $result['errors'];
        $newMigs++;
    }

    if ($newMigs === 0) {
        echo "  ✅ Nothing to migrate — all up to date.\n";
    }
    echo "\n";
}

// ─── Run Seeds ────────────────────────────────────────────────────────────────
if ($runSeeds) {
    $seedDir   = __DIR__ . '/seeds';
    $seedFiles = glob($seedDir . '/*.sql');
    sort($seedFiles);

    echo "🌱 Seeds\n";
    echo str_repeat('─', 50) . "\n";

    if (empty($seedFiles)) {
        echo "  ⊙ No seed files found.\n";
    }

    foreach ($seedFiles as $filepath) {
        $filename = basename($filepath);
        echo "  ▶ Seeding: $filename\n";
        $result = runSqlFile($conn, $filepath, "seed_$filename", $currentBatch);
        foreach ($result['messages'] as $msg) echo "$msg\n";
        $totalSuccess += $result['success'];
        $totalErrors  += $result['errors'];
    }
    echo "\n";
}

// ─── Summary ──────────────────────────────────────────────────────────────────
echo str_repeat('═', 50) . "\n";
echo "  ✅ Statements executed: $totalSuccess\n";
if ($totalErrors > 0) {
    echo "  ⚠️  Errors encountered: $totalErrors\n";
} else {
    echo "  🎉 No errors — everything applied cleanly!\n";
}
echo str_repeat('═', 50) . "\n\n";

exit($totalErrors > 0 ? 1 : 0);
