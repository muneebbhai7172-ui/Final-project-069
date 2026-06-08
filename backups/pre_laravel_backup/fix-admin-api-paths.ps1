# Fix all remaining old API paths in admin modules
$modulesPath = "C:\xampp\htdocs\waqar\modules"
$adminModules = @('dashboard', 'medicines', 'orders', 'customers', 'billing', 'stock', 'reports', 'settings', 'backup', 'records')

Write-Host "Fixing API paths in admin modules..." -ForegroundColor Cyan
$fixed = 0

foreach ($moduleName in $adminModules) {
    $modulePath = Join-Path $modulesPath $moduleName
    
    if (Test-Path $modulePath) {
        $htmlFiles = Get-ChildItem -Path $modulePath -Filter "*.html"
        
        foreach ($file in $htmlFiles) {
            $content = Get-Content $file.FullName -Raw
            $original = $content
            
            # Fix old API paths - these reference old structure
            $content = $content -replace "fetch\(['\`"]\.\.\/dashboard\.php", "fetch('./api.php"
            $content = $content -replace "fetch\(['\`"]\.\.\/medicines\.php", "fetch('./api.php"
            $content = $content -replace "fetch\(['\`"]\.\.\/orders\.php", "fetch('./api.php"
            $content = $content -replace "fetch\(['\`"]\.\.\/customers\.php", "fetch('./api.php"
            $content = $content -replace "fetch\(['\`"]\.\.\/billing\.php", "fetch('./api.php"
            $content = $content -replace "fetch\(['\`"]\.\.\/stock_check\.php", "fetch('./api.php"
            $content = $content -replace "fetch\(['\`"]\.\.\/reports\.php", "fetch('./api.php"
            $content = $content -replace "fetch\(['\`"]\.\.\/settings\.php", "fetch('./api.php"
            $content = $content -replace "fetch\(['\`"]\.\.\/backup\.php", "fetch('./api.php"
            
            # Fix jQuery ajax url patterns
            $content = $content -replace "url:\s*['\`"]\.\.\/dashboard\.php", "url: './api.php"
            $content = $content -replace "url:\s*['\`"]\.\.\/medicines\.php", "url: './api.php"
            $content = $content -replace "url:\s*['\`"]\.\.\/orders\.php", "url: './api.php"
            $content = $content -replace "url:\s*['\`"]\.\.\/customers\.php", "url: './api.php"
            $content = $content -replace "url:\s*['\`"]\.\.\/billing\.php", "url: './api.php"
            $content = $content -replace "url:\s*['\`"]\.\.\/stock_check\.php", "url: './api.php"
            $content = $content -replace "url:\s*['\`"]\.\.\/reports\.php", "url: './api.php"
            $content = $content -replace "url:\s*['\`"]\.\.\/settings\.php", "url: './api.php"
            $content = $content -replace "url:\s*['\`"]\.\.\/backup\.php", "url: './api.php"
            
            if ($content -ne $original) {
                Set-Content -Path $file.FullName -Value $content -NoNewline
                Write-Host "Fixed: $($file.Name) in $moduleName" -ForegroundColor Green
                $fixed++
            }
        }
    }
}

Write-Host ""
Write-Host "Total files fixed: $fixed" -ForegroundColor Green
