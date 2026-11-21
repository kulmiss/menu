<#
  Deploy the live site from VS Code (Terminal > Run Task > "Deploy live site", or the button).
  Owner, 2026-11-21: the same one-click way as the other projects, for this repo's layout.

  What it does:
    1. checks you are on the deploy branch (main), with nothing uncommitted, and up to date with GitHub;
    2. builds the admin app (frontend -> backend/public/app; the server has no Node);
    3. uploads it with the server script (scp), then runs that script over SSH:
       git pull, composer install, admin app, migrate, caches, menu pages.

  Settings: deploy.config.json in the repo root (copy deploy.config.example.json; never committed).
  Needs: git, pnpm, tar, scp and ssh (all on Windows 10/11 with Laragon), and your SSH key on the server.

  Options:  -DryRun     show what would happen; changes nothing here or on the server
            -SkipBuild  upload the admin app as it is in backend/public/app (already built)
#>
param(
    [switch]$DryRun,
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $root

function Step([string]$text) { Write-Host ''; Write-Host "== $text" -ForegroundColor Cyan }
function Fail([string]$text) { Write-Host ''; Write-Host "!! $text" -ForegroundColor Red; exit 1 }
function Run([string]$exe, [string[]]$argList) {
    Write-Host ("   > {0} {1}" -f $exe, ($argList -join ' ')) -ForegroundColor DarkGray
    if ($DryRun) { return }
    & $exe @argList
    if ($LASTEXITCODE -ne 0) { Fail ("{0} stopped with exit code {1}." -f [System.IO.Path]::GetFileNameWithoutExtension($exe), $LASTEXITCODE) }
}
function GitOut([string[]]$argList) {
    # Windows PowerShell 5.1 turns any git warning on stderr into an error when "Stop" is on.
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { $out = & git @argList 2>&1 } finally { $ErrorActionPreference = $previous }
    if ($LASTEXITCODE -ne 0) { Fail ("git {0}: {1}" -f ($argList -join ' '), ($out -join ' ')) }
    return ($out -join "`n").Trim()
}

# Windows: use Windows' own tar, scp and ssh (System32). Git Bash's versions read "C:\..." as a server name "C".
function Tool([string]$name, [string]$windowsPath) {
    if ($env:SystemRoot) {
        $path = Join-Path $env:SystemRoot $windowsPath
        if (Test-Path $path) { return $path }
    }
    return $name
}
$tar = Tool 'tar' 'System32\tar.exe'
$scp = Tool 'scp' 'System32\OpenSSH\scp.exe'
$ssh = Tool 'ssh' 'System32\OpenSSH\ssh.exe'

# --- Settings ---------------------------------------------------------------
$configPath = Join-Path $root 'deploy.config.json'
if (-not (Test-Path $configPath)) {
    Fail "No deploy.config.json. Copy deploy.config.example.json to deploy.config.json and fill in your server."
}
$config = Get-Content $configPath -Raw | ConvertFrom-Json
foreach ($key in @('host', 'user', 'path')) {
    if (-not $config.$key -or "$($config.$key)" -match '^(example|CHANGE)') { Fail "deploy.config.json: fill in `"$key`"." }
}
$branch   = if ($config.branch)   { "$($config.branch)" }   else { 'main' }
$php      = if ($config.php)      { "$($config.php)" }      else { 'php' }
$composer = if ($config.composer) { "$($config.composer)" } else { 'composer' }
$port     = if ($config.port)     { "$($config.port)" }     else { '22' }
$target   = "$($config.user)@$($config.host)"
foreach ($value in @($config.path, $branch, $php, $composer)) {
    if ("$value" -match "['""``]") { Fail "deploy.config.json: no quotes in the values ($value)." }
}

Write-Host ("Deploying '{0}' to {1}:{2}{3}" -f $branch, $target, $config.path, $(if ($DryRun) { '  (dry run: nothing changes)' } else { '' })) -ForegroundColor Yellow

# --- 1. Git -----------------------------------------------------------------
Step "Check the code"
$current = GitOut @('rev-parse', '--abbrev-ref', 'HEAD')
if ($current -ne $branch) { Fail "You are on '$current'. The live site runs '$branch': git checkout $branch; git pull" }
$dirty = GitOut @('status', '--porcelain', '--untracked-files=no')
if ($dirty) { Fail "You have uncommitted changes. Commit them (through a pull request) or stash them first." }
GitOut @('fetch', '--quiet', 'origin', $branch) | Out-Null
$local  = GitOut @('rev-parse', 'HEAD')
$remote = GitOut @('rev-parse', "origin/$branch")
if ($local -ne $remote) {
    $base = GitOut @('merge-base', 'HEAD', "origin/$branch")
    if ($base -eq $local) {
        Run 'git' @('pull', '--ff-only', '--quiet', 'origin', $branch)
    } else {
        Fail "Your '$branch' has commits that are not on GitHub. The server deploys what is on GitHub: push them (through a pull request) first."
    }
}
Write-Host ("   {0}" -f (GitOut @('log', '--oneline', '-1', "origin/$branch")))

# --- 2. Admin app -----------------------------------------------------------
if ($SkipBuild) {
    Step "Admin app: using the build already in backend/public/app"
} else {
    Step "Build the admin app"
    Push-Location ([System.IO.Path]::Combine($root, 'frontend'))
    try {
        Run 'pnpm' @('install', '--frozen-lockfile')
        Run 'pnpm' @('build')
    } finally { Pop-Location }
}
$appDir = [System.IO.Path]::Combine($root, 'backend', 'public', 'app')
if (-not $DryRun -and -not (Test-Path (Join-Path $appDir 'index.html'))) { Fail "backend/public/app/index.html is missing: the admin app is not built." }

# --- 3. Upload --------------------------------------------------------------
Step "Upload"
$archive = Join-Path ([System.IO.Path]::GetTempPath()) 'menu-app.tgz'
if (-not $DryRun -and (Test-Path $archive)) { Remove-Item $archive -Force }
Run $tar @('-czf', $archive, '-C', [System.IO.Path]::Combine($root, 'backend', 'public'), 'app')
$serverScript = [System.IO.Path]::Combine($root, 'scripts', 'deploy', 'menu-deploy.sh')
Run $scp @('-P', $port, '-q', $archive, $serverScript, "${target}:")

# --- 4. Update the server ---------------------------------------------------
Step "Update the server"
Run $ssh @('-p', $port, $target, "bash ~/menu-deploy.sh '$($config.path)' '$branch' '$php' '$composer'")

if (-not $DryRun -and (Test-Path $archive)) { Remove-Item $archive -Force }
Write-Host ''
if ($DryRun) {
    Write-Host 'Dry run finished: nothing was changed.' -ForegroundColor Yellow
} else {
    Write-Host 'Deployed. Open the site and check it (a menu, the admin, /up).' -ForegroundColor Green
}
