# Remove admin-sidebar-loader.js script tags from all admin pages
$modulesPath = "C:\xampp\htdocs\waqar\modules"
$allFiles = Get-ChildItem -Path $modulesPath -Filter "*.html" -Recurse

Write-Host "Removing sidebar loader script from admin pages..." -ForegroundColor Cyan
$fixed = 0

foreach ($file in $allFiles) {
    $content = Get-Content $file.FullName -Raw
    $original = $content
    
    # Remove the sidebar loader script tag
    $content = $content -replace '<script src="\.\.\/\.\.\/js\/admin-sidebar-loader\.js"><\/script>\s*', ''
    $content = $content -replace '<script src="\.\.\/js\/admin-sidebar-loader\.js"><\/script>\s*', ''
    
    if ($content -ne $original) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($file.Name) in $($file.Directory.Name)" -ForegroundColor Green
        $fixed++
    }
}

Write-Host ""
Write-Host "Total files fixed: $fixed" -ForegroundColor Green
