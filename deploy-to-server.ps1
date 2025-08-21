# Script đồng bộ dự án lên server
# Sử dụng: .\deploy-to-server.ps1

param(
    [string]$ServerHost = "newbvote2025s.online",
    [string]$Username = "",
    [string]$ServerPath = "/home/newbvote2025s.online/public_html",
    [string]$LocalPath = ".",
    [switch]$DryRun = $false
)

# Màu sắc cho output
$Green = "Green"
$Red = "Red"
$Yellow = "Yellow"
$Cyan = "Cyan"

Write-Host "=== SCRIPT ĐỒNG BỘ DỰ ÁN LÊN SERVER ===" -ForegroundColor $Cyan
Write-Host "Server: $ServerHost" -ForegroundColor $Green
Write-Host "Đường dẫn server: $ServerPath" -ForegroundColor $Green
Write-Host "Đường dẫn local: $LocalPath" -ForegroundColor $Green

if ($Username -eq "") {
    $Username = Read-Host "Nhập username SSH"
}

# Danh sách file/folder cần loại trừ
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
    "DEPLOYMENT.md",
    "deploy.lnk"
)

Write-Host "`n=== KIỂM TRA KẾT NỐI SSH ===" -ForegroundColor $Yellow
$sshTest = ssh -o ConnectTimeout=10 -o BatchMode=yes "$Username@$ServerHost" "echo 'SSH connection successful'"
if ($LASTEXITCODE -ne 0) {
    Write-Host "Lỗi: Không thể kết nối SSH tới server!" -ForegroundColor $Red
    Write-Host "Vui lòng kiểm tra:" -ForegroundColor $Yellow
    Write-Host "1. Địa chỉ server: $ServerHost" -ForegroundColor $Yellow
    Write-Host "2. Username: $Username" -ForegroundColor $Yellow
    Write-Host "3. SSH key hoặc password" -ForegroundColor $Yellow
    exit 1
}

Write-Host "✓ Kết nối SSH thành công!" -ForegroundColor $Green

# Tạo thư mục backup trên server
$BackupDir = "/home/$Username/backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
Write-Host "`n=== TẠO BACKUP TRÊN SERVER ===" -ForegroundColor $Yellow
ssh "$Username@$ServerHost" "mkdir -p $BackupDir && cp -r $ServerPath/* $BackupDir/ 2>/dev/null || true"
Write-Host "✓ Đã tạo backup tại: $BackupDir" -ForegroundColor $Green

# Kiểm tra rsync
$hasRsync = Get-Command rsync -ErrorAction SilentlyContinue
if ($hasRsync) {
    Write-Host "`n=== SỬ DỤNG RSYNC ĐỂ ĐỒNG BỘ ===" -ForegroundColor $Yellow
    
    $excludeArgs = $ExcludeList | ForEach-Object { "--exclude=$_" }
    $rsyncCmd = "rsync -avz --delete $($excludeArgs -join ' ') ./ $Username@${ServerHost}:$ServerPath/"
    
    if ($DryRun) {
        $rsyncCmd += " --dry-run"
        Write-Host "DRY RUN MODE - Không thực sự copy file" -ForegroundColor $Yellow
    }
    
    Write-Host "Lệnh rsync: $rsyncCmd" -ForegroundColor $Cyan
    Invoke-Expression $rsyncCmd
} else {
    Write-Host "`n=== SỬ DỤNG SCP ĐỂ ĐỒNG BỘ ===" -ForegroundColor $Yellow
    
    # Tạo file tạm chứa danh sách file cần copy
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
    
    Write-Host "Tìm thấy $($fileList.Count) file cần đồng bộ" -ForegroundColor $Green
    
    if (-not $DryRun) {
        # Xóa nội dung cũ trên server (trừ .env)
        ssh "$Username@$ServerHost" "find $ServerPath -type f ! -name '.env' -delete"
        
        # Copy từng file
        $count = 0
        foreach ($file in $fileList) {
            $count++
            $relativePath = $file.FullName.Substring((Get-Location).Path.Length + 1)
            $serverFilePath = "$ServerPath/$($relativePath -replace '\\', '/')"
            $serverDir = Split-Path $serverFilePath -Parent
            
            # Tạo thư mục trên server nếu chưa có
            ssh "$Username@$ServerHost" "mkdir -p '$serverDir'"
            
            # Copy file
            scp "$($file.FullName)" "$Username@${ServerHost}:$serverFilePath"
            
            Write-Progress -Activity "Đang copy file" -Status "$relativePath" -PercentComplete (($count / $fileList.Count) * 100)
        }
    } else {
        Write-Host "DRY RUN - Danh sách file sẽ được copy:" -ForegroundColor $Yellow
        $fileList | ForEach-Object { Write-Host "  $($_.FullName.Substring((Get-Location).Path.Length + 1))" }
    }
}

if (-not $DryRun) {
    Write-Host "`n=== THIẾT LẬP QUYỀN FILE TRÊN SERVER ===" -ForegroundColor $Yellow
    ssh "$Username@$ServerHost" @"
        cd $ServerPath
        find . -type f -name '*.php' -exec chmod 644 {} \;
        find . -type d -exec chmod 755 {} \;
        chmod 600 .env 2>/dev/null || true
        chmod 755 uploads/ logs/ storage/ storage/sessions/ 2>/dev/null || true
"@
    Write-Host "✓ Đã thiết lập quyền file" -ForegroundColor $Green
    
    Write-Host "`n=== HOÀN THÀNH ĐỒNG BỘ ===" -ForegroundColor $Green
    Write-Host "✓ Dự án đã được đồng bộ lên server thành công!" -ForegroundColor $Green
    Write-Host "✓ Backup được lưu tại: $BackupDir" -ForegroundColor $Green
    Write-Host "✓ Website: https://$ServerHost" -ForegroundColor $Green
} else {
    Write-Host "`n=== DRY RUN HOÀN THÀNH ===" -ForegroundColor $Yellow
    Write-Host "Chạy lại script không có tham số -DryRun để thực hiện đồng bộ thật" -ForegroundColor $Yellow
}
