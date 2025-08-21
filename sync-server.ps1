param(
    [string]$ServerHost = "newbvote2025s.online",
    [string]$Username = "",
    [string]$ServerPath = "/home/newbvote2025s.online/public_html",
    [switch]$DryRun = $false
)

$Green = "Green"
$Red = "Red"
$Yellow = "Yellow"
$Cyan = "Cyan"

Write-Host "=== SCRIPT DONG BO DU AN LEN SERVER ===" -ForegroundColor $Cyan
Write-Host "Server: $ServerHost" -ForegroundColor $Green
Write-Host "Server path: $ServerPath" -ForegroundColor $Green

if ($Username -eq "") {
    $Username = Read-Host "Nhap username SSH"
}

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
    "deploy-to-server.ps1",
    "deploy-to-server-fixed.ps1",
    "sync-server.ps1",
    "DEPLOYMENT.md",
    "deploy.lnk"
)

Write-Host "`nKiem tra ket noi SSH..." -ForegroundColor $Yellow
ssh -o ConnectTimeout=10 -o BatchMode=yes "$Username@$ServerHost" "echo 'SSH OK'" 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Loi: Khong the ket noi SSH!" -ForegroundColor $Red
    Write-Host "Kiem tra server, username va SSH key" -ForegroundColor $Yellow
    exit 1
}

Write-Host "Ket noi SSH thanh cong!" -ForegroundColor $Green

$BackupDir = "/home/$Username/backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
Write-Host "`nTao backup tren server..." -ForegroundColor $Yellow
ssh "$Username@$ServerHost" "mkdir -p $BackupDir && cp -r $ServerPath/* $BackupDir/ 2>/dev/null || true"
Write-Host "Backup tai: $BackupDir" -ForegroundColor $Green

Write-Host "`nChuẩn bi danh sach file..." -ForegroundColor $Yellow
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

Write-Host "Tim thay $($fileList.Count) file can dong bo" -ForegroundColor $Green

if ($DryRun) {
    Write-Host "`nDRY RUN - Danh sach file se duoc copy:" -ForegroundColor $Yellow
    $fileList | ForEach-Object { 
        $relativePath = $_.FullName.Substring((Get-Location).Path.Length + 1)
        Write-Host "  $relativePath" -ForegroundColor $Cyan
    }
    Write-Host "`nDRY RUN hoan thanh" -ForegroundColor $Yellow
    Write-Host "Chay lai khong co -DryRun de thuc hien dong bo" -ForegroundColor $Yellow
} else {
    Write-Host "`nBat dau dong bo file..." -ForegroundColor $Yellow
    
    ssh "$Username@$ServerHost" "find $ServerPath -type f ! -name '.env' -delete 2>/dev/null || true"
    
    $count = 0
    foreach ($file in $fileList) {
        $count++
        $relativePath = $file.FullName.Substring((Get-Location).Path.Length + 1)
        $serverFilePath = "$ServerPath/$($relativePath -replace '\\', '/')"
        $serverDir = Split-Path $serverFilePath -Parent
        
        ssh "$Username@$ServerHost" "mkdir -p '$serverDir'" 2>$null
        scp "$($file.FullName)" "$Username@${ServerHost}:$serverFilePath" 2>$null
        
        Write-Progress -Activity "Dang copy file" -Status "$relativePath" -PercentComplete (($count / $fileList.Count) * 100)
    }
    
    Write-Host "`nThiet lap quyen file..." -ForegroundColor $Yellow
    ssh "$Username@$ServerHost" @"
        cd $ServerPath
        find . -type f -name '*.php' -exec chmod 644 {} \; 2>/dev/null || true
        find . -type d -exec chmod 755 {} \; 2>/dev/null || true
        chmod 600 .env 2>/dev/null || true
        chmod 755 uploads/ logs/ storage/ storage/sessions/ 2>/dev/null || true
"@
    
    Write-Host "`nHoan thanh dong bo!" -ForegroundColor $Green
    Write-Host "Du an da duoc dong bo len server thanh cong!" -ForegroundColor $Green
    Write-Host "Backup luu tai: $BackupDir" -ForegroundColor $Green
    Write-Host "Website: https://$ServerHost" -ForegroundColor $Green
}
