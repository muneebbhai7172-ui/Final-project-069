# CSS and JS Extraction Helper
# This script helps extract inline styles and scripts from HTML/PHP files

param(
    [Parameter(Mandatory=$true)]
    [string]$FilePath
)

if (!(Test-Path $FilePath)) {
    Write-Host "Error: File not found: $FilePath" -ForegroundColor Red
    exit
}

Write-Host "Processing: $FilePath" -ForegroundColor Cyan
Write-Host ""

# Read file content
$content = Get-Content $FilePath -Raw

# Extract inline CSS
Write-Host "Extracting CSS..." -ForegroundColor Yellow
$cssPattern = '(?s)<style[^>]*>(.*?)</style>'
$cssMatches = [regex]::Matches($content, $cssPattern)

if ($cssMatches.Count -gt 0) {
    $cssContent = ""
    foreach ($match in $cssMatches) {
        $cssContent += $match.Groups[1].Value + "`n`n"
    }
    
    # Save CSS file
    $cssFile = $FilePath -replace '\.(html|php)$', '.css'
    $cssContent | Out-File -FilePath $cssFile -Encoding UTF8 -Force
    
    Write-Host "✓ CSS extracted to: $cssFile" -ForegroundColor Green
    Write-Host "  Found $($cssMatches.Count) style block(s)" -ForegroundColor Gray
} else {
    Write-Host "  No inline CSS found" -ForegroundColor Gray
}

Write-Host ""

# Extract inline JavaScript
Write-Host "Extracting JavaScript..." -ForegroundColor Yellow
$jsPattern = '(?s)<script(?![^>]*src=)[^>]*>(.*?)</script>'
$jsMatches = [regex]::Matches($content, $jsPattern)

if ($jsMatches.Count -gt 0) {
    $jsContent = ""
    $scriptCount = 0
    
    foreach ($match in $jsMatches) {
        $scriptContent = $match.Groups[1].Value.Trim()
        
        # Skip empty scripts or those with just CDN references
        if ($scriptContent.Length -gt 10 -and 
            $scriptContent -notmatch '^\s*$' -and
            $scriptContent -notmatch 'cdn\.') {
            $jsContent += $scriptContent + "`n`n"
            $scriptCount++
        }
    }
    
    if ($scriptCount -gt 0) {
        # Save JS file
        $jsFile = $FilePath -replace '\.(html|php)$', '.js'
        $jsContent | Out-File -FilePath $jsFile -Encoding UTF8 -Force
        
        Write-Host "✓ JavaScript extracted to: $jsFile" -ForegroundColor Green
        Write-Host "  Found $scriptCount script block(s)" -ForegroundColor Gray
    } else {
        Write-Host "  No significant inline JavaScript found" -ForegroundColor Gray
    }
} else {
    Write-Host "  No inline JavaScript found" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  1. Review extracted files for correctness" -ForegroundColor Gray
Write-Host "  2. Update the HTML/PHP file to reference external files" -ForegroundColor Gray
Write-Host "  3. Remove inline <style> and <script> tags" -ForegroundColor Gray
Write-Host "  4. Add <link> and <script> tags for external files" -ForegroundColor Gray
Write-Host ""

# Generate replacement snippets
Write-Host "Suggested replacements:" -ForegroundColor Cyan
Write-Host ""
Write-Host "<!-- Replace <style> tags with: -->" -ForegroundColor Green
$fileName = Split-Path $FilePath -Leaf
$moduleName = $fileName -replace '\.(html|php)$', ''
Write-Host "<link rel=`"stylesheet`" href=`"./$moduleName.css`">" -ForegroundColor White
Write-Host ""
Write-Host "<!-- Replace <script> tags with: -->" -ForegroundColor Green
Write-Host "<script src=`"./$moduleName.js`"></script>" -ForegroundColor White
Write-Host ""
