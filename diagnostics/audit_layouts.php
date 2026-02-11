<?php
$dir = 'c:/xampp/htdocs/usms/admin/pages';
$files = glob("$dir/*.php");

$results = [];

foreach ($files as $file) {
    if (basename($file) === 'login.php') continue; // Login usually different
    
    $content = file_get_contents($file);
    
    $has_footer = strpos($content, '$layout->footer()') !== false;
    
    // Check if footer is inside the wrapper.
    // Logic: find $layout->footer(), then check the next few lines for </div></div>
    // Or just check if footer comes before the last two </div> tags that usually end the layout.
    
    $footer_pos = strrpos($content, '$layout->footer()');
    $last_div_pos = strrpos($content, '</div>');
    
    $status = 'OK';
    if (!$has_footer) {
        $status = 'MISSING_FOOTER';
    } else {
        // If footer is after the last div, it's definitely wrong.
        if ($footer_pos > $last_div_pos) {
            $status = 'FOOTER_OUTSIDE_WRAPPER';
        }
        
        // Check if there are at least two </div> after footer
        $after_footer = substr($content, $footer_pos);
        $div_count = substr_count($after_footer, '</div>');
        if ($div_count < 2) {
            $status = 'FOOTER_MISPLACED';
        }
    }
    
    $results[] = [
        'file' => basename($file),
        'has_footer' => $has_footer,
        'status' => $status,
        'divs_after_footer' => $has_footer ? $div_count : 0
    ];
}

$out = sprintf("%-25s | %-15s | %-25s | %s\n", "File", "Has Footer", "Status", "Divs After");
$out .= str_repeat("-", 80) . "\n";

foreach ($results as $res) {
    $out .= sprintf("%-25s | %-15s | %-25s | %d\n", 
        $res['file'], 
        $res['has_footer'] ? 'Yes' : 'No', 
        $res['status'], 
        $res['divs_after_footer']
    );
}

file_put_contents(__DIR__ . '/audit_results.txt', $out);
echo "Audited " . count($results) . " files.\n";
