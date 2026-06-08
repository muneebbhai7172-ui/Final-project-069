# PowerShell script to fix theme CSS loading order

$MODULES = @(
    "billing",
    "customers",
    "medicines", 
    "stock",
    "orders",
    "reports",
    "records",
    "backup",
    "settings"
)

foreach ($module in $MODULES) {
    $file = "modules\$module\index.html"
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        
        # Check if theme CSS is loaded before modern sidebar CSS
        if ($content -match 'theme-variables\.css.*modern-sidebar\.css') {
            # Remove the theme CSS from its current position
            $content = $content -replace '\s*<!-- Theme CSS -->\s*\n\s*<link rel="stylesheet" href="../../css/theme-variables\.css">', ''
            
            # Add theme CSS after modern sidebar CSS
            $content = $content -replace '(<link rel="stylesheet" href="../../css/components/_modern-sidebar\.css[^>]*>)', "`$1`n`n    <!-- Theme CSS - Must load AFTER sidebar CSS to override properly -->`n    <link rel=`"stylesheet`" href=`"../../css/theme-variables.css`">"
            
            Set-Content -Path $file -Value $content -NoNewline
            Write-Host "Fixed CSS order in $file" -ForegroundColor Green
        } else {
            Write-Host "CSS order already correct in $file" -ForegroundColor Yellow
        }
    } else {
        Write-Host "File $file not found" -ForegroundColor Red
    }
}

Write-Host "CSS loading order fix completed!" -ForegroundColor Cyan