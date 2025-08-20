param(
    [string]$FtpHost = "newbvote2025s.online",
    [string]$FtpUser = "",
    [string]$FtpPass = "",
    [string]$RemotePath = "/public_html",
    [switch]$DryRun = $false
)

$Green = "Green"
$Red = "Red"
$Yellow = "Yellow"
$Cyan = "Cyan"

Write-Host "=== DEPLOY VIA FTP ===" -ForegroundColor $Cyan

if ($FtpUser -eq "") {
    $FtpUser = Read-Host "Nhap FTP username"
}

if ($FtpPass -eq "") {
    $FtpPass = Read-Host "Nhap FTP password" -AsSecureString
    $FtpPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($FtpPass))
}

# Danh sách file cần upload
$ExcludeList = @(
    ".git*",
    "node_modules",
    "vendor",
    "composer.lock",
    "*.log",
    "logs/*",
    "storage/sessions/*",
    ".env.example",
    "deploy-*.ps1",
    "sync-*.ps1",
    "DEPLOYMENT.md",
    "*.lnk"
)

function Upload-FileViaFTP {
    param($LocalFile, $RemoteFile, $FtpHost, $FtpUser, $FtpPass)
    
    try {
        $ftpRequest = [System.Net.FtpWebRequest]::Create("ftp://$FtpHost$RemoteFile")
        $ftpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $ftpRequest.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
        $ftpRequest.UseBinary = $true
        
        $fileContent = [System.IO.File]::ReadAllBytes($LocalFile)
        $ftpRequest.ContentLength = $fileContent.Length
        
        $requestStream = $ftpRequest.GetRequestStream()
        $requestStream.Write($fileContent, 0, $fileContent.Length)
        $requestStream.Close()
        
        $response = $ftpRequest.GetResponse()
        $response.Close()
        return $true
    }
    catch {
        Write-Host "Loi upload $LocalFile : $($_.Exception.Message)" -ForegroundColor $Red
        return $false
    }
}

Write-Host "Chuan bi danh sach file..." -ForegroundColor $Yellow
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

Write-Host "Tim thay $($fileList.Count) file can upload" -ForegroundColor $Green

if ($DryRun) {
    Write-Host "`nDRY RUN - Danh sach file se duoc upload:" -ForegroundColor $Yellow
    $fileList | ForEach-Object { 
        $relativePath = $_.FullName.Substring((Get-Location).Path.Length + 1)
        Write-Host "  $relativePath" -ForegroundColor $Cyan
    }
    Write-Host "`nDRY RUN hoan thanh" -ForegroundColor $Yellow
} else {
    Write-Host "`nBat dau upload qua FTP..." -ForegroundColor $Yellow
    
    $successCount = 0
    $count = 0
    
    foreach ($file in $fileList) {
        $count++
        $relativePath = $_.FullName.Substring((Get-Location).Path.Length + 1)
        $remoteFile = "$RemotePath/$($relativePath -replace '\\', '/')"
        
        Write-Progress -Activity "Dang upload file" -Status "$relativePath" -PercentComplete (($count / $fileList.Count) * 100)
        
        if (Upload-FileViaFTP -LocalFile $file.FullName -RemoteFile $remoteFile -FtpHost $FtpHost -FtpUser $FtpUser -FtpPass $FtpPass) {
            $successCount++
        }
    }
    
    Write-Host "`nKet qua upload:" -ForegroundColor $Green
    Write-Host "Thanh cong: $successCount/$($fileList.Count) file" -ForegroundColor $Green
    Write-Host "Website: http://$FtpHost" -ForegroundColor $Green
}
