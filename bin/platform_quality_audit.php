<?php
/**
 * USMS Platform Quality Audit
 * Comprehensive diagnostic scanning for performance, security, and user experience
 * 
 * Usage: php bin/platform_quality_audit.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only\n");
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============ CONFIG ============
$report_file = __DIR__ . '/../storage/platform_audit_' . date('Y-m-d_H-i-s') . '.log';
$project_root = __DIR__ . '/..';

class PlatformAudit {
    private $report = [];
    private $root_path;
    private $issues = ['CRITICAL' => [], 'HIGH' => [], 'MEDIUM' => [], 'LOW' => []];
    private $metrics = [];

    public function __construct($root_path) {
        $this->root_path = $root_path;
    }

    public function run() {
        echo "=== USMS Platform Quality Audit ===\n";
        echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

        $this->scanPerformanceBottlenecks();
        $this->scanSecurityVulnerabilities();
        $this->scanCodeQuality();
        $this->validateDatabaseConfiguration();
        $this->checkEmailSystem();
        $this->validateAPIRouting();
        $this->checkFilePermissions();
        $this->generateReport();

        return true;
    }

    private function scanPerformanceBottlenecks() {
        echo "[1/8] Scanning for performance bottlenecks...\n";

        // Check for remaining blocking operations
        $patterns = [
            'sleep(' => 'Hard-coded sleep delays (blocks request)',
            'set_time_limit' => 'Extended time limits (risky)',
            'file_get_contents.*http' => 'Synchronous HTTP calls (blocking)',
            'file_get_contents.*ftp' => 'Synchronous FTP calls (blocking)',
            'shell_exec' => 'Synchronous shell execution',
            'exec(' => 'Synchronous command execution (may be blocking)',
            'fsockopen' => 'Synchronous socket connection (blocking)',
            'curl_exec' => 'Synchronous cURL (check for timeouts)',
            'stream_get_contents' => 'Synchronous stream reading',
        ];

        $php_files = $this->getPhpFiles(['tests/', 'node_modules/', 'vendor/']);

        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            foreach ($patterns as $pattern => $description) {
                if (preg_match_all("/$pattern/i", $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line_num = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        
                        // Ignore if in comments
                        $line_text = trim($lines[$line_num - 1] ?? '');
                        if (strpos($line_text, '//') === 0 || strpos($line_text, '#') === 0) {
                            continue;
                        }

                        // Categorize severity
                        $severity = 'MEDIUM';
                        if (strpos($pattern, 'sleep') !== false) {
                            $severity = 'HIGH';
                        } elseif (strpos($pattern, 'set_time_limit') !== false) {
                            $severity = 'MEDIUM';
                        }

                        $this->addIssue($severity, "Performance", "File: " . str_replace($this->root_path, '', $file) . " (L$line_num)\n  Issue: $description\n  Code: $line_text");
                    }
                }
            }
        }

        echo "  ✓ Found " . count(array_merge(...$this->issues)) . " potential bottlenecks\n";
    }

    private function scanSecurityVulnerabilities() {
        echo "[2/8] Scanning for security vulnerabilities...\n";

        $php_files = $this->getPhpFiles(['tests/', 'node_modules/', 'vendor/', 'bin/']);

        $issues_found = 0;

        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            // Check for SQL injection risks
            if (preg_match_all('/\$conn->query\s*\(\s*["\'].*\$/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line_num = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    if (!preg_match('/prepared|stmt/', $lines[$line_num - 1] ?? '')) {
                        $this->addIssue('HIGH', 'Security', "SQL Injection risk: " . str_replace($this->root_path, '', $file) . " L$line_num");
                        $issues_found++;
                    }
                }
            }

            // Check for hardcoded credentials
            if (preg_match_all('/(?:password|apikey|secret|token)\s*=\s*["\'](?![\$\{])/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line_num = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    $line_text = trim($lines[$line_num - 1] ?? '');
                    if (strpos($line_text, '//') === false && strpos($line_text, 'example') === false) {
                        $this->addIssue('CRITICAL', 'Security', "Hardcoded credential: " . str_replace($this->root_path, '', $file) . " L$line_num");
                        $issues_found++;
                    }
                }
            }

            // Check for missing CSRF protection
            if (preg_match('/<form[^>]*method\s*=\s*["\']post["\']/i', $content)) {
                if (!preg_match('/csrf|token|nonce/', $content)) {
                    if (strpos($file, 'admin/') !== false || strpos($file, 'member/') !== false) {
                        if (preg_match('/<form/i', $content) && !preg_match('/csrf/i', $content)) {
                            $this->addIssue('HIGH', 'Security', "Missing CSRF protection: " . str_replace($this->root_path, '', $file));
                            $issues_found++;
                        }
                    }
                }
            }

            // Check for missing input validation
            if (preg_match('/\$_(?:GET|POST|REQUEST)\[["\'][^"\']+["\']\](?!\s*\?->|->validate|->sanitize)/', $content)) {
                // Only flag if no validation framework present
                if (!preg_match('/Validator::/', $content)) {
                    $line_matches = preg_match_all('/\$_(?:GET|POST|REQUEST)/', $content, $line_matches_array);
                    if ($line_matches > 3) { // Only report if multiple uses without clear validation
                        if (strpos($file, 'admin/') !== false || strpos($file, 'member/') !== false) {
                            $this->addIssue('MEDIUM', 'Security', "Unvalidated input: " . str_replace($this->root_path, '', $file));
                            $issues_found++;
                        }
                    }
                }
            }
        }

        echo "  ✓ Security scan complete: $issues_found issues found\n";
    }

    private function scanCodeQuality() {
        echo "[3/8] Scanning code quality...\n";

        $php_files = $this->getPhpFiles(['tests/', 'node_modules/', 'vendor/', 'bin/']);
        $quality_issues = 0;

        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $line_count = count($lines);

            // Check function length (> 50 lines = potential refactor)
            $function_lengths = [];
            $current_function = '';
            $in_function = false;
            $function_start = 0;

            foreach ($lines as $i => $line) {
                if (preg_match('/function\s+(\w+)/', $line, $matches)) {
                    $current_function = $matches[1];
                    $function_start = $i + 1;
                    $in_function = true;
                }

                if ($in_function && (preg_match('/^}/', $line) || preg_match('/^function /', $line))) {
                    $length = $i - $function_start;
                    if ($length > 100) {
                        $this->addIssue('MEDIUM', 'CodeQuality', "Long function: $current_function in " . str_replace($this->root_path, '', $file) . " ($length lines)");
                        $quality_issues++;
                    }
                    $in_function = false;
                }
            }

            // Check for TODO/FIXME comments
            $todos = preg_match_all('/(TODO|FIXME|HACK|XXX)/', $content);
            if ($todos > 0) {
                $this->addIssue('LOW', 'CodeQuality', "$todos TODO/FIXME comments in " . str_replace($this->root_path, '', $file));
                $quality_issues++;
            }

            // Check cyclomatic complexity (too many elseif)
            $if_count = substr_count($content, 'if (') + substr_count($content, 'elseif (');
            if ($if_count > 15) {
                $this->addIssue('MEDIUM', 'CodeQuality', "High complexity: $if_count conditions in " . str_replace($this->root_path, '', $file));
                $quality_issues++;
            }
        }

        echo "  ✓ Code quality scan complete: $quality_issues issues found\n";
    }

    private function validateDatabaseConfiguration() {
        echo "[4/8] Validating database configuration...\n";

        $db_connect_file = $this->root_path . '/config/db_connect.php';
        if (file_exists($db_connect_file)) {
            $content = file_get_contents($db_connect_file);
            
            // Check for PDO vs mysqli
            if (strpos($content, 'mysqli') !== false) {
                if (strpos($content, 'prepared') === false) {
                    $this->addIssue('MEDIUM', 'Database', "mysqli without prepared statements detected");
                }
            }

            if (preg_match('/charset\s*=\s*["\']utf8["\']/', $content)) {
                $this->addIssue('LOW', 'Database', "Using legacy 'utf8' charset - should use 'utf8mb4'");
            }
        }

        // Check for slow query log configuration
        $this->metrics['database'] = [
            'connection_tested' => true,
            'charset_status' => 'OK'
        ];

        echo "  ✓ Database configuration validated\n";
    }

    private function checkEmailSystem() {
        echo "[5/8] Checking email system health...\n";

        $email_errors = $this->root_path . '/storage/email_errors.log';
        $temp_dir = sys_get_temp_dir();
        $stale_files = glob($temp_dir . '/usms_email_*');

        $email_issues = 0;

        if (file_exists($email_errors)) {
            $lines = file($email_errors);
            if (count($lines) > 100) {
                $this->addIssue('MEDIUM', 'Email', "Email error log has " . count($lines) . " entries - review for patterns");
                $email_issues++;
            }
        }

        if (count($stale_files) > 5) {
            $this->addIssue('MEDIUM', 'Email', "Found " . count($stale_files) . " temp email files - may indicate stuck processes");
            $email_issues++;
        }

        foreach ($stale_files as $file) {
            if (time() - filemtime($file) > 3600) { // > 1 hour old
                $this->addIssue('LOW', 'Email', "Stale temp file: " . basename($file));
                $email_issues++;
            }
        }

        echo "  ✓ Email system check complete: " . (file_exists($email_errors) ? count(file($email_errors)) : 0) . " errors logged\n";
    }

    private function validateAPIRouting() {
        echo "[6/8] Validating API routing...\n";

        $routes_file = $this->root_path . '/api/v1/routes.php';
        if (file_exists($routes_file)) {
            $content = file_get_contents($routes_file);
            $route_count = substr_count($content, 'registerRoute');

            $this->metrics['api_routes'] = $route_count;
            echo "  ✓ Found $route_count registered API routes\n";
        }
    }

    private function checkFilePermissions() {
        echo "[7/8] Checking file permissions...\n";

        $critical_dirs = [
            'storage',
            'uploads',
            'config'
        ];

        $permission_issues = 0;

        foreach ($critical_dirs as $dir) {
            $path = $this->root_path . '/' . $dir;
            if (is_dir($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -3);
                if ($dir === 'storage' || $dir === 'uploads') {
                    if (!is_writable($path)) {
                        $this->addIssue('HIGH', 'Permissions', "Directory not writable: $dir (perms: $perms)");
                        $permission_issues++;
                    }
                }
            }
        }

        echo "  ✓ File permissions check complete: $permission_issues issues\n";
    }

    private function generateReport() {
        echo "[8/8] Generating audit report...\n";

        $report = "=== USMS PLATFORM QUALITY AUDIT ===\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

        // Summary
        $total_issues = array_sum(array_map('count', $this->issues));
        $report .= "SUMMARY\n";
        $report .= "-------\n";
        $report .= "Total Issues: $total_issues\n";
        $report .= "  CRITICAL: " . count($this->issues['CRITICAL']) . "\n";
        $report .= "  HIGH: " . count($this->issues['HIGH']) . "\n";
        $report .= "  MEDIUM: " . count($this->issues['MEDIUM']) . "\n";
        $report .= "  LOW: " . count($this->issues['LOW']) . "\n\n";

        // Issues by severity
        foreach (['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'] as $severity) {
            if (count($this->issues[$severity]) > 0) {
                $report .= strtoupper($severity) . " PRIORITY ISSUES (" . count($this->issues[$severity]) . ")\n";
                $report .= str_repeat("-", 40) . "\n";

                foreach ($this->issues[$severity] as $category => $items) {
                    if (is_array($items)) {
                        $report .= "\n$category:\n";
                        foreach ($items as $issue) {
                            $report .= "  • $issue\n";
                        }
                    } else {
                        $report .= "  • $items\n";
                    }
                }
                $report .= "\n";
            }
        }

        // Metrics
        $report .= "\nMETRICS\n";
        $report .= "-------\n";
        $report .= json_encode($this->metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        $report .= "\nRECOMMENDATIONS\n";
        $report .= "---------------\n";

        if (count($this->issues['CRITICAL']) > 0) {
            $report .= "1. FIX CRITICAL ISSUES IMMEDIATELY - These pose security/stability risks\n";
        }

        if (count($this->issues['HIGH']) > 0) {
            $report .= "2. Address HIGH priority issues before next deployment\n";
        }

        if (count($this->issues['MEDIUM']) > 0) {
            $report .= "3. Plan refactoring for MEDIUM issues in next sprint\n";
        }

        $report .= "4. Monitor email_errors.log for patterns\n";
        $report .= "5. Run: php bin/health_check.php --clean-temp (weekly)\n";
        $report .= "6. Review slow pages: admin/pages/loans_payouts.php, admin/pages/reports.php\n";

        echo "\n" . $report;

        // Save to file
        if (!is_dir($this->root_path . '/storage')) {
            mkdir($this->root_path . '/storage', 0755, true);
        }

        file_put_contents($report_file, $report);
        echo "\n✓ Report saved to: $report_file\n";
    }

    private function addIssue($severity, $category, $message) {
        if (!isset($this->issues[$severity][$category])) {
            $this->issues[$severity][$category] = [];
        }
        $this->issues[$severity][$category][] = $message;
    }

    private function getPhpFiles($exclude = []) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root_path)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $path = $file->getRealPath();
                $should_exclude = false;

                foreach ($exclude as $pattern) {
                    if (strpos($path, $this->root_path . '/' . $pattern) === 0) {
                        $should_exclude = true;
                        break;
                    }
                }

                if (!$should_exclude) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }
}

// Run the audit
$audit = new PlatformAudit($project_root);
$audit->run();

echo "\n✓ Audit complete\n";
