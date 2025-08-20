# Upload files to server using various methods
param(
    [string]$Method = "manual",  # manual, winscp, ftp
    [string]$Server = "newbvote2025s.online",
    [string]$Username = "",
    [string]$Password = "",
    [string]$RemotePath = "/public_html"
)

$Green = "Green"
$Red = "Red"
$Yellow = "Yellow"
$Cyan = "Cyan"

Write-Host "=== UPLOAD FILES TO SERVER ===" -ForegroundColor $Cyan
Write-Host "Server: $Server" -ForegroundColor $Green
Write-Host "Method: $Method" -ForegroundColor $Green

# Create file list
$files = @(
    "index.php",
    "router.php", 
    "routes.php",
    ".htaccess",
    ".env"
)

$folders = @(
    "api",
    "assets", 
    "config",
    "controllers",
    "includes",
    "src",
    "views",
    "public"
)

Write-Host "`nFiles to upload:" -ForegroundColor $Yellow
foreach ($file in $files) {
    if (Test-Path $file) {
        Write-Host "  ✓ $file" -ForegroundColor $Green
    } else {
        Write-Host "  ✗ $file (missing)" -ForegroundColor $Red
    }
}

Write-Host "`nFolders to upload:" -ForegroundColor $Yellow
foreach ($folder in $folders) {
    if (Test-Path $folder) {
        $count = (Get-ChildItem -Recurse $folder -File).Count
        Write-Host "  ✓ $folder ($count files)" -ForegroundColor $Green
    } else {
        Write-Host "  ✗ $folder (missing)" -ForegroundColor $Red
    }
}

switch ($Method) {
    "manual" {
        Write-Host "`n=== MANUAL UPLOAD INSTRUCTIONS ===" -ForegroundColor $Cyan
        Write-Host "1. Open FTP client (FileZilla, WinSCP, etc.)" -ForegroundColor $Yellow
        Write-Host "2. Connect to: $Server" -ForegroundColor $Yellow
        Write-Host "3. Upload to: $RemotePath" -ForegroundColor $Yellow
        Write-Host "4. Upload these files and folders listed above" -ForegroundColor $Yellow
        Write-Host "`nOR use cPanel File Manager:" -ForegroundColor $Cyan
        Write-Host "1. Login to cPanel" -ForegroundColor $Yellow
        Write-Host "2. Open File Manager" -ForegroundColor $Yellow
        Write-Host "3. Navigate to public_html" -ForegroundColor $Yellow
        Write-Host "4. Upload files via web interface" -ForegroundColor $Yellow
    }
    
    "winscp" {
        Write-Host "`n=== WINSCP SCRIPT ===" -ForegroundColor $Cyan
        $winscp = @"
open sftp://$Username@$Server
cd $RemotePath
put index.php
put router.php
put routes.php
put .htaccess
put .env
put -r api/
put -r assets/
put -r config/
put -r controllers/
put -r includes/
put -r src/
put -r views/
put -r public/
exit
"@
        Write-Host $winscp -ForegroundColor $Green
        Write-Host "`nSave this as upload.txt and run:" -ForegroundColor $Yellow
        Write-Host "winscp.exe /script=upload.txt" -ForegroundColor $Cyan
    }
    
    "ftp" {
        if ($Username -eq "") {
            $Username = Read-Host "Enter FTP username"
        }
        if ($Password -eq "") {
            $securePassword = Read-Host "Enter FTP password" -AsSecureString
            $Password = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword))
        }
        
        Write-Host "`n=== FTP UPLOAD ===" -ForegroundColor $Cyan
        
        try {
            # Test FTP connection
            $ftpRequest = [System.Net.FtpWebRequest]::Create("ftp://$Server/")
            $ftpRequest.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectory
            $ftpRequest.Credentials = New-Object System.Net.NetworkCredential($Username, $Password)
            
            $response = $ftpRequest.GetResponse()
            Write-Host "FTP connection successful!" -ForegroundColor $Green
            $response.Close()
            
            # Upload files
            foreach ($file in $files) {
                if (Test-Path $file) {
                    Write-Host "Uploading $file..." -ForegroundColor $Yellow
                    $ftpRequest = [System.Net.FtpWebRequest]::Create("ftp://$Server$RemotePath/$file")
                    $ftpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
                    $ftpRequest.Credentials = New-Object System.Net.NetworkCredential($Username, $Password)
                    
                    $fileContent = [System.IO.File]::ReadAllBytes($file)
                    $ftpRequest.ContentLength = $fileContent.Length
                    
                    $requestStream = $ftpRequest.GetRequestStream()
                    $requestStream.Write($fileContent, 0, $fileContent.Length)
                    $requestStream.Close()
                    
                    $response = $ftpRequest.GetResponse()
                    Write-Host "  ✓ $file uploaded" -ForegroundColor $Green
                    $response.Close()
                }
            }
        }
        catch {
            Write-Host "FTP Error: $($_.Exception.Message)" -ForegroundColor $Red
            Write-Host "Try manual upload method instead" -ForegroundColor $Yellow
        }
    }
}

Write-Host "`n=== NEXT STEPS ===" -ForegroundColor $Cyan
Write-Host "1. Verify files uploaded correctly" -ForegroundColor $Yellow
Write-Host "2. Check website: https://$Server" -ForegroundColor $Yellow
Write-Host "3. Test functionality" -ForegroundColor $Yellow
