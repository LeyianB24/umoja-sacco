<?php
$file = 'c:\xampp\htdocs\usms\admin\pages\expenses.php';
$content = file_get_contents($file);

$startPattern = '/<\?php\s*\/\/\s*2\.\s*Handle Form Submission/is';

if (preg_match($startPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
    $posStart = $matches[0][1];
    
    $heroPos = strpos($content, "<!-- ═══ HERO ════");
    if ($heroPos !== false) {
        $qmark = chr(63);
        $closeTag = $qmark . ">";
        $openTag = "<" . $qmark . "php";
        
        $phpEndPos = strrpos(substr($content, 0, $heroPos), $closeTag);
        if ($phpEndPos !== false && $phpEndPos > $posStart) {
            $phpEndPos += 2;
            
            $block = substr($content, $posStart, $phpEndPos - $posStart);
            
            $content = substr($content, 0, $posStart) . substr($content, $phpEndPos);
            
            $insertMatch = '$pageTitle = "Expenditure Portal";';
            $insertPos = strpos($content, $insertMatch);
            
            if ($insertPos !== false) {
                $insertPos += strlen($insertMatch);
                
                // Clean block
                $cleanBlock = preg_replace('/^' . preg_quote($openTag, '/') . '\s*/', '', trim($block));
                $cleanBlock = preg_replace('/' . preg_quote($closeTag, '/') . '$/', '', trim($cleanBlock));
                
                $content = substr($content, 0, $insertPos) . "\n\n" . $cleanBlock . "\n" . substr($content, $insertPos);
                
                file_put_contents($file, $content);
                echo "Success\n";
            } else {
                echo "Insert position not found\n";
            }
        } else {
             echo "Failed to find end var tag\n";
        }
    } else {
        echo "Failed to find Hero section target\n";
    }
} else {
    echo "Start pattern not found\n";
}
