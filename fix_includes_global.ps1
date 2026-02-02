
$dirs = @("C:\xampp\htdocs\usms\member\pages", "C:\xampp\htdocs\usms\admin\pages")

foreach ($dir in $dirs) {
    if (Test-Path $dir) {
        $files = Get-ChildItem -Path $dir -Filter *.php
        foreach ($f in $files) {
            $content = Get-Content $f.FullName -Raw
            
            # 1. Fix standard includes ( ../ -> ../../ )
            $content = $content -replace "require_once __DIR__ . '/\.\./config", "require_once __DIR__ . '/../../config" `
                               -replace "require_once __DIR__ . '/\.\./inc", "require_once __DIR__ . '/../../inc" `
                               -replace "@include_once __DIR__ . '/\.\./config", "@include_once __DIR__ . '/../../config"
            
            # 2. Fix LayoutManager ordering
            # If LayoutManager::create is used before it is required, move the require to the top
            if ($content -match "LayoutManager::create") {
                # Strip any existing LayoutManager requires to re-insert correctly
                $content = $content -replace "(?m)^require_once\s+__DIR__\s+\.\s+'/../../inc/LayoutManager\.php';\s*`r?`n", ""
                $content = $content -replace "(?m)^require_once\s+__DIR__\s+\.\s+'/../../inc/LayoutManager\.php';", ""
                
                # Find a good place to insert (after opening <?php or session_start)
                if ($content -match "(?m)^session_start\s*\(\s*\)\s*;") {
                     $content = $content -replace "(session_start\s*\(\s*\)\s*;)", "$1`r`nrequire_once __DIR__ . '/../../inc/LayoutManager.php';"
                } else {
                     $content = $content -replace "^(<\?php)", "$1`r`nrequire_once __DIR__ . '/../../inc/LayoutManager.php';"
                }
            }
            
            Set-Content -Path $f.FullName -Value $content
            Write-Host "Processed $($f.FullName)"
        }
    }
}
