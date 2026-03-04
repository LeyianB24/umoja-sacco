<?php
$files = explode("\n", trim(shell_exec("git diff --name-only --diff-filter=U")));
$count = 0;
foreach ($files as $file) {
    if (empty($file) || !file_exists($file)) continue;
    
    // We only process PHP files and other text files, but let's just do all files returned by git diff
    $content = file_get_contents($file);
    
    $lines = explode("\n", $content);
    $out = [];
    $in_conflict = false;
    $keep = false;
    
    foreach ($lines as $line) {
        if (str_starts_with($line, '<<<<<<< HEAD')) {
            $in_conflict = true;
            $keep = true;
            continue;
        }
        if (str_starts_with($line, '=======')) {
            $keep = false;
            continue;
        }
        if (preg_match('/^>>>>>>> [0-9a-fA-F]+/', $line)) {
            $in_conflict = false;
            $keep = false;
            continue;
        }
        
        if (!$in_conflict) {
            $out[] = $line;
        } elseif ($keep) {
            $out[] = $line;
        }
    }
    
    file_put_contents($file, implode("\n", $out));
    shell_exec("git add " . escapeshellarg($file));
    $count++;
}
echo "Resolved $count files.\n";
