<?php
$dirs = ['admin/pages', 'admin/api', 'admin/includes', 'admin/system'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (!is_file($path)) continue;

        $content = file_get_contents($path);
        
        // Replace relative paths
        $content = str_replace("__DIR__ . '/../config/", "__DIR__ . '/../../config/", $content);
        $content = str_replace("__DIR__ . '/../inc/", "__DIR__ . '/../../inc/", $content);
        $content = str_replace("__DIR__ . '/../vendor/", "__DIR__ . '/../../vendor/", $content);
        $content = str_replace("__DIR__ . '/../public/", "__DIR__ . '/../../public/", $content);
        
        // Correct internal admin references if they were relative
        // e.g. include 'sidebar.php' in /admin/pages/ needs to be '../../inc/sidebar.php'?
        // Wait, where is sidebar.php? 
        // Currently it's in /inc/ (based on dashboard.php line 88: include __DIR__ . '/../inc/sidebar.php')
        // So after move to /admin/pages, it should be include __DIR__ . '/../../inc/sidebar.php'
        // This is covered by the str_replace for /inc/ above.

        file_put_contents($path, $content);
        echo "Updated $path\n";
    }
}
?>
