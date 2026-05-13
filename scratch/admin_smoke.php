<?php
declare(strict_types=1);

$base = dirname(__DIR__);
$php = PHP_BINARY;

require_once $base . '/config/app.php';

function first_value(mysqli $conn, string $sql): ?string
{
    $res = $conn->query($sql);
    if (!$res) {
        return null;
    }

    $row = $res->fetch_row();
    return $row ? (string)$row[0] : null;
}

$adminId = first_value($conn, 'SELECT admin_id FROM admins ORDER BY admin_id LIMIT 1') ?? '1';
$memberId = first_value($conn, 'SELECT member_id FROM members ORDER BY member_id LIMIT 1') ?? '1';
$loanId = first_value($conn, 'SELECT loan_id FROM loans ORDER BY loan_id LIMIT 1') ?? '1';
$documentId = first_value($conn, 'SELECT document_id FROM member_documents ORDER BY document_id LIMIT 1') ?? '0';

$tests = [
    [
        'name' => 'loan details ajax',
        'file' => $base . '/admin/pages/ajax_get_loan_details.php',
        'method' => 'GET',
        'get' => ['loan_id' => $loanId],
        'expect_json' => true,
    ],
    [
        'name' => 'audit feed ajax',
        'file' => $base . '/admin/pages/ajax_audit_feed.php',
        'method' => 'GET',
        'get' => ['since_id' => '0'],
        'expect_json' => true,
    ],
    [
        'name' => 'statement csv export',
        'file' => $base . '/admin/api/generate_statement.php',
        'method' => 'POST',
        'post' => [
            'member_id' => $memberId,
            'start_date' => '2020-01-01',
            'end_date' => date('Y-m-d'),
            'report_type' => 'full',
            'format' => 'csv',
        ],
    ],
    [
        'name' => 'document serving endpoint',
        'file' => $base . '/admin/api/serve_document.php',
        'method' => 'GET',
        'get' => ['id' => $documentId],
    ],
    [
        'name' => 'backup download guard',
        'file' => $base . '/admin/pages/download_backup.php',
        'method' => 'GET',
        'get' => ['file' => 'not-a-backup.txt'],
    ],
];

$failures = [];

foreach ($tests as $test) {
    $runner = tempnam(sys_get_temp_dir(), 'usms_admin_smoke_') . '.php';
    $get = $test['get'] ?? [];
    $post = $test['post'] ?? [];
    $relative = str_replace('\\', '/', substr($test['file'], strlen($base) + 1));
    $query = http_build_query($get);

    $code = '<?php' . PHP_EOL
        . 'declare(strict_types=1);' . PHP_EOL
        . 'chdir(' . var_export($base, true) . ');' . PHP_EOL
        . '$_SERVER["REQUEST_METHOD"] = ' . var_export($test['method'], true) . ';' . PHP_EOL
        . '$_SERVER["SCRIPT_NAME"] = "/" . ' . var_export($relative, true) . ';' . PHP_EOL
        . '$_SERVER["PHP_SELF"] = $_SERVER["SCRIPT_NAME"];' . PHP_EOL
        . '$_SERVER["REQUEST_URI"] = $_SERVER["SCRIPT_NAME"] . (' . var_export($query, true) . ' !== "" ? "?' . $query . '" : "");' . PHP_EOL
        . '$_SERVER["DOCUMENT_ROOT"] = ' . var_export($base, true) . ';' . PHP_EOL
        . '$_SERVER["HTTP_HOST"] = "localhost";' . PHP_EOL
        . '$_GET = ' . var_export($get, true) . ';' . PHP_EOL
        . '$_POST = ' . var_export($post, true) . ';' . PHP_EOL
        . 'if (session_status() === PHP_SESSION_NONE) session_start();' . PHP_EOL
        . '$_SESSION["admin_id"] = ' . var_export((int)$adminId, true) . ';' . PHP_EOL
        . '$_SESSION["role_id"] = 1;' . PHP_EOL
        . '$_SESSION["role"] = "superadmin";' . PHP_EOL
        . '$_SESSION["role_name"] = "superadmin";' . PHP_EOL
        . '$_SESSION["admin_name"] = "Smoke Admin";' . PHP_EOL
        . 'require ' . var_export($test['file'], true) . ';' . PHP_EOL;

    file_put_contents($runner, $code);
    $cmd = '"' . $php . '" ' . escapeshellarg($runner) . ' 2>&1';
    $lines = [];
    exec($cmd, $lines, $exitCode);
    $stdout = implode(PHP_EOL, $lines);
    $stderr = '';
    @unlink($runner);

    $ok = $exitCode === 0;
    if ($ok && !empty($test['expect_json'])) {
        $jsonStart = strpos($stdout, '{');
        $jsonEnd = strrpos($stdout, '}');
        $json = ($jsonStart === false || $jsonEnd === false)
            ? null
            : json_decode(substr($stdout, $jsonStart, $jsonEnd - $jsonStart + 1), true);
        $ok = is_array($json);
    }

    echo ($ok ? '[OK] ' : '[FAIL] ') . $test['name'] . PHP_EOL;
    if (!$ok) {
        $failures[] = $test['name'] . ': ' . trim($stdout . PHP_EOL . $stderr);
    }
}

if ($failures) {
    echo PHP_EOL . implode(PHP_EOL, $failures) . PHP_EOL;
    exit(1);
}

exit(0);
