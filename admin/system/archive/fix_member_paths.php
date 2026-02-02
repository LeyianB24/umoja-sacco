<?php
$dirs = ['member/pages', 'member/api', 'member/includes'];
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
        
        // Also check for links in HTML (href="support.php") -> need to check if internal links break?
        // If everything is in pages/, links like 'support.php' still work.
        // But links to '../public/' might need adjustment? 
        // __DIR__ fixes requires. hrefs are usually absolute defined by BASE_URL or relative.
        // If relative 'href="../public/"' -> from member/ it is ../public. From member/pages/ it is ../../public.
        
        // Most links in this project seem to use BASE_URL (from what I saw in admin). 
        // Let's verify a member file content.

        file_put_contents($path, $content);
        echo "Updated $path\n";
    }
}
?>
