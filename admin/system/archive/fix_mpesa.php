<?php
$file = 'member/pages/mpesa_request.php';
$content = file_get_contents($file);

// Remove the misplaced LayoutManager init from lines 2-4
$content = preg_replace('/^<\?php\s*\n\s*\n\/\/ Initialize Layout Manager\n\$layout = LayoutManager::create\(\'member\'\);\n/m', "<?php\n", $content);

// Add declare right after <?php
$content = preg_replace('/^(<\?php)\s*\n/', "$1\ndeclare(strict_types=1);\n\n", $content);

// Add LayoutManager init after the last require_once
$content = preg_replace('/(require_once __DIR__ \. \'\/\.\.\/\.\.\/inc\/LayoutManager\.php\';)\s*\n/', "$1\n\n// Initialize Layout Manager\n\$layout = LayoutManager::create('member');\n\n", $content);

file_put_contents($file, $content);
echo "Fixed mpesa_request.php\n";
?>
