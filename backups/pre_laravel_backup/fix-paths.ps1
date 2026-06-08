# Fix all API path references in modules
$files = Get-ChildItem -Path "C:\xampp\htdocs\waqar\modules" -Filter "*.php" -Recurse

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    
    # Fix config/database.php paths
    $content = $content -replace "'\.\./config/", "'../../config/"
    
    # Fix includes paths  
    $content = $content -replace "'\.\./includes/", "'../../includes/"
    
    # Fix with double quotes too
    $content = $content -replace '"\.\./config/', '"../../config/'
    $content = $content -replace '"\.\./includes/', '"../../includes/'
    
    Set-Content -Path $file.FullName -Value $content -NoNewline
}

Write-Host "All paths fixed!" -ForegroundColor Green
