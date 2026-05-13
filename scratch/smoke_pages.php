<?php
declare(strict_types=1);

$base = dirname(__DIR__);
$php = PHP_BINARY;

require_once $base . '/config/app.php';

$memberId = 1;
$memberName = 'Smoke Member';
$res = $conn->query("SELECT member_id, full_name FROM members ORDER BY member_id ASC LIMIT 1");
if ($res && ($row = $res->fetch_assoc())) {
    $memberId = (int)$row['member_id'];
    $memberName = $row['full_name'] ?: $memberName;
}

$adminId = 1;
$adminName = 'Smoke Admin';
$roleId = 1;
$roleName = 'superadmin';
$res = $conn->query("SELECT a.admin_id, a.full_name, a.role_id, COALESCE(r.name, 'superadmin') AS role_name FROM admins a LEFT JOIN roles r ON a.role_id = r.id ORDER BY a.admin_id ASC LIMIT 1");
if ($res && ($row = $res->fetch_assoc())) {
    $adminId = (int)$row['admin_id'];
    $adminName = $row['full_name'] ?: $adminName;
    $roleId = (int)($row['role_id'] ?: 1);
    $roleName = strtolower($row['role_name'] ?: $roleName);
}

$groups = [
    'admin' => glob($base . '/admin/pages/*.php') ?: [],
    'member' => glob($base . '/member/pages/*.php') ?: [],
    'public' => glob($base . '/public/*.php') ?: [],
    'api_v1' => glob($base . '/api/v1/*.php') ?: [],
];

$skipNames = [
    'download_backup.php',
    'serve_document.php',
    'transactions_pdf.php',
];

$results = [];

foreach ($groups as $group => $files) {
    foreach ($files as $file) {
        if (in_array(basename($file), $skipNames, true)) {
            continue;
        }

        $runner = tempnam(sys_get_temp_dir(), 'usms_smoke_') . '.php';
        $relative = str_replace('\\', '/', substr($file, strlen($base) + 1));
        $session = [
            'member_id' => $memberId,
            'member_name' => $memberName,
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'full_name' => $adminName,
            'role_id' => $roleId,
            'role' => $roleName,
            'role_name' => $roleName,
        ];

        $query = match (basename($file)) {
            'support_view.php',
            'member_profile.php' => 'id=1',
            'mpesa_request.php' => 'type=savings',
            default => '',
        };

        $code = '<?php' . PHP_EOL
            . 'declare(strict_types=1);' . PHP_EOL
            . 'chdir(' . var_export($base, true) . ');' . PHP_EOL
            . '$_SERVER["REQUEST_METHOD"] = "GET";' . PHP_EOL
            . '$_SERVER["SCRIPT_NAME"] = "/" . ' . var_export($relative, true) . ';' . PHP_EOL
            . '$_SERVER["PHP_SELF"] = $_SERVER["SCRIPT_NAME"];' . PHP_EOL
            . '$_SERVER["REQUEST_URI"] = $_SERVER["SCRIPT_NAME"] . (' . var_export($query, true) . ' !== "" ? "?' . $query . '" : "");' . PHP_EOL
            . '$_SERVER["DOCUMENT_ROOT"] = ' . var_export($base, true) . ';' . PHP_EOL
            . '$_SERVER["HTTP_HOST"] = "localhost";' . PHP_EOL
            . 'parse_str(' . var_export($query, true) . ', $_GET);' . PHP_EOL
            . 'if (session_status() === PHP_SESSION_NONE) session_start();' . PHP_EOL
            . '$_SESSION = array_merge($_SESSION, ' . var_export($session, true) . ');' . PHP_EOL
            . 'ob_start();' . PHP_EOL
            . 'try {' . PHP_EOL
            . '    require ' . var_export($file, true) . ';' . PHP_EOL
            . '    ob_end_clean();' . PHP_EOL
            . '    exit(0);' . PHP_EOL
            . '} catch (Throwable $e) {' . PHP_EOL
            . '    ob_end_clean();' . PHP_EOL
            . '    fwrite(STDERR, $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL);' . PHP_EOL
            . '    exit(1);' . PHP_EOL
            . '}' . PHP_EOL;

        file_put_contents($runner, $code);
        $cmd = '"' . $php . '" ' . escapeshellarg($runner);
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptorSpec, $pipes, $base);
        $output = [];
        $exitCode = 1;

        if (is_resource($process)) {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $started = microtime(true);
            $timedOut = false;

            while (true) {
                $status = proc_get_status($process);
                $output[] = stream_get_contents($pipes[1]);
                $output[] = stream_get_contents($pipes[2]);

                if (!$status['running']) {
                    $exitCode = $status['exitcode'];
                    break;
                }

                if ((microtime(true) - $started) > 8) {
                    $timedOut = true;
                    proc_terminate($process);
                    $exitCode = 124;
                    $output[] = 'Timed out after 8 seconds';
                    break;
                }

                usleep(100000);
            }

            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);

            if ($timedOut) {
                @unlink($runner);
            }
        } else {
            $output[] = 'Unable to start PHP process';
        }
        @unlink($runner);

        $results[] = [
            'group' => $group,
            'file' => $relative,
            'ok' => $exitCode === 0,
            'output' => trim(implode(PHP_EOL, $output)),
        ];
    }
}

$failures = array_values(array_filter($results, static fn(array $r): bool => !$r['ok']));

echo 'Checked ' . count($results) . ' pages/endpoints.' . PHP_EOL;
echo 'Passed: ' . (count($results) - count($failures)) . PHP_EOL;
echo 'Failed: ' . count($failures) . PHP_EOL;

foreach ($failures as $failure) {
    echo PHP_EOL . '[FAIL] ' . $failure['file'] . PHP_EOL;
    echo ($failure['output'] ?: '(no output)') . PHP_EOL;
}

exit(count($failures) === 0 ? 0 : 1);
