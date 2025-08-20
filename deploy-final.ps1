# Deploy to Production Server
# Usage: .\deploy-final.ps1

param(
    [string]$ServerHost = "newbvote2025s.online",
    [string]$Username = "root",
    [string]$ServerPath = "/var/www/html/newbvote2025s-deploy",
    [string]$LocalPath = ".",
    [switch]$DryRun = $false
)

Write-Host "=== DEPLOY TO PRODUCTION SERVER ===" -ForegroundColor Cyan
Write-Host "Server: $ServerHost" -ForegroundColor Green
Write-Host "Server Path: $ServerPath" -ForegroundColor Green
Write-Host "Username: $Username" -ForegroundColor Green

# Files to exclude
$ExcludeList = @(
    ".git",
    ".gitignore",
    "node_modules",
    "vendor",
    "composer.lock",
    "*.log",
    "logs/*",
    "storage/sessions/*",
    ".env.example",
    "deploy-*.ps1",
    "DEPLOYMENT.md",
    "deploy.lnk",
    "winscp-*.txt"
)

Write-Host "`n=== CHECKING SSH CONNECTION ===" -ForegroundColor Yellow
ssh -o ConnectTimeout=10 -o BatchMode=yes "$Username@$ServerHost" "echo 'SSH connection successful'" 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Cannot connect to server via SSH!" -ForegroundColor Red
    Write-Host "Please check:" -ForegroundColor Yellow
    Write-Host "1. Server address: $ServerHost" -ForegroundColor Yellow
    Write-Host "2. Username: $Username" -ForegroundColor Yellow
    Write-Host "3. SSH key or password" -ForegroundColor Yellow
    exit 1
}

Write-Host "✓ SSH connection successful!" -ForegroundColor Green

# Create backup on server
$BackupDir = "/tmp/backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
Write-Host "`n=== CREATING BACKUP ON SERVER ===" -ForegroundColor Yellow
ssh "$Username@$ServerHost" "mkdir -p $BackupDir"
ssh "$Username@$ServerHost" "cp -r $ServerPath/* $BackupDir/ 2>/dev/null"
Write-Host "✓ Backup created at: $BackupDir" -ForegroundColor Green

# Prepare file list
Write-Host "`n=== PREPARING FILE LIST ===" -ForegroundColor Yellow
$fileList = Get-ChildItem -Recurse -File | Where-Object {
    $file = $_
    $shouldExclude = $false
    foreach ($exclude in $ExcludeList) {
        if ($file.FullName -like "*$exclude*") {
            $shouldExclude = $true
            break
        }
    }
    return -not $shouldExclude
}

Write-Host "Found $($fileList.Count) files to sync" -ForegroundColor Green

if ($DryRun) {
    Write-Host "`n=== DRY RUN - FILES TO BE COPIED ===" -ForegroundColor Yellow
    $fileList | ForEach-Object {
        $relativePath = $_.FullName.Substring((Get-Location).Path.Length + 1)
        Write-Host "  $relativePath" -ForegroundColor Cyan
    }
    Write-Host "`n=== DRY RUN COMPLETED ===" -ForegroundColor Yellow
    Write-Host "Run script without -DryRun parameter to perform actual sync" -ForegroundColor Yellow
} else {
    Write-Host "`n=== STARTING FILE SYNC ===" -ForegroundColor Yellow

    # Remove old content on server (except .env)
    ssh "$Username@$ServerHost" "find $ServerPath -type f ! -name '.env' -delete 2>/dev/null"

    # Copy files
    $count = 0
    foreach ($file in $fileList) {
        $count++
        $relativePath = $file.FullName.Substring((Get-Location).Path.Length + 1)
        $serverFilePath = "$ServerPath/$($relativePath -replace '\\', '/')"
        $serverDir = Split-Path $serverFilePath -Parent

        # Create directory on server if needed
        ssh "$Username@$ServerHost" "mkdir -p '$serverDir'" 2>$null

        # Copy file
        scp "$($file.FullName)" "$Username@${ServerHost}:$serverFilePath" 2>$null

        Write-Progress -Activity "Copying files" -Status "$relativePath" -PercentComplete (($count / $fileList.Count) * 100)
    }

    Write-Host "`n=== SETTING FILE PERMISSIONS ON SERVER ===" -ForegroundColor Yellow
    ssh "$Username@$ServerHost" "cd $ServerPath"
    ssh "$Username@$ServerHost" "find . -type f -name '*.php' -exec chmod 644 {} \; 2>/dev/null"
    ssh "$Username@$ServerHost" "find . -type d -exec chmod 755 {} \; 2>/dev/null"
    ssh "$Username@$ServerHost" "chmod 600 .env 2>/dev/null"
    ssh "$Username@$ServerHost" "chmod 755 uploads/ logs/ storage/ storage/sessions/ 2>/dev/null"

    Write-Host "✓ File permissions set" -ForegroundColor Green

    Write-Host "`n=== DEPLOYMENT COMPLETED ===" -ForegroundColor Green
    Write-Host "✓ Project deployed to server successfully!" -ForegroundColor Green
    Write-Host "✓ Backup saved at: $BackupDir" -ForegroundColor Green
    Write-Host "✓ Website: https://$ServerHost" -ForegroundColor Green
}
