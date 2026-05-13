<?php
/**
 * test_production_features.php - Production Feature Verification
 * 
 * Tests all critical features for production:
 * - PDF Exports
 * - Admin Reports
 * - M-Pesa Production Configuration
 * - Email Configuration
 * - Database Connection
 * 
 * Run this: php test_production_features.php
 */

declare(strict_types=1);

// Start output buffering to prevent header issues
ob_start();

session_start();

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/environment.php';
require_once __DIR__ . '/inc/ReportGenerator.php';

use USMS\Config\EnvLoader;

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║      PRODUCTION FEATURES TEST & VERIFICATION                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$results = [
    'database' => ['status' => '⏳', 'details' => ''],
    'pdf_export' => ['status' => '⏳', 'details' => ''],
    'admin_reports' => ['status' => '⏳', 'details' => ''],
    'mpesa_config' => ['status' => '⏳', 'details' => ''],
    'email_config' => ['status' => '⏳', 'details' => ''],
];

// ═══════════════════════════════════════════════════════════════════════════
// TEST 1: DATABASE CONNECTION
// ═══════════════════════════════════════════════════════════════════════════
echo "1️⃣  DATABASE CONNECTION\n";
echo "───────────────────────────────────────────────────────────────\n";

try {
    if ($conn->ping()) {
        echo "   ✅ Connected to: " . DB_HOST . "/" . DB_NAME . "\n";
        
        // Verify critical tables
        $critical_tables = ['members', 'loans', 'transactions', 'ledger_accounts', 'ledger_entries'];
        $missing_tables = [];
        
        foreach ($critical_tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                $missing_tables[] = $table;
            }
        }
        
        if (empty($missing_tables)) {
            echo "   ✅ All critical tables exist\n";
            $results['database']['status'] = '✅';
            $results['database']['details'] = 'Database connected, all tables verified';
        } else {
            echo "   ⚠️  Missing tables: " . implode(', ', $missing_tables) . "\n";
            $results['database']['status'] = '⚠️';
            $results['database']['details'] = 'Missing: ' . implode(', ', $missing_tables);
        }
    } else {
        echo "   ❌ Database connection failed\n";
        $results['database']['status'] = '❌';
        $results['database']['details'] = $conn->error;
    }
} catch (Exception $e) {
    echo "   ❌ " . $e->getMessage() . "\n";
    $results['database']['status'] = '❌';
    $results['database']['details'] = $e->getMessage();
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════════════
// TEST 2: PDF EXPORT FUNCTIONALITY
// ═══════════════════════════════════════════════════════════════════════════
echo "2️⃣  PDF EXPORT FUNCTIONALITY\n";
echo "───────────────────────────────────────────────────────────────\n";

try {
    // Check if FPDF exists via Composer or legacy path
    if (class_exists('FPDF') || file_exists(__DIR__ . '/fpdf/fpdf.php')) {
        echo "   ✅ FPDF library found\n";
    } else {
        echo "   ⚠️  FPDF library not found\n";
    }
    
    // Check if dompdf autoloader exists
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "   ✅ Composer autoloader found\n";
    } else {
        echo "   ⚠️  Composer autoloader not found\n";
    }
    
    // Check if export engine classes exist
    $export_classes = [
        'USMS\Services\UniversalExportEngine',
        'USMS\Services\FinancialExportEngine',
        'USMS\Reports\PdfTemplate',
    ];
    
    $missing_classes = [];
    foreach ($export_classes as $class) {
        if (!class_exists($class)) {
            $missing_classes[] = $class;
        }
    }
    
    if (empty($missing_classes)) {
        echo "   ✅ All export engine classes available\n";
        $results['pdf_export']['status'] = '✅';
        $results['pdf_export']['details'] = 'PDF export engine ready';
    } else {
        echo "   ⚠️  Missing classes: " . implode(', ', $missing_classes) . "\n";
        $results['pdf_export']['status'] = '⚠️';
        $results['pdf_export']['details'] = 'Missing: ' . implode(', ', $missing_classes);
    }
} catch (Exception $e) {
    echo "   ❌ " . $e->getMessage() . "\n";
    $results['pdf_export']['status'] = '❌';
    $results['pdf_export']['details'] = $e->getMessage();
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════════════
// TEST 3: ADMIN REPORTS PAGE
// ═══════════════════════════════════════════════════════════════════════════
echo "3️⃣  ADMIN REPORTS PAGE\n";
echo "───────────────────────────────────────────────────────────────\n";

try {
    // Check if reports page exists
    if (file_exists(__DIR__ . '/admin/pages/reports.php')) {
        echo "   ✅ Reports page exists at /admin/pages/reports.php\n";
    } else {
        echo "   ❌ Reports page not found\n";
        throw new Exception("Reports page not found");
    }
    
    // Check if ReportGenerator class exists
    if (class_exists('ReportGenerator')) {
        echo "   ✅ ReportGenerator class available\n";
        $results['admin_reports']['status'] = '✅';
        $results['admin_reports']['details'] = 'Reports page and generator ready';
    } else {
        echo "   ⚠️  ReportGenerator class not found (may be loaded at runtime)\n";
        $results['admin_reports']['status'] = '⚠️';
        $results['admin_reports']['details'] = 'ReportGenerator may need runtime loading';
    }
} catch (Exception $e) {
    echo "   ❌ " . $e->getMessage() . "\n";
    $results['admin_reports']['status'] = '❌';
    $results['admin_reports']['details'] = $e->getMessage();
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════════════
// TEST 4: M-PESA PRODUCTION CONFIGURATION
// ═══════════════════════════════════════════════════════════════════════════
echo "4️⃣  M-PESA CONFIGURATION\n";
echo "───────────────────────────────────────────────────────────────\n";

try {
    $app_env = defined('APP_ENV') ? APP_ENV : 'unknown';
    echo "   • APP_ENV: $app_env\n";
    
    $mpesa_env = defined('MPESA_ENV') ? MPESA_ENV : 'unknown';
    echo "   • MPESA_ENV: $mpesa_env\n";
    
    if ($app_env === 'production') {
        echo "   ℹ️  Production mode detected\n";
        
        // Check for live keys
        $live_keys = [
            'MPESA_LIVE_CONSUMER_KEY' => EnvLoader::get('MPESA_LIVE_CONSUMER_KEY'),
            'MPESA_LIVE_CONSUMER_SECRET' => EnvLoader::get('MPESA_LIVE_CONSUMER_SECRET'),
            'MPESA_LIVE_SHORTCODE' => EnvLoader::get('MPESA_LIVE_SHORTCODE'),
            'MPESA_LIVE_PASSKEY' => EnvLoader::get('MPESA_LIVE_PASSKEY'),
        ];
        
        $configured = 0;
        foreach ($live_keys as $key => $value) {
            if (!empty($value)) {
                echo "   ✅ $key: configured\n";
                $configured++;
            } else {
                echo "   ❌ $key: NOT configured\n";
            }
        }
        
        if ($configured === count($live_keys)) {
            echo "   ✅ M-Pesa production keys fully configured\n";
            $results['mpesa_config']['status'] = '✅';
            $results['mpesa_config']['details'] = 'Production M-Pesa configured';
        } else {
            echo "   ⚠️  M-Pesa production keys incomplete ($configured/" . count($live_keys) . ")\n";
            $results['mpesa_config']['status'] = '⚠️';
            $results['mpesa_config']['details'] = "Only $configured/" . count($live_keys) . " keys configured";
        }
    } elseif ($app_env === 'development' || $mpesa_env === 'sandbox') {
        echo "   ℹ️  Sandbox/Development mode detected\n";
        
        // Check for sandbox keys
        $sandbox_keys = [
            'MPESA_SANDBOX_CONSUMER_KEY' => EnvLoader::get('MPESA_SANDBOX_CONSUMER_KEY'),
            'MPESA_SANDBOX_CONSUMER_SECRET' => EnvLoader::get('MPESA_SANDBOX_CONSUMER_SECRET'),
            'MPESA_SANDBOX_SHORTCODE' => EnvLoader::get('MPESA_SANDBOX_SHORTCODE'),
            'MPESA_SANDBOX_PASSKEY' => EnvLoader::get('MPESA_SANDBOX_PASSKEY'),
        ];
        
        $configured = 0;
        foreach ($sandbox_keys as $key => $value) {
            if (!empty($value)) {
                echo "   ✅ $key: configured\n";
                $configured++;
            } else {
                echo "   ⚠️  $key: not configured\n";
            }
        }
        
        if ($configured === count($sandbox_keys)) {
            echo "   ✅ M-Pesa sandbox configured\n";
            $results['mpesa_config']['status'] = '✅';
            $results['mpesa_config']['details'] = 'Sandbox M-Pesa configured';
        } else {
            echo "   ⚠️  M-Pesa sandbox incomplete\n";
            $results['mpesa_config']['status'] = '⚠️';
            $results['mpesa_config']['details'] = 'Sandbox incomplete';
        }
    } else {
        echo "   ⚠️  Unknown environment\n";
        $results['mpesa_config']['status'] = '⚠️';
        $results['mpesa_config']['details'] = 'Unknown environment';
    }
} catch (Exception $e) {
    echo "   ❌ " . $e->getMessage() . "\n";
    $results['mpesa_config']['status'] = '❌';
    $results['mpesa_config']['details'] = $e->getMessage();
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════════════
// TEST 5: EMAIL CONFIGURATION
// ═══════════════════════════════════════════════════════════════════════════
echo "5️⃣  EMAIL CONFIGURATION\n";
echo "───────────────────────────────────────────────────────────────\n";

try {
    $smtp_config = [
        'SMTP_HOST' => SMTP_HOST ?? '',
        'SMTP_PORT' => SMTP_PORT ?? '',
        'SMTP_USERNAME' => SMTP_USERNAME ?? '',
    ];
    
    echo "   • SMTP_HOST: " . ($smtp_config['SMTP_HOST'] ?: 'NOT SET') . "\n";
    echo "   • SMTP_PORT: " . ($smtp_config['SMTP_PORT'] ?: 'NOT SET') . "\n";
    echo "   • SMTP_USERNAME: " . ($smtp_config['SMTP_USERNAME'] ? '✅ configured' : 'NOT SET') . "\n";
    
    $has_password = defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD);
    echo "   • SMTP_PASSWORD: " . ($has_password ? '✅ configured' : 'NOT SET') . "\n";
    
    if (!empty($smtp_config['SMTP_HOST']) && !empty($smtp_config['SMTP_PORT']) && 
        !empty($smtp_config['SMTP_USERNAME']) && $has_password) {
        echo "   ✅ Email configuration complete\n";
        $results['email_config']['status'] = '✅';
        $results['email_config']['details'] = 'Email SMTP configured';
    } else {
        echo "   ⚠️  Email configuration incomplete\n";
        $results['email_config']['status'] = '⚠️';
        $results['email_config']['details'] = 'Some email settings missing';
    }
} catch (Exception $e) {
    echo "   ❌ " . $e->getMessage() . "\n";
    $results['email_config']['status'] = '❌';
    $results['email_config']['details'] = $e->getMessage();
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════════════════
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        SUMMARY                                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$total_pass = 0;
$total_warn = 0;
$total_fail = 0;

foreach ($results as $feature => $result) {
    $status = $result['status'];
    $display_name = str_replace('_', ' ', ucwords($feature));
    
    if ($status === '✅') {
        $total_pass++;
    } elseif ($status === '⚠️') {
        $total_warn++;
    } else {
        $total_fail++;
    }
    
    echo "{$status} " . str_pad($display_name, 30) . "{$result['details']}\n";
}

echo "\n";
echo "Total: $total_pass ✅ | $total_warn ⚠️ | $total_fail ❌\n";
echo "\n";

if ($total_fail === 0 && $total_warn === 0) {
    echo "🎉 ALL SYSTEMS READY FOR PRODUCTION\n";
    http_response_code(200);
} elseif ($total_fail === 0) {
    echo "⚠️  Some warnings detected. Review recommended.\n";
    http_response_code(200);
} else {
    echo "❌ CRITICAL ISSUES DETECTED. Fix before deploying to production.\n";
    http_response_code(500);
}

echo "\n";

// Prevent any output that would mess with the test
ob_end_flush();
