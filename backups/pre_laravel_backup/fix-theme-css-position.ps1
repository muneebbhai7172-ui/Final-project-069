# PowerShell script to ensure theme CSS loads after sidebar CSS in all pages

$MODULES = @(
    "customers",
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
        
        # Remove any existing theme CSS
        $content = $content -replace '\s*<!-- Theme CSS -->[^\n]*\n\s*<link rel="stylesheet" href="../../css/theme-variables\.css"[^>]*>', ''
        
        # Find modern sidebar CSS and add theme CSS after it
        if ($content -match '<link rel="stylesheet" href="../../css/components/_modern-sidebar\.css[^>]*>') {
            $content = $content -replace '(<link rel="stylesheet" href="../../css/components/_modern-sidebar\.css[^>]*>)', "`$1`n`n    <!-- Theme CSS - Must load AFTER sidebar CSS to override properly -->`n    <link rel=`"stylesheet`" href=`"../../css/theme-variables.css`">"
            
            Set-Content -Path $file -Value $content -NoNewline
            Write-Host "Fixed $file" -ForegroundColor Green
        } else {
            Write-Host "No sidebar CSS found in $file - skipping" -ForegroundColor Yellow
        }
    } else {
        Write-Host "File $file not found" -ForegroundColor Red
    }
}

Write-Host "Theme CSS positioning completed!" -ForegroundColor Cyan