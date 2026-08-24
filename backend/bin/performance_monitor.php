<?php
/**
 * USMS Performance Monitoring Dashboard
 * Real-time performance metrics and diagnostics
 * 
 * Usage: php bin/performance_monitor.php
 *        php bin/performance_monitor.php --export json
 *        php bin/performance_monitor.php --export csv
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only\n");
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

class PerformanceMonitor {
    private $project_root;
    private $metrics = [
        'page_load_times' => [],
        'database_queries' => [],
        'external_calls' => [],
        'memory_usage' => [],
    ];
    private $slow_threshold = 1000; // ms

    public function __construct($project_root) {
        $this->project_root = $project_root;
    }

    public function analyze($export_format = null) {
        echo "=== USMS Performance Monitor ===\n";
        echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

        $this->analyzePageLoadTimes();
        $this->analyzeDatabaseQueries();
        $this->analyzeExternalCalls();
        $this->analyzeMemoryUsage();
        $this->identifyBottlenecks();

        if ($export_format) {
            $this->export($export_format);
        } else {
            $this->displayDashboard();
        }
    }

    private function analyzePageLoadTimes() {
        echo "[1/4] Analyzing page load times...\n";

        // Scan key pages and estimate load time based on code analysis
        $pages = [
            'admin/pages/dashboard.php' => 'Admin Dashboard',
            'admin/pages/loans.php' => 'Loans Management',
            'admin/pages/loans_payouts.php' => 'Loan Payouts',
            'admin/pages/reports.php' => 'Reports & Analytics',
            'member/dashboard.php' => 'Member Dashboard',
            'member/pages/profile.php' => 'Member Profile',
            'member/pages/mpesa_request.php' => 'M-Pesa Payment',
            'public/register.php' => 'Registration',
            'public/login.php' => 'Login',
        ];

        foreach ($pages as $file => $name) {
            $path = $this->project_root . '/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $estimated_time = $this->estimateLoadTime($content);

                $this->metrics['page_load_times'][] = [
                    'page' => $name,
                    'file' => $file,
                    'estimated_ms' => $estimated_time,
                    'is_slow' => $estimated_time > $this->slow_threshold
                ];
            }
        }

        usort($this->metrics['page_load_times'], function($a, $b) {
            return $b['estimated_ms'] <=> $a['estimated_ms'];
        });

        echo "  ✓ Analyzed " . count($this->metrics['page_load_times']) . " pages\n";
    }

    private function analyzeDatabaseQueries() {
        echo "[2/4] Analyzing database queries...\n";

        // Check QueryLogger if available
        $logger_file = $this->project_root . '/core/Database/QueryLogger.php';
        if (file_exists($logger_file)) {
            $content = file_get_contents($logger_file);
            if (preg_match('/class QueryLogger/', $content)) {
                $this->metrics['database_queries'][] = [
                    'feature' => 'Query Performance Logging',
                    'status' => 'Implemented',
                    'available' => true
                ];
            }
        }

        // Scan for N+1 query patterns
        $admin_files = glob($this->project_root . '/admin/pages/*.php');
        $n_plus_one_risk = 0;

        foreach ($admin_files as $file) {
            $content = file_get_contents($file);
            // Look for patterns like: foreach ($items as $item) { query() }
            if (preg_match('/foreach.*as\s+\$\w+\).*?\n\s*\$.*query|mysqli|PDO/s', $content)) {
                $n_plus_one_risk++;
            }
        }

        $this->metrics['database_queries'][] = [
            'risk_type' => 'N+1 Queries',
            'files_at_risk' => $n_plus_one_risk,
            'severity' => $n_plus_one_risk > 5 ? 'HIGH' : ($n_plus_one_risk > 0 ? 'MEDIUM' : 'LOW')
        ];

        echo "  ✓ Database query analysis complete\n";
    }

    private function analyzeExternalCalls() {
        echo "[3/4] Analyzing external API calls...\n";

        $gateway_files = [
            'core/Services/Gateways/MpesaService.php' => 'M-Pesa Gateway',
            'core/Services/Gateways/PaystackService.php' => 'Paystack Gateway',
        ];

        foreach ($gateway_files as $file => $name) {
            $path = $this->project_root . '/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);

                // Check for timeout configuration
                $has_timeout = preg_match('/CURLOPT_TIMEOUT|CURLOPT_CONNECTTIMEOUT/', $content);
                $timeout_value = 30;

                if (preg_match('/CURLOPT_TIMEOUT[,\s]+(\d+)/', $content, $matches)) {
                    $timeout_value = (int)$matches[1];
                }

                $this->metrics['external_calls'][] = [
                    'service' => $name,
                    'has_timeout' => $has_timeout,
                    'timeout_seconds' => $timeout_value,
                    'status' => $has_timeout ? 'Protected' : 'UNPROTECTED'
                ];
            }
        }

        echo "  ✓ External API analysis complete\n";
    }

    private function analyzeMemoryUsage() {
        echo "[4/4] Analyzing memory efficiency...\n";

        // Check for memory-intensive operations
        $concerns = [];

        // Check for unoptimized file uploads
        $upload_files = glob($this->project_root . '/public/**/upload*.php', GLOB_RECURSE);
        if (count($upload_files) > 0) {
            $concerns[] = [
                'area' => 'File Uploads',
                'count' => count($upload_files),
                'recommendation' => 'Implement chunked uploads for large files'
            ];
        }

        // Check for PDF generation
        $export_files = glob($this->project_root . '/admin/pages/*export*.php');
        if (count($export_files) > 0) {
            $concerns[] = [
                'area' => 'PDF Exports',
                'count' => count($export_files),
                'recommendation' => 'Use background job for large reports'
            ];
        }

        $this->metrics['memory_usage'] = $concerns;

        echo "  ✓ Memory efficiency analysis complete\n";
    }

    private function identifyBottlenecks() {
        echo "\n=== BOTTLENECK ANALYSIS ===\n";

        // Identify slowest pages
        $slow_pages = array_filter($this->metrics['page_load_times'], function($p) {
            return $p['is_slow'];
        });

        if (!empty($slow_pages)) {
            echo "\nSlow Pages (" . count($slow_pages) . "):\n";
            foreach ($slow_pages as $page) {
                echo "  ⚠ {$page['page']}: {$page['estimated_ms']}ms\n";
            }
        } else {
            echo "\n✓ No pages estimated as slow\n";
        }

        // Unprotected APIs
        $unprotected = array_filter($this->metrics['external_calls'], function($c) {
            return $c['status'] === 'UNPROTECTED';
        });

        if (!empty($unprotected)) {
            echo "\nUnprotected External Calls:\n";
            foreach ($unprotected as $call) {
                echo "  ⚠ {$call['service']}: No timeout configured\n";
            }
        } else {
            echo "\n✓ All external calls have timeout protection\n";
        }
    }

    private function displayDashboard() {
        echo "\n╔═══════════════════════════════════════════════════════════╗\n";
        echo "║          PERFORMANCE DASHBOARD                            ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";

        echo "PAGE LOAD TIMES (Est.)\n";
        echo "─────────────────────────────────────────────────────────────\n";
        foreach ($this->metrics['page_load_times'] as $page) {
            $icon = $page['is_slow'] ? '⚠' : '✓';
            printf("%-40s %6dms %s\n", $page['page'], $page['estimated_ms'], $icon);
        }

        echo "\nEXTERNAL API PROTECTION\n";
        echo "─────────────────────────────────────────────────────────────\n";
        foreach ($this->metrics['external_calls'] as $call) {
            $icon = $call['has_timeout'] ? '✓' : '✗';
            printf("%-40s %3ds timeout %s\n", $call['service'], $call['timeout_seconds'], $icon);
        }

        echo "\nMEMORY OPTIMIZATION AREAS\n";
        echo "─────────────────────────────────────────────────────────────\n";
        if (empty($this->metrics['memory_usage'])) {
            echo "✓ No obvious memory optimization areas detected\n";
        } else {
            foreach ($this->metrics['memory_usage'] as $concern) {
                printf("• %s (%d items): %s\n", $concern['area'], $concern['count'], $concern['recommendation']);
            }
        }

        $slow_count = count(array_filter($this->metrics['page_load_times'], function($p) { return $p['is_slow']; }));
        $total_count = count($this->metrics['page_load_times']);

        echo "\n" . str_repeat("─", 60) . "\n";
        echo "Overall Health: " . ($slow_count === 0 ? "✓ GOOD" : "⚠ " . $slow_count . "/" . $total_count . " pages may be slow") . "\n";
        echo "Last Updated: " . date('Y-m-d H:i:s') . "\n";
    }

    private function export($format) {
        $filename = $this->project_root . '/storage/performance_metrics_' . date('Y-m-d_H-i-s') . '.' . $format;

        if ($format === 'json') {
            $json = json_encode($this->metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($filename, $json);
            echo "\n✓ Metrics exported to: storage/performance_metrics_*.json\n";
        } elseif ($format === 'csv') {
            $csv = "Page,File,Estimated Load Time (ms),Is Slow\n";
            foreach ($this->metrics['page_load_times'] as $page) {
                $csv .= "\"{$page['page']}\",\"{$page['file']}\",{$page['estimated_ms']}," . ($page['is_slow'] ? 'Yes' : 'No') . "\n";
            }
            file_put_contents($filename, $csv);
            echo "\n✓ Metrics exported to: storage/performance_metrics_*.csv\n";
        }
    }

    private function estimateLoadTime($content) {
        $time = 50; // Base load time

        // Count database queries (rough estimate: 50ms per query)
        $query_count = substr_count($content, '$conn->query') + substr_count($content, 'prepare') + substr_count($content, 'execute');
        $time += $query_count * 50;

        // Check for external API calls (rough estimate: 200ms per call)
        $api_count = substr_count($content, 'curl_') + substr_count($content, 'file_get_contents');
        $time += $api_count * 200;

        // Check for file operations (rough estimate: 30ms per operation)
        $file_count = substr_count($content, 'file_') + substr_count($content, 'fopen') + substr_count($content, 'include');
        $time += $file_count * 30;

        // Check for loops (rough estimate: 10ms per loop with DB)
        if (preg_match('/foreach.*\n.*\$conn->query|foreach.*\n.*prepare/s', $content)) {
            $time += 100; // Potential N+1
        }

        // Subtract if using cache
        if (strpos($content, 'cache') !== false || strpos($content, 'Cache') !== false) {
            $time = max(50, $time - 200);
        }

        return $time;
    }
}

// Parse arguments
$export_format = null;
if (isset($argv[1]) && $argv[1] === '--export' && isset($argv[2])) {
    $export_format = $argv[2];
}

$project_root = dirname(__DIR__);
$monitor = new PerformanceMonitor($project_root);
$monitor->analyze($export_format);
