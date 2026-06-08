# Waqar Drug House - Project Reorganization Script
# This script helps automate the file migration process

Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "Waqar Drug House - Project Reorganization" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# Set base path
$basePath = "C:\xampp\htdocs\waqar"
Set-Location $basePath

Write-Host "Current Directory: $basePath" -ForegroundColor Yellow
Write-Host ""

# Function to create backup
function Create-Backup {
    Write-Host "Creating backup..." -ForegroundColor Green
    $backupName = "backup_" + (Get-Date -Format "yyyyMMdd_HHmmss")
    $backupPath = Join-Path $basePath "backups\project_backups\$backupName"
    
    if (!(Test-Path $backupPath)) {
        New-Item -ItemType Directory -Path $backupPath -Force | Out-Null
    }
    
    # Copy important folders
    Copy-Item "$basePath\admin" -Destination "$backupPath\admin" -Recurse -Force
    Copy-Item "$basePath\api" -Destination "$backupPath\api" -Recurse -Force
    Copy-Item "$basePath\css" -Destination "$backupPath\css" -Recurse -Force
    Copy-Item "$basePath\js" -Destination "$backupPath\js" -Recurse -Force
    
    Write-Host "✓ Backup created at: $backupPath" -ForegroundColor Green
    Write-Host ""
}

# Function to copy and convert file
function Copy-AndConvert {
    param(
        [string]$source,
        [string]$destination
    )
    
    if (Test-Path $source) {
        # Create destination directory if it doesn't exist
        $destDir = Split-Path $destination -Parent
        if (!(Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        
        # Copy file
        Copy-Item $source -Destination $destination -Force
        
        # If it's an HTML file, rename to index.php
        if ($destination -match "\.html$") {
            $newDest = $destination -replace "\.html$", ".php"
            Rename-Item $destination -NewName (Split-Path $newDest -Leaf) -Force
            return $newDest
        }
        
        return $destination
    } else {
        Write-Host "  ✗ Source not found: $source" -ForegroundColor Red
        return $null
    }
}

# Ask user if they want to proceed
Write-Host "This script will:" -ForegroundColor Yellow
Write-Host "  1. Create a backup of current files" -ForegroundColor Yellow
Write-Host "  2. Create new modular folder structure" -ForegroundColor Yellow
Write-Host "  3. Copy files to new locations" -ForegroundColor Yellow
Write-Host "  4. Convert .html files to .php" -ForegroundColor Yellow
Write-Host ""
Write-Host "⚠️  WARNING: This will modify your project structure!" -ForegroundColor Red
Write-Host ""

$response = Read-Host "Do you want to proceed? (yes/no)"

if ($response -ne "yes") {
    Write-Host "Operation cancelled." -ForegroundColor Yellow
    exit
}

Write-Host ""
Write-Host "Starting migration process..." -ForegroundColor Cyan
Write-Host ""

# Step 1: Create backup
Create-Backup

# Step 2: Verify folders exist
Write-Host "Verifying folder structure..." -ForegroundColor Green
$folders = @(
    "modules\dashboard",
    "modules\medicines",
    "modules\orders",
    "modules\customers",
    "modules\billing",
    "modules\stock",
    "modules\reports",
    "modules\settings",
    "modules\backup",
    "shared\css",
    "shared\js",
    "shared\components"
)

foreach ($folder in $folders) {
    $fullPath = Join-Path $basePath $folder
    if (!(Test-Path $fullPath)) {
        New-Item -ItemType Directory -Path $fullPath -Force | Out-Null
    }
}
Write-Host "✓ Folder structure verified" -ForegroundColor Green
Write-Host ""

# Step 3: Migrate Dashboard
Write-Host "Migrating Dashboard module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\dashboard.html" "$basePath\modules\dashboard\index.html"
Copy-AndConvert "$basePath\api\dashboard.php" "$basePath\modules\dashboard\api.php"
Copy-AndConvert "$basePath\api\stats.php" "$basePath\modules\dashboard\stats-api.php"
Write-Host "✓ Dashboard migrated" -ForegroundColor Green
Write-Host ""

# Step 4: Migrate Medicines
Write-Host "Migrating Medicines module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\modern-medicines.html" "$basePath\modules\medicines\index.html"
Copy-AndConvert "$basePath\api\medicines.php" "$basePath\modules\medicines\api.php"
Copy-AndConvert "$basePath\api\medicine_suggestions.php" "$basePath\modules\medicines\suggestions-api.php"
Write-Host "✓ Medicines migrated" -ForegroundColor Green
Write-Host ""

# Step 5: Migrate Orders
Write-Host "Migrating Orders module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\orders.html" "$basePath\modules\orders\index.html"
Copy-AndConvert "$basePath\api\orders.php" "$basePath\modules\orders\api.php"
Copy-AndConvert "$basePath\api\orders_enhanced.php" "$basePath\modules\orders\enhanced-api.php"
Write-Host "✓ Orders migrated" -ForegroundColor Green
Write-Host ""

# Step 6: Migrate Customers
Write-Host "Migrating Customers module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\modern-customers.html" "$basePath\modules\customers\index.html"
Copy-AndConvert "$basePath\api\customers.php" "$basePath\modules\customers\api.php"
Copy-AndConvert "$basePath\api\export_customers.php" "$basePath\modules\customers\export-api.php"
Write-Host "✓ Customers migrated" -ForegroundColor Green
Write-Host ""

# Step 7: Migrate Billing
Write-Host "Migrating Billing module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\modern-billing.html" "$basePath\modules\billing\index.html"
Copy-AndConvert "$basePath\api\billing.php" "$basePath\modules\billing\api.php"
Copy-AndConvert "$basePath\api\get_bill.php" "$basePath\modules\billing\get-bill-api.php"
Copy-AndConvert "$basePath\api\search_bills.php" "$basePath\modules\billing\search-api.php"
Write-Host "✓ Billing migrated" -ForegroundColor Green
Write-Host ""

# Step 8: Migrate Stock
Write-Host "Migrating Stock module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\stock-check.html" "$basePath\modules\stock\index.html"
Copy-AndConvert "$basePath\api\stock_check.php" "$basePath\modules\stock\api.php"
Copy-AndConvert "$basePath\api\stock_management.php" "$basePath\modules\stock\management-api.php"
Write-Host "✓ Stock migrated" -ForegroundColor Green
Write-Host ""

# Step 9: Migrate Reports
Write-Host "Migrating Reports module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\modern-reports.html" "$basePath\modules\reports\index.html"
Copy-AndConvert "$basePath\api\reports.php" "$basePath\modules\reports\api.php"
Copy-AndConvert "$basePath\api\export_reports.php" "$basePath\modules\reports\export-api.php"
Write-Host "✓ Reports migrated" -ForegroundColor Green
Write-Host ""

# Step 10: Migrate Settings
Write-Host "Migrating Settings module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\settings.html" "$basePath\modules\settings\index.html"
Copy-AndConvert "$basePath\api\settings.php" "$basePath\modules\settings\api.php"
Write-Host "✓ Settings migrated" -ForegroundColor Green
Write-Host ""

# Step 11: Migrate Backup
Write-Host "Migrating Backup module..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\admin\backup.html" "$basePath\modules\backup\index.html"
Copy-AndConvert "$basePath\api\backup.php" "$basePath\modules\backup\api.php"
Write-Host "✓ Backup migrated" -ForegroundColor Green
Write-Host ""

# Step 12: Migrate shared resources
Write-Host "Migrating shared resources..." -ForegroundColor Cyan
Copy-AndConvert "$basePath\css\components\_modern-sidebar.css" "$basePath\shared\css\sidebar.css"
Copy-AndConvert "$basePath\js\modern-sidebar.js" "$basePath\shared\js\sidebar.js"
Copy-AndConvert "$basePath\includes\admin-sidebar.php" "$basePath\shared\components\sidebar.php"
Write-Host "✓ Shared resources migrated" -ForegroundColor Green
Write-Host ""

# Create README files for each module
Write-Host "Creating module README files..." -ForegroundColor Cyan
$moduleReadme = @"
# Module README

## Files in this folder:
- **index.php** - Main page (converted from HTML)
- **api.php** - Backend API endpoints
- **{module}.css** - Module-specific styles (to be extracted)
- **{module}.js** - Module-specific JavaScript (to be extracted)

## Next Steps:
1. Extract inline CSS from index.php to {module}.css
2. Extract inline JavaScript from index.php to {module}.js
3. Update path references in index.php
4. Test functionality

## Path Updates Required:
- CSS: `../../shared/css/` for shared, `./` for module-specific
- JS: `../../shared/js/` for shared, `./` for module-specific
- API: `./api.php` for module API calls
- Components: `../../shared/components/`
"@

foreach ($folder in $folders) {
    if ($folder -match "^modules") {
        $readmePath = Join-Path $basePath "$folder\README.md"
        $moduleReadme | Out-File -FilePath $readmePath -Encoding UTF8 -Force
    }
}
Write-Host "✓ README files created" -ForegroundColor Green
Write-Host ""

# Summary
Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "Migration Complete!" -ForegroundColor Green
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "✓ Files have been copied to new structure" -ForegroundColor Green
Write-Host "✓ HTML files converted to PHP" -ForegroundColor Green
Write-Host "✓ Backup created" -ForegroundColor Green
Write-Host ""
Write-Host "⚠️  Next Manual Steps Required:" -ForegroundColor Yellow
Write-Host "  1. Extract inline CSS to {module}.css files" -ForegroundColor Yellow
Write-Host "  2. Extract inline JavaScript to {module}.js files" -ForegroundColor Yellow
Write-Host "  3. Update all path references" -ForegroundColor Yellow
Write-Host "  4. Update sidebar navigation links" -ForegroundColor Yellow
Write-Host "  5. Test each module thoroughly" -ForegroundColor Yellow
Write-Host "  6. Update API endpoint references" -ForegroundColor Yellow
Write-Host ""
Write-Host "📚 See REORGANIZATION_GUIDE.md for detailed instructions" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

