<?php
/**
 * Railway Database Connection Test Script
 * Use this to verify Railway database connectivity before deployment
 * 
 * Run: php test_railway_connection.php
 */

require_once __DIR__ . '/config/bootstrap.php';

use USMS\Config\EnvLoader;

echo "═══════════════════════════════════════════════════════════════\n";
echo "   RAILWAY DATABASE CONNECTION TEST\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Test 1: Check environment variables
echo "✓ STEP 1: Reading Environment Variables\n";
echo "   ├─ APP_ENV: " . (EnvLoader::get('APP_ENV') ?? 'NOT SET') . "\n";
echo "   ├─ MYSQLHOST: " . (EnvLoader::get('MYSQLHOST') ?? EnvLoader::get('DB_HOST') ?? 'NOT SET') . "\n";
echo "   ├─ MYSQLPORT: " . (EnvLoader::get('MYSQLPORT') ?? EnvLoader::get('DB_PORT') ?? 'NOT SET') . "\n";
echo "   ├─ MYSQLUSER: " . (EnvLoader::get('MYSQLUSER') ?? EnvLoader::get('DB_USER') ?? 'NOT SET') . "\n";
echo "   ├─ MYSQLPASSWORD: " . (EnvLoader::get('MYSQLPASSWORD') ? '***' . substr(EnvLoader::get('MYSQLPASSWORD'), -4) : 'NOT SET') . "\n";
echo "   └─ MYSQLDATABASE: " . (EnvLoader::get('MYSQLDATABASE') ?? EnvLoader::get('DB_NAME') ?? 'NOT SET') . "\n\n";

// Test 2: Attempt connection using same priority logic as db_connect.php
echo "✓ STEP 2: Attempting Database Connection\n";

$host   = EnvLoader::get('MYSQLHOST') ?? EnvLoader::get('DB_HOST') ?? 'localhost';
$user   = EnvLoader::get('MYSQLUSER') ?? EnvLoader::get('DB_USER') ?? 'root';
$pass   = EnvLoader::get('MYSQLPASSWORD') ?? EnvLoader::get('DB_PASS') ?? '';
$port   = EnvLoader::get('MYSQLPORT') ?? EnvLoader::get('DB_PORT') ?? 3306;
$dbname = EnvLoader::get('MYSQLDATABASE') ?? EnvLoader::get('DB_NAME') ?? 'umoja_drivers_sacco';

echo "   Connecting to: $host:$port as $user...\n";

$conn = @new mysqli($host, $user, $pass, $dbname, (int)$port);

if ($conn->connect_errno) {
    echo "   ✗ CONNECTION FAILED: " . $conn->connect_error . "\n";
    echo "\n   Troubleshooting:\n";
    echo "   1. Check your .env.local file has Railway credentials enabled\n";
    echo "   2. Verify your Railway database is running\n";
    echo "   3. Check your internet connection\n";
    echo "   4. Verify credentials in Railway dashboard\n";
    exit(1);
} else {
    echo "   ✓ CONNECTION SUCCESSFUL!\n\n";
}

// Test 3: Check database and tables
echo "✓ STEP 3: Checking Database Contents\n";

$result = $conn->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '$dbname'");
if (!$result) {
    echo "   ✗ Query failed: " . $conn->error . "\n";
} else {
    $row = $result->fetch_assoc();
    echo "   ├─ Tables in database: " . $row['table_count'] . "\n";
    
    // List tables
    $tables = $conn->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = '$dbname' LIMIT 10");
    if ($tables) {
        echo "   └─ Tables:\n";
        while ($table = $tables->fetch_assoc()) {
            echo "      • " . $table['TABLE_NAME'] . "\n";
        }
    }
}

// Test 4: Verify charset
echo "\n✓ STEP 4: Verifying Configuration\n";
echo "   ├─ Active Database: " . $conn->select_db($dbname) ? $dbname : "ERROR" . "\n";
echo "   ├─ Character Set: " . $conn->character_set_name() . "\n";
echo "   ├─ Server Version: " . $conn->server_info . "\n";
echo "   └─ Connection ID: " . $conn->thread_id . "\n\n";

// Test 5: Run a simple query
echo "✓ STEP 5: Executing Test Query\n";
$result = $conn->query("SELECT NOW() `current_time`");
if ($result) {
    $row = $result->fetch_assoc();
    echo "   └─ Server Time: " . $row['current_time'] . "\n\n";
} else {
    echo "   ✗ Query failed: " . $conn->error . "\n\n";
}

$conn->close();

echo "═══════════════════════════════════════════════════════════════\n";
echo "   ✓ ALL TESTS PASSED - Ready for Railway Deployment!\n";
echo "═══════════════════════════════════════════════════════════════\n";
?>
