# PowerShell script to add theme CSS to all admin pages
$THEME_CSS_LINE = '    <link rel="stylesheet" href="../../css/theme-variables.css">'

# List of module directories
$MODULES = @(
    "billing",
    "customers",
    "medicines", 
    "stock",
    "orders",
    "reports",
    "records",
    "backup"
)

foreach ($module in $MODULES) {
    $file = "modules\$module\index.html"
    if (Test-Path $file) {
        # Check if theme CSS is already included
        $content = Get-Content $file -Raw
        if ($content -notmatch "theme-variables.css") {
            # Find the head section and add theme CSS
            $content = $content -replace '(<head>)', "`$1`n    <!-- Theme CSS -->`n    <link rel=`"stylesheet`" href=`"../../css/theme-variables.css`">"
            Set-Content -Path $file -Value $content -NoNewline
            Write-Host "Added theme CSS to $file" -ForegroundColor Green
        } else {
            Write-Host "Theme CSS already exists in $file" -ForegroundColor Yellow
        }
    } else {
        Write-Host "File $file not found" -ForegroundColor Red
    }
}

Write-Host "Theme CSS addition completed!" -ForegroundColor Cyan