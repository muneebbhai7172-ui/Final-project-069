# Comprehensive API path fix for all modules
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Fixing ALL API Paths in All Modules" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$modulesPath = "C:\xampp\htdocs\waqar\modules"
$totalFixed = 0

# Define API mappings for each module
$apiMappings = @{
    'auth' = @{
        'api/login.php' = './login-api.php'
        'api/register.php' = './register-api.php'
        'api/logout.php' = './logout-api.php'
        'api/unlock_account.php' = './unlock-account-api.php'
    }
    'shop' = @{
        'api/medicines.php' = '../medicines/api.php'
        'api/medicine_suggestions.php' = '../medicines/suggestions-api.php'
        'api/cart.php' = './cart-api.php'
        'api/track_order.php' = './track-order-api.php'
        'api/reviews.php' = './reviews-api.php'
    }
    'dashboard' = @{
        'api/dashboard.php' = './api.php'
        'api/stats.php' = './stats-api.php'
    }
    'medicines' = @{
        'api/medicines.php' = './api.php'
        'api/medicine_suggestions.php' = './suggestions-api.php'
        'api/bulk_import.php' = './bulk-import-api.php'
        'api/export_medicines.php' = './export-api.php'
    }
    'orders' = @{
        'api/orders.php' = './api.php'
        'api/orders_enhanced.php' = './enhanced-api.php'
    }
    'customers' = @{
        'api/customers.php' = './api.php'
        'api/export_customers.php' = './export-api.php'
        'api/bulk_customer_import.php' = './bulk-import-api.php'
    }
    'billing' = @{
        'api/billing.php' = './api.php'
        'api/get_bill.php' = './get-bill-api.php'
        'api/search_bills.php' = './search-api.php'
    }
    'stock' = @{
        'api/stock_check.php' = './api.php'
        'api/stock_management.php' = './management-api.php'
        'api/export_stock.php' = './export-api.php'
    }
    'reports' = @{
        'api/reports.php' = './api.php'
        'api/export_reports.php' = './export-api.php'
    }
    'settings' = @{
        'api/settings.php' = './api.php'
    }
    'backup' = @{
        'api/backup.php' = './api.php'
    }
}

# Process each module
foreach ($moduleName in $apiMappings.Keys) {
    $modulePath = Join-Path $modulesPath $moduleName
    
    if (Test-Path $modulePath) {
        $htmlFiles = Get-ChildItem -Path $modulePath -Filter "*.html" -ErrorAction SilentlyContinue
        
        if ($htmlFiles.Count -gt 0) {
            Write-Host "Processing $moduleName module..." -ForegroundColor Yellow
            
            foreach ($file in $htmlFiles) {
                $content = Get-Content $file.FullName -Raw
                $originalContent = $content
                $fileChanged = $false
                
                # Apply all mappings for this module
                foreach ($oldPath in $apiMappings[$moduleName].Keys) {
                    $newPath = $apiMappings[$moduleName][$oldPath]
                    
                    # Fix various API call patterns
                    # Pattern 1: fetch('api/...
                    $content = $content -replace "fetch\(['""]$oldPath", "fetch('$newPath"
                    $content = $content -replace "fetch\(``$oldPath", "fetch(``$newPath"
                    
                    # Pattern 2: url: 'api/...
                    $content = $content -replace "url:\s*['""]$oldPath", "url: '$newPath"
                    
                    # Pattern 3: ../api/...
                    $escapedOldPath = $oldPath -replace '\?', '\?'
                    $content = $content -replace "\.\./api/([^'""\s\)]+)", "../`$1"
                }
                
                # Check if content changed
                if ($content -ne $originalContent) {
                    Set-Content -Path $file.FullName -Value $content -NoNewline
                    Write-Host "  Fixed: $($file.Name)" -ForegroundColor Green
                    $totalFixed++
                    $fileChanged = $true
                }
            }
        }
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Total files fixed: $totalFixed" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
