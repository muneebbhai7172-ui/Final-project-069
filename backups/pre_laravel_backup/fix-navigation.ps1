# Fix all navigation links in frontend and shop modules
$frontendFiles = Get-ChildItem -Path "C:\xampp\htdocs\waqar\modules\frontend" -Filter "*.html"
$shopFiles = Get-ChildItem -Path "C:\xampp\htdocs\waqar\modules\shop" -Filter "*.html"

Write-Host "Fixing frontend files..." -ForegroundColor Cyan

foreach ($file in $frontendFiles) {
    $content = Get-Content $file.FullName -Raw
    
    # Fix links from frontend to other modules
    $content = $content -replace 'href="login\.html"', 'href="../auth/login.html"'
    $content = $content -replace 'href="register\.html"', 'href="../auth/register.html"'
    $content = $content -replace 'href="cart\.html"', 'href="../shop/cart.html"'
    $content = $content -replace 'href="track-order\.html"', 'href="../shop/track-order.html"'
    
    # about.html stays in same folder, so keep relative
    # index.html stays in same folder
    
    Set-Content -Path $file.FullName -Value $content -NoNewline
    Write-Host "  Fixed: $($file.Name)" -ForegroundColor Green
}

Write-Host "`nFixing shop files..." -ForegroundColor Cyan

foreach ($file in $shopFiles) {
    $content = Get-Content $file.FullName -Raw
    
    # Fix links from shop to other modules
    $content = $content -replace 'href="index\.html"', 'href="../frontend/index.html"'
    $content = $content -replace 'href="about\.html"', 'href="../frontend/about.html"'
    $content = $content -replace 'href="login\.html"', 'href="../auth/login.html"'
    $content = $content -replace 'href="register\.html"', 'href="../auth/register.html"'
    
    # cart, search, track-order, medicine-detail stay in same shop folder
    
    Set-Content -Path $file.FullName -Value $content -NoNewline
    Write-Host "  Fixed: $($file.Name)" -ForegroundColor Green
}

Write-Host "`nAll navigation links fixed!" -ForegroundColor Green
