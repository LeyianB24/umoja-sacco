<?php
// bin/health_check.php
// Usage: php health_check.php [--clean-temp]
// Monitors email_errors.log and leftover temp files

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line." . PHP_EOL;
    exit(1);
}

$cleanTemp = in_array('--clean-temp', $argv);
$logFile = __DIR__ . '/../storage/email_errors.log';
$tempDir = sys_get_temp_dir();

echo "=== USMS Email Health Check ===" . PHP_EOL;
echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// 1. Check email errors log
if (file_exists($logFile)) {
    $size = filesize($logFile);
    $lines = count(file($logFile));
    echo "[Log] $logFile" . PHP_EOL;
    echo "  Size: " . number_format($size) . " bytes" . PHP_EOL;
    echo "  Lines: $lines" . PHP_EOL;
    
    if ($lines > 0) {
        echo "  Last 3 errors:" . PHP_EOL;
        $errors = array_slice(file($logFile), -3);
        foreach ($errors as $err) {
            echo "    " . trim($err) . PHP_EOL;
        }
    }
} else {
    echo "[Log] $logFile — NOT FOUND (no errors yet)" . PHP_EOL;
}

echo PHP_EOL;

// 2. Check for leftover temp files
$pattern = 'usms_email_*';
$tempFiles = glob($tempDir . '/' . $pattern);
$activeCount = 0;
$staleCount = 0;
$now = time();

if (count($tempFiles) > 0) {
    echo "[Temp] Found " . count($tempFiles) . " temp file(s):" . PHP_EOL;
    foreach ($tempFiles as $file) {
        $age = $now - filemtime($file);
        if ($age > 300) {
            // > 5 min old = stale
            echo "  [STALE] " . basename($file) . " (age: " . $age . "s)" . PHP_EOL;
            $staleCount++;
            if ($cleanTemp) {
                @unlink($file);
                echo "    → Deleted" . PHP_EOL;
            }
        } else {
            echo "  [ACTIVE] " . basename($file) . " (age: " . $age . "s)" . PHP_EOL;
            $activeCount++;
        }
    }
} else {
    echo "[Temp] No temp files found (healthy)" . PHP_EOL;
}

echo PHP_EOL;

// 3. Summary
echo "=== Summary ===" . PHP_EOL;
echo "Temp files - Active: $activeCount, Stale: $staleCount" . PHP_EOL;
echo "Log file - Errors: " . (file_exists($logFile) ? count(file($logFile)) : 0) . PHP_EOL;

if ($staleCount > 5 || count(file($logFile) ?? []) > 100) {
    echo "[WARNING] High error count detected. Review and clean manually if needed." . PHP_EOL;
    exit(1);
}

echo "[OK] Email system health is good." . PHP_EOL;
exit(0);
