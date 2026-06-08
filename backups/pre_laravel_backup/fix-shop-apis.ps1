# Fix all API paths in shop module files
$shopPath = "C:\xampp\htdocs\waqar\modules\shop"
$shopFiles = Get-ChildItem -Path $shopPath -Filter "*.html"

Write-Host "Fixing API paths in shop module..." -ForegroundColor Cyan
$fixed = 0

foreach ($file in $shopFiles) {
    $content = Get-Content $file.FullName -Raw
    $original = $content
    
    # Fix medicines API calls
    $content = $content -replace "url:\s*['\`"]api/medicines\.php", "url: '../medicines/api.php"
    $content = $content -replace "fetch\(['\`"]api/medicines\.php", "fetch('../medicines/api.php"
    $content = $content -replace "fetch\(`api/medicines\.php", "fetch(`../medicines/api.php"
    
    # Fix track order API calls
    $content = $content -replace "url:\s*['\`"]api/track_order\.php", "url: '../shop/track-order-api.php"
    $content = $content -replace "fetch\(['\`"]api/track_order\.php", "fetch('./track-order-api.php"
    
    # Fix cart API calls
    $content = $content -replace "url:\s*['\`"]api/cart\.php", "url: './cart-api.php"
    $content = $content -replace "fetch\(['\`"]api/cart\.php", "fetch('./cart-api.php"
    
    if ($content -ne $original) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($file.Name)" -ForegroundColor Green
        $fixed++
    }
}

Write-Host ""
Write-Host "Total shop files fixed: $fixed" -ForegroundColor Green
