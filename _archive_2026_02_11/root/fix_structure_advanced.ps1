
$dirs = @("C:\xampp\htdocs\usms\member\pages", "C:\xampp\htdocs\usms\admin\pages")

foreach ($dir in $dirs) {
    if (Test-Path $dir) {
        $files = Get-ChildItem -Path $dir -Filter *.php
        foreach ($f in $files) {
            $content = Get-Content $f.FullName -Raw
            
            # --- 1. Clean up existing session/layout/config references to standardize ---
            # Remove any LayoutManager::create if it's at the very top (we'll re-insert properly)
            $content = $content -replace "(?m)^\$layout\s*=\s*LayoutManager::create\(.*?\);\s*`r?`n", ""
            
            # Remove existing requires to re-insert in correct order
            $content = $content -replace "require_once\s+__DIR__\s+\.\s+'/(?:\.\./)+inc/LayoutManager\.php';\s*`r?`n", ""
            $content = $content -replace "require_once\s+__DIR__\s+\.\s+'/(?:\.\./)+config/app_config\.php';\s*`r?`n", ""
            $content = $content -replace "require_once\s+__DIR__\s+\.\s+'/(?:\.\./)+config/db_connect\.php';\s*`r?`n", ""
            $content = $content -replace "require_once\s+__DIR__\s+\.\s+'/(?:\.\./)+inc/Auth\.php';\s*`r?`n", ""
            $content = $content -replace "require_once\s+__DIR__\s+\.\s+'/(?:\.\./)+inc/auth\.php';\s*`r?`n", ""

            # Standardize paths to ../../
            $is_subpage = ($dir -match "pages")
            $prefix = if ($is_subpage) { "../../" } else { "../" }

            # --- 2. Build Header ---
            $header = "<?php`r`n"
            $header += "declare(strict_types=1);`r`n"
            $header += "if (session_status() === PHP_SESSION_NONE) session_start();`r`n`r`n"
            $header += "require_once __DIR__ . '/${prefix}config/app_config.php';`r`n"
            $header += "require_once __DIR__ . '/${prefix}config/db_connect.php';`r`n"
            $header += "require_once __DIR__ . '/${prefix}inc/Auth.php';`r`n"
            $header += "require_once __DIR__ . '/${prefix}inc/LayoutManager.php';`r`n`r`n"
            
            # Check context
            $context = if ($dir -match "admin") { "admin" } else { "member" }
            $header += "`$layout = LayoutManager::create('$context');`r`n"
            
            # --- 3. Apply Change ---
            # Remove existing <?php and any opening comments if they are at the very top
            $content = $content -replace "^<\?php\s*", ""
            
            # Prepend new header
            $newContent = $header + $content
            
            Set-Content -Path $f.FullName -Value $newContent
            Write-Host "Re-Structured $($f.FullName)"
        }
    }
}
