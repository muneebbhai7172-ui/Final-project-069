# Fix all resource paths (CSS, JS, Images) in all HTML files
$modulesPath = "C:\xampp\htdocs\waqar\modules"
$allFiles = Get-ChildItem -Path $modulesPath -Filter "*.html" -Recurse

Write-Host "Fixing resource paths in all HTML files..." -ForegroundColor Cyan
$fixed = 0

foreach ($file in $allFiles) {
    $content = Get-Content $file.FullName -Raw
    $original = $content
    
    # Fix CSS paths
    $content = $content -replace 'href="css/', 'href="../../css/'
    $content = $content -replace 'href="\.\.\/css/', 'href="../../css/'
    
    # Fix JS paths
    $content = $content -replace 'src="js/', 'src="../../js/'
    $content = $content -replace 'src="\.\.\/js/', 'src="../../js/'
    
    # Fix images paths
    $content = $content -replace 'src="images/', 'src="../../images/'
    $content = $content -replace 'src="\.\.\/images/', 'src="../../images/'
    
    # Fix includes paths
    $content = $content -replace 'href="includes/', 'href="../../includes/'
    $content = $content -replace 'src="includes/', 'src="../../includes/'
    
    if ($content -ne $original) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($file.FullName.Replace($modulesPath, 'modules'))" -ForegroundColor Green
        $fixed++
    }
}

Write-Host ""
Write-Host "Total files fixed: $fixed" -ForegroundColor Green
