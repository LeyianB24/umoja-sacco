<?php
$dir = __DIR__ . '/admin/pages/';
$files = glob($dir . '*.php');

$replacements = 0;

foreach ($files as $file) {
    if (in_array(basename($file), ['login.php'])) continue;

    $content = file_get_contents($file);
    $orig = $content;

    // 1. Remove old wrapper structure:
    // <div class="d-flex">
    //    <?php $layout->sidebar(); ?>
    //    <div class="flex-fill main-content-wrapper p-0">
    //        <?php $layout->topbar(...); ?>
    //        <div class="container-fluid">
    
    $pattern_top = '/<div\s+class="d-flex">\s*(<\?php\s+\$layout->sidebar\(\);\s*\?>)\s*<div\s+class="flex-fill\s+main-content-wrapper[^>]*">\s*(<\?php\s+\$layout->topbar\([^)]*\);\s*\?>)\s*<div\s+class="container-fluid[^>]*">/s';
    
    $content = preg_replace($pattern_top, "$1\n<div class=\"main-wrapper\">\n    $2\n    <div class=\"main-content\">", $content);

    // 2. Remove the old trailing divs AFTER the footer
    // <?php $layout->footer(); ?>
    // </div> </div> </div>
    $pattern_bottom_after = '/(<\?php\s+\$layout->footer\(\);\s*\?>)\s*(?:<\/div>\s*(?:<!--.*?-->)?\s*){1,4}/s';
    $content = preg_replace($pattern_bottom_after, "$1\n", $content);

    // 3. Remove inline styles for main-content-wrapper
    $content = preg_replace('/\x3cstyle\x3e\s*\.main-content-wrapper\s*\{[^}]*\}\s*@media[^\{]*\{[^}]*\.[^}]*\}\s*.*?(\x3c\/style\x3e)?/s', '', $content);
    // simpler one for leftovers
    $content = preg_replace('/\.main-content-wrapper\s*\{[^}]*\}/s', '', $content);

    if ($orig !== $content) {
        file_put_contents($file, $content);
        echo "Fixed top/bottom layout in " . basename($file) . "\n";
        $replacements++;
    }
}
echo "Total files fixed: $replacements\n";
?>
