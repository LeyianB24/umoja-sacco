<?php
/**
 * scripts/inject_dark_mode.php
 * Run this script to automatically inject the dark mode loader into all pages 
 * that have their own <head> tags but missing the darkmode.css link.
 */

require_once __DIR__ . '/../config/app_config.php';

$directories = [
    __DIR__ . '/../admin/pages',
    __DIR__ . '/../member/pages',
    __DIR__ . '/../public'
];

$files_patched = 0;
$loader_inc = "<?php require_once __DIR__ . '" . (isset($is_nested) ? '/' : '/') . "'; ?>"; // We'll use absolute path relative constant

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        
        $path = $file->getRealPath();
        $content = file_get_contents($path);
        
        // Skip files that already have darkmode.css or no <head>
        if (strpos($content, 'darkmode.css') !== false || strpos($content, '<head>') === false) {
            continue;
        }

        // Determine relative path to inc/dark_mode_loader.php
        $depth = substr_count(str_replace(__DIR__ . '/../', '', $path), DIRECTORY_SEPARATOR);
        $rel = str_repeat('../', $depth) . 'inc/dark_mode_loader.php';
        
        $injection = "\n    <?php require_once __DIR__ . '/" . basename($rel) . "'; // Fallback if absolute fails ?>";
        // Let's use a more robust way: find the project root from the path
        
        $root_search = 'usms';
        $pos = strpos($path, $root_search);
        $project_root = substr($path, 0, $pos + strlen($root_search));
        $loader_path = $project_root . '/inc/dark_mode_loader.php';
        
        $injection = "\n    <?php require_once '" . str_replace('\\', '/', $loader_path) . "'; ?>";
        
        // Inject after <head> or before </head>
        // We inject after <title> or first link to be safe
        if (strpos($content, '</head>') !== false) {
            $new_content = str_replace('</head>', $injection . "\n</head>", $content);
            file_put_contents($path, $new_content);
            $files_patched++;
            echo "Patched: $path\n";
        }
    }
}

echo "\nTotal files patched: $files_patched\n";
