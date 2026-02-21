<?php
/**
 * tools/add_strict_types.php
 * One-time utility: adds declare(strict_types=1) to inc/ service files.
 */
$dir = __DIR__ . '/../inc/';
$skip = [
    'header.php', 'footer.php', 'sidebar.php', 'topbar.php',
    'functions.php', 'email.php', 'sms.php', 'dark_mode_loader.php',
    'finance_nav.php', 'message_icon.php', 'notification_bell.php',
    'sidebar_styles.php', 'topbar_styles.php', 'notify_all.php',
];

$patched = 0;
$skipped = 0;
$alreadyHas = 0;

foreach (glob($dir . '*.php') as $file) {
    $name = basename($file);
    if (in_array($name, $skip)) { $skipped++; continue; }

    $content = file_get_contents($file);

    if (strpos($content, 'declare(strict_types') !== false) {
        $alreadyHas++;
        continue;
    }

    // Insert after the opening <?php tag
    $new = preg_replace('/^<\?php\r?\n/', "<?php\ndeclare(strict_types=1);\n", $content, 1, $count);

    if ($count === 0) {
        echo "SKIP (no <?php tag): $name\n";
        $skipped++;
        continue;
    }

    file_put_contents($file, $new);
    echo "Patched: $name\n";
    $patched++;
}

echo "\n=== Summary ===\n";
echo "Patched:      $patched\n";
echo "Already had:  $alreadyHas\n";
echo "Skipped:      $skipped\n";
