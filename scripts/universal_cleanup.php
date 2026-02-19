<?php
/**
 * UMOJA SACCO — UNIVERSAL DARK MODE CLEANUP & INJECTION SCRIPT (ADMIN SWEEP V2)
 * Purges hardcoded light-themed styles and injects darkmode system into manual heads.
 */

$root = __DIR__ . '/..';
$directories = ['admin', 'member', 'inc', 'public'];

$patterns = [
    // 1. CSS Injection Logic (For manual heads)
    // We only inject if <head> exists AND darkmode.css is NOT already mentioned
    '/(<head[^>]*>)/i' => function($matches) use (&$content) {
        if (strpos($content, 'darkmode.css') !== false) return $matches[1];
        
        $injection = $matches[1] . "\n    <link rel=\"stylesheet\" href=\"/usms/public/assets/css/darkmode.css\">" .
                     "\n    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>";
        return $injection;
    },
    
    // 2. Prevent Double Injection & Cleanup duplicates
    '/href="[^"]*darkmode\.css"[^>]*>\s*<link rel="stylesheet" href="[^"]*darkmode\.css"[^>]*>/i' => '<link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">',
    '/<script>\(function\(\){const s=localStorage\.getItem\(\'theme\'\)\|\|\'light\';document\.documentElement\.setAttribute\(\'data-bs-theme\',s\);}\)\(\);<\/script>\s*<script>\(function\(\){const s=localStorage\.getItem\(\'theme\'\)\|\|\'light\';document\.documentElement\.setAttribute\(\'data-bs-theme\',s\);}\)\(\);<\/script>/i' => '<script>(function(){const s=localStorage.getItem(\'theme\')||\'light\';document.documentElement.setAttribute(\'data-bs-theme\',s);})();</script>',

    // 3. Admin-Specific Backgrounds to Purge
    '/style="background: #f0f4f3;[^"]*"/i' => '',
    '/style="background: #f4f7f6;[^"]*"/i' => '',
    '/style="background: white;[^"]*"/i' => '',
    '/style="background-color: white;[^"]*"/i' => '',
    '/style="background: #FFFFFF;[^"]*"/i' => '',
    '/style="background-color: #FFFFFF;[^"]*"/i' => '',
    '/style="background: #fff;[^"]*"/i' => '',
    '/style="background: #f8fafc;[^"]*"/i' => '',
    '/style="background-color: #f8fafc;[^"]*"/i' => '',
    '/style="background: #f1f5f9;[^"]*"/i' => '',
    '/style="background-color: #f1f5f9;[^"]*"/i' => '',
    '/style="background: #fdfdfd;[^"]*"/i' => '',
    '/style="background-color: #fdfdfd;[^"]*"/i' => '',

    // 4. Shared Style Purging
    '/ class="([^"]*)text-dark([^"]*)"/i' => function($matches) {
        return ' class="' . trim(str_replace('text-dark', '', $matches[2] . ' ' . $matches[1])) . '"';
    },

    // 5. Hero Background Cleanup (Allowing the gradient to be replaced by CSS)
    '/style="background: linear-gradient\(135deg, var\(--forest\) 0%, #1a4d3e 100%\);/i' => 'style="',
];

foreach ($directories as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) continue;

    echo "Scanning: $path\n";
    
    $it = new RecursiveDirectoryIterator($path);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->getExtension() !== 'php') continue;
        
        $filePath = $file->getRealPath();
        $content = file_get_contents($filePath);
        $original = $content;

        foreach ($patterns as $pattern => $replacement) {
            if (is_callable($replacement)) {
                $content = preg_replace_callback($pattern, $replacement, $content);
            } else {
                $content = preg_replace($pattern, $replacement, $content);
            }
        }

        if ($content !== $original) {
            file_put_contents($filePath, $content);
            echo "   [FIXED] " . basename($filePath) . "\n";
        }
    }
}

echo "\nAdmin Portal Sweep V2 Complete.\n";
