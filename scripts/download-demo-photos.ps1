<#
.SYNOPSIS
    Downloads the demo photos (Unsplash / Pexels licence only) into
    backend/database/seeders/demo-photos, as WebP, then lists what to commit.

.DESCRIPTION
    The exact list is backend/database/seeders/demo-photos/photos.json; credits
    are written to CREDITS.md next to it. The work is done by
    "php artisan demo:photos" (downloads, resizes to at most 1280 px, WebP
    quality ~75, keeps the whole set under about 8 MB). Safe to run again:
    photos already downloaded are skipped.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\download-demo-photos.ps1
    powershell -ExecutionPolicy Bypass -File scripts\download-demo-photos.ps1 -Force
#>
param(
    [switch]$Force,
    [string]$Only = ''
)

$ErrorActionPreference = 'Stop'
$backend = Join-Path (Split-Path -Parent $PSScriptRoot) 'backend'

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host 'PHP was not found. Open the Laragon terminal (or add PHP to PATH) and run this again.' -ForegroundColor Red
    exit 1
}
$webp = php -r "echo function_exists('imagewebp') ? 'yes' : 'no';"
if ($webp -ne 'yes') {
    Write-Host 'PHP GD with WebP support is needed: enable "extension=gd" in php.ini and run this again.' -ForegroundColor Red
    exit 1
}

$arguments = @('artisan', 'demo:photos')
if ($Force) { $arguments += '--force' }
if ($Only -ne '') { $arguments += "--only=$Only" }

Push-Location $backend
try {
    & php @arguments
    $code = $LASTEXITCODE
} finally {
    Pop-Location
}

Write-Host ''
Write-Host 'Next:' -ForegroundColor Cyan
Write-Host '  git add backend/database/seeders/demo-photos'
Write-Host '  git commit -m "Add demo photos"'
Write-Host '  cd backend; php artisan migrate:fresh --seed --seeder=DemoSeeder'
exit $code
