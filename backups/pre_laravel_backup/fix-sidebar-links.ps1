# Fix all sidebar navigation links in admin pages
$modulesPath = "C:\xampp\htdocs\waqar\modules"
$adminModules = @('dashboard', 'medicines', 'orders', 'customers', 'billing', 'stock', 'reports', 'settings', 'backup', 'records')

Write-Host "Fixing sidebar navigation links in admin pages..." -ForegroundColor Cyan
$fixed = 0

foreach ($moduleName in $adminModules) {
    $modulePath = Join-Path $modulesPath $moduleName
    
    if (Test-Path $modulePath) {
        $htmlFiles = Get-ChildItem -Path $modulePath -Filter "*.html"
        
        foreach ($file in $htmlFiles) {
            $content = Get-Content $file.FullName -Raw
            $original = $content
            
            # Fix all admin page links to new module structure
            $content = $content -replace 'href="dashboard\.html"', 'href="../dashboard/index.html"'
            $content = $content -replace 'href="modern-medicines\.html"', 'href="../medicines/index.html"'
            $content = $content -replace 'href="medicine-import\.html"', 'href="../medicines/import.html"'
            $content = $content -replace 'href="orders\.html"', 'href="../orders/index.html"'
            $content = $content -replace 'href="modern-customers\.html"', 'href="../customers/index.html"'
            $content = $content -replace 'href="stock-check\.html"', 'href="../stock/index.html"'
            $content = $content -replace 'href="modern-billing\.html"', 'href="../billing/index.html"'
            $content = $content -replace 'href="modern-records\.html"', 'href="../records/index.html"'
            $content = $content -replace 'href="modern-reports\.html"', 'href="../reports/index.html"'
            $content = $content -replace 'href="backup\.html"', 'href="../backup/index.html"'
            $content = $content -replace 'href="settings\.html"', 'href="../settings/index.html"'
            $content = $content -replace 'href="settings-new\.html"', 'href="../settings/alternative.html"'
            
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
