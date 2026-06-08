# PowerShell script to add admin-common.js to all admin pages

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
        # Check if admin-common.js is already included
        $content = Get-Content $file -Raw
        if ($content -notmatch "admin-common.js") {
            # Find sidebar-loader.js and add admin-common.js after it
            $content = $content -replace '(<script src="../../js/sidebar-loader\.js[^"]*"></script>)', "`$1`n<script src=`"../../js/admin-common.js`"></script>"
            Set-Content -Path $file -Value $content -NoNewline
            Write-Host "Added admin-common.js to $file" -ForegroundColor Green
        } else {
            Write-Host "admin-common.js already exists in $file" -ForegroundColor Yellow
        }
    } else {
        Write-Host "File $file not found" -ForegroundColor Red
    }
}

Write-Host "Admin-common.js addition completed!" -ForegroundColor Cyan