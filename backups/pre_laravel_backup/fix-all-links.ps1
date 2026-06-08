# Fix all navigation links
$modulesPath = "C:\xampp\htdocs\waqar\modules"
$allFiles = Get-ChildItem -Path $modulesPath -Filter "*.html" -Recurse

Write-Host "Fixing navigation links in all HTML files..." -ForegroundColor Cyan
$fixed = 0

foreach ($file in $allFiles) {
    $content = Get-Content $file.FullName -Raw
    $original = $content
    
    # Frontend pages
    $content = $content -replace 'href="index\.html"', 'href="../frontend/index.html"'
    $content = $content -replace 'href="about\.html"', 'href="../frontend/about.html"'
    
    # Shop pages
    $content = $content -replace 'href="search\.html"', 'href="../shop/search.html"'
    $content = $content -replace 'href="cart\.html"', 'href="../shop/cart.html"'
    $content = $content -replace 'href="medicine-detail\.html"', 'href="../shop/medicine-detail.html"'
    $content = $content -replace 'href="track-order\.html"', 'href="../shop/track-order.html"'
    
    # Auth pages
    $content = $content -replace 'href="login\.html"', 'href="../auth/login.html"'
    $content = $content -replace 'href="register\.html"', 'href="../auth/register.html"'
    $content = $content -replace 'href="unlock-account\.html"', 'href="../auth/unlock-account.html"'
    
    # Admin pages
    $content = $content -replace 'href="dashboard\.html"', 'href="../dashboard/index.html"'
    $content = $content -replace 'href="modern-medicines\.html"', 'href="../medicines/index.html"'
    $content = $content -replace 'href="orders\.html"', 'href="../orders/index.html"'
    $content = $content -replace 'href="modern-customers\.html"', 'href="../customers/index.html"'
    $content = $content -replace 'href="modern-billing\.html"', 'href="../billing/index.html"'
    $content = $content -replace 'href="stock-check\.html"', 'href="../stock/index.html"'
    $content = $content -replace 'href="modern-reports\.html"', 'href="../reports/index.html"'
    $content = $content -replace 'href="settings\.html"', 'href="../settings/index.html"'
    $content = $content -replace 'href="backup\.html"', 'href="../backup/index.html"'
    
    if ($content -ne $original) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($file.Name)" -ForegroundColor Green
        $fixed++
    }
}

Write-Host "Total files fixed: $fixed" -ForegroundColor Green
